<?php
/**
 * Admin list tables for studio records.
 *
 * Bookings and enquiries are data, not content, so the default "title and date"
 * list is close to useless for them. These columns make the list answer the
 * question the studio actually has: who, when, and did it sync.
 *
 * @package ESS\Core
 */

declare( strict_types = 1 );

namespace ESS\Core;

defined( 'ABSPATH' ) || exit;

final class Admin {

	public static function init(): void {
		add_filter( 'manage_' . Post_Types::BOOKING . '_posts_columns', [ self::class, 'booking_columns' ] );
		add_action( 'manage_' . Post_Types::BOOKING . '_posts_custom_column', [ self::class, 'booking_column' ], 10, 2 );

		add_filter( 'manage_' . Post_Types::INQUIRY . '_posts_columns', [ self::class, 'inquiry_columns' ] );
		add_action( 'manage_' . Post_Types::INQUIRY . '_posts_custom_column', [ self::class, 'inquiry_column' ], 10, 2 );

		add_action( 'admin_head', [ self::class, 'status_styles' ] );
	}

	public static function booking_columns( array $columns ): array {
		$date = $columns['date'] ?? '';
		unset( $columns['date'] );

		$columns['ess_slot']      = __( 'Consultation', 'ess-core' );
		$columns['ess_contact']   = __( 'Contact', 'ess-core' );
		$columns['ess_type']      = __( 'Project type', 'ess-core' );
		$columns['ess_status']    = __( 'Status', 'ess-core' );
		$columns['ess_reference'] = __( 'Ref', 'ess-core' );
		$columns['date']          = $date;

		return $columns;
	}

	public static function booking_column( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'ess_slot':
				$start = (string) get_post_meta( $post_id, 'ess_slot_start', true );
				echo esc_html( Availability::to_studio( $start ) ?: '—' );
				break;

			case 'ess_contact':
				$email = (string) get_post_meta( $post_id, 'ess_email', true );
				$phone = (string) get_post_meta( $post_id, 'ess_phone', true );
				printf(
					'<a href="mailto:%1$s">%1$s</a>%2$s',
					esc_attr( $email ),
					$phone ? '<br><span class="description">' . esc_html( $phone ) . '</span>' : ''
				);
				break;

			case 'ess_type':
				$type = (string) get_post_meta( $post_id, 'ess_project_type', true );
				echo esc_html( $type ? ucwords( str_replace( '_', ' ', $type ) ) : '—' );
				break;

			case 'ess_status':
				self::render_status( (string) get_post_meta( $post_id, 'ess_status', true ) );
				$note = (string) get_post_meta( $post_id, 'ess_sync_note', true );
				if ( $note ) {
					echo '<br><span class="description">' . esc_html( $note ) . '</span>';
				}
				break;

			case 'ess_reference':
				echo '<code>' . esc_html( (string) get_post_meta( $post_id, 'ess_reference', true ) ) . '</code>';
				break;
		}
	}

	public static function inquiry_columns( array $columns ): array {
		$date = $columns['date'] ?? '';
		unset( $columns['date'] );

		$columns['ess_contact'] = __( 'Contact', 'ess-core' );
		$columns['ess_subject'] = __( 'Subject', 'ess-core' );
		$columns['ess_excerpt'] = __( 'Message', 'ess-core' );
		$columns['date']        = $date;

		return $columns;
	}

	public static function inquiry_column( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'ess_contact':
				$email = (string) get_post_meta( $post_id, 'ess_email', true );
				printf( '<a href="mailto:%1$s">%1$s</a>', esc_attr( $email ) );
				break;

			case 'ess_subject':
				$subject = (string) get_post_meta( $post_id, 'ess_subject', true );
				echo esc_html( $subject ? ucwords( str_replace( '_', ' ', $subject ) ) : '—' );
				break;

			case 'ess_excerpt':
				$message = (string) get_post_meta( $post_id, 'ess_message', true );
				echo esc_html( wp_trim_words( $message, 14 ) );
				break;
		}
	}

	private static function render_status( string $status ): void {
		printf(
			'<span class="ess-pill ess-pill--%s">%s</span>',
			esc_attr( $status ?: 'pending' ),
			esc_html( ucfirst( $status ?: 'pending' ) )
		);
	}

	public static function status_styles(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || Post_Types::BOOKING !== $screen->post_type ) {
			return;
		}
		?>
		<style>
			.ess-pill{display:inline-block;padding:2px 10px;border-radius:999px;font-size:11px;font-weight:600;line-height:1.7}
			.ess-pill--pending{background:#fef3c7;color:#92400e}
			.ess-pill--confirmed{background:#d1fae5;color:#065f46}
			.ess-pill--cancelled{background:#fee2e2;color:#991b1b}
		</style>
		<?php
	}
}
