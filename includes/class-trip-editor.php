<?php
/**
 * Trip editor: a details box under the main editor, with lists you can add to and reorder.
 *
 * @package Journeyline
 */

namespace Journeyline;

defined( 'ABSPATH' ) || exit;

/**
 * The "Trip details" box on the trip edit screen.
 */
final class Trip_Editor {

	const NONCE = 'journeyline_trip_nonce';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'add_meta_boxes_' . Content_Types::TRIP, array( __CLASS__, 'box' ) );
		add_action( 'save_post_' . Content_Types::TRIP, array( __CLASS__, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	/**
	 * Admin script and styles, on trip screens only.
	 *
	 * @param string $hook Admin page.
	 */
	public static function assets( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || Content_Types::TRIP !== $screen->post_type || ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		wp_enqueue_style( 'journeyline-admin', URL . 'assets/admin.css', array(), VERSION );
		wp_enqueue_script( 'journeyline-admin', URL . 'assets/admin.js', array(), VERSION, true );
	}

	/**
	 * Register the box.
	 */
	public static function box() {
		add_meta_box( 'journeyline-trip', __( 'Trip details', 'journeyline' ), array( __CLASS__, 'render' ), Content_Types::TRIP, 'normal', 'high' );
	}

	/**
	 * Simple fields: key => [label, type, help].
	 *
	 * @return array
	 */
	private static function simple() {
		return array(
			'jrnl_hook'       => array( __( 'One-line hook', 'journeyline' ), 'text', __( 'Shown under the trip name.', 'journeyline' ) ),
			'jrnl_days'       => array( __( 'Days', 'journeyline' ), 'number', '' ),
			'jrnl_nights'     => array( __( 'Nights', 'journeyline' ), 'number', '' ),
			/* translators: %s: currency label */
			'jrnl_from_price' => array( sprintf( __( 'From price per person (%s)', 'journeyline' ), (string) Settings::get( 'currency_label' ) ), 'number', __( 'Leave empty to use the lowest upcoming departure price.', 'journeyline' ) ),
			'jrnl_activity'   => array( __( 'Activity level (1 gentle to 5 demanding)', 'journeyline' ), 'number', '' ),
			'jrnl_min_age'    => array( __( 'Minimum age', 'journeyline' ), 'number', '' ),
			'jrnl_trip_type'  => array( __( 'Trip type', 'journeyline' ), 'text', __( 'For example: Small group, or private on request.', 'journeyline' ) ),
			'jrnl_good_for'   => array( __( 'Good for', 'journeyline' ), 'text', __( 'For example: solo travellers, couples, families.', 'journeyline' ) ),
			'jrnl_season'     => array( __( 'Best season', 'journeyline' ), 'text', '' ),
			'jrnl_stays'      => array( __( 'Where you stay', 'journeyline' ), 'text', '' ),
			'jrnl_moment'     => array( __( 'The moment guests remember', 'journeyline' ), 'textarea', '' ),
		);
	}

	/**
	 * Draw the box.
	 *
	 * @param \WP_Post $post Trip.
	 */
	public static function render( $post ) {
		wp_nonce_field( 'journeyline_save_trip', self::NONCE );
		echo '<div class="jl-admin">';
		echo '<div class="jl-grid">';
		foreach ( self::simple() as $key => $f ) {
			$val = (string) get_post_meta( $post->ID, $key, true );
			echo '<p class="jl-field' . ( 'textarea' === $f[1] || 'jrnl_hook' === $key ? ' jl-wide' : '' ) . '"><label for="' . esc_attr( $key ) . '">' . esc_html( $f[0] ) . '</label>';
			if ( 'textarea' === $f[1] ) {
				echo '<textarea id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" rows="2">' . esc_textarea( $val ) . '</textarea>';
			} else {
				$extra = 'number' === $f[1] ? ' min="' . ( 'jrnl_activity' === $key ? '1' : '0' ) . '"' . ( 'jrnl_activity' === $key ? ' max="5"' : '' ) . ' step="1"' : '';
				echo '<input type="' . esc_attr( $f[1] ) . '" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $val ) . '"' . $extra . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $extra is built from fixed strings.
			}
			if ( $f[2] ) {
				echo '<span class="description">' . esc_html( $f[2] ) . '</span>';
			}
			echo '</p>';
		}
		echo '</div>';

		$lists = array(
			'jrnl_highlights' => __( 'Highlights', 'journeyline' ),
			'jrnl_included'   => __( 'Included', 'journeyline' ),
			'jrnl_excluded'   => __( 'Not included', 'journeyline' ),
		);
		foreach ( $lists as $key => $label ) {
			$items = (array) get_post_meta( $post->ID, $key, true );
			self::list_field( $key, $label, $items );
		}

		$days = (array) get_post_meta( $post->ID, 'jrnl_itinerary', true );
		echo '<fieldset class="jl-list jl-days" data-name="jrnl_itinerary"><legend>' . esc_html__( 'Day by day', 'journeyline' ) . '</legend><ol class="jl-rows">';
		foreach ( $days as $i => $d ) {
			self::day_row( (int) $i, (array) $d );
		}
		echo '</ol><template>';
		self::day_row( '__i__', array() );
		echo '</template><button type="button" class="button jl-add">' . esc_html__( 'Add a day', 'journeyline' ) . '</button></fieldset>';
		echo '</div>';
	}

	/**
	 * A list of one-line items.
	 *
	 * @param string $key   Field name.
	 * @param string $label Heading.
	 * @param array  $items Current items.
	 */
	private static function list_field( $key, $label, $items ) {
		echo '<fieldset class="jl-list" data-name="' . esc_attr( $key ) . '"><legend>' . esc_html( $label ) . '</legend><ol class="jl-rows">';
		foreach ( $items as $item ) {
			self::item_row( $key, (string) $item );
		}
		echo '</ol><template>';
		self::item_row( $key, '' );
		echo '</template><button type="button" class="button jl-add">' . esc_html__( 'Add a line', 'journeyline' ) . '</button></fieldset>';
	}

	/**
	 * One row of a one-line list.
	 *
	 * @param string $key  Field name.
	 * @param string $item Value.
	 */
	private static function item_row( $key, $item ) {
		echo '<li class="jl-row"><input type="text" name="' . esc_attr( $key ) . '[]" value="' . esc_attr( $item ) . '" aria-label="' . esc_attr__( 'Line', 'journeyline' ) . '">';
		self::row_tools();
		echo '</li>';
	}

	/**
	 * One day of the itinerary.
	 *
	 * @param int|string $i Index (or a placeholder in the template).
	 * @param array      $d Day.
	 */
	private static function day_row( $i, $d ) {
		$n = 'jrnl_itinerary[' . $i . ']';
		echo '<li class="jl-row jl-day"><div class="jl-day-fields">';
		printf( '<label>%1$s<input type="text" name="%2$s[title]" value="%3$s"></label>', esc_html__( 'Day title', 'journeyline' ), esc_attr( $n ), esc_attr( (string) ( $d['title'] ?? '' ) ) );
		printf( '<label class="jl-wide">%1$s<textarea name="%2$s[body]" rows="3">%3$s</textarea></label>', esc_html__( 'What happens', 'journeyline' ), esc_attr( $n ), esc_textarea( (string) ( $d['body'] ?? '' ) ) );
		printf( '<label>%1$s<input type="text" name="%2$s[meals]" value="%3$s" placeholder="B, L, D"></label>', esc_html__( 'Meals', 'journeyline' ), esc_attr( $n ), esc_attr( (string) ( $d['meals'] ?? '' ) ) );
		printf( '<label>%1$s<input type="text" name="%2$s[overnight]" value="%3$s"></label>', esc_html__( 'Overnight', 'journeyline' ), esc_attr( $n ), esc_attr( (string) ( $d['overnight'] ?? '' ) ) );
		printf( '<label>%1$s<input type="number" min="0" step="1" name="%2$s[stop]" value="%3$s"></label>', esc_html__( 'Map stop number', 'journeyline' ), esc_attr( $n ), esc_attr( (string) ( $d['stop'] ?? '' ) ) );
		echo '</div>';
		self::row_tools();
		echo '</li>';
	}

	/**
	 * Move up, move down and remove buttons.
	 */
	private static function row_tools() {
		echo '<span class="jl-tools"><button type="button" class="button-link jl-up" aria-label="' . esc_attr__( 'Move up', 'journeyline' ) . '">↑</button><button type="button" class="button-link jl-down" aria-label="' . esc_attr__( 'Move down', 'journeyline' ) . '">↓</button><button type="button" class="button-link jl-remove" aria-label="' . esc_attr__( 'Remove', 'journeyline' ) . '">×</button></span>';
	}

	/**
	 * Save the box.
	 *
	 * @param int      $post_id Trip ID.
	 * @param \WP_Post $post    Trip.
	 */
	public static function save( $post_id, $post ) {
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), 'journeyline_save_trip' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified above.
		foreach ( self::simple() as $key => $f ) {
			$raw = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised below by type.
			if ( 'number' === $f[1] ) {
				if ( '' === $raw ) {
					delete_post_meta( $post_id, $key );
					continue;
				}
				$val = 'jrnl_from_price' === $key ? max( 0, (float) $raw ) : absint( $raw );
				if ( 'jrnl_activity' === $key ) {
					$val = min( 5, max( 1, $val ) );
				}
			} else {
				$val = 'textarea' === $f[1] ? sanitize_textarea_field( (string) $raw ) : sanitize_text_field( (string) $raw );
			}
			update_post_meta( $post_id, $key, $val );
		}
		foreach ( array( 'jrnl_highlights', 'jrnl_included', 'jrnl_excluded' ) as $key ) {
			$items = isset( $_POST[ $key ] ) && is_array( $_POST[ $key ] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST[ $key ] ) ) : array();
			update_post_meta( $post_id, $key, array_values( array_filter( $items, 'strlen' ) ) );
		}
		$days = array();
		if ( isset( $_POST['jrnl_itinerary'] ) && is_array( $_POST['jrnl_itinerary'] ) ) {
			foreach ( wp_unslash( $_POST['jrnl_itinerary'] ) as $d ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each part sanitised below.
				$d   = (array) $d;
				$day = array(
					'title'     => sanitize_text_field( (string) ( $d['title'] ?? '' ) ),
					'body'      => sanitize_textarea_field( (string) ( $d['body'] ?? '' ) ),
					'meals'     => sanitize_text_field( (string) ( $d['meals'] ?? '' ) ),
					'overnight' => sanitize_text_field( (string) ( $d['overnight'] ?? '' ) ),
					'stop'      => absint( $d['stop'] ?? 0 ),
				);
				if ( $day['title'] || $day['body'] ) {
					$days[] = $day;
				}
			}
		}
		update_post_meta( $post_id, 'jrnl_itinerary', $days );
		// phpcs:enable
	}
}
