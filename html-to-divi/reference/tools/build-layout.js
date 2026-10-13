/**
 * Compiles one page into Divi 5 block markup, once, into two artefacts:
 *
 *   layout/<slug>.blocks.html   the markup, with the plugin URL left as a token
 *   layout/<slug>.json          a Divi Library import file, token resolved
 *
 * The PHP builder (includes/class-layout-builder.php) reads the .blocks.html and
 * swaps the token for ACME_DM_URL, so the one-click build and the import file
 * are the same layout — there is no second copy of the page to keep in sync.
 *
 *   node tools/build-layout.js <slug>     reads layout/page-content/<slug>.json
 *
 * A second page with the same layout is a second content file. Nothing here or
 * in the plugin changes.
 *
 * ⚠️ Block names, attribute shapes and the import envelope must be copied from a
 * real export taken from the TARGET install — see reference/block-serialization.md.
 * The values below were verified against Divi 5.3.3; confirm them, don't assume.
 */

const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const OUT_DIR = path.join(ROOT, 'layout');
const BUILDER_VERSION = '5.3.3';
const URL_TOKEN = '{{ACME_DM_URL}}';

// Where the plugin's own assets live on a stock install. Root-relative so the
// import file is not tied to a domain (staging vs live).
const DEFAULT_PLUGIN_URL = '/wp-content/plugins/acme-divi-modules/';

// One content file per page: `node tools/build-layout.js <slug>` reads
// layout/page-content/<slug>.json and writes layout/<slug>.json next to it.
const PAGE_SLUG = process.argv[2] || 'example-page';

const content = JSON.parse(
  fs.readFileSync(path.join(OUT_DIR, 'page-content', PAGE_SLUG + '.json'), 'utf8')
);

const PAGE_TITLE = content.title || PAGE_SLUG;

// --- block serialisation -----------------------------------------------------

/**
 * Encode attributes the way WordPress's own block serialiser does: `<`, `>` and
 * `&` as \u escapes, so nothing inside an attribute can terminate the block
 * comment. Slashes are left alone and non-ASCII is escaped, matching the real
 * export byte for byte.
 */
const encodeAttrs = (attrs) =>
  JSON.stringify(attrs)
    .replace(/[^\x20-\x7e]/g, (c) => '\\u' + c.charCodeAt(0).toString(16).padStart(4, '0'))
    .replace(/</g, '\\u003c')
    .replace(/>/g, '\\u003e')
    .replace(/&/g, '\\u0026');

// --- validation --------------------------------------------------------------

/**
 * Every module and the attributes it actually declares, read from the
 * generated module.json files. A typo in a block name or an attribute name
 * would otherwise surface as a silently empty element on the live page, so the
 * compiler refuses to emit one.
 */
// `modules-json/` is webpack's copy of `src/components/`, so it only exists
// after a build. Fall back to the source so `build:layout` works on its own.
const schemaDir = ['modules-json', path.join('src', 'components')]
  .map((dir) => path.join(ROOT, dir))
  .find((dir) => fs.existsSync(dir));

if (!schemaDir) {
  throw new Error('no module schemas found — run `npm run build:json` first');
}

const declared = Object.fromEntries(
  fs
    .readdirSync(schemaDir)
    .map((slug) => path.join(schemaDir, slug, 'module.json'))
    .filter((file) => fs.existsSync(file))
    .map((file) => {
      const meta = JSON.parse(fs.readFileSync(file, 'utf8'));
      return [meta.name, Object.keys(meta.attributes || {})];
    })
);

/**
 * The Divi blocks this layout writes, with the top-level attributes each one
 * accepts. Transcribed from Divi's own generated module.json files on the
 * install — themes/Divi/includes/builder-5/visual-builder/packages/
 * module-library/src/components/<slug>/module.json — so a typo in a Divi
 * attribute name fails the build the same way one of yours does. Only the
 * blocks this file emits are listed.
 */
const DIVI_ATTRS = {
  'divi/section': ['module'],
  'divi/row': ['module'],
  'divi/column': ['module'],
  'divi/contact-form': [
    'module', 'title', 'captcha', 'field', 'checkbox', 'radio', 'button', 'email', 'redirect',
  ],
  'divi/contact-field': [
    'module', 'field', 'checkbox', 'radio', 'fieldTitle', 'fieldItem', 'conditionalLogic',
  ],
};

const assertModule = (name, attrs) => {
  const allowed = declared[name] || DIVI_ATTRS[name];

  if (!allowed) {
    throw new Error(`unknown block "${name}" — not in modules-json/ or DIVI_ATTRS`);
  }

  Object.keys(attrs).forEach((attr) => {
    if (!allowed.includes(attr)) {
      throw new Error(`block "${name}" has no attribute "${attr}"`);
    }
  });
};

const block = (name, attrs = {}, children = '') => {
  assertModule(name, attrs);

  const json = ' ' + encodeAttrs({ ...attrs, builderVersion: BUILDER_VERSION });

  return children === ''
    ? '<!-- wp:' + name + json + ' /-->\n'
    : '<!-- wp:' + name + json + ' -->\n' + children + '<!-- /wp:' + name + ' -->\n';
};

// --- attribute helpers -------------------------------------------------------

