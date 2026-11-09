<?php
/**
 * Quick check of the departure status rules. Run with: studio wp eval-file wp-content/plugins/journeyline/tests/status-check.php
 * Creates throwaway posts, prints the result, then deletes them.
 *
 * @package Journeyline
 */

use Journeyline\Departures;
use Journeyline\Content_Types;

$trip  = wp_insert_post( array( 'post_type' => Content_Types::TRIP, 'post_status' => 'publish', 'post_title' => 'Status check trip' ) );
$in    = gmdate( 'Y-m-d', strtotime( '+60 days' ) );
$by    = gmdate( 'Y-m-d', strtotime( '+30 days' ) );
$past  = gmdate( 'Y-m-d', strtotime( '-1 day' ) );
$cases = array(
	'open (3 booked)'           => array( 3, 12, $by, false, false, 'open' ),
	'guaranteed (9 booked)'     => array( 9, 12, $by, false, false, 'guaranteed' ),
	'sold out (12 booked)'      => array( 12, 12, $by, false, false, 'sold-out' ),
	'closed (book-by passed)'   => array( 4, 12, $past, false, false, 'closed' ),
	'closed (by hand)'          => array( 4, 12, $by, true, false, 'closed' ),
	'cancelled'                 => array( 4, 12, $by, false, true, 'cancelled' ),
);
$ids  = array();
$fail = 0;
foreach ( $cases as $name => $c ) {
	$id    = wp_insert_post( array( 'post_type' => Content_Types::DEPARTURE, 'post_status' => 'publish', 'post_title' => $name ) );
	$ids[] = $id;
	update_post_meta( $id, 'jrnl_trip_id', $trip );
	update_post_meta( $id, 'jrnl_start', $in );
	update_post_meta( $id, 'jrnl_seats_booked', $c[0] );
	update_post_meta( $id, 'jrnl_seats_total', $c[1] );
	update_post_meta( $id, 'jrnl_book_by', $c[2] );
	update_post_meta( $id, 'jrnl_closed', $c[3] );
	update_post_meta( $id, 'jrnl_cancelled', $c[4] );
	$st = Departures::status( $id );
	$ok = $st['key'] === $c[5];
	$fail += $ok ? 0 : 1;
	printf( "%s %-26s -> %s%s\n", $ok ? 'PASS' : 'FAIL', $name, $st['label'], $st['note'] ? ' · ' . $st['note'] : '' );
}
$up = Departures::upcoming( array( 'trip' => $trip ) );
printf( "%s upcoming leaves out cancelled: %d of %d listed\n", 5 === count( $up ) ? 'PASS' : 'FAIL', count( $up ), count( $ids ) );
$fail += 5 === count( $up ) ? 0 : 1;
$seat = Departures::upcoming( array( 'trip' => $trip, 'seats_only' => true ) );
printf( "%s seats-only lists open and guaranteed: %d\n", 2 === count( $seat ) ? 'PASS' : 'FAIL', count( $seat ) );
$fail += 2 === count( $seat ) ? 0 : 1;
foreach ( array_merge( $ids, array( $trip ) ) as $id ) {
	wp_delete_post( $id, true );
}
echo $fail ? "\n$fail check(s) failed\n" : "\nAll checks passed\n";
