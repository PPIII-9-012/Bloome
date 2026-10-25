<?php
declare(strict_types=1);
// Caja diaria (#19): resumen por medio de pago, libro de movimientos con origen, ingresos/gastos/retiros manuales,
// categorías de gasto y corrección de movimientos manuales. Autenticación y CSRF: front controller.
$deny = static function(int $status,string $message) use ($user,$page): never {
    http_response_code($status);
    render('message',compact('user','page','message')+['title'=>'Caja']); exit;
};
if (!canEdit($user)) $deny(403,'Solo administración y recepción pueden ver y operar la caja.');
if (!in_array($method,['GET','POST'],true)) $deny(405,'Método no permitido.');
$errors=[]; $isAdmin=$user['role']==='admin';

if (in_array($page,['caja-print','caja-export'],true)) {
    if ($method!=='GET') $deny(405,'Método no permitido.');
    $cashView=cashSessionView((int)input($_GET,'session','0'));
    if (!$cashView) $deny(404,'Todavía no hay cajas registradas.');
    $filters=['method'=>input($_GET,'method'),'kind'=>input($_GET,'kind')];
    if (!isset(PAYMENT_METHODS[$filters['method']])) $filters['method']='';
    if (!isset(CASH_KIND_LABELS[$filters['kind']])) $filters['kind']='';
    $summary=cashSummary((int)$cashView['id']);
    [$movements,$count]=cashLedger((int)$cashView['id'],$filters,1,true);
    if ($page==='caja-export') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="caja-'.$cashView['id'].'-'.date('Y-m-d').'.csv"');
        echo cashCsv($movements,$summary); exit;
    }
    $expected=cashExpectedCents((int)$cashView['id']);
    render('cash-print',compact('user','page','cashView','summary','movements','count','filters','expected')+['title'=>'Informe de caja']);
    exit;
}

if ($page==='caja-movement') {
    $session=currentCashSession();
    $key=bin2hex(random_bytes(16));
    $form=['kind'=>input($_GET,'kind','expense'),'method'=>'cash','amount'=>'','category_id'=>'','description'=>''];
    if (!isset(CASH_KIND_LABELS[$form['kind']]) || in_array($form['kind'],['payment','refund'],true)) $form['kind']='expense';
    if ($method==='POST') {
        $intent=input($_POST,'intent','save');
        $form=['kind'=>input($_POST,'kind'),'method'=>input($_POST,'method'),'amount'=>input($_POST,'amount'),'category_id'=>input($_POST,'category_id'),'description'=>input($_POST,'description')];
        $key=input($_POST,'idempotency_key',$key);
        try {
            if ($intent==='open-cash') {
                openCashSession((int)$user['id'],input($_POST,'opening_cash','0')); flash('Caja abierta.'); redirect('caja-movement',['kind'=>$form['kind']]);
            }
            if (!in_array($form['kind'],['income','expense','withdrawal'],true)) throw new DomainException('Elegí si es un ingreso, un gasto o un retiro.',422);
            if ($form['kind']==='expense' && $form['category_id']==='') throw new DomainException('Elegí la categoría del gasto.',422);
            if ($form['kind']!=='expense') $form['category_id']='';
            if ($form['kind']!=='income' && $form['description']==='') throw new DomainException('Indicá en qué se gastó o por qué se retira.',422);
            recordCashMovement((int)$user['id'],$form['kind'],$form['method'],$form['amount'],$form['description'],$form['category_id'],$key);
            flash('Movimiento registrado en la caja.'); redirect('caja');
        } catch (DomainException $e) {
            if (in_array($e->getCode(),[403,404],true)) $deny($e->getCode(),$e->getMessage());
            http_response_code($e->getCode()); $errors[]=$e->getMessage(); $session=currentCashSession();
        }
    }
    render('cash-movement-form',compact('user','page','form','key','errors','session')+['categories'=>expenseCategories(),'title'=>'Nuevo movimiento de caja']);
    exit;
}

