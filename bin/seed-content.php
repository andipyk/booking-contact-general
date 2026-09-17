<?php
/**
 * Seed the demo site.
 *
 * Content as code: running this against an empty install produces the same
 * site every time, which is the difference between a demo someone else can
 * reproduce and a database nobody can rebuild. Re-running is safe — every
 * object is looked up by slug first and updated rather than duplicated.
 *
 * Usage: docker compose run --rm wpcli eval-file bin/seed-content.php --user=admin
 *
 * @package ESS\Core
 */

// No strict_types here: `wp eval-file` runs the file through eval(),
// which rejects the declaration.

use ESS\Core\Post_Types;

defined( 'ABSPATH' ) || exit;
defined( 'WP_CLI' ) || exit( "Run this through WP-CLI.\n" );

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

/** Find a post by slug within a post type, or return 0. */
function ess_seed_find( string $slug, string $post_type ): int {
	$found = get_posts(
		[
			'name'           => $slug,
			'post_type'      => $post_type,
			'post_status'    => [ 'publish', 'draft' ],
			'posts_per_page' => 1,
			'fields'         => 'ids',
		]
	);
	return $found ? (int) $found[0] : 0;
}

/** Create or update one post, returning its ID. */
function ess_seed_post( array $args, array $meta = [], array $terms = [] ): int {
	$existing = ess_seed_find( $args['post_name'], $args['post_type'] );
	if ( $existing ) {
		$args['ID'] = $existing;
	}
	$args['post_status'] = 'publish';

	$post_id = wp_insert_post( $args, true );
	if ( is_wp_error( $post_id ) ) {
		WP_CLI::warning( 'Could not save ' . $args['post_name'] . ': ' . $post_id->get_error_message() );
		return 0;
	}

	foreach ( $meta as $key => $value ) {
		update_post_meta( (int) $post_id, $key, $value );
	}
	foreach ( $terms as $taxonomy => $names ) {
		wp_set_object_terms( (int) $post_id, $names, $taxonomy, false );
	}

	return (int) $post_id;
}

/** Sideload a seed image once and attach it. */
function ess_seed_image( int $post_id, string $filename, string $alt ): void {
	if ( get_post_thumbnail_id( $post_id ) ) {
		return;
	}

	$source = ABSPATH . 'bin/seed-images/' . $filename;
	if ( ! file_exists( $source ) ) {
		WP_CLI::warning( 'Seed image missing: ' . $filename );
		return;
	}

	// media_handle_sideload moves the file, so hand it a copy and keep ours.
	$tmp = wp_tempnam( $filename );
	copy( $source, $tmp );

	$attachment_id = media_handle_sideload(
		[ 'name' => $filename, 'tmp_name' => $tmp ],
		$post_id,
		$alt
	);

	if ( is_wp_error( $attachment_id ) ) {
		@unlink( $tmp );
		WP_CLI::warning( 'Sideload failed for ' . $filename . ': ' . $attachment_id->get_error_message() );
		return;
	}

	update_post_meta( (int) $attachment_id, '_wp_attachment_image_alt', $alt );
	set_post_thumbnail( $post_id, (int) $attachment_id );
}

// ---------------------------------------------------------------------------
// Services
// ---------------------------------------------------------------------------

