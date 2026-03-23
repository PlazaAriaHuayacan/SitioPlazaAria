# LocalenRenta.com.mx Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a premium static website for LocalenRenta.com.mx — a zone-based commercial rental search site with WhatsApp CTAs, cinematic scroll animations, and modern design.

**Architecture:** Static HTML/CSS/JS site with no build step. A central `zonas.json` data file feeds the homepage selector. Each zone has its own HTML page generated from a template pattern. GSAP + ScrollTrigger power all animations. Everything deploys via FTP upload.

**Tech Stack:** HTML5, CSS3 (custom properties, grid, flexbox), vanilla JavaScript, GSAP 3 + ScrollTrigger, Inter font (self-hosted)

**Spec:** `docs/superpowers/specs/2026-03-23-localenrenta-redesign-design.md`

**Note on CSS file structure:** The spec lists 2 CSS files (`main.css`, `animations.css`). This plan deliberately splits CSS into focused files (`reset.css`, `variables.css`, `base.css`, `nav.css`, `hero.css`, `sections.css`, `zona.css`, `footer.css`, `animations.css`) for maintainability. This is an improvement over the spec's simplified listing.

**Note on SVG map:** The spec marks the interactive SVG map as nice-to-have. This plan defers it entirely — city cards serve the same navigation purpose. If desired later, it can be added as a standalone task.

**Note on images:** All `<img>` tags use the `<picture>` element with WebP source and JPG fallback, per the spec. During development, CSS gradient placeholders are used until real photos are provided.

---

## File Structure

```
localenrenta/
├── index.html
├── naves-industriales.html
├── nosotros.html
├── 404.html
├── robots.txt
├── sitemap.xml
├── favicon.ico
├── zonas/                             # 17 zone pages
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
│   │   ├── reset.css                  # CSS reset/normalize
│   │   ├── variables.css              # Design tokens (colors, spacing, typography, breakpoints)
│   │   ├── base.css                   # Global styles, utilities, animation classes
│   │   ├── nav.css                    # Navigation + mobile hamburger
│   │   ├── footer.css                 # Footer + floating WhatsApp button
│   │   ├── hero.css                   # Hero section + selector dropdowns
│   │   ├── sections.css               # Homepage sections (social proof, benefits, cities, naves, steps, CTA)
│   │   ├── zona.css                   # Zone page styles
│   │   └── animations.css             # GSAP animation classes + CSS transitions/keyframes
│   ├── js/
│   │   ├── main.js                    # Nav toggle, scroll shadow, smooth scroll, floating WhatsApp
│   │   ├── animations.js             # GSAP + ScrollTrigger setup
│   │   ├── selector.js               # City/zone selector logic
│   │   ├── tilt.js                   # 3D tilt effect (mouse-tracking)
│   │   └── counter.js                # Animated number counter
│   ├── img/
│   │   ├── logos/                    # Client logos (PNG transparent)
│   │   ├── ciudades/                 # City photos (WebP + JPG)
│   │   ├── zonas/                    # Zone photos (WebP + JPG)
│   │   └── naves/                    # Warehouse photos
│   └── fonts/
│       └── inter-variable.woff2      # Inter variable font
└── data/
    └── zonas.json                    # All zone data (fetched by selector.js at runtime)
```

---

## Task 1: Project Scaffolding + CSS Foundation

**Files:**
- Create: `localenrenta/assets/css/reset.css`
- Create: `localenrenta/assets/css/variables.css`
- Create: `localenrenta/assets/css/base.css`
- Create: `localenrenta/assets/fonts/inter-variable.woff2`

- [ ] **Step 1: Create project directory structure**

```bash
mkdir -p localenrenta/{assets/{css,js,img/{logos,ciudades,zonas,naves},fonts},zonas,data}
```

- [ ] **Step 2: Download Inter variable font**

Download from Google Fonts. If the URL below is stale, download manually from https://fonts.google.com/specimen/Inter (select "Download family", extract the variable .woff2 file).

```bash
curl -L "https://fonts.gstatic.com/s/inter/v18/UcCo3FwrK3iLTcviYwY.woff2" -o localenrenta/assets/fonts/inter-variable.woff2
```

- [ ] **Step 3: Generate favicon**

