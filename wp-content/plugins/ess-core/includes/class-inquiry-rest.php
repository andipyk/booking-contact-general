<?php
/**
 * Contact enquiry route.
 *
 * Shares the nonce and anti-spam layer with the booking route, so the contact
 * form gets the same protection without a second implementation.
 *
 * @package ESS\Core
 */

declare( strict_types = 1 );

namespace ESS\Core;

defined( 'ABSPATH' ) || exit;

final class Inquiry_REST {

	public const SUBJECTS = [
		'new_project',
		'existing_project',
		'consultant_enquiry',
		'careers',
		'other',
	];

	public static function init(): void {
		add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
	}

	public static function register_routes(): void {
		register_rest_route(
			REST_NS,
			'/inquiry',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ self::class, 'create' ],
				'permission_callback' => [ Booking_REST::class, 'check_nonce' ],
				'args'                => [
					'name'        => [
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => static fn( $v ): bool => is_string( $v ) && strlen( trim( $v ) ) >= 2,
					],
					'email'       => [
						'type'              => 'string',
						'format'            => 'email',
						'required'          => true,
						'sanitize_callback' => 'sanitize_email',
						'validate_callback' => static fn( $v ): bool => (bool) is_email( $v ),
					],
					'phone'       => [
						'type'              => 'string',
						'required'          => false,
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'company'     => [
						'type'              => 'string',
						'required'          => false,
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'subject'     => [
						'type'              => 'string',
						'required'          => true,
						'enum'              => self::SUBJECTS,
						'sanitize_callback' => 'sanitize_key',
					],
					'message'     => [
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_textarea_field',
						'validate_callback' => static fn( $v ): bool => is_string( $v ) && strlen( trim( $v ) ) >= 10,
					],
					'source_page' => [
						'type'              => 'string',
						'required'          => false,
						'default'           => '',
						'sanitize_callback' => 'esc_url_raw',
					],
					'website'     => [
						'type'              => 'string',
						'required'          => false,
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
						'description'       => __( 'Honeypot. Must stay empty.', 'ess-core' ),
					],
					'rendered_at' => [
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);
	}

	public static function create( \WP_REST_Request $request ) {
		$spam_check = Anti_Spam::check( $request, 'inquiry' );
		if ( is_wp_error( $spam_check ) ) {
			return $spam_check;
		}

		$name      = (string) $request->get_param( 'name' );
		$subject   = (string) $request->get_param( 'subject' );
		$reference = 'ESQ-' . strtoupper( wp_generate_password( 6, false, false ) );

		$inquiry_id = wp_insert_post(
			[
				'post_type'   => Post_Types::INQUIRY,
				'post_status' => 'publish',
				'post_title'  => sprintf( '%s — %s', $name, str_replace( '_', ' ', $subject ) ),
			],
			true
		);

		if ( is_wp_error( $inquiry_id ) ) {
			log( 'Enquiry insert failed', [ 'error' => $inquiry_id->get_error_message() ] );
			return new \WP_Error(
				'ess_create_failed',
				__( 'We could not send your message. Please try again.', 'ess-core' ),
				[ 'status' => 500 ]
			);
		}

		$meta = [
			'ess_reference'   => $reference,
			'ess_name'        => $name,
			'ess_email'       => (string) $request->get_param( 'email' ),
			'ess_phone'       => (string) $request->get_param( 'phone' ),
			'ess_company'     => (string) $request->get_param( 'company' ),
			'ess_subject'     => $subject,
			'ess_message'     => (string) $request->get_param( 'message' ),
			'ess_source_page' => (string) $request->get_param( 'source_page' ),
			'ess_status'      => 'new',
		];
		foreach ( $meta as $key => $value ) {
			update_post_meta( (int) $inquiry_id, $key, $value );
		}

		self::notify_studio( (int) $inquiry_id, $meta );

		return new \WP_REST_Response(
			[
				'inquiry_id' => (int) $inquiry_id,
				'reference'  => $reference,
				'message'    => __( 'Thanks — your message is with the studio. We reply within one business day.', 'ess-core' ),
			],
			201
		);
	}

	/**
	 * Email the studio directly.
	 *
	 * Enquiries go straight out through wp_mail rather than through the
	 * automation engine: there is no multi-step workflow to run, and one hop is
	 * one fewer thing to fail.
	 */
	private static function notify_studio( int $inquiry_id, array $meta ): void {
		$to = (string) getenv( 'ESS_STUDIO_EMAIL' );
		if ( '' === $to ) {
			return;
		}

		$body = sprintf(
			"New enquiry %s\n\nName: %s\nEmail: %s\nPhone: %s\nCompany: %s\nSubject: %s\n\n%s\n\nFrom: %s\nOpen in admin: %s\n",
			$meta['ess_reference'],
			$meta['ess_name'],
			$meta['ess_email'],
			$meta['ess_phone'] ?: '—',
			$meta['ess_company'] ?: '—',
			str_replace( '_', ' ', $meta['ess_subject'] ),
			$meta['ess_message'],
			$meta['ess_source_page'] ?: '—',
			(string) get_edit_post_link( $inquiry_id, 'raw' )
		);

		$sent = wp_mail(
			$to,
			sprintf( '[Enquiry] %s — %s', $meta['ess_name'], $meta['ess_reference'] ),
			$body,
			[ 'Reply-To: ' . $meta['ess_name'] . ' <' . $meta['ess_email'] . '>' ]
		);

		if ( ! $sent ) {
			log( 'Enquiry notification failed to send', [ 'inquiry' => $inquiry_id ] );
		}
	}
}
