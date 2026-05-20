# Plaza Aria — Sitio web

**Fecha:** 2026-05-19
**Cliente:** Plaza Aria (gestionado por Punch Marketing)
**Autor:** Fernando Díaz

## Contexto

Plaza Aria es una plaza vecinal pequeña al aire libre, de dos pisos con estacionamiento techado, ubicada sobre Av. Huayacán en plena zona residencial de Cancún. El mix de locales incluye restaurantes, tiendas de muebles, manicura, barbería, clases de baile, spinning y clases de matemáticas. Funciona como "tercer lugar" del barrio: tráfico vecinal recurrente, no destino de fin de semana.

Fuentes disponibles para extraer contenido: Instagram `@plaza_aria` y carpeta de Google Drive con materiales del cliente.

## Objetivo

**Prioridad 1 (B): Rentar locales disponibles.** El sitio debe convertir prospectos en leads calificados para el comercializador.

**Prioridad 2 (A): Utilidad recurrente para vecinos.** Directorio vivo, horarios, agenda de clases. Esta puerta funciona como prueba social del leasing — un prospecto entra a ver locales y ve una plaza viva con comunidad.

**Prioridad 3 (C): Branding / posicionamiento.** Deseable pero subordinado a las dos anteriores.

## Enfoque elegido — "Dos puertas: Leasing + Vecinos"

Dos flujos claros que se refuerzan mutuamente. La Puerta Leasing es el motor de conversión. La Puerta Vecinos es la prueba de vida.

## Arquitectura de información

```
/ (Home)
├── Hero cinematográfico + dos CTAs: "Renta tu local" / "Explora la plaza"
│
├── /renta  (Puerta Leasing — prioridad B)
│   ├── Plano interactivo
│   ├── Dashboard "¿Por qué Aria?"
│   ├── Detalle de cada local disponible
│   └── Tour virtual / video drone
│
├── /directorio  (Puerta Vecinos — prioridad A)
│   ├── Grid de locales con filtros
│   ├── Ficha por local
│   └── Mini-mapa que conecta con el plano
│
├── /agenda  (clases y eventos)
│
└── /contacto
```

Las dos puertas se cruzan: desde el directorio se salta al plano; desde el plano se ven locales vecinos vivos; la agenda aparece como prueba de tráfico en el dashboard de leasing.

## Puerta Leasing — detalle

### Plano interactivo (WOW principal)
- Vista isométrica 2D estilizada de la plaza, dos pisos visibles, estacionamiento techado representado.
- SVG hecho a mano o exportado desde Figma, con coordenadas que matchean Airtable.
- Cada local es clickable. Ocupados muestran logo/nombre del negocio actual; disponibles tienen highlight sutil y badge "DISPONIBLE".
- Hover = preview rápido (m², renta aprox, foto). Click = panel lateral con detalle completo.
- Toggle Piso 1 / Piso 2.
- Filtros: "Solo disponibles", "Por tamaño", "Por giro sugerido".
- Animación de entrada: el plano se construye en ~3s (base → pisos → locales → brillan los disponibles).

### Dashboard "¿Por qué Aria?"
Tarjetas visuales:
- **La zona**: mapa de Huayacán con radio de captación, fraccionamientos cercanos, escuelas, densidad residencial.
- **Demografía**: ingreso promedio, edad, núcleos familiares (INEGI + estimaciones).
- **Mix actual**: gráfico de giros existentes (evita canibalización).
- **Gaps detectados**: "Aún no hay panadería / cafetería / farmacia en Aria" — gancho directo, alimentado desde tabla `Config`.
- **Tráfico**: cajones de estacionamiento, aforo estimado, horarios pico.

### Ficha de local disponible
Al hacer click en un local DISPONIBLE:
- Galería de fotos del espacio vacío.
- M², frente, instalaciones (agua, luz, drenaje, A/C, gas).
- Renta + mantenimiento.
- Locales vecinos (quién está al lado).
- Formulario corto: nombre, giro propuesto, WhatsApp, email, mensaje. Manda lead a Airtable + email al comercializador.

### Tour virtual
- Video drone 30-60 seg de la plaza (exterior, accesos, estacionamiento, fachadas).
- Fase 2 (no en alcance inicial): tour 360° de los locales disponibles.

### Generador de propuesta personalizada
Al enviar el formulario de lead, el prospecto recibe por email un PDF auto-generado con: su nombre y giro propuesto, mockup del local que le interesa, datos demográficos de la zona, mix actual y argumento de por qué su giro encaja. El comercializador usa esto como gancho de seguimiento.

## Puerta Vecinos — detalle

### Directorio
- Grid de tarjetas: foto del local, logo, nombre, giro, badge "Abierto ahora" / "Cierra pronto" / "Cerrado" (calculado dinámicamente desde horarios).
- Filtros: por giro (restaurantes, belleza, fitness, educación, hogar), por piso, "abierto ahora".
- Buscador rápido.
- Ordenamiento: alfabético, por piso, novedades.

### Ficha de local
- Hero con foto + logo.
- Horarios semana completa con cálculo dinámico de "abierto ahora".
- Descripción corta editable desde Airtable.
- Instagram embed con últimas 3 publicaciones del local — fuente de vida sin mantenimiento manual.
- WhatsApp / teléfono / menú PDF / link a reservar (según aplique).
- "Ubicación en el plano" → lleva al plano interactivo con el local resaltado.
- Locales vecinos (cross-sell entre tenants).

### Agenda (clases + eventos)
- Vista híbrida: calendario semanal + lista cronológica.
- Cada entrada: organizador (link al local), día/hora, cupo, descripción, foto.
- Filtros por tipo: fitness, educación, lifestyle, eventos especiales.
- **Reservas redirigen a WhatsApp/IG del local** — sin reservas ni pagos dentro del sitio (fase 2 si se requiere).
- "Esta semana en Aria" en home como teaser.

