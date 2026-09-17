<?php
/**
 * Booking routes under ess/v1.
 *
 * Three routes with three different authentication models:
 *   GET  /availability   — public, read-only.
 *   POST /booking        — cookie nonce plus the anti-spam checks.
 *   POST /booking/status — shared secret, for the automation engine only.
 *
 * @package ESS\Core
 */

declare( strict_types = 1 );

namespace ESS\Core;

defined( 'ABSPATH' ) || exit;

final class Booking_REST {

	public static function init(): void {
		add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
	}

	public static function register_routes(): void {
		register_rest_route(
			REST_NS,
			'/availability',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ self::class, 'get_availability' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'from' => [
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => [ self::class, 'validate_date' ],
						'description'       => __( 'Start date, Y-m-d, in the studio timezone.', 'ess-core' ),
					],
					'days' => [
						'type'              => 'integer',
						'required'          => false,
						'default'           => 14,
						'minimum'           => 1,
						'maximum'           => 31,
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		register_rest_route(
			REST_NS,
			'/booking',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ self::class, 'create_booking' ],
				'permission_callback' => [ self::class, 'check_nonce' ],
				'args'                => self::booking_args(),
			]
		);

		register_rest_route(
			REST_NS,
			'/booking/status',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ self::class, 'update_status' ],
				'permission_callback' => [ self::class, 'check_signature' ],
				'args'                => [
					'booking_id' => [
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					],
					'status'     => [
						'type'              => 'string',
						'required'          => false,
						'default'           => Meta::STATUS_CONFIRMED,
						'enum'              => Meta::booking_statuses(),
						'sanitize_callback' => 'sanitize_key',
					],
					'note'       => [
						'type'              => 'string',
						'required'          => false,
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);
	}

	/** @return array<string, array<string, mixed>> */
	private static function booking_args(): array {
		return [
			'slot_start'   => [
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
				'description'       => __( 'Slot start as UTC Y-m-d H:i:s.', 'ess-core' ),
			],
			'name'         => [
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => static fn( $v ): bool => is_string( $v ) && strlen( trim( $v ) ) >= 2,
			],
			'email'        => [
				'type'              => 'string',
				'format'            => 'email',
				'required'          => true,
				'sanitize_callback' => 'sanitize_email',
				'validate_callback' => static fn( $v ): bool => (bool) is_email( $v ),
			],
			'phone'        => [
				'type'              => 'string',
				'required'          => false,
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'company'      => [
				'type'              => 'string',
				'required'          => false,
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'project_type' => [
				'type'              => 'string',
				'required'          => true,
				'enum'              => Availability::PROJECT_TYPES,
				'sanitize_callback' => 'sanitize_key',
			],
			'message'      => [
				'type'              => 'string',
				'required'          => false,
				'default'           => '',
				'sanitize_callback' => 'sanitize_textarea_field',
			],
			'website'      => [
				'type'              => 'string',
				'required'          => false,
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
				'description'       => __( 'Honeypot. Must stay empty.', 'ess-core' ),
			],
			'rendered_at'  => [
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			],
		];
	}

	public static function validate_date( $value ): bool {
		if ( ! is_string( $value ) || '' === $value ) {
			return true;
		}
		$parsed = \DateTimeImmutable::createFromFormat( 'Y-m-d', $value, studio_timezone() );
		return $parsed && $parsed->format( 'Y-m-d' ) === $value;
	}

	/**
	 * Cookie nonce check for the public booking form.
	 *
	 * The form is anonymous, so this authenticates the *page*, not a user: it
	 * confirms the request came from a form this site rendered.
	 */
	public static function check_nonce( \WP_REST_Request $request ) {
		$nonce = $request->get_header( 'x_wp_nonce' ) ?: $request->get_param( '_wpnonce' );

		if ( ! $nonce || ! wp_verify_nonce( (string) $nonce, 'wp_rest' ) ) {
			return new \WP_Error(
				'ess_bad_nonce',
				__( 'Your session expired. Please reload the page and try again.', 'ess-core' ),
				[ 'status' => 403 ]
			);
		}

		return true;
	}

	/**
	 * Shared-secret check for the automation callback.
	 *
	 * hash_equals, never ===: a plain comparison returns as soon as two bytes
	 * differ, which leaks the correct prefix over enough attempts.
	 */
	public static function check_signature( \WP_REST_Request $request ) {
		$expected = Webhook::secret();

		if ( '' === $expected ) {
			log( 'Callback rejected: ESS_WEBHOOK_SECRET is not set' );
			return new \WP_Error(
				'ess_not_configured',
				__( 'The callback is not configured.', 'ess-core' ),
				[ 'status' => 500 ]
			);
		}

		$provided = (string) $request->get_header( 'x_ess_signature' );

		if ( '' === $provided || ! hash_equals( $expected, $provided ) ) {
			return new \WP_Error(
				'ess_forbidden',
				__( 'Invalid signature.', 'ess-core' ),
				[ 'status' => 403 ]
			);
		}

		return true;
	}

	public static function get_availability( \WP_REST_Request $request ): \WP_REST_Response {
		$from = (string) $request->get_param( 'from' );
		$days = (int) $request->get_param( 'days' );

		$start = '' !== $from
			? ( \DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $from . ' 00:00', studio_timezone() )
				?: Availability::now() )
			: Availability::now();

		$start_utc = $start->setTimezone( new \DateTimeZone( 'UTC' ) );
		$end_utc   = $start_utc->add( new \DateInterval( 'P' . $days . 'D' ) );

		$slots = Availability::slots( $start_utc, $end_utc );

		$response = rest_ensure_response(
			[
				'timezone'   => studio_timezone()->getName(),
				'slot_count' => count( $slots ),
				'days'       => self::group_by_day( $slots ),
			]
		);

		// Short public cache: long enough to absorb a burst, short enough that
		// a slot taken moments ago disappears quickly.
		$response->header( 'Cache-Control', 'public, max-age=60' );
		return $response;
	}

	/**
	 * Create a booking, claiming the slot atomically.
	 *
	 * Order matters: validate the slot, create the record, then claim the lock.
	 * If the claim loses the race the record is deleted again, so a rejected
	 * booking leaves nothing behind.
	 */
	public static function create_booking( \WP_REST_Request $request ) {
		$spam_check = Anti_Spam::check( $request, 'booking' );
		if ( is_wp_error( $spam_check ) ) {
			return $spam_check;
		}

		$slot_start = (string) $request->get_param( 'slot_start' );

		// Never trust a client timestamp: resolve it against the slots the
		// studio actually offers, or a crafted request books 03:00 on a Sunday.
		$slot = Availability::resolve( $slot_start );
		if ( null === $slot ) {
			return new \WP_Error(
				'ess_slot_unavailable',
				__( 'That time is no longer available. Please pick another slot.', 'ess-core' ),
				[ 'status' => 409 ]
			);
		}

		$name      = (string) $request->get_param( 'name' );
		$reference = self::reference();

		$booking_id = wp_insert_post(
			[
				'post_type'   => Post_Types::BOOKING,
				'post_status' => 'publish',
				'post_title'  => sprintf( '%s — %s', $name, Availability::to_studio( $slot['start'], 'j M Y, g:i a' ) ),
			],
			true
		);

		if ( is_wp_error( $booking_id ) ) {
			log( 'Booking insert failed', [ 'error' => $booking_id->get_error_message() ] );
			return new \WP_Error(
				'ess_create_failed',
				__( 'We could not save your booking. Please try again.', 'ess-core' ),
				[ 'status' => 500 ]
			);
		}

		if ( ! Slot_Lock::acquire( $slot['start'], $slot['end'], (int) $booking_id ) ) {
			// Someone else claimed the slot between resolve() and here. The
			// unique index caught it; undo the half-made record.
			wp_delete_post( (int) $booking_id, true );
			return new \WP_Error(
				'ess_slot_taken',
				__( 'Someone just booked that time. Please pick another slot.', 'ess-core' ),
				[ 'status' => 409 ]
			);
		}

		$meta = [
			'ess_reference'    => $reference,
			'ess_slot_start'   => $slot['start'],
			'ess_slot_end'     => $slot['end'],
			'ess_name'         => $name,
			'ess_email'        => (string) $request->get_param( 'email' ),
			'ess_phone'        => (string) $request->get_param( 'phone' ),
			'ess_company'      => (string) $request->get_param( 'company' ),
			'ess_project_type' => (string) $request->get_param( 'project_type' ),
			'ess_message'      => (string) $request->get_param( 'message' ),
			'ess_status'       => Meta::STATUS_PENDING,
		];
		foreach ( $meta as $key => $value ) {
			update_post_meta( (int) $booking_id, $key, $value );
		}

		do_action( Webhook::ACTION, (int) $booking_id );

		return new \WP_REST_Response(
			[
				'booking_id' => (int) $booking_id,
				'reference'  => $reference,
				'status'     => Meta::STATUS_PENDING,
				'slot_local' => Availability::to_studio( $slot['start'] ),
				'message'    => __( 'Your consultation is booked. A confirmation email is on its way.', 'ess-core' ),
			],
			201
		);
	}

	/** Automation callback: flip a pending booking to its final state. */
	public static function update_status( \WP_REST_Request $request ) {
		$booking_id = (int) $request->get_param( 'booking_id' );
		$booking    = get_post( $booking_id );

		if ( ! $booking || Post_Types::BOOKING !== $booking->post_type ) {
			return new \WP_Error(
				'ess_not_found',
				__( 'No such booking.', 'ess-core' ),
				[ 'status' => 404 ]
			);
		}

		$status = (string) $request->get_param( 'status' );
		$note   = (string) $request->get_param( 'note' );

		update_post_meta( $booking_id, 'ess_status', $status );
		update_post_meta( $booking_id, 'ess_confirmed_at', gmdate( Availability::MYSQL ) );
		if ( '' !== $note ) {
			update_post_meta( $booking_id, 'ess_sync_note', $note );
		}

		// A cancelled booking must put its slot back on sale.
		if ( Meta::STATUS_CANCELLED === $status ) {
			Slot_Lock::release( $booking_id );
		}

		return rest_ensure_response(
			[
				'booking_id'   => $booking_id,
				'status'       => $status,
				'confirmed_at' => (string) get_post_meta( $booking_id, 'ess_confirmed_at', true ),
			]
		);
	}

	/**
	 * Group flat slots into days so the front end can render a day picker
	 * without doing date maths in the browser.
	 */
	private static function group_by_day( array $slots ): array {
		$days = [];
		foreach ( $slots as $slot ) {
			$date = $slot['date'];
			if ( ! isset( $days[ $date ] ) ) {
				$parsed        = \DateTimeImmutable::createFromFormat( 'Y-m-d', $date, studio_timezone() );
				$days[ $date ] = [
					'date'      => $date,
					'weekday'   => $parsed ? $parsed->format( 'D' ) : '',
					'day_label' => $parsed ? $parsed->format( 'j M' ) : $date,
					'slots'     => [],
					'open'      => 0,
				];
			}
			$days[ $date ]['slots'][] = $slot;
			if ( $slot['available'] ) {
				++$days[ $date ]['open'];
			}
		}
		return array_values( $days );
	}

	/** Human-quotable booking reference, e.g. ESS-7K3QD2. */
	private static function reference(): string {
		return 'ESS-' . strtoupper( wp_generate_password( 6, false, false ) );
	}
}
