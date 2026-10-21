<?php
declare(strict_types=1);
// Estadísticas (tarjeta #27). Autenticación la comprueba el front controller; solo lectura (GET).
require_once ROOT . '/app/stats.php';
$deny = static function (int $status, string $message) use ($user, $page): never {
    http_response_code($status);
    render('message', compact('user', 'page', 'message') + ['title' => $status === 404 ? 'Informe no encontrado' : 'Estadísticas']);
    exit;
};
if (!canEdit($user)) $deny(403, 'Solo administración y recepción pueden ver las estadísticas.');
if ($method !== 'GET') $deny(405, 'Método no permitido.');

if ($page === 'estadisticas') {
    render('stats', compact('user', 'page') + ['title' => 'Estadísticas']);
    exit;
}

[$filters, $filterError] = statsFilterInput($_GET);
$kind = ['estadisticas-ventas' => 'ventas', 'estadisticas-gastos' => 'gastos', 'estadisticas-horas' => 'horas'][$page];
$view = input($_GET, 'v', 'general');
$views = $kind === 'ventas' ? STATS_SALES_VIEWS : ($kind === 'gastos' ? STATS_EXPENSE_VIEWS : ['general' => 'Horas totales empleadas']);
if (!isset($views[$view])) $deny(404, 'Ese informe no existe. Volvé al menú de estadísticas.');

$categories = query('SELECT id, name FROM service_categories ORDER BY name, id')->fetchAll();
$services = query('SELECT id, name FROM services ORDER BY name, id')->fetchAll();
$expenseCats = expenseCategories(false);
$employeeOptions = query('SELECT e.id, u.name FROM employees e JOIN users u ON u.id=e.user_id ORDER BY u.name, e.id')->fetchAll();
if ($filterError !== '') http_response_code(422);
$report = match ($kind) { 'ventas' => statsSales($view, $filters), 'gastos' => statsExpenses($view, $filters), 'horas' => statsHours($filters) };
render('stats-report', ['mode' => $view] + compact('user', 'page', 'kind', 'views', 'filters', 'filterError', 'report', 'categories', 'services', 'expenseCats', 'employeeOptions') + ['title' => 'Estadísticas · ' . ucfirst($kind) . ' · ' . $views[$view]]);
exit;
