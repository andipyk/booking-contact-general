<?php
/**
 * Atomic slot reservation.
 *
 * Post meta carries no unique index, so the obvious implementation — read
 * "is this slot free?", then write — lets two simultaneous requests both pass
 * the read and both write. The window is small but it is real, and a
 * double-booked principal is exactly the failure a booking system exists to
 * prevent.
 *
 * This table gives the slot a UNIQUE KEY, so the database decides who wins.
 * The second writer gets a duplicate-key error, which the REST layer turns into
 * a 409. Correctness comes from InnoDB, not from PHP timing.
 *
 * @package ESS\Core
 */

declare( strict_types = 1 );

namespace ESS\Core;

defined( 'ABSPATH' ) || exit;

final class Slot_Lock {

	public const TABLE       = 'ess_slot_locks';
	private const DB_VERSION = '1.0.0';
	private const OPTION_DB  = 'ess_slot_lock_db_version';

	public static function init(): void {
		// Bind-mounted plugins are activated by WP-CLI during setup, but a
		// rebuilt volume can leave the table behind. Checking a version option
		// on admin_init is cheap and self-heals that case.
		add_action( 'admin_init', [ self::class, 'maybe_upgrade' ] );
	}

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	public static function maybe_upgrade(): void {
		if ( get_option( self::OPTION_DB ) === self::DB_VERSION ) {
			return;
		}
		self::create_table();
	}

	public static function create_table(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$collate = $wpdb->get_charset_collate();

		// dbDelta is whitespace-sensitive: two spaces after PRIMARY KEY, and
		// each KEY on its own line, or it will try to re-create the table on
		// every run.
		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			slot_start datetime NOT NULL,
			slot_end datetime NOT NULL,
			booking_id bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slot_start (slot_start),
			KEY booking_id (booking_id)
		) {$collate};";

		dbDelta( $sql );
		update_option( self::OPTION_DB, self::DB_VERSION, false );
	}

	/**
	 * Claim a slot for a booking.
	 *
	 * Returns false when the slot is already claimed. No SELECT happens first
	 * on purpose — the INSERT is the test, and it is atomic.
	 */
	public static function acquire( string $slot_start_utc, string $slot_end_utc, int $booking_id ): bool {
		global $wpdb;

		// Suppress the notice WordPress would print for the expected duplicate
		// key error; the return value carries the outcome.
		$previous = $wpdb->suppress_errors( true );

		$inserted = $wpdb->insert(
			self::table(),
			[
				'slot_start' => $slot_start_utc,
				'slot_end'   => $slot_end_utc,
				'booking_id' => $booking_id,
				'created_at' => gmdate( Availability::MYSQL ),
			],
			[ '%s', '%s', '%d', '%s' ]
		);

		$wpdb->suppress_errors( $previous );

		if ( false === $inserted ) {
			log( 'Slot already claimed', [ 'slot' => $slot_start_utc, 'booking' => $booking_id ] );
			return false;
		}

		self::flush_cache();
		return true;
	}

	/** Release a slot, e.g. when a pending booking is abandoned or cancelled. */
	public static function release( int $booking_id ): void {
		global $wpdb;
		$wpdb->delete( self::table(), [ 'booking_id' => $booking_id ], [ '%d' ] );
		self::flush_cache();
	}

	/**
	 * Claimed slot starts within a UTC range, keyed for O(1) lookup.
	 *
	 * @return array<string, true>
	 */
	public static function taken_between( \DateTimeImmutable $from, \DateTimeImmutable $to ): array {
		global $wpdb;

		$cache_key = 'taken_' . $from->format( 'YmdHi' ) . '_' . $to->format( 'YmdHi' );
		$cached    = wp_cache_get( $cache_key, 'ess_slots' );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$table = self::table();
		$rows  = $wpdb->get_col(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is built from $wpdb->prefix.
				"SELECT slot_start FROM {$table} WHERE slot_start BETWEEN %s AND %s",
				$from->format( Availability::MYSQL ),
				$to->format( Availability::MYSQL )
			)
		);

		$taken = [];
		foreach ( (array) $rows as $row ) {
			$taken[ (string) $row ] = true;
		}

		wp_cache_set( $cache_key, $taken, 'ess_slots', 5 * MINUTE_IN_SECONDS );
		return $taken;
	}

	/**
	 * Locks held by bookings that never got confirmed.
	 *
	 * @return list<int> Booking IDs.
	 */
	public static function stale_booking_ids( int $older_than_minutes ): array {
		global $wpdb;

		$table  = self::table();
		$cutoff = gmdate( Availability::MYSQL, time() - ( $older_than_minutes * MINUTE_IN_SECONDS ) );

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is built from $wpdb->prefix.
				"SELECT booking_id FROM {$table} WHERE created_at < %s",
				$cutoff
			)
		);

		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Availability responses are cached; any write to the lock table has to
	 * invalidate them or the next visitor is offered a slot that just went.
	 */
	public static function flush_cache(): void {
		wp_cache_set( 'ess_slots_generation', microtime( true ), 'ess_slots' );
		if ( function_exists( 'wp_cache_flush_group' ) && wp_cache_supports( 'flush_group' ) ) {
			wp_cache_flush_group( 'ess_slots' );
		}
	}
}
