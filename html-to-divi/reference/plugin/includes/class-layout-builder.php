<?php
/**
 * Builds a whole page as a Divi Library layout, so the page can
 * be assembled in one click instead of dragging fifteen modules into place.
 *
 * The layout itself is not assembled here. `tools/build-layout.js` compiles it
 * once into `layout/<slug>.blocks.html` — the same markup that
 * ships as the importable `layout/<slug>.json` — so the
 * one-click build and the import file cannot drift apart. This class only
 * resolves the plugin URL token and inserts the post.
 *
 * Divi 5 stores layouts as WordPress block markup: the modules registered by
 * ModuleRegistration::register_module() are real block types, and Divi's own
 * structural blocks are `divi/section`, `divi/row` and `divi/column` — read off
 * a Divi 5.3.3 export of the live site rather than guessed. They are still
 * checked against the block registry before building, so a mismatched Divi
 * version says so instead of producing a broken layout.
 *
 * @package AcmeDM
 */

namespace AcmeDM;

defined( 'ABSPATH' ) || exit;

class Layout_Builder {

	/**
	 * Compiled block markup, with `{{ACME_DM_URL}}` where asset URLs go.
	 */
	const SLUG = 'example-page';

	/**
	 * Compiled block markup for that page, with `{{ACME_DM_URL}}` where asset
	 * URLs go. One pair of these per page; add a const per page or take the slug
	 * as an argument if the site has several.
	 */
	const TEMPLATE = 'layout/example-page.blocks.html';

	/**
	 * Divi's structural blocks. The layout cannot be built without these.
	 *
	 * @var string[]
	 */
	const REQUIRED_BLOCKS = [ 'divi/section', 'divi/row', 'divi/column' ];

	/**
	 * The native contact form in the proposal card. Optional: if this install
	 * names it something else, the layout is still built and the form is left
	 * out for the author to drop in.
	 */
	const FORM_BLOCK = 'divi/contact-form';

	/**
	 * One of these per field inside the form block.
	 */
	const FIELD_BLOCK = 'divi/contact-field';

	/**
	 * Title of the library item. Reused so a rebuild overwrites rather than
	 * making a second copy.
	 */
	const TITLE = 'Acme — Full Page';

	/**
	 * Which of the blocks this layout references are actually registered.
	 *
	 * @return array<string,bool> Block name => registered.
	 */
	public static function block_status() {
		$registry = \WP_Block_Type_Registry::get_instance()->get_all_registered();
		$status   = [];

		foreach ( array_merge( self::REQUIRED_BLOCKS, [ self::FORM_BLOCK ] ) as $name ) {
			$status[ $name ] = isset( $registry[ $name ] );
		}

		return $status;
	}

	/**
	 * Every registered block name in the divi/ namespace.
	 *
	 * Shown on the admin screen so that if a name above is wrong, the correct
	 * one is right there rather than needing another round trip.
	 *
	 * @return array
	 */
	public static function divi_blocks() {
		$names = array_keys( \WP_Block_Type_Registry::get_instance()->get_all_registered() );

		$names = array_filter(
			$names,
			function ( $name ) {
				return 0 === strpos( $name, 'divi/' );
			}
		);

		sort( $names );

		return $names;
	}

	/**
	 * Everything on this install that holds a copy of this layout, and what each
	 * copy actually contains.
	 *
	 * Loading a library layout onto a page *copies* its blocks into that page, so
	 * rebuilding or re-importing the library item does not change a page that was
	 * already built from it — the page keeps the older copy until it is loaded
	 * again. That is invisible from the front end and easy to misread as a broken
	 * layout, so this counts the blocks in each copy and the admin screen shows
	 * them side by side.
	 *
	 * @return array<int,array{title:string,type:string,edit:string,modules:int,fields:int,is_current:bool}>
	 */
	public static function audit() {
		global $wpdb;

		$posts = $wpdb->get_results(
			"SELECT ID, post_title, post_type
			 FROM {$wpdb->posts}
			 WHERE post_status NOT IN ( 'trash', 'auto-draft' )
			   AND post_content LIKE '%wp:select/hero%'
			 ORDER BY post_type, post_title
			 LIMIT 50"
		);

		$compiled = self::build();
		$expected = null === $compiled ? 0 : substr_count( $compiled, '<!-- wp:' . self::FIELD_BLOCK );

		$rows = [];

		foreach ( $posts as $post ) {
			$content = get_post_field( 'post_content', $post->ID );
			$fields  = substr_count( $content, '<!-- wp:' . self::FIELD_BLOCK );

			$rows[] = [
				'title'      => $post->post_title,
				'type'       => $post->post_type,
				'edit'       => get_edit_post_link( $post->ID, '' ),
				'modules'    => preg_match_all( '#<!-- wp:select/#', $content ),
				'fields'     => $fields,
				'is_current' => $fields === $expected,
			];
		}

		return $rows;
	}

