<?php
/**
 * Module: Example Child — one before/after bar pair inside the intro card.
 *
 * @package AcmeDM\Modules\ExampleChild
 */

namespace AcmeDM\Modules\ExampleChild;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Framework\Utility\HTMLUtility;
use AcmeDM\Modules\Shared\ModuleTrait;
use AcmeDM\Modules\Shared\Render;

class ExampleChild implements DependencyInterface {
	use ModuleTrait;

	public static function block_name() {
		return 'acme/example-child';
	}

	public static function json_slug() {
		return 'example-child';
	}

	public static function style_attrs() {
		return [ 'label', 'delta' ];
	}

	/**
	 * Clamp a user-entered percentage to something a bar can express.
	 *
	 * @param string $raw   Raw field value.
	 * @param string $fallback Default when empty or non-numeric.
	 *
	 * @return float
	 */
	private static function pct( $raw, $fallback ) {
		$value = is_numeric( trim( $raw ) ) ? (float) $raw : (float) $fallback;

		return max( 0, min( 100, $value ) );
	}

	public static function render_callback( $attrs, $content, $block, $elements ) {
		$e = function ( $name ) use ( $elements ) {
			return $elements->render( [ 'attrName' => $name ] );
		};

		$before = self::pct( Render::value( $attrs, 'beforePct', '0' ), 0 );
		$after  = self::pct( Render::value( $attrs, 'afterPct', '0' ), 0 );

		$bar = function ( $modifier, $width ) {
			return HTMLUtility::render(
				[
					'tag'        => 'div',
					'attributes' => [
						'class' => 'acme_child__bar acme_child__bar--' . $modifier,
						'style' => 'width:' . $width . '%;',
					],
				]
			);
		};

		$inner = Render::box(
			'acme_child__row',
			Render::box( 'acme_child__head', $e( 'label' ) . $e( 'delta' ) )
			. Render::box( 'acme_child__bars', $bar( 'before', $before ) . $bar( 'after', $after ) )
		);

		return Render::wrap( static::class, $attrs, $block, $elements, $inner );
	}
}
