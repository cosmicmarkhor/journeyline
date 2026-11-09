<?php
/**
 * Updates from GitHub releases.
 *
 * Each plugin that uses this sets an "Update URI" header pointing at its GitHub repository and
 * attaches a ready-made zip (named after the plugin folder) to every release. WordPress then shows
 * "Update available" like any other plugin, and its automatic updates work too.
 *
 * Private repositories (paid add-ons) need a read-only GitHub key. The site owner pastes it into
 * Settings; it is stored in the database, never in code, and only sent to api.github.com.
 *
 * @package Journeyline
 */

namespace Journeyline;

defined( 'ABSPATH' ) || exit;

/**
 * GitHub release updater. Journeyline and its add-ons each register once.
 */
final class Updater {

	/**
	 * Registered plugins: basename => [repo, slug, version].
	 *
	 * @var array
	 */
	private static $plugins = array();

	/**
	 * Whether the hooks are in place.
	 *
	 * @var bool
	 */
	private static $hooked = false;

	/**
	 * Register a plugin for updates.
	 *
	 * @param string $file    Main plugin file.
	 * @param string $repo    GitHub "owner/name".
	 * @param string $version Installed version.
	 */
	public static function register( $file, $repo, $version ) {
		$base                   = plugin_basename( $file );
		self::$plugins[ $base ] = array(
			'repo'    => $repo,
			'slug'    => dirname( $base ),
			'version' => $version,
		);
		if ( ! self::$hooked ) {
			self::$hooked = true;
			add_filter( 'update_plugins_github.com', array( __CLASS__, 'check' ), 10, 4 );
			add_filter( 'plugins_api', array( __CLASS__, 'info' ), 20, 3 );
			add_filter( 'http_request_args', array( __CLASS__, 'auth' ), 10, 2 );
		}
	}

	/**
	 * The read-only key for private repositories, if the owner has entered one.
	 *
	 * @return string
	 */
	private static function token() {
		return (string) get_option( 'journeyline_github_token', '' );
	}

	/**
	 * Latest release for a repository, cached for six hours.
	 *
	 * @param string $repo owner/name.
	 * @return array|null
	 */
	private static function latest( $repo ) {
		$key    = 'jl_rel_' . md5( $repo );
		$cached = get_site_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached ? $cached : null;
		}
		$res = wp_remote_get( 'https://api.github.com/repos/' . $repo . '/releases/latest', array( 'timeout' => 10, 'headers' => array( 'Accept' => 'application/vnd.github+json' ) ) );
		$out = array();
		if ( ! is_wp_error( $res ) && 200 === wp_remote_retrieve_response_code( $res ) ) {
			$rel = json_decode( wp_remote_retrieve_body( $res ), true );
			if ( is_array( $rel ) && ! empty( $rel['tag_name'] ) ) {
				$zip = '';
				foreach ( (array) ( $rel['assets'] ?? array() ) as $asset ) {
					if ( substr( (string) $asset['name'], -4 ) === '.zip' ) {
						$zip = (string) $asset['url'];
						break;
					}
				}
				$out = array(
					'version' => ltrim( (string) $rel['tag_name'], 'vV' ),
					'zip'     => $zip,
					'notes'   => (string) ( $rel['body'] ?? '' ),
					'url'     => (string) ( $rel['html_url'] ?? '' ),
					'date'    => (string) ( $rel['published_at'] ?? '' ),
				);
			}
		}
		set_site_transient( $key, $out, $out ? 6 * HOUR_IN_SECONDS : HOUR_IN_SECONDS );
		return $out ? $out : null;
	}

	/**
	 * Tell WordPress about a newer release.
	 *
	 * @param array|false $update      Update data so far.
	 * @param array       $plugin_data Plugin headers.
	 * @param string      $file        Plugin basename.
	 * @param string[]    $locales     Locales.
	 * @return array|false
	 */
	public static function check( $update, $plugin_data, $file, $locales ) {
		if ( ! isset( self::$plugins[ $file ] ) ) {
			return $update;
		}
		$p   = self::$plugins[ $file ];
		$rel = self::latest( $p['repo'] );
		if ( ! $rel || ! $rel['zip'] ) {
			return $update;
		}
		return array(
			'id'           => 'https://github.com/' . $p['repo'],
			'slug'         => $p['slug'],
			'version'      => $rel['version'],
			'new_version'  => $rel['version'],
			'url'          => 'https://github.com/' . $p['repo'],
			'package'      => $rel['zip'],
			'requires'     => $plugin_data['RequiresWP'] ?? '',
			'requires_php' => $plugin_data['RequiresPHP'] ?? '',
		);
	}

	/**
	 * Release notes in the "View details" window.
	 *
	 * @param false|object|array $result Result so far.
	 * @param string             $action API action.
	 * @param object             $args   Arguments.
	 * @return false|object|array
	 */
	public static function info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) ) {
			return $result;
		}
		foreach ( self::$plugins as $file => $p ) {
			if ( $p['slug'] !== $args->slug ) {
				continue;
			}
			$rel = self::latest( $p['repo'] );
			if ( ! $rel ) {
				return $result;
			}
			$data = get_plugin_data( WP_PLUGIN_DIR . '/' . $file, false, false );
			return (object) array(
				'name'          => $data['Name'],
				'slug'          => $p['slug'],
				'version'       => $rel['version'],
				'author'        => $data['Author'],
				'homepage'      => 'https://github.com/' . $p['repo'],
				'last_updated'  => $rel['date'],
				'sections'      => array( 'changelog' => wp_kses_post( wpautop( esc_html( $rel['notes'] ) ) ) ),
				'download_link' => $rel['zip'],
			);
		}
		return $result;
	}

	/**
	 * Ask GitHub for the zip file itself, and add the key for private repositories.
	 *
	 * @param array  $args HTTP arguments.
	 * @param string $url  Address being fetched.
	 * @return array
	 */
	public static function auth( $args, $url ) {
		if ( 0 !== strpos( $url, 'https://api.github.com/repos/' ) ) {
			return $args;
		}
		$known = false;
		foreach ( self::$plugins as $p ) {
			if ( 0 === strpos( $url, 'https://api.github.com/repos/' . $p['repo'] . '/' ) ) {
				$known = true;
				break;
			}
		}
		if ( ! $known ) {
			return $args;
		}
		if ( false !== strpos( $url, '/releases/assets/' ) ) {
			$args['headers']['Accept'] = 'application/octet-stream';
		}
		$token = self::token();
		if ( $token ) {
			$args['headers']['Authorization'] = 'Bearer ' . $token;
		}
		return $args;
	}
}
