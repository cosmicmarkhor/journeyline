<?php
/**
 * Departures block. Variables: $attrs, $trip, $wrapper. Copy to yourtheme/journeyline/departures.php to change it.
 *
 * @package Journeyline
 */

use Journeyline\Blocks;
use Journeyline\Content_Types;
use Journeyline\Departures;

defined( 'ABSPATH' ) || exit;

$only  = ( 'all' !== $attrs['scope'] && $trip ) ? $trip : 0;
$ids   = Departures::upcoming( array( 'trip' => $only, 'limit' => (int) $attrs['limit'] ) );
$group = ! empty( $attrs['groupByMonth'] );

if ( ! $ids ) {
	echo '<div ' . $wrapper . '><p class="jl-empty">' . esc_html__( 'No upcoming dates at the moment. Ask us about a private journey on dates that suit you.', 'journeyline' ) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-escaped wrapper attributes.
	return;
}

$rows    = array();
$filters = array( 'month' => array(), 'country' => array(), 'style' => array() );
foreach ( $ids as $id ) {
	$tid     = (int) get_post_meta( $id, 'jrnl_trip_id', true );
	$start   = (string) get_post_meta( $id, 'jrnl_start', true );
	$end     = (string) get_post_meta( $id, 'jrnl_end', true );
	$month   = substr( $start, 0, 7 );
	$country = wp_get_post_terms( $tid, Content_Types::DESTINATION, array( 'parent' => 0 ) );
	$style   = wp_get_post_terms( $tid, Content_Types::STYLE );
	$st      = Departures::status( $id );
	$filters['month'][ $month ] = wp_date( 'F Y', strtotime( $start ) );
	if ( $country ) {
		$filters['country'][ $country[0]->slug ] = $country[0]->name;
	}
	if ( $style ) {
		$filters['style'][ $style[0]->slug ] = $style[0]->name;
	}
	$rows[ $month ][] = compact( 'id', 'tid', 'start', 'end', 'country', 'style', 'st' );
}

echo '<div ' . $wrapper . ' data-jl-deps>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-escaped wrapper attributes.
if ( ! empty( $attrs['showFilters'] ) && ! $only ) {
	$labels = array( 'month' => __( 'Any month', 'journeyline' ), 'country' => __( 'Any destination', 'journeyline' ), 'style' => __( 'Any trip style', 'journeyline' ) );
	$names  = array( 'month' => __( 'Month', 'journeyline' ), 'country' => __( 'Destination', 'journeyline' ), 'style' => __( 'Trip style', 'journeyline' ) );
	echo '<div class="jl-dep-filters">';
	foreach ( $filters as $key => $opts ) {
		if ( 'month' !== $key ) {
			asort( $opts );
		}
		$fid = 'jl-f-' . $key . '-' . wp_unique_id();
		echo '<label class="screen-reader-text" for="' . esc_attr( $fid ) . '">' . esc_html( $names[ $key ] ) . '</label><select id="' . esc_attr( $fid ) . '" data-jl-dep-filter="' . esc_attr( $key ) . '"><option value="">' . esc_html( $labels[ $key ] ) . '</option>';
		foreach ( $opts as $v => $l ) {
			echo '<option value="' . esc_attr( $v ) . '">' . esc_html( $l ) . '</option>';
		}
		echo '</select>';
	}
	echo '<label class="jl-toggle"><input type="checkbox" data-jl-dep-filter="seats"> ' . esc_html__( 'Seats available only', 'journeyline' ) . '</label>';
	echo '<p class="jl-count" aria-live="polite"></p></div>';
}

