<?php
/**
 * Submission hygiene for the public write routes.
 *
 * No form plugin means no bundled spam handling, so the three cheap checks that
 * catch most automated submissions are implemented here: a honeypot field, a
 * minimum fill time, and a per-IP rate limit. All three run before any record
 * is written.
 *
 * @package ESS\Core
 */

declare( strict_types = 1 );

namespace ESS\Core;

defined( 'ABSPATH' ) || exit;

final class Anti_Spam {

	/** A human takes longer than this to read the form and fill it in. */
	private const MIN_FILL_SECONDS = 3;

	/** Nobody legitimately submits more than this from one address. */
	private const MAX_PER_WINDOW = 5;
	private const WINDOW_SECONDS = 600;

	/**
	 * Run every check for a public submission.
	 *
	 * @return true|\WP_Error
	 */
	public static function check( \WP_REST_Request $request, string $scope ) {
		// 1. Honeypot: a field hidden from people, irresistible to bots.
		$honeypot = (string) $request->get_param( 'website' );
		if ( '' !== trim( $honeypot ) ) {
			log( 'Honeypot tripped', [ 'scope' => $scope ] );
			return new \WP_Error(
				'ess_rejected',
				__( 'This submission could not be accepted.', 'ess-core' ),
				[ 'status' => 422 ]
			);
		}

		// 2. Time trap: the form stamps its render time, signed so it cannot be
		// forged or replayed from an old page.
		$stamp = (string) $request->get_param( 'rendered_at' );
		$age   = self::verify_stamp( $stamp );
		if ( null === $age ) {
			return new \WP_Error(
				'ess_stale_form',
				__( 'This form has expired. Please reload the page and try again.', 'ess-core' ),
				[ 'status' => 422 ]
			);
		}
		if ( $age < self::MIN_FILL_SECONDS ) {
			log( 'Time trap tripped', [ 'scope' => $scope, 'age' => $age ] );
			return new \WP_Error(
				'ess_rejected',
				__( 'This submission could not be accepted.', 'ess-core' ),
				[ 'status' => 422 ]
			);
		}

		// 3. Rate limit, backed by the object cache (Redis in this stack).
		if ( ! self::within_rate_limit( $scope ) ) {
			return new \WP_Error(
				'ess_rate_limited',
				__( 'Too many submissions. Please try again shortly.', 'ess-core' ),
				[ 'status' => 429 ]
			);
		}

		return true;
	}

	/** Signed timestamp embedded in the rendered form. */
	public static function stamp(): string {
		$now = (string) time();
		return $now . '.' . hash_hmac( 'sha256', $now, wp_salt( 'nonce' ) );
	}

	/**
	 * Validate a stamp and return its age in seconds.
	 *
	 * @return int|null Null when the stamp is malformed, forged or older than an hour.
	 */
	private static function verify_stamp( string $stamp ): ?int {
		if ( ! str_contains( $stamp, '.' ) ) {
			return null;
		}

		[ $issued, $signature ] = explode( '.', $stamp, 2 );
		$expected               = hash_hmac( 'sha256', $issued, wp_salt( 'nonce' ) );

		// Constant-time comparison: a fast-fail string compare leaks how much
		// of the signature was correct.
		if ( ! hash_equals( $expected, $signature ) ) {
			return null;
		}

		$age = time() - (int) $issued;
		if ( $age < 0 || $age > HOUR_IN_SECONDS ) {
			return null;
		}

		return $age;
	}

	private static function within_rate_limit( string $scope ): bool {
		$key   = 'rl_' . $scope . '_' . md5( self::client_ip() );
		$count = (int) wp_cache_get( $key, 'ess_rate' );

		/**
		 * Filter the per-window submission ceiling.
		 *
		 * A busy studio behind one office NAT would legitimately exceed the
		 * default, and the concurrency test needs to raise it to fire enough
		 * parallel requests to be meaningful.
		 *
		 * @param int    $max   Submissions allowed per window.
		 * @param string $scope Either 'booking' or 'inquiry'.
		 */
		$max = (int) apply_filters( 'ess_rate_limit_max', self::MAX_PER_WINDOW, $scope );

		if ( $count >= $max ) {
			log( 'Rate limit hit', [ 'scope' => $scope ] );
			return false;
		}

		wp_cache_set( $key, $count + 1, 'ess_rate', self::WINDOW_SECONDS );
		return true;
	}

	/**
	 * Client address for rate limiting only.
	 *
	 * REMOTE_ADDR is the only value a client cannot set, so no forwarded-for
	 * header is trusted here. Behind a real proxy this would read the specific
	 * header that proxy is known to set, and nothing else.
	 */
	private static function client_ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
			: '0.0.0.0';
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
	}
}
