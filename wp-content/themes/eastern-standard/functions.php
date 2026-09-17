<?php
/**
 * Eastern Standard theme setup.
 *
 * Deliberately short. Design lives in theme.json, layout in templates and
 * patterns, and everything that is site functionality lives in the ess-core
 * plugin. What remains here is asset loading, the pattern category, and the
 * structured data the SEO plugin has no vocabulary for.
 *
 * @package EasternStandard
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

const ESS_THEME_VERSION = '1.0.0';

/**
 * Theme supports.
 *
 * Most of what a classic theme declared here is now theme.json's job; these are
 * the ones that still live in PHP.
 */
add_action(
	'after_setup_theme',
	static function (): void {
		add_theme_support( 'wp-block-styles' );
		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'editor-styles' );
		add_theme_support( 'custom-logo', [ 'height' => 40, 'width' => 200, 'flex-width' => true ] );
		add_theme_support( 'post-thumbnails' );
		add_editor_style( 'assets/editor.css' );

		// Project cards are 3:2; generating the size means the grid never
		// downloads a 2000px original to display it at 400px.
		add_image_size( 'ess-card', 800, 534, true );
		add_image_size( 'ess-hero', 1800, 1013, true );
	}
);

/**
 * Front-end styles.
 */
add_action(
	'wp_enqueue_scripts',
	static function (): void {
		// Locally the file's mtime is the cache key, so an edited stylesheet is
		// actually re-fetched instead of silently served from cache.
		$css  = get_theme_file_path( 'assets/theme.css' );
		$ver  = ( 'local' === wp_get_environment_type() && file_exists( $css ) )
			? (string) filemtime( $css )
			: ESS_THEME_VERSION;

		wp_enqueue_style(
			'eastern-standard',
			get_theme_file_uri( 'assets/theme.css' ),
			[],
			$ver
		);
	}
);

/**
 * Preload the two fonts that render above the fold.
 *
 * theme.json emits the @font-face rules, but the browser only discovers them
 * after the stylesheet parses. Preloading removes that round trip, which is
 * what shows up as a better LCP.
 */
add_action(
	'wp_head',
	static function (): void {
		foreach ( [ 'inter-variable.woff2', 'instrument-serif.woff2' ] as $font ) {
			printf(
				'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
				esc_url( get_theme_file_uri( 'assets/fonts/' . $font ) )
			);
		}
	},
	1
);

/**
 * A pattern category, so the studio's own patterns are not scattered through
 * the inserter's generic buckets.
 */
add_action(
	'init',
	static function (): void {
		register_block_pattern_category(
			'eastern-standard',
			[ 'label' => __( 'Eastern Standard', 'eastern-standard' ) ]
		);
	}
);

/**
 * Project-specific structured data.
 *
 * Rank Math handles the site-level graph (organisation, breadcrumbs, sitemaps).
 * It has no schema type for a design project, so the CreativeWork node for a
 * single project is emitted here from the same meta the page renders — the
 * markup and the structured data cannot disagree, because they read one source.
 */
add_action(
	'wp_head',
	static function (): void {
		if ( ! is_singular( 'ess_project' ) ) {
			return;
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return;
		}

		$graph = [
			'@context' => 'https://schema.org',
			'@type'    => 'CreativeWork',
			'name'     => get_the_title( $post_id ),
			'url'      => get_permalink( $post_id ),
			'abstract' => wp_strip_all_tags( (string) get_the_excerpt( $post_id ) ),
			'creator'  => [
				'@type' => 'ProfessionalService',
				'name'  => get_bloginfo( 'name' ),
				'url'   => home_url( '/' ),
			],
		];

		$year = (int) get_post_meta( $post_id, 'ess_year_completed', true );
		if ( $year > 0 ) {
			$graph['dateCreated'] = (string) $year;
		}

		$location = (string) get_post_meta( $post_id, 'ess_location', true );
		if ( '' !== $location ) {
			$graph['locationCreated'] = [ '@type' => 'Place', 'name' => $location ];
		}

		$thumbnail = get_the_post_thumbnail_url( $post_id, 'ess-hero' );
		if ( $thumbnail ) {
			$graph['image'] = $thumbnail;
		}

		$disciplines = wp_get_post_terms( $post_id, 'ess_discipline', [ 'fields' => 'names' ] );
		if ( ! is_wp_error( $disciplines ) && $disciplines ) {
			$graph['keywords'] = implode( ', ', $disciplines );
		}

		printf(
			'<script type="application/ld+json">%s</script>' . "\n",
			wp_json_encode( $graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
		);
	},
	20
);

/**
 * Show every project on the archive rather than paginating at ten.
 *
 * A studio with a few dozen projects wants one browsable wall of work; a
 * paginated portfolio buries the older projects nobody clicks through to.
 */
add_action(
	'pre_get_posts',
	static function ( WP_Query $query ): void {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( $query->is_post_type_archive( 'ess_project' ) ) {
			$query->set( 'posts_per_page', 24 );
		}
		if ( $query->is_post_type_archive( 'ess_service' ) ) {
			$query->set( 'posts_per_page', 20 );
			$query->set( 'orderby', 'menu_order title' );
			$query->set( 'order', 'ASC' );
		}
	}
);
