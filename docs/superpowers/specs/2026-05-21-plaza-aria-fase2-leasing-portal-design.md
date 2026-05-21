# Plaza Aria — Fase 2: Leasing Portal

**Fecha:** 2026-05-21
**Cliente:** Plaza Aria (gestionado por Punch Marketing)
**Autor:** Fernando Díaz
**Spec maestro:** `docs/superpowers/specs/2026-05-19-plaza-aria-website-design.md`

## Contexto

Fase 1 entregó el sitio base, directorio vecinal, agenda y un `/renta` placeholder. Plaza Aria está en producción en `https://sitio-plaza-aria.vercel.app/` (futuro dominio plazaaria.mx).

Fase 2 construye el **leasing portal** — el corazón comercial del sitio. Es donde un prospecto que vio "Renta tu local" entra, se enamora visualmente del plano, valida que la zona le sirve con el dashboard, y manda un lead calificado al comercializador.

## Objetivo de Fase 2

Convertir visitas a `/renta` en **leads calificados de leasing**. Métrica norte: % de visitantes únicos de `/renta` que envían el formulario.

## Conocimiento del edificio (de las fotos aéreas)

- **Forma:** barra rectangular alargada, una sola crujía.
- **Pisos:** 2, ambos al aire libre, conectados por escalera + elevador centrales.
- **Locales:** ~8 por piso, 16 total. Distribución lineal a ambos lados del núcleo central.
- **Estacionamiento:** ~15-16 cajones diagonales al frente, contra Av. Huayacán.
- **Techo:** teja terracota (combina con paleta `aria-terracotta`).
- **Lenguaje arquitectónico:** moderno, fachadas en vidrio oscuro, marquesinas blancas, zócalo azul marino, palmeras frente a cada bloque de locales. Sin elementos coloniales ni rústicos.
- **Identidad visual fuerte:** núcleo central (torre de concreto gris) — landmark del plano.

## Las 4 piezas de Fase 2

Para entregarlas con checkpoints intermedios demostrables, se dividen en 4 sub-fases secuenciales:

### Sub-fase 2A — Plano interactivo isométrico ⭐ (el WOW)

**Es la pieza protagonista.** Reemplaza el grid actual de Disponibles en `/renta`.

**Diseño visual:**
- SVG isométrico estilizado, hecho a mano (no 3D real). Estética: SimCity moderno + sobrio.
- Ángulo: 30° (no 45°) para que la fachada se vea claramente.
- Edificio dibujado fielmente: barra rectangular, 2 pisos visibles con balcón corrido en piso 2, núcleo central destacado, techo terracota.
- 16 bloques de locales (8 + 8) — cada uno es un `<g>` clickable.
- Decoración fija: palmeras estilizadas frente a cada bloque, estacionamiento con cajones diagonales en planta baja, camellón con palmas al frente (Av. Huayacán).
- Paleta consistente con el sitio (terracotta, olive, sand, ink).

**Estados visuales por local:**
- **Ocupado** → bloque con logo del negocio (extraído de Airtable). Sutil.
- **Disponible** → bloque resaltado con borde terracotta, pequeño badge "DISPONIBLE", animación de pulsación lenta.
- **Próximamente** → bloque ligeramente translúcido + badge "Próximamente".

**Interacción:**
- Hover (desktop) → preview tooltip con: nombre/giro o "Disponible · m² · renta", foto pequeña.
- Click → panel lateral desliza desde la derecha con la ficha completa del local. Misma ficha estructural que `/directorio/[slug]`, pero embedded sin navegar.
- Click en Disponible → la ficha incluye el formulario de lead expandido (Sub-fase 2C).
- Toggle Piso 1 / Piso 2 (radio elegante en la esquina superior izquierda del plano).
- Filtro: "Solo disponibles" (toggle pill) — atenúa los ocupados.

**Animación de entrada (3-4s):**
1. Base del edificio aparece (0-1s, fade-in + slight scale-up)
2. Piso 1 se construye desde la base (1-2s, locales aparecen de izq a der)
3. Piso 2 se construye encima (2-3s, mismo patrón)
4. Núcleo central (escalera + elevador) aparece al final (3-3.5s)
5. Locales disponibles empiezan a pulsar (3.5s+)

Usamos `framer-motion` para la coreografía. Performance objetivo: 60fps en MacBook Air 2018.

**Mobile:**
- En mobile, el plano se renderiza completo pero con zoom inicial al piso 1.
- Pinch-to-zoom + scroll horizontal funcionan nativamente sobre el SVG (con `overflow-x-auto`).
- Toggle de pisos visible.
- En lugar del side panel, click abre un modal full-screen con la ficha.

**Datos en Airtable:**
- Cada local en la tabla `Locales` ya tiene `CoordX, CoordY, Ancho, Alto` (definidos en Fase 1 schema).
- Estos campos definen la posición del bloque del local dentro del SVG isométrico.
- El cliente puede mover un bloque ajustando coordenadas si la realidad cambia — sin tocar código.

