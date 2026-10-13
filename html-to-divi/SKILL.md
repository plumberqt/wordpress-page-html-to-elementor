---
name: html-to-divi
description: Reproduce a design (HTML page, Claude Design .dc.html canvas, or design folder) as editable Divi 5 custom modules in one WordPress plugin, plus an importable Divi Library layout that assembles the whole page. Every text and image value editable in the Visual Builder; the source design is the spec, matched exactly rather than approximated. Also covers additional pages that reuse the same modules with different content. Use when the target is Divi 5 (builderVersion 5.x, Gutenberg-style `wp:divi/*` blocks). SKIP for Elementor (use html-to-elementor), Bricks, Beaver Builder, Divi 4 shortcode builds, and greenfield design with no source.
metadata:
  opencode/slash: "true"
---

# Design → Divi 5 modules + layout

The user has a finished design and wants it rebuilt as Divi 5 custom modules, with the whole page
available as a one-click Divi Library layout. This skill is the playbook from a build that shipped
15 modules across 9 sections — including the four rounds of defects it took to get right, which are
marked ⚠️ throughout. Apply them from the start; do not rediscover them.

> Runs in **Claude Code** (local filesystem, Node, PHP CLI, headless Chrome, zip). It builds and
> packages a plugin and layout files; it does **not** touch the WordPress site. The user installs the
> zip and imports the layout.

## The bar

**Lift the design's real values; never re-derive by eye.** Open the source and read the actual
colors, spacing, type and markup out of it. Hand-guessing from a screenshot is what produces "tiny
differences", and across 9 sections that drift compounds into a rebuild.

## ⚠️ Hard rule: do not invent responsiveness

The source design is the spec. If it is a single desktop artboard with no media queries — most Claude
Design canvases are — **ship it that way and say so**. Authoring breakpoints by taste and presenting
them as fidelity is inventing requirements: they have no source to be right against, they are the
part most likely to be wrong, and the user did not ask for them.

If the design has responsive rules, reproduce those exactly. If it has none and the page plainly
needs them, **say so and ask** — a separate, explicit piece of work with its own review, not a
silent addition.

Same principle for **container width**: default to the design's own shell (the canvas's max-width
and gutter). Matching the host site's container instead is a real and often-wanted change, but it is
the user's call — offer it, with the measurement method in `reference/verification.md`, and only do
it when asked.

## 0. Ask before building

Get these up front so the plugin is wired correctly from the first build:

1. **Brand/slug** → plugin folder + prefix (`acme` → `acme-divi-modules`, blocks `acme/*`, CSS
   `acme_*`, constants `ACME_DM_*`, namespace `AcmeDM`).
2. **An export from the target install** — one existing page or Divi Library item. This is
   non-negotiable ground truth for block names and attribute shapes; see
   `reference/block-serialization.md`. Ask them to strip the `images` key if the file is huge.
3. **Divi version** (expect 5.x) and whether the header/footer are Theme Builder templates — those
   are out of scope by default.
4. **GitHub repo** (`owner/repo`) for the auto-updater, and the token constant name for a private
   repo (default a shared org token from `wp-config.php`). Bake both in immediately — don't defer.
5. **Forms.** If the design has one, ask for an **export of the form module they already use**. It
   carries the working recipient, message template and redirect, and the field shapes are not
   guessable. See `reference/core-modules.md`.
6. **Which page(s)** — if several pages share this layout, say so now; step 6 makes each additional
   page nearly free.

Then read the source. If the folder holds both a large "standalone" HTML and a `*.dc.html` +
`support.js`/`image-slot.js`, the standalone is a bundler artifact — **use the `.dc.html`**. Its
`<script type="text/x-dc">` block holds the design's data model (the arrays behind repeated cards,
topics, FAQs); mine that for content, not just the rendered DOM. State-driven behaviour lives in a
`DCLogic` component as state transitions, not as JavaScript to lift.

## 1. Extract the design system

`src/system/tokens.css` — colors, fonts, the shell width and gutter, as CSS custom properties copied
from the source. `src/system/sections.css` — one block of rules per section, values lifted verbatim.
`src/system/style.css` — **must be named that**; webpack routes it to the VB-only bundle.

Two rules that are not optional:

- **Theme isolation.** Divi's element selectors and any child theme outrank single-class component
  rules, so re-assert inherited typography on a `.acme_module` class that every module carries.
- ⚠️ **Divi container reset.** A module that carries the design's own shell and vertical rhythm has
  to be freed from Divi's containers, or the row caps every band's width and clips full-bleed
  backgrounds. Scope it with `:has()` so hand-assembled pages behave like the compiled layout:

