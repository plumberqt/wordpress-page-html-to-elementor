/**
 * Every module declared compactly. THIS is the file you edit to change fields;
 * src/components/<slug>/module.json is generated from it and must never be
 * hand-edited.
 *
 * Why a generator: one Divi 5 attribute is ~30 lines of JSON for what is
 * conceptually "a text box labelled X rendering into <span class="Y">". Across
 * fifteen modules that is thousands of lines nobody can review or diff. Here it
 * is one line each.
 *
 * Shown below: one parent + one child, which is the pattern for every repeated
 * element in a design — Divi 5 has no repeater field.
 *
 * Builders (see fields.js):
 *   text(name, label, { block, tag, cls, heading, description })
 *   richtext(name, label, { block, ... })     — wraps content in <p> in the VB
 *   value(name, label, { description })       — no element of its own: URLs,
 *                                               counts, percentages
 *   image(name, label, { block, cls })        — declares its own media group
 */

const { moduleAttr, text, richtext, value, image, mediaGroup, resetPriority } = require('./fields');

const spec = () => {
  const modules = [];

  const add = (def) => {
    resetPriority();
    modules.push(def(def));
    return modules[modules.length - 1];
  };

  // --- A parent: owns the section shell and hosts the children --------------
  add(() => {
    const block = 'acme_parent';

    return {
      slug: 'example-parent',
      name: 'acme/example-parent',
      title: 'Acme Example Parent',
      titles: 'Acme Example Parents',
      className: block,
      category: 'module',
      adminLabel: 'Example Parent',
      // Pairs the child to this parent in the builder. The child must exist.
      child: { name: 'acme/example-child', title: 'Item' },
      // Names the Content group in the settings panel; defaults to 'Content'.
      contentLabel: 'Section Content',
      headingLevel: 'h2',
      attributes: {
        module: moduleAttr('Example Parent'),
        eyebrow: text('eyebrow', 'Eyebrow', { block, tag: 'span' }),
        title: text('title', 'Title', { block, tag: 'h2', heading: true }),
        body: richtext('body', 'Body', { block }),
        buttonText: text('buttonText', 'Button Text', { block, cls: `${block}__button-label`, tag: 'span' }),
        buttonUrl: value('buttonUrl', 'Button URL'),
      },
      cssFields: { title: ` .${block}__title`, body: ` .${block}__body` },
      // Front-end defaults, so a freshly dropped module already looks like the
      // design instead of showing placeholder text.
      defaults: {
        eyebrow: 'What we do',
        title: 'A headline lifted from the design',
        body: '<p>Body copy lifted from the design, not paraphrased.</p>',
        buttonText: 'Book a Call',
        buttonUrl: '#',
      },
    };
  });

  // --- A child: one repeated item ------------------------------------------
  add(() => {
    const block = 'acme_child';

    return {
      slug: 'example-child',
      name: 'acme/example-child',
      title: 'Item',
      titles: 'Items',
      className: block,
      category: 'child-module',
      adminLabel: 'Item',
      attributes: {
        module: moduleAttr('Item'),
        photo: image('photo', 'Photo', { block }),
        label: text('label', 'Label', { block, tag: 'span' }),
        detail: text('detail', 'Detail', { block, tag: 'span' }),
        linkUrl: value('linkUrl', 'Link URL'),
      },
      // An image field points at a media group; declaring it here is what gives
      // that group its label. Group labels live on the declaration — see
      // reference/divi5-api.md.
      groups: mediaGroup('Photo'),
      defaults: {
        label: 'Item label',
        detail: 'Item detail',
        linkUrl: '#',
      },
    };
  });

  return modules;
};

// The generator consumes the array, not the factory.
module.exports = spec();
