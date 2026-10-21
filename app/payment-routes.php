<?php
declare(strict_types=1);
// Cobrar una venta, aplicarle una seña, anularla (#18) y registrar la seña de una cita (#16). Autenticación y CSRF: front controller.
$deny = static function(int $status,string $message) use ($user,$page): never {
    http_response_code($status);
    render('message',compact('user','page','message')+['title'=>'Cobros']); exit;
};
if (!canEdit($user)) $deny(403,'Solo administración y recepción pueden registrar cobros.');
if (!in_array($method,['GET','POST'],true)) $deny(405,'Método no permitido.');
$errors=[]; $session=currentCashSession(); $key=bin2hex(random_bytes(16));

if ($page==='sale-pay') {
    $id=filter_var(input($_GET,'id'),FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    $sale=$id?saleRecord($id):false;
    if (!$sale) $deny(404,'Venta no encontrada.');
    $backUrl=url('sale',['id'=>$sale['id']]);
    if ($sale['status']!=='confirmed') $deny(409,'No se puede cobrar una venta anulada.');
    $form=['amount'=>centsToDecimal(max(0,$sale['balance']['balance'])),'method'=>'cash'];
    if ($method==='POST') {
        $intent=input($_POST,'intent','pay');
        try {
            if ($intent==='open-cash') {
                openCashSession((int)$user['id'],input($_POST,'opening_cash','0'));
                flash('Caja abierta.'); redirect('sale-pay',['id'=>$sale['id']]);
            } elseif ($intent==='apply-deposit') {
                applyDeposit((int)$user['id'],(int)input($_POST,'payment_id'),(int)$sale['id'],input($_POST,'amount'));
                flash('Seña aplicada a la venta.'); redirect('sale',['id'=>$sale['id']]);
            } else {
                $form=['amount'=>input($_POST,'amount'),'method'=>input($_POST,'method')]; $key=input($_POST,'idempotency_key',$key);
                [, $replayed]=recordPayment((int)$user['id'],['idempotency_key'=>$key,'method'=>$form['method'],'amount'=>$form['amount'],'sale_id'=>(string)$sale['id']]);
                flash($replayed?'Ese cobro ya estaba registrado; no se duplicó.':'Cobro registrado.'); redirect('sale',['id'=>$sale['id']]);
            }
        } catch (DomainException $e) {
            if (in_array($e->getCode(),[403,404],true)) $deny($e->getCode(),$e->getMessage());
            http_response_code($e->getCode()); $errors[]=$e->getMessage(); $session=currentCashSession();
            $sale=saleRecord($sale['id']);
        }
    }
    $deposits=clientDeposits((int)$sale['client_id']);
    render('sale-pay',compact('user','page','sale','form','key','errors','session','deposits','backUrl')+['title'=>'Cobrar venta #'.$sale['id']]);
    exit;
}

if ($page==='sale-void') {
    $id=filter_var(input($_GET,'id'),FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    $sale=$id?saleRecord($id):false;
    if (!$sale) $deny(404,'Venta no encontrada.');
    if ($user['role']!=='admin') $deny(403,'Solo administración puede anular ventas.');
    if ($sale['status']==='voided') $deny(409,'La venta ya está anulada.');
    $backUrl=url('sale',['id'=>$sale['id']]); $reason='';
    if ($method==='POST') {
        $reason=input($_POST,'reason');
        try {
            if (input($_POST,'intent')==='open-cash') { openCashSession((int)$user['id'],input($_POST,'opening_cash','0')); flash('Caja abierta.'); redirect('sale-void',['id'=>$sale['id']]); }
            voidSale((int)$user['id'],(int)$sale['id'],$reason);
            flash('Venta anulada. Los cobros se reintegraron en la caja y las señas quedaron disponibles.'); redirect('sale',['id'=>$sale['id']]);
        } catch (DomainException $e) {
            if (in_array($e->getCode(),[403,404],true)) $deny($e->getCode(),$e->getMessage());
            http_response_code($e->getCode()); $errors[]=$e->getMessage(); $session=currentCashSession();
        }
    }
    $payments=array_values(array_filter(salePayments((int)$sale['id']),fn($p)=>!$p['released_at'] && $p['status']==='paid'));
    $refundCents=array_sum(array_map(fn($p)=>$p['kind']==='payment'?decimalToCents($p['amount']):0,$payments));
    $depositCents=array_sum(array_map(fn($p)=>$p['kind']==='deposit'?decimalToCents($p['amount']):0,$payments));
    render('sale-void',compact('user','page','sale','reason','errors','session','refundCents','depositCents','backUrl')+['title'=>'Anular venta #'.$sale['id']]);
    exit;
}

// --- deposit-new: seña de una cita ---
$appointmentId=input($_GET,'appointment');
$appointment=ctype_digit($appointmentId)?query('SELECT a.*,c.first_name,c.last_name FROM appointments a JOIN clients c ON c.id=a.client_id WHERE a.id=?',[$appointmentId])->fetch():false;
if (!$appointment) $deny(404,'Cita no encontrada.');
if ($appointment['status']==='cancelled') $deny(409,'No se puede registrar una seña para una cita cancelada.');
$backUrl=url('agenda',['date'=>substr($appointment['starts_at'],0,10)]);
$form=['amount'=>'','method'=>'cash'];
if ($method==='POST') {
    $intent=input($_POST,'intent','pay');
    try {
        if ($intent==='open-cash') {
            openCashSession((int)$user['id'],input($_POST,'opening_cash','0'));
            flash('Caja abierta.'); redirect('deposit-new',['appointment'=>$appointment['id']]);
        }
        $form=['amount'=>input($_POST,'amount'),'method'=>input($_POST,'method')]; $key=input($_POST,'idempotency_key',$key);
        [, $replayed]=recordPayment((int)$user['id'],['idempotency_key'=>$key,'method'=>$form['method'],'amount'=>$form['amount'],'appointment_id'=>(string)$appointment['id']]);
        flash($replayed?'Esa seña ya estaba registrada; no se duplicó.':'Seña registrada. Queda disponible para aplicarla a la venta.'); redirect('agenda',['date'=>substr($appointment['starts_at'],0,10)]);
    } catch (DomainException $e) {
        if (in_array($e->getCode(),[403,404],true)) $deny($e->getCode(),$e->getMessage());
        http_response_code($e->getCode()); $errors[]=$e->getMessage(); $session=currentCashSession();
    }
}
$deposited=decimalToCents(query("SELECT COALESCE(SUM(pa.amount),0) FROM payment_allocations pa JOIN payments p ON p.id=pa.payment_id WHERE pa.appointment_id=? AND p.kind='deposit' AND p.status='paid'",[$appointment['id']])->fetchColumn());
render('deposit-form',compact('user','page','appointment','form','key','errors','session','deposited','backUrl')+['title'=>'Registrar seña']);
exit;
