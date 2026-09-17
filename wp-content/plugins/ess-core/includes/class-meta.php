<?php
/**
 * Post meta registration.
 *
 * Project and service meta is registered with `show_in_rest` and a `label`
 * because the theme binds core blocks straight to these keys through the Block
 * Bindings `core/post-meta` source. Without both, the binding silently renders
 * the fallback text instead of the value.
 *
 * Booking and enquiry meta is deliberately kept off REST: those records are
 * only ever written by this plugin's own routes.
 *
 * @package ESS\Core
 */

declare( strict_types = 1 );

namespace ESS\Core;

defined( 'ABSPATH' ) || exit;

final class Meta {

	/** Booking lifecycle. A booking is created pending and confirmed by n8n. */
	public const STATUS_PENDING   = 'pending';
	public const STATUS_CONFIRMED = 'confirmed';
	public const STATUS_CANCELLED = 'cancelled';

	public static function init(): void {
		add_action( 'init', [ self::class, 'register' ], 5 );
	}

	public static function register(): void {
		foreach ( self::public_schema() as $post_type => $fields ) {
			foreach ( $fields as $key => $field ) {
				register_post_meta(
					$post_type,
					$key,
					[
						'type'              => $field['type'],
						'label'             => $field['label'],
						'description'       => $field['label'],
						'single'            => true,
						'default'           => $field['default'],
						'show_in_rest'      => true,
						'sanitize_callback' => $field['sanitize'],
						'auth_callback'     => static fn(): bool => current_user_can( 'edit_posts' ),
					]
				);
			}
		}

		foreach ( self::private_schema() as $post_type => $fields ) {
			foreach ( $fields as $key => $field ) {
				register_post_meta(
					$post_type,
					$key,
					[
						'type'              => $field['type'],
						'single'            => true,
						'default'           => $field['default'],
						'show_in_rest'      => false,
						'sanitize_callback' => $field['sanitize'],
						'auth_callback'     => static fn(): bool => current_user_can( 'edit_others_posts' ),
					]
				);
			}
		}
	}

	/**
	 * Meta the theme renders through Block Bindings.
	 *
	 * @return array<string, array<string, array{type:string,label:string,default:mixed,sanitize:callable|string}>>
	 */
	public static function public_schema(): array {
		return [
			Post_Types::PROJECT => [
				'ess_location'       => [
					'type'     => 'string',
					'label'    => __( 'Location', 'ess-core' ),
					'default'  => '',
					'sanitize' => 'sanitize_text_field',
				],
				'ess_year_completed' => [
					'type'     => 'integer',
					'label'    => __( 'Year completed', 'ess-core' ),
					'default'  => 0,
					'sanitize' => 'absint',
				],
				'ess_square_feet'    => [
					'type'     => 'integer',
					'label'    => __( 'Square feet', 'ess-core' ),
					'default'  => 0,
					'sanitize' => 'absint',
				],
				'ess_client_type'    => [
					'type'     => 'string',
					'label'    => __( 'Client type', 'ess-core' ),
					'default'  => '',
					'sanitize' => 'sanitize_text_field',
				],
				'ess_scope'          => [
					'type'     => 'string',
					'label'    => __( 'Scope of work', 'ess-core' ),
					'default'  => '',
					'sanitize' => 'sanitize_text_field',
				],
				'ess_duration'       => [
					'type'     => 'string',
					'label'    => __( 'Duration', 'ess-core' ),
					'default'  => '',
					'sanitize' => 'sanitize_text_field',
				],
			],
			Post_Types::SERVICE => [
				'ess_lead_time'      => [
					'type'     => 'string',
					'label'    => __( 'Typical lead time', 'ess-core' ),
					'default'  => '',
					'sanitize' => 'sanitize_text_field',
				],
				'ess_starting_price' => [
					'type'     => 'string',
					'label'    => __( 'Starting price', 'ess-core' ),
					'default'  => '',
					'sanitize' => 'sanitize_text_field',
				],
				'ess_deliverable'    => [
					'type'     => 'string',
					'label'    => __( 'Key deliverable', 'ess-core' ),
					'default'  => '',
					'sanitize' => 'sanitize_text_field',
				],
			],
		];
	}

	/**
	 * Studio records. Written by this plugin's routes, never by REST clients.
	 *
	 * @return array<string, array<string, array{type:string,default:mixed,sanitize:callable|string}>>
	 */
	public static function private_schema(): array {
		$text  = [ 'type' => 'string', 'default' => '', 'sanitize' => 'sanitize_text_field' ];
		$email = [ 'type' => 'string', 'default' => '', 'sanitize' => 'sanitize_email' ];
		$long  = [ 'type' => 'string', 'default' => '', 'sanitize' => 'sanitize_textarea_field' ];
		$key   = [ 'type' => 'string', 'default' => '', 'sanitize' => 'sanitize_key' ];
		$url   = [ 'type' => 'string', 'default' => '', 'sanitize' => 'esc_url_raw' ];

		return [
			Post_Types::BOOKING => [
				'ess_reference'    => $text,
				// Both stored as UTC 'Y-m-d H:i:s'. Rendering converts to the
				// studio timezone; comparisons never leave UTC.
				'ess_slot_start'   => $text,
				'ess_slot_end'     => $text,
				'ess_name'         => $text,
				'ess_email'        => $email,
				'ess_phone'        => $text,
				'ess_company'      => $text,
				'ess_project_type' => $key,
				'ess_message'      => $long,
				'ess_status'       => [ 'type' => 'string', 'default' => self::STATUS_PENDING, 'sanitize' => 'sanitize_key' ],
				'ess_confirmed_at' => $text,
				'ess_sync_note'    => $text,
			],
			Post_Types::INQUIRY => [
				'ess_reference'   => $text,
				'ess_name'        => $text,
				'ess_email'       => $email,
				'ess_phone'       => $text,
				'ess_company'     => $text,
				'ess_subject'     => $key,
				'ess_message'     => $long,
				'ess_source_page' => $url,
				'ess_status'      => [ 'type' => 'string', 'default' => 'new', 'sanitize' => 'sanitize_key' ],
			],
		];
	}

	/** Valid booking statuses, for REST enum validation. */
	public static function booking_statuses(): array {
		return [ self::STATUS_PENDING, self::STATUS_CONFIRMED, self::STATUS_CANCELLED ];
	}
}
