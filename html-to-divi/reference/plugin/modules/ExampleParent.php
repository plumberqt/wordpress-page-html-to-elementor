<?php
/**
 * Module: Example Parent — stat card beside the section headline.
 *
 * @package AcmeDM\Modules\ExampleParent
 */

namespace AcmeDM\Modules\ExampleParent;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use AcmeDM\Modules\Shared\ModuleTrait;
use AcmeDM\Modules\Shared\Render;

class ExampleParent implements DependencyInterface {
	use ModuleTrait;

	public static function block_name() {
		return 'acme/example-parent';
	}

	public static function json_slug() {
		return 'example-parent';
	}

	public static function style_attrs() {
		return [ 'cardEyebrow', 'cardSubtitle', 'footnote', 'title', 'body', 'buttonText' ];
	}

	public static function text_selector_suffix() {
		return ' .acme_parent__right';
	}

	/**
	 * @param array     $attrs    Block attributes.
	 * @param string    $content  Child module output (the metric bars).
	 * @param \WP_Block $block    Parsed block.
	 * @param object    $elements ModuleElements instance.
	 *
	 * @return string
	 */
	public static function render_callback( $attrs, $content, $block, $elements ) {
		$e = function ( $name ) use ( $elements ) {
			return $elements->render( [ 'attrName' => $name ] );
		};

		$legend = Render::box(
			'acme_parent__legend',
			Render::box( 'acme_parent__legend-item', '<span class="acme_parent__swatch acme_parent__swatch--before"></span>' . $e( 'legendBefore' ) )
			. Render::box( 'acme_parent__legend-item', '<span class="acme_parent__swatch acme_parent__swatch--after"></span>' . $e( 'legendAfter' ) )
		);

		$card_head = Render::box(
			'acme_parent__cardhead',
			Render::box( 'acme_parent__cardhead-text', $e( 'cardEyebrow' ) . $e( 'cardSubtitle' ) ) . $legend
		);

		$card = Render::box(
			'acme_parent__card',
			$card_head . Render::box( 'acme_parent__metrics', $content ) . $e( 'footnote' )
		);

		$right = Render::box(
			'acme_parent__right',
			$e( 'title' )
			. $e( 'body' )
			. Render::button( 'acme_parent__button', $e( 'buttonText' ), Render::value( $attrs, 'buttonUrl' ) )
		);

		return Render::wrap(
			static::class,
			$attrs,
			$block,
			$elements,
			Render::box( 'acme_parent__inner', $card . $right )
		);
	}
}