$services = [
	[
		'slug'    => 'architecture',
		'title'   => 'Architecture',
		'order'   => 1,
		'excerpt' => 'Schematic design through construction administration for additions, conversions and ground-up work under 75 feet.',
		'lead'    => '6–10 weeks to filing set',
		'price'   => '$12,000',
		'deliver' => 'Filed drawing set plus DOB correspondence',
		'body'    => '<!-- wp:paragraph --><p>We work at the scale where one team can hold the whole project in its head: brownstone additions, floor conversions, and ground-up buildings under 75 feet. That scale is a deliberate choice. Above it, the coordination overhead grows faster than the design work, and clients end up paying for meetings.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>Every set leaves this office already checked against the mechanical drawings, because the engineers sit twenty feet away. A client who hires an architect and an engineer separately is paying, somewhere in the schedule, for the two of them to discover each other.</p><!-- /wp:paragraph -->
<!-- wp:heading {"level":3} --><h3 class="wp-block-heading">What you get</h3><!-- /wp:heading -->
<!-- wp:list --><ul class="wp-block-list"><li>Zoning and code analysis before design begins</li><li>Schematic design, design development and construction documents</li><li>Filing through DOB NOW, including objection responses</li><li>Site visits and RFI responses during construction</li></ul><!-- /wp:list -->',
	],
	[
		'slug'    => 'mep-engineering',
		'title'   => 'MEP engineering',
		'order'   => 2,
		'excerpt' => 'Mechanical, electrical and plumbing design coordinated against the architecture before the set is issued.',
		'lead'    => '4–7 weeks',
		'price'   => '$8,500',
		'deliver' => 'Coordinated MEP set with load calculations',
		'body'    => '<!-- wp:paragraph --><p>Most of what goes wrong on a small commercial job goes wrong in the ceiling. A duct crosses a beam, a condensate line has nowhere to fall, a panel is three circuits short. None of it is hard to solve on paper; all of it is expensive to solve on site.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>We model the services in the same file as the structure and run clash detection before issue. When we hand over a set, the ceiling has been resolved at the drawing stage.</p><!-- /wp:paragraph -->
<!-- wp:heading {"level":3} --><h3 class="wp-block-heading">What you get</h3><!-- /wp:heading -->
<!-- wp:list --><ul class="wp-block-list"><li>Heating and cooling load calculations</li><li>Ductwork and piping layouts coordinated against structure</li><li>Panel schedules and riser diagrams</li><li>Energy code compliance documentation</li></ul><!-- /wp:list -->',
	],
	[
		'slug'    => 'existing-conditions',
		'title'   => 'Existing conditions',
		'order'   => 3,
		'excerpt' => 'Measured surveys and point-cloud scanning, turned into a model every later drawing can sit on.',
		'lead'    => '2–3 weeks',
		'price'   => '$4,200',
		'deliver' => 'Dimensioned as-built drawings and Revit model',
		'body'    => '<!-- wp:paragraph --><p>Every drawing after this one inherits its errors, so this is the phase worth spending on. We measure by hand for small spaces and scan anything over about four thousand square feet or with geometry that hand measurement would flatten.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>If you already hold a point cloud, send it. We would rather register to yours than bill you for a second survey, though we will spot-check a few dimensions on site before building on a scan that predates a fit-out.</p><!-- /wp:paragraph -->
<!-- wp:heading {"level":3} --><h3 class="wp-block-heading">What you get</h3><!-- /wp:heading -->
<!-- wp:list --><ul class="wp-block-list"><li>Laser survey or point-cloud capture</li><li>Dimensioned plans, elevations and sections</li><li>A Revit model later phases build on directly</li><li>A photographic record of concealed conditions</li></ul><!-- /wp:list -->',
	],
	[
		'slug'    => 'feasibility-and-zoning',
		'title'   => 'Feasibility and zoning',
		'order'   => 4,
		'excerpt' => 'A short study answering whether the idea is buildable, and what it would cost to find out properly.',
		'lead'    => '1–2 weeks',
		'price'   => '$2,800',
		'deliver' => 'Written study with massing diagrams',
		'body'    => '<!-- wp:paragraph --><p>Before anyone commissions drawings, one question needs an answer: is this legal, and roughly what does it cost? A feasibility study answers it in two weeks for a fraction of a design fee, and occasionally it answers no. That is the outcome that saves the most money.</p><!-- /wp:paragraph -->
<!-- wp:heading {"level":3} --><h3 class="wp-block-heading">What you get</h3><!-- /wp:heading -->
<!-- wp:list --><ul class="wp-block-list"><li>Zoning analysis: FAR, setbacks, use groups, overlays</li><li>Massing diagrams of what the envelope permits</li><li>An order-of-magnitude construction cost range</li><li>A recommended path, including when to walk away</li></ul><!-- /wp:list -->',
	],
	[
		'slug'    => 'filing-and-expediting',
		'title'   => 'Filing and expediting',
		'order'   => 5,
		'excerpt' => 'DOB NOW filings, objection responses and sign-off, handled by the people who drew the set.',
		'lead'    => '3–12 weeks at DOB',
		'price'   => '$3,500',
		'deliver' => 'Approved permit and sign-off package',
		'body'    => '<!-- wp:paragraph --><p>Filings go faster when the person answering the objection is the person who drew the detail. We file our own work and we file for other architects who would rather not spend their week inside DOB NOW.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>The timeline above is the department\'s, and we have no influence over it. What we control is how many rounds of objections it takes, and that number comes down when the set was coordinated before it went in.</p><!-- /wp:paragraph -->',
	],
];

