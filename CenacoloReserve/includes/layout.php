<?php
/**
 * Cenacolo Reservas - Layout/Template helpers
 */

function renderHeader($title = 'Dashboard', $user = null) {
    $siteName = RES_SITE_NAME;
    $version = RES_VERSION;
    $role = $user ? ucfirst($user['role']) : '';
    $restaurantName = $user['restaurant_name'] ?? 'Todos los restaurantes';

    $restaurants = getActiveRestaurants();
    $currentRestaurant = $_SESSION['res_current_restaurant'] ?? 'all';
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="mobile-web-app-capable" content="yes">
        <title><?= resSanitize($title) ?> - <?= $siteName ?></title>
        <link rel="icon" type="image/svg+xml" href="<?= resUrl('/assets/img/favicon.svg') ?>">
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
        <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
        <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
        <script>
            tailwind.config = {
                theme: {
                    extend: {
                        colors: {
                            gold: {
                                50: '#FFF9E6',
                                100: '#FFF2CC',
                                200: '#FFE599',
                                300: '#FFD966',
                                400: '#FFCC33',
                                500: '#D4AF37',
                                600: '#B8960F',
                                700: '#8C7200',
                                800: '#5F4D00',
                                900: '#332900',
                            },
                            dark: {
                                50: '#f8fafc',
                                100: '#f1f5f9',
                                200: '#e2e8f0',
                                300: '#cbd5e1',
                                400: '#94a3b8',
                                500: '#64748b',
                                600: '#475569',
                                700: '#334155',
                                800: '#1e293b',
                                900: '#0f172a',
                                950: '#020617',
                            }
                        },
                        fontFamily: {
                            'display': ['Playfair Display', 'serif'],
                            'body': ['Inter', 'sans-serif'],
                        }
                    }
                }
            }
        </script>
        <style>
            body { font-family: 'Inter', sans-serif; }
            .font-display { font-family: 'Playfair Display', serif; }
            .glass { background: rgba(255,255,255,0.05); backdrop-filter: blur(10px); }
            .gold-gradient { background: linear-gradient(135deg, #D4AF37 0%, #F5E6A3 50%, #D4AF37 100%); }
            .sidebar-link { transition: all 0.2s; }
            .sidebar-link:hover, .sidebar-link.active {
                background: rgba(212, 175, 55, 0.1);
                border-left: 3px solid #D4AF37;
            }
            .status-badge { font-size: 0.7rem; padding: 2px 8px; border-radius: 9999px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; }
            .status-pending { background: #FEF3C7; color: #92400E; }
            .status-confirmed { background: #D1FAE5; color: #065F46; }
            .status-seated { background: #DBEAFE; color: #1E40AF; }
            .status-completed { background: #E0E7FF; color: #3730A3; }
            .status-no_show { background: #FEE2E2; color: #991B1B; }
            .status-cancelled { background: #F3F4F6; color: #374151; }
            .status-waitlist { background: #FDE68A; color: #78350F; }
            .channel-badge { font-size: 0.65rem; padding: 1px 6px; border-radius: 4px; font-weight: 600; }
            .channel-opentable { background: #DA3743; color: white; }
            .channel-website { background: #3B82F6; color: white; }
            .channel-whatsapp { background: #25D366; color: white; }
            .channel-manual { background: #6B7280; color: white; }
            .channel-walkin { background: #8B5CF6; color: white; }
            .channel-concierge { background: #D4AF37; color: #1e293b; }
            .channel-instagram { background: #E1306C; color: white; }
            .channel-facebook { background: #1877F2; color: white; }
            .channel-google { background: #4285F4; color: white; }
            .channel-phone { background: #059669; color: white; }
            /* Scrollbar */
            ::-webkit-scrollbar { width: 6px; }
            ::-webkit-scrollbar-track { background: #1e293b; }
            ::-webkit-scrollbar-thumb { background: #475569; border-radius: 3px; }
            ::-webkit-scrollbar-thumb:hover { background: #64748b; }
            /* Animations */
            @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
            .fade-in { animation: fadeIn 0.3s ease-out; }
            /* Modal */
            .modal-overlay { background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); }

            /* ===== TABLET & TOUCH OPTIMIZATIONS ===== */
            html { scroll-behavior: smooth; -webkit-overflow-scrolling: touch; }

            /* Prevent iOS zoom on input focus */
            input, select, textarea { font-size: 16px !important; }

            /* Touch feedback */
            .touch-btn { transition: transform 0.1s, opacity 0.1s; }
            .touch-btn:active { transform: scale(0.96); opacity: 0.9; }

            /* Action buttons - large touch targets */
            .action-btn {
                display: inline-flex; align-items: center; justify-content: center; gap: 6px;
                min-height: 44px; min-width: 44px; padding: 8px 16px;
                border-radius: 10px; font-weight: 600; font-size: 0.875rem;
                transition: all 0.15s; cursor: pointer; user-select: none;
            }
            .action-btn:active { transform: scale(0.95); }
            .action-btn-seat { background: #2563EB; color: white; }
            .action-btn-seat:hover { background: #1D4ED8; }
            .action-btn-complete { background: #059669; color: white; }
            .action-btn-complete:hover { background: #047857; }
            .action-btn-noshow { background: #DC2626; color: white; }
            .action-btn-noshow:hover { background: #B91C1C; }
            .action-btn-assign { background: #D97706; color: white; }
            .action-btn-assign:hover { background: #B45309; }
            .action-btn-edit { background: #374155; color: #e2e8f0; }
            .action-btn-edit:hover { background: #475569; }

            /* Sidebar mobile/tablet toggle */
            @media (max-width: 1023px) {
                #sidebar { transform: translateX(-100%); }
                #sidebar.sidebar-open { transform: translateX(0); }
                #sidebarOverlay { display: none; }
                #sidebarOverlay.active { display: block; }
                main.main-content { margin-left: 0 !important; }
                .sidebar-toggle { display: flex !important; }
            }
            @media (min-width: 1024px) {
                .sidebar-toggle { display: none !important; }
                #sidebarOverlay { display: none !important; }
            }

            /* Customer info modal */
            .customer-modal {
                position: fixed; inset: 0; z-index: 60;
                display: flex; align-items: flex-end; justify-content: center;
            }
            @media (min-width: 768px) {
                .customer-modal { align-items: center; }
            }
            .customer-modal-content {
                background: #0f172a; border: 1px solid #334155; border-radius: 16px 16px 0 0;
                width: 100%; max-height: 85vh; overflow-y: auto;
                animation: slideUp 0.25s ease-out;
            }
            @media (min-width: 768px) {
                .customer-modal-content {
                    max-width: 480px; border-radius: 16px;
                    animation: fadeIn 0.2s ease-out;
                }
            }
            @keyframes slideUp {
                from { transform: translateY(100%); } to { transform: translateY(0); }
            }

            /* Seat selection modal */
            .seat-modal-grid {
                display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 12px;
            }
            .seat-table-btn {
                display: flex; flex-direction: column; align-items: center; justify-content: center;
                min-height: 80px; border-radius: 12px; border: 2px solid #334155;
                background: #1e293b; color: white; font-weight: 600; cursor: pointer;
                transition: all 0.15s; user-select: none;
            }
            .seat-table-btn:hover { border-color: #D4AF37; background: rgba(212,175,55,0.1); }
            .seat-table-btn:active { transform: scale(0.95); }
            .seat-table-btn.occupied { opacity: 0.4; pointer-events: none; border-color: #475569; }
        </style>
    </head>
    <body class="bg-dark-950 text-dark-200 min-h-screen">
        <!-- Sidebar Overlay (mobile/tablet) -->
        <div id="sidebarOverlay" class="fixed inset-0 bg-black/50 z-35 backdrop-blur-sm" onclick="toggleSidebar()"></div>

        <!-- Sidebar -->
        <aside id="sidebar" class="fixed left-0 top-0 h-full w-64 bg-dark-900 border-r border-dark-700 z-40 flex flex-col transition-transform duration-300">
            <!-- Logo -->
            <div class="p-5 border-b border-dark-700">
                <h1 class="font-display text-xl text-gold-500 font-bold tracking-wide">Cenacolo</h1>
                <p class="text-xs text-dark-400 mt-1">Central de Reservas</p>
            </div>

            <!-- Restaurant Selector -->
            <div class="px-4 py-3 border-b border-dark-700">
                <select id="restaurantSelector" onchange="switchRestaurant(this.value)"
                        class="w-full bg-dark-800 border border-dark-600 rounded-lg px-3 py-2 text-sm text-dark-200 focus:border-gold-500 focus:outline-none">
                    <option value="all" <?= $currentRestaurant === 'all' ? 'selected' : '' ?>>Todos los restaurantes</option>
                    <?php foreach ($restaurants as $r): ?>
                        <option value="<?= $r['id'] ?>" <?= $currentRestaurant == $r['id'] ? 'selected' : '' ?>>
                            <?= resSanitize($r['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 py-4 overflow-y-auto">
                <div class="px-4 mb-2">
                    <span class="text-xs font-semibold text-dark-500 uppercase tracking-wider">Principal</span>
                </div>
                <a href="<?= resUrl('/dashboard.php') ?>" class="sidebar-link flex items-center px-5 py-2.5 text-sm text-dark-300 hover:text-white <?= $title === 'Dashboard' ? 'active text-white' : '' ?>">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                    Dashboard
                </a>
                <a href="<?= resUrl('/reservations.php') ?>" class="sidebar-link flex items-center px-5 py-2.5 text-sm text-dark-300 hover:text-white <?= $title === 'Reservas' ? 'active text-white' : '' ?>">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    Reservas
                </a>
                <a href="<?= resUrl('/floorplan.php') ?>" class="sidebar-link flex items-center px-5 py-2.5 text-sm text-dark-300 hover:text-white <?= $title === 'Floor Plan' ? 'active text-white' : '' ?>">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/></svg>
                    Floor Plan
                </a>
                <a href="<?= resUrl('/customers.php') ?>" class="sidebar-link flex items-center px-5 py-2.5 text-sm text-dark-300 hover:text-white <?= $title === 'Clientes' ? 'active text-white' : '' ?>">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Clientes
                </a>

                <div class="px-4 mt-5 mb-2">
                    <span class="text-xs font-semibold text-dark-500 uppercase tracking-wider">Reportes</span>
                </div>
                <a href="<?= resUrl('/reports.php') ?>" class="sidebar-link flex items-center px-5 py-2.5 text-sm text-dark-300 hover:text-white <?= $title === 'Reportes' ? 'active text-white' : '' ?>">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    Reportes
                </a>

                <?php if (isResAdmin()): ?>
                <div class="px-4 mt-5 mb-2">
                    <span class="text-xs font-semibold text-dark-500 uppercase tracking-wider">Administración</span>
                </div>
                <a href="<?= resUrl('/admin/') ?>" class="sidebar-link flex items-center px-5 py-2.5 text-sm text-dark-300 hover:text-white <?= strpos($title, 'Admin') !== false ? 'active text-white' : '' ?>">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Configuración
                </a>
                <a href="<?= resUrl('/admin/integrations.php') ?>" class="sidebar-link flex items-center px-5 py-2.5 text-sm text-dark-300 hover:text-white <?= $title === 'Integraciones' ? 'active text-white' : '' ?>">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                    Integraciones
                </a>
                <a href="<?= resUrl('/admin/users.php') ?>" class="sidebar-link flex items-center px-5 py-2.5 text-sm text-dark-300 hover:text-white <?= $title === 'Usuarios' ? 'active text-white' : '' ?>">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                    Usuarios
                </a>
                <a href="<?= resUrl('/admin/concierges.php') ?>" class="sidebar-link flex items-center px-5 py-2.5 text-sm text-dark-300 hover:text-white <?= $title === 'Concierges & Socios' ? 'active text-white' : '' ?>">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    Concierges & Socios
                </a>
                <a href="<?= resUrl('/admin/availability.php') ?>" class="sidebar-link flex items-center px-5 py-2.5 text-sm text-dark-300 hover:text-white <?= $title === 'Disponibilidad' ? 'active text-white' : '' ?>">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13l-3 3m0 0l-3-3m3 3V9"/></svg>
                    Disponibilidad
                </a>
                <?php endif; ?>

                <div class="px-4 mt-5 mb-2">
                    <span class="text-xs font-semibold text-dark-500 uppercase tracking-wider">Enlace</span>
                </div>
                <a href="<?= biUrl('/dashboard.php') ?>" target="_blank" class="sidebar-link flex items-center px-5 py-2.5 text-sm text-dark-300 hover:text-white">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    Cenacolo BI
                </a>
            </nav>

            <!-- User info -->
            <div class="p-4 border-t border-dark-700">
                <div class="flex items-center">
                    <div class="w-8 h-8 rounded-full bg-gold-500 flex items-center justify-center text-dark-950 font-bold text-sm">
                        <?= strtoupper(substr($user['name'] ?? 'U', 0, 1)) ?>
                    </div>
                    <div class="ml-3 flex-1 min-w-0">
                        <p class="text-sm font-medium text-dark-200 truncate"><?= resSanitize($user['name'] ?? 'Usuario') ?></p>
                        <p class="text-xs text-dark-500"><?= $role ?></p>
                    </div>
                    <a href="<?= resUrl('/logout.php') ?>" class="text-dark-500 hover:text-red-400 transition-colors" title="Cerrar sesión">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                    </a>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content ml-64 min-h-screen">
            <!-- Top bar -->
            <header class="sticky top-0 z-30 bg-dark-900/80 backdrop-blur-md border-b border-dark-700 px-4 lg:px-6 py-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <!-- Hamburger (mobile/tablet) -->
                        <button onclick="toggleSidebar()" class="sidebar-toggle items-center justify-center w-10 h-10 rounded-lg bg-dark-800 border border-dark-600 text-dark-300" style="display:none;">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                        </button>
                        <div>
                            <h2 class="text-lg font-semibold text-white"><?= resSanitize($title) ?></h2>
                            <p class="text-xs text-dark-400"><?= date('l, d \d\e F Y', strtotime('now')) ?></p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2 lg:space-x-3">
                        <!-- Quick add reservation -->
                        <button onclick="openNewReservation()" class="flex items-center px-3 lg:px-4 py-2 bg-gold-500 text-dark-950 rounded-lg text-sm font-semibold hover:bg-gold-400 transition-colors touch-btn">
                            <svg class="w-4 h-4 lg:mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            <span class="hidden lg:inline">Nueva Reserva</span>
                        </button>
                        <!-- Offline connection status chip (populated by offline-staff.js) -->
                        <span id="connStatus" class="hidden sm:flex items-center gap-1.5 text-xs font-medium text-green-400">
                            <span class="w-2 h-2 rounded-full bg-green-400 inline-block"></span> En línea
                        </span>
                        <!-- Current time -->
                        <div class="text-sm text-dark-400 font-mono hidden sm:block" id="currentTime"></div>
                    </div>
                </div>
            </header>

            <!-- Offline banner (shown by offline-staff.js when connection is lost) -->
            <div id="offlineBanner" class="hidden bg-yellow-900 border-b border-yellow-700 text-yellow-200 px-4 py-2.5 text-sm text-center">
                📵 <strong>Sin conexión</strong> — Los cambios se guardarán y sincronizarán automáticamente al reconectar.
            </div>

            <!-- Page content -->
            <div class="p-4 lg:p-6">
    <?php
}

function renderFooter() {
    ?>
            </div>
        </main>

        <!-- Global Scripts -->
        <script>
            // Current time
            function updateTime() {
                const now = new Date();
                const time = now.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                document.getElementById('currentTime').textContent = time;
            }
            updateTime();
            setInterval(updateTime, 1000);

            // Restaurant switcher
            function switchRestaurant(value) {
                fetch('<?= resUrl('/api/switch-restaurant.php') ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ restaurant_id: value })
                }).then(() => window.location.reload());
            }

            // Open new reservation modal
            function openNewReservation() {
                window.location.href = '<?= resUrl('/reservations.php?action=new') ?>';
            }

            // Toast notification
            function showToast(message, type = 'success') {
                const toast = document.createElement('div');
                const colors = {
                    success: 'bg-green-600',
                    error: 'bg-red-600',
                    warning: 'bg-yellow-600',
                    info: 'bg-blue-600'
                };
                toast.className = `fixed bottom-4 right-4 ${colors[type]} text-white px-6 py-3 rounded-lg shadow-lg z-50 fade-in text-sm font-medium`;
                toast.textContent = message;
                document.body.appendChild(toast);
                setTimeout(() => toast.remove(), 4000);
            }

            // Confirm dialog
            function confirmAction(message) {
                return new Promise(resolve => {
                    if (confirm(message)) resolve(true);
                    else resolve(false);
                });
            }

            // Format helpers
            function formatDate(dateStr) {
                const d = new Date(dateStr + 'T00:00:00');
                return d.toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' });
            }
            function formatTime(timeStr) {
                const [h, m] = timeStr.split(':');
                const hour = parseInt(h);
                const ampm = hour >= 12 ? 'PM' : 'AM';
                const hour12 = hour % 12 || 12;
                return `${hour12}:${m} ${ampm}`;
            }

            // Status labels
            const statusLabels = {
                pending: 'Pendiente',
                confirmed: 'Confirmada',
                seated: 'Sentado',
                completed: 'Completada',
                no_show: 'No Show',
                cancelled: 'Cancelada',
                waitlist: 'Lista de espera'
            };

            // Channel labels
            const channelLabels = {
                opentable: 'OpenTable',
                website: 'Web',
                whatsapp: 'WhatsApp',
                manual: 'Manual',
                walkin: 'Walk-in',
                concierge: 'Concierge',
                instagram: 'Instagram',
                facebook: 'Facebook',
                google: 'Google',
                phone: 'Teléfono'
            };

            // Flatpickr locale
            flatpickr.localize(flatpickr.l10ns.es);

            // ===== SIDEBAR TOGGLE (mobile/tablet) =====
            function toggleSidebar() {
                const sidebar = document.getElementById('sidebar');
                const overlay = document.getElementById('sidebarOverlay');
                sidebar.classList.toggle('sidebar-open');
                overlay.classList.toggle('active');
            }

            // Close sidebar on navigation (for SPA-like feel on tablet)
            document.querySelectorAll('#sidebar a').forEach(link => {
                link.addEventListener('click', () => {
                    if (window.innerWidth < 1024) toggleSidebar();
                });
            });

            // ===== CUSTOMER INFO MODAL =====
            async function showCustomerInfo(customerId, guestName, guestPhone, guestEmail, reservationId) {
                // Create modal
                const modal = document.createElement('div');
                modal.className = 'customer-modal';
                modal.id = 'customerInfoModal';
                modal.innerHTML = `
                    <div class="modal-overlay absolute inset-0" onclick="closeCustomerModal()"></div>
                    <div class="customer-modal-content p-6 relative">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-white">Información del Cliente</h3>
                            <button onclick="closeCustomerModal()" class="w-8 h-8 rounded-full bg-dark-800 flex items-center justify-center text-dark-400 hover:text-white">✕</button>
                        </div>
                        <div class="text-center py-6 text-dark-400">Cargando...</div>
                    </div>
                `;
                document.body.appendChild(modal);

                // Fetch reservation details if available (includes concierge info)
                let resData = null;
                if (reservationId) {
                    try {
                        const rr = await fetch('<?= resUrl('/api/reservations.php') ?>?id=' + reservationId);
                        const rd = await rr.json();
                        if (rd && !rd.error) resData = rd;
                    } catch(e) {}
                }

                // Try to fetch customer data
                if (customerId) {
                    try {
                        const res = await fetch('<?= resUrl('/api/customers.php') ?>?id=' + customerId);
                        const data = await res.json();
                        if (data && !data.error) {
                            renderCustomerModal(data, resData);
                            return;
                        }
                    } catch(e) {}
                }

                // Fallback: show basic info (pass _guestName as extra backup)
                renderCustomerModal({
                    first_name: guestName?.split(' ')[0] || '',
                    last_name: guestName?.split(' ').slice(1).join(' ') || '',
                    phone: guestPhone || '',
                    email: guestEmail || '',
                    vip_level: 'standard',
                    total_visits: 0,
                    id: customerId,
                    _guestName: guestName || ''
                }, resData);
            }

            function renderCustomerModal(c, resData) {
                const content = document.querySelector('#customerInfoModal .customer-modal-content');
                if (!content) return;

                const vipBadge = c.vip_level === 'vvip' ? '<span class="px-2 py-0.5 bg-purple-500/20 text-purple-300 rounded-full text-xs font-semibold">★★ VVIP</span>'
                    : c.vip_level === 'vip' ? '<span class="px-2 py-0.5 bg-gold-500/20 text-gold-400 rounded-full text-xs font-semibold">★ VIP</span>' : '';

                // Build name: prefer customer record, then reservation guest_name, then fallback
                let fullName = ((c.first_name || '') + ' ' + (c.last_name || '')).trim();
                if (!fullName && resData && resData.guest_name) fullName = resData.guest_name;
                if (!fullName && c._guestName) fullName = c._guestName;
                if (!fullName) fullName = 'Sin nombre';
                const initial = (c.first_name || fullName || 'C')[0].toUpperCase();

                // Build reservation info section
                let reservationHtml = '';
                if (resData) {
                    const r = resData;
                    const channelLabels = {opentable:'OpenTable',website:'Website',concierge:'Concierge',affiliate:'Afiliado',whatsapp:'WhatsApp',walkin:'Walk-in',manual:'Manual',phone:'Teléfono',instagram:'Instagram',facebook:'Facebook',google:'Google'};
                    const channelLabel = channelLabels[r.channel] || r.channel || '-';
                    const channelColors = {opentable:'text-red-400',concierge:'text-gold-400',affiliate:'text-purple-400',website:'text-blue-400',whatsapp:'text-green-400',walkin:'text-purple-400'};
                    const channelColor = channelColors[r.channel] || 'text-dark-300';

                    reservationHtml = `
                    <div class="mb-5 p-3 bg-dark-800/50 rounded-lg border border-dark-700">
                        <p class="text-xs font-semibold text-dark-400 uppercase tracking-wider mb-2">Reserva</p>
                        <div class="grid grid-cols-2 gap-2 text-sm">
                            <div><span class="text-dark-500">Código:</span> <span class="text-gold-400 font-mono">${r.confirmation_code || '-'}</span></div>
                            <div><span class="text-dark-500">Pax:</span> <span class="text-white font-semibold">${r.party_size || '-'}</span></div>
                            <div><span class="text-dark-500">Canal:</span> <span class="${channelColor} font-semibold">${channelLabel}</span></div>
                            <div><span class="text-dark-500">Estado:</span> <span class="text-white">${r.status || '-'}</span></div>
                            ${r.occasion ? `<div class="col-span-2"><span class="text-dark-500">Ocasión:</span> <span class="text-gold-400">${r.occasion}</span></div>` : ''}
                            ${r.special_requests ? `<div class="col-span-2"><span class="text-dark-500">Peticiones:</span> <span class="text-dark-200">${r.special_requests}</span></div>` : ''}
                            ${r.allergies ? `<div class="col-span-2"><span class="text-red-400 font-semibold">Alergias:</span> <span class="text-red-300">${r.allergies}</span></div>` : ''}
                            ${r.source_details ? `<div class="col-span-2"><span class="text-dark-500">Fuente:</span> <span class="text-dark-200">${r.source_details}</span></div>` : ''}
                        </div>
                        ${(r.channel === 'concierge' || r.channel === 'affiliate') && r.concierge_name ? `
                        <div class="mt-3 p-2 rounded-lg ${r.channel === 'affiliate' ? 'bg-purple-900/20 border border-purple-800/30' : 'bg-gold-500/10 border border-gold-500/20'}">
                            <p class="text-xs font-semibold ${r.channel === 'affiliate' ? 'text-purple-400' : 'text-gold-400'} uppercase mb-1">
                                ${r.channel === 'affiliate' ? 'Afiliado' : 'Concierge'}
                            </p>
                            <p class="text-sm text-white font-medium">${r.concierge_name}</p>
                            ${r.concierge_hotel ? `<p class="text-xs text-dark-400">${r.concierge_hotel}</p>` : ''}
                            ${r.concierge_company ? `<p class="text-xs text-dark-400">${r.concierge_company}</p>` : ''}
                            ${r.concierge_commission ? `<p class="text-xs text-dark-400">Comisión: ${r.concierge_commission}</p>` : ''}
                        </div>` : ''}
                    </div>`;
                }

                content.innerHTML = `
                    <div class="flex items-center justify-between mb-5">
                        <h3 class="text-lg font-semibold text-white">Cliente</h3>
                        <button onclick="closeCustomerModal()" class="w-8 h-8 rounded-full bg-dark-800 flex items-center justify-center text-dark-400 hover:text-white touch-btn">✕</button>
                    </div>

                    <!-- Avatar & Name -->
                    <div class="flex items-center gap-4 mb-5">
                        <div class="w-14 h-14 rounded-full bg-gold-500 flex items-center justify-center text-dark-950 font-bold text-xl flex-shrink-0">${initial}</div>
                        <div class="min-w-0">
                            <p class="text-white font-semibold text-lg truncate">${fullName} ${vipBadge}</p>
                            ${(c.phone || (resData && resData.guest_phone)) ? `<p class="text-dark-400 text-sm"><a href="tel:${c.phone || resData.guest_phone}" class="hover:text-gold-400">📱 ${c.phone || resData.guest_phone}</a></p>` : ''}
                            ${(c.email || (resData && resData.guest_email)) ? `<p class="text-dark-400 text-sm truncate"><a href="mailto:${c.email || resData.guest_email}" class="hover:text-gold-400">✉️ ${c.email || resData.guest_email}</a></p>` : ''}
                        </div>
                    </div>

                    ${reservationHtml}

                    <!-- Stats -->
                    <div class="grid grid-cols-3 gap-3 mb-5">
                        <div class="bg-dark-800 rounded-lg p-3 text-center">
                            <p class="text-xl font-bold text-white">${c.total_visits || 0}</p>
                            <p class="text-xs text-dark-400">Visitas</p>
                        </div>
                        <div class="bg-dark-800 rounded-lg p-3 text-center">
                            <p class="text-xl font-bold text-white">${c.total_no_shows || 0}</p>
                            <p class="text-xs text-dark-400">No Shows</p>
                        </div>
                        <div class="bg-dark-800 rounded-lg p-3 text-center">
                            <p class="text-xl font-bold text-white">${c.average_party_size || '-'}</p>
                            <p class="text-xs text-dark-400">Pax Prom</p>
                        </div>
                    </div>

                    <!-- Details -->
                    <div class="space-y-2 text-sm mb-5">
                        ${c.allergies ? `<div class="flex gap-2 p-2 bg-red-900/20 rounded-lg"><span class="text-red-400 font-semibold flex-shrink-0">⚠️ Alergias:</span><span class="text-red-300">${c.allergies}</span></div>` : ''}
                        ${c.dietary_preferences ? `<div class="flex gap-2 p-2 bg-dark-800 rounded-lg"><span class="text-dark-400 flex-shrink-0">🍽️ Dieta:</span><span class="text-dark-200">${c.dietary_preferences}</span></div>` : ''}
                        ${c.preferred_section ? `<div class="flex gap-2 p-2 bg-dark-800 rounded-lg"><span class="text-dark-400 flex-shrink-0">📍 Sección:</span><span class="text-dark-200">${c.preferred_section}</span></div>` : ''}
                        ${c.notes ? `<div class="flex gap-2 p-2 bg-dark-800 rounded-lg"><span class="text-dark-400 flex-shrink-0">📝 Notas:</span><span class="text-dark-200">${c.notes}</span></div>` : ''}
                        ${c.opentable_notes ? `<div class="flex gap-2 p-2 bg-dark-800 rounded-lg"><span class="text-dark-400 flex-shrink-0">🔗 OT:</span><span class="text-dark-200">${c.opentable_notes}</span></div>` : ''}
                        ${c.last_visit_date ? `<div class="flex gap-2 p-2 bg-dark-800 rounded-lg"><span class="text-dark-400 flex-shrink-0">📅 Última visita:</span><span class="text-dark-200">${c.last_visit_date}</span></div>` : ''}
                    </div>

                    ${c.id ? `<a href="<?= resUrl('/customers.php') ?>?action=edit&id=${c.id}" class="action-btn action-btn-edit w-full justify-center touch-btn">Ver perfil completo →</a>` : ''}
                `;
            }

            function closeCustomerModal() {
                const modal = document.getElementById('customerInfoModal');
                if (modal) modal.remove();
            }

            // ===== SEAT WITH TABLE SELECTION MODAL =====
            async function seatWithTable(reservationId, partySize, restaurantId, reservationTime) {
                // Fetch available tables
                let tables = [];
                try {
                    const res = await fetch('<?= resUrl('/api/tables.php') ?>?action=floorplan&restaurant_id=' + restaurantId);
                    const data = await res.json();
                    const rawRows = data.data || data.tables || [];

                    // Group rows by table ID (LEFT JOIN creates duplicates for multi-reservation tables)
                    const tableMap = {};
                    rawRows.forEach(row => {
                        if (!tableMap[row.id]) {
                            tableMap[row.id] = { ...row, _reservations: [] };
                        }
                        if (row.reservation_id) {
                            tableMap[row.id]._reservations.push({
                                id: row.reservation_id,
                                time: row.reservation_time,
                                end_time: row.end_time,
                                status: row.res_status,
                                guest: row.guest_name,
                                party: row.res_party_size
                            });
                        }
                    });

                    // Helper: parse "HH:MM:SS" or "HH:MM" to minutes since midnight
                    function timeToMinutes(t) {
                        if (!t) return null;
                        const parts = t.split(':');
                        return parseInt(parts[0]) * 60 + parseInt(parts[1] || 0);
                    }
                    const newResMinutes = timeToMinutes(reservationTime);
                    const THREE_HOURS = 180; // minutes

                    // Filter tables
                    tables = Object.values(tableMap).filter(t => {
                        if (t.status === 'blocked' || t.status === 'maintenance') return false;
                        if (parseInt(t.max_capacity) < partySize) return false; // PAX filter

                        // Check 3-hour window against all existing reservations on this table
                        if (t._reservations.length > 0 && newResMinutes !== null) {
                            const hasConflict = t._reservations.some(r => {
                                if (r.status === 'seated') return true; // Currently occupied - unavailable
                                const existingMin = timeToMinutes(r.time);
                                if (existingMin === null) return false;
                                return Math.abs(newResMinutes - existingMin) < THREE_HOURS;
                            });
                            if (hasConflict) {
                                t._conflict = true; // Mark for UI but still could show
                                return false; // Don't show conflicting tables
                            }
                        }
                        return true;
                    });

                    // Add a flag for tables that have reservations but are 3+ hours away
                    tables.forEach(t => {
                        t._hasFarReservation = t._reservations.length > 0 && t._reservations.some(r => r.status !== 'seated');
                    });
                } catch(e) {
                    console.error('Error loading tables:', e);
                }

                // Group by section
                const sections = {};
                tables.forEach(t => {
                    const sec = t.section_name || 'Sin sección';
                    if (!sections[sec]) sections[sec] = [];
                    sections[sec].push(t);
                });

                const modal = document.createElement('div');
                modal.className = 'customer-modal';
                modal.id = 'seatTableModal';

                let sectionsHtml = '';
                for (const [secName, secTables] of Object.entries(sections)) {
                    sectionsHtml += `<div class="mb-4">
                        <p class="text-xs font-semibold text-dark-400 uppercase tracking-wider mb-2">${secName}</p>
                        <div class="seat-modal-grid">
                            ${secTables.map(t => {
                                const hasFarRes = t._hasFarReservation;
                                const nextResInfo = hasFarRes ? t._reservations.filter(r => r.status !== 'seated').map(r => r.time?.substring(0,5)).join(', ') : '';
                                return `<button class="seat-table-btn ${hasFarRes ? 'ring-1 ring-gold-500/30' : ''}" onclick="confirmSeat(${reservationId}, ${t.id})" title="${t.min_capacity}-${t.max_capacity} personas${hasFarRes ? ' (reserva a las ' + nextResInfo + ')' : ''}">
                                    <span class="text-lg font-bold">${t.table_number}</span>
                                    <span class="text-xs text-dark-400">${t.min_capacity}-${t.max_capacity}p</span>
                                    ${hasFarRes ? '<span class="text-xs text-gold-400">' + nextResInfo + '</span>' : (t.shape === 'round' ? '<span class="text-xs text-dark-500">●</span>' : '<span class="text-xs text-dark-500">■</span>')}
                                </button>`;
                            }).join('')}
                        </div>
                    </div>`;
                }

                if (!sectionsHtml) {
                    sectionsHtml = '<p class="text-dark-400 text-center py-4">No hay mesas disponibles</p>';
                }

                modal.innerHTML = `
                    <div class="modal-overlay absolute inset-0" onclick="closeSeatModal()"></div>
                    <div class="customer-modal-content p-6 relative">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-lg font-semibold text-white">Seleccionar Mesa</h3>
                                <p class="text-xs text-dark-400">${partySize} personas</p>
                            </div>
                            <button onclick="closeSeatModal()" class="w-8 h-8 rounded-full bg-dark-800 flex items-center justify-center text-dark-400 hover:text-white touch-btn">✕</button>
                        </div>

                        ${sectionsHtml}

                        <div class="mt-4 pt-4 border-t border-dark-700">
                            <button onclick="confirmSeat(${reservationId}, null)" class="action-btn action-btn-seat w-full justify-center touch-btn">
                                Sentar sin asignar mesa
                            </button>
                        </div>
                    </div>
                `;
                document.body.appendChild(modal);
            }

            // ===== ASSIGN TABLE ONLY (sin sentar) =====
            async function assignTableOnly(reservationId, partySize, restaurantId, reservationTime) {
                // Reutiliza la misma lógica de seatWithTable pero llama a confirmAssignTable
                let tables = [];
                try {
                    const res = await fetch('<?= resUrl('/api/tables.php') ?>?action=floorplan&restaurant_id=' + restaurantId);
                    const data = await res.json();
                    const rawRows = data.data || data.tables || [];

                    const tableMap = {};
                    rawRows.forEach(row => {
                        if (!tableMap[row.id]) {
                            tableMap[row.id] = { ...row, _reservations: [] };
                        }
                        if (row.reservation_id) {
                            tableMap[row.id]._reservations.push({
                                id: row.reservation_id,
                                time: row.reservation_time,
                                end_time: row.end_time,
                                status: row.res_status,
                                guest: row.guest_name,
                                party: row.res_party_size
                            });
                        }
                    });

                    function timeToMinutes(t) {
                        if (!t) return null;
                        const parts = t.split(':');
                        return parseInt(parts[0]) * 60 + parseInt(parts[1] || 0);
                    }
                    const newResMinutes = timeToMinutes(reservationTime);
                    const THREE_HOURS = 180;

                    tables = Object.values(tableMap).filter(t => {
                        if (t.status === 'blocked' || t.status === 'maintenance') return false;
                        if (parseInt(t.max_capacity) < partySize) return false;
                        if (t._reservations.length > 0 && newResMinutes !== null) {
                            const hasConflict = t._reservations.some(r => {
                                if (r.status === 'seated') return true;
                                const existingMin = timeToMinutes(r.time);
                                if (existingMin === null) return false;
                                return Math.abs(newResMinutes - existingMin) < THREE_HOURS;
                            });
                            if (hasConflict) return false;
                        }
                        return true;
                    });

                    tables.forEach(t => {
                        t._hasFarReservation = t._reservations.length > 0 && t._reservations.some(r => r.status !== 'seated');
                    });
                } catch(e) {
                    console.error('Error loading tables:', e);
                }

                const sections = {};
                tables.forEach(t => {
                    const sec = t.section_name || 'Sin sección';
                    if (!sections[sec]) sections[sec] = [];
                    sections[sec].push(t);
                });

                const modal = document.createElement('div');
                modal.className = 'customer-modal';
                modal.id = 'seatTableModal';

                let sectionsHtml = '';
                for (const [secName, secTables] of Object.entries(sections)) {
                    sectionsHtml += `<div class="mb-4">
                        <p class="text-xs font-semibold text-dark-400 uppercase tracking-wider mb-2">${secName}</p>
                        <div class="seat-modal-grid">
                            ${secTables.map(t => {
                                const hasFarRes = t._hasFarReservation;
                                const nextResInfo = hasFarRes ? t._reservations.filter(r => r.status !== 'seated').map(r => r.time?.substring(0,5)).join(', ') : '';
                                return `<button class="seat-table-btn ${hasFarRes ? 'ring-1 ring-gold-500/30' : ''}" onclick="confirmAssignTable(${reservationId}, ${t.id}, '${t.table_number}')" title="${t.min_capacity}-${t.max_capacity} personas${hasFarRes ? ' (reserva a las ' + nextResInfo + ')' : ''}">
                                    <span class="text-lg font-bold">${t.table_number}</span>
                                    <span class="text-xs text-dark-400">${t.min_capacity}-${t.max_capacity}p</span>
                                    ${hasFarRes ? '<span class="text-xs text-gold-400">' + nextResInfo + '</span>' : (t.shape === 'round' ? '<span class="text-xs text-dark-500">●</span>' : '<span class="text-xs text-dark-500">■</span>')}
                                </button>`;
                            }).join('')}
                        </div>
                    </div>`;
                }

                if (!sectionsHtml) {
                    sectionsHtml = '<p class="text-dark-400 text-center py-4">No hay mesas disponibles para esta capacidad y horario</p>';
                }

                modal.innerHTML = `
                    <div class="modal-overlay absolute inset-0" onclick="closeSeatModal()"></div>
                    <div class="customer-modal-content p-6 relative">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-lg font-semibold text-white">Asignar Mesa</h3>
                                <p class="text-xs text-dark-400">${partySize} personas · Solo asigna, no sienta al cliente</p>
                            </div>
                            <button onclick="closeSeatModal()" class="w-8 h-8 rounded-full bg-dark-800 flex items-center justify-center text-dark-400 hover:text-white touch-btn">✕</button>
                        </div>
                        ${sectionsHtml}
                    </div>
                `;
                document.body.appendChild(modal);
            }

            // Solo asigna table_id sin cambiar status
            async function confirmAssignTable(reservationId, tableId, tableNumber) {
                closeSeatModal();
                try {
                    const body = { id: reservationId, table_id: tableId };
                    const res = await fetch('<?= resUrl('/api/reservations.php') ?>', {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(body)
                    });
                    const data = await res.json();
                    if (data.success) {
                        showToast('Mesa ' + (tableNumber || tableId) + ' asignada');
                        setTimeout(() => window.location.reload(), 500);
                    } else {
                        showToast(data.error || 'Error al asignar mesa', 'error');
                    }
                } catch(e) {
                    showToast('Error de conexión', 'error');
                }
            }

            // Sentar: cambia status a seated (la mesa ya debería estar asignada o se pasa aquí)
            async function confirmSeat(reservationId, tableId) {
                closeSeatModal();
                try {
                    const body = { id: reservationId, status: 'seated' };
                    if (tableId) body.table_id = tableId;

                    const res = await fetch('<?= resUrl('/api/reservations.php') ?>', {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(body)
                    });
                    const data = await res.json();
                    if (data.success) {
                        showToast(tableId ? 'Sentado en mesa ' + tableId : 'Sentado correctamente');
                        setTimeout(() => window.location.reload(), 500);
                    } else {
                        showToast(data.error || 'Error al sentar', 'error');
                    }
                } catch(e) {
                    showToast('Error de conexión', 'error');
                }
            }

            // Reasignar/cambiar mesa (para reservas ya asignadas o sentadas)
            async function reassignTable(reservationId, partySize, restaurantId, reservationTime) {
                return assignTableOnly(reservationId, partySize, restaurantId, reservationTime);
            }

            function closeSeatModal() {
                const modal = document.getElementById('seatTableModal');
                if (modal) modal.remove();
            }
        </script>
    </body>
    </html>
    <?php
}
