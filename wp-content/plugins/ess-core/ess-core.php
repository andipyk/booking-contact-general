<?php
/**
 * Plugin Name:       Eastern Standard Core
 * Description:       Projects, services, consultation booking and enquiries for Eastern Standard Studio. All site functionality lives here so that switching themes never destroys studio data.
 * Version:           1.0.0
 * Requires at least: 6.9
 * Requires PHP:      8.1
 * Author:            Andi Syafrianda
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ess-core
 *
 * @package ESS\Core
 */

declare( strict_types = 1 );

namespace ESS\Core;

defined( 'ABSPATH' ) || exit;

const VERSION  = '1.0.0';
const FILE     = __FILE__;
const REST_NS  = 'ess/v1';

define( __NAMESPACE__ . '\DIR', plugin_dir_path( __FILE__ ) );
define( __NAMESPACE__ . '\URL', plugin_dir_url( __FILE__ ) );

require_once DIR . 'includes/class-post-types.php';
require_once DIR . 'includes/class-meta.php';
require_once DIR . 'includes/class-bindings.php';
require_once DIR . 'includes/class-availability.php';
require_once DIR . 'includes/class-slot-lock.php';
require_once DIR . 'includes/class-anti-spam.php';
require_once DIR . 'includes/class-webhook.php';
require_once DIR . 'includes/class-booking-rest.php';
require_once DIR . 'includes/class-inquiry-rest.php';
require_once DIR . 'includes/class-cron.php';
require_once DIR . 'includes/class-blocks.php';
require_once DIR . 'includes/class-admin.php';

/**
 * Boot every subsystem.
 *
 * Runs on `plugins_loaded` so that post types and meta register before `init`
 * fires, which the Block Bindings `core/post-meta` source depends on.
 */
function boot(): void {
	Post_Types::init();
	Meta::init();
	Bindings::init();
	Slot_Lock::init();
	Webhook::init();
	Booking_REST::init();
	Inquiry_REST::init();
	Cron::init();
	Blocks::init();
	Admin::init();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\boot' );

/**
 * Create the slot-lock table and schedule cron on activation.
 *
 * Registering post types here too means the rewrite rules flushed below
 * actually include the project and service archives.
 */
function activate(): void {
	Post_Types::init();
	Post_Types::register();
	Slot_Lock::create_table();
	Cron::schedule();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, __NAMESPACE__ . '\activate' );

/**
 * Leave the data alone on deactivation; only stop the scheduled work.
 */
function deactivate(): void {
	Cron::unschedule();
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\deactivate' );

/**
 * Write to the debug log with a consistent prefix.
 *
 * Outbound webhook problems must never surface to a visitor mid-booking, so
 * failures are logged here and swallowed rather than thrown.
 */
function log( string $message, array $context = [] ): void {
	if ( ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
		return;
	}
	$suffix = $context ? ' ' . wp_json_encode( $context ) : '';
	error_log( '[ess-core] ' . $message . $suffix );
}

/**
 * The studio's wall-clock timezone. Slots are generated here, stored as UTC.
 */
function studio_timezone(): \DateTimeZone {
	$tz = getenv( 'ESS_STUDIO_TIMEZONE' ) ?: 'America/New_York';
	try {
		return new \DateTimeZone( $tz );
	} catch ( \Exception $e ) {
		log( 'Invalid ESS_STUDIO_TIMEZONE, falling back to UTC', [ 'value' => $tz ] );
		return new \DateTimeZone( 'UTC' );
	}
}
