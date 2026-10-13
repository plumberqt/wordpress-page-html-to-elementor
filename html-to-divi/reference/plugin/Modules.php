<?php
/**
 * Register every module with Divi's dependency tree.
 *
 * ⚠️ THE RULE THAT MUST NOT BE BROKEN
 *
 * Module classes are required INSIDE the hook callback, never at plugin-load
 * time. WordPress loads plugins from wp-settings.php BEFORE the theme, and Divi
 * is a theme — so at plugin-load time no `ET\Builder\...` class exists yet.
 * Declaring `class X implements DependencyInterface` at that moment is an
 * immediate site-wide fatal, admin included, not a graceful failure.
 *
 * By the time `divi_module_library_modules_dependency_tree` fires, Divi is fully
 * loaded. The `interface_exists()` guard keeps a Divi-less site alive.
 *
 * Adding a module = adding its name to the list below.
 *
 * @package AcmeDM\Modules
 */

namespace AcmeDM\Modules;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * Module class names, in registration order. Each is
 * modules/<Name>/<Name>.php declaring \AcmeDM\Modules\<Name>\<Name>.
 *
 * @return string[]
 */
function acme_dm_module_classes() {
	return [
		'ExampleParent',
		'ExampleChild',
	];
}

add_action(
	'divi_module_library_modules_dependency_tree',
	function ( $dependency_tree ) {
		// Divi 5 only. Without the interface there is nothing to register
		// against, and declaring the classes would fatal.
		if ( ! interface_exists( '\ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface' ) ) {
			return;
		}

		require_once ACME_DM_PATH . 'modules/Shared/ModuleTrait.php';
		require_once ACME_DM_PATH . 'modules/Shared/Render.php';

		foreach ( acme_dm_module_classes() as $name ) {
			require_once ACME_DM_PATH . "modules/{$name}/{$name}.php";

			$class = "\\AcmeDM\\Modules\\{$name}\\{$name}";

			$dependency_tree->add_dependency( new $class() );
		}
	}
);
