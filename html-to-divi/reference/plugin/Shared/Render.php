<?php
/**
 * Rendering helpers shared by every Acme module.
 *
 * @package AcmeDM\Modules\Shared
 */

namespace AcmeDM\Modules\Shared;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\Module\Module;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\Packages\Module\Options\Element\ElementComponents;

class Render {

	/**
	 * Wrap a module's inner HTML in Divi's standard module shell.
	 *
	 * Collapses the ~40 lines of Module::render() boilerplate that would
	 * otherwise be copy-pasted into every render callback.
	 *
	 * @param string    $class      Module class name (provides the callbacks).
	 * @param array     $attrs      Block attributes.
	 * @param \WP_Block $block      Parsed block.
	 * @param object    $elements   ModuleElements instance.
	 * @param string    $inner_html The module's own markup.
	 *
	 * @return string
	 */
	public static function wrap( $class, $attrs, $block, $elements, $inner_html ) {
		$parent       = BlockParserStore::get_parent( $block->parsed_block['id'], $block->parsed_block['storeInstance'] );
		$parent_attrs = $parent->attrs ?? [];

		$children_ids = ! empty( $block->parsed_block['innerBlocks'] )
			? array_map(
				function ( $inner_block ) {
					return $inner_block['id'];
				},
				$block->parsed_block['innerBlocks']
			)
			: [];

		return Module::render(
			[
				// FE only.
				'orderIndex'          => $block->parsed_block['orderIndex'],
				'storeInstance'       => $block->parsed_block['storeInstance'],

				// VB equivalent.
				'attrs'               => $attrs,
				'elements'            => $elements,
				'id'                  => $block->parsed_block['id'],
				'name'                => $block->block_type->name,
				'moduleCategory'      => $block->block_type->category,
				'classnamesFunction'  => [ $class, 'module_classnames' ],
				'stylesComponent'     => [ $class, 'module_styles' ],
				'scriptDataComponent' => [ $class, 'module_script_data' ],
				'parentAttrs'         => $parent_attrs,
				'parentId'            => $parent->id ?? '',
				'parentName'          => $parent->blockName ?? '',
				'childrenIds'         => $children_ids,
				'children'            => ElementComponents::component(
					[
						'attrs'         => $attrs['module']['decoration'] ?? [],
						'id'            => $block->parsed_block['id'],
						'orderIndex'    => $block->parsed_block['orderIndex'],
						'storeInstance' => $block->parsed_block['storeInstance'],
					]
				) . $inner_html,
			]
		);
	}

	/**
	 * Shorthand for a div with a class and pre-sanitised children.
	 *
	 * @param string $class    CSS class.
	 * @param string $children Inner HTML.
	 * @param string $tag      Tag name.
	 *
	 * @return string
	 */
	public static function box( $class, $children, $tag = 'div' ) {
		return HTMLUtility::render(
			[
				'tag'               => $tag,
				'attributes'        => [ 'class' => $class ],
				'childrenSanitizer' => 'et_core_esc_previously',
				'children'          => $children,
			]
		);
	}

	/**
	 * A link styled as one of the design's green buttons.
	 *
	 * @param string $class    CSS class.
	 * @param string $label    Pre-rendered label element.
	 * @param string $url      Destination.
	 *
	 * @return string
	 */
	public static function button( $class, $label, $url ) {
		return HTMLUtility::render(
			[
				'tag'               => 'a',
				'attributes'        => [
					'class' => $class,
					'href'  => '' !== $url ? esc_url( $url ) : null,
				],
				'childrenSanitizer' => 'et_core_esc_previously',
				'children'          => $label,
			]
		);
	}

	/**
	 * Read a plain attribute value (no element rendering).
	 *
	 * @param array  $attrs Attributes.
	 * @param string $name  Attribute name.
	 * @param string $fallback Default.
	 *
	 * @return string
	 */
	public static function value( $attrs, $name, $fallback = '' ) {
		$value = $attrs[ $name ]['innerContent']['desktop']['value'] ?? '';

		return '' !== $value ? $value : $fallback;
	}
}
