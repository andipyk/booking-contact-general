/**
 * Front-end behaviour for the contact block.
 *
 * Loaded by the browser as a native ES module; `@wordpress/interactivity`
 * resolves through the import map WordPress ships.
 */

import { store, getContext } from '@wordpress/interactivity';

const { state } = store( 'ess/contact', {
	state: {
		get submitLabel() {
			const context = getContext();
			return context.submitting
				? state.labels.submitting
				: state.labels.submit;
		},
	},

	actions: {
		*submit( event ) {
			event.preventDefault();

			const context = getContext();
			if ( context.submitting ) {
				return;
			}

			const form = event.target;
			if ( ! form.checkValidity() ) {
				form.reportValidity();
				return;
			}

			const fields = new FormData( form );
			context.submitting = true;
			context.error = '';

			try {
				const response = yield fetch( `${ state.restRoot }/inquiry`, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': state.nonce,
					},
					body: JSON.stringify( {
						name: fields.get( 'name' ) || '',
						email: fields.get( 'email' ) || '',
						phone: fields.get( 'phone' ) || '',
						company: fields.get( 'company' ) || '',
						subject: fields.get( 'subject' ) || '',
						message: fields.get( 'message' ) || '',
						website: fields.get( 'website' ) || '',
						source_page: state.sourcePage,
						rendered_at: state.stamp,
					} ),
				} );

				const body = yield response.json();

				if ( ! response.ok ) {
					context.error = body?.message || state.labels.genericError;
					return;
				}

				context.reference = body.reference;
				context.success = true;
			} catch ( error ) {
				context.error = state.labels.networkError;
			} finally {
				context.submitting = false;
			}
		},
	},
} );
