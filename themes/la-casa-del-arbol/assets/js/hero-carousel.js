/**
 * Home hero carousel (Handoff §2.1).
 *
 * Progressive enhancement of the "Home — Carrusel de portada" pattern:
 * section.lcda-hero > .lcda-hero__track > one image block per slide. The
 * track is a CSS scroll-snap row (assets/css/home.css), so swipe works
 * natively and, without this script, every image is still reachable by
 * scrolling. This script adds:
 * - previous/next buttons (loop) and one dot button per slide;
 * - carousel/slide semantics and a polite status message ("2 de 5");
 * - Left/Right arrow keys while a control has focus;
 * - eager loading of the neighbouring images.
 * No autoplay (V1 decision). Instant jumps with prefers-reduced-motion.
 * Slides are counted from the markup: editors add or remove image blocks.
 * Interface strings come from PHP (window.lcdaHeroCarousel, inc/assets.php).
 */
( function () {
	'use strict';

	var strings = window.lcdaHeroCarousel || {};
	var reduceMotion = window.matchMedia( '(prefers-reduced-motion: reduce)' );

	function t( key, fallback ) {
		return strings[ key ] || fallback;
	}

	function format( text, n, total ) {
		return text.replace( '%1$d', n ).replace( '%2$d', total );
	}

	function makeButton( className, label ) {
		var button = document.createElement( 'button' );
		button.type = 'button';
		button.className = className;
		button.setAttribute( 'aria-label', label );
		return button;
	}

	function init( hero, heroIndex ) {
		var track = hero.querySelector( '.lcda-hero__track' );

		if ( ! track || hero.classList.contains( 'is-ready' ) ) {
			return;
		}

		var slides = Array.prototype.slice.call( track.children );
		var total = slides.length;

		if ( total < 2 ) {
			return;
		}

		var current = 0;
		var target = null; // Slide a programmatic scroll is heading to.
		var targetTimer = 0;
		var announceTimer = 0;
		var frame = 0;
		var slideLabel = t( 'slideLabel', '%1$d de %2$d' );

		if ( ! track.id ) {
			track.id = 'lcda-hero-track-' + ( heroIndex + 1 );
		}

		hero.setAttribute( 'role', 'region' );
		hero.setAttribute( 'aria-roledescription', t( 'carousel', 'carrusel' ) );
		if ( ! hero.hasAttribute( 'aria-label' ) ) {
			hero.setAttribute( 'aria-label', t( 'label', 'Imágenes de La Casa' ) );
		}

		slides.forEach( function ( slide, i ) {
			slide.setAttribute( 'role', 'group' );
			slide.setAttribute( 'aria-roledescription', t( 'slide', 'imagen' ) );
			slide.setAttribute( 'aria-label', format( slideLabel, i + 1, total ) );
		} );

		var prev = makeButton( 'lcda-hero__arrow lcda-hero__arrow--prev', t( 'prev', 'Imagen anterior' ) );
		var next = makeButton( 'lcda-hero__arrow lcda-hero__arrow--next', t( 'next', 'Imagen siguiente' ) );
		prev.textContent = '‹';
		next.textContent = '›';
		prev.setAttribute( 'aria-controls', track.id );
		next.setAttribute( 'aria-controls', track.id );

		var dotsGroup = document.createElement( 'div' );
		dotsGroup.className = 'lcda-hero__dots';
		dotsGroup.setAttribute( 'role', 'group' );
		dotsGroup.setAttribute( 'aria-label', t( 'dots', 'Elegir imagen' ) );

		var dots = slides.map( function ( slide, i ) {
			var dot = makeButton( 'lcda-hero__dot', format( t( 'goTo', 'Imagen %1$d de %2$d' ), i + 1, total ) );
			var bar = document.createElement( 'span' );
			bar.className = 'lcda-hero__dot-bar';
			bar.setAttribute( 'aria-hidden', 'true' );
			dot.setAttribute( 'aria-controls', track.id );
			dot.appendChild( bar );
			dotsGroup.appendChild( dot );
			return dot;
		} );

		var status = document.createElement( 'p' );
		status.className = 'screen-reader-text';
		status.setAttribute( 'aria-live', 'polite' );
		status.setAttribute( 'aria-atomic', 'true' );

		hero.appendChild( prev );
		hero.appendChild( next );
		hero.appendChild( dotsGroup );
		hero.appendChild( status );

		function preload( i ) {
			[ i - 1, i, i + 1 ].forEach( function ( k ) {
				var img = slides[ ( k + total ) % total ].querySelector( 'img[loading="lazy"]' );
				if ( img ) {
					img.loading = 'eager';
				}
			} );
		}

		function setCurrent( i, announce ) {
			current = i;
			dots.forEach( function ( dot, k ) {
				if ( k === i ) {
					dot.setAttribute( 'aria-current', 'true' );
				} else {
					dot.removeAttribute( 'aria-current' );
				}
			} );
			preload( i );

			if ( announce ) {
				window.clearTimeout( announceTimer );
				announceTimer = window.setTimeout( function () {
					status.textContent = format( slideLabel, current + 1, total );
				}, 350 );
			}
		}

		function scrollToSlide( i, smooth ) {
			track.scrollTo( {
				left: i * track.clientWidth,
				behavior: smooth && ! reduceMotion.matches ? 'smooth' : 'auto',
			} );
		}

		function go( i ) {
			i = ( i + total ) % total;
			target = i;
			window.clearTimeout( targetTimer );
			targetTimer = window.setTimeout( function () {
				target = null;
			}, 1000 );
			scrollToSlide( i, true );
			setCurrent( i, true );
			return i;
		}

		prev.addEventListener( 'click', function () {
			go( current - 1 );
		} );

		next.addEventListener( 'click', function () {
			go( current + 1 );
		} );

		dots.forEach( function ( dot, i ) {
			dot.addEventListener( 'click', function () {
				go( i );
			} );
		} );

		hero.addEventListener( 'keydown', function ( event ) {
			var step = { ArrowLeft: -1, ArrowRight: 1 }[ event.key ];
			var onDot = dots.indexOf( event.target ) > -1;

			if ( ! step || ( ! onDot && event.target !== prev && event.target !== next ) ) {
				return;
			}

			event.preventDefault();
			var i = go( current + step );
			if ( onDot ) {
				dots[ i ].focus();
			}
		} );

		// Swipe / trackpad / scrollbar: follow the scroll position. While a
		// button-driven scroll passes intermediate slides, keep its target.
		track.addEventListener( 'scroll', function () {
			if ( frame ) {
				return;
			}
			frame = window.requestAnimationFrame( function () {
				frame = 0;
				var i = Math.round( track.scrollLeft / Math.max( track.clientWidth, 1 ) );
				i = Math.min( Math.max( i, 0 ), total - 1 );

				if ( null !== target ) {
					if ( i === target ) {
						target = null;
					}
					return;
				}

				if ( i !== current ) {
					setCurrent( i, true );
				}
			} );
		}, { passive: true } );

		// Keep the current slide in view when the viewport width changes.
		var lastWidth = track.clientWidth;
		window.addEventListener( 'resize', function () {
			if ( track.clientWidth !== lastWidth ) {
				lastWidth = track.clientWidth;
				scrollToSlide( current, false );
			}
		} );

		hero.classList.add( 'is-ready' );
		setCurrent( 0, false );
	}

	Array.prototype.forEach.call( document.querySelectorAll( '.lcda-hero' ), init );
}() );
