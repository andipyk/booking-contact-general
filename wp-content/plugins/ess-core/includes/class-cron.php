<?php
/**
 * Scheduled recovery.
 *
 * A slot is claimed the moment a booking record is created, before the
 * automation has confirmed anything. If the automation never calls back, that
 * slot would stay claimed forever and quietly shrink the studio's calendar.
 * This releases those abandoned holds.
 *
 * @package ESS\Core
 */

declare( strict_types = 1 );

namespace ESS\Core;

defined( 'ABSPATH' ) || exit;

final class Cron {

	public const HOOK = 'ess_release_stale_holds';

	/** How long a booking may sit pending before its slot goes back on sale. */
	private const STALE_AFTER_MINUTES = 30;

	public static function init(): void {
		add_action( self::HOOK, [ self::class, 'release_stale_holds' ] );
		add_action( 'init', [ self::class, 'schedule' ] );
	}

	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + ( 5 * MINUTE_IN_SECONDS ), 'hourly', self::HOOK );
		}
	}

	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::HOOK );
	}

	/**
	 * Release locks whose booking never reached a confirmed state.
	 *
	 * Only pending bookings are touched. A confirmed booking keeps its slot no
	 * matter how old the lock row is.
	 */
	public static function release_stale_holds(): void {
		$released = 0;

		foreach ( Slot_Lock::stale_booking_ids( self::STALE_AFTER_MINUTES ) as $booking_id ) {
			$status = (string) get_post_meta( $booking_id, 'ess_status', true );

			if ( Meta::STATUS_PENDING !== $status ) {
				continue;
			}

			Slot_Lock::release( $booking_id );
			update_post_meta( $booking_id, 'ess_status', Meta::STATUS_CANCELLED );
			update_post_meta( $booking_id, 'ess_sync_note', 'slot released: never confirmed by the automation' );
			++$released;
		}

		if ( $released > 0 ) {
			log( 'Released stale holds', [ 'count' => $released ] );
		}
	}
}
