# Divi 5 custom module API — the real contract

Divi 5 modules do **NOT** use `ET_Builder_Module`. That is the Divi 4 backward-compatibility path,
and several community doc sites (e.g. `16wells.github.io/divi-docs`) describe it as if it were the
Divi 5 API. Building against it wastes an entire plugin.

**Ground truth to clone:** `github.com/elegantthemes/d5-extension-example-modules` — official and
working. Official tutorial: https://dev.elegantthemes.com/docs/tutorials/module/beginner/create-simple-quick-module

## Registration

**PHP** — a class implementing
`ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface` with a `load()` method,
registered via `add_action('divi_module_library_modules_dependency_tree', …)` →
`$tree->add_dependency(new X())`. On `init`, `load()` calls
`ET\Builder\Packages\ModuleLibrary\ModuleRegistration::register_module($json_folder, ['render_callback' => …])`.

Render uses `Module::render()`, `HTMLUtility::render()`, `ElementComponents::component()`,
`BlockParserStore::get_parent()`. Supporting traits: RenderCallback, ModuleClassnames, ModuleStyles,
ModuleScriptData, CustomCss — all of which live once in `plugin/Shared/ModuleTrait.php` rather than
being repeated per module.

**JS** — `addAction('divi.moduleLibrary.registerModuleLibraryStore.after', ns, () => registerModule(metadata, mod))`.
A Visual Builder component is **mandatory**; there is no PHP-only module. Webpack externals map
`@divi/module-library` → `['divi','moduleLibrary']`, `@wordpress/hooks` → `['vendor','wp','hooks']`,
`react` → `['vendor','React']`.

**Assets** — `PackageBuildManager::register_package_build()` on the
`divi_visual_builder_assets_before_enqueue_scripts` hook, deps `divi-module-library` and
`divi-vendor-wp-hooks`.

**Fields** are declarative in `module.json`: `divi/text`, `divi/textarea`, `divi/richtext`,
`divi/upload`, `divi/select`, `divi/range`, `divi/icon-picker`, `divi/color-picker`,
`divi/composite` (groups), plus group components `divi/border`, `divi/spacing`, `divi/box-shadow`,
`divi/filters`.

## ⚠️ Load order — the rule that must not be broken

WordPress loads **plugins before the theme**, and Divi is a theme. At plugin-load time no
`ET\Builder\…` class exists. A module class declared then — `class X implements DependencyInterface`
— is an **immediate site-wide fatal**, not a graceful failure. It fatals in `wp-settings.php`, so
the admin goes down with the front end. *This took a live site down once already.*

`require_once` every module class **inside** the `divi_module_library_modules_dependency_tree`
callback, and guard the callback with `interface_exists()`. See `plugin/Modules.php`.

Divi's own example plugin hides this behind Composer PSR-4 autoloading (lazy by nature), so copying
its structure without Composer reintroduces the bug.

## No repeater field

Divi 5 has none. Repeated content is **parent + child modules**: the parent's module.json declares
`childModuleName`, `childModuleTitle`, `childrenName: […]`; the child sets
`category: "child-module"`. The parent's render callback places child output by echoing `$content`;
the JSX side uses `<ChildModulesContainer ids={childrenIds} />`.

This is better than a repeater would have been — each child is individually selectable and
reorderable in the layers panel — at the cost of entering shared values (a CTA label) once per
child.

A child module is a **single DOM box**. A design where one item appears in two places (a tab rail
row in column 1 plus its panel in column 2) needs the second part absolutely positioned out of the
child into the other column. Which runs straight into:

## ⚠️ Divi core positions every module

Divi's core CSS includes:

```css
.et_pb_all_tabs, .et_pb_circle_counter_inner, .et_pb_module, … { position: relative; … }
```

So anything of yours that is absolutely positioned **across a module boundary** resolves against the
module, not the ancestor you wrote it for. Symptom seen in the field: a topic panel written as
`left: 398px; right: 0` against the parent's grid resolved against a 330px child instead — **zero
width**, and vertically offset to whichever child was open. The fix is to opt that box out, with
specificity that beats Divi's grouped selector rather than `!important`:

```css
.acme_topic.et_pb_module { position: static; }
```

Reproduce this rule in the preview generator or the preview will look right while the site is broken.

## ⚠️ Settings panels — two failures that are invisible from the front end

Both produce a module that registers, lists correctly in the layers panel, and renders perfectly on
the page — while being uneditable or unlabelled in the builder. No machine-side check catches
either; only opening the Visual Builder does.

**1. Every module needs all three panels declared**, child modules included:

```json
"settings": { "content": "auto", "design": "auto", "advanced": "auto" }
```

Declaring only `advanced: "auto"` for a child — which reads plausible, since a child has no
structural content of its own — gives it a **completely empty settings panel**: its attributes point
at a content group that nothing created, so the fields have nowhere to be drawn. Divi's own child
module, `divi/contact-field`, declares a full Content panel.

**2. A group must be declared, and its label lives on the declaration.** An attribute claims its
group with `item.groupSlug`, which must match a key in `settings.groups`:

```json
"groups": {
  "contentText": {
    "panel": "content", "priority": 5, "groupName": "text", "multiElements": true,
    "component": { "name": "divi/composite", "props": { "groupLabel": "Content" } }
  }
}
```

Point an attribute at a name nothing declares and Divi **still draws the group box — with no label
on it**. There is no built-in group called `mainContent`; that name looks reasonable and is not
real. `divi/text`, `divi/heading`, `divi/button` and `divi/contact-field` all declare theirs.

`tools/build-module-json.js` fails the build if any attribute references an undeclared group, which
closes the half a machine can check. The label text itself still needs one look in the builder.

## Why the module.json generator exists

A single Divi 5 attribute is ~30 lines of JSON for what is conceptually "a text box labelled X
rendering into `<span class="Y">`". Across fifteen modules that is thousands of lines nobody can
review or diff, and every one of them is a place for the two panels above to go wrong silently.

`tools/modules.spec.js` declares each field in one line; `tools/build-module-json.js` generates
`src/components/<slug>/module.json` plus `module-default-render-attributes.json`. The generated
files are build output — never hand-edit them, and never let a module.json become the source of
truth for a field.
