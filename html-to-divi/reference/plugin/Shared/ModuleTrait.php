<?php
/**
 * Shared plumbing for every Acme module.
 *
 * Divi's own example extension repeats classnames/styles/script-data/custom-css
 * boilerplate per module. Across fourteen modules that is a lot of identical
 * code to keep in sync, so it lives here once and is parameterised by three
 * static methods each module declares:
 *
 *   block_name()  — the registered block name, e.g. 'acme/faq'
 *   json_slug()   — the modules-json/ folder name, e.g. 'faq'
 *   style_attrs() — attribute names that need generated styles
 *
 * @package AcmeDM\Modules\Shared
 */

namespace AcmeDM\Modules\Shared;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Packages\Module\Options\Text\TextClassnames;
use ET\Builder\Packages\Module\Options\Element\ElementScriptData;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\FrontEnd\Module\Style;

trait ModuleTrait {

	/**
	 * Register the module once WordPress is initialised.
	 *
	 * @return void
	 */
	public function load() {
		$json_path = ACME_DM_JSON_PATH . static::json_slug() . '/';
		$class     = static::class;

		add_action(
			'init',
			function () use ( $json_path, $class ) {
				ModuleRegistration::register_module(
					$json_path,
					[
						'render_callback' => [ $class, 'render_callback' ],
					]
				);
			}
		);
	}

	/**
	 * Attribute names that get generated styles. Overridden per module.
	 *
	 * @return array
	 */
	public static function style_attrs() {
		return [];
	}

	/**
	 * Selector suffix that Divi's text-colour option should apply to.
	 *
	 * @return string
	 */
	public static function text_selector_suffix() {
		return '';
	}

	/**
	 * Module classnames.
	 *
	 * Every module also gets `acme_module`, which is what the shared
	 * theme-isolation CSS keys off — see src/system/tokens.css.
	 *
	 * @param array $args Function arguments.
	 *
	 * @return void
	 */
	public static function module_classnames( $args ) {
		$classnames_instance = $args['classnamesInstance'];
		$attrs               = $args['attrs'];

		$classnames_instance->add( 'acme_module', true );

		$text_options_classnames = TextClassnames::text_options_classnames( $attrs['module']['advanced']['text'] ?? [] );

		if ( $text_options_classnames ) {
			$classnames_instance->add( $text_options_classnames, true );
		}
	}

	/**
	 * Custom CSS fields declared in the module's module.json.
	 *
	 * @return array
	 */
	public static function custom_css() {
		$block = \WP_Block_Type_Registry::get_instance()->get_registered( static::block_name() );

		return $block->customCssFields ?? [];
	}

	/**
	 * Emit generated styles for the module and each of its styled attributes.
	 *
	 * @param array $args Function arguments.
	 *
	 * @return void
	 */
	public static function module_styles( $args ) {
		$attrs    = $args['attrs'] ?? [];
		$elements = $args['elements'];
		$settings = $args['settings'] ?? [];

		$text_selector = $args['orderClass'] . static::text_selector_suffix();

		$styles = [
			$elements->style(
				[
					'attrName'   => 'module',
					'styleProps' => [
						'disabledOn'     => [
							'disabledModuleVisibility' => $settings['disabledModuleVisibility'] ?? null,
						],
						'advancedStyles' => [
							[
								'componentName' => 'divi/text',
								'props'         => [
									'selector' => $text_selector,
									'attr'     => $attrs['module']['advanced']['text'] ?? [],
								],
							],
						],
					],
				]
			),
		];

		foreach ( static::style_attrs() as $attr_name ) {
			$styles[] = $elements->style( [ 'attrName' => $attr_name ] );
		}

		$styles[] = CssStyle::style(
			[
				'selector'  => $args['orderClass'],
				'attr'      => $attrs['css'] ?? [],
				'cssFields' => static::custom_css(),
			]
		);

		Style::add(
			[
				'id'            => $args['id'],
				'name'          => $args['name'],
				'orderIndex'    => $args['orderIndex'],
				'storeInstance' => $args['storeInstance'],
				'styles'        => $styles,
			]
		);
	}

	/**
	 * Element script data (link options, responsive/hover decoration).
	 *
	 * @param array $args Function arguments.
	 *
	 * @return void
	 */
	public static function module_script_data( $args ) {
		$id             = $args['id'] ?? '';
		$selector       = $args['selector'] ?? '';
		$attrs          = $args['attrs'] ?? [];
		$store_instance = $args['storeInstance'] ?? null;

		ElementScriptData::set(
			[
				'id'            => $id,
				'selector'      => $selector,
				'attrs'         => array_merge(
					$attrs['module']['decoration'] ?? [],
					[
						'link' => $attrs['module']['advanced']['link'] ?? [],
					]
				),
				'storeInstance' => $store_instance,
			]
		);
	}
}
