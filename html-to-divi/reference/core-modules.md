# Writing Divi's *own* modules into a layout

Sometimes the design's section is best served by a native Divi module — a contact form above all,
because replacing a working form means replacing working submission handling, spam protection and
email routing. Composing your modules around a native one is the right call. Getting its block
attributes right is where the time goes.

## Two sources of truth, in this order

**1. Divi's generated schema, served over HTTP.** Divi ships a `module.json` per module inside the
theme, and those files are readable without a login:

```
/wp-content/themes/Divi/includes/builder-5/visual-builder/packages/module-library/src/
  components/<slug>/module.json
  components/<slug>/module-default-render-attributes.json
```

Try `contact-field`, `contact-form`, `column`, `row`, `section`, `heading`, `text`, `button`. Each
file gives the block name, its `d4Shortcode`, the attribute tree, every `attrName`, and — in the
defaults file — the values Divi itself writes. `/wp-json/wp/v2/block-types` returns 401 for
anonymous requests, so this is the way in.

**2. An export of the equivalent working module from the client's own library.** The schema gives
attribute *paths*; it does not tell you which of them Divi actually writes, and it will mislead you
if you assume. Ask for it — Divi → Divi Library → the module → Export. It also hands over the
client's working routing config, which you would otherwise have to ask for field by field.

Both were needed on the build this skill came from. The schema said field width lived on the flex
grid; the export showed Divi does not use that at all.

## Contact form — verified shapes

Fields are `divi/contact-field` **children** of `divi/contact-form`. A form block with no children
imports as a bare submit button — which reads exactly like "the import dropped my fields".

```html
<!-- wp:divi/contact-form {…form attrs…} -->
<!-- wp:divi/contact-field {"fieldItem":{"advanced":{"fullwidth":{"desktop":{"value":"on"}},
  "id":{"desktop":{"value":"firstname"}}},"innerContent":{"desktop":{"value":"First Name *"}}},
  "module":{"decoration":{"attributes":{"desktop":{"value":{"attributes":[{"id":"ab12cd34ef",
  "name":"class","value":"width-50","adminLabel":"","targetElement":"main"}]}}}}},
  "builderVersion":"5.3.3"} /-->
<!-- /wp:divi/contact-form -->
```

Field attributes:

| Attribute | Notes |
|---|---|
| `fieldItem.innerContent` | the label, which Divi also uses as the placeholder |
| `fieldItem.advanced.id` | `firstname`, `email`, … — these are the names submissions carry, so match the client's existing form |
| `fieldItem.advanced.type` | `input` (default, omit it) \| `email` \| `text` (textarea) \| `checkbox` \| `radio` \| `select` |
| `fieldItem.advanced.required` | defaults to `on` |
| `fieldItem.advanced.fullwidth` | **`"on"` on every field** |

**There is no half-width field setting.** `fullwidth` is `on` even for fields that sit two-up; the
site's two-up rows come from a **`width-50` CSS class** on the field, added via
`module.decoration.attributes` and styled by the theme (or by you). Divi 4's `.width-50` class is
often assumed dead in Divi 5 — it isn't, it is just no longer Divi's own; it's a convention the
theme carries. Size fields from your own CSS, keyed on that class *and* on the input's
`data-original_id` (which Divi renders from the field ID) so the layout holds either way.

Form-level attributes:

| Attribute | Notes |
|---|---|
| `title.innerContent` | set `""` if the design has its own header module |
| `email.advanced.receiver` | recipient. Copy the client's, don't leave it to default to the admin address |
| `email.innerContent` | message template using `%%fieldid%%` tokens |
| `button.innerContent` | `{ text: "Submit" }` — an object, not a string |
| `module.advanced.successMessage` | |
| `redirect.advanced.useRedirect` + `redirect.innerContent` | thank-you page |
| `module.advanced.spamProtection` | `{enabled, useBasicCaptcha, provider, account}` |

**The captcha:** Divi ships `useBasicCaptcha: "on"`, so an untouched imported form shows an
arithmetic box that no design asked for. Set it `off` if the client's other forms don't show one —
and note that **hiding it with CSS instead would leave a form nobody can submit**, since Divi
validates the answer server-side. If it stays on, style it into the design rather than hiding it.

## Front-end DOM to style against

Divi renders the form as `.et_pb_contact_form_container > .et_pb_contact > form.et_pb_contact_form`,
each field as `.et_pb_contact_field.et_pb_module.et_flex_module.et_flex_column_24_24`, and the
captcha + submit inside `.et_contact_bottom_container`.

Watch the flex axis when restyling: `.et_contact_bottom_container` is a child of the form's
row-direction flex, so `flex: 0 0 100%` there is a *width*. Make that container stack and the same
declaration on its own children becomes a *height* — which stretches the submit button to the full
height of the container. Use `flex: 0 0 auto` plus an explicit `width` inside a stacked container.
