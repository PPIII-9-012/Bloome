<?php
declare(strict_types=1);
// Nueva venta (desde agenda o ficha) y detalle de venta. Autenticación y CSRF las comprueba el front controller.
$deny = static function(int $status,string $message) use ($user,$page): never {
    http_response_code($status);
    render('message',compact('user','page','message')+['title'=>'Ventas']); exit;
};
if (!canEdit($user)) $deny(403,'Solo administración y recepción pueden ver y registrar ventas.');
if (!in_array($method,['GET','POST'],true) || (in_array($page,['sale','sales','sales-print','sales-export'],true) && $method!=='GET')) $deny(405,'Método no permitido.');

/** Filtros del listado de ventas desde la URL: [filtros válidos, aviso de error, empleados para el selector]. */
function salesFilterInput(array $get): array
{
    $filters=['q'=>substr(input($get,'q'),0,100),'status'=>input($get,'status'),'from'=>input($get,'from'),'to'=>input($get,'to'),'employee'=>input($get,'employee'),'method'=>input($get,'method')];
    $filterError='';
    $employeeOptions=query('SELECT e.id,u.name FROM employees e JOIN users u ON u.id=e.user_id ORDER BY u.name,e.id')->fetchAll();
    if (!in_array($filters['employee'],array_map(fn($r)=>(string)$r['id'],$employeeOptions),true)) $filters['employee']='';
    if (!isset(PAYMENT_METHODS[$filters['method']])) $filters['method']='';
    if (!isset(COLLECTION_LABELS[$filters['status']])) $filters['status']='';
    foreach (['from','to'] as $field) if ($filters[$field]!=='' && !validDate($filters[$field])) { $filterError='Revisá las fechas: usá el selector de fecha.'; $filters[$field]=''; }
    if ($filters['from']!=='' && $filters['to']!=='' && $filters['from']>$filters['to']) { $filterError='La fecha "desde" no puede ser posterior a "hasta".'; $filters['from']=$filters['to']=''; }
    return [$filters,$filterError,$employeeOptions];
}