$service_count = 0;
foreach ( $services as $service ) {
	$id = ess_seed_post(
		[
			'post_type'    => Post_Types::SERVICE,
			'post_name'    => $service['slug'],
			'post_title'   => $service['title'],
			'post_excerpt' => $service['excerpt'],
			'post_content' => $service['body'],
			'menu_order'   => $service['order'],
		],
		[
			'ess_lead_time'      => $service['lead'],
			'ess_starting_price' => $service['price'],
			'ess_deliverable'    => $service['deliver'],
		]
	);
	if ( $id ) {
		++$service_count;
	}
}
WP_CLI::log( "  services: {$service_count}" );

// ---------------------------------------------------------------------------
// Projects
// ---------------------------------------------------------------------------

$projects = [
	[
		'slug'     => 'wythe-avenue-addition',
		'title'    => 'Wythe Avenue rooftop addition',
		'image'    => 'wythe-avenue-addition.jpg',
		'alt'      => 'Plan fragment of the Wythe Avenue rooftop addition showing the structural grid and a service run',
		'excerpt'  => 'A two-storey addition over an occupied warehouse, threaded between an existing elevator core and a cornice the landmarks commission would not let us touch.',
		'location' => 'Williamsburg, Brooklyn',
		'year'     => 2024,
		'sqft'     => 4800,
		'client'   => 'Private owner',
		'scope'    => 'Architecture + MEP',
		'duration' => '19 months',
		'sectors'  => [ 'Commercial' ],
		'disc'     => [ 'Architecture', 'MEP engineering' ],
		'body'     => '<!-- wp:paragraph --><p>The building had been a textile warehouse, then storage, and by the time we saw it the roof carried three generations of abandoned equipment. The owner wanted two floors of office over the top without emptying the tenants below.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>The existing elevator stopped one floor short and the shaft could not move, so the addition had to work around a core that ended in the wrong place. We put the stair where the plan wanted the core and ran the new mechanical risers through the gap left by a removed freight hoist.</p><!-- /wp:paragraph -->
<!-- wp:heading {"level":3} --><h3 class="wp-block-heading">The hard part</h3><!-- /wp:heading -->
<!-- wp:paragraph --><p>Landmarks approval turned on the addition being invisible from the opposite sidewalk. We modelled sight lines from eleven points along the block and set the parapet from the worst of them, which cost about nine inches of ceiling height on the upper floor.</p><!-- /wp:paragraph -->',
	],
	[
		'slug'     => 'gowanus-workshop-fitout',
		'title'    => 'Gowanus workshop fit-out',
		'image'    => 'gowanus-workshop-fitout.jpg',
		'alt'      => 'Plan fragment of the Gowanus workshop showing bays and a dust-extraction run',
		'excerpt'  => 'A furniture workshop in a flood-zone ground floor, where every piece of equipment had to sit above the design flood elevation.',
		'location' => 'Gowanus, Brooklyn',
		'year'     => 2025,
		'sqft'     => 6200,
		'client'   => 'Fabrication business',
		'scope'    => 'Architecture + MEP',
		'duration' => '11 months',
		'sectors'  => [ 'Industrial' ],
		'disc'     => [ 'Architecture', 'MEP engineering' ],
		'body'     => '<!-- wp:paragraph --><p>Ground-floor space in Gowanus comes with a flood elevation, and flood elevation means the electrical service, the dust collection motor and the compressor all live on a platform. The client had budgeted for none of that.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>We worked the platform into the plan as a mezzanine office instead of a raised slab, which recovered the floor area the platform would have cost and gave the owner somewhere to meet clients away from the noise.</p><!-- /wp:paragraph -->',
	],
	[
		'slug'     => 'prospect-heights-brownstone',
		'title'    => 'Prospect Heights brownstone',
		'image'    => 'prospect-heights-brownstone.jpg',
		'alt'      => 'Plan fragment of the Prospect Heights brownstone showing the rear extension',
		'excerpt'  => 'A rear extension and full mechanical replacement in a single-family brownstone, drawn from a point cloud because nothing in the house was square.',
		'location' => 'Prospect Heights, Brooklyn',
		'year'     => 2024,
		'sqft'     => 3100,
		'client'   => 'Private owner',
		'scope'    => 'Architecture + MEP + survey',
		'duration' => '14 months',
		'sectors'  => [ 'Residential' ],
		'disc'     => [ 'Architecture', 'MEP engineering', 'Survey' ],
		'body'     => '<!-- wp:paragraph --><p>An 1890s brownstone that had settled about four inches front to back and been renovated twice by people who did not measure. Hand survey would have produced a drawing that looked right and fit nothing.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>We scanned the whole house, built the model from the cloud, and designed the extension against the geometry that was there. The steel fabricator worked from the same model, and the beam fitted on the first attempt.</p><!-- /wp:paragraph -->',
	],
	[
		'slug'     => 'navy-yard-lab',
		'title'    => 'Navy Yard materials lab',
		'image'    => 'navy-yard-lab.jpg',
		'alt'      => 'Plan fragment of the Navy Yard materials lab showing the fume-hood bank',
		'excerpt'  => 'A small testing laboratory with six fume hoods, where the exhaust design drove the entire floor plan.',
		'location' => 'Brooklyn Navy Yard',
		'year'     => 2025,
		'sqft'     => 5400,
		'client'   => 'Research tenant',
		'scope'    => 'MEP engineering',
		'duration' => '9 months',
		'sectors'  => [ 'Institutional' ],
		'disc'     => [ 'MEP engineering' ],
		'body'     => '<!-- wp:paragraph --><p>Six fume hoods in a building with one available shaft. The exhaust had to reach the roof, and the shaft could carry about half what six hoods wanted at full flow.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>Variable-air-volume hoods with a manifolded exhaust solved it: the hoods are rarely all open at once, and diversity brought the peak inside what the shaft could take. The alternative was a second shaft through four tenanted floors.</p><!-- /wp:paragraph -->',
	],
	[
		'slug'     => 'bed-stuy-passive-house',
		'title'    => 'Bed-Stuy passive house retrofit',
		'image'    => 'bed-stuy-passive-house.jpg',
		'alt'      => 'Plan fragment of the Bed-Stuy retrofit showing the continuous air barrier',
		'excerpt'  => 'A deep-energy retrofit of a two-family house, taken to passive-house airtightness without touching the street facade.',
		'location' => 'Bedford-Stuyvesant, Brooklyn',
		'year'     => 2023,
		'sqft'     => 2600,
		'client'   => 'Private owner',
		'scope'    => 'Architecture + MEP',
		'duration' => '16 months',
		'sectors'  => [ 'Residential' ],
		'disc'     => [ 'Architecture', 'MEP engineering' ],
		'body'     => '<!-- wp:paragraph --><p>Airtightness is a detailing problem before it is a products problem. The street facade was protected, so the air barrier had to run on the inside face and stay continuous through every floor joist pocket.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>We drew the barrier as a single unbroken line on every section and made the contractor initial each junction at the blower-door test. The house came in at 0.048 CFM per square foot of envelope.</p><!-- /wp:paragraph -->',
	],
	[
		'slug'     => 'dumbo-loft-mep',
		'title'    => 'Dumbo loft mechanical replacement',
		'image'    => 'dumbo-loft-mep.jpg',
		'alt'      => 'Plan fragment of the Dumbo loft showing replacement riser positions',
		'excerpt'  => 'Replacing the heating and cooling in eleven occupied loft apartments, one riser at a time, without moving anyone out.',
		'location' => 'Dumbo, Brooklyn',
		'year'     => 2023,
		'sqft'     => 18400,
		'client'   => 'Co-op board',
		'scope'    => 'MEP engineering',
		'duration' => '22 months',
		'sectors'  => [ 'Residential' ],
		'disc'     => [ 'MEP engineering' ],
		'body'     => '<!-- wp:paragraph --><p>A co-op with a failing two-pipe system and no appetite for anyone to move out. The constraint was scheduling rather than engineering: any apartment could lose service for at most four working days.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>We split the work into eleven riser-by-riser phases and sequenced them so no two vertically adjacent units were open at once. The drawings were issued as eleven separate packages, each one standing on its own.</p><!-- /wp:paragraph -->',
	],
];

