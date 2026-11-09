<?php
/**
 * Fields: the details stored on trips and departures, and the departure edit form.
 *
 * @package Journeyline
 */

namespace Journeyline;

defined( 'ABSPATH' ) || exit;

/**
 * Registers trip and departure fields so themes, blocks and the REST API can read them.
 */
final class Fields {

	const NONCE = 'journeyline_departure_nonce';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ), 20 );
		add_action( 'add_meta_boxes_' . Content_Types::DEPARTURE, array( __CLASS__, 'departure_box' ) );
		add_action( 'save_post_' . Content_Types::DEPARTURE, array( __CLASS__, 'save_departure' ), 10, 2 );
	}

	/**
	 * Trip fields: name => [type, schema items or null].
	 *
	 * @return array
	 */
	public static function trip_fields() {
		$day = array(
			'type'       => 'object',
			'properties' => array(
				'title'     => array( 'type' => 'string' ),
				'body'      => array( 'type' => 'string' ),
				'meals'     => array( 'type' => 'string' ),
				'overnight' => array( 'type' => 'string' ),
				'stop'      => array( 'type' => 'integer' ),
			),
		);
		return array(
			'jrnl_hook'       => array( 'string', null ),
			'jrnl_days'       => array( 'integer', null ),
			'jrnl_nights'     => array( 'integer', null ),
			'jrnl_activity'   => array( 'integer', null ),
			'jrnl_min_age'    => array( 'integer', null ),
			'jrnl_group_min'  => array( 'integer', null ),
			'jrnl_group_max'  => array( 'integer', null ),
			'jrnl_from_price' => array( 'number', null ),
			'jrnl_moment'     => array( 'string', null ),
			'jrnl_season'     => array( 'string', null ),
			'jrnl_stays'      => array( 'string', null ),
			'jrnl_good_for'   => array( 'string', null ),
			'jrnl_trip_type'  => array( 'string', null ),
			'jrnl_highlights' => array( 'array', array( 'type' => 'string' ) ),
			'jrnl_included'   => array( 'array', array( 'type' => 'string' ) ),
			'jrnl_excluded'   => array( 'array', array( 'type' => 'string' ) ),
			'jrnl_itinerary'  => array( 'array', $day ),
		);
	}

	/**
	 * Departure fields: name => type.
	 *
	 * @return array
	 */
	public static function departure_fields() {
		return array(
			'jrnl_trip_id'      => 'integer',
			'jrnl_start'        => 'string',
			'jrnl_end'          => 'string',
			'jrnl_price'        => 'number',
			'jrnl_seats_total'  => 'integer',
			'jrnl_seats_booked' => 'integer',
			'jrnl_book_by'      => 'string',
			'jrnl_closed'       => 'boolean',
			'jrnl_cancelled'    => 'boolean',
		);
	}

	/**
	 * Register all fields.
	 */
	public static function register() {
		foreach ( self::trip_fields() as $key => $def ) {
			$args = array(
				'type'          => $def[0],
				'single'        => true,
				'auth_callback' => array( __CLASS__, 'can_edit' ),
				'show_in_rest'  => true,
			);
			if ( 'array' === $def[0] ) {
				$args['default']      = array();
				$args['show_in_rest'] = array( 'schema' => array( 'type' => 'array', 'items' => $def[1] ) );
			}
			register_post_meta( Content_Types::TRIP, $key, $args );
		}
		foreach ( self::departure_fields() as $key => $type ) {
			register_post_meta(
				Content_Types::DEPARTURE,
				$key,
				array(
					'type'          => $type,
					'single'        => true,
					'auth_callback' => array( __CLASS__, 'can_edit' ),
					'show_in_rest'  => true,
				)
			);
		}
	}

	/**
	 * Only people who can edit the post may change its fields.
	 *
	 * @param bool   $allowed Current answer.
	 * @param string $key     Meta key.
	 * @param int    $post_id Post ID.
	 * @return bool
	 */
	public static function can_edit( $allowed, $key, $post_id ) {
		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * The departure form.
	 */
	public static function departure_box() {
		add_meta_box( 'journeyline-departure', __( 'Departure details', 'journeyline' ), array( __CLASS__, 'render_departure' ), Content_Types::DEPARTURE, 'normal', 'high' );
	}

	/**
	 * Draw the departure form.
	 *
	 * @param \WP_Post $post Departure.
	 */
	public static function render_departure( $post ) {
		wp_nonce_field( 'journeyline_save_departure', self::NONCE );
		$v     = function ( $k ) use ( $post ) {
			return get_post_meta( $post->ID, $k, true );
		};
		$trips = get_posts(
			array(
				'post_type'      => Content_Types::TRIP,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => 500,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'fields'         => 'ids',
			)
		);
		$max   = (int) Settings::get( 'max_group' );
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th scope="row"><label for="jrnl_trip_id">' . esc_html( (string) Settings::get( 'trip_label' ) ) . '</label></th><td><select id="jrnl_trip_id" name="jrnl_trip_id" required><option value="">' . esc_html__( 'Choose one', 'journeyline' ) . '</option>';
		foreach ( $trips as $id ) {
			printf( '<option value="%1$d"%2$s>%3$s</option>', (int) $id, selected( (int) $v( 'jrnl_trip_id' ), (int) $id, false ), esc_html( get_the_title( $id ) ) );
		}
		echo '</select></td></tr>';
		$rows = array(
			'jrnl_start'        => array( __( 'First day', 'journeyline' ), 'date', '' ),
			'jrnl_end'          => array( __( 'Last day', 'journeyline' ), 'date', '' ),
			'jrnl_book_by'      => array( __( 'Book by', 'journeyline' ), 'date', __( 'After this date the departure shows as Booking closed.', 'journeyline' ) ),
			/* translators: %s: currency label, e.g. US$ */
			'jrnl_price'        => array( sprintf( __( 'Price per person (%s)', 'journeyline' ), (string) Settings::get( 'currency_label' ) ), 'number', '' ),
			'jrnl_seats_total'  => array( __( 'Seats in total', 'journeyline' ), 'number', '' ),
			'jrnl_seats_booked' => array( __( 'Seats booked', 'journeyline' ), 'number', '' ),
		);
		foreach ( $rows as $key => $row ) {
			$val = $v( $key );
			if ( 'jrnl_seats_total' === $key && '' === $val ) {
				$val = $max;
			}
			printf(
				'<tr><th scope="row"><label for="%1$s">%2$s</label></th><td><input type="%3$s" id="%1$s" name="%1$s" value="%4$s"%5$s>%6$s</td></tr>',
				esc_attr( $key ),
				esc_html( $row[0] ),
				esc_attr( $row[1] ),
				esc_attr( (string) $val ),
				'number' === $row[1] ? ' min="0" step="' . ( 'jrnl_price' === $key ? '1' : '1' ) . '"' : '',
				$row[2] ? '<p class="description">' . esc_html( $row[2] ) . '</p>' : ''
			);
		}
		printf( '<tr><th scope="row">%1$s</th><td><label><input type="checkbox" name="jrnl_closed" value="1"%2$s> %3$s</label><br><label><input type="checkbox" name="jrnl_cancelled" value="1"%4$s> %5$s</label></td></tr>', esc_html__( 'Overrides', 'journeyline' ), checked( (bool) $v( 'jrnl_closed' ), true, false ), esc_html__( 'Close booking now', 'journeyline' ), checked( (bool) $v( 'jrnl_cancelled' ), true, false ), esc_html__( 'Cancelled (hidden from the site)', 'journeyline' ) );
		echo '</tbody></table>';
		$status = Departures::status( $post->ID );
		/* translators: %s: status label, e.g. Guaranteed */
		echo '<p><strong>' . esc_html( sprintf( __( 'Shows on the site as: %s', 'journeyline' ), $status['label'] . ( $status['note'] ? ' · ' . $status['note'] : '' ) ) ) . '</strong></p>';
	}

	/**
	 * Save the departure form, and give the departure a readable title.
	 *
	 * @param int      $post_id Departure ID.
	 * @param \WP_Post $post    Departure.
	 */
	public static function save_departure( $post_id, $post ) {
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), 'journeyline_save_departure' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$date = function ( $k ) {
			$raw = isset( $_POST[ $k ] ) ? sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
			return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw ) ? $raw : '';
		};
		$int  = function ( $k ) {
			return isset( $_POST[ $k ] ) ? absint( $_POST[ $k ] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		};
		update_post_meta( $post_id, 'jrnl_trip_id', $int( 'jrnl_trip_id' ) );
		update_post_meta( $post_id, 'jrnl_start', $date( 'jrnl_start' ) );
		update_post_meta( $post_id, 'jrnl_end', $date( 'jrnl_end' ) );
		update_post_meta( $post_id, 'jrnl_book_by', $date( 'jrnl_book_by' ) );
		update_post_meta( $post_id, 'jrnl_price', isset( $_POST['jrnl_price'] ) ? max( 0, (float) $_POST['jrnl_price'] ) : 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified above; cast to float.
		update_post_meta( $post_id, 'jrnl_seats_total', $int( 'jrnl_seats_total' ) );
		update_post_meta( $post_id, 'jrnl_seats_booked', $int( 'jrnl_seats_booked' ) );
		update_post_meta( $post_id, 'jrnl_closed', ! empty( $_POST['jrnl_closed'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		update_post_meta( $post_id, 'jrnl_cancelled', ! empty( $_POST['jrnl_cancelled'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.

		$trip  = (int) get_post_meta( $post_id, 'jrnl_trip_id', true );
		$start = (string) get_post_meta( $post_id, 'jrnl_start', true );
		$title = trim( ( $trip ? get_the_title( $trip ) : '' ) . ( $start ? ' · ' . wp_date( get_option( 'date_format' ), strtotime( $start ) ) : '' ) );
		if ( $title && $title !== $post->post_title ) {
			remove_action( 'save_post_' . Content_Types::DEPARTURE, array( __CLASS__, 'save_departure' ), 10 );
			wp_update_post( array( 'ID' => $post_id, 'post_title' => $title ) );
			add_action( 'save_post_' . Content_Types::DEPARTURE, array( __CLASS__, 'save_departure' ), 10, 2 );
		}
	}
}
