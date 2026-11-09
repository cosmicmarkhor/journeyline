<?php
/**
 * Trip list block: highlights, included or not included.
 * Variables: $attrs, $trip, $wrapper. Copy to yourtheme/journeyline/trip-list.php to change it.
 *
 * @package Journeyline
 */

use Journeyline\Blocks;

defined( 'ABSPATH' ) || exit;

$which = in_array( $attrs['which'], array( 'highlights', 'included', 'excluded' ), true ) ? $attrs['which'] : 'highlights';
$items = $trip ? (array) get_post_meta( $trip, 'jrnl_' . $which, true ) : array();
if ( ! $items ) {
	return;
}
$mark = 'excluded' === $which
	? '<svg class="jl-cross" viewBox="0 0 16 16" width="16" height="16" aria-hidden="true" focusable="false"><path d="M4 4l8 8M12 4l-8 8" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>'
	: Blocks::tick();
echo '<ul ' . $wrapper . ' data-which="' . esc_attr( $which ) . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-escaped wrapper attributes.
foreach ( $items as $item ) {
	echo '<li>' . $mark . '<span>' . esc_html( (string) $item ) . '</span></li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG.
}
echo '</ul>';
