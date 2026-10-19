<div class="config-page">

    <header class="config-page-header">
        <div>
            <span class="config-eyebrow">BLOOME / PREFERENCIAS</span>
            <h1>Configuración</h1>
            <p>Administrá tu centro, los servicios y las preferencias del establecimiento.</p>
        </div>
    </header>

    <!-- 1. EMPRESA -->
    <section class="config-section">
        <div class="config-section-heading">
            <h2>1. Empresa</h2>
            <p>Configurá los datos del centro, su apariencia y el equipo de trabajo.</p>
        </div>

        <!-- Centro -->
        <article class="config-settings-card">
            <div class="config-setting-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                    <path d="M3 21h18M5 21V7l7-4 7 4v14"/>
                    <path d="M9 21v-6h6v6M9 9h.01M15 9h.01"/>
                </svg>
            </div>
            <div class="config-setting-info">
                <h3>Centro</h3>
                <p>Nombre del centro, razón social, email, país, provincia, dirección, código postal y teléfono móvil.</p>
            </div>
            <a class="config-action-link" href="<?= e(url('configuracion', ['seccion' => 'centro'])) ?>">
                Configurar <span aria-hidden="true">→</span>
            </a>
        </article>

        <!-- Diseño -->
        <article class="config-settings-card">
            <div class="config-setting-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                    <path d="M12 3a9 9 0 1 0 0 18h2a2 2 0 0 0 1.5-3.3A2 2 0 0 1 17 14h1a3 3 0 0 0 3-3c0-4.4-4-8-9-8Z"/>
                    <circle cx="7.5" cy="10" r="1"/>
                    <circle cx="10" cy="6.5" r="1"/>
                    <circle cx="15" cy="7.5" r="1"/>
                </svg>
            </div>
            <div class="config-setting-info">
                <h3>Diseño</h3>
                <p>Adjuntá el logotipo del centro y elegí el color principal del tema.</p>
            </div>
            <a class="config-action-link" href="<?= e(url('configuracion', ['seccion' => 'diseno'])) ?>">
                Personalizar <span aria-hidden="true">→</span>
            </a>
        </article>

        <!-- Empleados -->
        <article class="config-settings-card">
            <div class="config-setting-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                    <circle cx="9" cy="8" r="4"/>
                    <path d="M2 21v-2a7 7 0 0 1 14 0v2"/>
                    <path d="M16 4a4 4 0 0 1 0 8M19 14a6 6 0 0 1 3 5v2"/>
                </svg>
            </div>
            <div class="config-setting-info">
                <h3>Empleados</h3>
                <p>Buscá empleados, consultá sus perfiles, editá sus datos y administrá vacaciones y horarios.</p>
            </div>
            <a class="config-action-link" href="<?= e(url('configuracion', ['seccion' => 'empleados'])) ?>">
                Gestionar <span aria-hidden="true">→</span>
            </a>
        </article>
    </section>

    <!-- 2. VENTAS -->
    <section class="config-section">
        <div class="config-section-heading">
            <h2>2. Ventas</h2>
            <p>Organizá las categorías y los servicios que ofrece tu centro.</p>
        </div>

        <!-- Categorías -->
        <article class="config-settings-card">
            <div class="config-setting-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                    <rect x="3" y="3" width="8" height="8" rx="2"/>
                    <rect x="13" y="3" width="8" height="8" rx="2"/>
                    <rect x="3" y="13" width="8" height="8" rx="2"/>
                    <rect x="13" y="13" width="8" height="8" rx="2"/>
                </svg>
            </div>
            <div class="config-setting-info">
                <h3>Categorías</h3>
                <p>Buscá, organizá y administrá las categorías de servicios.</p>
            </div>
            <a class="config-action-link" href="<?= e(url('configuracion', ['seccion' => 'categorias'])) ?>">
                Gestionar <span aria-hidden="true">→</span>
            </a>
        </article>

        <!-- Servicios -->
        <article class="config-settings-card">
            <div class="config-setting-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                    <path d="M12 3v18M3 12h18"/>
                    <circle cx="12" cy="12" r="9"/>
                </svg>
            </div>
            <div class="config-setting-info">
                <h3>Servicios</h3>
                <p>Consultá los servicios y administrá sus precios, referencias y duración.</p>
            </div>
            <a class="config-action-link" href="<?= e(url('configuracion', ['seccion' => 'servicios'])) ?>">
                Gestionar <span aria-hidden="true">→</span>
            </a>
        </article>
    </section>

    <!-- 3. OTROS -->
    <section class="config-section">
        <div class="config-section-heading">
            <h2>3. Otros</h2>
            <p>Configurá los horarios generales y los días en que el centro permanecerá cerrado.</p>
        </div>

        <!-- Horarios -->
        <article class="config-settings-card">
            <div class="config-setting-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                    <circle cx="12" cy="12" r="9"/>
                    <path d="M12 7v5l3 2"/>
                </svg>
            </div>
            <div class="config-setting-info">
                <h3>Horarios</h3>
                <p>Definí los días de apertura y las franjas horarias de atención del centro.</p>
            </div>
            <a class="config-action-link" href="<?= e(url('configuracion', ['seccion' => 'horarios'])) ?>">
                Configurar <span aria-hidden="true">→</span>
            </a>
        </article>

        <!-- Festivos -->
        <article class="config-settings-card">
            <div class="config-setting-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                    <rect x="3" y="4" width="18" height="17" rx="2"/>
                    <path d="M16 2v4M8 2v4M3 10h18"/>
                    <path d="m9 15 2 2 4-4"/>
                </svg>
            </div>
            <div class="config-setting-info">
                <h3>Festivos</h3>
                <p>Administrá los días festivos y los cierres excepcionales del establecimiento.</p>
            </div>
            <a class="config-action-link" href="<?= e(url('configuracion', ['seccion' => 'festivos'])) ?>">
                Gestionar <span aria-hidden="true">→</span>
            </a>
        </article>
    </section>

</div>
