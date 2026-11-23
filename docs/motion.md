# Motion and interaction

The motion layer follows the "Private Atelier" rules in `.github/copilot-instructions.md`: motion explains state or direction, uses transform and opacity where possible, and respects reduced-motion preferences. Tailwind CSS 4 is configured through `@theme` in `resources/css/motion.css`; this repository does not use a `tailwind.config.js`.

## Tokens

| Token | Value | Use |
| --- | --- | --- |
| `--ease-out-expo` | `cubic-bezier(0.16, 1, 0.3, 1)` | Entrances, drawer movement, and settling |
| `--ease-in-out-quart` | `cubic-bezier(0.76, 0, 0.24, 1)` | The one-pass PII scan |
| `--ease-standard` | `cubic-bezier(0.2, 0, 0, 1)` | Fades and steady UI transitions |
| `--ease-spring-soft` | `linear(0, 0.18 12%, 0.42 30%, 0.7 55%, 0.9 78%, 1)` | Toggle thumb and drop settle, with no overshoot |
| `--dur-instant` / `--duration-instant` | `90ms` | Press feedback |
| `--dur-fast` / `--duration-fast` | `180ms` | Short fades and state changes |
| `--dur-base` / `--duration-base` | `320ms` | Standard element transitions |
| `--dur-slow` / `--duration-slow` | `480ms` | Reveal and count-up |
| `--dur-page` / `--duration-page` | `560ms` | Page and step progress |
| `--duration-auth-intro` | `900ms` | Auth paper entrance |
| `--duration-loader` | `900ms` | Button line loader and PII scan sweep |
| `--duration-toast` | `4s` | Default toast lifetime |
| `--duration-highlight` | `600ms` | CV preview change highlight |
| `--duration-drawer` | `420ms` | Theme preview drawer entrance |
| `--duration-stamp` | `200ms` | Successful export stamp |
| `--duration-preview-exit` / `--duration-preview-enter` | `160ms` / `240ms` | Preview fade out and in |
| `--duration-navigation-progress` | `1.8s` | Top navigation progress line |
| `--duration-tooltip` / `--duration-tooltip-delay` | `120ms` / `300ms` | Tooltip appearance and opening delay |
| `--duration-count-pulse` | `1.2s` | Autosave activity dot |
| `--duration-shimmer` | `2.4s` | Streaming skeleton |
| `--duration-caret` | `1.1s` | Streaming caret |
| `--stagger` | `45ms` | General entrance sequence |
| `--stagger-menu` | `30ms` | Action menu item sequence |
| `--stagger-pii` | `40ms` | PII underline sequence |
| `--motion-brass` | `#a98b4f` | Focus, progress, and highlight accent |

`--motion-duration-fast`, `--motion-duration-ui`, `--motion-duration-page`, and `--motion-stagger` are aliases used by existing styles. `--duration-highlight` is also defined in `resources/css/cv-preview-motion.css` because the CV preview is an isolated document.

## Named view transitions

| Name | Behavior | Used by |
| --- | --- | --- |
| `top-bar` | Remains still | Shared app header |
| `page-title` | New title rises 12px and fades in | Full-page and HTMX navigation |
| `page-content` | Page content fades and rises; AI pages use a directional 24px slide | Full-page and HTMX navigation, AI flow |
| `cv-preview` | Paper fades and scales between routes | CV preview regions when present on both pages |
| `navigation-indicator` | Shared 1px brass rule moves between active navigation items | App header navigation |
| `root` | Fallback cross-document fade and slight rise | Full-page navigation and explicit theme changes |

Cross-document transitions are enabled with `@view-transition { navigation: auto; }`. HTMX navigation swaps `#content` with `transition:true`, refreshes view names after the swap, rotates its CSRF header token from the response, and restores focus. Unsupported browsers receive a plain fade. A 1px brass line tracks navigation and HTMX requests.

## Motion by area

| Area | Implemented behavior |
| --- | --- |
| Auth | Split layout paper mockup enters slowly; pointer parallax is transform-only and capped at 6px. Form content and fields use one-time staggered reveals. Primary login and registration buttons show a fixed-width line loader while pending. |
| App shell | Navigation items animate once per session. The active link uses a shared view-transition name. Motion progress and focus restoration are attached to HTMX navigation. |
| CV editor | Navigator and form zones reveal in sequence. The preview paper settles from scale 0.985 to 1 after load. Autosave state is announced with `aria-live`; changes in the rendered CV are highlighted inside the preview document. Zoom and fit use transforms. |
| Theme preview | The drawer fades and slides from the right. Preview content fades down while loading and back in when ready. |
| PDF export | Paid exports show a three-step ledger, announce progress, stamp on success, and use the toast system. Unpaid exports follow the existing payment redirect. |
| Dashboard | CV cards lift slightly. Their paper thumbnail lifts 4px and deepens its static pseudo-element shadow. Progress fills use `scaleX`, not width animation. |
| Section ordering | Pointer reorder uses FLIP transforms and a settling animation. Keyboard users have explicit move-up and move-down controls. |
| Toasts | Toasts enter from the lower right, stack, pause their countdown on hover, and can be dismissed by button or horizontal touch swipe. |
| AI story and confirmation | Stepper progress uses a transform-based brass line; completed marks reveal with `clip-path`. Character counts use tabular figures and announce threshold crossings only. PII detection runs in the browser; its preview starts masked and can be toggled for review without changing the submitted field. |
| AI generation | A three-line skeleton appears before output. The caret and status use restrained opacity animation. AI pages slide forward or backward according to the navigation direction. |
| Reusable interactions | `.reveal`, `.reveal-stagger > *`, `.hover-lift`, `.press`, `.motion-tooltip`, `.motion-checkmark`, and `.motion-toggle-thumb` provide shared primitives. Tooltips support hover and keyboard focus. |

The AI flow is implemented as server-rendered pages, not an in-page stepper. Its direction is therefore expressed through page transitions; it does not animate a shared container height. The current generator does not provide sentence-level provenance, and the review flow does not keep a version timeline. Those features are not fabricated by the motion layer.

## Reduced motion

| Sequence | Reduced-motion behavior |
| --- | --- |
| Page and HTMX transitions | Reduced to an effectively immediate fade; navigation remains functional |
| Reveal and stagger | Content is immediately visible |
| Auth parallax | Not attached, or removed if the preference changes while open |
| Card lift, drag lift, step scale, drawer movement | Movement is removed |
| Skeleton, caret, checkmark, and other animations | Run once at effectively zero duration |
| Preview zoom and controls | The selected scale remains functional; its transition is immediate |
| Toast countdown and dismissal | Countdown and dismissal still work without animated movement |

## Build and tests

Build the application assets with `npm run build`. Run motion checks with `npm run test:motion`, AI stream checks with `node --test tests/javascript/cv-ai-stream.test.cjs`, and PHP tests without the optional coverage driver with `php vendor/bin/phpunit --no-coverage`.
