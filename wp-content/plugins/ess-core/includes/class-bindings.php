<?php
/**
 * A custom Block Bindings source.
 *
 * `core/post-meta` is the right tool for text that is already display-ready:
 * a location or a client type goes straight from the database to the page. It
 * has no formatting layer though, so a square footage stored as 4800 renders
 * as "4800".
 *
 * Keeping the integer in the database matters — it is what makes the value
 * sortable and queryable — so the formatting belongs at render time. That is
 * what this source does, and it is why the project template uses two sources
 * rather than one.
 *
 * @package ESS\Core
 */

declare( strict_types = 1 );

namespace ESS\Core;

defined( 'ABSPATH' ) || exit;

final class Bindings {

	public const SOURCE = 'ess/project-spec';

	/** Rendered when a field is empty, so the spec sheet keeps its grid. */
	private const EMPTY_MARK = '—';

	public static function init(): void {
		add_action( 'init', [ self::class, 'register' ], 15 );
	}

	public static function register(): void {
		if ( ! function_exists( 'register_block_bindings_source' ) ) {
			log( 'Block Bindings API unavailable; spec fields will fall back to their placeholder text' );
			return;
		}

		register_block_bindings_source(
			self::SOURCE,
			[
				'label'              => __( 'Project spec', 'ess-core' ),
				'get_value_callback' => [ self::class, 'get_value' ],
				'uses_context'       => [ 'postId', 'postType' ],
			]
		);
	}

	/**
	 * Resolve one bound field.
	 *
	 * @param array     $source_args Arguments from the block's binding config.
	 * @param \WP_Block $block       The block being rendered.
	 * @return string
	 */
	public static function get_value( array $source_args, \WP_Block $block ): string {
		$key = isset( $source_args['key'] ) ? sanitize_key( (string) $source_args['key'] ) : '';
		if ( '' === $key ) {
			return self::EMPTY_MARK;
		}

		$post_id = $block->context['postId'] ?? get_the_ID();
		if ( ! $post_id ) {
			return self::EMPTY_MARK;
		}

		$raw = get_post_meta( (int) $post_id, $key, true );

		return match ( $key ) {
			'ess_square_feet'    => self::area( $raw ),
			'ess_year_completed' => self::year( $raw ),
			default              => '' !== (string) $raw ? (string) $raw : self::EMPTY_MARK,
		};
	}

	private static function area( $raw ): string {
		$value = (int) $raw;
		return $value > 0
			? sprintf(
				/* translators: %s: formatted square footage, e.g. 4,800. */
				__( '%s sq ft', 'ess-core' ),
				number_format_i18n( $value )
			)
			: self::EMPTY_MARK;
	}

	private static function year( $raw ): string {
		$value = (int) $raw;
		// A four-digit sanity check keeps a mistyped 202 out of the page.
		return ( $value >= 1800 && $value <= 2200 ) ? (string) $value : self::EMPTY_MARK;
	}
}
