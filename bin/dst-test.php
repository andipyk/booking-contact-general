<?php
/**
 * Assert slot generation stays correct across the US DST boundary.
 * 2026: DST ends Sunday 1 November. A 09:00 studio slot is 13:00 UTC before
 * that date (EDT, UTC-4) and 14:00 UTC after it (EST, UTC-5).
 */
use ESS\Core\Availability;

// Widen the horizon just for this run so November is reachable.
update_option( Availability::OPTION, array_merge( Availability::defaults(), [ 'horizon_days' => 120 ] ) );

$utc   = new DateTimeZone( 'UTC' );
$from  = new DateTimeImmutable( '2026-10-28 00:00:00', $utc );
$to    = new DateTimeImmutable( '2026-11-06 23:59:59', $utc );
$slots = Availability::slots( $from, $to );

$nine_am = [];
foreach ( $slots as $slot ) {
	if ( '9:00 am' === $slot['time'] ) {
		$nine_am[ $slot['date'] ] = $slot['start'];
	}
}

$expect = [
	'2026-10-29' => '2026-10-29 13:00:00', // Thu, EDT
	'2026-10-30' => '2026-10-30 13:00:00', // Fri, EDT
	'2026-11-02' => '2026-11-02 14:00:00', // Mon, EST — the day after the change
	'2026-11-03' => '2026-11-03 14:00:00', // Tue, EST
];

$fail = 0;
foreach ( $expect as $date => $want ) {
	$got = $nine_am[ $date ] ?? '(no slot)';
	$ok  = ( $got === $want );
	if ( ! $ok ) {
		++$fail;
	}
	printf( "%s  9:00 am local -> %-22s expected %-22s %s\n", $date, $got, $want, $ok ? 'PASS' : 'FAIL' );
}

// Weekend must stay closed even across the transition.
$sunday = isset( $nine_am['2026-11-01'] ) ? 'FAIL (slot generated)' : 'PASS (closed)';
printf( "2026-11-01  Sunday, the transition day itself  -> %s\n", $sunday );
if ( isset( $nine_am['2026-11-01'] ) ) {
	++$fail;
}

// Round-tripping a stored UTC value back to studio time must still read 9:00.
$back = Availability::to_studio( '2026-11-02 14:00:00', 'g:i a T' );
printf( "round-trip 2026-11-02 14:00 UTC -> %-14s %s\n", $back, str_contains( $back, '9:00 am' ) ? 'PASS' : 'FAIL' );
if ( ! str_contains( $back, '9:00 am' ) ) {
	++$fail;
}

delete_option( Availability::OPTION );
echo $fail === 0 ? "\n✅ DST: all assertions passed\n" : "\n❌ DST: {$fail} assertion(s) failed\n";
