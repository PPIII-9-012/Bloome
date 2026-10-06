<?php $notice = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); ?>
<!doctype html>
<html lang="es-AR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= e($title) ?> · Bloome</title>
    <link rel="stylesheet" href="/assets/app.css">
    <link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
</head>
<body class="<?= empty($user) ? 'guest-view' : 'app-view' ?>">
<?php if (!empty($user)): ?>
<a class="skip-link" href="#main">Ir al contenido</a>

<!-- FIGMA TOPBAR (02-top-menu.png) -->
<header class="figma-topbar">
    <div class="topbar-left">
        <div class="branch-pill" title="Centro seleccionado">
            <span class="branch-icon-circle" aria-hidden="true">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2L12 22"/><path d="M17 7C17 7 13 9 12 14"/><path d="M7 11C7 11 11 13 12 18"/></svg>
            </span>
            <span>AIXA</span>
            <span class="branch-dropdown-arrow" aria-hidden="true">▼</span>
        </div>
        <div class="topbar-divider" aria-hidden="true"></div>
        <a class="topbar-brand" href="<?= e(url('dashboard')) ?>" title="Bloome Estética Integral">
            <img src="/assets/bloome-logo.png" alt="BLOOME Estética Integral" class="topbar-logo-img">
        </a>
    </div>

    <div class="topbar-right">
        <form class="topbar-search-form" method="get" action="/">
            <input type="hidden" name="page" value="clients">
            <div class="topbar-search-box">
                <span class="topbar-search-icon" aria-hidden="true">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                </span>
                <input type="search" name="q" placeholder="Buscar cliente" aria-label="Buscar cliente">
            </div>
        </form>

        <div class="topbar-user" title="Perfil activo: <?= e($user['name']) ?>">
            <span class="topbar-user-icon" aria-hidden="true">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            </span>
            <span><?= e($user['name']) ?></span>
        </div>

        <form action="<?= e(url('logout')) ?>" method="post" class="topbar-logout-form">
            <?= csrfField() ?>
            <button type="submit" class="topbar-logout-btn" title="Cerrar sesión">
                <span>Salir</span>
                <span aria-hidden="true">↗</span>
            </button>
        </form>
    </div>
</header>

<!-- FIGMA SIDEBAR (03-side-menu.png) -->
<aside class="figma-sidebar">
    <nav class="sidebar-nav" aria-label="Navegación principal">
        <?php
        $navItems = [
            'agenda' => [
                'label' => 'Agenda',
                'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
                'enabled' => true
            ],
            'caja' => [
                'label' => 'Caja',
                'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polygon points="12 6 13.8 9.8 18 10.4 15 13.3 15.7 17.5 12 15.5 8.3 17.5 9 13.3 6 10.4 10.2 9.8 12 6"/></svg>',
                'enabled' => false
            ],
            'clients' => [
                'label' => 'Clientes',
                'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
                'enabled' => true
            ],
            'estadisticas' => [
                'label' => 'Estadísticas',
                'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
                'enabled' => false
            ],
            'configuracion' => [
                'label' => 'Configuración',
                'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
                'enabled' => false
            ],
        ];
        ?>
        <?php foreach ($navItems as $key => $item):
            $isActive = ($page === $key) || ($key === 'clients' && str_starts_with($page, 'client-'));
        ?>
            <?php if ($item['enabled']): ?>
            <a class="sidebar-nav-item <?= $isActive ? 'active' : '' ?>" href="<?= e(url($key)) ?>" <?= $isActive ? 'aria-current="page"' : '' ?>>
                <?= $item['icon'] ?>
                <span><?= e($item['label']) ?></span>
            </a>
            <?php else: ?>
            <div class="sidebar-nav-item disabled" title="Módulo planificado en el roadmap">
                <?= $item['icon'] ?>
                <span><?= e($item['label']) ?></span>
                <small class="nav-tag-pending">Próx.</small>
            </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
        <img src="/assets/sidebar-leaf.png" alt="Bloome Botanical" class="sidebar-leaf-img">
    </div>
</aside>

<!-- MAIN WORKSPACE -->
<div class="figma-app-container">
    <main id="main" class="figma-main">
        <?php if ($notice): ?>
            <div class="notice success" role="status">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                <span><?= e($notice) ?></span>
            </div>
        <?php endif; ?>
        <?= $content ?>
    </main>
    <footer class="figma-footer">
        <span>BLOOME · Estética Integral</span>
        <span>Hecho para cuidar cada detalle.</span>
    </footer>
</div>
<?php else: ?>
<main id="main">
    <?= $content ?>
</main>
<?php endif; ?>
</body>
</html>