if ($page==='caja-close') {
    $session=currentCashSession();
    if (!$session) { flash('No hay una caja abierta para cerrar.'); redirect('caja'); }
    $form=['counted_cash'=>'','note'=>'']; $preview=null;
    if ($method==='POST') {
        $form=['counted_cash'=>input($_POST,'counted_cash'),'note'=>input($_POST,'note')];
        try {
            if (input($_POST,'intent')==='close') {
                $result=closeCashSession((int)$user['id'],(int)$session['id'],$form['counted_cash'],$form['note']);
                flash('Caja #'.$session['id'].' cerrada. '.($result['difference']===0?'Sin diferencias.':'Diferencia registrada: '.moneyFormat($result['difference']).'.')); redirect('caja',['session'=>$session['id']]);
            }
            $preview=cashCountPreview((int)$session['id'],$form['counted_cash']);
        } catch (DomainException $e) {
            if (in_array($e->getCode(),[403,404],true)) $deny($e->getCode(),$e->getMessage());
            http_response_code($e->getCode()); $errors[]=$e->getMessage();
            try { $preview=cashCountPreview((int)$session['id'],$form['counted_cash']); } catch (DomainException) { $preview=null; }
            if (!currentCashSession()) { flash('Esa caja ya está cerrada.'); redirect('caja'); }
        }
    }
    $expected=cashExpectedCents((int)$session['id']);
    render('cash-close',compact('user','page','session','form','preview','errors','expected')+['summary'=>cashSummary((int)$session['id']),'title'=>'Cerrar caja']);
    exit;
}

// --- caja ---
if ($method==='POST') {
    $intent=input($_POST,'intent');
    try {
        if ($intent==='open-cash') { openCashSession((int)$user['id'],input($_POST,'opening_cash','0')); flash('Caja abierta.'); redirect('caja'); }
        if ($intent==='reverse') {
            if (!$isAdmin) $deny(403,'Solo administración puede corregir movimientos.');
            reverseCashMovement((int)$user['id'],(int)input($_POST,'movement_id'),input($_POST,'reason')); flash('Corrección registrada con un movimiento inverso.'); redirect('caja');
        }
        if ($intent==='add-category') {
            if (!$isAdmin) $deny(403,'Solo administración puede crear categorías.');
            addExpenseCategory((int)$user['id'],input($_POST,'name')); flash('Categoría creada.'); redirect('caja');
        }
        throw new DomainException('Acción no reconocida.',422);
    } catch (DomainException $e) {
        if (in_array($e->getCode(),[403,404],true)) $deny($e->getCode(),$e->getMessage());
        http_response_code($e->getCode()); $errors[]=$e->getMessage();
    }
}
$cashView=cashSessionView((int)input($_GET,'session','0'));
$filters=['method'=>input($_GET,'method'),'kind'=>input($_GET,'kind')];
if (!isset(PAYMENT_METHODS[$filters['method']])) $filters['method']='';
if (!isset(CASH_KIND_LABELS[$filters['kind']])) $filters['kind']='';
$number=max(1,min(100000,(int)input($_GET,'p','1')));
$summary=$movements=null; $count=0; $pages=1;
if ($cashView) {
    $summary=cashSummary((int)$cashView['id']);
    [$movements,$count]=cashLedger((int)$cashView['id'],$filters,$number);
    $pages=max(1,(int)ceil($count/CASH_PER_PAGE));
    if ($number>$pages) { $number=$pages; [$movements,$count]=cashLedger((int)$cashView['id'],$filters,$number); }
}
$open=currentCashSession();
$isCurrent=$cashView && $open && (int)$cashView['id']===(int)$open['id'];
$expected=$cashView?cashExpectedCents((int)$cashView['id']):0;
render('cash',compact('user','page','cashView','summary','movements','count','number','pages','filters','errors','open','isCurrent','expected','isAdmin')+['sessions'=>cashSessionList(),'categories'=>$isAdmin?expenseCategories(false):[],'title'=>'Caja']);
exit;
