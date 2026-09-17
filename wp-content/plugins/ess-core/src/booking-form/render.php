<?php
/**
 * Server render for the consultation booking block.
 *
 * The first two weeks of slots are rendered into the HTML here, before any
 * JavaScript runs. That keeps the block useful to a crawler, removes the
 * "loading…" flash a client-fetched calendar would show, and means the derived
 * state the directives read is already correct on first paint.
 *
 * @package ESS\Core
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner content.
 * @var WP_Block $block      Block instance.
 */

declare( strict_types = 1 );

use ESS\Core\Availability;
use ESS\Core\Blocks;

defined( 'ABSPATH' ) || exit;

$ess_days_ahead = max( 1, min( 31, (int) ( $attributes['days'] ?? 14 ) ) );
$ess_heading    = (string) ( $attributes['heading'] ?? __( 'Book a consultation', 'ess-core' ) );
$ess_intro      = (string) ( $attributes['intro'] ?? '' );

$ess_now   = Availability::now();
$ess_slots = Availability::slots( $ess_now, $ess_now->add( new DateInterval( 'P' . $ess_days_ahead . 'D' ) ) );

// Group into days for the picker. Days with no free slot still render, shown as
// full, so the calendar reads as a calendar rather than as a list with holes.
$ess_days = [];
foreach ( $ess_slots as $ess_slot ) {
	$ess_date = $ess_slot['date'];
	if ( ! isset( $ess_days[ $ess_date ] ) ) {
		$ess_parsed          = DateTimeImmutable::createFromFormat( 'Y-m-d', $ess_date, ESS\Core\studio_timezone() );
		$ess_days[ $ess_date ] = [
			'date'    => $ess_date,
			'weekday' => $ess_parsed ? $ess_parsed->format( 'D' ) : '',
			'day'     => $ess_parsed ? $ess_parsed->format( 'j' ) : '',
			'month'   => $ess_parsed ? $ess_parsed->format( 'M' ) : '',
			'slots'   => [],
			'open'    => 0,
		];
	}
	$ess_days[ $ess_date ]['slots'][] = $ess_slot;
	if ( $ess_slot['available'] ) {
		++$ess_days[ $ess_date ]['open'];
	}
}
$ess_days = array_values( $ess_days );

// Preselect the first day that actually has room.
$ess_selected_date = '';
foreach ( $ess_days as $ess_day ) {
	if ( $ess_day['open'] > 0 ) {
		$ess_selected_date = $ess_day['date'];
		break;
	}
}

$ess_config = Blocks::form_config();

$ess_project_types = [
	'residential_addition'       => __( 'Residential addition or renovation', 'ess-core' ),
	'commercial_fitout'          => __( 'Commercial fit-out', 'ess-core' ),
	'mep_coordination'           => __( 'MEP coordination', 'ess-core' ),
	'existing_conditions_survey' => __( 'Existing-conditions survey', 'ess-core' ),
	'feasibility_review'         => __( 'Feasibility review', 'ess-core' ),
];

wp_interactivity_state(
	'ess/booking',
	[
		'timezone' => ESS\Core\studio_timezone()->getName(),
		'restRoot' => $ess_config['root'],
		'nonce'    => $ess_config['nonce'],
		'stamp'    => $ess_config['stamp'],

		// Derived state, computed in PHP as well as JS. Without the PHP half,
		// the hidden attributes below would be absent on first paint and the
		// block would flash its empty state before hydrating.
		'hasSlots'       => static function () {
			$state = wp_interactivity_state();
			return ! empty( $state['dayCount'] );
		},
		'isSelectedDay'  => static function () {
			$context = wp_interactivity_get_context();
			return isset( $context['date'], $context['selectedDate'] )
				&& $context['date'] === $context['selectedDate'];
		},
		'isSelectedSlot' => static function () {
			$context = wp_interactivity_get_context();
			return ! empty( $context['slot'] )
				&& ( $context['slot'] === ( $context['selectedSlot'] ?? '' ) );
		},
		'submitLabel'    => static function () {
			$state   = wp_interactivity_state();
			$context = wp_interactivity_get_context();
			return ! empty( $context['submitting'] )
				? $state['labels']['submitting']
				: $state['labels']['submit'];
		},
		'dayCount'       => count( $ess_days ),

		// Translated once in PHP and read from the view module, so the module
		// needs no i18n runtime of its own and the two halves cannot drift.
		'labels'         => [
			'submit'       => __( 'Confirm consultation', 'ess-core' ),
			'submitting'   => __( 'Booking…', 'ess-core' ),
			'genericError' => __( 'We could not save your booking. Please try again.', 'ess-core' ),
			'networkError' => __( 'Could not reach the studio. Check your connection and try again.', 'ess-core' ),
			'missingSlot'  => __( 'Please choose a time first.', 'ess-core' ),
		],
	]
);

