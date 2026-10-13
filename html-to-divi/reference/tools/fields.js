/**
 * Field builders for Divi 5 module.json files.
 *
 * A Divi 5 attribute is ~30 lines of JSON for what is conceptually "a text box
 * labelled X that renders into <span class="Y">". Across fourteen modules that
 * is thousands of lines nobody can diff. These helpers emit the same shapes
 * Divi's own example modules use, from a one-line declaration.
 */

let priority = 0;
const nextPriority = () => (priority += 10);

/** Reset ordering between modules so priorities stay small and readable. */
const resetPriority = () => { priority = 0; };

/** The standard `module` attribute every module carries. */
const moduleAttr = (adminLabel) => ({
  type: 'object',
  selector: '{{selector}}',
  ...(adminLabel
    ? { default: { meta: { adminLabel: { desktop: { value: adminLabel } } } } }
    : {}),
  styleProps: { border: { important: true } },
  settings: {
    meta: { adminLabel: {} },
    advanced: { link: {}, text: {}, htmlAttributes: {} },
    decoration: {
      attributes: {},
      background: {},
      sizing: {},
      spacing: {},
      border: {},
      boxShadow: {},
      filters: {},
      transform: {},
      animation: {},
      overflow: {},
      disabledOn: {},
      transition: {},
      position: {},
      zIndex: {},
      scroll: {},
      sticky: {},
    },
  },
});

/**
 * A rendered text element.
 *
 * @param {string} name  Attribute name; also drives the CSS class.
 * @param {string} label Field label in the builder.
 * @param {object} opts  tag, cls, block (root class), heading, rich, font.
 */
const text = (name, label, opts = {}) => {
  const {
    block,
    tag = 'span',
    cls = `${block}__${name}`,
    heading = false,
    rich = false,
    font = true,
    description = '',
  } = opts;

  const p = nextPriority();

  return {
    type: 'object',
    selector: `{{selector}} .${cls}`,
    tagName: tag,
    attributes: { class: cls },
    inlineEditor: rich ? 'richText' : 'plainText',
    childrenSanitizer: 'et_core_esc_previously',
    ...(heading ? { elementType: 'heading' } : {}),
    ...(heading
      ? {
        styleProps: {
          font: { important: { font: { desktop: { value: { color: true } } } } },
        },
      }
      : {}),
    ...(rich
      ? {
        styleProps: {
          bodyFont: { selectors: { desktop: { value: `{{selector}} .${cls}` } } },
        },
      }
      : {}),
    settings: {
      innerContent: {
        groupType: 'group-item',
        item: {
          groupSlug: MAIN_GROUP,
          priority: p,
          render: true,
          attrName: `${name}.innerContent`,
          label,
          ...(description ? { description } : {}),
          features: { sticky: false, dynamicContent: false },
          component: { name: rich ? 'divi/richtext' : 'divi/text', type: 'field' },
        },
      },
      decoration: font
        ? (rich
          ? { bodyFont: { attrName: `${name}.decoration.bodyFont` } }
          : {
            font: {
              priority: p,
              component: {
                props: {
                  groupLabel: `${label} Text`,
                  fieldLabel: label,
                  ...(heading ? {} : { fields: { headingLevel: { render: false } } }),
                },
              },
            },
          })
        : {},
    },
  };
};

/** A rendered rich-text element. */
const richtext = (name, label, opts = {}) => text(name, label, { ...opts, rich: true });

/**
 * A value with no element of its own — a URL, a percentage, a count.
 * Read directly off attrs in the render callback.
 */
const value = (name, label, opts = {}) => {
  const { description = '', responsive = false } = opts;

  return {
    type: 'object',
    settings: {
      innerContent: {
        groupType: 'group-item',
        item: {
          groupSlug: MAIN_GROUP,
          priority: nextPriority(),
          render: true,
          attrName: `${name}.innerContent`,
          label,
          ...(description ? { description } : {}),
          features: { sticky: false, responsive, dynamicContent: false },
          component: { name: 'divi/text', type: 'field' },
        },
      },
    },
  };
};

/** An image field rendered through Divi's upload control. */
const image = (name, label, opts = {}) => {
  const { block, cls = `${block}__${name}`, description = '' } = opts;

  return {
    type: 'object',
    selector: `{{selector}} .${cls} img`,
    elementType: 'image',
    settings: {
      innerContent: {
        groupType: 'group-items',
        items: {
          src: {
            groupSlug: 'contentMedia',
            attrName: `${name}.innerContent`,
            subName: 'src',
            priority: nextPriority(),
            render: true,
            label,
            ...(description ? { description } : {}),
            features: { sticky: false, dynamicContent: false },
            component: {
              name: 'divi/upload',
              type: 'field',
              props: {
                syncImageData: { src: true, id: true, alt: true, titleText: true },
              },
            },
          },
        },
      },
    },
  };
};

/** The composite group an image field needs to appear under. */
/**
 * The content panel's main group.
 *
 * Divi does not create a group just because attributes claim to belong to one:
 * the group has to be declared here, and its label lives on the declaration. An
 * item then points at it by `groupSlug` — the key below — which is exactly how
 * `divi/text`, `divi/heading` and `divi/button` are built. Referencing an
 * undeclared name instead (this file used to say `groupName: 'mainContent'`)
 * still draws a group box in the panel, but an unlabelled one.
 */
const MAIN_GROUP = 'contentText';

const mainGroup = (groupLabel = 'Content') => ({
  [MAIN_GROUP]: {
    panel: 'content',
    priority: 5,
    groupName: 'text',
    multiElements: true,
    component: { name: 'divi/composite', props: { groupLabel } },
  },
});

const mediaGroup = (groupLabel = 'Image') => ({
  contentMedia: {
    panel: 'content',
    priority: 30,
    groupName: 'contentMedia',
    multiElements: true,
    component: { name: 'divi/composite', props: { groupLabel } },
  },
});

module.exports = {
  moduleAttr, text, richtext, value, image, mainGroup, mediaGroup, resetPriority,
};
