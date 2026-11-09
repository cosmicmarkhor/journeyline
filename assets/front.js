/* Journeyline on the page: enquiry form sending and inline errors, trip and departure filters.
   Plain JavaScript, no dependencies. Everything still reads correctly without it. */
( function () {
	'use strict';
	var cfg = window.JOURNEYLINE || {};
	var EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

	function setError( form, name, msg ) {
		var input = form.querySelector( '[name="' + name + '"]' );
		if ( ! input ) {
			return;
		}
		var box = document.getElementById( input.id + '-e' );
		if ( box ) {
			box.textContent = msg || '';
		}
		if ( msg ) {
			input.setAttribute( 'aria-invalid', 'true' );
			input.setAttribute( 'aria-describedby', input.id + '-e' );
		} else {
			input.removeAttribute( 'aria-invalid' );
		}
	}

	function initForm( form ) {
		var wrap = form.parentNode;
		var status = form.querySelector( '.jl-status' );
		var button = form.querySelector( '.jl-submit' );
		form.addEventListener( 'input', function ( e ) {
			if ( e.target.name ) {
				setError( form, e.target.name, '' );
			}
		} );
		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			var data = {};
			new FormData( form ).forEach( function ( v, k ) {
				data[ k ] = v;
			} );
			data.page = window.location.href;
			var errs = {};
			if ( ! ( data.name || '' ).trim() ) {
				errs.name = cfg.errName;
			}
			if ( ! EMAIL.test( ( data.email || '' ).trim() ) ) {
				errs.email = cfg.errEmail;
			}
			if ( ! data.consent ) {
				errs.consent = cfg.errConsent;
			}
			[ 'name', 'email', 'consent' ].forEach( function ( k ) {
				setError( form, k, errs[ k ] );
			} );
			var first = Object.keys( errs )[ 0 ];
			if ( first ) {
				form.querySelector( '[name="' + first + '"]' ).focus();
				return;
			}
			button.disabled = true;
			status.textContent = cfg.sending || 'Sending…';
			fetch( cfg.endpoint || form.action, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify( data ),
			} )
				.then( function ( r ) {
					return r.json().then( function ( j ) {
						return { ok: r.ok, body: j };
					} );
				} )
				.then( function ( res ) {
					button.disabled = false;
					if ( res.ok ) {
						form.hidden = true;
						var sent = wrap.querySelector( '.jl-sent' );
						sent.hidden = false;
						sent.focus();
						wrap.dispatchEvent( new CustomEvent( 'journeyline:sent', { bubbles: true, detail: data } ) );
						return;
					}
					var fields = res.body && res.body.data && res.body.data.fields;
					if ( fields ) {
						Object.keys( fields ).forEach( function ( k ) {
							setError( form, k, fields[ k ] );
						} );
						status.textContent = '';
					} else {
						status.textContent = ( res.body && res.body.message ) || cfg.failed;
					}
				} )
				.catch( function () {
					button.disabled = false;
					status.textContent = cfg.failed || 'Sorry, that did not send.';
				} );
		} );
	}

	function initTrips( scope ) {
		var buttons = scope.querySelectorAll( '.jl-filter' );
		var cards = scope.querySelectorAll( '.jl-card' );
		var count = scope.querySelector( '.jl-count' );
		buttons.forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				var key = b.getAttribute( 'data-jl-key' );
				var shown = 0;
				buttons.forEach( function ( x ) {
					x.setAttribute( 'aria-pressed', x === b );
				} );
				cards.forEach( function ( c ) {
					var ok = ! key || ( ' ' + c.getAttribute( 'data-jl-keys' ) + ' ' ).indexOf( ' ' + key + ' ' ) > -1;
					c.hidden = ! ok;
					shown += ok ? 1 : 0;
				} );
				if ( count ) {
					count.textContent = ( cfg.showing || '%1$s of %2$s' ).replace( '%1$s', shown ).replace( '%2$s', cards.length );
				}
			} );
		} );
	}

	function initDeps( scope ) {
		var selects = scope.querySelectorAll( '[data-jl-dep-filter]' );
		if ( ! selects.length ) {
			return;
		}
		var rows = scope.querySelectorAll( '.jl-dep' );
		var months = scope.querySelectorAll( '.jl-month' );
		var count = scope.querySelector( '.jl-count' );
		var empty = scope.querySelector( '.jl-empty' );
		function apply() {
			var f = {};
			selects.forEach( function ( s ) {
				f[ s.getAttribute( 'data-jl-dep-filter' ) ] = s.type === 'checkbox' ? s.checked : s.value;
			} );
			var shown = 0;
			rows.forEach( function ( r ) {
				var ok = ( ! f.month || r.dataset.month === f.month ) && ( ! f.country || r.dataset.country === f.country ) && ( ! f.style || r.dataset.style === f.style ) && ( ! f.seats || r.dataset.open === '1' );
				r.hidden = ! ok;
				shown += ok ? 1 : 0;
			} );
			months.forEach( function ( m ) {
				m.hidden = ! m.querySelector( '.jl-dep:not([hidden])' );
			} );
			if ( count ) {
				count.textContent = ( cfg.showing || '%1$s of %2$s' ).replace( '%1$s', shown ).replace( '%2$s', rows.length );
			}
			if ( empty ) {
				empty.hidden = shown !== 0;
			}
		}
		selects.forEach( function ( s ) {
			s.addEventListener( 'change', apply );
		} );
	}

	function boot() {
		document.querySelectorAll( 'form[data-jl-enquiry]' ).forEach( initForm );
		document.querySelectorAll( '[data-jl-filter-scope]' ).forEach( initTrips );
		document.querySelectorAll( '[data-jl-deps]' ).forEach( initDeps );
	}
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
