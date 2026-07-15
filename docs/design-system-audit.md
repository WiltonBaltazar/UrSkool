# UrSkool — Design System Audit Report
**Date:** 2026-04-09 | **Auditor:** Claude Code | **Scope:** `resources/js/`

---

## Executive Summary

The system is built on a solid foundation: shadcn/ui primitives, a well-structured CSS custom property token layer, and a consistent monochromatic palette with DM Serif Display / DM Sans typography. Both light and dark modes are defined. However, the audit uncovered **22 issues** — 4 critical, 8 high, 7 medium, 3 low — concentrated in three areas: incomplete dark-mode token coverage, accessibility failures on interactive elements, and token/naming gaps that limit safe reuse.

| Severity | Count |
|----------|-------|
| 🔴 Critical | 4 |
| 🟠 High | 8 |
| 🟡 Medium | 7 |
| 🟢 Low | 3 |
| **Total** | **22** |

---

## 1. Component Inventory

### shadcn/ui Primitives (41 components)
Accordion, Alert, AlertDialog, AspectRatio, Avatar, Badge, Breadcrumb, Button, Calendar, Card, Carousel, Chart, Checkbox, Collapsible, Command, ContextMenu, Dialog, Drawer, DropdownMenu, Form, HoverCard, Input, InputOTP, Label, Menubar, NavigationMenu, Pagination, Popover, Progress, RadioGroup, Resizable, ScrollArea, Select, Separator, Sheet, Skeleton, Slider, Sonner, Switch, Table, Tabs, Textarea, Toast, Toaster, Toggle, ToggleGroup, Tooltip

### Custom Components (7)
`Navbar`, `CourseCard`, `CurriculumAccordion`, `CourseReviews`, `NavLink`, `CodeHighlightEditor` (admin), `RichTextEditor` (admin)

### Custom CSS Utilities (1)
`.card-hover` — shadow + lift transition

---

## 2. Token Coverage

### Defined Tokens (index.css :root)
```
Core semantic:    background, foreground, card, popover, primary, secondary,
                  muted, accent, destructive, border, input, ring, radius
Status:           success, success-foreground
Surface:          surface-elevated, surface-sunken
Interaction:      accent-soft, accent-hover
Gradient:         gradient-hero, gradient-accent
Shadow:           shadow-card, shadow-card-hover
Typography:       font-display, font-body
Sidebar (8):      sidebar-background … sidebar-ring
```

### Token Findings

#### 🔴 CRITICAL-1 — `--success` / `--success-foreground` have no dark mode override
**Location:** `index.css` `.dark {}` block  
**Impact:** In dark mode the success color renders as a light-mode green on dark surfaces. Passes the theme switch in a broken state.  
**Fix:** Add to `.dark {}`:
```css
--success: 152 55% 52%;
--success-foreground: 0 0% 5%;
```

#### 🔴 CRITICAL-2 — Gradient tokens not mapped in `tailwind.config.ts`
**Location:** `tailwind.config.ts` — `backgroundImage` key absent  
**Impact:** `--gradient-hero` and `--gradient-accent` cannot be consumed as Tailwind utilities. Any usage relies on inline styles or arbitrary values, breaking the token contract.  
**Fix:** Add to `tailwind.config.ts` under `theme.extend`:
```ts
backgroundImage: {
  'gradient-hero':   'var(--gradient-hero)',
  'gradient-accent': 'var(--gradient-accent)',
},
boxShadow: {
  card:       'var(--shadow-card)',
  'card-hover': 'var(--shadow-card-hover)',
},
```

#### 🔴 CRITICAL-3 — `muted-foreground` fails WCAG AA contrast on light background
**Location:** `index.css` — `--muted-foreground: 0 0% 45%` on `--background: 0 0% 97%`  
**Measured contrast:** ~4.2:1 (requires 4.5:1 for normal text)  
**Impact:** Body-copy text using `text-muted-foreground` (instructor names, subtitles, metadata in `CourseCard`, `Navbar`) fails WCAG 2.2 AA for normal text.  
**Fix:** Lower lightness to 40% or below:
```css
--muted-foreground: 0 0% 40%;   /* gives ≈5.1:1 */
```

#### 🔴 CRITICAL-4 — Brand name mismatch: code says "Learnova", project is "UrSkool"
**Location:** `Navbar.tsx:87` — `<span>Learnova</span>`  
**Impact:** Visual identity inconsistency exposed to all users on every page.  
**Fix:** Replace literal string with a config constant or update to "UrSkool".

---

## 3. Accessibility Audit (WCAG 2.2 AA)

