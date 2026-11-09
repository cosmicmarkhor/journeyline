<?php
/**
 * Content types: trips, departures, destinations and trip styles.
 *
 * @package Journeyline
 */

namespace Journeyline;

defined( 'ABSPATH' ) || exit;

/**
 * Registers Journeyline's post types and taxonomies.
 */
final class Content_Types {

	const TRIP        = 'jrnl_trip';
	const DEPARTURE   = 'jrnl_departure';
	const DESTINATION = 'jrnl_destination';
	const STYLE       = 'jrnl_style';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'init', array( __CLASS__, 'maybe_flush' ), 99 );
	}

	/**
	 * Register everything.
	 */
	public static function register() {
		$one  = (string) Settings::get( 'trip_label' );
		$many = (string) Settings::get( 'trips_label' );

		register_post_type(
			self::TRIP,
			array(
				'labels'        => array(
					'name'               => $many,
					'singular_name'      => $one,
					/* translators: %s: name for one trip, e.g. Tour */
					'add_new_item'       => sprintf( __( 'Add new %s', 'journeyline' ), $one ),
					/* translators: %s: name for one trip */
					'edit_item'          => sprintf( __( 'Edit %s', 'journeyline' ), $one ),
					/* translators: %s: name for one trip */
					'view_item'          => sprintf( __( 'View %s', 'journeyline' ), $one ),
					/* translators: %s: name for several trips */
					'all_items'          => sprintf( __( 'All %s', 'journeyline' ), $many ),
					/* translators: %s: name for several trips */
					'search_items'       => sprintf( __( 'Search %s', 'journeyline' ), $many ),
					/* translators: %s: name for several trips */
					'not_found'          => sprintf( __( 'No %s found.', 'journeyline' ), strtolower( $many ) ),
					'menu_name'          => $many,
				),
				'public'        => true,
				'show_in_rest'  => true,
				'menu_position' => 5,
				'menu_icon'     => 'dashicons-palmtree',
				'supports'      => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'revisions' ),
				'has_archive'   => (string) Settings::get( 'trip_slug' ),
				'rewrite'       => array(
					'slug'       => (string) Settings::get( 'trip_slug' ),
					'with_front' => false,
				),
				'template'      => array(
					array( 'core/paragraph', array( 'placeholder' => __( 'The story of this trip, in a few short paragraphs.', 'journeyline' ) ) ),
				),
			)
		);

		register_post_type(
			self::DEPARTURE,
			array(
				'labels'             => array(
					'name'          => __( 'Departures', 'journeyline' ),
					'singular_name' => __( 'Departure', 'journeyline' ),
					'add_new_item'  => __( 'Add new departure', 'journeyline' ),
					'edit_item'     => __( 'Edit departure', 'journeyline' ),
					'all_items'     => __( 'Departures', 'journeyline' ),
					'not_found'     => __( 'No departures yet.', 'journeyline' ),
				),
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'show_in_menu'       => 'edit.php?post_type=' . self::TRIP,
				'show_in_rest'       => true,
				'supports'           => array( 'custom-fields' ),
				'rewrite'            => false,
			)
		);

		register_taxonomy(
			self::DESTINATION,
			array( self::TRIP ),
			array(
				'labels'            => array(
					'name'          => __( 'Destinations', 'journeyline' ),
					'singular_name' => __( 'Destination', 'journeyline' ),
					'add_new_item'  => __( 'Add new destination', 'journeyline' ),
					'edit_item'     => __( 'Edit destination', 'journeyline' ),
					'parent_item'   => __( 'Country', 'journeyline' ),
				),
				'description'       => __( 'Countries, and regions within them.', 'journeyline' ),
				'hierarchical'      => true,
				'public'            => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'rewrite'           => array(
					'slug'         => 'destinations',
					'with_front'   => false,
					'hierarchical' => true,
				),
			)
		);

		register_taxonomy(
			self::STYLE,
			array( self::TRIP ),
			array(
				'labels'            => array(
					'name'          => __( 'Trip styles', 'journeyline' ),
					'singular_name' => __( 'Trip style', 'journeyline' ),
					'add_new_item'  => __( 'Add new trip style', 'journeyline' ),
					'edit_item'     => __( 'Edit trip style', 'journeyline' ),
				),
				'description'       => __( 'The kinds of journey you offer, for example food, heritage or trekking.', 'journeyline' ),
				'hierarchical'      => false,
				'public'            => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'rewrite'           => array(
					'slug'       => 'ways-to-travel',
					'with_front' => false,
				),
			)
		);
	}

	/**
	 * Refresh permalinks once after the trip address setting changes.
	 */
	public static function maybe_flush() {
		if ( get_option( 'journeyline_flush_rewrite' ) ) {
			delete_option( 'journeyline_flush_rewrite' );
			flush_rewrite_rules();
		}
	}
}
