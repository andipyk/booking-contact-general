<?php
/**
 * Outbound notification to the automation engine.
 *
 * WordPress posts each new booking to n8n, which sends the emails and then
 * calls back to mark the record confirmed. The call is fire-and-forget from the
 * visitor's point of view: a failure here is logged and leaves the booking
 * pending for the recovery cron, because a visitor who picked a slot should
 * never see an automation error.
 *
 * @package ESS\Core
 */

declare( strict_types = 1 );

namespace ESS\Core;

defined( 'ABSPATH' ) || exit;

final class Webhook {

	public const ACTION = 'ess_booking_created';

	public static function init(): void {
		add_action( self::ACTION, [ self::class, 'send' ], 10, 1 );
	}

	public static function url(): string {
		return (string) getenv( 'ESS_WEBHOOK_URL' );
	}

	public static function secret(): string {
		return (string) getenv( 'ESS_WEBHOOK_SECRET' );
	}

	public static function is_configured(): bool {
		return '' !== self::url() && '' !== self::secret();
	}

	/**
	 * Where the automation should call back.
	 *
	 * rest_url() gives the public address, which is what a browser needs and
	 * what this would use in production behind one hostname. Inside Docker the
	 * automation reaches WordPress by service name instead, and resolving
	 * localhost there lands on n8n's own port.
	 */
	public static function callback_url(): string {
		$internal = (string) getenv( 'ESS_CALLBACK_URL' );
		return '' !== $internal ? $internal : rest_url( REST_NS . '/booking/status' );
	}

	/**
	 * Post a booking to n8n.
	 *
	 * The payload carries its own callback URL so the automation never has to
	 * hardcode where this site lives.
	 */
	public static function send( int $booking_id ): void {
		if ( ! self::is_configured() ) {
			log( 'Webhook skipped: ESS_WEBHOOK_URL or ESS_WEBHOOK_SECRET is empty' );
			return;
		}

		$booking = get_post( $booking_id );
		if ( ! $booking || Post_Types::BOOKING !== $booking->post_type ) {
			log( 'Webhook skipped: not a booking', [ 'id' => $booking_id ] );
			return;
		}

		$slot_start = (string) get_post_meta( $booking_id, 'ess_slot_start', true );

		$payload = [
			'booking_id'      => $booking_id,
			'reference'       => (string) get_post_meta( $booking_id, 'ess_reference', true ),
			'name'            => (string) get_post_meta( $booking_id, 'ess_name', true ),
			'email'           => (string) get_post_meta( $booking_id, 'ess_email', true ),
			'phone'           => (string) get_post_meta( $booking_id, 'ess_phone', true ),
			'company'         => (string) get_post_meta( $booking_id, 'ess_company', true ),
			'project_type'    => (string) get_post_meta( $booking_id, 'ess_project_type', true ),
			'message'         => (string) get_post_meta( $booking_id, 'ess_message', true ),
			'slot_start_utc'  => $slot_start,
			'slot_end_utc'    => (string) get_post_meta( $booking_id, 'ess_slot_end', true ),
			// Pre-formatted so the automation never has to do timezone maths.
			'slot_local'      => Availability::to_studio( $slot_start ),
			'studio_timezone' => studio_timezone()->getName(),
			'studio_email'    => (string) getenv( 'ESS_STUDIO_EMAIL' ),
			'admin_url'       => get_edit_post_link( $booking_id, 'raw' ),
			'callback_url'    => self::callback_url(),
			'created_at'      => gmdate( 'c' ),
		];

		$response = wp_remote_post(
			self::url(),
			[
				'timeout'  => 10,
				'blocking' => true,
				'headers'  => [
					'Content-Type'     => 'application/json',
					'x-ess-signature'  => self::secret(),
				],
				'body'     => wp_json_encode( $payload ),
			]
		);

		if ( is_wp_error( $response ) ) {
			log( 'Webhook transport failure', [ 'booking' => $booking_id, 'error' => $response->get_error_message() ] );
			update_post_meta( $booking_id, 'ess_sync_note', 'transport error: ' . $response->get_error_message() );
			return;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			log( 'Webhook rejected', [ 'booking' => $booking_id, 'status' => $code ] );
			update_post_meta( $booking_id, 'ess_sync_note', 'automation returned HTTP ' . $code );
			return;
		}

		// No success note here. The callback runs while this request is still
		// blocking, so it has already written a more specific note by now and
		// writing one from this side would overwrite it.
		log( 'Webhook delivered', [ 'booking' => $booking_id, 'status' => $code ] );
	}
}
