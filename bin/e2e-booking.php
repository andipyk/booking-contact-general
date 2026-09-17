<?php
/**
 * Print a valid nonce, a signed anti-spam stamp and the first free slot, so a
 * shell script can exercise the real booking route the way a browser would.
 *
 * Usage: docker compose run --rm wpcli eval-file bin/e2e-booking.php
 */

use ESS\Core\Anti_Spam;
use ESS\Core\Availability;

defined( 'ABSPATH' ) || exit;

$slots = Availability::slots( Availability::now(), Availability::now()->modify( '+7 days' ) );
$free  = null;
foreach ( $slots as $slot ) {
	if ( $slot['available'] ) {
		$free = $slot;
		break;
	}
}

echo wp_json_encode(
	[
		'nonce' => wp_create_nonce( 'wp_rest' ),
		'stamp' => Anti_Spam::stamp(),
		'slot'  => $free['start'] ?? '',
		'label' => $free['label'] ?? '',
	]
);
