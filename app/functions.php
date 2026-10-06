<?php
declare(strict_types=1);

function e(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function url(string $page, array $params = []): string { return '/?' . http_build_query(['page' => $page] + $params); }
function redirect(string $page, array $params = []): never { header('Location: ' . url($page, $params), true, 303); exit; }
function input(array $source, string $key, string $default = ''): string { return isset($source[$key]) && is_string($source[$key]) ? trim($source[$key]) : $default; }
function textLength(string $value): int { return preg_match_all('/./us', $value) ?: strlen($value); }
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function csrfField(): string { return '<input type="hidden" name="csrf" value="' . e(csrf()) . '">'; }
function validCsrf(string $token): bool { return $token !== '' && isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token); }
function flash(string $message): void { $_SESSION['flash'] = $message; }
function canEdit(array $user): bool { return in_array($user['role'], ['admin', 'reception'], true); }
function render(string $view, array $data = []): void
{
    extract($data, EXTR_SKIP);
    ob_start();
    require ROOT . '/views/' . $view . '.php';
    $content = ob_get_clean();
    require ROOT . '/views/layout.php';
}
function initials(string $first, string $last = ''): string {
    preg_match('/^./us', $first, $a);
    preg_match('/^./us', $last, $b);
    return strtoupper(($a[0] ?? '') . ($b[0] ?? ''));
}
