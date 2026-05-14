---
name: POLICE ALL STAR PD
description: High-tech Dispatch Terminal for roleplay communities.
colors:
  primary: "#3b82f6" # Approximation of oklch(60% 0.18 250)
  accent: "#f97316" # Approximation of oklch(70% 0.15 45)
  neutral-bg: "#0f172a" # Approximation of oklch(15% 0.02 260)
  neutral-surface: "#1e293b" # Approximation of oklch(20% 0.02 260)
  text: "#f8fafc"
  text-muted: "#94a3b8"
  success: "#22c55e"
  danger: "#ef4444"
typography:
  display:
    fontFamily: "Rajdhani, sans-serif"
    fontWeight: 700
    letterSpacing: "0.05em"
    textTransform: "uppercase"
  body:
    fontFamily: "Noto Sans Thai, sans-serif"
    fontSize: "14px"
    lineHeight: "1.6"
rounded:
  md: "18px"
spacing:
  container: "1280px"
  gutter: "24px"
components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "#ffffff"
    rounded: "{rounded.md}"
    padding: "10px 24px"
  card:
    backgroundColor: "{colors.neutral-surface}"
    rounded: "{rounded.md}"
    padding: "32px"
---

# Design System: POLICE ALL STAR PD

## 1. Overview

**Creative North Star: "The Tactical Terminal HUD"**

The visual system is designed to evoke the high-stakes, high-precision environment of a modern police command center. It prioritizes data density and immediate status recognition through a dark, high-contrast "Terminal" aesthetic. The interface rejects generic SaaS "softness" in favor of sharp, tactical precision, utilizing semi-transparent surfaces and purposeful glows to create a sense of depth and dimension.

**Key Characteristics:**
- High-contrast "Tactical Dark" theme.
- Technical, uppercase typography for headers.
- Semi-transparent "Glass" panels with high-end blur.
- Purposeful status indicators (Pulse dots, status badges).

## 2. Colors

The palette is anchored in deep oceanic neutrals and a vibrant "Police Blue" primary, used sparingly for critical UI elements and focus.

### Primary
- **Police Blue** (oklch(60% 0.18 250)): Used for primary actions, navigational active states, and focus rings. It represents authority and system readiness.

### Accent
- **Alert Orange** (oklch(70% 0.15 45)): Reserved for high-priority alerts, emergency calls, and warnings. Its rarity is the point.

### Neutral
- **Deep Midnight** (oklch(15% 0.02 260)): The base canvas color. Provides a stable, low-glare background for long-duration use.
- **Terminal Surface** (oklch(20% 0.02 260)): Background for cards and secondary panels, often used with a backdrop blur.

### Named Rules
**The 10% Protocol.** The Primary "Police Blue" should never cover more than 10% of any given screen. Its impact comes from its contrast against the Deep Midnight canvas.

## 3. Typography

**Display Font:** Rajdhani (700)
**Body Font:** Noto Sans Thai (400-800)

**Character:** Technical and authoritative. Rajdhani provides a futuristic, machined feel for headers, while Noto Sans Thai ensures maximum legibility for incident logs and officer names.

### Hierarchy
- **Display** (700, 2rem+, 1.2): Used for page titles and large terminal headings.
- **Headline** (600, 1.5rem, 1.2): Used for card titles and section breaks.
- **Body** (400, 14px, 1.6): Standard prose, incident descriptions, and logs. Max line length 75ch.
- **Label** (700, 11px, 0.05em, uppercase): Used for metadata, system status, and small badges.

## 4. Elevation

The system uses a "HUD Layering" approach. Instead of traditional physical shadows, depth is conveyed through translucent surfaces and subtle glowing "rim" lights.

### Shadow Vocabulary
- **HUD Glow** (`0 12px 36px oklch(0% 0 0 / 0.4)`): Used on primary cards to separate them from the base terminal background.
- **Active Focus** (`0 0 15px var(--primary-dim)`): A subtle outer glow used to highlight active or focused elements.

### Named Rules
**The Glass Rule.** Surfaces that sit "above" the base canvas must use a semi-transparent background (alpha 0.85) and a backdrop-filter (blur 12px) to maintain the HUD aesthetic.

## 5. Components

### Buttons
- **Shape:** Softened tactical corners (18px radius).
- **Primary:** High-visibility blue with bold uppercase text.
- **Hover:** Brightness shift and slight lift via shadow.

### Cards
- **Style:** Deep surfaces with 18px rounding.
- **Border:** Subtle 1px translucent border (`oklch(100% 0 0 / 0.06)`).
- **Dimension:** Uses `var(--shadow-md)` for a "floating" HUD effect.

### Status Badges
- **Style:** Compact pills with high-contrast backgrounds (Success Green or Danger Red).
- **Motion:** Active badges (On-Duty) use a "Pulse" animation to draw attention.

## 6. Do's and Don'ts

### Do:
- **Do** use uppercase Rajdhani for all functional headers.
- **Do** utilize `backdrop-filter: blur` on all floating panels.
- **Do** tint neutrals toward the primary blue (chroma 0.02).

### Don't:
- **Don't** use soft, "squishy" shadows or pastel colors.
- **Don't** use em-dashes; use tactical separators (:: or |).
- **Don't** use border-left "side-stripes" as accents; use full borders or background tints.
