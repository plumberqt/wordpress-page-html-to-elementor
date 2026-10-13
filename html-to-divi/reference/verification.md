# Verifying without a WordPress install — and knowing what you can't

You will not have the site. Four techniques cover most of it; one category is genuinely out of
reach, and pretending otherwise is what produces "it's done" followed by four rounds of bugs.

## 1. Bare-PHP harnesses (the two that matter most)

Run the plugin's own PHP with WordPress stubbed. `php -v` is usually available locally; these need
nothing else.

**Load order.** Include the bootstrap with WP functions stubbed and **no Divi classes defined**. It
must load clean and register zero module dependencies (only the hook callback). This is the check
that stops a site-wide fatal — see `divi5-api.md`.

```php
define('ABSPATH', 1);
function add_action($h, $cb, $p = 10, $a = 1) { $GLOBALS['hooks'][$h][] = $cb; }
function plugin_dir_path($f) { return dirname($f) . '/'; }
// … stub the rest as needed
require '<plugin>/plugin.php';           // must not fatal
```

**Module contract.** Define the interface, then assert every module implements it, exposes a callable
`render_callback`, points at a `modules-json/<slug>/module.json` that exists, and that every
module.json declares `content` + `design` + `advanced`.

**Layout builder.** Call `build()` with `SELECT_DM_PATH`/`URL` defined and assert on the markup:
section count, module count, child counts, balanced delimiters, JSON validity of every attribute
set, and that the import file's `post_content` equals the template with the URL token resolved.

## 2. The preview page

`tools/build-preview.js` emits the DOM the render callbacks produce, styled by the built
`bundle.css`. It is only worth its fidelity, so it must wrap each band in Divi's
section > row > column with Divi's real container defaults, and reproduce Divi core's
`.et_pb_module { position: relative }`. Both of those caught real bugs; without them the preview
looked correct while the live page was broken.

## 3. Measure against the live page in headless Chrome

The strongest technique available, and it needs no WordPress access. Once the plugin is installed on
a staging site, you can verify a CSS change against the site's own DOM *before* asking anyone to
deploy it:

```bash
# 1. pull the real page
curl -sS -o page.html "https://site.test/the-page/"

# 2. in a copy: add <base href> so remote assets still resolve, and repoint the
#    plugin's CSS/JS at your local build
#    …/plugins/acme-divi-modules/styles/bundle.css  ->  file:///…/styles/bundle.css

# 3. append a probe that measures computed boxes and prints them into the DOM
#    <pre id="measure">…getBoundingClientRect() / getComputedStyle()…</pre>

# 4. read them back out
chrome --headless --disable-gpu --allow-file-access-from-files \
  --window-size=1600,1000 --virtual-time-budget=9000 --dump-dom "file:///…/copy.html" \
  | grep -o '<pre id="measure".*</pre>'
```

Wrap the probe in `window.addEventListener('load', () => setTimeout(…, 900))` so Divi's own scripts
have run. The probe can also **click things** — it is how the pager arrows and the topic panel were
verified, and how a form's field layout was checked by splicing the site's own rendered field markup
into the page first.

Loop the window width to check a container ladder at every breakpoint, and compare your bands
against an element you know is the site's own container (a Theme Builder footer row, say) rather
than against a number you remember.

`chrome --headless --screenshot` also works for a visual check; to reach a fold far down the page,
inject a script that hides the other sections rather than trying to scroll.

## 4. Build-time validation

Both generators refuse to emit known-bad output, which is cheaper than any test:

- `build-module-json.js` — fails if an attribute references a group the module doesn't declare.
- `build-layout.js` — fails if a block name isn't in `modules-json/`, or an attribute name isn't in
  that module's schema. Extend `DIVI_ATTRS` with the top-level attributes of any Divi core block you
  write, taken from Divi's own module.json.

## What you cannot verify — hand this list over

Only Divi's Visual Builder renders its own settings chrome. Every one of these failed silently on a
real build **after** all the checks above passed:

- **Open one child of each child-module type.** Does the panel show fields? Is the group **labelled**?
- **Open the parent** — are the children listed in its Elements group / layers panel?
- **Open any native Divi module you wrote into the layout** — does it have its children (form fields)?
- **Check the page, not just the library item** — a page built from an earlier copy keeps it.
- Confirm the responsive treatment, if any was agreed, at the client's real breakpoints.

Give the client this list explicitly, as a short numbered checklist, at handover. It is not padding:
three of the four rounds of defects on the source build were things only this list surfaces.
