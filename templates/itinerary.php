<?php
/**
 * Itinerary block: days as native open-and-close panels (no script needed).
 * Variables: $attrs, $trip, $wrapper. Copy to yourtheme/journeyline/itinerary.php to change it.
 *
 * @package Journeyline
 */

defined( 'ABSPATH' ) || exit;

$days = $trip ? (array) get_post_meta( $trip, 'jrnl_itinerary', true ) : array();
if ( ! $days ) {
	return;
}
echo '<div ' . $wrapper . '><ol class="jl-days" role="list">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-escaped wrapper attributes.
foreach ( array_values( $days ) as $i => $d ) {
	$open = ( 0 === $i && ! empty( $attrs['openFirst'] ) ) ? ' open' : '';
	echo '<li class="jl-day" data-stop="' . esc_attr( (string) ( $d['stop'] ?? '' ) ) . '"><details' . $open . '><summary>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed string.
	/* translators: %d: day number */
	echo '<span class="jl-day-no">' . esc_html( sprintf( __( 'Day %d', 'journeyline' ), $i + 1 ) ) . '</span><span class="jl-day-title">' . esc_html( (string) ( $d['title'] ?? '' ) ) . '</span></summary>';
	echo '<div class="jl-day-body">';
	if ( ! empty( $d['body'] ) ) {
		echo wp_kses_post( wpautop( esc_html( (string) $d['body'] ) ) );
	}
	$meta = array();
	if ( ! empty( $d['meals'] ) ) {
		/* translators: %s: meals, e.g. B, L, D */
		$meta[] = sprintf( __( 'Meals: %s', 'journeyline' ), $d['meals'] );
	}
	if ( ! empty( $d['overnight'] ) ) {
		/* translators: %s: place */
		$meta[] = sprintf( __( 'Overnight: %s', 'journeyline' ), $d['overnight'] );
	}
	if ( $meta ) {
		echo '<p class="jl-day-meta">';
		foreach ( $meta as $m ) {
			echo '<span>' . esc_html( $m ) . '</span>';
		}
		echo '</p>';
	}
	echo '</div></details></li>';
}
echo '</ol></div>';