/** A Divi 5 content attribute: { innerContent: { desktop: { value } } }. */
const value = (v) => ({ innerContent: { desktop: { value: v } } });

const image = (src, alt) => value({ src, alt });

const padding = (top, bottom) => ({
  spacing: {
    desktop: {
      value: {
        padding: { top, bottom, syncVertical: 'off', syncHorizontal: 'off' },
      },
    },
  },
});

// --- structure ---------------------------------------------------------------

/**
 * One section > row > column wrapper.
 *
 * Every module is its own full-width band, carrying the site's content
 * shell, gutter and its vertical rhythm inside, so sections are zero-padded and
 * the row is neutralised in CSS (see the container reset at the top of
 * src/system/sections.css). The zero padding is written out as a real attribute
 * as well, so the builder's spacing fields read 0 instead of inheriting a
 * site-wide section preset.
 */
const section = (children, spacing) => {
  const column = block(
    'divi/column',
    {
      module: {
        advanced: { type: { desktop: { value: '4_4' } } },
        decoration: { sizing: { desktop: { value: { flexType: '24_24' } } } },
      },
    },
    children
  );

  const row = block(
    'divi/row',
    {
      module: {
        advanced: {
          columnStructure: { desktop: { value: '4_4' } },
          flexColumnStructure: { desktop: { value: 'equal-columns_1' } },
        },
        decoration: { layout: { desktop: { value: { flexWrap: 'nowrap' } } } },
      },
    },
    column
  );

  return block(
    'divi/section',
    { module: { decoration: spacing || padding('0px', '0px') } },
    row
  );
};

// --- the contact form --------------------------------------------------------
//
// A Divi core module with children needs its children written out, and their
// attribute shapes are NOT guessable. See reference/core-modules.md: ask for an
// export of the equivalent working module from the client's own Divi Library and
// copy its serialisation. Delete this whole section if the design has no form.

// --- the page ----------------------------------------------------------------

/**
 * PROJECT-SPECIFIC. One section() per band of the design, in order.
 *
 * Modules are emitted with empty attributes wherever their own defaults already
 * match the design — Divi falls back to module-default-render-attributes.json,
 * so repeating those values here would only create a second copy to keep in
 * sync. Only genuinely per-instance content (each card, row, item) is written.
 */
const buildPage = () => {
  const parts = [];

  // A section whose module carries all its own content via defaults.
  parts.push(section(block('acme/example-parent')));

  // A parent with one child per repeated item.
  const items = content.items
    .map((item) =>
      block('acme/example-child', {
        label: value(item.label),
        detail: value(item.detail),
      })
    )
    .join('');

  parts.push(section(block('acme/example-parent', {}, items)));

  // An image on a child: the token is resolved per install (PHP) or to the
  // stock plugin path (import file).
  parts.push(
    section(
      block('acme/example-child', {
        photo: image(URL_TOKEN + 'assets/images/example.jpg', 'Example'),
      })
    )
  );

  return parts.join('\n');
};

// --- the import envelope -----------------------------------------------------

/**
 * Shape copied field for field from a real Divi 5 library export. `images` and
 * `thumbnails` are empty on purpose: every image here is a plugin asset served
 * from disk, so there is nothing for Divi to sideload.
 *
 * `_et_pb_use_divi_5: on` is what marks the content as D5 blocks rather than D4
 * shortcodes. The term slugs are Divi 5's own (`non_global` / `regular`), not
 * Divi 4's `not_global` / `on_page`.
 */
const envelope = (blocks) => ({
  context: 'et_builder_layouts',
  data: {
    1: {
      ID: 1,
      post_content: blocks,
      post_title: PAGE_TITLE,
      post_excerpt: '',
      post_status: 'publish',
      post_name: PAGE_SLUG,
      post_type: 'et_pb_layout',
      menu_order: 0,
      post_parent: 0,
      post_meta: {
        _et_pb_built_for_post_type: ['page'],
        _et_pb_use_divi_5: ['on'],
      },
      terms: {
        1: { name: 'layout', slug: 'layout', taxonomy: 'layout_type', parent: 0, all_parents: [], description: '' },
        2: { name: 'non_global', slug: 'non_global', taxonomy: 'scope', parent: 0, all_parents: [], description: '' },
        3: { name: 'regular', slug: 'regular', taxonomy: 'module_width', parent: 0, all_parents: [], description: '' },
      },
    },
  },
  presets: { module: {} },
  global_colors: [],
  global_variables: [],
  canvases: [],
  images: {},
  thumbnails: [],
});

// --- write -------------------------------------------------------------------

const blocks = buildPage();

fs.writeFileSync(path.join(OUT_DIR, PAGE_SLUG + '.blocks.html'), blocks, 'utf8');

fs.writeFileSync(
  path.join(OUT_DIR, PAGE_SLUG + '.json'),
  JSON.stringify(envelope(blocks.split(URL_TOKEN).join(DEFAULT_PLUGIN_URL)), null, 1),
  'utf8'
);

const count = (name) =>
  (blocks.match(new RegExp('<!-- wp:' + name + '[ /]', 'g')) || []).length;

console.log(
  PAGE_SLUG + ': ' + count('divi/section') + ' sections, ' + count('acme/[a-z-]+') + ' modules'
);
