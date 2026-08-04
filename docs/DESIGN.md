---
name: Elevated Grooming & Wellness
colors:
  surface: '#0f1416'
  surface-dim: '#0f1416'
  surface-bright: '#353a3c'
  surface-container-lowest: '#0a0f11'
  surface-container-low: '#171c1e'
  surface-container: '#1b2022'
  surface-container-high: '#262b2d'
  surface-container-highest: '#303638'
  on-surface: '#dfe3e6'
  on-surface-variant: '#d1c5b4'
  inverse-surface: '#dfe3e6'
  inverse-on-surface: '#2c3133'
  outline: '#9a8f80'
  outline-variant: '#4e4639'
  surface-tint: '#e9c176'
  primary: '#e9c176'
  on-primary: '#412d00'
  primary-container: '#c5a059'
  on-primary-container: '#4e3700'
  inverse-primary: '#775a19'
  secondary: '#c1c8ca'
  on-secondary: '#2b3234'
  secondary-container: '#434a4c'
  on-secondary-container: '#b2babc'
  tertiary: '#bec8d1'
  on-tertiary: '#283139'
  tertiary-container: '#9da6af'
  on-tertiary-container: '#333c43'
  error: '#ffb4ab'
  on-error: '#690005'
  error-container: '#93000a'
  on-error-container: '#ffdad6'
  primary-fixed: '#ffdea5'
  primary-fixed-dim: '#e9c176'
  on-primary-fixed: '#261900'
  on-primary-fixed-variant: '#5d4201'
  secondary-fixed: '#dde4e6'
  secondary-fixed-dim: '#c1c8ca'
  on-secondary-fixed: '#161d1f'
  on-secondary-fixed-variant: '#41484a'
  tertiary-fixed: '#dae4ed'
  tertiary-fixed-dim: '#bec8d1'
  on-tertiary-fixed: '#141d23'
  on-tertiary-fixed-variant: '#3f4850'
  background: '#0f1416'
  on-background: '#dfe3e6'
  surface-variant: '#303638'
typography:
  display-lg:
    fontFamily: Inter
    fontSize: 32px
    fontWeight: '700'
    lineHeight: 40px
    letterSpacing: -0.02em
  headline-md:
    fontFamily: Inter
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 32px
    letterSpacing: -0.01em
  headline-sm:
    fontFamily: Inter
    fontSize: 20px
    fontWeight: '600'
    lineHeight: 28px
  body-lg:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  body-md:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '400'
    lineHeight: 20px
  label-caps:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '600'
    lineHeight: 16px
    letterSpacing: 0.05em
  data-mono:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '500'
    lineHeight: 20px
rounded:
  sm: 0.125rem
  DEFAULT: 0.25rem
  md: 0.375rem
  lg: 0.5rem
  xl: 0.75rem
  full: 9999px
spacing:
  base: 4px
  xs: 8px
  sm: 12px
  md: 16px
  lg: 24px
  xl: 32px
  container-margin: 16px
  gutter: 12px
---

## Brand & Style
The design system is built for a premium service environment—think high-end barbershops and boutique aesthetic clinics. The brand personality is **authoritative, sophisticated, and meticulous**. It aims to evoke a sense of calm professionalism and "quiet luxury" for administrators and service providers.

The design style is **Corporate Modern with Tactile Accents**. It leverages a structured, systematic layout (Modern) but introduces warmth through specific color accents and subtle depth (Tactile). The interface focuses on high-density information management without feeling cluttered, using generous negative space and a rigorous alignment to a geometric grid.

## Colors
The palette is rooted in a **Sophisticated Dark** theme. The foundation is "Midnight Slate" (`#0F1416`), providing a deep, low-fatigue background for long-duration administrative work. 

- **Primary (Bronze/Gold):** Used exclusively for high-priority actions, active states, and branding moments. It represents the "premium" nature of the service.
- **Secondary/Surface:** Deep grays used to define containers and sidebars, creating a clear hierarchy of depth.
- **Accents:** Muted bronze is used for decorative elements or secondary highlights to prevent the gold from becoming overwhelming.
- **Functional Colors:** Success and error states are desaturated to maintain the professional aesthetic while remaining legible.

## Typography
This design system utilizes **Inter** for its exceptional legibility in data-heavy environments. The typographic scale is optimized for high information density.

- **Headlines:** Use tighter letter spacing and heavier weights to command attention on dashboards.
- **Body:** Standardized at 14px for most administrative tasks to maximize content visibility on mobile screens.
- **Labels:** Small caps with increased tracking are used for table headers and section titles to create clear visual separation.
- **Numerical Data:** Tabular num settings are enabled by default for charts and data tables to ensure columns of numbers align perfectly for easy scanning.

## Layout & Spacing
The design system follows a **Mobile-First Fluid Grid** philosophy. On mobile devices, the layout relies on a single-column stack with 16px side margins. 

- **Sidebar:** On mobile, the sidebar is a drawer (off-canvas) triggered by a top-left hamburger menu. On larger screens, it becomes a fixed 240px vertical column.
- **Rhythm:** An 8px linear scale is used for all spatial relationships. 4px increments are reserved for micro-spacing within components (e.g., icon-to-text).
- **Data Tables:** Tables utilize horizontal scrolling on mobile, with the first column (e.g., Client Name) fixed to ensure context is never lost.

## Elevation & Depth
Depth is communicated through **Tonal Layering** rather than traditional shadows, maintaining a clean and professional look.

- **Level 0 (Base):** Deepest background (`#0F1416`).
- **Level 1 (Cards/Sidebar):** Elevated surface (`#1E272E`).
- **Level 2 (Modals/Overlays):** Brightest surface (`#2D3436`) with a subtle 1px border in a slightly lighter gray (`#3D4446`) to define edges.
- **Interactions:** Hover or active states use a low-opacity primary color (Gold) overlay to indicate selection without disrupting the dark aesthetic.

## Shapes
The shape language is **Soft and Precise**. 

- **Components:** Standard buttons, input fields, and cards use a 4px (0.25rem) corner radius. This conveys a sense of modern precision suitable for a medical or professional grooming context.
- **Indicators:** Status tags and small badges use a slightly higher radius (8px) to distinguish them from actionable buttons.
- **Charts:** Line charts use smoothed Bezier curves to contrast with the rigid geometric layout of the dashboard.

## Components
- **Buttons:** Primary buttons are solid Gold (`#C5A059`) with dark text. Secondary buttons use a ghost style with the 1px Slate border.
- **Sidebar Navigation:** Icons are outlined and 20px in size. The active state features a vertical 3px Gold bar on the left edge of the menu item.
- **Data Tables:** Rows use subtle zebra-striping or a 1px bottom border. Headers are styled using the `label-caps` typography token.
- **Metric Cards:** Large numeric values are paired with a small trend indicator (e.g., +12% in `success_green`).
- **Input Fields:** Dark backgrounds with a subtle border. On focus, the border transitions to Gold with a 2px outer glow of the same color at 20% opacity.
- **Interactive Charts:** Tooltips should use the Level 2 elevation background with primary gold text for data points.
