<?php
/**
 * Plugin bootstrap: loads the parts and wires their hooks.
 *
 * @package Journeyline
 */

namespace Journeyline;

defined( 'ABSPATH' ) || exit;

/**
 * Starts Journeyline.
 */
final class Plugin {

	/**
	 * Load every part and hook it up. Runs once, when the plugin file loads.
	 */
	public static function boot() {
		foreach ( array( 'settings', 'content-types', 'fields', 'departures', 'trip-editor', 'enquiries', 'blocks', 'schema', 'term-images', 'updater' ) as $part ) {
			require_once DIR . 'includes/class-' . $part . '.php';
		}
		add_action( 'init', array( __CLASS__, 'load_textdomain' ), 1 );
		Settings::init();
		Content_Types::init();
		Fields::init();
		Departures::init();
		Trip_Editor::init();
		Enquiries::init();
		Blocks::init();
		Schema::init();
		Term_Images::init();
		Updater::register( FILE, 'cosmicmarkhor/journeyline', VERSION );
	}

	/**
	 * Translations.
	 */
	public static function load_textdomain() {
		load_plugin_textdomain( 'journeyline', false, dirname( plugin_basename( FILE ) ) . '/languages' );
	}

	/**
	 * On activation: register content types, then refresh permalinks so trip pages work at once.
	 */
	public static function activate() {
		require_once DIR . 'includes/class-settings.php';
		require_once DIR . 'includes/class-content-types.php';
		Content_Types::register();
		flush_rewrite_rules();
	}

	/**
	 * On deactivation: tidy permalinks. Content and settings stay until the plugin is deleted.
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}
}