if ($page==='sales') {
    [$filters,$filterError,$employeeOptions]=salesFilterInput($_GET);
    $number=max(1,min(100000,(int)input($_GET,'p','1')));
    [$sales,$count,$totals]=searchSales($filters,$number);
    $pages=max(1,(int)ceil($count/SALES_PER_PAGE));
    if ($number>$pages) { $number=$pages; [$sales,$count,$totals]=searchSales($filters,$number); }
    if ($filterError!=='') http_response_code(422);
    render('sales',compact('user','page','sales','count','totals','number','pages','filters','filterError','employeeOptions')+['title'=>'Ventas']);
    exit;
}
if (in_array($page,['sales-print','sales-export'],true)) {
    [$filters,$filterError,$employeeOptions]=salesFilterInput($_GET);
    if ($filterError!=='') {
        http_response_code(422);
        render('message',compact('user','page')+['title'=>'No se pudo generar el informe','message'=>$filterError.' Volvé al listado y corregí los filtros.']); exit;
    }
    [$sales,$count,$totals]=searchSales($filters,1,true);
    if ($page==='sales-export') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="ventas-'.date('Y-m-d').'.csv"');
        echo salesCsv($sales,$totals); exit;
    }
    $employeeName='Todos'; foreach($employeeOptions as $opt) if ((string)$opt['id']===$filters['employee']) $employeeName=$opt['name'];
    render('sales-print',compact('user','page','sales','count','totals','filters','employeeName')+['title'=>'Informe de ventas']);
    exit;
}
if ($page==='sale') {
    $id=filter_var(input($_GET,'id'),FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    $sale=$id?saleRecord($id):false;
    if (!$sale) $deny(404,'Venta no encontrada.');
    $payments=salePayments((int)$sale['id']);
    render('sale-detail',compact('user','page','sale','payments')+['title'=>'Venta #'.$sale['id']]);
    exit;
}

// --- sale-new ---
$appointmentId=input($_GET,'appointment');
$appointment=false;
if ($appointmentId!=='') {
    $appointment=ctype_digit($appointmentId)?query('SELECT * FROM appointments WHERE id=?',[$appointmentId])->fetch():false;
    if (!$appointment) $deny(404,'Cita no encontrada.');
    if (in_array($appointment['status'],['cancelled','missed'],true)) $deny(422,'No se puede vender una cita cancelada o ausente.');
    $existing=activeSalesByAppointment([(int)$appointment['id']])[(int)$appointment['id']]??null;
    if ($existing) { flash('Esta cita ya tiene una venta vigente.'); redirect('sale',['id'=>$existing]); }
}
$formData=['client_id'=>$appointment?(string)$appointment['client_id']:'','employee_id'=>$appointment?(string)$appointment['employee_id']:'','notes'=>'','lines'=>[]];
if (!$appointment && ctype_digit(input($_GET,'client')) && query('SELECT id FROM clients WHERE id=?',[input($_GET,'client')])->fetchColumn()) $formData['client_id']=input($_GET,'client');
if ($appointment) foreach (query('SELECT service_id FROM appointment_services WHERE appointment_id=? ORDER BY id',[$appointment['id']])->fetchAll() as $row) {
    $formData['lines'][]=['service_id'=>(string)$row['service_id'],'quantity'=>'1','discount_type'=>'none','discount_value'=>''];
}
$errors=[]; $preview=null;
if ($method==='POST') {
    $intent=input($_POST,'intent','save');
    $formData['client_id']=input($_POST,'client_id'); $formData['employee_id']=input($_POST,'employee_id'); $formData['notes']=input($_POST,'notes');
    $formData['lines']=[];
    $posted=is_array($_POST['lines']??null)?array_slice($_POST['lines'],0,30):[];
    foreach ($posted as $line) {
        if (!is_array($line)) continue;
        $row=[]; foreach (['service_id','quantity','discount_type','discount_value'] as $field) $row[$field]=is_string($line[$field]??null)?trim($line[$field]):'';
        $formData['lines'][]=$row;
    }
    // Las líneas sin servicio elegido se ignoran, salvo que se pida otra fila vacía.
    $filled=array_values(array_filter($formData['lines'],fn($l)=>$l['service_id']!==''));
    try {
        if ($intent==='add-line') {
            if (count($formData['lines'])>=30) throw new DomainException('Una venta admite hasta 30 líneas.',422);
            $formData['lines'][]=['service_id'=>'','quantity'=>'1','discount_type'=>'none','discount_value'=>''];
        } elseif ($intent==='preview') {
            $preview=previewSale((int)$user['id'],$filled,$appointment?(string)$appointment['id']:'');
        } else {
            $saleId=createSale((int)$user['id'],['client_id'=>$formData['client_id'],'employee_id'=>$formData['employee_id'],'appointment_id'=>$appointment?(string)$appointment['id']:'','notes'=>$formData['notes'],'lines'=>$filled]);
            flash('Venta registrada. El total se calculó en el servidor.');
            redirect('sale',['id'=>$saleId]);
        }
    } catch (DomainException $e) {
        if (in_array($e->getCode(),[403,404],true)) $deny($e->getCode(),$e->getMessage());
        http_response_code($e->getCode()); $errors[]=$e->getMessage();
    }
}
if (!$formData['lines']) $formData['lines'][]=['service_id'=>'','quantity'=>'1','discount_type'=>'none','discount_value'=>''];
$clients=query('SELECT id,first_name,last_name FROM clients ORDER BY last_name,first_name,id')->fetchAll();
$employees=query('SELECT e.id,u.name FROM employees e JOIN users u ON u.id=e.user_id WHERE (e.active=1 AND u.active=1) OR e.id=? ORDER BY u.name',[$appointment['employee_id']??0])->fetchAll();
$services=query('SELECT id,name,price FROM services WHERE active=1 ORDER BY name,id')->fetchAll();
$backUrl=$appointment?url('agenda',['date'=>substr($appointment['starts_at'],0,10)]):(ctype_digit($formData['client_id'])&&$formData['client_id']!==''?url('client',['id'=>$formData['client_id']]):url('clients'));
render('sale-form',compact('user','page','formData','errors','preview','clients','employees','services','appointment','backUrl')+['title'=>'Nueva venta']);
exit;
