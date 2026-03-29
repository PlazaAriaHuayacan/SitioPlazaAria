# ALTAI CRM + WhatsApp Agent — Design Spec

**Fecha:** 2026-03-28
**Proyecto:** Venta de terreno Lote #32, ALTAI Residencial, Cancún
**Contacto de escalamiento:** Ana Caro Bassol, +52 998 386 5415

---

## 1. Resumen

Sistema para atender prospectos interesados en un terreno de 145.35 m2 en ALTAI Residencial vía WhatsApp. Consta de 3 componentes:

1. **CRM Laravel** — Gestión de leads con pipeline de 3 etapas
2. **Flujo N8N** — Orquestación de conversaciones WhatsApp ↔ CRM
3. **Agente IA** — Prompt que atiende dudas y clasifica prospectos

---

## 2. Arquitectura General

```
WhatsApp (Evolution API)
    ↕ webhook
N8N Workflow
    ↕ API REST (X-API-Key)
Laravel CRM (ALTAI)
    │
    ├── API: crear leads, mover etapas, registrar actividades
    ├── UI: dashboard, contactos, pipeline kanban
    └── DB: MySQL
```

- **Evolution API** → recibe/envía mensajes de WhatsApp
- **N8N** → orquesta la conversación (agente con IA), llama al CRM via API
- **Laravel CRM** → almacena leads, muestra pipeline, permite ver el estado de los prospectos

El CRM no sabe nada de WhatsApp. N8N es el puente entre ambos.

---

## 3. Modelo de Datos

### 3.1 Pipeline (3 etapas fijas)