foreach ( $rows as $month => $list ) {
	if ( $group ) {
		echo '<section class="jl-month" data-jl-month="' . esc_attr( $month ) . '"><h3 class="jl-month-title">' . esc_html( wp_date( 'F Y', strtotime( $month . '-01' ) ) ) . '</h3>';
	}
	echo '<ul class="jl-deps" role="list">';
	foreach ( $list as $r ) {
		$price = (float) get_post_meta( $r['id'], 'jrnl_price', true );
		$by    = (string) get_post_meta( $r['id'], 'jrnl_book_by', true );
		printf(
			'<li class="jl-dep jl-is-%1$s" data-month="%2$s" data-country="%3$s" data-style="%4$s" data-open="%5$s">',
			esc_attr( $r['st']['key'] ),
			esc_attr( $month ),
			esc_attr( $r['country'] ? $r['country'][0]->slug : '' ),
			esc_attr( $r['style'] ? $r['style'][0]->slug : '' ),
			$r['st']['bookable'] ? '1' : '0'
		);
		echo '<p class="jl-dep-date"><span class="jl-dep-day">' . esc_html( wp_date( 'j', strtotime( $r['start'] ) ) ) . '</span><span class="jl-dep-range">' . esc_html( wp_date( 'j M', strtotime( $r['start'] ) ) . ( $r['end'] ? ' to ' . wp_date( 'j M Y', strtotime( $r['end'] ) ) : wp_date( ' Y', strtotime( $r['start'] ) ) ) ) . '</span></p>';
		echo '<div class="jl-dep-trip">';
		if ( ! $only ) {
			echo '<a class="jl-dep-name" href="' . esc_url( get_permalink( $r['tid'] ) ) . '">' . esc_html( get_the_title( $r['tid'] ) ) . '</a>';
			if ( $r['country'] ) {
				echo '<span class="jl-dep-where">' . esc_html( $r['country'][0]->name ) . '</span>';
			}
		}
		if ( $price ) {
			/* translators: %s: price */
			echo '<span class="jl-dep-price">' . esc_html( sprintf( __( 'From %s per person', 'journeyline' ), Departures::price( $price ) ) ) . '</span>';
		}
		echo '</div><div class="jl-dep-status"><span class="jl-badge jl-badge-' . esc_attr( $r['st']['key'] ) . '">' . ( 'guaranteed' === $r['st']['key'] ? Blocks::tick() : '' ) . esc_html( $r['st']['label'] ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- tick() is a fixed SVG.
		$note = $r['st']['note'];
		if ( $by && $r['st']['bookable'] ) {
			/* translators: %s: date */
			$note .= ' · ' . sprintf( __( 'Book by %s', 'journeyline' ), wp_date( 'j M Y', strtotime( $by ) ) );
		}
		echo '<span class="jl-dep-note">' . esc_html( $note ) . '</span></div>';
		$action = $r['st']['bookable'] ? __( 'Ask about this date', 'journeyline' ) : ( 'sold-out' === $r['st']['key'] ? __( 'Join the waitlist', 'journeyline' ) : __( 'Ask about places', 'journeyline' ) );
		$url    = add_query_arg( array( 'trip' => $r['tid'], 'departure' => $r['id'] ), get_permalink( $r['tid'] ) ) . '#enquire';
		echo '<div class="jl-dep-actions">';
		echo '<a class="jl-dep-action" href="' . esc_url( $url ) . '" data-jl-departure="' . esc_attr( (string) $r['id'] ) . '">' . esc_html( apply_filters( 'journeyline_departure_action_label', $action, $r['id'], $r['st'] ) ) . '</a>';
		/**
		 * After a departure's main link: add-ons can add another way to ask, for example WhatsApp.
		 *
		 * @param int   $departure Departure ID.
		 * @param int   $trip      Trip ID.
		 * @param array $status    Status from Departures::status().
		 */
		do_action( 'journeyline_departure_after_action', (int) $r['id'], (int) $r['tid'], $r['st'] );
		echo '</div>';
		echo '</li>';
	}
	echo '</ul>';
	if ( $group ) {
		echo '</section>';
	}
}
echo '<p class="jl-empty" hidden>' . esc_html__( 'No dates match those filters.', 'journeyline' ) . '</p>';
echo '</div>';
