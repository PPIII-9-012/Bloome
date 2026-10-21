<?php
declare(strict_types=1);
// Authentication and CSRF are checked by the front controller.
$deny = static function(int $status,string $message) use ($user,$page): never {
    http_response_code($status);
    render('message',compact('user','page','message')+['title'=>'Turnos']); exit;
};
if (!canEdit($user)) $deny(403,'Solo administración y recepción pueden modificar turnos.');
if (!in_array($method,['GET','POST'],true) || ($page==='appointment-cancel' && $method!=='POST')) $deny(405,'Método no permitido.');
$new=$page==='appointment-new';
$id=$new?null:filter_var(input($_GET,'id'),FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if (!$new && !$id) $deny(404,'Turno no encontrado.');
$record=$new?false:appointmentRecord($id);
if (!$new && !$record) $deny(404,'Turno no encontrado.');
$formData=$new?['client_id'=>'','employee_id'=>'','starts_at'=>date('Y-m-d').'T09:00','status'=>'pending','notes'=>'','services'=>[]]:[
    'client_id'=>(string)$record['client_id'],'employee_id'=>(string)$record['employee_id'],
    'starts_at'=>str_replace(' ','T',substr($record['starts_at'],0,16)), 'status'=>$record['status'],'notes'=>$record['notes']??'',
    'services'=>array_map('strval',array_column($record['services'],'service_id'))];
$version=$record?appointmentVersion($record):''; $errors=[];
if ($method==='POST') {
    try {
        $savedId=saveAppointment((int)$user['id'],$id,$_POST,$page==='appointment-cancel');
        $saved=appointmentRecord($savedId);
        flash($page==='appointment-cancel'?'Turno cancelado. El historial se conserva.':'Turno guardado correctamente.');
        redirect('agenda',['date'=>substr($saved['starts_at'],0,10)]);
    } catch(DomainException $e) {
        if (in_array($e->getCode(),[403,404],true) || $page==='appointment-cancel') $deny($e->getCode(),$e->getMessage());
        http_response_code($e->getCode()); $errors[]=$e->getMessage();
        foreach(['client_id','employee_id','starts_at','status','notes'] as $field) $formData[$field]=input($_POST,$field);
        $formData['services']=is_array($_POST['services']??null)?array_values(array_filter($_POST['services'],'is_string')):[];
        // A stale form must not silently acquire a new version and overwrite another edit.
        $version=input($_POST,'version');
    }
}
$clients=query('SELECT id,first_name,last_name FROM clients ORDER BY last_name,first_name,id')->fetchAll();
$employees=query('SELECT e.id,u.name,(e.active AND u.active) AS active FROM employees e JOIN users u ON u.id=e.user_id WHERE (e.active=1 AND u.active=1) OR e.id=? ORDER BY u.name',[$record['employee_id']??0])->fetchAll();
$services=query('SELECT * FROM services ORDER BY name,id')->fetchAll();
$previous=$record?array_column($record['services'],null,'service_id'):[];
render('appointment-form',compact('user','page','id','new','formData','version','errors','clients','employees','services','previous','record')+['title'=>$new?'Nuevo turno':'Editar turno']);
exit;
