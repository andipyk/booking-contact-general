/**
 * Editor registration for ess/booking-form.
 *
 * Written against the wp.* globals rather than as a JSX module, so the plugin
 * carries no build step. The preview comes from the same render.php the front
 * end uses, which means an editor never sees a mock-up that has drifted from
 * the real block.
 */
( function ( blocks, element, blockEditor, serverSideRender, i18n ) {
	'use strict';

	var el = element.createElement;
	var __ = i18n.__;
	var useBlockProps = blockEditor.useBlockProps;
	var InspectorControls = blockEditor.InspectorControls;
	var components = wp.components;

	blocks.registerBlockType( 'ess/booking-form', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;

			return el(
				'div',
				useBlockProps(),
				el(
					InspectorControls,
					null,
					el(
						components.PanelBody,
						{ title: __( 'Booking settings', 'ess-core' ) },
						el( components.TextControl, {
							label: __( 'Heading', 'ess-core' ),
							value: attributes.heading,
							onChange: function ( value ) {
								setAttributes( { heading: value } );
							},
							__nextHasNoMarginBottom: true,
						} ),
						el( components.TextareaControl, {
							label: __( 'Intro', 'ess-core' ),
							value: attributes.intro,
							onChange: function ( value ) {
								setAttributes( { intro: value } );
							},
							__nextHasNoMarginBottom: true,
						} ),
						el( components.RangeControl, {
							label: __( 'Days to show', 'ess-core' ),
							value: attributes.days,
							min: 1,
							max: 31,
							onChange: function ( value ) {
								setAttributes( { days: value } );
							},
							__nextHasNoMarginBottom: true,
						} )
					)
				),
				el( serverSideRender, {
					block: 'ess/booking-form',
					attributes: attributes,
				} )
			);
		},

		// Dynamic block: the markup always comes from render.php.
		save: function () {
			return null;
		},
	} );
} )( wp.blocks, wp.element, wp.blockEditor, wp.serverSideRender, wp.i18n );
