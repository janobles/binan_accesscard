# Product Requirements Document (PRD): UI Modernization

## 1. Goal Description

The primary goal is to stylize the Biñan Access Card application to feel significantly more modern, premium, and dynamic, breaking away from the flat, out-of-the-box appearance of Bootstrap 5 and the SB Admin theme. This involves a UI/UX modernization pass over the current application, focusing on layout enhancements, typography improvements, micro-animations, and a unified design system.

## 2. Current Architecture & CSS Audit

A review of the `public/css/` directory reveals the following architecture:
- **Structure**: The CSS is split into global theme files (`theme.css`) and component/page-specific files (`login.css`, `familymodal.css`, `accounts.css`, etc.). 
- **Standardization**: The file structure is standard for a buildless PHP application. The `theme.css` smartly layers customizations over SB Admin via Bootstrap's CSS Custom Properties (`var(--bs-primary)`).
- **PHP Components**: The reusable UI fragments in `app/Views/components/` (e.g., `modal.php`, `card.php`) follow a highly standard "props-only" architecture. Modal contents are injected via `$bodyView`, mirroring modern component-driven development and adhering strictly to the `views-bootstrap.md` conventions.

## 3. Technology Strategy: CSS Variables over Sass

While compiling Bootstrap from source via **Sass (SCSS)** is traditionally the most powerful way to customize the framework, it introduces a build step (Node.js/npm) which is undesirable in this straightforward CodeIgniter 4 repository.

Given that the project uses **Bootstrap v5.3.3**, we will leverage **Native CSS Custom Properties (Variables)**.
- Bootstrap 5 exposes a massive number of its internal values as CSS variables (e.g., `--bs-primary`, `--bs-border-radius`).
- We will deeply customize the application by overriding these `:root` variables and utilizing modern native CSS features (like native CSS nesting and `color-scheme`). This achieves maximum modernization without the overhead of a build pipeline.

## 4. Modernization Features

To elevate the UI beyond standard Bootstrap, the following aesthetic upgrades will be implemented:

1. **Premium Color Palette & Soft Gradients**:
   - Replace flat colors with deeper, richer tones (updating `theme.css`).
   - Introduce subtle background gradients for surfaces and cards to create depth.
2. **Glassmorphism & Depth**:
   - Utilize modern `backdrop-filter: blur()` properties for modals, dropdowns, and sticky headers.
   - Upgrade standard Bootstrap box-shadows to multi-layered, softer shadows that provide a modern "floating" feel.
3. **Typography Upgrade**:
   - Adjust letter-spacing and font weights to improve legibility and hierarchy.
4. **Micro-Animations (Motion)**:
   - Add subtle transitions for hover states, button presses, and dropdown reveals.
   - Introduce CSS View Transitions or scroll-driven animations where appropriate to make the interface feel "alive".
5. **Component Refinements**:
   - **Cards**: Remove harsh borders, increase border-radius, and use soft shadows.
   - **Inputs/Forms**: Implement floating labels, focus-within states with dynamic outlines, and softer backgrounds.
   - **Navigation**: Clean up the sidebar/topnav to feel less rigid and more integrated with the content.

## 5. Proposed Implementation Phases

### Phase 1: CSS Architecture Consolidation
- Reorganize and clean up `public/css/` to establish a clearer hierarchy.
- Create a `design-tokens.css` to hold all core modern variables, colors, and shadows.
- Create an `animations.css` for reusable micro-animations.
- Evolve `theme.css` into the primary entry point for global overrides, pulling in the modern variables and base styling.

### Phase 2: Component & Layout Upgrades
- Apply glassmorphism and modern gradient backgrounds to the login card (`login.css`).
- Update the dashboard shell (sidebar and topnav).
- Refine the PHP components in `app/Views/components/` to ensure they consume the new CSS variables gracefully.

## 6. Verification Plan

- Manually verify the Login page and Dashboard shell locally.
- Ensure all interactive elements (buttons, inputs) trigger the new micro-animations.
- Verify that standard Bootstrap components inherit the new modern design tokens without breaking responsive layouts.
