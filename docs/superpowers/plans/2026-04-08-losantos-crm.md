# LOS SANTOS CRM — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fork `altai-crm` como `losantos-crm`, reemplazando todo el branding AC/JL/ALTAI por ETS/LOS SANTOS y actualizando todos los datos del lote.

**Architecture:** Clon directo de `altai-crm` con cambios de datos y branding. Mismo stack Laravel + Livewire + MySQL + N8N + Evolution API. Proyectos independientes, sin código compartido.

**Tech Stack:** Laravel 11, Livewire 3, MySQL, Tailwind CSS, N8N, Evolution API, GPT-4o-mini

---

## File Map

| Archivo | Acción | Razón |
|---------|--------|-------|
| `losantos-crm/` | Crear (fork) | Directorio raíz del proyecto |
| `config/altai.php` | Eliminar | Reemplazado por losantos.php |
| `config/losantos.php` | Crear | Config del proyecto con datos de Los Santos |
| `resources/views/components/layouts/app.blade.php` | Modificar | Colores sidebar (verde → azul), brand ALTAI → LOS SANTOS |
| `resources/views/layouts/guest.blade.php` | Modificar | Colores login, AC/JL → ETS |
| `database/seeders/UserSeeder.php` | Modificar | Ana Caro / Jose Luis → Eduardo Torreblanca |
| `docs/n8n/losantos-whatsapp-agent-workflow.json` | Crear | Workflow N8N adaptado a Los Santos |
| `docs/LOSANTOS-Lote15-Marketing.md` | Crear | Ficha de marketing del lote |
| `DEPLOY.md` | Modificar | Instrucciones de deploy para losantosCRM |

---

## Task 1: Fork del proyecto

**Files:**
- Crear: `losantos-crm/` (copia de `altai-crm/`)

- [ ] **Step 1.1: Copiar proyecto**

```bash
cd "/Users/fernando.diaz/Downloads/SINERGIA Proyectos"
cp -r altai-crm losantos-crm
```

- [ ] **Step 1.2: Entrar al directorio y limpiar git**

```bash
cd "/Users/fernando.diaz/Downloads/SINERGIA Proyectos/losantos-crm"
rm -rf .git
git init
git add -A
git commit -m "init: fork de altai-crm como losantos-crm"
```

- [ ] **Step 1.3: Verificar estructura**

```bash
ls -la "/Users/fernando.diaz/Downloads/SINERGIA Proyectos/losantos-crm"
```

Esperado: mismos archivos que `altai-crm/` (composer.json, artisan, app/, config/, etc.)

---

## Task 2: Reemplazar configuración ALTAI → LOS SANTOS

**Files:**
- Eliminar: `config/altai.php`
- Crear: `config/losantos.php`

- [ ] **Step 2.1: Eliminar config/altai.php**

```bash
rm "/Users/fernando.diaz/Downloads/SINERGIA Proyectos/losantos-crm/config/altai.php"
```

- [ ] **Step 2.2: Crear config/losantos.php**

Crear `config/losantos.php` con el siguiente contenido:

```php
<?php

return [
    'name'    => 'LOS SANTOS CRM',
    'company' => 'Los Santos Residencial',
    'version' => '1.0.0',

    'colors' => [
        'azul_caribe'  => '#0077B6',  // Primary — CTAs
        'turquesa'     => '#90E0EF',  // Fondos, frescura
        'arena_blanca' => '#F5F0E8',  // Backgrounds
        'verde_selva'  => '#2D6A4F',  // Acento — selva maya
        'gris_carbon'  => '#333333',  // Textos
    ],

    'defaults' => [
        'currency'    => 'MXN',
        'timezone'    => 'America/Mexico_City',
        'date_format' => 'd/m/Y',
        'per_page'    => 25,
    ],

    'etapas' => [
        'prospecto'     => ['label' => 'Prospecto',     'color' => '#3B82F6'],
        'interesado'    => ['label' => 'Interesado',    'color' => '#22C55E'],
        'no_interesado' => ['label' => 'No Interesado', 'color' => '#EF4444'],
    ],

    'lead_sources' => [
        'whatsapp' => 'WhatsApp',
        'website'  => 'Sitio Web',
        'referido' => 'Referido',
        'otro'     => 'Otro',
    ],

    'escalamiento' => [
        'nombre'   => 'Eduardo Torreblanca',
        'telefono' => 'PENDIENTE', // Actualizar con número nuevo de WhatsApp ETS
    ],
];
```

- [ ] **Step 2.3: Commit**

```bash
cd "/Users/fernando.diaz/Downloads/SINERGIA Proyectos/losantos-crm"
git add config/
git commit -m "feat: config losantos.php con datos de Los Santos Lote 15"
```

---

## Task 3: Actualizar layout principal (sidebar y colores)

**Files:**
- Modificar: `resources/views/components/layouts/app.blade.php`

- [ ] **Step 3.1: Reemplazar app.blade.php completo**