### Sub-fase 2B — Dashboard "¿Por qué Aria?"

Sección persuasiva debajo del plano. Tarjetas visuales que responden la pregunta del prospecto: ¿por qué rentar aquí?

**Datos:** Combinación de:
1. **INEGI público** — Investigaré datos públicos del AGEB de Huayacán (Cancún): ingreso promedio, edad, núcleos familiares, densidad residencial. Los hardcodeamos en la tabla `Config` de Airtable (campos nuevos) para que el cliente pueda ajustar si quiere.
2. **Estimaciones de Punch** — Aforo estimado, mix de giros actual (calculado de Locales), cajones de estacionamiento, gaps de giros.

**Estructura del dashboard (5 tarjetas):**

1. **La zona** → Mapa estilizado de Huayacán con radio de captación de 2km. Pin de Plaza Aria. Pines pequeños para fraccionamientos cercanos. Lista de 5-7 fraccionamientos al lado.
2. **Demografía** → Stats grandes: ingreso promedio del rumbo, edad mediana, núcleos familiares en radio de 2km. Pequeño disclaimer "Fuente: INEGI 2020 + estimaciones Punch".
3. **Mix actual de Aria** → Pie chart simple de giros existentes (Restaurante 30%, Belleza 15%, etc.). Calculado dinámicamente de Airtable. Refuerza al prospecto que no hay canibalización.
4. **Gaps detectados** → Pills terracota con los giros que faltan (de `Config.GapsGiros`). "Aría está buscando: Panadería · Cafetería · Farmacia · Pediatría". Gancho directo.
5. **Tráfico y servicios** → Cajones de estacionamiento, aforo semanal estimado, accesos (entrada/salida Av. Huayacán), tipo de plaza (vecinal, al aire libre, 2 pisos).

**Tono:** datos visuales que se vean serios pero no aburridos. Misma estética editorial del resto del sitio (Fraunces para los números grandes, Inter para etiquetas).

### Sub-fase 2C — Ficha de local disponible + formulario funcional

Cuando el prospecto clickea un local Disponible en el plano (o entra directo desde `/renta`):

**Ficha de local disponible (panel lateral en desktop, modal en mobile):**
- Galería de fotos del espacio vacío (de Airtable `Fotos`)
- Nombre del local (ej. "Local A-12") + Piso + giro sugerido si aplica
- Specs: m², frente, instalaciones (agua, luz, A/C, drenaje, gas)
- Renta + mantenimiento (MXN/mes)
- Locales vecinos (cards pequeños linkeando al directorio)
- Línea "Forma parte de Aria" con badges del giro de locales adyacentes

**Formulario de lead expandido:**
- Nombre completo
- WhatsApp
- Email
- Giro propuesto (select con opciones de la tabla `Locales.Giro` + opción "Otro" + texto libre)
- Mensaje opcional (textarea)
- Honeypot anti-spam (campo oculto)
- Botón "Solicitar información del local"

**Flujo de envío:**
1. Validación cliente (Zod schema): email válido, whatsapp con 10 dígitos, campos requeridos
2. POST a `/api/leads` (nuevo endpoint en Fase 2)
3. Endpoint:
   - Valida server-side
   - Llama a `createLeadRenta()` (ya existe en client.ts) para escribir a Airtable
   - Dispara generación de PDF (Sub-fase 2D)
   - Manda email al prospecto + email al comercializador con el PDF adjunto
4. Confirmación en UI: "Recibimos tu interés en el Local X. Te llegará a `email@ejemplo.com` un kit completo en los próximos minutos. El equipo de Aria te contactará por WhatsApp."

**Rate limiting:** máximo 5 envíos por IP por hora (en memoria con `Map` simple — suficiente para escala vecinal; upstash/redis sería over-engineering).

### Sub-fase 2D — Generador de PDF personalizado + delivery

**Tecnología:** **`@react-pdf/renderer`** (no Puppeteer).
- Razón: 100% server-side compatible con Vercel, sin overhead de Chromium, JSX nativo para layout, fonts personalizadas funcionan bien.

**Estructura del PDF (4-6 páginas, formato carta vertical):**

1. **Portada** — Wordmark Aria, "Propuesta de leasing personalizada para [Nombre del Prospecto]", fecha, foto aérea de la plaza, eyebrow "Plaza Aria · Av. Huayacán · Cancún"
2. **El local que te interesa** — Foto principal del espacio, specs (m², frente, renta, instalaciones), plano del piso con el local resaltado (versión simplificada del isométrico)
3. **La zona que te respalda** — Mapa de Huayacán, demografía resumida, fraccionamientos en captación
4. **Por qué tu marca encaja en Aria** — Mix actual de giros (chart), gap analysis (si el giro propuesto está en gaps, lo destacamos), tráfico estimado
5. **El siguiente paso** — Contacto del comercializador (WhatsApp con QR code → escaneable desde el PDF impreso), CTA "Agenda una visita"
6. **Sobre Aria** (opcional) — Foto frontal + breve narrativa + redes

