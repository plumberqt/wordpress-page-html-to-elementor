# How It Works

Each skill is a `SKILL.md` — a playbook an AI agent loads on demand. When loaded, the agent follows
the playbook step by step: read the source, ask setup questions, then generate and package a
WordPress plugin. Below is what actually happens, per skill.

---

## Shared principle: **lift, don't re-derive**

For both Elementor and Divi, the source HTML is the single source of truth. The agent must:

- read the **real** hex colors, font stacks, spacing, letter-spacing and `clamp()` values out of the
  source CSS / inline styles,
- copy CSS rules, `@keyframes` and JS behavior **verbatim**,
- keep the source's **class names and ids unchanged** (the bundled JS selects them),
- use the source's **real SVG icon paths**, not look-alikes.

Hand-guessing from a screenshot is explicitly forbidden — it is the root cause of "tiny differences"
that compound across sections.

---

## Skill 1 & 2 — `html-to-elementor` / `html-to-elementor-pro`

### What it produces

One self-contained WordPress plugin, stamped from a template:

```
<brand>-elementor-widgets/
  <brand>-elementor-widgets.php          ← registers category, widgets, assets, updater
  includes/class-github-updater.php      ← copied VERBATIM from reference/
  includes/class-template-registrar.php  ← copied VERBATIM from reference/
  widgets/class-<section>-widget.php     ← ONE editable widget per section
  assets/css/<brand>-system.css          ← tokens + source component CSS, verbatim
  assets/js/<brand>-interactions.js      ← source behaviors, verbatim
  assets/fonts/  assets/images/  templates/<page>.json
```

### Process the agent follows

1. **Orient first.** Find the canonical source (`final.html`). Ignore other HTML variants — they
   often disagree (different copy, light vs dark). Detect compiled React/JS pages and extract the
   underlying data arrays, not just rendered DOM.
2. **Split into sections.** Every top-level section becomes a widget — including id-less micro-CTAs
   and callouts. Header/footer excluded unless asked.
3. **Extract verbatim, per section.** Markup + inline styles, the class CSS, `@keyframes`, driving
   JS (sliders, `IntersectionObserver` scroll-reveal, hover-steal, float loops), the real icons, and
   the design tokens.
4. **Bundle assets.** Local images into `assets/images/` (downscaled for web), external image URLs
   downloaded/bundled, fonts (`woff2` + `woff`) into `assets/fonts/` with `@font-face`. **Never** link
   a webfont CDN.
5. **Build one plugin, many widgets.** Every widget depends on the shared `*-system` CSS and
   `*-interactions` JS, and outputs the section's exact source markup so the shared JS works
   untouched.
6. **Theme isolation.** A scoped CSS block (keyed to the widget prefix) re-asserts fonts/colors and
   fixes the classic `img { height:auto !important }` theme override that collapses
   `object-fit:cover` images.
7. **Editability.** Content is exposed as controls (TEXT / TEXTAREA / WYSIWYG / MEDIA / URL), with a
   **repeater** for every card/list/slider collection. Style controls inject values into the inline
   style string (defaulting to the source value, so the design stays pixel-exact until edited).
8. **Verify.** Build a self-contained preview per section; compare against source.
9. **Package.** Forward-slash `.zip` only (`git archive`), with a `build.sh` that hard-fails on
   regression.

### Why the reference PHP is copied verbatim

`class-github-updater.php` handles private-repo authenticated downloads, redirect handling and cache
bypass. Re-writing it from prose reliably breaks these in silent ways. The skill instructs the agent
to **copy the file and change only the namespace line**.

### Editable, not monolithic

A hard rule: **never ship the page as one giant "whole page" widget.** Every section must be its own
editable widget. The "fast monolithic page first" shortcut always forces a full rebuild.

---

## Skill 3 — `html-to-divi`

### What it produces

One WordPress plugin of **Divi 5 custom modules**, plus an importable **Divi Library layout** that
assembles the whole page. Target is Divi 5 (`builderVersion 5.x`, Gutenberg-style `wp:divi/*`
blocks).

### Process the agent follows

1. **Ask up front** — brand/slug, an **export from the target install** (ground truth for block names
   and attribute shapes), Divi version, GitHub repo + token, forms, which pages.
2. **Extract the design system** — `tokens.css` (colors, fonts, shell width), `sections.css` (one
   block per section, values lifted verbatim), `style.css` (must be named that; webpack routes it to
   the Visual-Builder-only bundle).
3. **Theme isolation + container reset.** Re-assert typography on a per-module class, and free modules
   from Divi's containers with a `:has()`-scoped reset so full-bleed backgrounds and band widths
   render correctly.
4. **Plan the module split** — one module per band; anywhere content repeats, a **parent + child**
   pair (Divi 5 has no repeater field). Confirmed with the user as a table before coding.
5. **Generate the plugin** — from the bundled `reference/plugin/`, `reference/tools/`,
   `reference/src/`. Per module: a line in `tools/modules.spec.js`, a PHP render callback and a JSX
   edit component that mirror each other element for element.
6. **Compile the layout** — `tools/build-layout.js` turns one content file per page into block markup
   **plus** an importable `et_pb_layout` JSON (and the same markup for the one-click builder). One
   compile, two artifacts.
7. **Verify** — bare-PHP harnesses, preview page, measurement against the live page in headless
   Chrome, build-time validation, then a short **Visual Builder checklist** (some defects only show
   in Divi's own settings panels).
8. **Additional pages** are nearly free: a new `page-content/<slug>.json` + one compile + import. No
   module or plugin change.

---

## The WordPress plugin updater (both Elementor + Divi)

Both stacks bake in a **GitHub auto-updater**:

- an `Update URI:` header + `*_GH_OWNER` / `*_GH_REPO` constants,
- a read-only token from `wp-config.php` for private repos,
- release flow: push → tag `vX.Y.Z` → attach `<slug>.zip` as a release asset → WordPress shows a
  one-click **Update**.

> The release asset must be named **exactly `<slug>.zip`**. A versioned filename silently falls back
> to the zipball endpoint, which rejects the asset `Accept` header with **HTTP 415**.

---

## Packaging rule (all skills)

Never use PowerShell `Compress-Archive` — on PS 5.1 it writes backslash path separators, which
WordPress's extractor mishandles → **"Plugin file does not exist."** Use:

```bash
git archive --format=zip -9 --prefix=<name>/ -o dist/<name>.zip HEAD
```

or Info-ZIP:

```bash
zip -r -q <name>.zip <name> -x "*.DS_Store" -x "<name>/.git/*"
```

---

## Requirements recap

Node.js · PHP CLI · Git + `gh` · headless Chrome · WordPress with Elementor and/or Divi 5.