	/**
	 * The compiled layout, with asset URLs resolved for this install.
	 *
	 * @param bool $with_form Whether to keep the native contact form block.
	 *
	 * @return string|null Block markup, or null if the compiled file is missing.
	 */
	public static function build( $with_form = true ) {
		$path = ACME_DM_PATH . self::TEMPLATE;

		if ( ! is_readable( $path ) ) {
			return null;
		}

		$blocks = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		if ( ! $with_form ) {
			// Drop the whole form block. It wraps one divi/contact-field child per
			// field, so the children and the closing delimiter have to go with it
			// or the layout is left with orphaned blocks.
			$quoted = preg_quote( self::FORM_BLOCK, '#' );

			$blocks = preg_replace(
				'#^<!-- wp:' . $quoted . '[^\n]*\n(?:.*?\n)?<!-- /wp:' . $quoted . ' -->\n#ms',
				'',
				$blocks
			);
		}

		return str_replace( '{{ACME_DM_URL}}', ACME_DM_URL, $blocks );
	}

	/**
	 * Create (or refresh) the Divi Library item.
	 *
	 * @return array{status:string,message:string,id:int}
	 */
	public static function create() {
		$status  = self::block_status();
		$missing = [];

		foreach ( self::REQUIRED_BLOCKS as $name ) {
			if ( empty( $status[ $name ] ) ) {
				$missing[] = $name;
			}
		}

		if ( ! empty( $missing ) ) {
			return [
				'status'  => 'error',
				'message' => sprintf(
					'This install does not register %s, so the layout would not render. The divi/* blocks it does register are listed below — send that list over and the layout can be recompiled against the right names.',
					implode( ', ', $missing )
				),
				'id'      => 0,
			];
		}

		$has_form = ! empty( $status[ self::FORM_BLOCK ] );
		$content  = self::build( $has_form );

		if ( null === $content ) {
			return [
				'status'  => 'error',
				'message' => sprintf(
					'The compiled layout (%s) is missing from the plugin folder. Run `npm run build:layout` and re-upload.',
					self::TEMPLATE
				),
				'id'      => 0,
			];
		}

		$existing = get_posts(
			[
				'post_type'      => 'et_pb_layout',
				'title'          => self::TITLE,
				'posts_per_page' => 1,
				'post_status'    => 'any',
			]
		);

		$postarr = [
			'post_title'   => self::TITLE,
			'post_content' => $content,
			'post_status'  => 'publish',
			'post_type'    => 'et_pb_layout',
		];

		if ( ! empty( $existing ) ) {
			$postarr['ID'] = $existing[0]->ID;
			$post_id       = wp_update_post( $postarr, true );
			$verb          = 'updated';
		} else {
			$post_id = wp_insert_post( $postarr, true );
			$verb    = 'created';
		}

		if ( is_wp_error( $post_id ) ) {
			return [
				'status'  => 'error',
				'message' => $post_id->get_error_message(),
				'id'      => 0,
			];
		}

		// Divi's library taxonomies, with the slugs Divi 5 itself exports
		// (`non_global` / `regular`, not Divi 4's `not_global` / `on_page`).
		wp_set_object_terms( $post_id, 'layout', 'layout_type' );
		wp_set_object_terms( $post_id, 'non_global', 'scope' );
		wp_set_object_terms( $post_id, 'regular', 'module_width' );
		update_post_meta( $post_id, '_et_pb_built_for_post_type', 'page' );

		// Marks the content as Divi 5 blocks rather than Divi 4 shortcodes.
		update_post_meta( $post_id, '_et_pb_use_divi_5', 'on' );

		return [
			'status'  => 'success',
			'message' => sprintf(
				'Layout %s. %s Open Divi → Divi Library to load it onto a page.',
				$verb,
				$has_form
					? 'Set the contact form\'s fields once in the builder — First/Last/Mobile/Email at 50% width.'
					: sprintf( '%s is not registered here, so the proposal form was left out: add a native Contact Form module between the header and the contact strip.', self::FORM_BLOCK )
			),
			'id'      => (int) $post_id,
		];
	}
}