Reemplazar el contenido de `resources/views/components/layouts/app.blade.php`:

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? 'LOS SANTOS CRM' }} - {{ config('app.name', 'LOS SANTOS CRM') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles

        <style>
            /* Mobile-first responsive */
            .crm-sidebar {
                position: fixed;
                top: 0;
                left: 0;
                bottom: 0;
                width: 256px;
                z-index: 50;
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            .crm-sidebar.open {
                transform: translateX(0);
            }
            .crm-overlay {
                display: none;
                position: fixed;
                inset: 0;
                background: rgba(0,0,0,0.5);
                z-index: 40;
            }
            .crm-overlay.open {
                display: block;
            }
            .crm-main {
                margin-left: 0;
                min-height: 100vh;
            }
            .crm-mobile-header {
                display: flex;
            }
            /* Desktop */
            @media (min-width: 1024px) {
                .crm-sidebar {
                    position: fixed;
                    transform: translateX(0);
                }
                .crm-main {
                    margin-left: 256px;
                }
                .crm-mobile-header .crm-hamburger {
                    display: none;
                }
                .crm-overlay {
                    display: none !important;
                }
            }
            /* Mobile table → cards */
            @media (max-width: 767px) {
                .crm-table thead { display: none; }
                .crm-table tbody tr {
                    display: block;
                    padding: 12px 16px;
                    border-bottom: 1px solid #e5e7eb;
                }
                .crm-table tbody td {
                    display: flex;
                    justify-content: space-between;
                    padding: 4px 0;
                    border: none;
                }
                .crm-table tbody td::before {
                    content: attr(data-label);
                    font-weight: 500;
                    color: #6b7280;
                    margin-right: 8px;
                }
                .crm-table tbody td.td-name {
                    font-size: 1rem;
                    font-weight: 600;
                    display: block;
                    padding-bottom: 8px;
                }
                .crm-table tbody td.td-name::before { display: none; }
                /* Hide less important columns on mobile */
                .crm-table tbody td.td-hide-mobile { display: none; }
            }
            /* Mobile detail - single column */
            @media (max-width: 1023px) {
                .crm-detail-grid {
                    display: flex !important;
                    flex-direction: column;
                }
            }
            /* Chat bubbles mobile */
            @media (max-width: 640px) {
                .chat-bubble { max-width: 90% !important; }
            }
        </style>
    </head>
    <body class="font-sans antialiased" x-data="{ sidebarOpen: false }">
        {{-- Overlay --}}
        <div class="crm-overlay" :class="{ 'open': sidebarOpen }" @click="sidebarOpen = false"></div>

        {{-- Sidebar --}}
        <aside class="crm-sidebar flex flex-col bg-[#0077B6] text-white" :class="{ 'open': sidebarOpen }">
            {{-- Logo / Brand --}}
            <div class="flex h-16 items-center justify-between border-b border-blue-800 px-6">
                <div>
                    <span class="text-xl font-bold tracking-wide text-[#90E0EF]">LOS SANTOS</span>
                    <span class="ml-1 text-sm font-medium text-blue-100">CRM</span>
                </div>
                {{-- Close button (mobile) --}}
                <button @click="sidebarOpen = false" class="lg:hidden rounded p-1 text-blue-200 hover:text-white">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{-- Navigation --}}
            <nav class="mt-4 flex-1 space-y-1 px-3">
                <a href="{{ route('dashboard') }}" @click="sidebarOpen = false"
                   class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition
                          {{ request()->routeIs('dashboard') ? 'bg-blue-800/60 text-white' : 'text-blue-100 hover:bg-blue-800/40 hover:text-white' }}">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                    </svg>
                    Dashboard
                </a>

                <a href="{{ route('contactos.index') }}" @click="sidebarOpen = false"
                   class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition
                          {{ request()->routeIs('contactos.*') ? 'bg-blue-800/60 text-white' : 'text-blue-100 hover:bg-blue-800/40 hover:text-white' }}">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                    </svg>
                    Contactos
                </a>

                <a href="{{ route('pipeline') }}" @click="sidebarOpen = false"
                   class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition
                          {{ request()->routeIs('pipeline') ? 'bg-blue-800/60 text-white' : 'text-blue-100 hover:bg-blue-800/40 hover:text-white' }}">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 4.5v15m6-15v15m-10.875 0h15.75c.621 0 1.125-.504 1.125-1.125V5.625c0-.621-.504-1.125-1.125-1.125H4.125C3.504 4.5 3 5.004 3 5.625v12.75c0 .621.504 1.125 1.125 1.125z" />
                    </svg>
                    Pipeline
                </a>

                @if(auth()->user()?->isAdmin())
                <a href="{{ route('settings.index') }}" @click="sidebarOpen = false"
                   class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition
                          {{ request()->routeIs('settings.*') ? 'bg-blue-800/60 text-white' : 'text-blue-100 hover:bg-blue-800/40 hover:text-white' }}">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    Configuraci&oacute;n
                </a>
                @endif
            </nav>

            {{-- User section --}}
            <div class="border-t border-blue-800 px-4 py-3">
                <div class="flex items-center justify-between">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-white">{{ auth()->user()?->name ?? 'Usuario' }}</p>
                        <p class="truncate text-xs text-blue-200">{{ auth()->user()?->email ?? '' }}</p>
                    </div>
                    <livewire:actions.logout />
                </div>
            </div>
        </aside>

        {{-- Main content --}}
        <div class="crm-main flex-1 bg-[#F5F0E8]">
            {{-- Top bar with hamburger --}}
            <header class="crm-mobile-header flex h-14 items-center border-b border-gray-200 bg-white px-4 shadow-sm lg:h-16 lg:px-6">
                <button @click="sidebarOpen = true" class="crm-hamburger mr-3 rounded-lg p-2 text-gray-500 hover:bg-gray-100 lg:hidden">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </button>
                <h1 class="text-base font-semibold text-[#333333] lg:text-lg">{{ $title ?? 'Dashboard' }}</h1>
            </header>

            {{-- Page content --}}
            <div class="p-4 lg:p-6">
                {{ $slot }}
            </div>
        </div>

        @livewireScripts
    </body>
</html>
```

- [ ] **Step 3.2: Commit**

```bash
cd "/Users/fernando.diaz/Downloads/SINERGIA Proyectos/losantos-crm"
git add resources/views/components/layouts/app.blade.php
git commit -m "feat: sidebar azul caribe, brand LOS SANTOS CRM"
```

---

## Task 4: Actualizar layout de login (guest)

**Files:**
- Modificar: `resources/views/layouts/guest.blade.php`

- [ ] **Step 4.1: Reemplazar guest.blade.php**

Reemplazar el contenido de `resources/views/layouts/guest.blade.php`:

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div style="min-height: 100vh; display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 24px 0; background-color: #F5F0E8; font-family: 'Figtree', sans-serif;">
            <div style="margin-bottom: 16px;">
                <a href="/">
                    <img src="{{ asset('images/logo-ets.png') }}" alt="ETS" style="height: 96px; width: auto;"
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='block'">
                    <span style="display:none; font-size: 1.5rem; font-weight: 700; color: #0077B6; letter-spacing: 0.05em;">LOS SANTOS</span>
                </a>
            </div>

            <div style="width: 100%; max-width: 448px; padding: 24px 32px; background: white; box-shadow: 0 10px 15px -3px rgba(0,0,0,.1); overflow: hidden; border-radius: 12px; border-top: 4px solid #0077B6;">
                <h2 style="text-align: center; font-size: 1.25rem; font-weight: 600; margin-bottom: 24px; color: #0077B6;">ETS CRM</h2>
                {{ $slot }}
            </div>

            <p style="margin-top: 24px; font-size: 0.875rem; color: #333333;">Powered by Sinergia</p>
        </div>
    </body>
</html>
```

> Nota: El logo `public/images/logo-ets.png` se agrega cuando ETS proporcione su logo. El `onerror` muestra el texto "LOS SANTOS" como fallback.

- [ ] **Step 4.2: Commit**

```bash
cd "/Users/fernando.diaz/Downloads/SINERGIA Proyectos/losantos-crm"
git add resources/views/layouts/guest.blade.php
git commit -m "feat: login page ETS CRM con paleta azul caribe"
```

---

## Task 5: Actualizar UserSeeder (Ana Caro / Jose Luis → Eduardo Torreblanca)

**Files:**
- Modificar: `database/seeders/UserSeeder.php`

- [ ] **Step 5.1: Reemplazar UserSeeder.php**

Reemplazar el contenido de `database/seeders/UserSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name'     => 'Sinergia Admin',
                'email'    => 'admin@sinergia.com',
                'password' => Hash::make('SinergiaLosantos2026!'),
                'role'     => 'admin',
            ],
            [
                'name'     => 'Eduardo Torreblanca',
                'email'    => 'eduardo@sinergia.com',
                'password' => Hash::make('EduardoETS2026!'),
                'role'     => 'usuario',
            ],
        ];

        foreach ($users as $userData) {
            User::updateOrCreate(
                ['email' => $userData['email']],
                $userData
            );
        }
    }
}
```

- [ ] **Step 5.2: Commit**

```bash
cd "/Users/fernando.diaz/Downloads/SINERGIA Proyectos/losantos-crm"
git add database/seeders/UserSeeder.php
git commit -m "feat: usuarios ETS (Eduardo Torreblanca), eliminar Ana Caro y Jose Luis"
```

---

## Task 6: Crear workflow N8N para Los Santos

**Files:**
- Crear: `docs/n8n/losantos-whatsapp-agent-workflow.json`

- [ ] **Step 6.1: Crear el archivo JSON del workflow**

Crear `docs/n8n/losantos-whatsapp-agent-workflow.json` con el siguiente contenido:

```json
{
  "name": "LOS SANTOS CRM WhatsApp Agent",
  "nodes": [
    {
      "parameters": {
        "httpMethod": "POST",
        "path": "losantos-whatsapp",
        "responseMode": "lastNode",
        "options": {}
      },
      "type": "n8n-nodes-base.webhook",
      "typeVersion": 1,
      "position": [-900, -200],
      "id": "f47ac10b-58cc-4372-a567-0e02b2c3d479",
      "name": "Webhook Evolution API"
    },
    {
      "parameters": {
        "jsCode": "const body = $input.first().json.body || $input.first().json;\nconst remoteJid = body?.data?.key?.remoteJid || '';\nconst rawPhone = remoteJid.replace('@s.whatsapp.net', '').replace('@g.us', '');\nlet phone = rawPhone;\nif (phone && !phone.startsWith('+')) phone = '+' + phone;\nconst message = body?.data?.message?.conversation || body?.data?.message?.extendedTextMessage?.text || body?.data?.message?.imageMessage?.caption || '';\nconst pushName = body?.data?.pushName || '';\nconst instance = body?.instance || '';\nconst isTextMessage = message.length > 0;\nreturn [{ json: { phone, message, pushName, instance, isTextMessage, rawPayload: body } }];"
      },
      "type": "n8n-nodes-base.code",
      "typeVersion": 1,
      "position": [-640, -200],
      "id": "550e8400-e29b-41d4-a716-446655440001",
      "name": "Extract Data"
    },
    {
      "parameters": {
        "conditions": {
          "string": [
            {
              "value1": "={{ $json.message }}",
              "operation": "isNotEmpty"
            }
          ]
        }
      },
      "type": "n8n-nodes-base.if",
      "typeVersion": 1,
      "position": [-400, -200],
      "id": "550e8400-e29b-41d4-a716-446655440002",
      "name": "Is Text Message"
    },
    {
      "parameters": {
        "url": "=https://www.punch.com.mx/losantosCRM/api/v1/contactos/buscar?telefono={{ encodeURIComponent($json.phone) }}",
        "authentication": "genericCredentialType",
        "genericAuthType": "httpHeaderAuth",
        "sendHeaders": true,
        "headerParameters": {
          "parameters": [
            {
              "name": "X-API-Key",
              "value": "REEMPLAZAR_CON_TOKEN_LOSANTOS"
            }
          ]
        },
        "options": {
          "allowUnauthorizedCerts": false,
          "never": "error"
        }
      },
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 3,
      "position": [-140, -200],
      "id": "550e8400-e29b-41d4-a716-446655440003",
      "name": "Search Contact CRM"
    },
    {
      "parameters": {
        "conditions": {
          "boolean": [
            {
              "value1": "={{ $json.found }}",
              "operation": "equal",
              "value2": true
            }
          ]
        }
      },
      "type": "n8n-nodes-base.if",
      "typeVersion": 1,
      "position": [120, -200],
      "id": "550e8400-e29b-41d4-a716-446655440004",
      "name": "Contact Exists"
    },
    {
      "parameters": {
        "conditions": {
          "string": [
            {
              "value1": "={{ $json.etapa }}",
              "operation": "equals",
              "value2": "interesado"
            }
          ]
        }
      },
      "type": "n8n-nodes-base.if",
      "typeVersion": 1,
      "position": [380, -360],
      "id": "550e8400-e29b-41d4-a716-446655440005",
      "name": "Already Interesado"
    },
    {
      "parameters": {
        "method": "POST",
        "url": "=YOUR_EVOLUTION_API_URL/message/sendText/YOUR_LOSANTOS_INSTANCE",
        "sendHeaders": true,
        "headerParameters": {
          "parameters": [
            {
              "name": "apikey",
              "value": "YOUR_EVOLUTION_API_KEY"
            },
            {
              "name": "Content-Type",
              "value": "application/json"
            }
          ]
        },
        "sendBody": true,
        "bodyParameters": {
          "parameters": []
        },
        "specifyBody": "json",
        "jsonBody": "={\n  \"number\": \"{{ $('Extract Data').item.json.phone }}\",\n  \"text\": \"Ya estás en proceso con el vendedor. Si tienes alguna duda adicional, con gusto te ayudo.\"\n}",
        "options": {}
      },
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 3,
      "position": [660, -500],
      "id": "550e8400-e29b-41d4-a716-446655440006",
      "name": "Reply Already In Process"
    },
    {
      "parameters": {
        "promptType": "define",
        "text": "={{ $('Extract Data').item.json.message }}",
        "options": {
          "systemMessage": "Eres el asistente de ventas de un terreno residencial en Los Santos Cancún.\nTu objetivo es atender prospectos por WhatsApp, resolver sus dudas y detectar si tienen interés real de compra.\n\n## INFORMACIÓN DEL TERRENO\n- Desarrollo: Los Santos Cancún\n- Lote: #15, Manzana 05, Calle Isla Contoy\n- Superficie: 311.09 m²\n- Precio: $3,000,000 MXN\n- Financiamiento: 48 meses sin intereses (48 MSI)\n\n## AMENIDADES DEL RESIDENCIAL\n- Clubhouse de 8,000 m²\n- Alberca\n- Gimnasio\n- Salón de juegos\n- Salón multiusos\n- Área de BBQ\n- Canchas de pádel\n- Terraza\n- Caseta de seguridad doble\n- Cerca perimetral\n- Estacionamiento para visitantes\n- Entorno de vegetación de selva maya\n\n## TU COMPORTAMIENTO\n1. PRIMER CONTACTO: Saluda, preséntate como asistente de ventas del terreno en Los Santos Cancún, pide nombre completo y correo electrónico.\n2. CONVERSACIÓN: Responde dudas con la info de arriba. Si no sabes algo: \"Déjame confirmar ese dato y te respondo a la brevedad.\" NO inventes.\n3. EVALÚA INTERÉS: Señales de interés real: pregunta por precio o proceso de compra, confirma presupuesto, pide visita, pregunta sobre escrituración o financiamiento.\n4. ESCALAMIENTO cuando detectes interés real: \"¡Muchas gracias por tu interés en nuestro terreno en Los Santos! Con tus datos, el vendedor se pondrá en contacto contigo para darte seguimiento personalizado. ¿Tienes alguna otra duda mientras tanto?\"\n5. NO INTERESADO: \"Entiendo perfectamente. Si cambias de opinión, con gusto te atendemos. ¡Que tengas excelente día!\"\n\n## REGLAS ESTRICTAS\n- Español mexicano natural, profesional pero cercano\n- Respuestas cortas (2-4 oraciones máximo)\n- NUNCA inventes datos que no estén aquí\n- NUNCA compartas datos de contacto del vendedor\n- Máximo 1-2 emojis por mensaje\n\n## CLASIFICACIÓN — agrega SIEMPRE al final en línea separada:\n[INTENT:NEED_DATA] si aún no ha dado nombre o email\n[INTENT:CHATTING] si es conversación normal\n[INTENT:INTERESTED] si muestra interés real de compra\n[INTENT:NOT_INTERESTED] si dice que no le interesa"
        }
      },
      "type": "@n8n/n8n-nodes-langchain.agent",
      "typeVersion": 1.6,
      "position": [660, -260],
      "id": "550e8400-e29b-41d4-a716-446655440007",
      "name": "AI Agent"
    },
    {
      "parameters": {
        "model": "gpt-4o-mini",
        "options": {}
      },
      "type": "@n8n/n8n-nodes-langchain.lmChatOpenAi",
      "typeVersion": 1,
      "position": [560, -60],
      "id": "550e8400-e29b-41d4-a716-446655440008",
      "name": "OpenAI Chat Model"
    },
    {
      "parameters": {
        "sessionKey": "={{ $('Extract Data').item.json.phone }}",
        "contextWindowLength": 20
      },
      "type": "@n8n/n8n-nodes-langchain.memoryBufferWindow",
      "typeVersion": 1.1,
      "position": [760, -60],
      "id": "550e8400-e29b-41d4-a716-446655440009",
      "name": "Window Buffer Memory"
    },
    {
      "parameters": {
        "jsCode": "const agentOutput = $input.first().json.output || $input.first().json.text || '';\nconst intentMatch = agentOutput.match(/\\[INTENT:(NEED_DATA|CHATTING|INTERESTED|NOT_INTERESTED)\\]/);\nconst intent = intentMatch ? intentMatch[1] : 'CHATTING';\nconst responseText = agentOutput.replace(/\\[INTENT:(NEED_DATA|CHATTING|INTERESTED|NOT_INTERESTED)\\]/, '').trim();\nconst extractNode = $('Extract Data').item.json;\nconst searchNode = $('Search Contact CRM').item.json;\nconst contactId = searchNode?.id || null;\nconst nombre = searchNode?.nombre || extractNode.pushName || '';\nconst phone = extractNode.phone;\nreturn [{ json: { responseText, intent, contactId, phone, nombre, isNewContact: !contactId } }];"
      },
      "type": "n8n-nodes-base.code",
      "typeVersion": 1,
      "position": [960, -260],
      "id": "550e8400-e29b-41d4-a716-44665544000a",
      "name": "Parse Response"
    },
    {
      "parameters": {
        "rules": {
          "rules": [
            {
              "operation": "equals",
              "value2": "NEED_DATA",
              "output": 0
            },
            {
              "operation": "equals",
              "value2": "CHATTING",
              "output": 1
            },
            {
              "operation": "equals",
              "value2": "INTERESTED",
              "output": 2
            },
            {
              "operation": "equals",
              "value2": "NOT_INTERESTED",
              "output": 3
            }
          ]
        },
        "dataType": "string",
        "value1": "={{ $json.intent }}"
      },
      "type": "n8n-nodes-base.switch",
      "typeVersion": 1,
      "position": [1220, -260],
      "id": "550e8400-e29b-41d4-a716-44665544000b",
      "name": "Intent Router"
    },
    {
      "parameters": {
        "method": "POST",
        "url": "https://www.punch.com.mx/losantosCRM/api/v1/contactos",
        "sendHeaders": true,
        "headerParameters": {
          "parameters": [
            {
              "name": "X-API-Key",
              "value": "REEMPLAZAR_CON_TOKEN_LOSANTOS"
            },
            {
              "name": "Content-Type",
              "value": "application/json"
            }
          ]
        },
        "sendBody": true,
        "specifyBody": "json",
        "jsonBody": "={\n  \"telefono\": \"{{ $('Parse Response').item.json.phone }}\",\n  \"nombre\": \"{{ $('Parse Response').item.json.nombre }}\",\n  \"fuente\": \"whatsapp\",\n  \"etapa\": \"prospecto\"\n}",
        "options": {}
      },
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 3,
      "position": [1500, -440],
      "id": "550e8400-e29b-41d4-a716-44665544000c",
      "name": "Create Contact CRM"
    },
    {
      "parameters": {
        "method": "PUT",
        "url": "=https://www.punch.com.mx/losantosCRM/api/v1/contactos/{{ $('Parse Response').item.json.contactId }}/etapa",
        "sendHeaders": true,
        "headerParameters": {
          "parameters": [
            {
              "name": "X-API-Key",
              "value": "REEMPLAZAR_CON_TOKEN_LOSANTOS"
            },
            {
              "name": "Content-Type",
              "value": "application/json"
            }
          ]
        },
        "sendBody": true,
        "specifyBody": "json",
        "jsonBody": "{\n  \"etapa\": \"interesado\",\n  \"notas\": \"Interés detectado por agente WhatsApp AI\"\n}",
        "options": {}
      },
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 3,
      "position": [1500, -200],
      "id": "550e8400-e29b-41d4-a716-44665544000d",
      "name": "Update Interesado"
    },
    {
      "parameters": {
        "method": "PUT",
        "url": "=https://www.punch.com.mx/losantosCRM/api/v1/contactos/{{ $('Parse Response').item.json.contactId }}/etapa",
        "sendHeaders": true,
        "headerParameters": {
          "parameters": [
            {
              "name": "X-API-Key",
              "value": "REEMPLAZAR_CON_TOKEN_LOSANTOS"
            },
            {
              "name": "Content-Type",
              "value": "application/json"
            }
          ]
        },
        "sendBody": true,
        "specifyBody": "json",
        "jsonBody": "{\n  \"etapa\": \"no_interesado\",\n  \"notas\": \"No interesado según conversación WhatsApp\"\n}",
        "options": {}
      },
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 3,
      "position": [1500, 20],
      "id": "550e8400-e29b-41d4-a716-44665544000e",
      "name": "Update No Interesado"
    },
    {
      "parameters": {
        "method": "POST",
        "url": "=https://www.punch.com.mx/losantosCRM/api/v1/contactos/{{ $('Parse Response').item.json.contactId }}/actividades",
        "sendHeaders": true,
        "headerParameters": {
          "parameters": [
            {
              "name": "X-API-Key",
              "value": "REEMPLAZAR_CON_TOKEN_LOSANTOS"
            },
            {
              "name": "Content-Type",
              "value": "application/json"
            }
          ]
        },
        "sendBody": true,
        "specifyBody": "json",
        "jsonBody": "={\n  \"tipo\": \"whatsapp\",\n  \"descripcion\": \"WhatsApp AI - Intent: {{ $('Parse Response').item.json.intent }}\",\n  \"automatico\": true\n}",
        "options": {}
      },
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 3,
      "position": [1780, -320],
      "id": "550e8400-e29b-41d4-a716-44665544000f",
      "name": "Log Activity CRM"
    },
    {
      "parameters": {
        "method": "POST",
        "url": "=YOUR_EVOLUTION_API_URL/message/sendText/YOUR_LOSANTOS_INSTANCE",
        "sendHeaders": true,
        "headerParameters": {
          "parameters": [
            {
              "name": "apikey",
              "value": "YOUR_EVOLUTION_API_KEY"
            },
            {
              "name": "Content-Type",
              "value": "application/json"
            }
          ]
        },
        "sendBody": true,
        "specifyBody": "json",
        "jsonBody": "={\n  \"number\": \"NUMERO_WHATSAPP_ETS\",\n  \"text\": \"Nuevo interesado en LOS SANTOS Lote 15:\\nNombre: {{ $('Parse Response').item.json.nombre }}\\nTel: {{ $('Parse Response').item.json.phone }}\"\n}",
        "options": {}
      },
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 3,
      "position": [1780, -140],
      "id": "550e8400-e29b-41d4-a716-446655440010",
      "name": "Notify ETS"
    },
    {
      "parameters": {
        "method": "POST",
        "url": "=YOUR_EVOLUTION_API_URL/message/sendText/YOUR_LOSANTOS_INSTANCE",
        "sendHeaders": true,
        "headerParameters": {
          "parameters": [
            {
              "name": "apikey",
              "value": "YOUR_EVOLUTION_API_KEY"
            },
            {
              "name": "Content-Type",
              "value": "application/json"
            }
          ]
        },
        "sendBody": true,
        "specifyBody": "json",
        "jsonBody": "={\n  \"number\": \"{{ $('Parse Response').item.json.phone }}\",\n  \"text\": \"{{ $('Parse Response').item.json.responseText }}\"\n}",
        "options": {}
      },
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 3,
      "position": [2060, -260],
      "id": "550e8400-e29b-41d4-a716-446655440011",
      "name": "Reply to Prospect"
    },
    {
      "parameters": {
        "rule": {
          "interval": [
            {
              "field": "hours",
              "hoursInterval": 24
            }
          ]
        }
      },
      "type": "n8n-nodes-base.scheduleTrigger",
      "typeVersion": 1,
      "position": [-900, 300],
      "id": "550e8400-e29b-41d4-a716-446655440012",
      "name": "Schedule Trigger"
    },
    {
      "parameters": {
        "url": "https://www.punch.com.mx/losantosCRM/api/v1/contactos/inactivos?horas=48",
        "sendHeaders": true,
        "headerParameters": {
          "parameters": [
            {
              "name": "X-API-Key",
              "value": "REEMPLAZAR_CON_TOKEN_LOSANTOS"
            }
          ]
        },
        "options": {}
      },
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 3,
      "position": [-620, 300],
      "id": "550e8400-e29b-41d4-a716-446655440013",
      "name": "Get Inactive Contacts"
    },
    {
      "parameters": {
        "batchSize": 1,
        "options": {}
      },
      "type": "n8n-nodes-base.splitInBatches",
      "typeVersion": 1,
      "position": [-360, 300],
      "id": "550e8400-e29b-41d4-a716-446655440014",
      "name": "Loop Contacts"
    },
    {
      "parameters": {
        "method": "PUT",
        "url": "=https://www.punch.com.mx/losantosCRM/api/v1/contactos/{{ $json.id }}/etapa",
        "sendHeaders": true,
        "headerParameters": {
          "parameters": [
            {
              "name": "X-API-Key",
              "value": "REEMPLAZAR_CON_TOKEN_LOSANTOS"
            },
            {
              "name": "Content-Type",
              "value": "application/json"
            }
          ]
        },
        "sendBody": true,
        "specifyBody": "json",
        "jsonBody": "{\n  \"etapa\": \"no_interesado\",\n  \"notas\": \"Sin respuesta después de 48 horas\"\n}",
        "options": {}
      },
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 3,
      "position": [-100, 300],
      "id": "550e8400-e29b-41d4-a716-446655440015",
      "name": "Mark No Interesado"
    }
  ],
  "connections": {
    "Webhook Evolution API": {
      "main": [[{ "node": "Extract Data", "type": "main", "index": 0 }]]
    },
    "Extract Data": {
      "main": [[{ "node": "Is Text Message", "type": "main", "index": 0 }]]
    },
    "Is Text Message": {
      "main": [
        [{ "node": "Search Contact CRM", "type": "main", "index": 0 }],
        []
      ]
    },
    "Search Contact CRM": {
      "main": [[{ "node": "Contact Exists", "type": "main", "index": 0 }]]
    },
    "Contact Exists": {
      "main": [
        [{ "node": "Already Interesado", "type": "main", "index": 0 }],
        [{ "node": "AI Agent", "type": "main", "index": 0 }]
      ]
    },
    "Already Interesado": {
      "main": [
        [{ "node": "Reply Already In Process", "type": "main", "index": 0 }],
        [{ "node": "AI Agent", "type": "main", "index": 0 }]
      ]
    },
    "AI Agent": {
      "main": [[{ "node": "Parse Response", "type": "main", "index": 0 }]]
    },
    "OpenAI Chat Model": {
      "ai_languageModel": [[{ "node": "AI Agent", "type": "ai_languageModel", "index": 0 }]]
    },
    "Window Buffer Memory": {
      "ai_memory": [[{ "node": "AI Agent", "type": "ai_memory", "index": 0 }]]
    },
    "Parse Response": {
      "main": [[{ "node": "Intent Router", "type": "main", "index": 0 }]]
    },
    "Intent Router": {
      "main": [
        [{ "node": "Create Contact CRM", "type": "main", "index": 0 }],
        [{ "node": "Log Activity CRM", "type": "main", "index": 0 }],
        [
          { "node": "Update Interesado", "type": "main", "index": 0 },
          { "node": "Log Activity CRM", "type": "main", "index": 0 }
        ],
        [
          { "node": "Update No Interesado", "type": "main", "index": 0 },
          { "node": "Log Activity CRM", "type": "main", "index": 0 }
        ]
      ]
    },
    "Create Contact CRM": {
      "main": [[{ "node": "Reply to Prospect", "type": "main", "index": 0 }]]
    },
    "Update Interesado": {
      "main": [[{ "node": "Notify ETS", "type": "main", "index": 0 }]]
    },
    "Update No Interesado": {
      "main": [[{ "node": "Reply to Prospect", "type": "main", "index": 0 }]]
    },
    "Log Activity CRM": {
      "main": [[{ "node": "Reply to Prospect", "type": "main", "index": 0 }]]
    },
    "Notify ETS": {
      "main": [[{ "node": "Reply to Prospect", "type": "main", "index": 0 }]]
    },
    "Schedule Trigger": {
      "main": [[{ "node": "Get Inactive Contacts", "type": "main", "index": 0 }]]
    },
    "Get Inactive Contacts": {
      "main": [[{ "node": "Loop Contacts", "type": "main", "index": 0 }]]
    },
    "Loop Contacts": {
      "main": [
        [{ "node": "Mark No Interesado", "type": "main", "index": 0 }],
        []
      ]
    }
  },
  "active": false,
  "settings": {
    "executionOrder": "v1"
  },
  "pinData": {}
}
```

> Valores a reemplazar antes de importar en N8N:
> - `REEMPLAZAR_CON_TOKEN_LOSANTOS` → token generado por `php artisan db:seed` en el servidor
> - `YOUR_EVOLUTION_API_URL` → URL de tu instancia Evolution API
> - `YOUR_LOSANTOS_INSTANCE` → nombre de la instancia para el número ETS
> - `YOUR_EVOLUTION_API_KEY` → API key de Evolution API
> - `NUMERO_WHATSAPP_ETS` → número de WhatsApp de Eduardo Torreblanca (pendiente)

- [ ] **Step 6.2: Commit**

```bash
cd "/Users/fernando.diaz/Downloads/SINERGIA Proyectos/losantos-crm"
git add docs/n8n/losantos-whatsapp-agent-workflow.json
git commit -m "feat: workflow N8N LOS SANTOS con system prompt Lote 15 y notificacion a ETS"
```

---

## Task 7: Actualizar DEPLOY.md

**Files:**
- Modificar: `DEPLOY.md`

- [ ] **Step 7.1: Reemplazar DEPLOY.md**

Reemplazar el contenido de `DEPLOY.md`:

```markdown
# Deploy LOS SANTOS CRM en HostGator (cPanel) - punch.com.mx

## Estructura en el servidor

```
public_html/
├── losantos-crm/             ← Código Laravel (protegido con .htaccess)
│   ├── .htaccess             ← "Deny from all"
│   ├── app/
│   ├── bootstrap/
│   ├── config/
│   ├── database/
│   ├── resources/
│   ├── routes/
│   ├── storage/
│   ├── vendor/
│   ├── .env
│   └── artisan
└── losantosCRM/              ← Solo archivos públicos (accesible por web)
    ├── build/
    ├── .htaccess
    └── index.php             ← Apunta a ../losantos-crm
```

## Paso 1: Crear base de datos MySQL

1. En cPanel → **Bases de datos MySQL**
2. Crear base de datos: `punchcom_losantos_crm`
3. Crear usuario: `punchcom_crm_losantos` con contraseña segura
4. Asignar usuario a la base de datos con **TODOS los privilegios**

## Paso 2: Preparar ZIP en tu Mac

```bash
cd "/Users/fernando.diaz/Downloads/SINERGIA Proyectos"
zip -r losantos-crm.zip losantos-crm/ \
  -x "losantos-crm/node_modules/*" \
  -x "losantos-crm/vendor/*" \
  -x "losantos-crm/.git/*" \
  -x "losantos-crm/.env" \
  -x "losantos-crm/database/database.sqlite"
```

## Paso 3: Subir archivos

### File Manager de cPanel
1. Entrar a cPanel → **Navegador de archivos**
2. Navegar a `public_html/`
3. Subir `losantos-crm.zip`
4. Click derecho → **Extraer** en `public_html/`

## Paso 4: Instalar dependencias

En cPanel → **Terminal**:

```bash
cd ~/public_html/losantos-crm
curl -sS https://getcomposer.org/installer | php
php composer.phar install --no-dev --optimize-autoloader
```

## Paso 5: Configurar .env

```bash
cd ~/public_html/losantos-crm
cp .env.example .env
nano .env
```

Configurar:
```env
APP_NAME="LOS SANTOS CRM"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://www.punch.com.mx/losantosCRM

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=punchcom_losantos_crm
DB_USERNAME=punchcom_crm_losantos
DB_PASSWORD=TU_CONTRASEÑA_AQUI

APP_TIMEZONE=America/Mexico_City
```

```bash
php artisan key:generate
```

## Paso 6: Crear carpeta pública losantosCRM

```bash
mkdir -p ~/public_html/losantosCRM
cp -r ~/public_html/losantos-crm/public/* ~/public_html/losantosCRM/
cp ~/public_html/losantos-crm/public/.htaccess ~/public_html/losantosCRM/
```

### Modificar index.php en losantosCRM/

Reemplazar `public_html/losantosCRM/index.php`:

```php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$basePath = __DIR__ . '/../losantos-crm';

if (file_exists($maintenance = $basePath.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $basePath.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $basePath.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
```

### Modificar .htaccess en losantosCRM/

```apache
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On
    RewriteBase /losantosCRM/

    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
```

### Proteger losantos-crm/

Crear `public_html/losantos-crm/.htaccess`:
```apache
Order Deny,Allow
Deny from all
```

## Paso 7: Permisos y storage

```bash
cd ~/public_html/losantos-crm
chmod -R 775 storage bootstrap/cache
ln -sf ~/public_html/losantos-crm/storage/app/public ~/public_html/losantosCRM/storage
```

## Paso 8: Migraciones y seeders

```bash
cd ~/public_html/losantos-crm
php artisan migrate --force
php artisan db:seed --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Paso 9: Verificar

1. Abrir `https://www.punch.com.mx/losantosCRM`
2. Login: `admin@sinergia.com` / `SinergiaLosantos2026!`
3. **Cambiar la contraseña del admin inmediatamente**

## Paso 10: Configurar API Token para N8N

1. Login como admin → Configuración → API Tokens
2. Crear token "N8N WhatsApp Agent"
3. Copiar el token generado
4. En N8N, reemplazar `REEMPLAZAR_CON_TOKEN_LOSANTOS` en todos los nodos del workflow
5. Base URL del CRM: `https://www.punch.com.mx/losantosCRM/api/v1`

## Troubleshooting

### Error 500
```bash
tail -50 ~/public_html/losantos-crm/storage/logs/laravel.log
chmod -R 775 ~/public_html/losantos-crm/storage
```

### Página en blanco
```bash
cd ~/public_html/losantos-crm
php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

### Assets no cargan
Verificar que `build/` se copió a `losantosCRM/build/`.

### Versión de PHP
HostGator → cPanel → MultiPHP Manager → seleccionar PHP 8.2+.
```

- [ ] **Step 7.2: Commit**

```bash
cd "/Users/fernando.diaz/Downloads/SINERGIA Proyectos/losantos-crm"
git add DEPLOY.md
git commit -m "docs: DEPLOY.md actualizado para losantosCRM en HostGator"
```

---

## Task 8: Verificación y tests

**Files:**
- Sin cambios de código — solo correr los tests existentes

- [ ] **Step 8.1: Instalar dependencias localmente**

```bash
cd "/Users/fernando.diaz/Downloads/SINERGIA Proyectos/losantos-crm"
composer install
```

Esperado: instalación exitosa sin errores.

- [ ] **Step 8.2: Copiar .env para tests**

```bash
cp .env.example .env
php artisan key:generate
```

Editar `.env` para tests (SQLite):
```env
APP_NAME="LOS SANTOS CRM"
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
```

- [ ] **Step 8.3: Correr tests**

```bash
php artisan test
```

Esperado: todos los tests pasan (misma lógica que altai-crm, sin cambios en código de negocio).

- [ ] **Step 8.4: Commit final**

```bash
cd "/Users/fernando.diaz/Downloads/SINERGIA Proyectos/losantos-crm"
git add -A
git commit -m "chore: proyecto losantos-crm listo para deploy"
```

---

## Pendientes post-deploy (no bloqueantes para el código)

- [ ] Actualizar `config/losantos.php` → `escalamiento.telefono` con número real de Eduardo
- [ ] Actualizar nodo "Notify ETS" en N8N → `NUMERO_WHATSAPP_ETS` con número real
- [ ] Crear instancia en Evolution API para el número de WhatsApp nuevo
- [ ] Agregar logo `public/images/logo-ets.png` si Eduardo proporciona uno
