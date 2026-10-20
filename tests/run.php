<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
$_SESSION = [];
$passed = 0;
function check(bool $condition, string $label): void {
    global $passed;
    if (!$condition) { fwrite(STDERR, "FAIL: $label\n"); exit(1); }
    $passed++;
    echo "OK: $label\n";
}
check(validDate('2024-02-29'), 'Fecha bisiesta válida');
check(!validDate('2025-02-29'), 'Rechaza fecha inexistente');
check(!validDate('2026-13-01'), 'Rechaza mes inexistente');
[$client,$errors] = validateClient(['first_name'=>' Lucía ', 'last_name'=>'Pérez','email'=>'lucia@example.test','phone'=>'+54 (11) 5555-1234','birth_date'=>'1990-01-20']);
check($errors === [] && $client['first_name'] === 'Lucía', 'Normaliza cliente válido');
[, $errors] = validateClient(['first_name'=>[], 'last_name'=>'', 'email'=>'invalid', 'phone'=>'abcdefg', 'birth_date'=>'2999-01-01']);
check(count($errors) === 5, 'Rechaza tipos, obligatorios y contactos inválidos');
[, $errors] = validateClient(['first_name'=>'Ana','last_name'=>'Prueba','birth_date'=>'2025-02-30']);
check(isset($errors['birth_date']), 'No permite normalización silenciosa de fecha');
[, $errors] = validateClient(['first_name'=>'Ana','last_name'=>'Prueba','notes'=>str_repeat('x',2001)]);
check(isset($errors['notes']), 'Limita observaciones');
check(e('<script>"&') === '&lt;script&gt;&quot;&amp;', 'Escapa HTML no confiable');
$token = csrf();
check(validCsrf($token) && !validCsrf('invalid') && !validCsrf(''), 'Valida CSRF y rechaza ausente');
check(canEdit(['role'=>'admin']) && canEdit(['role'=>'reception']) && !canEdit(['role'=>'professional']), 'Permisos de escritura');
check(input(['q'=>['injection']], 'q') === '', 'No acepta arrays como filtro');
check(url('clients',['q'=>'a&b']) === '/?page=clients&q=a%26b', 'Codifica parámetros de navegación');

ob_start();
render('clients', ['user'=>['name'=>'Admin','role'=>'admin'],'page'=>'clients','title'=>'Clientes','clients'=>[['id'=>1,'first_name'=>'<script>alert(1)</script>','last_name'=>'Prueba','email'=>null,'phone'=>null,'created_at'=>'2026-10-05']],'search'=>'','number'=>1,'pages'=>1,'count'=>1]);
$html = ob_get_clean();
check(!str_contains($html,'<script>') && str_contains($html,'&lt;script&gt;'), 'Vista clientes bloquea HTML almacenado');
check(str_contains($html,'client-edit') && str_contains($html,'name="csrf"'), 'Vista tiene edición y formulario de sesión protegido');
check(!str_contains($html,'onclick=') && str_contains($html,'Próx.'), 'Listado no usa controladores inline bloqueados por CSP ni simula exportación');
ob_start();
render('client-form',['user'=>['name'=>'Admin','role'=>'admin'],'page'=>'client-new','title'=>'Nuevo cliente','client'=>array_fill_keys(['first_name','last_name','email','phone','birth_date','notes'],''),'errors'=>['first_name'=>'Requerido'],'id'=>0]);
$html=ob_get_clean();
check(str_contains($html,'aria-invalid="true"') && str_contains($html,'error-first_name'), 'Errores accesibles vinculados al campo');
$period=agendaPeriod('2024-02-29','month');
check($period['from']==='2024-02-01' && $period['until']==='2024-03-01','Mes bisiesto tiene límites exactos');
$period=agendaPeriod('2026-01-01','week');
check($period['from']==='2025-12-29' && $period['until']==='2026-01-05','Semana cruza año de lunes a domingo');
$period=agendaPeriod('2026-03-31','month');
check($period['previous']==='2026-02-01' && $period['next']==='2026-04-01','Navegación mensual no salta meses cortos');
$period=agendaPeriod('2026-12-31','day');
check($period['until']==='2027-01-01' && $period['previous']==='2026-12-30','Día cruza año correctamente');
check(agendaContext(['date'=>'bad','mode'=>'javascript:','employee'=>'-1','status'=>'bad','return'=>'https://example.test'])===[],'Contexto de retorno acepta solo parámetros válidos de agenda');
$_SESSION['appointment_drafts']['expired']=['created'=>time()-3601];
check(appointmentDraft('expired')===null && appointmentDraft('missing')===null,'Borradores vencidos o inexistentes se rechazan');
check(csvSafeCell('=1+1')==="'=1+1" && csvSafeCell('+54')==="'+54" && csvSafeCell('-x')==="'-x" && csvSafeCell('@a')==="'@a" && csvSafeCell("\tx")==="'\tx" && csvSafeCell('Ana')==='Ana','CSV neutraliza celdas que parecen fórmulas');
$csv=agendaCsv([['starts_at'=>'2026-11-02 10:00:00','ends_at'=>'2026-11-02 10:50:00','first_name'=>'Ñ=a','last_name'=>'"B";','service_name'=>"Uno\nDos",'employee_name'=>'@Emp','status'=>'confirmed']]);
check(str_starts_with($csv,"\xEF\xBB\xBFFecha;") && str_contains($csv,'02/11/2026;10:00;10:50;"Ñ=a ""B"";";"Uno'."\r\n".'Dos";\'@Emp;Confirmada'),'CSV escapa comillas, saltos y separadores');
check(agendaCsv([])==="\xEF\xBB\xBFFecha;Inicio;Fin;Cliente;Servicios;Empleado;Estado\r\n",'CSV vacío conserva encabezados');
echo "\n$passed pruebas aprobadas.\n";