**Generación:**
- Función server `generateLeasingPDF(lead, local, config)` que devuelve un Buffer
- El Buffer se envía por email como adjunto via Resend
- También se guarda en Vercel Blob por 30 días para descarga del prospecto desde un link único en el email

**Email delivery (Resend):**
- **Para el prospecto:** Email HTML con preview del PDF, link de descarga, ETA del contacto humano ("El equipo te contacta en máximo 24 hrs hábiles"), CTA directo a WhatsApp del comercializador
- **Para el comercializador (`+52 998 321 4614`):** Email interno con los datos del lead + PDF adjunto + link al registro en Airtable. Asunto: "Nuevo lead Aria: [Nombre] - Local [X] - Giro [Y]"

**Resend setup:**
- Cuenta de Resend (gratis hasta 100 emails/día — sobrado)
- Dominio verificado (eventualmente `aria@plazaaria.mx`; mientras tanto, `aria@punch.com.mx` o el dominio de Resend)
- API key en env var `RESEND_API_KEY`

## Tablas nuevas en Airtable (Fase 2)

Ampliamos la tabla `Config` con campos para datos del dashboard:

```
Config (campos nuevos)
├── IngresoPromedioMXN (currency)         — Ingreso promedio del rumbo (INEGI)
├── EdadMediana (number)                  — Edad mediana del rumbo
├── NucleosFamiliares (number)            — Núcleos familiares en radio 2km
├── DensidadHabitantesKm2 (number)        — Habitantes por km²
├── FraccionamientosCercanos (long text)  — Lista con saltos de línea
├── AccesosDescripcion (long text)        — Texto sobre accesos vehiculares
└── DemografiaFuente (single text)        — Footnote para el dashboard
```

Tabla `Locales` no cambia (los campos `CoordX/Y/Ancho/Alto` ya estaban definidos en Fase 1 schema).

## Stack adicional Fase 2

Sobre lo que ya hay en Fase 1:
- `framer-motion` (ya instalado en Fase 1) — animación del plano
- `zod` — validación del formulario de lead
- `@react-pdf/renderer` — generación de PDF
- `resend` — delivery de email
- `qrcode` — generar QR del WhatsApp dentro del PDF

## Variables de entorno nuevas

```
RESEND_API_KEY=re_...
COMERCIALIZADOR_EMAIL=fernando@punch.com.mx
COMERCIALIZADOR_WHATSAPP=5299983214614
```

## Cosas que quedan FUERA de Fase 2 (Fase 3 o más adelante)

- Tour virtual 360° de cada local (la galería de fotos lo aproxima)
- Video drone embebido en el hero (placeholder gradient sigue siendo aceptable)
- Hero Day-to-Night animado
- "Latido de Aria" en tiempo real con UI rica (la versión simple ya está)
- "Sábado en Aria" en home
- Easter egg para pitch
- Notificaciones opt-in para vecinos
- Login de locatarios para editar su propia ficha
- Versión en inglés

Estas piezas quedan documentadas pero postergadas a Fase 3 / Fase 4.

## Riesgos y dependencias

1. **Coordenadas del plano:** la primera vez que se construya el SVG, alguien (yo o el cliente) tiene que asignar manualmente `CoordX/Y/Ancho/Alto` a cada uno de los 16 locales. Esto requiere una sesión de calibración con el cliente o un screenshot del plano arquitectónico real (no lo tenemos aún).

2. **Datos INEGI:** investigar y validar los datos de Huayacán/Cancún toma 1-2 horas de research. Si el cliente nos pasa un estudio propio, mejor.

3. **Renta + fotos de Disponibles:** requerimos que el cliente nos pase las rentas reales y fotos de los espacios vacíos antes del demo final. Mientras tanto, usamos placeholders.

4. **Resend domain verification:** verificar `plazaaria.mx` (o un subdominio como `mail.plazaaria.mx`) toma 24-48 hrs por DNS. Si todavía no se apunta el dominio a Vercel, usamos el sandbox de Resend o `@punch.com.mx`.

## Métricas de éxito Fase 2

- Lighthouse 90+ móvil con el plano completo cargado
- Animación del plano a 60fps en MacBook Air 2018
- Conversión `/renta` → lead enviado: meta de 8%+ (industria leasing ~2-5%)
- Tiempo desde primera visita del prospecto hasta el contacto del comercializador: < 4hrs
- 0 emails fallidos (deliverability via Resend)

## Pendientes antes de empezar implementación

- [ ] Coordenadas iniciales de los 16 locales (yo las propongo, cliente confirma)
- [ ] Datos INEGI investigados o estudio del cliente recibido
- [ ] Cuenta de Resend creada y API key generada
- [ ] Confirmar email del comercializador (¿ferdiaz@punch.com.mx o uno dedicado?)
- [ ] Subir las fotos aéreas de Drive a Airtable `Config.FotosGenerales` para uso en home y PDF

---

Hecho con cariño por **Punch Marketing**.