### Integraciones discretas
- **Clima de Cancún** en franja sutil ("Tarde de 28°, ideal para terraza") — relevante para plaza al aire libre.
- **Opt-in de notificaciones**: "Avísame cuando abra X / cuando haya evento de baile" — captura email sin login, escribe a Airtable.

## Modelo de datos en Airtable

El cliente debe poder actualizar todo desde Airtable sin tocar código.

### Tabla `Locales`
- ID, Nombre, Giro, Estado (`Ocupado` / `Disponible` / `Próximamente`)
- Piso (1/2), Número de local, Coordenadas en el plano (x, y, ancho, alto)
- M², Frente, Renta, Mantenimiento (visibles solo si Disponible)
- Fotos (attachments), Logo, Descripción corta
- Horarios L–D (apertura/cierre por día)
- WhatsApp, Teléfono, IG handle, Menú PDF, Link reservar
- Instalaciones (multi-select: agua, luz, A/C, drenaje, gas)

### Tabla `Eventos_Clases`
- Título, Local (link a Locales), Tipo (fitness/edu/lifestyle/evento)
- Día / recurrencia, Hora inicio, Hora fin, Cupo, Descripción, Foto
- Link reservar (WhatsApp/IG del local)

### Tabla `Leads_Renta` (escritura desde el sitio)
- Fecha, Nombre, WhatsApp, Email
- Local interesado (link a Locales)
- Giro propuesto, Mensaje, Estado seguimiento

### Tabla `Notif_Vecinos` (opt-ins de notificaciones)
- Email, Tipo de interés, Fecha alta

### Tabla `Config` (un solo registro)
- Hero video URL, fotos generales de la plaza
- Mix de giros faltantes (gaps a mostrar)
- Aforo estimado, cajones de estacionamiento
- Datos demográficos de la zona

### Mecánica de sincronización
- El sitio lee Airtable vía REST API desde el servidor (token en variable de entorno).
- Cache con ISR (revalidación cada 5 minutos).
- Webhook opcional de Airtable → endpoint `/api/revalidate` para refrescar al instante cuando cambia un campo crítico (ej. estado de local).
- Formularios escriben directo a `Leads_Renta` / `Notif_Vecinos` y disparan notificación al comercializador.

## Stack técnico

### Frontend
- **Next.js 14 (App Router) + TypeScript + Tailwind CSS**
- Server components para fetch de Airtable rápido y con SEO
- `framer-motion` para animaciones (plano, transiciones)
- `next/image` para optimización de imágenes
- Imágenes pesadas (drone, fotos) en Cloudinary o Vercel Blob

### Plano interactivo
- SVG con `<g>` por local, interactivo con React state + framer-motion
- Más ligero, mantenible y SEO-friendly que Canvas / Three.js

### Datos
- Airtable como CMS (REST API)
- ISR de Next.js cada 5 minutos
- Webhook de Airtable opcional para revalidación instantánea

### Formularios y notificaciones
- Endpoints en Next.js → escriben a Airtable
- **Resend** para emails al comercializador y PDFs auto-generados
- Honeypot + rate limit simple anti-spam

### Analytics
- Vercel Analytics + Plausible (privacy-friendly)
- Eventos custom: click en local disponible, envío de formulario, tiempo en plano, descarga de PDF

### Hosting
- **Sitio en Vercel** (free tier). Mejor performance, ISR nativo, deploy con git push.
- **Dominio comprado en Hostinger se apunta a Vercel vía DNS.**
- **Hostinger se conserva para correo gratis + email marketing con IA** (servicios que Vercel no ofrece).
- Costo extra mensual: $0.

## Detalles WOW para el pitch

- **Hero Day-to-Night**: video drone que cambia según la hora real del visitante; mensaje también cambia ("Buenos días, Aria" / etc.).
- **"Latido de Aria"**: franja en tiempo real ("🟢 12 locales abiertos ahora · 🎯 3 clases hoy · 📍 2 locales disponibles"). Se alimenta de Airtable.
- **Animación de entrada al plano**: ~3s, momento "ohhh" en pitch.
- **Generador de propuesta personalizada** (descrito en sección Leasing).
- **"Sábado en Aria"** en home: foto del día lleno + agenda + mosaico de IG posts recientes de varios locales.
- **Easter egg para pitch**: comando "punch" muestra animación corta de complicidad con el cliente; se usa solo en demo.
- **Performance**: meta Lighthouse 95+ móvil.
- **Demo mode** (toggle interno): placeholders elegantes mientras se consolida contenido real. Permite presentar al cliente sin esperar a tener todas las fotos.

## Alcance fuera de la versión inicial

- Reservas / pagos de clases dentro del sitio (sigue siendo redirección a WhatsApp/IG).
- Tour 360° de cada local disponible.
- Login de locatarios para que editen su propia ficha.
- Versión en inglés.

Estos quedan como fase 2 para evaluar después del lanzamiento.

## Métricas de éxito

- Leads en `Leads_Renta` por mes.
- Tasa de conversión: visitantes únicos en `/renta` → envío de formulario.
- Tiempo promedio en plano interactivo.
- Lighthouse 95+ en móvil al lanzamiento.
- Visitas recurrentes de vecinos (proxy del éxito de la Puerta Vecinos).

## Pendientes antes de implementar

- Recolectar del cliente: plano de la plaza, identificación de locales vacíos, m², renta, fotos de espacios, fotos/video drone, logos de locatarios.
- Confirmar acceso a Airtable (o crearlo).
- Confirmar dominio en Hostinger y permitir cambio de DNS hacia Vercel.
- Definir comercializador y correo / WhatsApp para recibir leads.
