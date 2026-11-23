# Project: CV Maker (CodeIgniter 4)

Stack: CodeIgniter 4 (PHP 8.2+), Tailwind CSS (compiled via Vite, no CDN), Alpine.js, HTMX, SSE for Ollama streaming. UI copy is natural Bahasa Indonesia baku, stored in app/Language/id/. Never hardcode user-facing strings in views.

## Design concept: "The Private Atelier"
The UI feels like a quiet stationery studio or a private ledger. Warm paper surfaces, ink-black type, hairline rules instead of boxes, one restrained accent used like a wax seal. Default theme is LIGHT (paper #F4F0E8, ink #1A1A1A, stone #8A8578, hairline rgba(26,26,26,.12)). Accent: antique brass #A98B4F, used ONLY for the single primary action per screen, focus rings, and the active step. Dark theme (obsidian #0E0E10) is secondary and must be fully supported via CSS variables, no flash on load.
Typography: Newsreader or Cormorant Garamond for display, Inter for UI. Nothing else. Tabular numerals for counters and numbered sections (01, 02, 03).
The A4 CV preview is the most beautiful object on every screen: real paper, soft natural shadow.

## Anti-slop rules (never generate)
- Gradient text, neon glows, aurora/blob backgrounds, purple-blue gradients
- Glassmorphism on normal cards, identical rounded-2xl shadow cards in grids of three
- Sparkle/magic-wand icons, emoji as icons, robot/brain imagery, icon-in-circle bullets
- Generic copy: "Welcome back!", "Supercharge", "Seamless", "Unlock", "Elevate"
- Lorem ipsum, "John Doe", fake metrics, decorative widgets with no purpose
Use instead: hairline rules, whitespace, dramatic type scale contrast, real Indonesian content, one focal point per screen.

## Motion principles
1. Motion explains, never decorates: it shows where something came from, where it went, or that something changed.
2. Choreograph, don't scatter: one orchestrated sequence per screen load, not many independent animations.
3. Animate only `transform`, `opacity`, `clip-path`, and `filter` (sparingly). Never animate width, height, top, left, or box-shadow directly.
4. Use design tokens for all timing. No magic numbers in components.
5. Every animation must respect `prefers-reduced-motion` (replace movement with a simple opacity fade or none).
6. Interaction feedback under 120ms, element transitions 240-420ms, page-level 400-600ms. Nothing over 700ms except intentional ambient loops.

Motion tokens (define in CSS variables and Tailwind theme):
--ease-out-expo: cubic-bezier(0.16, 1, 0.3, 1)
--ease-in-out-quart: cubic-bezier(0.76, 0, 0.24, 1)
--ease-standard: cubic-bezier(0.2, 0, 0, 1)
--ease-spring-soft: linear() approximation of a gentle spring (no bounce overshoot above 4%)
--dur-instant: 90ms, --dur-fast: 180ms, --dur-base: 320ms, --dur-slow: 480ms, --dur-page: 560ms
--stagger: 45ms

## Code conventions
- Views: layouts in app/Views/layouts, components as View Cells or partials in app/Views/components
- JS: Alpine components in resources/js/components, one file each, no inline script blocks except Alpine x-data
- CSS: Tailwind utilities plus a small `motion.css` for keyframes, view-transition rules, and tokens
- HTMX: always send the CSRF token (hx-headers or meta tag listener); use hx-swap with transition:true
- Escape all output with esc(). Accessible by default: landmarks, labels, aria-live for async status, visible focus rings, keyboard navigation
- No heavy animation libraries by default. If needed, allow only Motion One (tiny) or GSAP for complex timelines, imported on demand