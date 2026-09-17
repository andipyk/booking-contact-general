/**
 * Editor registration for ess/contact-form.
 *
 * Preview comes from render.php, so the editor cannot drift from the front end.
 */
( function ( blocks, element, blockEditor, serverSideRender, i18n ) {
	'use strict';

	var el = element.createElement;
	var __ = i18n.__;
	var useBlockProps = blockEditor.useBlockProps;
	var InspectorControls = blockEditor.InspectorControls;
	var components = wp.components;

	blocks.registerBlockType( 'ess/contact-form', {
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
						{ title: __( 'Contact settings', 'ess-core' ) },
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
						} )
					)
				),
				el( serverSideRender, {
					block: 'ess/contact-form',
					attributes: attributes,
				} )
			);
		},

		save: function () {
			return null;
		},
	} );
} )( wp.blocks, wp.element, wp.blockEditor, wp.serverSideRender, wp.i18n );