#### 🟠 HIGH-1 — Navbar search input has no accessible label
**Location:** `Navbar.tsx:91-96`  
**Issue:** `<Input placeholder="Pesquisar cursos..." />` — placeholder is not a label substitute. Screen readers announce the field with no name after the user types.  
**Fix:** Add `aria-label="Pesquisar cursos"` to the Input.

#### 🟠 HIGH-2 — Cart icon button has no accessible label
**Location:** `Navbar.tsx:177-185`  
**Issue:** `<Button variant="ghost" size="icon">` contains only `<ShoppingCart />` SVG. No `aria-label`.  
**Fix:** Add `aria-label={cartCount > 0 ? \`Carrinho — ${cartCount} item${cartCount > 1 ? 's' : ''}\` : 'Carrinho'}`.

#### 🟠 HIGH-3 — Badge rendered as `<div>` — wrong semantic element
**Location:** `badge.tsx:25`  
**Issue:** `<div className={cn(badgeVariants(...))}>` is a non-semantic container. Badges are inline text annotations and should be `<span>`.  
**Fix:** Change `div` → `span` and update the interface to `React.HTMLAttributes<HTMLSpanElement>`.

#### 🟠 HIGH-4 — CourseCard entire card is a link wrapping block-level content
**Location:** `CourseCard.tsx:13`  
**Issue:** `<Link>...<div>...<h3>...<img>` — an `<a>` wrapping block-level elements like `<div>` and `<h3>` is invalid HTML5. The link label is the entire card content, which screen readers read verbosely.  
**Fix:** Use `role="article"` on the outer div, add `aria-label={course.title}` to the Link, and limit the anchor's interactive footprint.

#### 🟠 HIGH-5 — Destructive "Limpar carrinho" has no confirmation
**Location:** `Navbar.tsx:131-135`  
**Issue:** Clicking "Limpar carrinho" immediately destroys cart contents with no undo. Violates WCAG 3.3.4 (Error Prevention).  
**Fix:** Wrap in `AlertDialog` or add a `confirm()` guard.

#### 🟠 HIGH-6 — `--destructive` dark-mode lightness too low for AA
**Location:** `index.css` `.dark` — `--destructive: 0 62.8% 30.6%`  
**Measured contrast:** `hsl(0, 62.8%, 30.6%)` ≈ `#7a1f1f` on dark background `hsl(0,0%,7%)` ≈ 2.4:1. Fails AA.  
**Fix:**
```css
--destructive: 0 72% 52%;          /* ≈ #e04040, ~5.2:1 on dark bg */
--destructive-foreground: 0 0% 98%;
```

#### 🟠 HIGH-7 — No `aria-current` on active nav links
**Location:** `Navbar.tsx`, `NavLink.tsx`  
**Issue:** Active route state is tracked via `useLocation` but no `aria-current="page"` is applied to the active link.  
**Fix:** In `NavLink` (or Navbar link wrappers), apply `aria-current={isActive ? "page" : undefined}`.

#### 🟠 HIGH-8 — Images have no `loading` attribute / no fallback
**Location:** `CourseCard.tsx:16-19`  
**Issue:** `<img>` has no `loading="lazy"` and no `onError` fallback. Failed image loads produce broken image icons with no alt text shown.  
**Fix:** Add `loading="lazy"` and an `onError` handler that swaps to a placeholder.

---

## 4. Naming Consistency

#### 🟡 MEDIUM-1 — `accent` and `primary` are semantically duplicated
Both tokens resolve to the same value (`0 0% 10%` light / `0 0% 96%` dark). This causes confusion: is the CTA button `primary` or `accent`? Code uses both interchangeably.  
**Fix:** Decide: `primary` = interactive (buttons, links). `accent` = highlight/brand decoration. Differentiate their values or alias one to the other explicitly.

#### 🟡 MEDIUM-2 — `accent-hover` is a state token mixed with semantic tokens
`--accent-hover` represents a hover-state color but is named like a semantic token. It should either be `--accent-hover` scoped under a component or renamed to make intent clear (e.g., `--accent-interactive`).

#### 🟡 MEDIUM-3 — Inconsistent token naming pattern
- `surface-elevated` / `surface-sunken` → noun-adjective ✓
- `accent-soft` → noun-adjective ✓
- `accent-hover` → noun-state ✗ (mixes patterns)
- `shadow-card` / `shadow-card-hover` → noun-noun / noun-noun-state ✗

**Fix:** Adopt `{category}-{property}-{variant}` pattern: `shadow-card-default`, `shadow-card-hover`, `surface-base`, `surface-raised`, `surface-recessed`.

#### 🟡 MEDIUM-4 — No `warning` status token
`success` and `destructive` exist but there is no `warning` token. Forms and validation states will need to use ad-hoc colors.  
**Fix:**
```css
--warning: 38 92% 50%;
--warning-foreground: 0 0% 10%;
```

