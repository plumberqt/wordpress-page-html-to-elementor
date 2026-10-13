// External dependencies.
import React, { Fragment } from 'react';

// Divi dependencies.
import {
  StyleContainer,
  CssStyle,
  textOptionsClassnames,
} from '@divi/module';

/**
 * Classnames shared by every Acme module.
 *
 * `acme_module` is the hook the shared theme-isolation CSS keys off, so it
 * must land on every module wrapper. Mirrors ModuleTrait::module_classnames().
 */
export const moduleClassnames = ({ classnamesInstance, attrs }) => {
  classnamesInstance.add('acme_module', true);
  classnamesInstance.add(textOptionsClassnames(attrs?.module?.advanced?.text));
};

/**
 * Script data shared by every Acme module.
 */
export const ModuleScriptData = ({ elements }) => (
  <Fragment>
    {elements.scriptData({ attrName: 'module' })}
  </Fragment>
);

/**
 * Build a ModuleStyles component for a module.
 *
 * Mirrors ModuleTrait::module_styles(): style the module wrapper, then
 * each named attribute, then custom CSS last so it can override.
 *
 * @param {string[]} attrNames           Attributes needing generated styles.
 * @param {object}   cssFields           Custom CSS field map.
 * @param {string}   textSelectorSuffix  Where Divi's text-colour option applies.
 */
export const makeStyles = (attrNames = [], cssFields = {}, textSelectorSuffix = '') => ({
  attrs,
  elements,
  settings,
  orderClass,
  mode,
  state,
  noStyleTag,
}) => (
  <StyleContainer mode={mode} state={state} noStyleTag={noStyleTag}>
    {elements.style({
      attrName: 'module',
      styleProps: {
        disabledOn: {
          disabledModuleVisibility: settings?.disabledModuleVisibility,
        },
        advancedStyles: [
          {
            componentName: 'divi/text',
            props: {
              selector: `${orderClass}${textSelectorSuffix}`,
              attr: attrs?.module?.advanced?.text,
            },
          },
        ],
      },
    })}

    {attrNames.map((attrName) => (
      <Fragment key={attrName}>
        {elements.style({ attrName })}
      </Fragment>
    ))}

    <CssStyle
      selector={orderClass}
      attr={attrs.css}
      cssFields={cssFields}
    />
  </StyleContainer>
);

/**
 * Label custom-CSS fields pulled straight from a module.json.
 *
 * @param {object} fields Raw customCssFields.
 * @param {object} labels subName → label.
 */
export const labelCssFields = (fields, labels) => {
  Object.keys(labels).forEach((key) => {
    if (fields[key]) {
      fields[key].label = labels[key];
    }
  });

  return { ...fields };
};
