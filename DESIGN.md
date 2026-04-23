---
name: Tactical Elite
colors:
  surface: '#101415'
  surface-dim: '#101415'
  surface-bright: '#363a3b'
  surface-container-lowest: '#0b0f10'
  surface-container-low: '#191c1e'
  surface-container: '#1d2022'
  surface-container-high: '#272a2c'
  surface-container-highest: '#323537'
  on-surface: '#e0e3e5'
  on-surface-variant: '#c3c8c2'
  inverse-surface: '#e0e3e5'
  inverse-on-surface: '#2d3133'
  outline: '#8d928d'
  outline-variant: '#424844'
  surface-tint: '#b6ccbd'
  primary: '#b6ccbd'
  on-primary: '#22342a'
  primary-container: '#0d1f16'
  on-primary-container: '#74887c'
  inverse-primary: '#4f6357'
  secondary: '#ffffff'
  on-secondary: '#293500'
  secondary-container: '#c7f300'
  on-secondary-container: '#576c00'
  tertiary: '#c5c7c8'
  on-tertiary: '#2e3132'
  tertiary-container: '#191c1d'
  on-tertiary-container: '#828485'
  error: '#ffb4ab'
  on-error: '#690005'
  error-container: '#93000a'
  on-error-container: '#ffdad6'
  primary-fixed: '#d2e8d9'
  primary-fixed-dim: '#b6ccbd'
  on-primary-fixed: '#0d1f16'
  on-primary-fixed-variant: '#384b40'
  secondary-fixed: '#c7f300'
  secondary-fixed-dim: '#aed500'
  on-secondary-fixed: '#171e00'
  on-secondary-fixed-variant: '#3d4d00'
  tertiary-fixed: '#e1e3e4'
  tertiary-fixed-dim: '#c5c7c8'
  on-tertiary-fixed: '#191c1d'
  on-tertiary-fixed-variant: '#444748'
  background: '#101415'
  on-background: '#e0e3e5'
  surface-variant: '#323537'
typography:
  headline-xl:
    fontFamily: Lexend
    fontSize: 48px
    fontWeight: '800'
    lineHeight: '1.1'
    letterSpacing: -0.02em
  headline-lg:
    fontFamily: Lexend
    fontSize: 32px
    fontWeight: '700'
    lineHeight: '1.2'
    letterSpacing: -0.01em
  headline-md:
    fontFamily: Lexend
    fontSize: 24px
    fontWeight: '600'
    lineHeight: '1.3'
  body-lg:
    fontFamily: Inter
    fontSize: 18px
    fontWeight: '400'
    lineHeight: '1.6'
  body-md:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '400'
    lineHeight: '1.5'
  label-bold:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '600'
    lineHeight: '1.2'
    letterSpacing: 0.05em
  label-sm:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '500'
    lineHeight: '1.2'
rounded:
  sm: 0.125rem
  DEFAULT: 0.25rem
  md: 0.375rem
  lg: 0.5rem
  xl: 0.75rem
  full: 9999px
spacing:
  base: 8px
  xs: 4px
  sm: 12px
  md: 24px
  lg: 40px
  xl: 64px
  gutter: 24px
  margin: 32px
---

## Brand & Style

The design system is engineered for high-performance sports management, evoking the intensity of the locker room and the precision of the pitch. It targets professional scouts, coaches, and club directors who require immediate access to complex data without sacrificing visual impact. 

The aesthetic is a fusion of **Glassmorphism** and **Minimalism**. By utilizing translucent layers and sharp, intentional accents, the UI feels like a high-tech tactical board. Every interaction must feel "tactical"—meaning responses are snappy, borders are crisp, and the hierarchy is absolute. The goal is to create an atmosphere of elite athletic excellence where data is the star.

## Colors

This design system is natively dark. The palette is anchored by **Deep Pitch Green**, a desaturated, professional base that provides more depth than a standard black. The primary accent is **Energetic Lime**, a high-visibility hue used exclusively for calls to action, active states, and critical data points.

**Dark Charcoal** serves as the secondary background for container surfaces, providing a distinct contrast from the base "Pitch" color. Neutral tones are kept cool and crisp to ensure legibility against the dark backgrounds. Gradients should be used sparingly, primarily as subtle top-to-bottom fades on glass cards to simulate stadium lighting.

## Typography

The typography strategy leverages the athletic DNA of **Lexend** for all headings. Its geometric construction mirrors the markings on a pitch, providing a bold, motivating presence. Headlines should favor heavy weights (Bold/ExtraBold) to maintain a commanding hierarchy.

For administrative and data-heavy tasks, **Inter** provides the necessary utilitarian clarity. Body text should maintain generous line heights to ensure readability during fast-paced operations. Labels and metadata utilize a slight tracking increase (letter-spacing) and uppercase styling to mimic tactical annotations found in sports broadcasting.

## Layout & Spacing

The design system utilizes a **12-column fixed grid** for desktop dashboard views to ensure consistency across complex data visualizations. A strict 8px spatial rhythm governs all padding and margins, reinforcing the "precise" brand attribute.

Layouts are primarily **card-based**, where related data points (e.g., player stats, upcoming fixtures) are grouped into distinct containers. Sections should be separated by generous whitespace (40px+) to prevent the dark interface from feeling cramped or overwhelming.

## Elevation & Depth

Depth in this design system is achieved through **Glassmorphism** and tonal layering rather than traditional heavy shadows.

1.  **Base Layer:** Deep Pitch Green (Surface).
2.  **Mid Layer (Cards):** Dark Charcoal with a 60% opacity and a 12px backdrop-blur. 
3.  **Top Layer (Modals/Popovers):** Dark Charcoal with 80% opacity and a subtle 1px border using a low-opacity Lime or White.

Shadows should be "Ambient"—extremely soft, using the primary green or black color at 40% opacity with a large blur radius (20px+) to make elements appear as if they are floating over a turf surface.

## Shapes

The shape language is "Soft" yet disciplined. While the base roundedness is 0.25rem (4px) for interactive elements like buttons and inputs, larger containers like dashboard cards should use `rounded-lg` (8px) to soften the overall interface.

This balance ensures the UI feels modern and approachable while maintaining the sharp "tactical" edges associated with professional sports equipment and data tablets. Interactive elements should never be fully circular (pill-shaped) unless they are status indicators or small badges.

## Components

**Buttons:** 
The primary button uses the Energetic Lime background with black text for maximum contrast. The hover state should include a subtle lime outer glow. Secondary buttons use a ghost style with a 1px lime border.

**Cards:** 
All cards must feature a subtle 1px top-border (inner stroke) at 10% white opacity to catch the "light" and define the glass effect. Padding inside cards is strictly 24px.

**Input Fields:** 
Inputs feature a Dark Charcoal fill and a 1px Slate border. On focus, the border transitions to Energetic Lime with a 2px "tactical glow."

**Chips & Badges:** 
Used for player positions (e.g., "ST", "CM") and status. These use Lexend Bold at small sizes and high-contrast background fills.

**Specialty Components:**
- **Tactical Pitch Map:** A scaled-down football field container for player positioning.
- **Stat Radar:** A custom visualization tool for player attributes using lime-colored strokes.
- **Match Timeline:** A vertical or horizontal track with glassmorphic markers for goals and substitutions.