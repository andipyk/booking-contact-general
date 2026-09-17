<?php
/**
 * Consultation slot generation.
 *
 * Every slot is generated against the studio's wall-clock timezone and then
 * converted to UTC. UTC is the only format that is stored or compared, so a
 * daylight-saving transition cannot shift an existing booking.
 *
 * @package ESS\Core
 */

declare( strict_types = 1 );

namespace ESS\Core;

defined( 'ABSPATH' ) || exit;

final class Availability {

	public const OPTION = 'ess_availability_rules';
	public const MYSQL  = 'Y-m-d H:i:s';

	/** Consultation types offered, used to validate the booking payload. */
	public const PROJECT_TYPES = [
		'residential_addition',
		'commercial_fitout',
		'mep_coordination',
		'existing_conditions_survey',
		'feasibility_review',
	];

	/**
	 * Default booking rules. Stored as an option so the studio could change
	 * them without a deploy.
	 */
	public static function defaults(): array {
		return [
			// ISO-8601 weekday (1 = Monday) => list of [open, close] local times.
			'windows'        => [
				1 => [ [ '09:00', '12:00' ], [ '13:30', '17:00' ] ],
				2 => [ [ '09:00', '12:00' ], [ '13:30', '17:00' ] ],
				3 => [ [ '09:00', '12:00' ], [ '13:30', '17:00' ] ],
				4 => [ [ '09:00', '12:00' ], [ '13:30', '17:00' ] ],
				5 => [ [ '09:00', '12:00' ], [ '13:30', '16:00' ] ],
			],
			'slot_minutes'   => 45,
			'buffer_minutes' => 15,
			// Nobody books a same-afternoon consultation with a studio principal.
			'lead_hours'     => 24,
			'horizon_days'   => 21,
			'blackout_dates' => [],
		];
	}

	public static function rules(): array {
		$stored = get_option( self::OPTION, [] );
		return is_array( $stored ) ? array_merge( self::defaults(), $stored ) : self::defaults();
	}

	/** Earliest bookable moment, in UTC. */
	public static function earliest(): \DateTimeImmutable {
		$rules = self::rules();
		return self::now()->add( new \DateInterval( 'PT' . (int) $rules['lead_hours'] . 'H' ) );
	}

	/** Latest bookable moment, in UTC. */
	public static function latest(): \DateTimeImmutable {
		$rules = self::rules();
		return self::now()->add( new \DateInterval( 'P' . (int) $rules['horizon_days'] . 'D' ) );
	}

