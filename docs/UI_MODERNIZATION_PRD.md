# Product Requirements Document (PRD): Deterministic UI Modernization

## 1. Goal Description

The primary goal is to stylize the Biñan Access Card application to achieve a clean, minimal, and low-cognitive-load aesthetic inspired by modern regional utility platforms like Grab, PayMaya, eGovPH, and PSA Helpline, while integrating the official visual identity of the City Government of Biñan.

The design explicitly rejects trends like glassmorphism or heavy shadows in favor of a highly deterministic, strictly tokenized design system that relies effectively on **White, Green, Gray**, and a **Yellow Accent**. 

A core objective is to enforce **deterministic component rules** so that future developers and AI agents produce 100% consistent interfaces, eliminating the ad-hoc inconsistencies (e.g., varying button styles, stray icons) seen in previous iterations.

## 2. Target Audience: LGU Users & Information Density

When adopting a compact, high-data-density design (similar to advanced map-based routing tools), we must balance the UI for LGU (Local Government Unit) employees. LGU users often span a wide demographic, including older users who may struggle with very small text or cramped touch targets.
- **Data-Heavy Views (Tables/Dashboards)**: A compact layout (reduced padding, 13px-14px font size) is highly encouraged for data grids and map overlays to minimize scrolling and maximize data visibility for power users.
- **Accessibility Floor**: Even in compact views, primary interactive elements (Buttons, Inputs) must maintain strict accessible touch targets (minimum 44px height) to prevent misclicks and user fatigue. 

## 3. Tokenized Design System

The application will use native CSS Custom Properties (Variables) to strictly define the allowed design tokens. Ad-hoc hex codes and inline styles are strictly prohibited.

### Color Tokens
- **Primary Green**: The singular brand color used for primary actions, active states, and emphasis.
- **Yellow Accent**: Derived from the official Biñan branding. To maintain a minimal cognitive load, yellow is used sparingly for highlights, warnings, or specific display branding. 
  - *Gradient Rule*: A Green-to-Yellow gradient is permissible **only** for large display typography or specific hero banners. It is strictly forbidden on UI components (buttons, inputs, cards), which must remain solid, flat colors.
- **Grays**: A strict scale of grays (e.g., Gray-50 to Gray-900) used for typography, borders, and secondary surface backgrounds to create subtle hierarchy.
- **White**: The primary surface color for cards, modals, and the main content area to maximize whitespace and readability.

### Spacing & Sizing Tokens
- Strict spacing multipliers (e.g., `--space-sm`, `--space-md`, `--space-lg`) must be used for all margins and paddings.
- Standardized border radii for all containers.

## 4. Deterministic Component Rules (Non-Negotiables)

To ensure absolute consistency, components must adhere to rigid, lintable constraints.

### Layout & Grids
- **Bootstrap Responsive Grid**: The UI must strictly adhere to Bootstrap 5's responsive grid system (`container`, `row`, `col-*`, `gap-*`). 
- **Enforcement**: Hand-rolled CSS grid or flex layouts for standard page structuring are discouraged if a Bootstrap grid utility can achieve the same result. Components must be predictably responsive across standard Bootstrap breakpoints.

### Buttons
- **Style**: Color-coded flat surfaces (solid fills or outlines). No gradients, no glassmorphism.
- **Icons**: NO icons inside buttons. Text only.
- **Alignment**: Text must be perfectly centered.
- **Sizing**: Must enforce strict minimum dimensions (e.g., `min-height: 44px; min-width: 120px;`) for proper touch targets and visual weight.
- **Padding**: Enforced, unalterable internal padding (e.g., `padding: 0.75rem 1.5rem;`).
- **Corners**: Consistent border-radius applied globally to all buttons.

### Cards, Modals & Panels
- **Surface**: Solid white backgrounds only.
- **Elevation**: Use crisp 1px light gray borders (`var(--token-border-light)`) to separate content. Drop shadows should be completely eliminated or restricted to an ultra-flat, subtle state for floating elements (like dropdowns).
- **Padding**: Generous, uniform internal whitespace to lower cognitive load. (Note: Internal padding can be tightened explicitly for data-dense power-user cards).

### Inputs & Forms
- **Background**: Solid white or ultra-light gray.
- **Borders**: 1px solid gray, snapping to Primary Green on focus.
- **Structure**: Clear separation between label and input.

## 5. Implementation Strategy

### Phase 1: Establish Tokens
- Create `public/css/design-tokens.css` that maps all allowed colors, spacings, and sizes to CSS variables (`:root`).
- Override Bootstrap 5's default variables (like `--bs-primary`, `--bs-border-color`) using ONLY our defined design tokens.

### Phase 2: Enforce Component Rules
- Refactor `public/css/theme.css` to strip out any legacy complex styling.
- Update `app/Views/components/` (like `modal.php` and `card.php`) to strictly consume the new gray/white flat token system and grid layouts.

### Phase 3: Agentic/Linting Enforcement
- Document these exact deterministic rules in `docs/knowledge/binan-conventions/ui-design-system.md`.
- Ensure any future CSS linting prevents the usage of raw color values or invalid paddings in component classes.

## 6. Verification Plan
- Code review to ensure zero usage of `backdrop-filter` or complex `box-shadow` properties.
- Visual audit against the reference platforms (Grab, PayMaya) to ensure the interface reads as a flat, high-utility service.
- DOM inspection to ensure every button strictly follows the "no icon, min-size, centered text, enforced padding" rule.
- Layout audit to verify strict adherence to Bootstrap grid classes.
