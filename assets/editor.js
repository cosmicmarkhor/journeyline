/* Journeyline blocks in the editor: a live preview drawn by the server, with simple settings in the sidebar.
   Plain JavaScript using WordPress's own editor packages; no build step. */
( function ( wp ) {
	'use strict';
	if ( ! wp || ! wp.blocks ) {
		return;
	}
	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var SSR = wp.serverSideRender;
	var be = wp.blockEditor;
	var c = wp.components;
	var labels = {
		destination: __( 'Destination (web name, for example pakistan)', 'journeyline' ),
		style: __( 'Trip style (web name)', 'journeyline' ),
		limit: __( 'How many', 'journeyline' ),
		showFilters: __( 'Show filters', 'journeyline' ),
		scope: __( 'Which departures', 'journeyline' ),
		groupByMonth: __( 'Group by month', 'journeyline' ),
		openFirst: __( 'Open the first day', 'journeyline' ),
		which: __( 'Which list', 'journeyline' ),
		showTopic: __( 'Ask what it is about', 'journeyline' ),
		showTravellers: __( 'Ask how many are travelling', 'journeyline' ),
		button: __( 'Button text', 'journeyline' ),
	};
	var choices = {
		scope: [ { label: __( 'This trip on trip pages, otherwise all', 'journeyline' ), value: 'auto' }, { label: __( 'All trips', 'journeyline' ), value: 'all' } ],
		which: [ { label: __( 'Highlights', 'journeyline' ), value: 'highlights' }, { label: __( 'Included', 'journeyline' ), value: 'included' }, { label: __( 'Not included', 'journeyline' ), value: 'excluded' } ],
	};

	Object.keys( window.JOURNEYLINE_BLOCKS || {} ).forEach( function ( slug ) {
		var def = window.JOURNEYLINE_BLOCKS[ slug ];
		wp.blocks.registerBlockType( 'journeyline/' + slug, {
			edit: function ( props ) {
				var a = props.attributes;
				var controls = Object.keys( def.attrs ).map( function ( key ) {
					var type = def.attrs[ key ][ 0 ];
					var set = function ( v ) {
						var o = {};
						o[ key ] = v;
						props.setAttributes( o );
					};
					if ( choices[ key ] ) {
						return el( c.SelectControl, { key: key, label: labels[ key ] || key, value: a[ key ], options: choices[ key ], onChange: set, __nextHasNoMarginBottom: true } );
					}
					if ( 'boolean' === type ) {
						return el( c.ToggleControl, { key: key, label: labels[ key ] || key, checked: !! a[ key ], onChange: set, __nextHasNoMarginBottom: true } );
					}
					if ( 'number' === type ) {
						return el( c.RangeControl, { key: key, label: labels[ key ] || key, value: a[ key ], min: 0, max: 48, onChange: set, __nextHasNoMarginBottom: true } );
					}
					return el( c.TextControl, { key: key, label: labels[ key ] || key, value: a[ key ], onChange: set, __nextHasNoMarginBottom: true } );
				} );
				return el(
					'div',
					be.useBlockProps(),
					controls.length ? el( be.InspectorControls, null, el( c.PanelBody, { title: def.title }, controls ) ) : null,
					el( SSR, {
						block: 'journeyline/' + slug,
						attributes: a,
						EmptyResponsePlaceholder: function () {
							return el( c.Placeholder, { label: def.title, instructions: __( 'Shows here on trip pages, or once there is something to show.', 'journeyline' ) } );
						},
					} )
				);
			},
			save: function () {
				return null;
			},
		} );
	} );
} )( window.wp );
