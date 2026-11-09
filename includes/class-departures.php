<?php
/**
 * Departures: status rules, queries and the admin list.
 *
 * Status rules, in order:
 * 1. Cancelled: hidden everywhere.
 * 2. Sold out: no seats left. Stays visible with "Join the waitlist".
 * 3. Booking closed: past the book-by date, or closed by hand. Visible until the start date.
 * 4. Guaranteed: at least the "guaranteed at" number have booked. Shows seats left.
 * 5. Booking open: shows seats left only (never "X more needed").
 * Departures disappear from the site once they have started.
 *
 * @package Journeyline
 */

namespace Journeyline;

defined( 'ABSPATH' ) || exit;

/**
 * Departure helpers.
 */
final class Departures {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_filter( 'manage_' . Content_Types::DEPARTURE . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . Content_Types::DEPARTURE . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
	}

	/**
	 * Today in the site's time zone, as Y-m-d.
	 *
	 * @return string
	 */
	public static function today() {
		return wp_date( 'Y-m-d' );
	}

	/**
	 * Work out how a departure should show.
	 *
	 * @param int $id Departure ID.
	 * @return array{key:string,label:string,note:string,seats_left:int,bookable:bool}
	 */
	public static function status( $id ) {
		$total  = (int) get_post_meta( $id, 'jrnl_seats_total', true );
		$total  = $total ? $total : (int) Settings::get( 'max_group' );
		$booked = (int) get_post_meta( $id, 'jrnl_seats_booked', true );
		$left   = max( 0, $total - $booked );
		$by     = (string) get_post_meta( $id, 'jrnl_book_by', true );
		$today  = self::today();

		/* translators: %d: number of seats left */
		$seats = sprintf( _n( '%d seat left', '%d seats left', $left, 'journeyline' ), $left );

		if ( get_post_meta( $id, 'jrnl_cancelled', true ) ) {
			return array( 'key' => 'cancelled', 'label' => __( 'Cancelled', 'journeyline' ), 'note' => '', 'seats_left' => 0, 'bookable' => false );
		}
		if ( 0 === $left ) {
			return array( 'key' => 'sold-out', 'label' => __( 'Sold out', 'journeyline' ), 'note' => __( 'Join the waitlist', 'journeyline' ), 'seats_left' => 0, 'bookable' => false );
		}
		if ( get_post_meta( $id, 'jrnl_closed', true ) || ( $by && $by < $today ) ) {
			return array( 'key' => 'closed', 'label' => __( 'Booking closed', 'journeyline' ), 'note' => __( 'Ask us about last-minute places', 'journeyline' ), 'seats_left' => $left, 'bookable' => false );
		}
		if ( $booked >= (int) Settings::get( 'guarantee_at' ) ) {
			return array( 'key' => 'guaranteed', 'label' => __( 'Guaranteed', 'journeyline' ), 'note' => $seats, 'seats_left' => $left, 'bookable' => true );
		}
		return array( 'key' => 'open', 'label' => __( 'Booking open', 'journeyline' ), 'note' => $seats, 'seats_left' => $left, 'bookable' => true );
	}

	/**
	 * Upcoming departures, soonest first. Cancelled and already-started ones are left out.
	 *
	 * @param array $args Optional: trip (ID), limit (int), seats_only (bool).
	 * @return int[] Departure IDs.
	 */
	public static function upcoming( $args = array() ) {
		$args  = wp_parse_args( $args, array( 'trip' => 0, 'limit' => -1, 'seats_only' => false ) );
		$meta  = array(
			'relation' => 'AND',
			array( 'key' => 'jrnl_start', 'value' => self::today(), 'compare' => '>=', 'type' => 'DATE' ),
		);
		if ( $args['trip'] ) {
			$meta[] = array( 'key' => 'jrnl_trip_id', 'value' => (int) $args['trip'], 'compare' => '=', 'type' => 'NUMERIC' );
		}
		$ids = get_posts(
			array(
				'post_type'      => Content_Types::DEPARTURE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => 'jrnl_start', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- sorting by date needs it.
				'orderby'        => 'meta_value',
				'order'          => 'ASC',
				'meta_query'     => $meta, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- departures are few.
			)
		);
		$out = array();
		foreach ( $ids as $id ) {
			$st = self::status( $id );
			if ( 'cancelled' === $st['key'] || ( $args['seats_only'] && ! $st['bookable'] ) ) {
				continue;
			}
			$out[] = (int) $id;
			if ( $args['limit'] > 0 && count( $out ) >= $args['limit'] ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Format a price with the site's currency label.
	 *
	 * @param float $amount Amount.
	 * @return string
	 */
	public static function price( $amount ) {
		return (string) Settings::get( 'currency_label' ) . number_format_i18n( (float) $amount );
	}

	/**
	 * Admin list columns.
	 *
	 * @param array $cols Columns.
	 * @return array
	 */
	public static function columns( $cols ) {
		return array(
			'cb'           => $cols['cb'] ?? '',
			'title'        => __( 'Departure', 'journeyline' ),
			'jrnl_dates'   => __( 'Dates', 'journeyline' ),
			'jrnl_seats'   => __( 'Seats', 'journeyline' ),
			'jrnl_status'  => __( 'Shows as', 'journeyline' ),
		);
	}

	/**
	 * Fill the admin list columns.
	 *
	 * @param string $col Column.
	 * @param int    $id  Departure ID.
	 */
	public static function column( $col, $id ) {
		$fmt = get_option( 'date_format' );
		if ( 'jrnl_dates' === $col ) {
			$a = (string) get_post_meta( $id, 'jrnl_start', true );
			$b = (string) get_post_meta( $id, 'jrnl_end', true );
			echo esc_html( ( $a ? wp_date( $fmt, strtotime( $a ) ) : '' ) . ( $b ? ' to ' . wp_date( $fmt, strtotime( $b ) ) : '' ) );
		} elseif ( 'jrnl_seats' === $col ) {
			$st = self::status( $id );
			$t  = (int) get_post_meta( $id, 'jrnl_seats_total', true );
			/* translators: 1: seats booked, 2: seats in total */
			echo esc_html( sprintf( __( '%1$d of %2$d booked', 'journeyline' ), (int) get_post_meta( $id, 'jrnl_seats_booked', true ), $t ? $t : (int) Settings::get( 'max_group' ) ) );
		} elseif ( 'jrnl_status' === $col ) {
			$st = self::status( $id );
			echo esc_html( $st['label'] . ( $st['note'] ? ' · ' . $st['note'] : '' ) );
		}
	}
}
