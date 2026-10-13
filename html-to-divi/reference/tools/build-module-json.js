/**
 * Emit module.json + module-default-render-attributes.json for every module in
 * modules.spec.js.
 *
 * Runs before webpack (see the build script), so the copy plugin picks the
 * fresh files up on the same pass.
 */

const fs = require('fs');
const path = require('path');

const spec = require('./modules.spec');
const { mainGroup } = require('./fields');

const componentsDir = path.resolve(__dirname, '..', 'src', 'components');

const write = (file, data) => {
  fs.mkdirSync(path.dirname(file), { recursive: true });
  fs.writeFileSync(file, `${JSON.stringify(data, null, 2)}\n`);
};

spec.forEach((m) => {
  const dir = path.join(componentsDir, m.slug);

  const metadata = {
    name: m.name,
    d4Shortcode: '',
    title: m.title,
    titles: m.titles,
    moduleClassName: m.className,
    moduleOrderClassName: m.className,
    category: m.category,
    ...(m.child
      ? {
        childModuleName: m.child.name,
        childModuleTitle: m.child.title,
        childrenName: [m.child.name],
      }
      : {}),
    attributes: m.attributes,
    customCssFields: Object.fromEntries(
      Object.entries(m.cssFields || {}).map(([subName, selectorSuffix]) => [
        subName,
        { subName, selectorSuffix },
      ]),
    ),
    settings: {
      // Every module needs all three panels declared, child modules included.
      // A child that declares only `advanced: 'auto'` still appears in the
      // layers panel and still renders on the front end, but selecting it opens
      // an EMPTY settings panel: its attributes point at a content group
      // (`groupName: 'mainContent'`) that nothing ever created, so there is
      // nowhere for the fields to be drawn. Divi's own child module
      // (`divi/contact-field`) declares a full Content panel the same way.
      content: 'auto',
      design: 'auto',
      advanced: 'auto',
      // The main content group must be declared or its box draws without a
      // label; `contentLabel` names it per module. Groups the spec declares
      // itself (media, background) merge on top.
      groups: {
        ...mainGroup(m.contentLabel),
        ...(m.groups || {}),
      },
    },
  };

  // An attribute pointing at a group nobody declared does not fail anywhere:
  // Divi draws the group box, just without a label, and the module looks fine on
  // the front end. That cost two rounds of "the panel is empty" to find, so the
  // build refuses to emit it.
  const declared = new Set(Object.keys(metadata.settings.groups));
  const referenced = new Set(
    (JSON.stringify(metadata.attributes).match(/"groupSlug":"[^"]+"/g) || []).map((s) =>
      s.slice('"groupSlug":"'.length, -1)
    )
  );

  referenced.forEach((group) => {
    if (!declared.has(group)) {
      throw new Error(
        `${m.name}: attribute points at group "${group}", which the module does not declare ` +
          `(declared: ${[...declared].join(', ') || 'none'})`
      );
    }
  });

  write(path.join(dir, 'module.json'), metadata);

  // Default attribute values, in the shape Divi expects.
  const defaults = {
    module: {
      meta: { adminLabel: { desktop: { value: m.adminLabel } } },
    },
  };

  Object.entries(m.defaults || {}).forEach(([name, value]) => {
    defaults[name] = { innerContent: { desktop: { value } } };
  });

  if (m.headingLevel) {
    defaults.title = defaults.title || {};
    defaults.title.decoration = {
      font: { font: { desktop: { value: { headingLevel: m.headingLevel } } } },
    };
  }

  write(path.join(dir, 'module-default-render-attributes.json'), defaults);
});

// eslint-disable-next-line no-console
console.log(`Generated module.json for ${spec.length} modules.`);