```css
.et_pb_section:has(.acme_module) { padding: 0 !important; }
.et_pb_row:has(.acme_module) { width: 100% !important; max-width: 100% !important; padding: 0 !important; margin: 0 auto !important; }
.et_pb_row:has(.acme_module) .et_pb_column { width: 100% !important; max-width: 100% !important; padding: 0 !important; margin: 0 !important; }
```

Exclude any section where the Divi **row itself** is a design element (a card with a background):
`:not(:has(.acme_card))`. And remember ⚠️ **Divi always wraps modules in a column** — CSS that
treats the row as a flex container never reaches the modules. Style the column too.

## 2. Plan the module split — confirm before coding

One module per band of the design. Anywhere content repeats, a **parent + child** pair (Divi 5 has
no repeater field). Write the plan out as a table — section, parent module, child module, child
count — and get it agreed. Fifteen modules is a normal size for a nine-section page.

Name modules for what the client sees ("Topic Switcher", not "Section 4").

## 3. Generate the plugin

Copy `reference/plugin/` and `reference/tools/` and `reference/src/` into the project, then rename
the `acme` tokens in one pass:

```bash
grep -rl 'acme\|Acme\|ACME_DM' . | xargs sed -i \
  -e 's/AcmeDM/BrandDM/g' -e 's/ACME_DM/BRAND_DM/g' -e 's/ACME_GH_TOKEN/BRAND_GH_TOKEN/g' \
  -e "s/acme-divi-modules/brand-divi-modules/g" -e 's/acme_module/brand_module/g' \
  -e "s|'acme/|'brand/|g" -e 's/acme-dm-/brand-dm-/g' -e 's/\bAcme\b/Brand/g'
```

Everything in `reference/` is working code, not pseudocode: the plugin bootstrap with the safe load
order, the shared module trait (all the per-module boilerplate, once), the render helpers, the
`module.json` generator + field builders, the layout compiler, the preview generator, the admin page
with the layout audit, and the GitHub auto-updater.

Then per module: add it to `tools/modules.spec.js` (one line per field), write its PHP render
callback and its JSX edit component so they mirror each other element for element, and add it to
`Modules.php` and `src/index.js`.

**Read `reference/divi5-api.md` before writing the first module.** It carries the load-order rule
that white-screens a site, the parent/child pattern, and the two settings-panel failures that are
invisible from the front end.

## 4. Compile the layout

`tools/build-layout.js` turns one content file per page into block markup plus an importable
`et_pb_layout` JSON — and the same markup, with the plugin URL left as a token, for the plugin's
one-click builder to insert. One compile, two artefacts, no second copy of the page to keep in sync.

**Read `reference/block-serialization.md` first.** Mandatory row/column attributes, attribute
escaping, the import envelope, Divi 5's term slugs, site-wide presets, and — the fact that causes
the most confusion — ⚠️ **loading a layout onto a page copies it**, so a rebuild does not reach a
page built from an earlier copy.

## 5. Verify, then hand over what you can't verify

`reference/verification.md`: bare-PHP harnesses, the preview page, measuring against the live page in
headless Chrome, and build-time validation.

Then give the user the short **Visual Builder checklist** from that file. Only Divi's own chrome
renders its settings panels; several defects on the source build were invisible to every automated
check and surfaced only by clicking one child module. Say plainly which parts you verified and which
need their eyes.

## 6. Additional pages, same layout

The expensive work is done and does not repeat. A new page is:

1. `layout/page-content/<slug>.json` — the copy, plus `title`
2. `node tools/build-layout.js <slug>` → `layout/<slug>.json`
3. Import, load onto the page

No module work, no plugin change, no zip re-upload. Different text, different counts (5 items
instead of 7), a dropped section, different images — all data. A genuinely **new kind of section** is
the one case that needs a new module: say so and price it, rather than quietly building it.

For content input, cheapest first: a filled-in content JSON (~3k tokens/page) > markdown or plain
text with headings matching the field names > HTML (~13k tokens/page — it re-describes a layout you
already compiled) > `.docx` (binary, needs conversion, structure has to be inferred from
formatting). For repeated cards, a CSV beats prose in any format.

## Packaging

Commit `scripts/`, `styles/`, `modules-json/`, `assets/` and `layout/` so the zip installs and runs
with no build step on the server. Bump the plugin version on every CSS/JS change — the version is
the cache-buster, and a user testing a stale `?ver=` will report your fix as broken.

Zip the plugin folder excluding `node_modules`, and hand over: the zip, the layout JSON path, the
import steps, the VB checklist, and anything left for them (a form's recipient address, say).
