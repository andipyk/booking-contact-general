/**
 * Front-end behaviour for the consultation booking block.
 *
 * This file is loaded by the browser as-is. WordPress ships an import map that
 * resolves `@wordpress/interactivity`, so there is no bundler in this project
 * and no build output to keep in sync with the source.
 *
 * Every getter here mirrors a PHP closure in render.php. The PHP half decides
 * what the first paint looks like; this half takes over once directives
 * hydrate. They have to agree, or the block visibly changes on load.
 */

import { store, getContext } from '@wordpress/interactivity';

const { state } = store( 'ess/booking', {
	state: {
		get hasSlots() {
			return state.dayCount > 0;
		},

		get isSelectedDay() {
			const context = getContext();
			return context.date === context.selectedDate;
		},

		get isSelectedSlot() {
			const context = getContext();
			return !! context.slot && context.slot === context.selectedSlot;
		},

		get submitLabel() {
			const context = getContext();
			return context.submitting
				? state.labels.submitting
				: state.labels.submit;
		},
	},

	actions: {
		selectDay() {
			const context = getContext();
			context.selectedDate = context.date;
			// Changing day invalidates the chosen time, or the form would
			// submit a slot the visitor can no longer see.
			context.selectedSlot = '';
			context.slotLabel = '';
			context.error = '';
		},

		selectSlot() {
			const context = getContext();
			context.selectedSlot = context.slot;
			context.slotLabel = context.slotText;
			context.error = '';
		},

		*submit( event ) {
			event.preventDefault();

			const context = getContext();
			if ( context.submitting ) {
				return;
			}

			const form = event.target;

			// The form carries `novalidate` so this runs on our terms, after
			// the slot check, rather than the browser blocking submit first.
			if ( ! context.selectedSlot ) {
				context.error = state.labels.missingSlot;
				return;
			}
			if ( ! form.checkValidity() ) {
				form.reportValidity();
				return;
			}

			const fields = new FormData( form );
			context.submitting = true;
			context.error = '';

			try {
				const response = yield fetch( `${ state.restRoot }/booking`, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': state.nonce,
					},
					body: JSON.stringify( {
						slot_start: context.selectedSlot,
						name: fields.get( 'name' ) || '',
						email: fields.get( 'email' ) || '',
						phone: fields.get( 'phone' ) || '',
						company: fields.get( 'company' ) || '',
						project_type: fields.get( 'project_type' ) || '',
						message: fields.get( 'message' ) || '',
						website: fields.get( 'website' ) || '',
						rendered_at: state.stamp,
					} ),
				} );

				const body = yield response.json();

				if ( ! response.ok ) {
					// The REST layer returns a visitor-safe sentence for the
					// cases a visitor can act on, such as a slot taken between
					// choosing it and confirming.
					context.error = body?.message || state.labels.genericError;
					return;
				}

				context.reference = body.reference;
				context.slotLabel = body.slot_local;
				context.success = true;
			} catch ( error ) {
				context.error = state.labels.networkError;
			} finally {
				context.submitting = false;
			}
		},
	},
} );
