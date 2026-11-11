<?php
/**
 * Plugin Name:       Journeyline
 * Plugin URI:        https://github.com/cosmicmarkhor/journeyline
 * Description:       Trips, departures, destinations and trip styles for small travel companies. Works with any modern WordPress theme.
 * Version:           0.3.1
 * Requires at least: 6.7
 * Requires PHP:      8.1
 * Author:            IngotKit
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       journeyline
 * Domain Path:       /languages
 * Update URI:        https://github.com/cosmicmarkhor/journeyline
 *
 * @package Journeyline
 */

namespace Journeyline;

defined( 'ABSPATH' ) || exit;

const VERSION = '0.3.1';
const FILE    = __FILE__;
define( __NAMESPACE__ . '\DIR', plugin_dir_path( __FILE__ ) );
define( __NAMESPACE__ . '\URL', plugin_dir_url( __FILE__ ) );

require_once DIR . 'includes/class-plugin.php';

Plugin::boot();

register_activation_hook( __FILE__, array( __NAMESPACE__ . '\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( __NAMESPACE__ . '\Plugin', 'deactivate' ) );
