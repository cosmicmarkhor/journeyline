<?php
/**
 * Blocks: trips, departures, itinerary, trip facts, trip lists and the enquiry form.
 *
 * Every block is drawn on the server from a small template. A theme can replace any template
 * by adding a file of the same name in a "journeyline" folder inside the theme.
 *
 * @package Journeyline
 */

namespace Journeyline;

defined( 'ABSPATH' ) || exit;

/**
 * Block registration and rendering.
 */
final class Blocks {

	/**
	 * Block names => default attributes and their types.
	 *
	 * @return array
	 */
	public static function blocks() {
		return array(
			'trips'        => array(
				'title' => __( 'Trips', 'journeyline' ),
				'desc'  => __( 'A grid of trips, optionally filtered by destination or trip style.', 'journeyline' ),
				'icon'  => 'palmtree',
				'attrs' => array( 'destination' => array( 'string', '' ), 'style' => array( 'string', '' ), 'limit' => array( 'number', 12 ), 'showFilters' => array( 'boolean', false ) ),
			),
			'departures'   => array(
				'title' => __( 'Departures', 'journeyline' ),
				'desc'  => __( 'Upcoming departure dates with status, seats and price. On a trip page it shows that trip only.', 'journeyline' ),
				'icon'  => 'calendar-alt',
				'attrs' => array( 'scope' => array( 'string', 'auto' ), 'limit' => array( 'number', 0 ), 'groupByMonth' => array( 'boolean', false ), 'showFilters' => array( 'boolean', false ) ),
			),
			'itinerary'    => array(
				'title' => __( 'Itinerary', 'journeyline' ),
				'desc'  => __( 'The day-by-day plan of the current trip.', 'journeyline' ),
				'icon'  => 'list-view',
				'attrs' => array( 'openFirst' => array( 'boolean', true ) ),
			),
			'trip-facts'   => array(
				'title' => __( 'Trip facts', 'journeyline' ),
				'desc'  => __( 'Price from, length, activity level, group size and next departure of the current trip.', 'journeyline' ),
				'icon'  => 'info-outline',
				'attrs' => array(),
			),
			'trip-list'    => array(
				'title' => __( 'Trip list', 'journeyline' ),
				'desc'  => __( 'Highlights, what is included, or what is not, for the current trip.', 'journeyline' ),
				'icon'  => 'yes-alt',
				'attrs' => array( 'which' => array( 'string', 'highlights' ) ),
			),
			'enquiry-form' => array(
				'title' => __( 'Enquiry form', 'journeyline' ),
				'desc'  => __( 'A short form that saves enquiries in WordPress and emails you.', 'journeyline' ),
				'icon'  => 'email-alt',
				'attrs' => array( 'showTopic' => array( 'boolean', false ), 'showTravellers' => array( 'boolean', true ), 'button' => array( 'string', '' ) ),
			),
		);
	}

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'front_assets' ) );
	}

	/**
	 * Register assets and blocks.
	 */
	public static function register() {
		wp_register_style( 'journeyline', URL . 'assets/front.css', array(), VERSION );
		wp_register_script( 'journeyline', URL . 'assets/front.js', array(), VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
		wp_localize_script(
			'journeyline',
			'JOURNEYLINE',
			array(
				'endpoint' => esc_url_raw( rest_url( Enquiries::ROUTE . '/enquiry' ) ),
				'sending'  => __( 'Sending…', 'journeyline' ),
				'failed'   => __( 'Sorry, that did not send. Please check your connection and try again.', 'journeyline' ),
				'errName'    => __( 'Enter your name so we know who to reply to.', 'journeyline' ),
				'errEmail'   => __( 'Enter an email address like name@example.com.', 'journeyline' ),
				'errConsent' => __( 'Tick the box so we can reply to you.', 'journeyline' ),
				/* translators: 1: number shown, 2: total */
				'showing'    => __( 'Showing %1$s of %2$s', 'journeyline' ),
			)
		);
		wp_register_script( 'journeyline-editor', URL . 'assets/editor.js', array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render', 'wp-i18n' ), VERSION, true );
		$meta = array();
		foreach ( self::blocks() as $slug => $b ) {
			$attrs = array();
			foreach ( $b['attrs'] as $k => $a ) {
				$attrs[ $k ] = array( 'type' => $a[0], 'default' => $a[1] );
			}
			register_block_type(
				'journeyline/' . $slug,
				array(
					'api_version'     => 3,
					'title'           => $b['title'],
					'description'     => $b['desc'],
					'category'        => 'widgets',
					'icon'            => $b['icon'],
					'attributes'      => $attrs,
					'supports'        => array( 'align' => array( 'wide', 'full' ), 'html' => false, 'anchor' => true ),
					'editor_script'   => 'journeyline-editor',
					'style'           => 'journeyline',
					'view_script'     => 'journeyline',
					'render_callback' => function ( $attributes ) use ( $slug ) {
						return self::render( $slug, (array) $attributes );
					},
				)
			);
			$meta[ $slug ] = array( 'title' => $b['title'], 'attrs' => $b['attrs'] );
		}
		wp_add_inline_script( 'journeyline-editor', 'window.JOURNEYLINE_BLOCKS = Object.assign( window.JOURNEYLINE_BLOCKS || {}, ' . wp_json_encode( $meta ) . ' );', 'before' );
	}

	/**
	 * Styles and script load only where a Journeyline block is used, or on trip pages.
	 */
	public static function front_assets() {
		if ( is_singular( Content_Types::TRIP ) || is_post_type_archive( Content_Types::TRIP ) || is_tax( array( Content_Types::DESTINATION, Content_Types::STYLE ) ) ) {
			wp_enqueue_style( 'journeyline' );
			wp_enqueue_script( 'journeyline' );
		}
	}

	/**
	 * The trip a block refers to: the one being viewed.
	 *
	 * @return int
	 */
	public static function current_trip() {
		$id = get_the_ID();
		return ( $id && Content_Types::TRIP === get_post_type( $id ) ) ? (int) $id : 0;
	}

	/**
	 * Render one block from its template.
	 *
	 * @param string $slug  Block name.
	 * @param array  $attrs Attributes.
	 * @return string
	 */
	public static function render( $slug, $attrs ) {
		$file = locate_template( 'journeyline/' . $slug . '.php' );
		if ( ! $file ) {
			$file = DIR . 'templates/' . $slug . '.php';
		}
		if ( ! file_exists( $file ) ) {
			return '';
		}
		$trip    = self::current_trip();
		$wrapper = function_exists( 'get_block_wrapper_attributes' ) ? get_block_wrapper_attributes( array( 'class' => 'jl jl-' . $slug ) ) : 'class="jl jl-' . esc_attr( $slug ) . '"';
		ob_start();
		include $file;
		return (string) ob_get_clean();
	}

	/**
	 * Lowest upcoming departure price for a trip, or its "from" price.
	 *
	 * @param int $trip Trip ID.
	 * @return float
	 */
	public static function from_price( $trip ) {
		$set = (float) get_post_meta( $trip, 'jrnl_from_price', true );
		if ( $set > 0 ) {
			return $set;
		}
		$low = 0.0;
		foreach ( Departures::upcoming( array( 'trip' => $trip ) ) as $d ) {
			$p = (float) get_post_meta( $d, 'jrnl_price', true );
			if ( $p > 0 && ( 0.0 === $low || $p < $low ) ) {
				$low = $p;
			}
		}
		return $low;
	}

	/**
	 * Small checkmark, for templates.
	 *
	 * @return string
	 */
	public static function tick() {
		return '<svg class="jl-tick" viewBox="0 0 16 16" width="16" height="16" aria-hidden="true" focusable="false"><path d="m3 8.5 3.2 3L13 4.5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
	}
}
