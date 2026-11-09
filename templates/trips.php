<?php
/**
 * Trips block. Variables: $attrs, $wrapper. Copy to yourtheme/journeyline/trips.php to change it.
 *
 * @package Journeyline
 */

use Journeyline\Blocks;
use Journeyline\Content_Types;
use Journeyline\Departures;

defined( 'ABSPATH' ) || exit;

$tax = array();
foreach ( array( 'destination' => Content_Types::DESTINATION, 'style' => Content_Types::STYLE ) as $key => $taxonomy ) {
	if ( ! empty( $attrs[ $key ] ) ) {
		$tax[] = array( 'taxonomy' => $taxonomy, 'field' => 'slug', 'terms' => array_map( 'sanitize_title', explode( ',', (string) $attrs[ $key ] ) ) );
	}
}
$trips = get_posts(
	array(
		'post_type'      => Content_Types::TRIP,
		'post_status'    => 'publish',
		'posts_per_page' => max( 1, (int) $attrs['limit'] ),
		'orderby'        => 'menu_order title',
		'order'          => 'ASC',
		'tax_query'      => $tax, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- small lists.
	)
);
if ( ! $trips ) {
	echo '<div ' . $wrapper . '><p class="jl-empty">' . esc_html__( 'No trips to show yet.', 'journeyline' ) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-escaped wrapper attributes.
	return;
}
$filters = array();
echo '<div ' . $wrapper . ' data-jl-filter-scope>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-escaped wrapper attributes.
ob_start();
echo '<ul class="jl-cards" role="list">';
foreach ( $trips as $t ) {
	$dest   = wp_get_post_terms( $t->ID, Content_Types::DESTINATION, array( 'parent' => 0 ) );
	$styles = wp_get_post_terms( $t->ID, Content_Types::STYLE );
	$keys   = array();
	foreach ( array_merge( $dest, $styles ) as $term ) {
		$keys[]                                   = $term->taxonomy . ':' . $term->slug;
		$filters[ $term->taxonomy ][ $term->slug ] = $term->name;
	}
	$days  = (int) get_post_meta( $t->ID, 'jrnl_days', true );
	$price = Blocks::from_price( $t->ID );
	$next  = Departures::upcoming( array( 'trip' => $t->ID, 'limit' => 1 ) );
	echo '<li class="jl-card" data-jl-keys="' . esc_attr( implode( ' ', $keys ) ) . '"><a class="jl-card-link" href="' . esc_url( get_permalink( $t ) ) . '">';
	echo '<span class="jl-card-media">' . ( has_post_thumbnail( $t ) ? get_the_post_thumbnail( $t, 'large', array( 'loading' => 'lazy', 'sizes' => '(max-width: 700px) 100vw, 33vw' ) ) : '' );
	if ( $styles ) {
		echo '<span class="jl-chip">' . esc_html( $styles[0]->name ) . '</span>';
	}
	echo '</span><span class="jl-card-body">';
	if ( $dest ) {
		echo '<span class="jl-kicker">' . esc_html( $dest[0]->name ) . '</span>';
	}
	echo '<span class="jl-card-title">' . esc_html( get_the_title( $t ) ) . '</span>';
	$hook = (string) get_post_meta( $t->ID, 'jrnl_hook', true );
	if ( $hook ) {
		echo '<span class="jl-card-hook">' . esc_html( $hook ) . '</span>';
	}
	echo '<span class="jl-card-meta">';
	if ( $days ) {
		/* translators: %d: number of days */
		echo '<span>' . esc_html( sprintf( _n( '%d day', '%d days', $days, 'journeyline' ), $days ) ) . '</span>';
	}
	if ( $price ) {
		/* translators: %s: price */
		echo '<span class="jl-price">' . esc_html( sprintf( __( 'From %s', 'journeyline' ), Departures::price( $price ) ) ) . '</span>';
	}
	echo '</span>';
	if ( $next ) {
		$st = Departures::status( $next[0] );
		/* translators: 1: date, 2: status note */
		echo '<span class="jl-card-next">' . esc_html( sprintf( __( 'Next: %1$s · %2$s', 'journeyline' ), wp_date( 'j M Y', strtotime( (string) get_post_meta( $next[0], 'jrnl_start', true ) ) ), 'guaranteed' === $st['key'] ? $st['label'] : $st['note'] ) ) . '</span>';
	}
	echo '</span></a></li>';
}
echo '</ul>';
$cards = (string) ob_get_clean();

if ( ! empty( $attrs['showFilters'] ) && $filters ) {
	echo '<div class="jl-filters" role="group" aria-label="' . esc_attr__( 'Filter trips', 'journeyline' ) . '"><button type="button" class="jl-filter" aria-pressed="true" data-jl-key="">' . esc_html__( 'All', 'journeyline' ) . '</button>';
	foreach ( $filters as $taxonomy => $terms ) {
		asort( $terms );
		foreach ( $terms as $slug => $name ) {
			echo '<button type="button" class="jl-filter" aria-pressed="false" data-jl-key="' . esc_attr( $taxonomy . ':' . $slug ) . '">' . esc_html( $name ) . '</button>';
		}
	}
	echo '</div><p class="jl-count" aria-live="polite"></p>';
}
echo $cards; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
echo '</div>';