Create a simple favicon with the "L" from LocalenRenta in blue (#2563eb). Use an online generator (realfavicongenerator.net) or create a 32x32 SVG and convert to .ico. Place at `localenrenta/favicon.ico`.

- [ ] **Step 4: Create reset.css**

File: `localenrenta/assets/css/reset.css`

```css
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; -webkit-text-size-adjust: 100%; }
body { min-height: 100vh; text-rendering: optimizeLegibility; -webkit-font-smoothing: antialiased; }
img, picture, video, canvas, svg { display: block; max-width: 100%; }
input, button, textarea, select { font: inherit; }
p, h1, h2, h3, h4, h5, h6 { overflow-wrap: break-word; }
a { text-decoration: none; color: inherit; }
ul, ol { list-style: none; }
```

- [ ] **Step 5: Create variables.css**

File: `localenrenta/assets/css/variables.css` — All design tokens including THREE breakpoints.

```css
@font-face {
  font-family: 'Inter';
  src: url('../fonts/inter-variable.woff2') format('woff2');
  font-weight: 100 900;
  font-display: swap;
}

:root {
  /* Colors */
  --color-white: #ffffff;
  --color-gray-50: #f8fafc;
  --color-gray-100: #f1f5f9;
  --color-gray-200: #e2e8f0;
  --color-gray-400: #94a3b8;
  --color-gray-500: #64748b;
  --color-gray-600: #475569;
  --color-gray-700: #334155;
  --color-gray-800: #1e293b;
  --color-gray-900: #0f172a;
  --color-black: #111111;
  --color-blue: #2563eb;
  --color-blue-light: #eff6ff;
  --color-blue-dark: #1d4ed8;
  --color-green: #25d366;
  --color-green-dark: #1da851;

  /* Typography */
  --font-family: 'Inter', system-ui, -apple-system, sans-serif;
  --font-size-xs: 0.75rem;
  --font-size-sm: 0.875rem;
  --font-size-base: 1rem;
  --font-size-lg: 1.125rem;
  --font-size-xl: 1.25rem;
  --font-size-2xl: 1.5rem;
  --font-size-3xl: 1.875rem;
  --font-size-4xl: 2.25rem;
  --font-size-5xl: 3rem;

  /* Spacing */
  --space-1: 0.25rem;
  --space-2: 0.5rem;
  --space-3: 0.75rem;
  --space-4: 1rem;
  --space-6: 1.5rem;
  --space-8: 2rem;
  --space-12: 3rem;
  --space-16: 4rem;
  --space-20: 5rem;
  --space-24: 6rem;

  /* Layout */
  --max-width: 1200px;
  --nav-height: 72px;
  --border-radius-sm: 8px;
  --border-radius-md: 12px;
  --border-radius-lg: 16px;
  --border-radius-full: 9999px;

  /* Breakpoints (for reference — use in media queries) */
  /* Mobile: < 768px */
  /* Tablet: 768px - 1024px */
  /* Desktop: > 1024px */

  /* Shadows */
  --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
  --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -2px rgba(0,0,0,0.1);
  --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -4px rgba(0,0,0,0.1);
  --shadow-xl: 0 20px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1);

  /* Transitions */
  --transition-fast: 150ms ease;
  --transition-base: 250ms ease;
  --transition-slow: 400ms ease;
}
```

- [ ] **Step 6: Create base.css**

File: `localenrenta/assets/css/base.css` — Global styles with all 3 breakpoints.

```css
/* Skip to content link for accessibility */
.skip-link {
  position: absolute;
  top: -40px;
  left: 0;
  background: var(--color-blue);
  color: var(--color-white);
  padding: var(--space-2) var(--space-4);
  z-index: 100;
  transition: top var(--transition-fast);
}
.skip-link:focus { top: 0; }

body {
  font-family: var(--font-family);
  font-size: var(--font-size-base);
  color: var(--color-black);
  background: var(--color-white);
  line-height: 1.6;
}

.container {
  width: 100%;
  max-width: var(--max-width);
  margin: 0 auto;
  padding: 0 var(--space-6);
}

.section-label {
  font-size: var(--font-size-xs);
  color: var(--color-blue);
  text-transform: uppercase;
  letter-spacing: 2px;
  font-weight: 600;
  margin-bottom: var(--space-2);
}

.section-title {
  font-size: var(--font-size-3xl);
  font-weight: 700;
  color: var(--color-black);
  margin-bottom: var(--space-6);
}

/* Buttons */
.btn {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-3) var(--space-6);
  border-radius: var(--border-radius-md);
  font-weight: 600;
  font-size: var(--font-size-base);
  cursor: pointer;
  border: none;
  transition: transform var(--transition-fast), box-shadow var(--transition-fast);
}
.btn:hover { transform: translateY(-2px); box-shadow: var(--shadow-lg); }
.btn:active { transform: translateY(0); }
.btn:focus-visible { outline: 3px solid var(--color-blue); outline-offset: 2px; }

.btn-whatsapp { background: var(--color-green); color: var(--color-white); }
.btn-whatsapp:hover { background: var(--color-green-dark); }
.btn-primary { background: var(--color-blue); color: var(--color-white); }
.btn-primary:hover { background: var(--color-blue-dark); }
.btn-dark { background: var(--color-black); color: var(--color-white); }

/* Animation utility classes — elements start hidden, GSAP reveals them */
.anim-fade-up { opacity: 0; transform: translateY(40px); }
.anim-fade-in { opacity: 0; }
.anim-scale-in { opacity: 0; transform: scale(0.9); }

/* Image with WebP fallback pattern */
.img-placeholder {
  background: linear-gradient(135deg, var(--color-gray-200), var(--color-gray-100));
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--color-gray-400);
  font-size: var(--font-size-sm);
}

/* Responsive: Tablet */
@media (max-width: 1024px) {
  .container { padding: 0 var(--space-4); }
  .section-title { font-size: var(--font-size-2xl); }
}

/* Responsive: Mobile */
@media (max-width: 768px) {
  .container { padding: 0 var(--space-4); }
  .section-title { font-size: var(--font-size-xl); }
}
```

- [ ] **Step 7: Commit**

```bash
git add localenrenta/
git commit -m "feat: project scaffolding with CSS foundation, design tokens, Inter font, favicon"
```

---

## Task 2: Navigation + Footer + Floating WhatsApp

**Files:**
- Create: `localenrenta/assets/css/nav.css`
- Create: `localenrenta/assets/css/footer.css`
- Create: `localenrenta/assets/js/main.js`

**Note:** Footer and floating WhatsApp are created here (early) so all subsequent pages can include them from the start.

- [ ] **Step 1: Create nav.css**

File: `localenrenta/assets/css/nav.css`

Expected HTML structure for the nav:
```html
<a href="#main-content" class="skip-link">Saltar al contenido</a>
<header class="nav" role="banner">
  <div class="nav__inner container">
    <a href="/" class="nav__logo">
      <span class="nav__brand">Local<span class="nav__brand-accent">en</span>Renta</span>
      <span class="nav__sub">por Vivo Comercial</span>
    </a>
    <nav class="nav__links" role="navigation" aria-label="Navegación principal">
      <a href="/#locales" class="nav__link">Locales</a>
      <a href="/naves-industriales.html" class="nav__link">Naves Industriales</a>
      <a href="/#ubicaciones" class="nav__link">Ubicaciones</a>
      <a href="/nosotros.html" class="nav__link">Nosotros</a>
      <a href="https://wa.me/GENERAL_NUMBER" class="btn btn-whatsapp nav__wa" target="_blank" rel="noopener">💬 WhatsApp</a>
    </nav>
    <button class="nav__hamburger" aria-label="Abrir menú" aria-expanded="false">
      <span></span><span></span><span></span>
    </button>
  </div>
</header>
```

CSS must include:
- Fixed position, white background, z-index: 1000
- `.nav--scrolled` class adds shadow (applied via JS)
- Desktop (>768px): horizontal flex layout, links visible
- Mobile (<=768px): hamburger visible, links hidden. `.nav--open` triggers full-screen overlay with fade-in, links centered vertically
- Hamburger spans animate to X when open
- `.nav__brand-accent` in blue color
- `.nav__sub` smaller, gray text

- [ ] **Step 2: Create footer.css**

File: `localenrenta/assets/css/footer.css`

Expected HTML structure:
```html
<footer class="footer">
  <div class="footer__inner container">
    <div class="footer__brand">
      <span class="footer__logo">LocalenRenta</span>
      <span class="footer__sub">por Vivo Comercial</span>
    </div>
    <div class="footer__cities">Cancún · Playa del Carmen · Mérida · Villahermosa · Cozumel</div>
    <div class="footer__copy">© 2026 LocalenRenta. Todos los derechos reservados.</div>
  </div>
</footer>

<!-- Floating WhatsApp button — on ALL pages -->
<a href="https://wa.me/GENERAL_NUMBER" class="wa-float" target="_blank" rel="noopener" aria-label="Contactar por WhatsApp">
  <svg><!-- WhatsApp icon SVG --></svg>
</a>
```

CSS:
- Dark background (#111), light text
- Flex layout, stacked on mobile
- `.wa-float`: fixed bottom-right, green circle, z-index: 999, pulse animation keyframe

- [ ] **Step 3: Create main.js**

File: `localenrenta/assets/js/main.js`

```javascript
document.addEventListener('DOMContentLoaded', () => {
  // --- Mobile nav toggle ---
  const hamburger = document.querySelector('.nav__hamburger');
  const nav = document.querySelector('.nav');
  if (hamburger) {
    hamburger.addEventListener('click', () => {
      const isOpen = nav.classList.toggle('nav--open');
      hamburger.setAttribute('aria-expanded', isOpen);
      document.body.style.overflow = isOpen ? 'hidden' : '';
    });
  }

  // --- Nav shadow on scroll ---
  window.addEventListener('scroll', () => {
    nav?.classList.toggle('nav--scrolled', window.scrollY > 10);
  }, { passive: true });

  // --- Smooth scroll for anchor links ---
  document.querySelectorAll('a[href^="#"]').forEach(link => {
    link.addEventListener('click', (e) => {
      const target = document.querySelector(link.getAttribute('href'));
      if (target) {
        e.preventDefault();
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        // Close mobile nav if open
        nav?.classList.remove('nav--open');
        hamburger?.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
      }
    });
  });

  // --- Close mobile nav on Escape ---
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && nav?.classList.contains('nav--open')) {
      nav.classList.remove('nav--open');
      hamburger?.setAttribute('aria-expanded', 'false');
      document.body.style.overflow = '';
      hamburger?.focus();
    }
  });
});
```

- [ ] **Step 4: Create minimal test page**

Create `localenrenta/index.html` with just the nav, a tall content div (to test scroll), footer, and floating WhatsApp. Link all CSS and JS. Verify:
- Nav displays correctly on desktop
- Hamburger works on mobile
- Shadow appears on scroll
- Footer renders at bottom
- Floating WhatsApp button pulses in corner
- Skip-link works with Tab key

- [ ] **Step 5: Commit**

```bash
git add localenrenta/
git commit -m "feat: responsive nav, footer, floating WhatsApp with accessibility"
```

---

## Task 3: Homepage Hero + City/Zone Selector

**Files:**
- Create: `localenrenta/assets/css/hero.css`
- Create: `localenrenta/assets/js/selector.js`
- Create: `localenrenta/data/zonas.json`

- [ ] **Step 1: Create zonas.json with placeholder data**

File: `localenrenta/data/zonas.json` — Complete array of all 17 zones with placeholder data. Sample entry:

```json
[
  {
    "id": "cancun-av-huayacan",
    "ciudad": "Cancún",
    "region": "Cancún Sur",
    "nombre": "Av. Huayacán y alrededores",
    "descripcion": "Zona de alto tráfico vehicular y peatonal en el corazón de Cancún Sur.",
    "tamano_min": 30,
    "tamano_max": 120,
    "renta_min": 15000,
    "renta_max": 45000,
    "tipos": ["Retail", "Alimentos", "Servicios", "Oficina"],
    "fotos": ["huayacan-1.webp", "huayacan-2.webp", "huayacan-3.webp"],
    "whatsapp_numero": "521XXXXXXXXXX",
    "whatsapp_mensaje": "Hola, me interesa un local en Av. Huayacán, Cancún. ¿Qué opciones tienen disponibles?"
  }
]
```

Include all 17 zones with placeholder phone numbers and realistic placeholder descriptions.

- [ ] **Step 2: Create hero.css**

File: `localenrenta/assets/css/hero.css`

Expected HTML structure for the selector:
```html
<section class="hero" id="locales">
  <div class="hero__inner container">
    <p class="section-label">Sureste de México</p>
    <h1 class="hero__title">Encuentra tu local comercial ideal</h1>
    <p class="hero__subtitle">Ubicaciones estratégicas en las ciudades con mayor crecimiento</p>
    <div class="selector">
      <div class="selector__dropdown" data-dropdown="ciudad">
        <button class="selector__trigger" aria-haspopup="listbox" aria-expanded="false">
          <span class="selector__label">Ciudad</span>
          <span class="selector__value">Selecciona ▾</span>
        </button>
        <ul class="selector__list" role="listbox" aria-label="Seleccionar ciudad">
          <!-- Populated by selector.js -->
        </ul>
      </div>
      <div class="selector__dropdown" data-dropdown="zona">
        <button class="selector__trigger" aria-haspopup="listbox" aria-expanded="false" disabled>
          <span class="selector__label">Zona</span>
          <span class="selector__value">Primero elige ciudad ▾</span>
        </button>
        <ul class="selector__list" role="listbox" aria-label="Seleccionar zona">
          <!-- Populated by selector.js after city selection -->
        </ul>
      </div>
      <button class="btn btn-primary selector__search" disabled>Buscar →</button>
    </div>
  </div>
</section>
```

CSS:
- Hero: full-width, gradient background (white → light blue), generous padding (120px top, 80px bottom)
- Selector row: flex, gap, centered. Stacks vertically on mobile (<768px)
- `.selector__dropdown`: white background, rounded, border, relative position
- `.selector__list`: absolute, below trigger, max-height with overflow scroll, shadow, hidden by default
- `.selector__list[aria-expanded="true"]` or `.selector__dropdown--open .selector__list`: visible with slide-down animation
- List items: padding, hover highlight, checkmark SVG on selected item
- Tablet (768-1024px): selector row stays horizontal but narrower

- [ ] **Step 3: Create selector.js**

File: `localenrenta/assets/js/selector.js`

```javascript
document.addEventListener('DOMContentLoaded', async () => {
  let zonas = [];
  let selectedCiudad = null;
  let selectedZona = null;

  // Fetch zone data
  try {
    const res = await fetch('/data/zonas.json');
    zonas = await res.json();
  } catch (e) {
    console.error('Error loading zonas.json:', e);
    return;
  }

  const ciudadDropdown = document.querySelector('[data-dropdown="ciudad"]');
  const zonaDropdown = document.querySelector('[data-dropdown="zona"]');
  const searchBtn = document.querySelector('.selector__search');

  // Get unique cities
  const ciudades = [...new Set(zonas.map(z => z.ciudad))];

  // Populate city list
  const ciudadList = ciudadDropdown?.querySelector('.selector__list');
  ciudades.forEach(ciudad => {
    const li = document.createElement('li');
    li.role = 'option';
    li.textContent = ciudad;
    li.dataset.value = ciudad;
    li.addEventListener('click', () => selectCiudad(ciudad));
    ciudadList?.appendChild(li);
  });

  function selectCiudad(ciudad) {
    selectedCiudad = ciudad;
    selectedZona = null;
    // Update trigger text
    ciudadDropdown.querySelector('.selector__value').textContent = ciudad + ' ▾';
    closeDropdown(ciudadDropdown);

    // Enable and populate zona dropdown
    const zonaTrigger = zonaDropdown.querySelector('.selector__trigger');
    zonaTrigger.disabled = false;
    zonaDropdown.querySelector('.selector__value').textContent = 'Selecciona zona ▾';

    const zonaList = zonaDropdown.querySelector('.selector__list');
    zonaList.innerHTML = '';
    const filtered = zonas.filter(z => z.ciudad === ciudad);
    filtered.forEach(zona => {
      const li = document.createElement('li');
      li.role = 'option';
      li.textContent = zona.nombre;
      li.dataset.value = zona.id;
      li.addEventListener('click', () => selectZona(zona));
      zonaList.appendChild(li);
    });

    searchBtn.disabled = true;
  }

  function selectZona(zona) {
    selectedZona = zona;
    zonaDropdown.querySelector('.selector__value').textContent = zona.nombre + ' ▾';
    closeDropdown(zonaDropdown);
    searchBtn.disabled = false;
  }

  // Search button → navigate to zone page
  searchBtn?.addEventListener('click', () => {
    if (selectedZona) {
      window.location.href = '/zonas/' + selectedZona.id + '.html';
    }
  });

  // Dropdown open/close logic
  document.querySelectorAll('.selector__trigger').forEach(trigger => {
    trigger.addEventListener('click', (e) => {
      if (trigger.disabled) return;
      const dropdown = trigger.closest('.selector__dropdown');
      const isOpen = dropdown.classList.contains('selector__dropdown--open');
      closeAllDropdowns();
      if (!isOpen) openDropdown(dropdown);
    });
  });

  function openDropdown(dd) {
    dd.classList.add('selector__dropdown--open');
    dd.querySelector('.selector__trigger').setAttribute('aria-expanded', 'true');
  }
  function closeDropdown(dd) {
    dd.classList.remove('selector__dropdown--open');
    dd.querySelector('.selector__trigger').setAttribute('aria-expanded', 'false');
  }
  function closeAllDropdowns() {
    document.querySelectorAll('.selector__dropdown').forEach(closeDropdown);
  }

  // Close on click outside
  document.addEventListener('click', (e) => {
    if (!e.target.closest('.selector__dropdown')) closeAllDropdowns();
  });

  // Close on Escape
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeAllDropdowns();
  });

  // --- Public method for city pre-selection (used by city cards) ---
  window.preselectCiudad = function(ciudad) {
    selectCiudad(ciudad);
    document.querySelector('#locales')?.scrollIntoView({ behavior: 'smooth' });
    setTimeout(() => openDropdown(zonaDropdown), 600);
  };
});
```

- [ ] **Step 4: Update index.html with hero**

Add the hero section markup to `index.html` after the nav. Verify:
- Cities populate from JSON
- Selecting city enables and filters zones
- Selecting zone enables search button
- Clicking search navigates to correct URL
- Mobile: selector stacks vertically
- Keyboard: Escape closes dropdowns

- [ ] **Step 5: Commit**

```bash
git add localenrenta/
git commit -m "feat: homepage hero with dynamic city/zone selector and accessibility"
```

---

## Task 4: Homepage Sections

**Files:**
- Create: `localenrenta/assets/css/sections.css`
- Modify: `localenrenta/index.html`

- [ ] **Step 1: Create sections.css**

File: `localenrenta/assets/css/sections.css`

Styles for all 6 content sections. Each section should use all 3 breakpoints (mobile <768, tablet 768-1024, desktop >1024):

1. **Social proof bar** (`.social-proof`): logos in CSS marquee (infinite scroll animation via `@keyframes marquee`), grayscale filter → color on hover, subtle top/bottom border
2. **Benefits** (`.benefits`): 4-column grid → 2 columns on tablet → 1 on mobile. Cards with icon, title, description on light background. `.tilt-card` class for 3D effect
3. **Cities** (`.cities`): grid of cards with CSS gradient placeholder backgrounds (until real photos). City name + zone count overlay. Hover: scale image 1.05. Cards are buttons with `onclick="preselectCiudad('Cancún')"`. 3 columns → 2 on tablet → 1 on mobile (or horizontal scroll)
4. **Naves promo** (`.naves-promo`): split 50/50 layout → stacks on mobile. Image left, text + CTA right
5. **Steps** (`.steps`): blue background (#2563eb), 3-column → 1 on mobile. Numbered circles with connecting line on desktop (CSS border-top on a pseudo-element)
6. **CTA final** (`.cta-final`): centered, light background, big WhatsApp button

- [ ] **Step 2: Add section HTML markup to index.html**

Add all 6 sections after the hero in `index.html`. Key details:
- Social proof logos: use text placeholders (OXXO, Telcel, etc.) until real logo PNGs are provided
- City cards: use CSS gradient backgrounds as image placeholders. Each card calls `onclick="preselectCiudad('CityName')"` to scroll to selector and pre-select that city
- Benefits: use SVG icons (inline) or emoji placeholders
- All animated elements get `class="anim-fade-up"`
- `id="ubicaciones"` on the cities section (for nav anchor link)

- [ ] **Step 3: Add meta tags and structured data to index.html**

Add to `<head>`:
```html
<title>Local en Renta | Locales Comerciales en el Sureste de México</title>
<meta name="description" content="Encuentra tu local comercial ideal en Cancún, Playa del Carmen, Mérida y Villahermosa. Ubicaciones estratégicas para hacer crecer tu negocio.">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta property="og:title" content="Local en Renta | Locales Comerciales en el Sureste de México">
<meta property="og:description" content="Encuentra tu local comercial ideal en el sureste de México">
<meta property="og:type" content="website">
<meta property="og:url" content="https://localenrenta.com.mx/">
<link rel="icon" href="/favicon.ico">
<meta name="view-transition" content="same-origin">
```

Add before `</body>`:
```html
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Organization",
  "name": "LocalenRenta",
  "url": "https://localenrenta.com.mx",
  "description": "Locales comerciales en renta en el sureste de México",
  "parentOrganization": {
    "@type": "Organization",
    "name": "Vivo Comercial"
  }
}
</script>
```

- [ ] **Step 4: Test complete homepage**

Verify in browser at all 3 breakpoints (375px, 768px, 1200px):
- All sections render correctly
- Logo marquee scrolls
- City cards call `preselectCiudad()` → scrolls to hero, pre-selects city, opens zone dropdown
- Benefits show 4/2/1 columns
- Steps show horizontal line on desktop, vertical on mobile
- No horizontal scroll on any breakpoint

- [ ] **Step 5: Commit**

```bash
git add localenrenta/
git commit -m "feat: complete homepage with all sections, city card interaction, SEO meta"
```

---

## Task 5: GSAP Animations + Scroll Effects

**Files:**
- Create: `localenrenta/assets/css/animations.css`
- Create: `localenrenta/assets/js/animations.js`
- Create: `localenrenta/assets/js/tilt.js`

- [ ] **Step 1: Create animations.css**

File: `localenrenta/assets/css/animations.css`

```css
/* Button ripple effect */
.btn { position: relative; overflow: hidden; }
.btn::after {
  content: '';
  position: absolute;
  inset: 0;
  background: radial-gradient(circle, rgba(255,255,255,0.3) 10%, transparent 70%);
  transform: scale(0);
  opacity: 0;
  transition: transform 0.5s, opacity 0.3s;
}
.btn:active::after { transform: scale(2.5); opacity: 1; transition: 0s; }

/* Card hover lift */
.card-hover {
  transition: transform var(--transition-base), box-shadow var(--transition-base);
}
.card-hover:hover {
  transform: translateY(-4px);
  box-shadow: var(--shadow-xl);
}

/* Logo grayscale → color */
.logo-gray {
  filter: grayscale(100%);
  opacity: 0.6;
  transition: filter var(--transition-base), opacity var(--transition-base);
}
.logo-gray:hover { filter: grayscale(0%); opacity: 1; }

/* WhatsApp floating pulse */
@keyframes wa-pulse {
  0%, 100% { box-shadow: 0 0 0 0 rgba(37, 211, 102, 0.4); }
  50% { box-shadow: 0 0 0 12px rgba(37, 211, 102, 0); }
}
.wa-float { animation: wa-pulse 2s infinite; }

/* Marquee for social proof */
@keyframes marquee {
  0% { transform: translateX(0); }
  100% { transform: translateX(-50%); }
}
```

- [ ] **Step 2: Create animations.js**

File: `localenrenta/assets/js/animations.js`

Add GSAP CDN scripts to all HTML pages (in `<head>` with `defer`):
```html
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js" defer></script>
```

```javascript
// Wait for GSAP to load (since it's deferred)
window.addEventListener('load', () => {
  if (typeof gsap === 'undefined') return;
  gsap.registerPlugin(ScrollTrigger);

  const isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0;

  // Staggered fade-up for all animated elements
  ScrollTrigger.batch('.anim-fade-up', {
    onEnter: (elements) => {
      gsap.to(elements, {
        opacity: 1, y: 0, duration: 0.8,
        stagger: 0.12, ease: 'power3.out'
      });
    },
    start: 'top 85%'
  });

  // Scale-in for benefit cards
  ScrollTrigger.batch('.anim-scale-in', {
    onEnter: (elements) => {
      gsap.to(elements, {
        opacity: 1, scale: 1, duration: 0.6,
        stagger: 0.1, ease: 'back.out(1.2)'
      });
    },
    start: 'top 85%'
  });

  // Parallax on hero (desktop only)
  if (!isTouchDevice) {
    const heroSection = document.querySelector('.hero');
    if (heroSection) {
      gsap.to(heroSection, {
        backgroundPosition: '50% 30%',
        ease: 'none',
        scrollTrigger: {
          trigger: heroSection,
          start: 'top top',
          end: 'bottom top',
          scrub: true
        }
      });
    }
  }
});
```

- [ ] **Step 3: Create tilt.js**

File: `localenrenta/assets/js/tilt.js`

```javascript
document.addEventListener('DOMContentLoaded', () => {
  if ('ontouchstart' in window) return; // Skip on touch devices

  document.querySelectorAll('.tilt-card').forEach(card => {
    card.addEventListener('mousemove', (e) => {
      const rect = card.getBoundingClientRect();
      const x = (e.clientX - rect.left) / rect.width - 0.5;
      const y = (e.clientY - rect.top) / rect.height - 0.5;
      card.style.transform = `perspective(1000px) rotateX(${-y * 8}deg) rotateY(${x * 8}deg) scale(1.02)`;
    });

    card.addEventListener('mouseleave', () => {
      card.style.transform = 'perspective(1000px) rotateX(0) rotateY(0) scale(1)';
      card.style.transition = 'transform 0.4s ease';
    });

    card.addEventListener('mouseenter', () => {
      card.style.transition = 'transform 0.1s ease';
    });
  });
});
```

- [ ] **Step 4: Add script tags to index.html and test**

Add all JS scripts to index.html. Verify:
- Desktop: elements fade in on scroll, cards tilt, parallax works, logo marquee runs
- Mobile (emulation): tilt disabled, scroll animations work, no performance issues
- Run Lighthouse audit — target: Performance > 90

- [ ] **Step 5: Commit**

```bash
git add localenrenta/
git commit -m "feat: GSAP scroll animations, parallax, 3D tilt, ripple effects"
```

---

## Task 6: Zone Page Template

**Files:**
- Create: `localenrenta/assets/css/zona.css`
- Create: `localenrenta/zonas/cancun-av-huayacan.html`

- [ ] **Step 1: Create zona.css**

File: `localenrenta/assets/css/zona.css`

All 3 breakpoints. Key classes:

- `.breadcrumb` — flex, arrow separators (CSS ::before), links in blue with underline-on-hover
- `.zona-header` — region label + h1 zone name + description paragraph
- `.zona-data` — flex row of data cards. Each card: light blue background, large number, unit label. Stacks on mobile
- `.zona-types` — flex wrap of pill tags
- `.zona-photos` — CSS grid: 1 large photo + 2 small thumbnails. Single column on mobile. Uses `<picture>` with WebP/JPG
- `.zona-cta` — centered, large WhatsApp button + "mensaje pre-llenado" note below
- `.zona-others` — grid of sibling zone cards (4 columns → 2 → 1). Each card: white, border, zone name, "desde $X/mes"
- `.zona-back` — centered "Ver todas las ciudades →" link

- [ ] **Step 2: Create template zone page**

File: `localenrenta/zonas/cancun-av-huayacan.html`

```html
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Local en Renta en Av. Huayacán, Cancún | LocalenRenta</title>
  <meta name="description" content="Locales comerciales en renta en Av. Huayacán y alrededores, Cancún Sur. Desde 30m² hasta 120m². Renta desde $15,000/mes.">
  <meta property="og:title" content="Local en Renta en Av. Huayacán, Cancún">
  <meta property="og:description" content="Locales comerciales desde $15,000/mes en Av. Huayacán, Cancún">
  <meta property="og:type" content="website">
  <link rel="icon" href="/favicon.ico">
  <meta name="view-transition" content="same-origin">
  <!-- CSS files -->
  <link rel="stylesheet" href="/assets/css/reset.css">
  <link rel="stylesheet" href="/assets/css/variables.css">
  <link rel="stylesheet" href="/assets/css/base.css">
  <link rel="stylesheet" href="/assets/css/nav.css">
  <link rel="stylesheet" href="/assets/css/footer.css">
  <link rel="stylesheet" href="/assets/css/zona.css">
  <link rel="stylesheet" href="/assets/css/animations.css">
  <!-- GSAP -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js" defer></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js" defer></script>
</head>
<body>
  <!-- Nav (same as homepage) -->
  <!-- Breadcrumb -->
  <nav class="breadcrumb container" aria-label="Ruta de navegación">
    <a href="/">Inicio</a>
    <a href="/#ubicaciones">Cancún</a>
    <span aria-current="page">Av. Huayacán y alrededores</span>
  </nav>

  <main id="main-content" class="zona container">
    <!-- Zone header, data cards, types, photos, CTA -->
    <!-- Cross-sell: other Cancún zones -->
    <!-- "Ver todas las ciudades" link -->
  </main>

  <!-- Footer + floating WhatsApp -->

  <!-- Structured data -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "LocalBusiness",
    "name": "Local en Renta - Av. Huayacán, Cancún",
    "description": "Locales comerciales en renta en Av. Huayacán y alrededores, Cancún Sur",
    "address": {
      "@type": "PostalAddress",
      "streetAddress": "Av. Huayacán",
      "addressLocality": "Cancún",
      "addressRegion": "Quintana Roo",
      "addressCountry": "MX"
    }
  }
  </script>

  <script src="/assets/js/main.js" defer></script>
  <script src="/assets/js/animations.js" defer></script>
  <script src="/assets/js/tilt.js" defer></script>
</body>
</html>
```

Fill in the complete HTML body with all zone content sections, placeholder data, and animation classes.

- [ ] **Step 3: Test zone page**

Verify at all 3 breakpoints:
- Breadcrumb navigates correctly (Inicio → homepage, Cancún → homepage #ubicaciones)
- Data cards show m² and rent ranges
- WhatsApp button opens `https://wa.me/NUMBER?text=ENCODED_MESSAGE`
- Cross-sell shows other Cancún zones (Nichupté, Las Torres, etc.)
- "Ver todas las ciudades →" links to homepage
- Animations trigger on scroll
- Structured data valid (test at search.google.com/test/rich-results)

- [ ] **Step 4: Commit**

```bash
git add localenrenta/
git commit -m "feat: zone page template with data cards, photos, CTA, cross-sell, SEO"
```

---

## Task 7: Generate All 17 Zone Pages

**Files:**
- Create: `localenrenta/zonas/*.html` (remaining 16 zone pages)

- [ ] **Step 1: Write a generation script**

Create a temporary Node.js or bash script that reads `data/zonas.json` and the template `zonas/cancun-av-huayacan.html`, then generates all 17 zone pages by replacing placeholder values. This avoids manual copy errors across 17 files.

For each zone, the script replaces:
- Page `<title>` and `<meta name="description">`
- OG tags
- Breadcrumb city name and zone name
- Region label, zone name heading, description
- Data card values (m² min/max, rent min/max)
- Type tags
- Photo file paths
- WhatsApp number and pre-filled message URL
- Cross-sell cards (other zones in same city, with their names and "desde $X/mes")
- Structured data JSON-LD (address, name, description)

- [ ] **Step 2: Run script and generate all pages**

Execute the script. Verify 17 HTML files exist in `/zonas/`.

- [ ] **Step 3: Spot-check 4 zone pages**

Open in browser and verify:
- `cancun-calzada-lakin.html` — breadcrumb shows "Cancún", cross-sell shows other Cancún Norte zones
- `playa-av-constituyentes.html` — breadcrumb shows "Playa del Carmen", cross-sell shows Playa zones
- `merida-san-marcos.html` — cross-sell shows Vega del Mayab
- `cozumel-el-encanto.html` — cross-sell shows no sibling zones (only zone in Cozumel), shows "Ver todas las ciudades" prominently

- [ ] **Step 4: Delete the generation script (temporary)**

Remove the script — it was only needed for initial generation. Future zone additions are done by manual duplication.

- [ ] **Step 5: Commit**

```bash
git add localenrenta/zonas/
git commit -m "feat: all 17 zone pages generated from template with correct data"
```

---

## Task 8: Naves Industriales + Nosotros Pages

**Files:**
- Create: `localenrenta/naves-industriales.html`
- Create: `localenrenta/nosotros.html`
- Create: `localenrenta/assets/js/counter.js`

- [ ] **Step 1: Create naves-industriales.html**

Full page. Naves data is hardcoded in the HTML (not from zonas.json — naves are a separate product). Sections:
1. Nav
2. Hero: background image placeholder, "Naves Industriales en el Sureste de México", subtitle about logistics/warehouse spaces
3. Benefits: 3 cards — large spaces (from 200m²), vehicle access (loading docks), strategic logistics locations
4. Available locations: cards per city where naves are available, with m² and rent ranges. Data comes from client Excel (placeholder for now)
5. CTA: "¿Necesitas una nave industrial?" + WhatsApp button with pre-filled message
6. Footer + floating WhatsApp

Meta tags, OG, structured data for naves page.

- [ ] **Step 2: Create counter.js**

File: `localenrenta/assets/js/counter.js`

```javascript
window.addEventListener('load', () => {
  if (typeof gsap === 'undefined' || typeof ScrollTrigger === 'undefined') return;

  document.querySelectorAll('[data-count-to]').forEach(el => {
    const target = parseInt(el.dataset.countTo, 10);
    const suffix = el.dataset.countSuffix || '';

    ScrollTrigger.create({
      trigger: el,
      start: 'top 85%',
      once: true,
      onEnter: () => {
        gsap.to({ val: 0 }, {
          val: target,
          duration: 2,
          ease: 'power2.out',
          onUpdate: function() {
            el.textContent = Math.round(this.targets()[0].val) + suffix;
          }
        });
      }
    });
  });
});
```

Usage in HTML: `<span data-count-to="150" data-count-suffix="+">0</span>`

- [ ] **Step 3: Create nosotros.html**

Full page. Sections:
1. Nav
2. Hero: "Conoce a Vivo Comercial", subtitle
3. About: 2-3 paragraphs about Vivo Comercial (placeholder text)
4. Stats: animated counters — `<span data-count-to="X">0</span>` for years, rentals, cities, clients. 4-column grid → 2 → 1
5. Social proof: same logo marquee as homepage
6. CTA: "¿Listo para ser parte?" + WhatsApp button
7. Footer + floating WhatsApp

Meta tags, OG tags.

- [ ] **Step 4: Test both pages**

Verify:
- Naves: all sections render, WhatsApp link works, mobile layout correct
- Nosotros: counter animates on scroll, stats show correct target numbers, logo marquee works
- Nav links between all pages work correctly

- [ ] **Step 5: Commit**

```bash
git add localenrenta/
git commit -m "feat: naves industriales and nosotros pages with animated counters"
```

---

## Task 9: 404 Page + SEO Files

**Files:**
- Create: `localenrenta/404.html`
- Create: `localenrenta/robots.txt`
- Create: `localenrenta/sitemap.xml`

- [ ] **Step 1: Create 404.html**

Custom error page:
- Nav
- Centered content with generous whitespace
- "Página no encontrada" heading
- "Lo sentimos, la página que buscas no existe o ha sido movida."
- Two buttons: "Volver al inicio" (link to /) and "Buscar un local" (link to /#locales)
- Footer
- Minimal — no heavy animations needed

- [ ] **Step 2: Create robots.txt**

```
User-agent: *
Allow: /
Sitemap: https://localenrenta.com.mx/sitemap.xml
```

- [ ] **Step 3: Create sitemap.xml**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url><loc>https://localenrenta.com.mx/</loc><priority>1.0</priority><changefreq>weekly</changefreq></url>
  <url><loc>https://localenrenta.com.mx/naves-industriales.html</loc><priority>0.7</priority></url>
  <url><loc>https://localenrenta.com.mx/nosotros.html</loc><priority>0.5</priority></url>
  <!-- All 17 zone pages with priority 0.8 -->
  <url><loc>https://localenrenta.com.mx/zonas/cancun-av-huayacan.html</loc><priority>0.8</priority></url>
  <!-- ... repeat for all 17 zones ... -->
</urlset>
```

- [ ] **Step 4: Commit**

```bash
git add localenrenta/
git commit -m "feat: 404 page, robots.txt, sitemap.xml"
```

---

## Task 10: Final Polish + Testing

**Files:**
- Modify: Various HTML/CSS files

- [ ] **Step 1: Performance audit**

Run Lighthouse in Chrome DevTools on the homepage (mobile mode). Target: Performance > 90.
Check:
- All images below fold have `loading="lazy"`
- Hero images do NOT have `loading="lazy"` (above fold)
- GSAP scripts have `defer`
- Font has `font-display: swap`
- No render-blocking CSS (all CSS is small enough to be fine)
- Total page weight < 2MB
- LCP < 2.5s

- [ ] **Step 2: Cross-page link verification**

Systematically navigate:
- Homepage → each nav link (Locales anchor, Naves page, Ubicaciones anchor, Nosotros page, WhatsApp)
- Homepage → each city card → verify it scrolls to selector and pre-selects city
- Homepage → selector → search → zone page
- Zone page → breadcrumb links → back to homepage
- Zone page → cross-sell → other zone pages
- Zone page → "Ver todas las ciudades" → homepage
- Naves page → WhatsApp
- Nosotros page → WhatsApp
- 404 page → both buttons

- [ ] **Step 3: Mobile testing**

Test in Chrome DevTools mobile emulation:
- iPhone SE (375px): everything fits, no horizontal scroll, hamburger works
- iPhone 14 (390px): same checks
- iPad (768px): tablet layout triggers (2-column grids, selector stays horizontal)
- iPad landscape (1024px): transitions to desktop layout

Verify:
- Tilt effect is disabled on all touch emulations
- Scroll animations still fire
- WhatsApp floating button is visible and tappable
- All text is readable without zooming

- [ ] **Step 4: Accessibility check**

- Tab through entire homepage with keyboard: all interactive elements are reachable
- Skip link works (Tab on page load → skip to main content)
- Hamburger announces state (`aria-expanded`)
- Dropdowns announce state
- Color contrast: verify primary text on white meets WCAG AA (4.5:1)
- All images have alt text (or are decorative with `alt=""`)

- [ ] **Step 5: Final commit**

```bash
git add localenrenta/
git commit -m "feat: performance optimization, accessibility fixes, cross-browser polish"
```

---

## Task 11: Package for Delivery

- [ ] **Step 1: Create DEPLOY.md**

File: `localenrenta/DEPLOY.md` with:
- FTP upload instructions: upload all files inside `localenrenta/` to the web root of localenrenta.com.mx
- File structure overview
- How to update zone data: edit the specific zone HTML file, update the corresponding entry in `data/zonas.json`, re-upload both files
- How to add a new zone: duplicate any zone HTML file, update all data fields, add entry to `zonas.json`, add URL to `sitemap.xml`, re-upload
- How to update WhatsApp numbers: search for `wa.me/` in the relevant files
- Technical contact: PUNCH! Marketing

- [ ] **Step 2: Verify complete file list**

```bash
find localenrenta/ -type f | sort | wc -l
# Expected: ~40 files (index + 17 zones + 2 pages + 404 + robots + sitemap + favicon + 9 CSS + 5 JS + 1 JSON + 1 font + DEPLOY.md)
```

Verify no missing files.

- [ ] **Step 3: Final commit**

```bash
git add localenrenta/
git commit -m "docs: deployment instructions and final delivery package"
```

- [ ] **Step 4: Delivery checklist**

The site is ready for deployment with placeholder data. **Pending from the client:**

1. ✅ Excel template delivered: `LocalenRenta_Datos_Requeridos.xlsx`
2. ⬜ Client fills out Excel with real data (WhatsApp numbers, m² ranges, rent ranges, descriptions)
3. ⬜ Client provides photos (cities, zones, naves, client logos, Vivo Comercial logo)
4. ⬜ PUNCH updates zone pages and zonas.json with real data
5. ⬜ PUNCH optimizes photos (WebP + JPG) and places in /assets/img/
6. ⬜ Final upload via FTP to localenrenta.com.mx
