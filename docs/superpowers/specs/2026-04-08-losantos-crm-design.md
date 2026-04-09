# LOS SANTOS CRM — Design Spec

**Fecha:** 2026-04-08
**Cliente:** Eduardo Torreblanca (ETS)
**Base:** Fork de `altai-crm`
**Proyecto:** `losantos-crm`

---

## 1. Objetivo

Sistema CRM + agente WhatsApp IA para gestionar prospectos interesados en el **Lote #15, Los Santos Cancún**. Clon funcional de `altai-crm` con datos, branding y configuración del nuevo lote. Proyecto completamente independiente de ALTAI.

---

## 2. Stack

Idéntico a `altai-crm`:
- **Backend:** Laravel + Livewire 3 + MySQL
- **API:** REST con autenticación `X-API-Key`
- **Automatización:** N8N + Evolution API (WhatsApp)
- **IA:** GPT-4o-mini (agente conversacional en N8N)
- **Deploy:** Easypanel VPS (Docker)

---

## 3. Datos del Lote

| Dato | Valor |
|------|-------|
| Desarrollo | Los Santos Cancún |
| Lote | #15 |
| Manzana | 05 |
| Calle | Isla Contoy |
| Superficie | 311.09 m² |
| Precio | $3,000,000 MXN |
| Financiamiento | 48 MSI |
| Vendedor / Escalamiento | Eduardo Torreblanca (ETS) |
| WhatsApp ETS | TBD (número nuevo por adquirir) |

### Amenidades del Residencial

- Clubhouse de 8,000 m²
- Alberca
- Gimnasio
- Salón de juegos
- Salón multiusos
- Área de BBQ
- Canchas de pádel
- Terraza
- Caseta de seguridad doble
- Cerca perimetral
- Estacionamiento para visitantes
- Entorno de vegetación de selva maya

---

## 4. Renombramientos Globales

| Antes (altai-crm) | Después (losantos-crm) |
|-------------------|------------------------|
| `altai-crm/` | `losantos-crm/` |
| `ALTAI CRM` | `LOS SANTOS CRM` |
| `ALTAI` | `LOSANTOS` |
| `altai` (slugs, rutas) | `losantos` |
| `config/altai.php` | `config/losantos.php` |
| `altai-whatsapp-agent-workflow.json` | `losantos-whatsapp-agent-workflow.json` |
| `altaiCRM` (URLs de N8N) | `losantosCRM` |
| Ana Caro / AC / JL | Eduardo Torreblanca / ETS |
| `ALTAI Terrenos` (company) | `Los Santos Residencial` |

---

## 5. Configuración (`config/losantos.php`)

```php
return [
    'name'    => 'LOS SANTOS CRM',
    'company' => 'Los Santos Residencial',
    'version' => '1.0.0',

    'colors' => [
        'azul_caribe'    => '#0077B6',  // Primary — CTAs
        'turquesa'       => '#90E0EF',  // Fondos, frescura
        'arena_blanca'   => '#F5F0E8',  // Backgrounds
        'verde_selva'    => '#2D6A4F',  // Acento — selva maya
        'gris_carbon'    => '#333333',  // Textos
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
        'telefono' => 'TBD',  // Actualizar cuando se adquiera el número
    ],
];
```

---

## 6. N8N Workflow (`losantos-whatsapp-agent-workflow.json`)

Fork de `altai-whatsapp-agent-workflow.json` con estos cambios:

| Nodo | Cambio |
|------|--------|
| Webhook | path: `losantos-whatsapp` |
| Search/Create/Update Contact CRM | URL base: `losantosCRM/api/v1/...` |
| X-API-Key | Nuevo token generado en el seeder |
| AI Agent — system prompt | Ficha completa del Lote #15, Los Santos (ver §6.1) |
| Notify ETS | Número TBD; texto: "Nuevo interesado en LOS SANTOS Lote 15" |
| Reply Already In Process | Texto actualizado a marca Los Santos |

### 6.1 System Prompt del Agente IA

El system prompt reemplaza toda la ficha de ALTAI con:

```
Eres el asistente de ventas de un terreno residencial en Los Santos Cancún.
Tu objetivo es atender prospectos por WhatsApp, resolver sus dudas y detectar si tienen interés real de compra.

## INFORMACIÓN DEL TERRENO
- Desarrollo: Los Santos Cancún
- Lote: #15, Manzana 05, Calle Isla Contoy
- Superficie: 311.09 m²
- Precio: $3,000,000 MXN
- Financiamiento: 48 MSI (meses sin intereses)
- Dirección: TBD

## AMENIDADES DEL RESIDENCIAL
- Clubhouse de 8,000 m²
- Alberca
- Gimnasio
- Salón de juegos
- Salón multiusos
- Área de BBQ
- Canchas de pádel
- Terraza
- Caseta de seguridad doble
- Cerca perimetral
- Estacionamiento para visitantes
- Entorno de vegetación de selva maya

## TU COMPORTAMIENTO
[Idéntico al de ALTAI: saludo, recopilación de nombre/email, evaluación de interés, escalamiento a ETS, manejo de no interesados]

## CLASIFICACIÓN — agregar SIEMPRE al final:
[INTENT:NEED_DATA] / [INTENT:CHATTING] / [INTENT:INTERESTED] / [INTENT:NOT_INTERESTED]
```

Nota: completar dirección exacta cuando esté disponible (§9).

---

## 7. Branding / UI

Paleta Los Santos aplicada en `config/losantos.php`, Tailwind config y vistas Blade/Livewire.

| Color | Hex | Uso |
|-------|-----|-----|
| Azul Caribe | `#0077B6` | Color principal, CTAs |
| Turquesa | `#90E0EF` | Fondos, frescura |
| Arena Blanca | `#F5F0E8` | Fondos de página |
| Verde Selva Maya | `#2D6A4F` | Acento |
| Gris Carbón | `#333333` | Textos |

---

## 8. Deploy

- Nuevo servicio en Easypanel: `losantosCRM`
- Base de datos MySQL independiente
- Variables de entorno: `APP_NAME`, `DB_*`, `OPENAI_API_KEY` (misma o nueva)
- El workflow de N8N apunta al nuevo dominio

---

## 9. Pendientes

- [ ] WhatsApp de Eduardo Torreblanca (número TBD)
- [ ] Dirección exacta de Los Santos Cancún (para el system prompt del agente)
- [ ] Instancia de Evolution API para el número nuevo
- [ ] Dominio/subdominio del deploy en Easypanel
