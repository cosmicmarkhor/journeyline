/* Journeyline admin: add, remove and reorder rows in trip lists. No build step, no dependencies. */
( function () {
	'use strict';
	function renumber( list ) {
		if ( ! list.classList.contains( 'jl-days' ) ) {
			return;
		}
		list.querySelectorAll( '.jl-rows > .jl-row' ).forEach( function ( row, i ) {
			row.querySelectorAll( '[name]' ).forEach( function ( el ) {
				el.name = el.name.replace( /jrnl_itinerary\[[^\]]*\]/, 'jrnl_itinerary[' + i + ']' );
			} );
		} );
	}
	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '.jl-admin button' );
		if ( ! btn ) {
			return;
		}
		var list = btn.closest( '.jl-list' );
		var rows = list.querySelector( '.jl-rows' );
		var row = btn.closest( '.jl-row' );
		if ( btn.classList.contains( 'jl-add' ) ) {
			var tpl = list.querySelector( 'template' );
			var node = tpl.content.firstElementChild.cloneNode( true );
			rows.appendChild( node );
			renumber( list );
			var first = node.querySelector( 'input, textarea' );
			if ( first ) {
				first.focus();
			}
		} else if ( btn.classList.contains( 'jl-remove' ) && row ) {
			var next = row.nextElementSibling || row.previousElementSibling;
			row.remove();
			renumber( list );
			( next ? next.querySelector( 'input, textarea' ) : list.querySelector( '.jl-add' ) ).focus();
		} else if ( btn.classList.contains( 'jl-up' ) && row && row.previousElementSibling ) {
			rows.insertBefore( row, row.previousElementSibling );
			renumber( list );
			btn.focus();
		} else if ( btn.classList.contains( 'jl-down' ) && row && row.nextElementSibling ) {
			rows.insertBefore( row.nextElementSibling, row );
			renumber( list );
			btn.focus();
		}
	} );
} )();
