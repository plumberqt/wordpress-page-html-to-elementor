/**
 * Visual Builder edit components.
 *
 * A VB component is MANDATORY — there is no PHP-only Divi 5 module. Each one
 * mirrors its PHP render_callback element for element. Keeping them in one file
 * makes that symmetry reviewable: when the two drift, the builder preview stops
 * matching the live page, which is the most likely way this plugin can go wrong.
 */

import React from 'react';

import { ChildModulesContainer, ModuleContainer } from '@divi/module';

import { moduleClassnames, ModuleScriptData, makeStyles } from './shared';

// --- helpers ----------------------------------------------------------------

/** The wrapper every module shares. */
const Shell = ({ props, styles, tag, children }) => (
  <ModuleContainer
    attrs={props.attrs}
    elements={props.elements}
    id={props.id}
    name={props.name}
    stylesComponent={styles}
    classnamesFunction={moduleClassnames}
    scriptDataComponent={ModuleScriptData}
    {...(tag ? { tag } : {})}
  >
    {props.elements.styleComponents({ attrName: 'module' })}
    {children}
  </ModuleContainer>
);

/** Read a plain (non-rendered) attribute value — URLs, counts, percentages. */
const val = (attrs, name, fallback = '') => attrs?.[name]?.innerContent?.desktop?.value ?? fallback;

/**
 * Build an edit component from a render function.
 *
 * @param {string[]} styleAttrs Attributes needing generated styles — must match
 *                             the module's PHP `style_attrs()`.
 * @param {object}   cssFields  Custom CSS field map, matching module.json.
 * @param {string}   textSuffix Where Divi's text-colour option applies.
 * @param {Function} render     (e, props) => JSX, where `e(name)` renders an
 *                              editable attribute.
 */
const edit = (styleAttrs, cssFields, textSuffix, render, tag) => {
  const styles = makeStyles(styleAttrs, cssFields, textSuffix);

  return (props) => {
    const e = (attrName) => props.elements.render({ attrName });

    return (
      <Shell props={props} styles={styles} tag={tag}>
        {render(e, props)}
      </Shell>
    );
  };
};

// --- A parent ---------------------------------------------------------------
// `childrenIds` + <ChildModulesContainer> is where the children are drawn.

export const ExampleParentEdit = edit(
  ['eyebrow', 'title', 'body', 'buttonText'],
  { title: ' .acme_parent__title', body: ' .acme_parent__body' },
  '',
  (e, { attrs, childrenIds }) => (
    <div className="acme_parent__inner">
      {e('eyebrow')}
      {e('title')}
      <div className="acme_parent__body">{e('body')}</div>

      <div className="acme_parent__items">
        <ChildModulesContainer ids={childrenIds} />
      </div>

      <a className="acme_parent__button" href={val(attrs, 'buttonUrl', '#')}>
        {e('buttonText')}
      </a>
    </div>
  ),
);

// --- A child ----------------------------------------------------------------

export const ExampleChildEdit = edit(
  ['label', 'detail'],
  {},
  '',
  (e, { attrs }) => (
    <div className="acme_child__row">
      <div className="acme_child__photo">{e('photo')}</div>
      <span className="acme_child__label">{e('label')}</span>
      <span className="acme_child__detail">{e('detail')}</span>
      <a className="acme_child__link" href={val(attrs, 'linkUrl', '#')} />
    </div>
  ),
);
