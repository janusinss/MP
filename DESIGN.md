# FreshCart Design System Specification

## Target Redesign Specification (Refined Organic Baseline)

### 1. Design Philosophy & Tone
- **Archetype**: Clean Editorial & Organic Commerce Platform.
- **Tone**: Grounded, authentic, artisan, and warm. Evoking natural whole foods, sustainable agriculture, and effortless modern digital shopping without synthetic/AI visual slop.

### 2. Taste Dials
- **`DESIGN_VARIANCE`**: `7` (Editorial rhythm, asymmetric bento discovery cards, dynamic badge offsets).
- **`MOTION_INTENSITY`**: `4` (Subtle, purposeful deceleration using `cubic-bezier(0.16, 1, 0.3, 1)`; zero bounce easing or layout shifts).
- **`VISUAL_DENSITY`**: `6` (Balanced grocery catalog density with disciplined whitespace and compact hero insets).

### 3. Typography Scale
- **Display & Section Headers**: `'Playfair Display', Georgia, serif` (Weights: 600, 700; tracking: -0.025em; line-height: 1.15).
- **Interface & Body Text**: `'Plus Jakarta Sans', 'Inter', -apple-system, sans-serif` (Weights: 400, 500, 600, 700; line-height: 1.55).
- **Numeric & Pricing**: `'Plus Jakarta Sans', tabular-nums, sans-serif` (Weights: 700; tracking: -0.01em).
- **Monospace & Metadata**: `'JetBrains Mono', 'Fira Code', monospace` (Weights: 500).

### 4. Color Tokens (CSS Custom Properties)
```css
:root {
  /* Surfaces & Canvas */
  --color-canvas: #F7F6F2;
  --color-canvas-rgb: 247, 246, 242;
  --color-surface: #FFFFFF;
  --color-surface-subtle: #F0EEE6;
  --color-surface-overlay: rgba(255, 255, 255, 0.88);
  --color-border: #E5E0D5;
  --color-border-subtle: #EDEAE1;
  --color-border-focus: #4A745B;

  /* Typography */
  --color-text-primary: #232620;
  --color-text-secondary: #6E7368;
  --color-text-tertiary: #989E92;
  --color-text-inverse: #FFFFFF;

  /* Brand Palette (Fresh Sage & Forest) */
  --color-brand: #4A745B;
  --color-brand-dark: #375743;
  --color-brand-light: #5E9173;
  --color-brand-tint: rgba(74, 116, 91, 0.08);
  --color-brand-glow: rgba(74, 116, 91, 0.22);

  /* Accents & Status */
  --color-accent-amber: #C98B32;
  --color-accent-amber-subtle: rgba(201, 139, 50, 0.12);
  --color-danger: #C0392B;
  --color-danger-subtle: rgba(192, 57, 43, 0.10);
  --color-success: #27AE60;
  --color-success-subtle: rgba(39, 174, 96, 0.10);

  /* Elevation Shadows */
  --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.04), 0 1px 3px rgba(35, 38, 32, 0.05);
  --shadow-md: 0 4px 12px rgba(35, 38, 32, 0.06), 0 2px 4px rgba(35, 38, 32, 0.04);
  --shadow-lg: 0 12px 32px rgba(35, 38, 32, 0.08), 0 4px 8px rgba(35, 38, 32, 0.04);
  --shadow-brand: 0 4px 20px rgba(74, 116, 91, 0.25);

  /* Radii */
  --radius-sm: 6px;
  --radius-md: 12px;
  --radius-lg: 20px;
  --radius-full: 9999px;

  /* Transitions */
  --ease-smooth: cubic-bezier(0.16, 1, 0.3, 1);
  --duration-fast: 150ms;
  --duration-base: 220ms;
}
```

### 5. Layout & Component Guidelines
- **Hero Clearance**: Top padding strictly `calc(var(--header-height, 70px) + 24px)` to banish dead vertical whitespace.
- **Card Hierarchy**: Flat borders (`1px solid var(--color-border)`) with subtle hover lift (`transform: translateY(-3px)`) and smooth shadow bloom.
- **Buttons**: Minimum interactive height 44px (`padding: 10px 24px`, `--radius-full`).
- **Photography Standards**: Real, authentic produce photography. Zero AI generation. Semi-transparent scrim gradient (`linear-gradient(to top, rgba(0,0,0,0.65), transparent)`) behind overlays.
- **Slop Zero-Tolerance**:
  - Removed all `[side-tab]` borders.
  - Removed all `[bounce-easing]` transitions in favor of `--ease-smooth`.
  - Removed layout transitions on `width`/`height` in favor of `transform` and `opacity`.

---

## Appendix: Current State (Extracted Baseline)

### 1. Legacy Typography
- **Headings**: `'Playfair Display', 'Merriweather', serif` (700 weight)
- **Body**: `'Inter', 'Helvetica Neue', sans-serif` (400, 500, 600)
- **Monospace**: `'Fira Code', monospace`

### 2. Legacy Color Palette
- `--bg-color`: `#F5F5F0`
- `--card-bg`: `rgba(255, 255, 255, 0.85)`
- `--text-main`: `#3b3b3b`
- `--text-muted`: `#8a7a6b`
- `--accent-color`: `#5D866C`
- `--accent-dark`: `#4a6b56`
- `--accent-glow`: `rgba(93, 134, 108, 0.4)`
- `--border-color`: `#d8d0c5`
