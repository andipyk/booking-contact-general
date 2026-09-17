<?php
/**
 * Server render for the contact block.
 *
 * @package ESS\Core
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner content.
 * @var WP_Block $block      Block instance.
 */

declare( strict_types = 1 );

use ESS\Core\Blocks;

defined( 'ABSPATH' ) || exit;

$ess_heading = (string) ( $attributes['heading'] ?? __( 'Tell us about the project', 'ess-core' ) );
$ess_intro   = (string) ( $attributes['intro'] ?? '' );
$ess_config  = Blocks::form_config();

$ess_subjects = [
	'new_project'        => __( 'A new project', 'ess-core' ),
	'existing_project'   => __( 'An existing project', 'ess-core' ),
	'consultant_enquiry' => __( 'Consultant or trade enquiry', 'ess-core' ),
	'careers'            => __( 'Careers', 'ess-core' ),
	'other'              => __( 'Something else', 'ess-core' ),
];

wp_interactivity_state(
	'ess/contact',
	[
		'restRoot'    => $ess_config['root'],
		'nonce'       => $ess_config['nonce'],
		'stamp'       => $ess_config['stamp'],
		'sourcePage'  => esc_url_raw( home_url( add_query_arg( [] ) ) ),
		'submitLabel' => static function () {
			$state   = wp_interactivity_state();
			$context = wp_interactivity_get_context();
			return ! empty( $context['submitting'] )
				? $state['labels']['submitting']
				: $state['labels']['submit'];
		},
		'labels'      => [
			'submit'       => __( 'Send message', 'ess-core' ),
			'submitting'   => __( 'Sending…', 'ess-core' ),
			'genericError' => __( 'We could not send your message. Please try again.', 'ess-core' ),
			'networkError' => __( 'Could not reach the studio. Check your connection and try again.', 'ess-core' ),
		],
	]
);

$ess_context = [
	'submitting' => false,
	'error'      => '',
	'success'    => false,
	'reference'  => '',
];
?>
<div
	<?php echo wp_kses_data( get_block_wrapper_attributes( [ 'class' => 'ess-contact' ] ) ); ?>
	data-wp-interactive="ess/contact"
	<?php echo wp_interactivity_data_wp_context( $ess_context ); ?>
>
	<div data-wp-bind--hidden="context.success">
		<h2 class="ess-contact__heading"><?php echo esc_html( $ess_heading ); ?></h2>
		<?php if ( '' !== $ess_intro ) : ?>
			<p class="ess-contact__intro"><?php echo esc_html( $ess_intro ); ?></p>
		<?php endif; ?>

		<form class="ess-contact__form" data-wp-on--submit="actions.submit" novalidate>
			<div class="ess-field-row">
				<div class="ess-field">
					<label for="ess-c-name"><?php esc_html_e( 'Name', 'ess-core' ); ?> <span aria-hidden="true">*</span></label>
					<input id="ess-c-name" name="name" type="text" required autocomplete="name" minlength="2">
				</div>
				<div class="ess-field">
					<label for="ess-c-email"><?php esc_html_e( 'Email', 'ess-core' ); ?> <span aria-hidden="true">*</span></label>
					<input id="ess-c-email" name="email" type="email" required autocomplete="email">
				</div>
			</div>

			<div class="ess-field-row">
				<div class="ess-field">
					<label for="ess-c-phone"><?php esc_html_e( 'Phone', 'ess-core' ); ?></label>
					<input id="ess-c-phone" name="phone" type="tel" autocomplete="tel">
				</div>
				<div class="ess-field">
					<label for="ess-c-company"><?php esc_html_e( 'Company', 'ess-core' ); ?></label>
					<input id="ess-c-company" name="company" type="text" autocomplete="organization">
				</div>
			</div>

			<div class="ess-field">
				<label for="ess-c-subject"><?php esc_html_e( 'What is this about?', 'ess-core' ); ?> <span aria-hidden="true">*</span></label>
				<select id="ess-c-subject" name="subject" required>
					<option value=""><?php esc_html_e( 'Select one…', 'ess-core' ); ?></option>
					<?php foreach ( $ess_subjects as $ess_value => $ess_label ) : ?>
						<option value="<?php echo esc_attr( $ess_value ); ?>"><?php echo esc_html( $ess_label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="ess-field">
				<label for="ess-c-message"><?php esc_html_e( 'Message', 'ess-core' ); ?> <span aria-hidden="true">*</span></label>
				<textarea id="ess-c-message" name="message" rows="5" required minlength="10"></textarea>
			</div>

			<?php // Honeypot: off-screen and hidden from assistive tech, so only a bot fills it. ?>
			<div class="ess-hp" aria-hidden="true">
				<label for="ess-c-website"><?php esc_html_e( 'Website', 'ess-core' ); ?></label>
				<input id="ess-c-website" name="website" type="text" tabindex="-1" autocomplete="off">
			</div>

			<p class="ess-contact__error" role="alert" data-wp-bind--hidden="!context.error" data-wp-text="context.error"></p>

			<button type="submit" class="ess-contact__submit" data-wp-bind--disabled="context.submitting" data-wp-text="state.submitLabel">
				<?php esc_html_e( 'Send message', 'ess-core' ); ?>
			</button>
		</form>
	</div>

	<div class="ess-contact__done" role="status" data-wp-bind--hidden="!context.success">
		<h2 class="ess-contact__heading"><?php esc_html_e( 'Message sent', 'ess-core' ); ?></h2>
		<p><?php esc_html_e( 'Thanks — your message is with the studio. We reply within one business day.', 'ess-core' ); ?></p>
		<p>
			<?php esc_html_e( 'Reference', 'ess-core' ); ?>
			<code data-wp-text="context.reference"></code>
		</p>
	</div>
</div>
