<?php
/**
 * Configure Rank Math from code.
 *
 * Clicking through a setup wizard produces a site nobody else can reproduce.
 * Writing the settings here means a fresh clone gets the same SEO
 * configuration, and the diff shows exactly what changed when it changes.
 *
 * Usage: docker compose run --rm wpcli eval-file bin/configure-seo.php --user=admin
 *
 * @package ESS\Core
 */

// No strict_types here: `wp eval-file` runs the file through eval(),
// which rejects the declaration.

use ESS\Core\Post_Types;

defined( 'ABSPATH' ) || exit;
defined( 'WP_CLI' ) || exit( "Run this through WP-CLI.\n" );

if ( ! defined( 'RANK_MATH_VERSION' ) ) {
	WP_CLI::warning( 'Rank Math is not active; skipping SEO configuration.' );
	return;
}

// Only the modules this site uses. Every extra module is admin weight and
// another thing emitting markup nobody audited.
update_option(
	'rank_math_modules',
	[ 'sitemap', 'rich-snippet', 'local-seo', 'image-seo', 'role-manager', 'status' ]
);

// --- Titles, meta and per-post-type schema --------------------------------

$titles = (array) get_option( 'rank-math-options-titles', [] );

$titles = array_merge(
	$titles,
	[
		'noindex_empty_taxonomies' => 'on',
		'title_separator'          => '·',
		'capitalize_titles'        => 'off',

		// The studio itself, for the knowledge graph.
		'knowledgegraph_type'      => 'company',
		'knowledgegraph_name'      => 'Eastern Standard Studio',
		'local_business_type'      => 'ProfessionalService',
		'local_address'            => [
			'streetAddress'   => '168 Wythe Avenue',
			'addressLocality' => 'Brooklyn',
			'addressRegion'   => 'NY',
			'postalCode'      => '11249',
			'addressCountry'  => 'US',
		],
		'local_address_format'     => '{address} {locality}, {region} {postalcode}',
		'phone_numbers'            => [
			[ 'type' => 'customer_support', 'number' => '+1-718-555-0143' ],
		],
		'opening_hours'            => [
			[ 'day' => 'Monday',    'time' => '09:00-17:00' ],
			[ 'day' => 'Tuesday',   'time' => '09:00-17:00' ],
			[ 'day' => 'Wednesday', 'time' => '09:00-17:00' ],
			[ 'day' => 'Thursday',  'time' => '09:00-17:00' ],
			[ 'day' => 'Friday',    'time' => '09:00-16:00' ],
		],
		'opening_hours_format'     => 'on',

		// Projects: the theme emits a CreativeWork node built from the same
		// meta the page renders, so Rank Math's own snippet is turned off here
		// rather than left to emit a second, competing description of the page.
		'pt_' . Post_Types::PROJECT . '_title'                => '%title% · %sitename%',
		'pt_' . Post_Types::PROJECT . '_description'          => '%excerpt%',
		'pt_' . Post_Types::PROJECT . '_custom_robots'        => 'off',
		'pt_' . Post_Types::PROJECT . '_default_rich_snippet' => 'off',
		'pt_' . Post_Types::PROJECT . '_add_meta_box'         => 'on',

		// Services map cleanly onto schema.org/Service, so Rank Math handles them.
		'pt_' . Post_Types::SERVICE . '_title'                => '%title% · %sitename%',
		'pt_' . Post_Types::SERVICE . '_description'          => '%excerpt%',
		'pt_' . Post_Types::SERVICE . '_custom_robots'        => 'off',
		'pt_' . Post_Types::SERVICE . '_default_rich_snippet' => 'service',
		'pt_' . Post_Types::SERVICE . '_default_snippet_name' => '%title%',
		'pt_' . Post_Types::SERVICE . '_default_snippet_desc' => '%excerpt%',
		'pt_' . Post_Types::SERVICE . '_add_meta_box'         => 'on',

		'pt_page_title'                => '%title% · %sitename%',
		'pt_page_description'          => '%excerpt%',
		'pt_page_default_rich_snippet' => 'off',

		'pt_post_title'                => '%title% · %sitename%',
		'pt_post_default_rich_snippet' => 'article',

		// Studio records are private post types; keep them out of everything.
		'pt_' . Post_Types::BOOKING . '_custom_robots' => 'on',
		'pt_' . Post_Types::BOOKING . '_robots'        => [ 'noindex' ],
		'pt_' . Post_Types::INQUIRY . '_custom_robots' => 'on',
		'pt_' . Post_Types::INQUIRY . '_robots'        => [ 'noindex' ],

		'tax_' . Post_Types::TAX_SECTOR . '_title'       => '%term% projects · %sitename%',
		'tax_' . Post_Types::TAX_SECTOR . '_custom_robots' => 'off',
		'tax_' . Post_Types::TAX_DISCIPLINE . '_title'   => '%term% · %sitename%',
		'tax_' . Post_Types::TAX_DISCIPLINE . '_custom_robots' => 'off',
	]
);
update_option( 'rank-math-options-titles', $titles );

