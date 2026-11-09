<?php
/**
 * Development sample: one clearly marked sample trip with departures, and a page using every block.
 * Run with: studio wp eval-file wp-content/plugins/journeyline/tests/seed-sample.php
 * Safe to run again: it replaces its own sample content.
 *
 * @package Journeyline
 */

use Journeyline\Content_Types;

$old = get_posts( array( 'post_type' => array( Content_Types::TRIP, Content_Types::DEPARTURE, 'page' ), 'meta_key' => '_jl_sample', 'posts_per_page' => -1, 'fields' => 'ids', 'post_status' => 'any' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
foreach ( $old as $id ) {
	wp_delete_post( $id, true );
}

$country = term_exists( 'Pakistan', Content_Types::DESTINATION ) ?: wp_insert_term( 'Pakistan', Content_Types::DESTINATION );
$style   = term_exists( 'Living Heritage', Content_Types::STYLE ) ?: wp_insert_term( 'Living Heritage', Content_Types::STYLE );

$trip = wp_insert_post(
	array(
		'post_type'    => Content_Types::TRIP,
		'post_status'  => 'publish',
		'post_title'   => 'Sample tour: Living Heritage in Pakistan',
		'post_excerpt' => 'A sample trip for building and testing. Not a real tour.',
		'post_content' => '<!-- wp:paragraph --><p>This is a sample trip used while building the site. Real tours replace it.</p><!-- /wp:paragraph -->',
	)
);
update_post_meta( $trip, '_jl_sample', 1 );
wp_set_object_terms( $trip, (int) ( is_array( $country ) ? $country['term_id'] : $country ), Content_Types::DESTINATION );
wp_set_object_terms( $trip, (int) ( is_array( $style ) ? $style['term_id'] : $style ), Content_Types::STYLE );
$meta = array(
	'jrnl_hook'       => 'A sample one-line hook, written from the tour sheet.',
	'jrnl_days'       => 8,
	'jrnl_nights'     => 7,
	'jrnl_activity'   => 2,
	'jrnl_trip_type'  => 'Small group, or private on request',
	'jrnl_highlights' => array( 'Sample highlight one', 'Sample highlight two', 'Sample highlight three' ),
	'jrnl_included'   => array( 'Stays throughout', 'Airport transfers', 'A team leader from first day to last' ),
	'jrnl_excluded'   => array( 'Visas', 'International flights', 'Tips, always optional' ),
	'jrnl_itinerary'  => array(
		array( 'title' => 'Arrival', 'body' => 'Someone meets you at the airport and takes you to your first stay.', 'meals' => 'D', 'overnight' => 'Sample city', 'stop' => 1 ),
		array( 'title' => 'Sample day two', 'body' => 'Two to four sentences from the tour sheet.', 'meals' => 'B, L, D', 'overnight' => 'Sample town', 'stop' => 2 ),
		array( 'title' => 'Departure', 'body' => 'A transfer to the airport for your flight home.', 'meals' => 'B', 'overnight' => '', 'stop' => 3 ),
	),
);
foreach ( $meta as $k => $v ) {
	update_post_meta( $trip, $k, $v );
}
$deps = array( array( '+50 days', '+57 days', '+20 days', 9, 1400 ), array( '+95 days', '+102 days', '+65 days', 3, 1500 ), array( '+130 days', '+137 days', '+100 days', 12, 1500 ) );
foreach ( $deps as $d ) {
	$id = wp_insert_post( array( 'post_type' => Content_Types::DEPARTURE, 'post_status' => 'publish', 'post_title' => 'Sample departure' ) );
	update_post_meta( $id, '_jl_sample', 1 );
	update_post_meta( $id, 'jrnl_trip_id', $trip );
	update_post_meta( $id, 'jrnl_start', gmdate( 'Y-m-d', strtotime( $d[0] ) ) );
	update_post_meta( $id, 'jrnl_end', gmdate( 'Y-m-d', strtotime( $d[1] ) ) );
	update_post_meta( $id, 'jrnl_book_by', gmdate( 'Y-m-d', strtotime( $d[2] ) ) );
	update_post_meta( $id, 'jrnl_seats_booked', $d[3] );
	update_post_meta( $id, 'jrnl_seats_total', 12 );
	update_post_meta( $id, 'jrnl_price', $d[4] );
}
$blocks = '<!-- wp:paragraph --><p>Every Journeyline block on one page, for testing.</p><!-- /wp:paragraph -->'
	. '<!-- wp:journeyline/trips {"showFilters":true} /-->'
	. '<!-- wp:journeyline/departures {"scope":"all","groupByMonth":true,"showFilters":true} /-->'
	. '<!-- wp:journeyline/enquiry-form {"showTopic":true} /-->';
$page   = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Block test', 'post_name' => 'block-test', 'post_content' => $blocks ) );
update_post_meta( $page, '_jl_sample', 1 );

$trip_blocks = '<!-- wp:journeyline/trip-facts /--><!-- wp:journeyline/trip-list {"which":"highlights"} /--><!-- wp:journeyline/itinerary /--><!-- wp:journeyline/departures /--><!-- wp:journeyline/trip-list {"which":"included"} /--><!-- wp:journeyline/trip-list {"which":"excluded"} /--><!-- wp:journeyline/enquiry-form /-->';
wp_update_post( array( 'ID' => $trip, 'post_content' => get_post_field( 'post_content', $trip ) . $trip_blocks ) );

printf( "Sample trip %d: %s\nBlock test page: %s\n", $trip, get_permalink( $trip ), get_permalink( $page ) );