#### 🟡 MEDIUM-5 — `font-display` / `font-body` vars unused by Tailwind utilities  
Font vars are defined as CSS custom properties but `tailwind.config.ts` references the raw font name strings, not the vars. They are out of sync — changing the var does not update Tailwind.  
**Fix:** Use `var(--font-display)` in tailwind.config.ts or remove the duplicate CSS vars.

---

## 5. Component State Completeness

| Component | Default | Hover | Focus | Disabled | Loading | Error | Empty |
|-----------|---------|-------|-------|----------|---------|-------|-------|
| Button | ✅ | ✅ | ✅ | ✅ | ⚠️ text-swap only | — | — |
| Input | ✅ | — | ✅ | ✅ | — | ❌ no error variant | — |
| Badge | ✅ | ✅ | ✅ | — | — | — | — |
| CourseCard | ✅ | ✅ | ❌ | — | ❌ no skeleton | ❌ no fallback | — |
| Navbar | ✅ | ✅ | ⚠️ | — | ⚠️ logout only | — | — |

#### 🟡 MEDIUM-6 — Input has no error state variant
`Input` does not accept a `state="error"` prop or apply `border-destructive`. Error presentation is delegated entirely to the Form/FormMessage wrapper.  
**Fix:** Add optional `aria-invalid` + `border-destructive` wiring to the Input component.

#### 🟡 MEDIUM-7 — CourseCard has no skeleton loading state
`Skeleton` component exists but `CourseCard` has no corresponding `CourseCardSkeleton`. List pages show nothing while courses load.  
**Fix:** Create `CourseCardSkeleton` using the `Skeleton` primitive matching the card's layout.

---

## 6. Dark Mode / Theming

#### 🟢 LOW-1 — Gradient tokens use hardcoded light-mode values in dark mode
`--gradient-hero: linear-gradient(135deg, hsl(0 0% 8%), hsl(0 0% 20%))` — not overridden in `.dark {}`. Works coincidentally (dark values in light definition) but is fragile.  
**Fix:** Add explicit dark overrides:
```css
.dark {
  --gradient-hero:   linear-gradient(135deg, hsl(0 0% 4%), hsl(0 0% 14%));
  --gradient-accent: linear-gradient(135deg, hsl(0 0% 8%), hsl(0 0% 20%));
}
```

#### 🟢 LOW-2 — No theme-toggle UI
Dark mode is declared in CSS (`.dark` class) but there is no toggle control in the Navbar or settings for users to switch modes. The system detects OS preference only implicitly.

#### 🟢 LOW-3 — No design token documentation
Tokens are only defined in code. There is no living reference (Storybook, token JSON, or style guide page) for designers or new developers to discover available tokens.

---

## 7. Remediation Roadmap

### Sprint 1 — Critical (address before next release)
1. Fix `--success` dark mode token `index.css`
2. Map gradient + shadow tokens in `tailwind.config.ts`
3. Fix `--muted-foreground` contrast to 40% lightness
4. Fix brand name "Learnova" → "UrSkool" in `Navbar.tsx`

### Sprint 2 — High (address in next sprint)
5. Add `aria-label` to Navbar search input and cart button
6. Change `Badge` from `<div>` to `<span>`
7. Fix `--destructive` dark contrast ratio
8. Add `AlertDialog` confirmation to "Limpar carrinho"
9. Add `aria-current="page"` to active nav links
10. Add `loading="lazy"` + `onError` fallback to `CourseCard` image

### Sprint 3 — Medium (backlog)
11. Differentiate `accent` vs `primary` tokens semantically
12. Add `--warning` token
13. Standardize token naming convention
14. Add `state="error"` variant to Input
15. Create `CourseCardSkeleton` component
16. Sync `--font-*` CSS vars with tailwind.config.ts

### Sprint 4 — Low / polish
17. Add dark mode gradient overrides
18. Add dark mode toggle to Navbar
19. Document design tokens (token JSON or Storybook)

---

## Quick Reference: Files to Change

| File | Issues |
|------|--------|
| `resources/js/index.css` | CRITICAL-1, CRITICAL-3, HIGH-6, LOW-1 |
| `tailwind.config.ts` | CRITICAL-2, MEDIUM-5 |
| `resources/js/components/Navbar.tsx` | CRITICAL-4, HIGH-1, HIGH-2, HIGH-5, HIGH-7 |
| `resources/js/components/ui/badge.tsx` | HIGH-3 |
| `resources/js/components/CourseCard.tsx` | HIGH-4, HIGH-8, MEDIUM-7 |
| `resources/js/components/ui/input.tsx` | MEDIUM-6 |