// --- General: breadcrumbs -------------------------------------------------

$general = (array) get_option( 'rank-math-options-general', [] );
$general = array_merge(
	$general,
	[
		'breadcrumbs'                  => 'on',
		'breadcrumbs_separator'        => '/',
		'breadcrumbs_home'             => 'on',
		'breadcrumbs_home_label'       => 'Home',
		'breadcrumbs_remove_post_title' => 'off',
		'strip_category_base'          => 'off',
		'nofollow_external_links'      => 'off',
		'new_window_external_links'    => 'on',
		'usage_tracking'               => 'off',
	]
);
update_option( 'rank-math-options-general', $general );

// --- Sitemap --------------------------------------------------------------

$sitemap = (array) get_option( 'rank-math-options-sitemap', [] );
$sitemap = array_merge(
	$sitemap,
	[
		'items_per_page'                            => 200,
		'include_images'                            => 'on',
		'include_featured_image'                    => 'on',
		'exclude_roles'                             => [],
		'authors_sitemap'                           => 'off',

		'pt_' . Post_Types::PROJECT . '_sitemap'    => 'on',
		'pt_' . Post_Types::SERVICE . '_sitemap'    => 'on',
		'pt_page_sitemap'                           => 'on',
		'pt_post_sitemap'                           => 'on',
		'pt_' . Post_Types::BOOKING . '_sitemap'    => 'off',
		'pt_' . Post_Types::INQUIRY . '_sitemap'    => 'off',
		'pt_attachment_sitemap'                     => 'off',

		'tax_' . Post_Types::TAX_SECTOR . '_sitemap'     => 'on',
		'tax_' . Post_Types::TAX_DISCIPLINE . '_sitemap' => 'on',
		'tax_category_sitemap'                           => 'off',
		'tax_post_tag_sitemap'                           => 'off',
	]
);
update_option( 'rank-math-options-sitemap', $sitemap );

// Skip the setup wizard; everything it would ask has been set above.
update_option( 'rank_math_wizard_completed', true );
update_option( 'rank_math_registration_skip', true );
update_option( 'rank_math_view_modes', [] );
delete_option( 'rank_math_activate_redirect' );

// Homepage meta lives on the static front page, so write it there.
$front_id = (int) get_option( 'page_on_front' );
if ( $front_id ) {
	update_post_meta( $front_id, 'rank_math_title', 'Eastern Standard Studio · Architecture and MEP engineering in Brooklyn' );
	update_post_meta(
		$front_id,
		'rank_math_description',
		'Architecture and MEP engineering under one roof in Brooklyn. Coordinated drawing sets for additions, fit-outs and mechanical replacement. Book a free 45-minute consultation.'
	);
}

// Rank Math's per-post-type default only applies to posts saved after it is
// set, so existing services carry no snippet type and emit breadcrumbs alone.
// Backfilling the per-post meta is what makes the setting retroactive.
$services = get_posts(
	[
		'post_type'      => Post_Types::SERVICE,
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	]
);
foreach ( $services as $service_id ) {
	// Current Rank Math reads `rank_math_schema_<Type>`. The older
	// `rank_math_rich_snippet` keys are still accepted by the editor UI but
	// are no longer what the front end renders from, so writing only those
	// leaves a page with breadcrumbs and nothing else.
	foreach ( array_keys( get_post_meta( $service_id ) ) as $existing_key ) {
		if ( str_starts_with( $existing_key, 'rank_math_schema_' ) || str_starts_with( $existing_key, 'rank_math_snippet' ) ) {
			delete_post_meta( $service_id, $existing_key );
		}
	}
	delete_post_meta( $service_id, 'rank_math_rich_snippet' );

	$schema = [
		'@type'       => 'Service',
		'metadata'    => [
			'title'     => 'Service',
			'type'      => 'template',
			'isPrimary' => 1,
			'shortcode' => 's-' . wp_generate_uuid4(),
		],
		'name'        => '%seo_title%',
		'description' => '%seo_description%',
		'serviceType' => get_the_title( $service_id ),
		'provider'    => [
			'@type' => 'ProfessionalService',
			'name'  => get_bloginfo( 'name' ),
			'url'   => home_url( '/' ),
		],
		'areaServed'  => 'Brooklyn, New York',
	];

	$price = preg_replace( '/[^0-9.]/', '', (string) get_post_meta( $service_id, 'ess_starting_price', true ) );
	if ( '' !== $price ) {
		$schema['offers'] = [
			'@type'         => 'Offer',
			'price'         => $price,
			'priceCurrency' => 'USD',
			'availability'  => 'https://schema.org/InStock',
			'url'           => get_permalink( $service_id ),
		];
	}

	update_post_meta( $service_id, 'rank_math_schema_Service', $schema );
}
WP_CLI::log( '  service schema backfilled: ' . count( $services ) );

if ( function_exists( 'rank_math' ) ) {
	do_action( 'rank_math/flush_sitemap_cache' );
}
flush_rewrite_rules();

WP_CLI::success( 'Rank Math configured.' );
