# LocalenRenta.com.mx — Rediseño Completo

## Resumen

Rediseño total de localenrenta.com.mx, pasando de un sitio WordPress/Elementor corporativo a un sitio estático premium con efecto WOW. El sitio funciona como un "buscador por zona" de locales comerciales y naves industriales en el sureste de México, donde toda conversión se canaliza a WhatsApp.

## Contexto

- **Cliente:** Vivo Comercial (grupo inmobiliario)
- **Agencia:** PUNCH! Marketing
- **Público objetivo:** PyMEs que buscan rentar un local comercial en Cancún, Playa del Carmen, Mérida, Villahermosa, Cozumel, Puerto Aventuras
- **Sitio actual:** WordPress + Elementor Free en localenrenta.com.mx
- **Problema:** El sitio actual se ve genérico/corporativo, no transmite sensación de marketplace, tiene CTAs compitiendo (formulario + WhatsApp), y carece de estructura visual moderna

## Decisiones de diseño

### Marca
- Nombre: **"LocalenRenta"** como marca principal del sitio
- Co-branding: **"por Vivo Comercial"** visible en nav y footer como respaldo
- El sitio se siente como un buscador neutro de locales, no como la página corporativa de Vivo Comercial

### Paleta de colores
- Fondos: Blanco (#ffffff), gris claro (#f8fafc, #f9fafb)
- Acento principal: Azul (#2563eb)
- CTA WhatsApp: Verde (#25d366)
- Texto: Negro (#111111), gris (#666666)
- Tipografía: Inter o similar sans-serif moderna

### Acción principal
- **WhatsApp** es el único canal de conversión
- Los botones de WhatsApp envían mensajes pre-llenados con la zona de interés (ej: "Hola, me interesa un local en Av. Huayacán, Cancún")
- No hay formulario de contacto — todo va a WhatsApp
- **Cada zona tiene su propio número de WhatsApp** — el CTA de cada página de zona apunta al número del encargado de esa zona
- Los números de WhatsApp se proporcionan en un documento junto con los datos de cada zona (rangos de m², precios)
- El número de WhatsApp del nav/header apunta a un número general (por definir)
- El botón del CTA final del homepage también apunta al número general

### Contenido actualizable
- Las ciudades y zonas son el nivel de detalle que se mantiene actualizado
- No hay catálogo de locales individuales (no hay fotos individuales ni disponibilidad en tiempo real)
- La disponibilidad se maneja internamente en Airtable — el visitante solo ve rangos generales
- Para agregar/modificar zonas, se nos pide a nosotros (PUNCH) y re-subimos por FTP

## Arquitectura de páginas

### 1. Homepage

**Secciones (en orden):**

1. **Navegación**
   - Logo "LocalenRenta" + "por Vivo Comercial"
   - Links: Locales (ancla al selector del hero), Naves Industriales (página), Ubicaciones (ancla a sección de ciudades), Nosotros (página)
   - Botón WhatsApp en nav (verde, siempre visible, número general)

2. **Hero con selector**
   - Subtítulo: "Sureste de México"
   - Titular: "Encuentra tu local comercial ideal"
   - Bajada: "Ubicaciones estratégicas en las ciudades con mayor crecimiento"
   - Selector: Dropdown Ciudad → Dropdown Zona → Botón "Buscar"
   - Al seleccionar ciudad, las zonas se filtran dinámicamente

3. **Barra de social proof**
   - "Empresas que ya confían en nosotros"
   - Logos reales: OXXO, Telcel, izzi, Willys, Ópticas Hannia, Los Canastos, Multi Apoyos, Perfumes Europeos, Cq-Coz Express, Abarrotes Monterrey

4. **¿Por qué nosotros?**
   - Titular: "Rentar tu local no debería ser complicado"
   - 4 beneficios con ícono:
     - Ubicaciones estratégicas — zonas de alto tráfico en ciudades dinámicas
     - Empresa de confianza — respaldados por Vivo Comercial
     - Locales listos — se entregan adaptados, la condición no es problema
     - Visibilidad garantizada — ubicaciones donde te encuentran fácilmente

5. **Explorar por ciudad**
   - Titular: "Locales en todo el sureste de México"
   - Tarjetas con foto de cada ciudad + número de zonas disponibles
   - Ciudades: Cancún, Playa del Carmen, Mérida, Villahermosa, Cozumel, Puerto Aventuras
   - Click en tarjeta scrollea al selector del hero y pre-selecciona la ciudad, mostrando sus zonas disponibles

6. **Naves Industriales**
   - Layout split: foto a la izquierda, texto a la derecha
   - Titular: "Naves Industriales"
   - Texto breve + CTA "Ver naves disponibles →"

7. **3 Pasos (fondo azul)**
   - "Tu local en 3 pasos"
   - Paso 1: Elige tu zona
   - Paso 2: Platícanos tu proyecto (WhatsApp)
   - Paso 3: Visita y estrena

8. **CTA Final**
   - "¿Listo para encontrar tu local?"
   - Botón grande verde de WhatsApp

9. **Footer**
   - Logo co-branded
   - Lista de ciudades
   - Copyright

### 2. Página de Zona (17 páginas)

Una página por cada zona disponible. Template reutilizable, solo cambian los datos.

**Estructura:**

1. **Breadcrumb:** Inicio → Ciudad → Zona
2. **Encabezado de zona:**
   - Subtítulo: región (ej: "Cancún Sur")
   - Título: nombre de zona (ej: "Av. Huayacán y alrededores")
   - Descripción: párrafo sobre la zona (tráfico, cercanía a puntos clave, perfil comercial)
3. **Datos clave (tarjetas):**
   - Rango de tamaño de locales (ej: "30 — 120 m²")
   - Rango de renta mensual (ej: "$15,000 — $45,000 /mes")
4. **Tags de tipo de local:** Retail, Alimentos, Servicios, Oficina (según aplique)
5. **Fotos de la zona:** 3 fotos de la avenida/área (no del local individual)
6. **CTA pre-llenado:** "Quiero un local en Av. Huayacán" → abre WhatsApp con mensaje
7. **Otras zonas en la misma ciudad:** Tarjetas con nombre y "desde $X/mes"
8. **CTA secundario:** "Ver todas las ciudades →"
9. **Footer**

**Zonas a crear (basadas en el sitio actual):**

- **Cancún Sur:** Av. Huayacán y alrededores, Av. Nichupté y alrededores, Av. Las Torres
- **Cancún Norte:** 20 de Noviembre y alrededores, Aloja y alrededores, Arco vial y alrededores, Calzada Lakín, Cielo Nuevo, Paraíso Maya
- **Playa del Carmen:** Av 28 de Julio, Av. Petempich, Av. Constituyentes
- **Mérida:** San Marcos, Vega del Mayab
- **Villahermosa:** Villa el Cielo y alrededores
- **Cozumel:** El Encanto
- **Puerto Aventuras:** Calzada Puerto Maya

### 3. Página de Naves Industriales

**Secciones:**

1. **Nav** — Mismo que homepage
2. **Hero** — Titular "Naves Industriales en el Sureste de México", bajada sobre espacios industriales/logísticos, imagen de fondo de nave
3. **¿Qué ofrecemos?** — Beneficios específicos de naves: tamaño, acceso vehicular, ubicación logística
4. **Ubicaciones disponibles** — Tarjetas con las ciudades/zonas donde hay naves, con rangos de m² y renta (mismo formato que zonas de locales)
5. **CTA** — Botón WhatsApp con mensaje pre-llenado "Me interesan naves industriales"
6. **Footer** — Mismo que homepage

Nota: Los datos de naves (ubicaciones, m², precios, WhatsApp) se proporcionan junto con los datos de locales en el documento del cliente.

### 4. Página Nosotros

**Secciones:**

1. **Nav** — Mismo que homepage
2. **Hero sencillo** — Titular "Conoce a Vivo Comercial", bajada breve
3. **Quiénes somos** — Párrafo sobre Vivo Comercial como grupo inmobiliario, su presencia en el sureste, años de experiencia
4. **Cifras de respaldo** — Números animados: X locales rentados, X ciudades, X clientes satisfechos (datos que proporciona el cliente)
5. **Social proof** — Misma barra de logos de clientes
6. **CTA** — "¿Listo para ser parte?" + botón WhatsApp
7. **Footer** — Mismo que homepage

## Efectos e interacciones (WOW factor)

### Animaciones de scroll
- Elementos aparecen con animaciones cinemáticas al entrar en viewport (staggered timing)
- Las tarjetas de ciudad entran en cascada (una tras otra)
- Los números/datos hacen animación de conteo
- Secciones con parallax sutil en fondos e imágenes

### Interacciones de mouse
- Tarjetas de ciudad con efecto tilt 3D al mover el mouse
- Cards de beneficios con hover elevado y sombra dinámica
- Botones con efecto de onda/ripple al click

### Mapa interactivo (nice-to-have)
- SVG simplificado de la península de Yucatán y Tabasco (solo los estados relevantes, no todo México)
- Se construye como SVG inline con paths por estado/ciudad — no requiere asset externo complejo
- Hover sobre cada ciudad resalta la zona y muestra tooltip con número de zonas
- Click scrollea al selector y pre-selecciona la ciudad
- Si el timeline no alcanza, se omite y se reemplaza con las tarjetas de ciudad (que ya cumplen la misma función)

### Transiciones de página (nice-to-have)
- Transiciones suaves entre páginas usando View Transitions API (Chrome/Edge) con fallback graceful (sin transición) en browsers que no lo soporten
- Si el soporte de browsers es insuficiente al momento de implementar, se omite

### Selector de ciudad/zona
- Dropdowns con animación fluida al abrir/cerrar
- Al seleccionar ciudad, las zonas se cargan con animación
- Feedback visual al seleccionar (checkmark, cambio de color)

### Micro-interacciones
- Logos de social proof con efecto de scroll infinito (marquee)
- Botón de WhatsApp con pulso sutil para llamar atención
- Breadcrumb con hover animado
- Tags de tipo de local con hover de color

## Stack tecnológico

### Core
- **HTML5 + CSS3 + JavaScript vanilla** — sin build step, sin frameworks
- CSS moderno: grid, flexbox, custom properties
- Las 17 páginas de zona se generan desde un template base — cada archivo HTML es independiente y funcional

### Animaciones
- **GSAP 3** (GreenSock) + **ScrollTrigger** para animaciones cinemáticas de scroll — GSAP es gratuito para sitios accesibles públicamente (licencia "no charge" aplica a sitios web estándar, no SaaS)
- Implementación propia para efecto tilt 3D en tarjetas (sin dependencia extra)
- CSS transitions/animations para hover y micro-interacciones

### Assets
- Fotos optimizadas en WebP con fallback JPG
- SVG para íconos y mapa interactivo
- Fuentes: Inter self-hosted (descargada de Google Fonts como variable font, servida desde /assets/fonts/ para mejor performance y privacidad)

### Performance
- Target: Lighthouse Performance > 90 en mobile
- Peso máximo de página: < 2MB incluyendo imágenes
- Largest Contentful Paint: < 2.5s
- GSAP y ScrollTrigger se cargan con defer para no bloquear el render inicial
- Imágenes con lazy loading nativo (loading="lazy")

### SEO
- Cada página de zona tiene title tag optimizado (ej: "Local en Renta en Av. Huayacán, Cancún | LocalenRenta")
- Meta descriptions únicas por zona
- Open Graph tags para compartir en redes
- Structured data: LocalBusiness schema en cada página de zona
- sitemap.xml generado con todas las URLs
- URLs limpias y descriptivas (/zonas/cancun-av-huayacan.html)

### Deployment
- Archivos estáticos que se suben por FTP o administrador de archivos del hosting
- Sin build step en servidor — todo se genera localmente y se sube
- Estructura de carpetas limpia: index.html, /zonas/cancun-av-huayacan.html, /naves/, /nosotros/, /assets/

### Responsive / Mobile
- Diseño mobile-first con breakpoints: mobile (< 768px), tablet (768-1024px), desktop (> 1024px)
- Nav mobile: hamburger menu con animación de apertura
- Selector de ciudad/zona: stacked verticalmente en mobile
- Tarjetas de ciudad: 2 columnas en tablet, 1 columna en mobile (scroll horizontal como alternativa)
- Efectos mouse-dependent (tilt 3D, hover) se desactivan en touch devices — se reemplazan con tap feedback y animaciones de scroll que funcionan igual
- Botón flotante de WhatsApp en mobile (fijo en esquina inferior derecha)
- Todas las secciones se reorganizan a una columna en mobile

## Estructura de archivos

```
localenrenta/
├── index.html                          # Homepage
├── naves-industriales.html             # Página de naves
├── nosotros.html                       # Página nosotros
├── 404.html                            # Página de error
├── robots.txt                          # Directivas para crawlers
├── sitemap.xml                         # Mapa del sitio para SEO
├── favicon.ico                         # Favicon
├── zonas/
│   ├── cancun-av-huayacan.html
│   ├── cancun-av-nichupte.html
│   ├── cancun-av-las-torres.html
│   ├── cancun-20-de-noviembre.html
│   ├── cancun-aloja.html
│   ├── cancun-arco-vial.html
│   ├── cancun-calzada-lakin.html
│   ├── cancun-cielo-nuevo.html
│   ├── cancun-paraiso-maya.html
│   ├── playa-av-28-de-julio.html
│   ├── playa-av-petempich.html
│   ├── playa-av-constituyentes.html
│   ├── merida-san-marcos.html
│   ├── merida-vega-del-mayab.html
│   ├── villahermosa-villa-el-cielo.html
│   ├── cozumel-el-encanto.html
│   └── puerto-aventuras-calzada-puerto-maya.html
├── assets/
│   ├── css/
│   │   ├── main.css                    # Estilos globales
│   │   └── animations.css              # Animaciones y efectos
│   ├── js/
│   │   ├── main.js                     # Lógica global
│   │   ├── animations.js              # GSAP y ScrollTrigger
│   │   ├── selector.js                # Lógica del selector ciudad/zona
│   │   └── map.js                     # Mapa interactivo SVG
│   ├── img/
│   │   ├── logos/                     # Logos de clientes
│   │   ├── ciudades/                  # Fotos de ciudades
│   │   ├── zonas/                     # Fotos de zonas
│   │   └── naves/                     # Fotos de naves
│   └── fonts/                         # Inter variable font
└── data/
    └── zonas.json                     # Datos de todas las zonas — se carga via fetch() en selector.js para poblar los dropdowns del hero dinámicamente
```

## Datos por zona (estructura)

Cada zona necesita estos datos (se pueden mantener en un JSON central):

```json
{
  "id": "cancun-av-huayacan",
  "ciudad": "Cancún",
  "region": "Cancún Sur",
  "nombre": "Av. Huayacán y alrededores",
  "descripcion": "Zona de alto tráfico vehicular y peatonal...",
  "tamano_min": 30,
  "tamano_max": 120,
  "renta_min": 15000,
  "renta_max": 45000,
  "tipos": ["Retail", "Alimentos", "Servicios", "Oficina"],
  "fotos": ["huayacan-1.webp", "huayacan-2.webp", "huayacan-3.webp"],
  "whatsapp_numero": "521XXXXXXXXXX",
  "whatsapp_mensaje": "Hola, me interesa un local en Av. Huayacán, Cancún. ¿Qué opciones tienen disponibles?"
}
```

## Páginas adicionales

### Página 404
- Diseño consistente con el sitio
- Mensaje amigable + botón para volver al inicio
- Sugerencia de usar el buscador

## Prerequisitos del cliente

Antes de iniciar implementación, el cliente (Vivo Comercial / PUNCH) debe proporcionar:
1. **Documento de datos por zona:** número de WhatsApp, rango de m², rango de precios, tipos de local
2. **Número de WhatsApp general** (para nav y CTA del homepage)
3. **Fotos de cada zona** (3 fotos de la avenida/área por zona) — si no están disponibles, se usan fotos de stock de cada ciudad como placeholder
4. **Fotos de ciudades** (1 foto representativa por ciudad para las tarjetas)
5. **Logos de clientes** en alta resolución (PNG/SVG transparente)
6. **Logo de Vivo Comercial** en alta resolución
7. **Descripción breve de cada zona** (o aprobación para que PUNCH las redacte)
8. **Fotos/info de naves industriales** para la página dedicada

## Fuera de alcance

- Conexión en tiempo real con Airtable (la disponibilidad se consulta internamente por el equipo)
- Catálogo de locales individuales con fotos propias
- Sistema de login o cuentas de usuario
- Blog o sección de contenido editorial
- Pasarela de pagos o reservas online
- Versión en inglés (solo español)
