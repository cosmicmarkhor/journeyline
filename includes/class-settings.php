<?php
/**
 * Settings: everything that differs between travel companies lives here, never in code.
 *
 * @package Journeyline
 */

namespace Journeyline;

defined( 'ABSPATH' ) || exit;

/**
 * Stores and edits Journeyline's settings (one option, an array).
 */
final class Settings {

	const OPTION = 'journeyline_settings';
	const PAGE   = 'journeyline-settings';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Defaults. A new install works without visiting the settings screen.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'currency'        => 'USD',
			'currency_label'  => 'US$',
			'trip_slug'       => 'trips',
			'min_group'       => 8,
			'max_group'       => 12,
			'guarantee_at'    => 8,
			'enquiry_email'   => '',
			'trip_label'      => __( 'Trip', 'journeyline' ),
			'trips_label'     => __( 'Trips', 'journeyline' ),
		);
	}

	/**
	 * One setting, or all of them.
	 *
	 * @param string|null $key Setting name, or null for every setting.
	 * @return mixed
	 */
	public static function get( $key = null ) {
		$all = wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
		if ( null === $key ) {
			return $all;
		}
		return $all[ $key ] ?? null;
	}

	/**
	 * Admin menu entry under the trips menu.
	 */
	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . Content_Types::TRIP,
			__( 'Journeyline settings', 'journeyline' ),
			__( 'Settings', 'journeyline' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Register the option with a sanitising callback.
	 */
	public static function register() {
		register_setting(
			self::PAGE,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	/**
	 * Clean every value before it is saved.
	 *
	 * @param mixed $input Raw form values.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$d     = self::defaults();
		$out   = array();
		$out['currency']       = strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', (string) ( $input['currency'] ?? $d['currency'] ) ), 0, 3 ) ) ?: $d['currency'];
		$out['currency_label'] = sanitize_text_field( (string) ( $input['currency_label'] ?? $d['currency_label'] ) );
		$out['trip_slug']      = sanitize_title( (string) ( $input['trip_slug'] ?? $d['trip_slug'] ) ) ?: $d['trip_slug'];
		$out['min_group']      = max( 1, absint( $input['min_group'] ?? $d['min_group'] ) );
		$out['max_group']      = max( $out['min_group'], absint( $input['max_group'] ?? $d['max_group'] ) );
		$out['guarantee_at']   = min( $out['max_group'], max( 1, absint( $input['guarantee_at'] ?? $d['guarantee_at'] ) ) );
		$out['enquiry_email']  = sanitize_email( (string) ( $input['enquiry_email'] ?? '' ) );
		$out['trip_label']     = sanitize_text_field( (string) ( $input['trip_label'] ?? $d['trip_label'] ) ) ?: $d['trip_label'];
		$out['trips_label']    = sanitize_text_field( (string) ( $input['trips_label'] ?? $d['trips_label'] ) ) ?: $d['trips_label'];
		if ( self::get( 'trip_slug' ) !== $out['trip_slug'] ) {
			update_option( 'journeyline_flush_rewrite', 1 );
		}
		return $out;
	}

	/**
	 * The settings screen.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s    = self::get();
		$name = self::OPTION;
		$rows = array(
			'trip_label'     => array( __( 'Name for one trip', 'journeyline' ), 'text', __( 'For example Trip, Tour or Journey. Used in menus and labels.', 'journeyline' ) ),
			'trips_label'    => array( __( 'Name for several trips', 'journeyline' ), 'text', '' ),
			'trip_slug'      => array( __( 'Web address for trips', 'journeyline' ), 'text', __( 'The part of the address before each trip, for example trips or tours.', 'journeyline' ) ),
			'currency'       => array( __( 'Currency code', 'journeyline' ), 'text', __( 'Three letters, for example USD.', 'journeyline' ) ),
			'currency_label' => array( __( 'Currency shown on prices', 'journeyline' ), 'text', __( 'For example US$.', 'journeyline' ) ),
			'min_group'      => array( __( 'Smallest group on a departure', 'journeyline' ), 'number', '' ),
			'max_group'      => array( __( 'Largest group on a departure', 'journeyline' ), 'number', '' ),
			'guarantee_at'   => array( __( 'Departure guaranteed once this many have booked', 'journeyline' ), 'number', '' ),
			'enquiry_email'  => array( __( 'Send enquiries to', 'journeyline' ), 'email', __( 'Leave empty to use the site\'s admin email.', 'journeyline' ) ),
		);
		echo '<div class="wrap"><h1>' . esc_html__( 'Journeyline settings', 'journeyline' ) . '</h1><form method="post" action="options.php">';
		settings_fields( self::PAGE );
		echo '<table class="form-table" role="presentation">';
		foreach ( $rows as $key => $row ) {
			$id = 'journeyline-' . $key;
			printf(
				'<tr><th scope="row"><label for="%1$s">%2$s</label></th><td><input type="%3$s" id="%1$s" name="%4$s[%5$s]" value="%6$s" class="regular-text"%7$s>%8$s</td></tr>',
				esc_attr( $id ),
				esc_html( $row[0] ),
				esc_attr( $row[1] ),
				esc_attr( $name ),
				esc_attr( $key ),
				esc_attr( (string) $s[ $key ] ),
				'number' === $row[1] ? ' min="1" step="1"' : '',
				$row[2] ? '<p class="description">' . esc_html( $row[2] ) . '</p>' : ''
			);
		}
		echo '</table>';
		submit_button();
		echo '</form></div>';
	}
}