	public static function now(): \DateTimeImmutable {
		return new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) );
	}

	/**
	 * Generate bookable slots between two UTC moments.
	 *
	 * @param bool $include_taken When true, already-booked slots are returned
	 *                            with `available => false` instead of removed.
	 *                            The front end uses this to show a full day as
	 *                            full rather than as a gap.
	 * @return list<array{start:string,end:string,date:string,time:string,label:string,available:bool}>
	 */
	public static function slots( \DateTimeImmutable $from_utc, \DateTimeImmutable $to_utc, bool $include_taken = true ): array {
		$rules  = self::rules();
		$studio = studio_timezone();
		$utc    = new \DateTimeZone( 'UTC' );

		$earliest = self::earliest();
		$latest   = self::latest();

		// Clamp the request to the bookable horizon so a crafted query cannot
		// make the server generate years of slots.
		$from = max( $from_utc, $earliest );
		$to   = min( $to_utc, $latest );
		if ( $from > $to ) {
			return [];
		}

		$step     = (int) $rules['slot_minutes'] + (int) $rules['buffer_minutes'];
		$duration = (int) $rules['slot_minutes'];
		$blackout = array_flip( (array) $rules['blackout_dates'] );
		$taken    = Slot_Lock::taken_between( $from, $to );

		// Walk local calendar days: a "Tuesday 09:00" slot is defined in the
		// studio's timezone, not in UTC.
		$cursor = $from->setTimezone( $studio )->setTime( 0, 0 );
		$end    = $to->setTimezone( $studio )->setTime( 23, 59, 59 );

		$slots = [];
		while ( $cursor <= $end ) {
			$date    = $cursor->format( 'Y-m-d' );
			$weekday = (int) $cursor->format( 'N' );

			if ( isset( $blackout[ $date ] ) || empty( $rules['windows'][ $weekday ] ) ) {
				$cursor = $cursor->modify( '+1 day' )->setTime( 0, 0 );
				continue;
			}

			foreach ( $rules['windows'][ $weekday ] as [ $open, $close ] ) {
				$window_open  = self::local( $date, $open, $studio );
				$window_close = self::local( $date, $close, $studio );
				if ( ! $window_open || ! $window_close ) {
					continue;
				}

				$slot_start = $window_open;
				while ( true ) {
					$slot_end = $slot_start->add( new \DateInterval( 'PT' . $duration . 'M' ) );
					if ( $slot_end > $window_close ) {
						break;
					}

					$start_utc = $slot_start->setTimezone( $utc );
					$end_utc   = $slot_end->setTimezone( $utc );

					if ( $start_utc >= $from && $start_utc <= $to ) {
						$key       = $start_utc->format( self::MYSQL );
						$available = ! isset( $taken[ $key ] );

						if ( $available || $include_taken ) {
							$slots[] = [
								'start'     => $key,
								'end'       => $end_utc->format( self::MYSQL ),
								'date'      => $slot_start->format( 'Y-m-d' ),
								'time'      => $slot_start->format( 'g:i a' ),
								'label'     => $slot_start->format( 'D j M, g:i a' ),
								'available' => $available,
							];
						}
					}

					$slot_start = $slot_start->add( new \DateInterval( 'PT' . $step . 'M' ) );
				}
			}

			$cursor = $cursor->modify( '+1 day' )->setTime( 0, 0 );
		}

		usort( $slots, static fn( array $a, array $b ): int => strcmp( $a['start'], $b['start'] ) );
		return $slots;
	}

	/**
	 * Build a local DateTimeImmutable, rejecting times that do not exist.
	 *
	 * On a spring-forward day PHP silently rolls a non-existent local time
	 * forward. Comparing the formatted result back against the requested time
	 * catches that instead of quietly offering a slot that no clock ever shows.
	 */
	private static function local( string $date, string $time, \DateTimeZone $tz ): ?\DateTimeImmutable {
		$built = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $date . ' ' . $time, $tz );
		if ( ! $built || $built->format( 'Y-m-d H:i' ) !== $date . ' ' . $time ) {
			return null;
		}
		return $built->setTime( (int) $built->format( 'H' ), (int) $built->format( 'i' ), 0 );
	}

	/**
	 * Confirm a submitted slot is one the studio actually offers.
	 *
	 * The booking route must never trust a client-supplied timestamp: without
	 * this check a crafted request could book 03:00 on a Sunday.
	 *
	 * @return array{start:string,end:string,label:string}|null
	 */
	public static function resolve( string $start_utc ): ?array {
		$parsed = \DateTimeImmutable::createFromFormat(
			self::MYSQL,
			$start_utc,
			new \DateTimeZone( 'UTC' )
		);
		if ( ! $parsed || $parsed->format( self::MYSQL ) !== $start_utc ) {
			return null;
		}

		// Generate just the day the request names, then look for an exact match.
		$window_start = $parsed->modify( '-1 day' );
		$window_end   = $parsed->modify( '+1 day' );

		foreach ( self::slots( $window_start, $window_end ) as $slot ) {
			if ( $slot['start'] === $start_utc ) {
				return $slot['available']
					? [
						'start' => $slot['start'],
						'end'   => $slot['end'],
						'label' => $slot['label'],
					]
					: null;
			}
		}

		return null;
	}

	/** Render a stored UTC timestamp in the studio's timezone. */
	public static function to_studio( string $utc, string $format = 'D j M Y, g:i a T' ): string {
		$parsed = \DateTimeImmutable::createFromFormat( self::MYSQL, $utc, new \DateTimeZone( 'UTC' ) );
		return $parsed ? $parsed->setTimezone( studio_timezone() )->format( $format ) : '';
	}
}
