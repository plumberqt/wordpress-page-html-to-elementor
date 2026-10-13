/**
 * Register every module with Divi's module library (the Visual Builder side).
 *
 * `registerModule(metadata, { defaultAttrs, renderers })` is the whole contract.
 * The metadata is the generated module.json; defaultAttrs is the generated
 * module-default-render-attributes.json; the renderer is the JSX edit component.
 *
 * The CSS imports matter as much as the registration: `system/*.css` lands in
 * the front-end bundle, and any file named `style.css` is routed by webpack's
 * splitChunks to the VB-only bundle (see webpack.config.js).
 */

import { addAction } from '@wordpress/hooks';
import { registerModule } from '@divi/module-library';

import { ExampleParentEdit, ExampleChildEdit } from './edit.example';

import metaParent from './components/example-parent/module.json';
import defParent from './components/example-parent/module-default-render-attributes.json';
import metaChild from './components/example-child/module.json';
import defChild from './components/example-child/module-default-render-attributes.json';

// The shared design system, lifted from the source design.
import './system/tokens.css';
import './system/sections.css';

// Builder-only overrides. The `style.css` filename is what routes this to
// vb-bundle.css instead of the front-end bundle.
import './system/style.css';

const modules = [
  [metaParent, defParent, ExampleParentEdit],
  [metaChild, defChild, ExampleChildEdit],
];

addAction('divi.moduleLibrary.registerModuleLibraryStore.after', 'acme', () => {
  modules.forEach(([metadata, defaultAttrs, editComponent]) => {
    registerModule(metadata, {
      defaultAttrs,
      renderers: { edit: editComponent },
    });
  });
});