$project_count = 0;
foreach ( $projects as $project ) {
	$id = ess_seed_post(
		[
			'post_type'    => Post_Types::PROJECT,
			'post_name'    => $project['slug'],
			'post_title'   => $project['title'],
			'post_excerpt' => $project['excerpt'],
			'post_content' => $project['body'],
		],
		[
			'ess_location'       => $project['location'],
			'ess_year_completed' => $project['year'],
			'ess_square_feet'    => $project['sqft'],
			'ess_client_type'    => $project['client'],
			'ess_scope'          => $project['scope'],
			'ess_duration'       => $project['duration'],
		],
		[
			Post_Types::TAX_SECTOR     => $project['sectors'],
			Post_Types::TAX_DISCIPLINE => $project['disc'],
		]
	);
	if ( $id ) {
		ess_seed_image( $id, $project['image'], $project['alt'] );
		++$project_count;
	}
}
WP_CLI::log( "  projects: {$project_count}" );

// ---------------------------------------------------------------------------
// Pages
// ---------------------------------------------------------------------------

$pages = [
	[
		'slug'    => 'home',
		'title'   => 'Home',
		'excerpt' => 'Architecture and MEP engineering under one roof in Brooklyn. Coordinated drawing sets for additions, fit-outs and mechanical replacement.',
		'body'    => '',
	],
	[
		'slug'    => 'book-a-consultation',
		'title'   => 'Book a consultation',
		'excerpt' => 'Pick a time for a free 45-minute consultation with a principal at Eastern Standard Studio. Bring a survey, a lease plan or a photograph, and leave with a straight answer on scope and programme.',
		'body'    => '<!-- wp:pattern {"slug":"eastern-standard/booking-section"} /-->',
	],
	[
		'slug'    => 'contact',
		'title'   => 'Contact',
		'excerpt' => 'Message the studio about a new or existing project in Brooklyn. We reply within one business day, or you can book a consultation directly.',
		'body'    => '<!-- wp:pattern {"slug":"eastern-standard/contact-section"} /-->',
	],
	[
		'slug'    => 'studio',
		'title'   => 'Studio',
		'excerpt' => 'Nine people, one office, both disciplines. How Eastern Standard Studio works and why the practice stays small on purpose.',
		'body'    => '<!-- wp:paragraph {"fontSize":"large"} --><p class="has-large-font-size">Eastern Standard Studio is an architecture and MEP engineering practice in Williamsburg. Nine people, one office, both disciplines.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>We started in 2011 doing mechanical drawings for other architects, and kept being asked to fix problems that had been designed in months earlier. Adding an architecture studio was the shortest route to those problems not existing.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>The practice stays small on purpose. Every project has a principal on it, and that principal has been to the site. We take roughly fourteen projects a year, which is what nine people can do without the work going thin.</p><!-- /wp:paragraph -->
<!-- wp:heading {"level":2} --><h2 class="wp-block-heading">How we work</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>Drawings are coordinated between disciplines before they leave the office. That sounds like table stakes and it is, but it is uncommon enough that clients mention it in feedback, usually by describing the last project where it did not happen.</p><!-- /wp:paragraph -->
<!-- wp:pattern {"slug":"eastern-standard/process-steps"} /-->
<!-- wp:pattern {"slug":"eastern-standard/booking-cta"} /-->',
	],
];

$page_ids = [];
foreach ( $pages as $page ) {
	$page_ids[ $page['slug'] ] = ess_seed_post(
		[
			'post_type'    => 'page',
			'post_name'    => $page['slug'],
			'post_title'   => $page['title'],
			'post_excerpt' => $page['excerpt'],
			'post_content' => $page['body'],
		]
	);
}
WP_CLI::log( '  pages: ' . count( array_filter( $page_ids ) ) );

// The front page template renders the home layout, so the page itself stays
// empty and exists only to be the static front page.
if ( ! empty( $page_ids['home'] ) ) {
	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', $page_ids['home'] );
}

update_option( 'blogdescription', 'Architecture and MEP engineering, Brooklyn NY' );
update_option( 'blogname', 'Eastern Standard Studio' );

WP_CLI::success( 'Seed complete.' );