| Etapa | Slug | Color | Descripción |
|-------|------|-------|-------------|
| Prospecto | `prospecto` | Azul (#3B82F6) | Escribió al WhatsApp, se capturaron sus datos |
| Interesado | `interesado` | Verde (#22C55E) | Mostró interés real, se escala con Ana Caro |
| No Interesado | `no_interesado` | Rojo (#EF4444) | Dejó conversación o dijo que no le interesa |

### 3.2 Tabla: `contactos`

| Campo | Tipo | Requerido | Origen |
|-------|------|-----------|--------|
| id | bigint AI | — | Auto |
| nombre | varchar(255) | Sí | Agente pregunta |
| email | varchar(255) | No | Agente pregunta |
| telefono | varchar(20) | Sí, **unique** | Se extrae del mensaje WhatsApp. Formato E.164: `+5219981234567` |
| etapa | enum(prospecto, interesado, no_interesado) | Sí | Default: prospecto |
| asignado_a | FK → users, nullable | No | Se asigna a Ana Caro al escalar |
| notas | text | No | Resumen de conversación |
| fuente | varchar(50) | Sí | Default: "WhatsApp" |
| motivo_no_interes | text | No | Si dice por qué no le interesa |
| fecha_contacto | timestamp | Sí | Auto al crear |
| fecha_escalado | timestamp | No | Cuando pasa a Interesado |
| ultima_actividad_at | timestamp | Sí | Se actualiza con cada interacción (usado por cron de inactividad) |
| created_at | timestamp | — | Auto |
| updated_at | timestamp | — | Auto |

**Normalización de teléfono:** Todo número se almacena en formato E.164 (`+521XXXXXXXXXX`). La API normaliza al recibir: quita espacios, guiones, y agrega `+52` si falta código de país.

### 3.3 Tabla: `actividades`

| Campo | Tipo | Requerido |
|-------|------|-----------|
| id | bigint AI | — |
| contacto_id | FK → contactos | Sí |
| tipo | enum(whatsapp, nota, escalamiento, cambio_etapa) | Sí |
| descripcion | text | Sí |
| metadata | json, nullable | No. Datos estructurados (ej. resumen de conversación para escalamiento) |
| automatico | boolean | Sí (default: true) |
| created_at | timestamp | — |
| updated_at | timestamp | — |

> Los cambios de etapa se registran como actividades tipo `cambio_etapa`, lo que da trazabilidad completa sin necesidad de una tabla `historial_etapas` separada.

### 3.4 Tabla: `users`

| Campo | Tipo |
|-------|------|
| id | bigint AI |
| name | varchar(255) |
| email | varchar(255) |
| password | varchar(255) |
| role | enum(admin, usuario) |
| created_at | timestamp |
| updated_at | timestamp |

**Usuarios iniciales:**

| Nombre | Email | Rol |
|--------|-------|-----|
| Sinergia_Admin | admin@sinergia.com | admin |
| Jose Luis | [POR CONFIRMAR] | usuario |
| Ana Caro | [POR CONFIRMAR] | usuario |

### 3.5 Tabla: `api_tokens`

| Campo | Tipo |
|-------|------|
| id | bigint AI |
| name | varchar(255) |
| token | varchar(255) unique |
| active | boolean (default: true) |
| expires_at | timestamp nullable |
| last_used_at | timestamp nullable |
| created_at | timestamp |

---

## 4. API REST

**Base URL:** `https://[DOMINIO]/api/v1`
**Autenticación:** Header `X-API-Key: {token}`
**Health check:** `GET /api/v1/ping` → `{ "status": "ok" }` (sin auth, para monitoreo de N8N)
**HTTPS obligatorio:** La API key viaja en headers, requiere TLS.

### 4.1 Respuestas de error (todos los endpoints)

| Código | Significado | Body |
|--------|-------------|------|
| 400 | Validación fallida | `{ "errors": { "campo": ["mensaje"] } }` |
| 401 | API key inválida o faltante | `{ "error": "No autorizado" }` |
| 404 | Recurso no encontrado | `{ "error": "No encontrado" }` |
| 409 | Conflicto (ej. teléfono duplicado) | `{ "error": "Ya existe un contacto con este teléfono", "contacto_id": 123 }` |
| 429 | Rate limit (60 req/min por token) | `{ "error": "Demasiadas solicitudes" }` |
| 500 | Error interno | `{ "error": "Error interno del servidor" }` |

### 4.2 Contactos

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| POST | `/contactos` | Crear prospecto (retorna 409 si teléfono ya existe) |
| GET | `/contactos/{id}` | Obtener contacto |
| GET | `/contactos/buscar?telefono=X` | Buscar por teléfono |
| PUT | `/contactos/{id}` | Actualizar datos (no permite cambiar etapa; usar endpoint dedicado) |
| PUT | `/contactos/{id}/etapa` | Mover de etapa |
| GET | `/contactos` | Listar todos (con filtros) |
| GET | `/contactos/inactivos?horas=48` | Prospectos sin actividad en N horas (para cron de N8N) |

#### POST `/contactos` — Crear prospecto

**Request:**
```json
{
  "nombre": "Juan Pérez",
  "email": "juan@email.com",
  "telefono": "5219981234567",
  "fuente": "WhatsApp",
  "notas": "Llegó por anuncio en Facebook"
}
```

**Response (201):**
```json
{
  "id": 1,
  "nombre": "Juan Pérez",
  "email": "juan@email.com",
  "telefono": "5219981234567",
  "etapa": "prospecto",
  "fuente": "WhatsApp",
  "fecha_contacto": "2026-03-28T10:00:00Z"
}
```

#### PUT `/contactos/{id}/etapa` — Mover de etapa

**Request:**
```json
{
  "etapa": "interesado",
  "notas": "Confirmó presupuesto, pidió hablar con propietarios"
}
```

**Response (200):** Contacto actualizado. Si la etapa es `interesado`, se setea `fecha_escalado`.

#### GET `/contactos/buscar?telefono=X` — Buscar por teléfono

**Response (200):** Contacto encontrado o `404` si no existe. La API normaliza el teléfono antes de buscar.

#### GET `/contactos/inactivos?horas=48` — Prospectos inactivos

Retorna contactos con `etapa=prospecto` cuya `ultima_actividad_at` sea mayor a N horas.

**Response (200):**
```json
{
  "contactos": [
    { "id": 5, "nombre": "...", "telefono": "...", "ultima_actividad_at": "..." }
  ]
}
```

#### GET `/contactos` — Listar con filtros

Parámetros opcionales: `?etapa=prospecto`, `?asignado_a=3`, `?page=1&per_page=20`

### 4.3 Actividades

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| POST | `/contactos/{id}/actividades` | Registrar interacción |
| GET | `/contactos/{id}/actividades` | Listar actividades de un contacto |

#### POST `/contactos/{id}/actividades`

**Request:**
```json
{
  "tipo": "whatsapp",
  "descripcion": "Preguntó por el precio del terreno",
  "automatico": true
}
```

---

## 5. UI del CRM (Livewire + Tailwind)

### 5.1 Dashboard
- Contadores: Prospectos / Interesados / No Interesados
- Leads recientes (últimos 10)
- Gráfica simple de leads por día (últimos 30 días)

### 5.2 Pipeline (vista kanban)
- 3 columnas: Prospecto | Interesado | No Interesado
- Cada tarjeta: nombre, teléfono, tiempo en la etapa
- Click en tarjeta → detalle del contacto

### 5.3 Detalle de contacto
- Datos del contacto (nombre, email, teléfono)
- Etapa actual con botones para mover manualmente
- Timeline de actividades
- Botón para copiar teléfono

### 5.4 Usuarios
- 3 usuarios: Sinergia_Admin (admin), Jose Luis (usuario), Ana Caro (usuario)
- Login con email/password
- Admin puede ver todo, usuarios pueden ver contactos y pipeline

---

## 6. Flujo de N8N

### 6.1 Trigger: Webhook de Evolution API
```
Evolution API → POST webhook → N8N
```
Recibe: número de teléfono, mensaje, timestamp

### 6.2 Flujo principal

```
1. Recibe mensaje de WhatsApp
2. Extrae teléfono del remitente
3. GET /contactos/buscar?telefono=X
4. ¿Existe?
   │
   ├── NO → Agente envía saludo + pide nombre y email
   │        Cuando el usuario responde:
   │        → POST /contactos (crea prospecto)
   │        → POST /contactos/{id}/actividades (log)
   │
   ├── SÍ y etapa=interesado
   │   → Agente responde: "Ya estás en proceso con los propietarios,
   │     cualquier duda aquí estoy"
   │
   ├── SÍ y etapa=no_interesado
   │   → Trata como nuevo prospecto (puede cambiar de opinión)
   │
   └── SÍ y etapa=prospecto → Continúa conversación con agente IA
        │
        ▼
   Agente IA responde dudas del terreno
   POST /contactos/{id}/actividades (log de cada interacción)
        │
        ▼
   ¿Señales de interés real?
   ├── SÍ → PUT /contactos/{id}/etapa → "interesado"
   │        → Envía WhatsApp a Ana Caro (+52 998 386 5415):
   │          "Nuevo interesado en ALTAI: [nombre], [teléfono], [resumen]"
   │        → Agente responde al prospecto: mensaje de escalamiento
   │
   ├── NO INTERESA → PUT /contactos/{id}/etapa → "no_interesado"
   │                → Agente se despide amablemente
   │
   └── AÚN EN CONVERSACIÓN → Sigue respondiendo
```

### 6.3 Cron: Inactividad 48 horas
- N8N verifica cada 24 horas
- Prospectos sin interacción en 48 horas → etapa: no_interesado
- Motivo: "Sin respuesta después de 48 horas"

### 6.4 Memoria del agente

El nodo AI Agent de N8N necesita recordar la conversación entre mensajes. Usamos **Window Buffer Memory** de N8N con session key basado en el teléfono del contacto. Esto mantiene las últimas N interacciones en memoria por sesión.

- **Session key:** teléfono del contacto (E.164)
- **Window size:** 20 mensajes (suficiente para el flujo de calificación)
- **Persistencia:** La memoria vive en N8N. Si N8N se reinicia, se pierde — pero las actividades quedan loggeadas en el CRM como respaldo.

### 6.5 Nodos principales de N8N

1. **Webhook Trigger** — Recibe de Evolution API
2. **HTTP Request** — Busca contacto en CRM
3. **IF** — ¿Existe el contacto?
4. **AI Agent** — Nodo de agente con Window Buffer Memory
5. **HTTP Request** — Crea/actualiza contacto en CRM
6. **HTTP Request** — Registra actividad en CRM
7. **IF** — ¿Interesado o no?
8. **HTTP Request** — Envía mensaje via Evolution API a Ana Caro
9. **HTTP Request** — Responde al prospecto via Evolution API
10. **Schedule Trigger** — Cron cada 24h para inactividad
11. **HTTP Request** — GET /contactos/inactivos?horas=48 → loop → PUT etapa no_interesado

---

## 7. Prompt del Agente

```
Eres el asistente de ventas de un terreno residencial en ALTAI Residencial, Cancún.
Tu objetivo es atender prospectos por WhatsApp, resolver sus dudas y detectar si tienen
interés real de compra.

## INFORMACIÓN DEL TERRENO

- Desarrollo: ALTAI Residencial (por Grupo Cumbres)
- Ubicación: Av. Huayacán km 7.5, esquina Chacmool, Cancún, Q. Roo
- Lote: #32, Calle 23 (Montes Balcanes), Manzana 337
- Superficie: 145.35 m2
- Frente: 8.55 m | Fondo: 17.00 m
- Precio: [POR CONFIRMAR]
- Precio por m2: [SE CALCULA]
- Tipo de venta: Reventa de particular (no es del desarrollador)
- Financiamiento: No hay financiamiento, compra directa
- Escrituras: [POR CONFIRMAR]
- Mantenimiento mensual: [POR CONFIRMAR]
- Construcción: Se puede construir con cualquier constructora, respetando el reglamento de ALTAI

## UBICACIÓN Y CONECTIVIDAD

- A ~20 minutos de la zona hotelera
- Sobre Av. Chacmool, avenida principal que cruza todo Cancún
- Salida a Av. Huayacán (hacia zona hotelera) y a Av. Colosio (hacia el centro)
- Hospitales cercanos: [POR CONFIRMAR]
- Escuelas cercanas: [POR CONFIRMAR]
- Plazas comerciales: [POR CONFIRMAR]

## AMENIDADES DE ALTAI RESIDENCIAL

- Ciclopista Infinity: circuito de +4 km para correr, caminar y bicicleta
- Parque infantil: kiosco, juegos, mini canchas de fútbol, skate park
- Área deportiva: 2 canchas de pádel, cancha multiusos, voleibol, gym al aire libre, zona CrossFit
- Casa Club: alberca recreativa, carriles semi-olímpicos, gimnasio, área recreativa
- +50,000 m2 de áreas verdes con fuentes en boulevard principal
- Dos parques lineales recreativos
- Zonas comerciales dentro del desarrollo
- Infraestructura: avenidas 100% concreto estampado, instalaciones subterráneas, sistema de riego,
  drenaje pluvial profesional

## TU COMPORTAMIENTO

1. PRIMER CONTACTO: Saluda amablemente, preséntate como asistente de ventas del terreno en ALTAI,
   y pide:
   - Nombre completo
   - Correo electrónico
   No pidas teléfono (ya lo tienes del WhatsApp).

2. CONVERSACIÓN: Responde dudas con la información que tienes. Si te preguntan algo que no sabes,
   di: "Déjame confirmar ese dato y te respondo a la brevedad." NO inventes datos.

3. EVALÚA INTERÉS: Durante la conversación, detecta señales de interés real:
   - Pregunta por precio y proceso de compra
   - Confirma que tiene presupuesto o forma de pago
   - Pide agendar visita al terreno
   - Pregunta por escrituración o trámites legales

4. ESCALAMIENTO: Cuando detectes interés real, di:
   "¡Muchas gracias por tu interés en nuestro terreno en ALTAI! Con tus datos, los propietarios
   se pondrán en contacto contigo para darte seguimiento personalizado. ¿Tienes alguna otra duda
   mientras tanto?"

5. NO INTERESADO: Si la persona dice que no le interesa o deja de responder, sé amable:
   "Entiendo perfectamente. Si en algún momento cambias de opinión o tienes alguna duda, con gusto
   te atendemos. ¡Que tengas excelente día!"

## REGLAS

- Sé amable, profesional pero cercano. Usa español mexicano natural.
- Respuestas cortas y claras. No envíes párrafos largos.
- NUNCA inventes precios, datos legales ni información que no tengas.
- NUNCA compartas datos de contacto de los propietarios. Tú escalas internamente.
- Si alguien pregunta algo fuera del tema, redirige amablemente.
- Usa emojis con moderación (máximo 1-2 por mensaje).
- Si te preguntan por el PRECIO y no lo tienes configurado, responde:
  "El precio está siendo actualizado. Déjame confirmarlo y te lo comparto a la brevedad."
  Esto es la pregunta más frecuente — no dejes al prospecto sin respuesta.
```

---

## 8. Datos Pendientes

| # | Dato | Necesario para |
|---|------|----------------|
| 1 | Precio de venta | Prompt del agente |
| 2 | ¿Escrituras o cesión de derechos? | Prompt del agente |
| 3 | Cuota de mantenimiento mensual | Prompt del agente |
| 4 | ¿Se puede construir de inmediato? | Prompt del agente |
| 5 | Estado de amenidades (¿terminadas?) | Prompt del agente |
| 6 | URL de Evolution API | Flujo N8N |
| 7 | Emails de Jose Luis y Ana Caro | Usuarios del CRM |
| 8 | Dominio para el CRM | Deploy |
| 9 | Fotos del terreno/desarrollo | Brochure |

---

## 9. Stack Técnico

- **CRM:** Laravel 12 + Livewire 3 + Tailwind CSS
- **DB:** MySQL
- **N8N:** Instancia existente en https://n8n-01-n8n.fkimek.easypanel.host/
- **WhatsApp:** Evolution API (pendiente de configurar)
- **IA:** OpenAI GPT-4o o Claude (nodo AI Agent de N8N)
- **Deploy CRM:** [POR DEFINIR - mismo hosting que Ellevant o EasyPanel]
