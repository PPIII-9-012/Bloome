<?php
declare(strict_types=1);
// Client profile (read-only). Authentication is checked by the front controller.
$deny = static function(int $status,string $title,string $message) use ($user,$page): never {
    http_response_code($status);
    render('message',compact('user','page','message')+['title'=>$title]); exit;
};
if ($method!=='GET') $deny(405,'Ficha del cliente','Método no permitido.');
$id=filter_var(input($_GET,'id'),FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
$client=$id?query('SELECT * FROM clients WHERE id=?',[$id])->fetch():false;
if (!$client) $deny(404,'Cliente no encontrado','Revisá el enlace o volvé al listado.');
$from=input($_GET,'from'); $to=input($_GET,'to'); $errors=[];
if ($from!=='' && !validDate($from)) $errors[]='La fecha de inicio no es válida.';
if ($to!=='' && !validDate($to)) $errors[]='La fecha de fin no es válida.';
if (!$errors && $from!=='' && $to!=='' && $from>$to) $errors[]='La fecha de inicio no puede ser posterior a la de fin.';
$number=max(1,min(100000,(int)input($_GET,'p','1'))); $perPage=15;
$history=['rows'=>[],'total'=>0,'byStatus'=>array_fill_keys(array_keys(appointmentStatuses()),0)];
if (!$errors) {
    $history=clientAppointments((int)$id,$from===''?null:$from,$to===''?null:$to,$perPage,($number-1)*$perPage);
    $pages=max(1,(int)ceil($history['total']/$perPage));
    if ($number>$pages) { $number=$pages; $history=clientAppointments((int)$id,$from===''?null:$from,$to===''?null:$to,$perPage,($number-1)*$perPage); }
} else { http_response_code(422); }
$pages=max(1,(int)ceil($history['total']/$perPage));
$sales=canEdit($user)?clientSales((int)$id):[];
$saleByAppointment=canEdit($user)?activeSalesByAppointment(array_column($history['rows'],'id')):[];
render('client-profile',compact('user','page','client','id','from','to','errors','history','number','pages','sales','saleByAppointment')+['title'=>$client['first_name'].' '.$client['last_name']]);
exit;
