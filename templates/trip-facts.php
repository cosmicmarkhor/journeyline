<?php
/**
 * Trip facts block. Variables: $attrs, $trip, $wrapper. Copy to yourtheme/journeyline/trip-facts.php to change it.
 *
 * @package Journeyline
 */

use Journeyline\Blocks;
use Journeyline\Departures;
use Journeyline\Settings;

defined( 'ABSPATH' ) || exit;

if ( ! $trip ) {
	return;
}
$facts = array();
$price = Blocks::from_price( $trip );
if ( $price ) {
	$facts[] = array( __( 'From', 'journeyline' ), Departures::price( $price ), __( 'per person', 'journeyline' ) );
}
$days   = (int) get_post_meta( $trip, 'jrnl_days', true );
$nights = (int) get_post_meta( $trip, 'jrnl_nights', true );
if ( $days ) {
	/* translators: %d: days */
	$facts[] = array( __( 'Length', 'journeyline' ), sprintf( _n( '%d day', '%d days', $days, 'journeyline' ), $days ), $nights ? sprintf( _n( '%d night', '%d nights', $nights, 'journeyline' ), $nights ) : '' ); // phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment -- obvious.
}
$level = (int) get_post_meta( $trip, 'jrnl_activity', true );
if ( $level ) {
	/* translators: %d: level 1 to 5 */
	$facts[] = array( __( 'Activity level', 'journeyline' ), sprintf( __( '%d of 5', 'journeyline' ), $level ), '', $level );
}
$min = (int) get_post_meta( $trip, 'jrnl_group_min', true );
$max = (int) get_post_meta( $trip, 'jrnl_group_max', true );
$min = $min ? $min : (int) Settings::get( 'min_group' );
$max = $max ? $max : (int) Settings::get( 'max_group' );
/* translators: 1: smallest group, 2: largest group */
$facts[] = array( __( 'Group size', 'journeyline' ), sprintf( __( '%1$d to %2$d', 'journeyline' ), $min, $max ), __( 'guests', 'journeyline' ) );
$next = Departures::upcoming( array( 'trip' => $trip, 'limit' => 1 ) );
if ( $next ) {
	$st      = Departures::status( $next[0] );
	$facts[] = array( __( 'Next departure', 'journeyline' ), wp_date( 'j M Y', strtotime( (string) get_post_meta( $next[0], 'jrnl_start', true ) ) ), trim( $st['note'] . ( 'guaranteed' === $st['key'] ? ' · ' . $st['label'] : '' ), ' ·' ) );
}
echo '<dl ' . $wrapper . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-escaped wrapper attributes.
foreach ( $facts as $f ) {
	echo '<div class="jl-fact"><dt>' . esc_html( $f[0] ) . '</dt><dd><span class="jl-fact-value">' . esc_html( $f[1] ) . '</span>';
	if ( ! empty( $f[3] ) ) {
		echo '<span class="jl-meter" aria-hidden="true">';
		for ( $i = 1; $i <= 5; $i++ ) {
			echo '<i' . ( $i <= $f[3] ? ' class="on"' : '' ) . '></i>';
		}
		echo '</span>';
	}
	if ( $f[2] ) {
		echo '<span class="jl-fact-note">' . esc_html( $f[2] ) . '</span>';
	}
	echo '</dd></div>';
}
echo '</dl>';
