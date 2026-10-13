<?php
/**
 * Tools → Acme Divi Layout.
 *
 * One button that builds the whole page as a Divi Library layout,
 * plus a diagnostic list of the Divi block names actually registered on this
 * install — so if the block names differ from the ones the layout was compiled
 * against, the screen says exactly what they are instead of failing silently.
 *
 * @package AcmeDM
 */

namespace AcmeDM;

defined( 'ABSPATH' ) || exit;

class Admin_Page {

	const SLUG   = 'acme-dm-layout';
	const ACTION = 'select_dm_build_layout';

	/**
	 * Hook the admin screen up.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', [ __CLASS__, 'register' ] );
	}

	/**
	 * Register the Tools submenu.
	 *
	 * @return void
	 */
	public static function register() {
		add_management_page(
			'Acme Divi Layout',
			'Acme Divi Layout',
			'edit_theme_options',
			self::SLUG,
			[ __CLASS__, 'render' ]
		);
	}

	/**
	 * Render the screen, handling the build request first.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_die( 'Insufficient permissions.' );
		}

		$result = null;

		if (
			isset( $_POST[ self::ACTION ] )
			&& check_admin_referer( self::ACTION )
		) {
			$result = Layout_Builder::create();
		}

		$blocks      = Layout_Builder::block_status();
		$divi_blocks = Layout_Builder::divi_blocks();

		echo '<div class="wrap">';
		echo '<h1>Acme — Divi Layout</h1>';

		if ( empty( $divi_blocks ) ) {
			echo '<div class="notice notice-error"><p>No <code>divi/*</code> blocks are registered. '
				. 'Open this screen with Divi 5 active.</p></div>';
			echo '</div>';
			return;
		}

		if ( $result ) {
			printf(
				'<div class="notice notice-%s"><p>%s</p></div>',
				'success' === $result['status'] ? 'success' : 'error',
				esc_html( $result['message'] )
			);

			if ( 'success' === $result['status'] ) {
				printf(
					'<p><a class="button button-primary" href="%s">Open the Divi Library</a></p>',
					esc_url( admin_url( 'edit.php?post_type=et_pb_layout' ) )
				);
			}
		}

		echo '<p>This builds the complete page — every section, with every '
			. 'topic, FAQ, logo, metric and city populated — as a single Divi Library layout. '
			. 'Load it onto a page from <strong>Divi → Divi Library</strong> rather than dragging '
			. 'modules in one at a time. Running it again overwrites the same layout.</p>';

		self::render_audit();

		echo '<h2>Blocks this layout references</h2>';
		echo '<table class="widefat striped" style="max-width:560px"><tbody>';

		foreach ( $blocks as $name => $registered ) {
			$optional = Layout_Builder::FORM_BLOCK === $name;

			printf(
				'<tr><td style="width:220px"><code>%s</code>%s</td><td>%s</td></tr>',
				esc_html( $name ),
				$optional ? ' <em>(optional)</em>' : '',
				$registered
					? 'registered'
					: sprintf(
						'<span style="color:#b32d2e">not found</span>%s',
						$optional ? ' — the form is left out of the layout' : ''
					)
			);
		}

		echo '</tbody></table>';

		$resolved = true;

		foreach ( Layout_Builder::REQUIRED_BLOCKS as $required ) {
			if ( empty( $blocks[ $required ] ) ) {
				$resolved = false;
			}
		}

		echo '<form method="post" style="margin:18px 0">';
		wp_nonce_field( self::ACTION );
		printf(
			'<button type="submit" name="%s" value="1" class="button button-primary"%s>Build the layout</button>',
			esc_attr( self::ACTION ),
			$resolved ? '' : ' disabled'
		);
		echo '</form>';

		if ( ! $resolved ) {
			echo '<div class="notice notice-warning inline"><p>One or more of Divi&#8217;s structural '
				. 'blocks is not registered on this install, so the build is disabled. Send the list '
				. 'below over and the layout can be recompiled against the right names.</p></div>';
		}

		echo '<h2>Registered <code>divi/*</code> blocks on this install</h2>';
		echo '<p class="description">Diagnostic — this is the ground truth for what the layout '
			. 'builder can reference.</p>';
		echo '<textarea readonly rows="12" style="width:100%;max-width:720px;font-family:monospace">'
			. esc_textarea( implode( "\n", $divi_blocks ) )
			. '</textarea>';

		echo '</div>';
	}

	/**
	 * Where this layout already exists on the install, and whether each copy is
	 * up to date.
	 *
	 * Loading a library layout onto a page copies its blocks into the page, so a
	 * rebuild or a re-import does not reach a page that was built earlier — that
	 * page keeps the older copy until the layout is loaded again. This table makes
	 * that visible instead of leaving it to be inferred from the front end.
	 *
	 * @return void
	 */
	private static function render_audit() {
		$rows = Layout_Builder::audit();

		echo '<h2>Copies of this layout on this site</h2>';

		if ( empty( $rows ) ) {
			echo '<p class="description">None yet — build the layout below, then load it onto a page '
				. 'from <strong>Divi → Divi Library</strong>.</p>';
			return;
		}

		echo '<p class="description">Loading a layout onto a page <em>copies</em> its blocks. '
			. 'Rebuilding the library item does not update a page built from an earlier copy — open '
			. 'that page in the builder and load the layout again.</p>';

		echo '<table class="widefat striped" style="max-width:820px"><thead><tr>'
			. '<th>Where</th><th>Type</th><th>Acme modules</th><th>Form fields</th><th></th>'
			. '</tr></thead><tbody>';

		foreach ( $rows as $row ) {
			printf(
				'<tr><td>%s</td><td><code>%s</code></td><td>%d</td><td>%d</td><td>%s</td></tr>',
				$row['edit']
					? sprintf( '<a href="%s">%s</a>', esc_url( $row['edit'] ), esc_html( $row['title'] ) )
					: esc_html( $row['title'] ),
				esc_html( $row['type'] ),
				(int) $row['modules'],
				(int) $row['fields'],
				$row['is_current']
					? '<span style="color:#00713b">up to date</span>'
					: '<strong style="color:#b32d2e">older copy</strong> — load the layout again'
			);
		}

		echo '</tbody></table>';
	}
}
