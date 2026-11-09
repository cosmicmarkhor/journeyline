<?php
/**
 * Search engine data: TouristTrip details on every trip page, so search results can show more.
 *
 * @package Journeyline
 */

namespace Journeyline;

defined( 'ABSPATH' ) || exit;

/**
 * Prints structured data for trip pages.
 */
final class Schema {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'wp_head', array( __CLASS__, 'trip' ) );
	}

	/**
	 * TouristTrip with its days and upcoming departures as offers.
	 */
	public static function trip() {
		if ( ! is_singular( Content_Types::TRIP ) ) {
			return;
		}
		$id   = get_queried_object_id();
		$data = array(
			'@context'    => 'https://schema.org',
			'@type'       => 'TouristTrip',
			'name'        => get_the_title( $id ),
			'url'         => get_permalink( $id ),
			'description' => wp_strip_all_tags( (string) ( get_post_meta( $id, 'jrnl_hook', true ) ?: get_the_excerpt( $id ) ) ),
			'provider'    => array( '@type' => 'Organization', 'name' => get_bloginfo( 'name' ), 'url' => home_url( '/' ) ),
		);
		$img = get_the_post_thumbnail_url( $id, 'full' );
		if ( $img ) {
			$data['image'] = $img;
		}
		$styles = wp_get_post_terms( $id, Content_Types::STYLE, array( 'fields' => 'names' ) );
		if ( $styles && ! is_wp_error( $styles ) ) {
			$data['touristType'] = $styles;
		}
		$days = (array) get_post_meta( $id, 'jrnl_itinerary', true );
		if ( $days ) {
			$items = array();
			foreach ( array_values( $days ) as $i => $d ) {
				$items[] = array(
					'@type'    => 'ListItem',
					'position' => $i + 1,
					'item'     => array( '@type' => 'TouristAttraction', 'name' => (string) ( $d['title'] ?? '' ), 'description' => (string) ( $d['body'] ?? '' ) ),
				);
			}
			$data['itinerary'] = array( '@type' => 'ItemList', 'numberOfItems' => count( $items ), 'itemListElement' => $items );
		}
		$offers = array();
		foreach ( Departures::upcoming( array( 'trip' => $id ) ) as $d ) {
			$price = (float) get_post_meta( $d, 'jrnl_price', true );
			if ( ! $price ) {
				continue;
			}
			$st       = Departures::status( $d );
			$offers[] = array(
				'@type'         => 'Offer',
				'price'         => $price,
				'priceCurrency' => (string) Settings::get( 'currency' ),
				'availability'  => 'sold-out' === $st['key'] ? 'https://schema.org/SoldOut' : ( $st['bookable'] ? 'https://schema.org/InStock' : 'https://schema.org/LimitedAvailability' ),
				'validThrough'  => (string) get_post_meta( $d, 'jrnl_book_by', true ),
				'url'           => get_permalink( $id ),
				'availabilityStarts' => (string) get_post_meta( $d, 'jrnl_start', true ),
			);
		}
		if ( $offers ) {
			$data['offers'] = $offers;
		}
		echo '<script type="application/ld+json">' . wp_json_encode( apply_filters( 'journeyline_trip_schema', $data, $id ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
	}
}
