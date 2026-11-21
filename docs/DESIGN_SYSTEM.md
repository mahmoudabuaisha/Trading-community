# Design system

A dark, warm interface: near-black surfaces, a single gold accent, soft glass panels. One typeface (Alexandria) serves both Arabic and Latin text.

## Tokens

Every colour, spacing step, radius, shadow and duration is defined once in `resources/css/tokens.css`. Components in `resources/css/app.css` reference tokens only — changing the brand colour touches one file.

| Group | Tokens | Use |
| --- | --- | --- |
| Surfaces | `--bg-900…700`, `--surface`, `--surface-raised`, `--surface-sunken` | Page, panels, inset wells |
| Gold | `--gold-200…900`, `--gold-gradient` | Accent text, borders, primary actions, progress |
| Text | `--text`, `--text-soft`, `--text-muted`, `--text-on-gold` | Body, secondary, captions, text on gold fills |
| Lines | `--line-soft`, `--line`, `--line-strong` | Dividers, default borders, emphasised borders |
| Semantic | `--green-500` (done), `--red-500` (error / urgent), `--blue-500`, `--neutral-400/500` | Status only — never decoration |
| Spacing | `--space-1…12` (4px base) | All padding and gaps |
| Shape | `--radius-sm…xl`, `--radius-pill` | Controls → cards → panels |
| Motion | `--motion-fast` (hover), `--motion-base` (entrances), `--motion-slow` (ambient) | Respects `prefers-reduced-motion` |

## Direction

Layout uses CSS logical properties throughout (`margin-inline-start`, `inset-inline-end`, `text-align: start`), so one stylesheet serves RTL and LTR. Direction is set once on `<html>` by `resources/js/i18n/index.ts`. Directional icons (chevrons, arrows) are swapped in the component by `isRtl`. Emails, dates and numbers that must stay left-to-right use `dir="ltr"` or `<bdi>`.

## Components

| Class | Role |
| --- | --- |
| `.panel`, `.suite`, `.banner` | Glass containers |
| `.btn` (+ `--outline`, `--ghost`, `--gold`, `--block`) | Actions; `--gold` is reserved for the single primary action on the auth screen |
| `.icon-btn`, `.pill`, `.chip-btn`, `.segmented` | Compact controls |
| `.count-pill` (+ `--ok`, `--gold`) | Read-only labels and counters |
| `.field`, `.input`, `.input-wrap`, `.check` | Form controls; errors render in `.field__error` beside the field |
| `.track`, `.ring`, `.score-orb`, `.timer-dial` | Progress, driven by an inline `--pct` or `inline-size` |
| `.stat-card`, `.empty`, `.toast`, `.dialog` | Feedback and summaries |
| `.avatar` (`--sm`, `--md`, `--lg`) | Photo or initial |

## Charts

Chart.js via `components/ChartCanvas.vue`, which reads colours and the font from the tokens. Axes carry a title, counts appear in doughnut legends, and series are distinguished by label as well as colour.

## Accessibility

Real `<button>`, `<nav>`, `<label>` and `<dialog>` elements; a visible gold focus ring on every interactive element; headings descend in order; destructive member actions offer undo, and irreversible admin actions ask for confirmation.
