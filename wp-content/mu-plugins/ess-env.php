<?php
/**
 * Plugin Name: Eastern Standard — Environment Glue
 * Description: Wires local infrastructure (Mailpit SMTP, sane demo defaults) into WordPress. Deliberately thin: everything that is site functionality lives in the ess-core plugin instead, so this file can be dropped on a real host without taking features with it.
 * Version:     1.0.0
 * License:     GPL-2.0-or-later
 *
 * @package ESS\Env
 */

declare( strict_types = 1 );

namespace ESS\Env;

defined( 'ABSPATH' ) || exit;

/**
 * Send all mail through Mailpit.
 *
 * The container has no MTA, so without this wp_mail() fails silently and the
 * booking confirmation simply never appears. Pointing PHPMailer at Mailpit
 * means every message is captured and inspectable at the Mailpit UI.
 */
add_action(
	'phpmailer_init',
	static function ( $phpmailer ): void {
		$host = getenv( 'ESS_SMTP_HOST' );
		$port = getenv( 'ESS_SMTP_PORT' );

		if ( ! $host || ! $port ) {
			return;
		}

		$phpmailer->isSMTP();
		$phpmailer->Host       = $host;
		$phpmailer->Port       = (int) $port;
		$phpmailer->SMTPAuth   = false;
		$phpmailer->SMTPSecure = '';
		$phpmailer->SMTPAutoTLS = false;
	}
);

/**
 * A recognisable From address, so captured mail is easy to scan.
 */
add_filter(
	'wp_mail_from',
	static function ( string $from ): string {
		$studio = getenv( 'ESS_STUDIO_EMAIL' );
		return $studio ? (string) $studio : $from;
	}
);

add_filter(
	'wp_mail_from_name',
	static fn(): string => 'Eastern Standard Studio'
);

/**
 * Keep the demo tidy: no "just another WordPress site" tagline, and the
 * timezone matching the studio the booking engine generates slots against.
 */
add_action(
	'init',
	static function (): void {
		if ( ! is_admin() && ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}

		// During `wp core install` this hook fires before the options table
		// exists, which logs a database error on every fresh provision. Both
		// guards are needed: wp_installing() covers the install request, and
		// is_blog_installed() covers a CLI run against an empty database.
		if ( wp_installing() || ! is_blog_installed() ) {
			return;
		}

		$timezone = (string) getenv( 'ESS_STUDIO_TIMEZONE' );
		if ( $timezone && get_option( 'timezone_string' ) !== $timezone ) {
			update_option( 'timezone_string', $timezone );
		}
	}
);