$ess_context = [
	'selectedDate' => $ess_selected_date,
	'selectedSlot' => '',
	'slotLabel'    => '',
	'submitting'   => false,
	'error'        => '',
	'success'      => false,
	'reference'    => '',
];
?>
<div
	<?php echo wp_kses_data( get_block_wrapper_attributes( [ 'class' => 'ess-booking' ] ) ); ?>
	data-wp-interactive="ess/booking"
	<?php echo wp_interactivity_data_wp_context( $ess_context ); ?>
>
	<div class="ess-booking__panel" data-wp-bind--hidden="context.success">
		<h2 class="ess-booking__heading"><?php echo esc_html( $ess_heading ); ?></h2>
		<?php if ( '' !== $ess_intro ) : ?>
			<p class="ess-booking__intro"><?php echo esc_html( $ess_intro ); ?></p>
		<?php endif; ?>

		<p class="ess-booking__empty" data-wp-bind--hidden="state.hasSlots">
			<?php esc_html_e( 'No consultation slots are open right now. Send a message instead and the studio will come back with times.', 'ess-core' ); ?>
		</p>

		<div data-wp-bind--hidden="!state.hasSlots">
			<p class="ess-booking__tz">
				<?php
				printf(
					/* translators: %s: IANA timezone name, e.g. America/New_York. */
					esc_html__( 'All times shown in %s.', 'ess-core' ),
					'<strong>' . esc_html( str_replace( '_', ' ', ESS\Core\studio_timezone()->getName() ) ) . '</strong>'
				);
				?>
			</p>

			<fieldset class="ess-booking__step">
				<legend class="ess-booking__legend"><?php esc_html_e( '1. Choose a day', 'ess-core' ); ?></legend>
				<div class="ess-booking__days" role="group">
					<?php foreach ( $ess_days as $ess_day ) : ?>
						<button
							type="button"
							class="ess-day"
							<?php echo wp_interactivity_data_wp_context( [ 'date' => $ess_day['date'] ] ); ?>
							data-wp-on--click="actions.selectDay"
							data-wp-class--is-selected="state.isSelectedDay"
							<?php disabled( 0, $ess_day['open'] ); ?>
						>
							<span class="ess-day__weekday"><?php echo esc_html( $ess_day['weekday'] ); ?></span>
							<span class="ess-day__num"><?php echo esc_html( $ess_day['day'] ); ?></span>
							<span class="ess-day__month"><?php echo esc_html( $ess_day['month'] ); ?></span>
							<span class="ess-day__open">
								<?php
								echo esc_html(
									$ess_day['open'] > 0
										? sprintf(
											/* translators: %d: number of free slots. */
											_n( '%d slot', '%d slots', $ess_day['open'], 'ess-core' ),
											$ess_day['open']
										)
										: __( 'Full', 'ess-core' )
								);
								?>
							</span>
						</button>
					<?php endforeach; ?>
				</div>
			</fieldset>

			<fieldset class="ess-booking__step">
				<legend class="ess-booking__legend"><?php esc_html_e( '2. Choose a time', 'ess-core' ); ?></legend>
				<?php foreach ( $ess_days as $ess_day ) : ?>
					<div
						class="ess-booking__slots"
						<?php echo wp_interactivity_data_wp_context( [ 'date' => $ess_day['date'] ] ); ?>
						data-wp-bind--hidden="!state.isSelectedDay"
					>
						<?php foreach ( $ess_day['slots'] as $ess_slot ) : ?>
							<button
								type="button"
								class="ess-slot"
								<?php
								echo wp_interactivity_data_wp_context(
									[
										'slot'      => $ess_slot['start'],
										'slotText'  => $ess_slot['label'],
									]
								);
								?>
								data-wp-on--click="actions.selectSlot"
								data-wp-class--is-selected="state.isSelectedSlot"
								<?php disabled( false, $ess_slot['available'] ); ?>
							>
								<?php echo esc_html( $ess_slot['time'] ); ?>
							</button>
						<?php endforeach; ?>
					</div>
				<?php endforeach; ?>
			</fieldset>

			<form class="ess-booking__form" data-wp-on--submit="actions.submit" data-wp-bind--hidden="!context.selectedSlot" novalidate>
				<fieldset class="ess-booking__step">
					<legend class="ess-booking__legend"><?php esc_html_e( '3. Your details', 'ess-core' ); ?></legend>

					<p class="ess-booking__chosen">
						<?php esc_html_e( 'Selected:', 'ess-core' ); ?>
						<strong data-wp-text="context.slotLabel"></strong>
					</p>

					<div class="ess-field">
						<label for="ess-b-name"><?php esc_html_e( 'Name', 'ess-core' ); ?> <span aria-hidden="true">*</span></label>
						<input id="ess-b-name" name="name" type="text" required autocomplete="name" minlength="2">
					</div>

					<div class="ess-field-row">
						<div class="ess-field">
							<label for="ess-b-email"><?php esc_html_e( 'Email', 'ess-core' ); ?> <span aria-hidden="true">*</span></label>
							<input id="ess-b-email" name="email" type="email" required autocomplete="email">
						</div>
						<div class="ess-field">
							<label for="ess-b-phone"><?php esc_html_e( 'Phone', 'ess-core' ); ?></label>
							<input id="ess-b-phone" name="phone" type="tel" autocomplete="tel">
						</div>
					</div>

					<div class="ess-field">
						<label for="ess-b-company"><?php esc_html_e( 'Company', 'ess-core' ); ?></label>
						<input id="ess-b-company" name="company" type="text" autocomplete="organization">
					</div>

					<div class="ess-field">
						<label for="ess-b-type"><?php esc_html_e( 'What is the project?', 'ess-core' ); ?> <span aria-hidden="true">*</span></label>
						<select id="ess-b-type" name="project_type" required>
							<option value=""><?php esc_html_e( 'Select one…', 'ess-core' ); ?></option>
							<?php foreach ( $ess_project_types as $ess_value => $ess_label ) : ?>
								<option value="<?php echo esc_attr( $ess_value ); ?>"><?php echo esc_html( $ess_label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="ess-field">
						<label for="ess-b-message"><?php esc_html_e( 'Anything we should read first?', 'ess-core' ); ?></label>
						<textarea id="ess-b-message" name="message" rows="3"></textarea>
					</div>

					<?php // Honeypot: off-screen and hidden from assistive tech, so only a bot fills it. ?>
					<div class="ess-hp" aria-hidden="true">
						<label for="ess-b-website"><?php esc_html_e( 'Website', 'ess-core' ); ?></label>
						<input id="ess-b-website" name="website" type="text" tabindex="-1" autocomplete="off">
					</div>

					<p class="ess-booking__error" role="alert" data-wp-bind--hidden="!context.error" data-wp-text="context.error"></p>

					<button type="submit" class="ess-booking__submit" data-wp-bind--disabled="context.submitting" data-wp-text="state.submitLabel">
						<?php esc_html_e( 'Confirm consultation', 'ess-core' ); ?>
					</button>

					<p class="ess-booking__fineprint">
						<?php esc_html_e( 'The slot is held as soon as you confirm. You will get an email with the details.', 'ess-core' ); ?>
					</p>
				</fieldset>
			</form>
		</div>
	</div>

	<div class="ess-booking__done" role="status" data-wp-bind--hidden="!context.success">
		<h2 class="ess-booking__heading"><?php esc_html_e( 'Your consultation is booked', 'ess-core' ); ?></h2>
		<p class="ess-booking__done-slot"><strong data-wp-text="context.slotLabel"></strong></p>
		<p>
			<?php esc_html_e( 'Reference', 'ess-core' ); ?>
			<code data-wp-text="context.reference"></code>
		</p>
		<p><?php esc_html_e( 'A confirmation email is on its way. Reply to it with drawings or a site address and we will read them before we meet.', 'ess-core' ); ?></p>
	</div>
</div>
