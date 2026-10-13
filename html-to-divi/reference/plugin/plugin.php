<?php
/**
 * Plugin Name: Acme — Divi Modules
 * Plugin URI:  https://example.com
 * Description: Pixel-exact, editable Divi 5 modules reproducing the Acme page designs.
 * Version:     0.4.6
 * Author:      TechWyse
 * Author URI:  https://www.techwyse.com
 * License:     GPL2
 * Text Domain: acme-divi-modules
 * Update URI:  https://github.com/OWNER/REPO
 *
 * Requires Divi 5.0+ (built and tested against Divi 5.3.3).
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

define( 'ACME_DM_VERSION', '0.4.6' );
define( 'ACME_DM_PATH', plugin_dir_path( __FILE__ ) );
define( 'ACME_DM_URL', plugin_dir_url( __FILE__ ) );
define( 'ACME_DM_JSON_PATH', ACME_DM_PATH . 'modules-json/' );

// --- GitHub auto-updater -----------------------------------------------------
// Fill GH_OWNER / GH_REPO in once the repo exists; define ACME_GH_TOKEN in
// wp-config.php for private-repo downloads.
define( 'ACME_DM_GH_OWNER', 'OWNER' );
define( 'ACME_DM_GH_REPO', 'REPO' );

require_once ACME_DM_PATH . 'includes/class-github-updater.php';

add_action(
	'admin_init',
	function () {
		if ( 'OWNER' === ACME_DM_GH_OWNER ) {
			return; // Repo not wired up yet — skip silently.
		}

		new \AcmeDM\GitHub_Updater(
			[
				'file'    => __FILE__,
				'version' => ACME_DM_VERSION,
				'owner'   => ACME_DM_GH_OWNER,
				'repo'    => ACME_DM_GH_REPO,
				'token'   => defined( 'ACME_GH_TOKEN' ) ? ACME_GH_TOKEN : '',
				'name'    => 'Acme — Divi Modules',
			]
		);
	}
);

// --- Module registration -----------------------------------------------------
// Modules.php only registers a hook; the module classes themselves are required
// inside that callback, once Divi has loaded. Nothing in this file may reference
// an `ET\Builder\...` class at plugin-load time — plugins load before the theme.
require_once ACME_DM_PATH . 'modules/Modules.php';

// --- Tools → Acme Divi Layout ----------------------------------------------
// Builds the whole page as a Divi Library layout. Admin-only and never runs on
// its own, so it cannot affect the front end.
require_once ACME_DM_PATH . 'includes/class-layout-builder.php';
require_once ACME_DM_PATH . 'includes/class-admin-page.php';

\AcmeDM\Admin_Page::init();

/**
 * Warn (rather than fatal) when Divi 5 is not the active theme.
 *
 * The dependency-tree hook simply never fires without Divi 5, so the plugin is
 * inert — but silently inert is confusing, so say so in the admin.
 */
add_action(
	'admin_notices',
	function () {
		if ( function_exists( 'et_builder_d5_enabled' ) ) {
			return;
		}

		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-warning"><p><strong>Acme — Divi Modules:</strong> '
			. 'Divi 5 was not detected, so these modules will not appear in the builder. '
			. 'Activate Divi 5.0 or later.</p></div>';
	}
);

/**
 * Register the Visual Builder bundle with Divi's package build manager.
 *
 * Runs only inside the VB app window; `divi-module-library` and
 * `divi-vendor-wp-hooks` supply the globals our bundle imports as externals.
 */
function select_dm_enqueue_vb_assets() {
	if ( ! function_exists( 'et_builder_d5_enabled' ) || ! et_builder_d5_enabled() ) {
		return;
	}

	if ( ! function_exists( 'et_core_is_fb_enabled' ) || ! et_core_is_fb_enabled() ) {
		return;
	}

	\ET\Builder\VisualBuilder\Assets\PackageBuildManager::register_package_build(
		[
			'name'    => 'acme-dm-builder-bundle-script',
			'version' => ACME_DM_VERSION,
			'script'  => [
				'src'                => ACME_DM_URL . 'scripts/bundle.js',
				'deps'               => [
					'divi-module-library',
					'divi-vendor-wp-hooks',
				],
				'enqueue_top_window' => false,
				'enqueue_app_window' => true,
			],
		]
	);

	\ET\Builder\VisualBuilder\Assets\PackageBuildManager::register_package_build(
		[
			'name'    => 'acme-dm-builder-vb-bundle-style',
			'version' => ACME_DM_VERSION,
			'style'   => [
				'src'                => ACME_DM_URL . 'styles/vb-bundle.css',
				'deps'               => [],
				'enqueue_top_window' => false,
				'enqueue_app_window' => true,
			],
		]
	);
}
add_action( 'divi_visual_builder_assets_before_enqueue_scripts', 'select_dm_enqueue_vb_assets' );

/**
 * Front-end assets: the shared design system lifted from the source page.
 *
 * Loaded on the front end AND inside the VB app window (which is a front-end
 * render), so the builder preview matches the live page.
 */
function select_dm_enqueue_frontend_assets() {
	wp_enqueue_style(
		'acme-dm-system',
		ACME_DM_URL . 'styles/bundle.css',
		[],
		ACME_DM_VERSION
	);

	// Drives the three stateful sections: the topic rail, the FAQ accordion
	// and the locations pager. It no-ops inside the Visual Builder.
	wp_enqueue_script(
		'acme-dm-interactions',
		ACME_DM_URL . 'assets/js/acme-interactions.js',
		[],
		ACME_DM_VERSION,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'select_dm_enqueue_frontend_assets' );
