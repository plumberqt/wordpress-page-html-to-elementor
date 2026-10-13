# Divi 5 layouts: block markup, import files, and how copies propagate

A Divi 5 layout is **WordPress block markup** in an `et_pb_layout` post. The modules registered by
`ModuleRegistration::register_module()` are real block types, so a layout is just serialised blocks.
Divi 4 `[et_pb_*]` shortcodes will not import into a Divi 5 layout — do not write them.

## Get ground truth before writing any layout

Divi's structural block names and attribute shapes are not published. **Ask the client to export one
existing page or template** from the target install (Divi → Divi Library → Export, or the page's
portability panel) and read the shapes out of it. That single file answers everything below for
*their* Divi version, and it costs them thirty seconds.

If the export is huge, tell them to strip the `images` key — it is base64 blobs and routinely 96% of
the file. Everything needed survives in `post_content`.

## Structure

```
divi/section  >  divi/row  >  divi/column  >  your modules
```

Verified against a Divi 5.3.3 export. The row and column attributes below are **mandatory** — omit
them and the layout imports but renders wrong:

```html
<!-- wp:divi/section {"module":{"decoration":{"spacing":{"desktop":{"value":{"padding":
  {"top":"0px","bottom":"0px","syncVertical":"off","syncHorizontal":"off"}}}}}},
  "builderVersion":"5.3.3"} -->
<!-- wp:divi/row {"module":{"advanced":{"columnStructure":{"desktop":{"value":"4_4"}},
  "flexColumnStructure":{"desktop":{"value":"equal-columns_1"}}},"decoration":{"layout":
  {"desktop":{"value":{"flexWrap":"nowrap"}}}}},"builderVersion":"5.3.3"} -->
<!-- wp:divi/column {"module":{"advanced":{"type":{"desktop":{"value":"4_4"}}},"decoration":
  {"sizing":{"desktop":{"value":{"flexType":"24_24"}}}}},"builderVersion":"5.3.3"} -->
<!-- wp:acme/hero {"builderVersion":"5.3.3"} /-->
<!-- /wp:divi/column --><!-- /wp:divi/row --><!-- /wp:divi/section -->
```

Other core blocks seen in exports: `divi/heading`, `divi/text`, `divi/image`, `divi/button`,
`divi/code`, `divi/contact-form` + `divi/contact-field`, `divi/placeholder`.

## Attribute shape

Uniform across every module:

```
{ <attrName>: { innerContent: { desktop: { value: … } } } }
```

Images are `{ innerContent: { desktop: { value: { src, alt } } } }`. Rich text lives **inside** the
JSON, not between the delimiters. Common decoration paths:

| What | Path |
|---|---|
| CSS class | `module.decoration.attributes.desktop.value.attributes[] = {id, name:"class", value, adminLabel, targetElement:"main"}` |
| Padding | `module.decoration.spacing.desktop.value.padding{top,bottom,syncVertical,syncHorizontal}` |
| Background | `module.decoration.background.desktop.value.color` / `.image.url` |
| Width on the flex grid | `module.decoration.sizing.desktop.value.flexType` (`24_24`, `12_24`) |

## Serialisation rules

- Encode `<`, `>` and `&` as the JSON unicode escapes `\u003c`, `\u003e`, `\u0026` — the same thing
  WordPress's own block serialiser does. Otherwise content HTML can terminate the block comment.
  Leave slashes unescaped; escape non-ASCII as `\uXXXX`. `tools/build-layout.js` does this in
  `encodeAttrs()`.
- `"builderVersion"` on every block. It is per-block and records when the block was last edited, so
  an export legitimately shows different versions on different blocks.
- Compact JSON, one space after the block name, `/-->` for childless blocks.

## Import envelope

```json
{
  "context": "et_builder_layouts",
  "data": { "1": {
    "ID": 1, "post_content": "…", "post_title": "…", "post_name": "…",
    "post_type": "et_pb_layout", "post_status": "publish",
    "post_meta": { "_et_pb_built_for_post_type": ["page"], "_et_pb_use_divi_5": ["on"] },
    "terms": { "1": {"name":"layout","slug":"layout","taxonomy":"layout_type","parent":0,"all_parents":[],"description":""},
               "2": {"…":"non_global / scope"}, "3": {"…":"regular / module_width"} }
  } },
  "presets": {}, "global_colors": [], "global_variables": [],
  "canvases": [], "images": {}, "thumbnails": []
}
```

- `_et_pb_use_divi_5: "on"` is what marks the content as D5 blocks rather than D4 shortcodes.
- Term slugs are Divi 5's **`non_global`** and **`regular`** — not Divi 4's `not_global` / `on_page`.
- A library *module* item (rather than a layout) instead carries
  `_et_pb_module_type: ["divi/contact-form"]`, `_et_pb_template_type: ["module"]`, `layout_type:
  module`, and wraps its content in `<!-- wp:divi/placeholder -->…<!-- /wp:divi/placeholder -->`.
  Shipping one of these is the cheapest way to hand over a single module without a whole layout.
- Use **root-relative** asset URLs (`/wp-content/plugins/acme-divi-modules/…`) so one file works on
  staging and live. `images: {}` is correct when every image is a plugin asset on disk — there is
  nothing for Divi to sideload.

## ⚠️ Loading a layout onto a page COPIES it

This causes more confusion than any other single fact here.

Re-importing the JSON, or pressing the plugin's *Build the layout* button, updates the **library
item** only. A page that was already built from an earlier copy keeps that copy — silently, with no
front-end signal. Two rounds of "the fields still aren't there" traced to exactly this.

So: after any layout change, say explicitly that the page must be **opened in the builder and the
layout loaded again**. The admin page template ships an audit table that lists every copy on the
install with its module and child-block counts, flagging the stale ones — use it rather than
inferring from the front end.

## ⚠️ Site-wide module presets

An install can carry a default preset per module type (`presets.module["divi/section"].default`),
which silently adds attributes to every new block of that type — 5em section padding, in one real
case. A layout that supplies its own spacing must write it explicitly to override the preset, which
is why the section template writes `0px` rather than omitting padding.
