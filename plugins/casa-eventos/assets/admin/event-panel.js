/**
 * Casa Eventos: block editor document panels for the Event post type.
 *
 * Plain JavaScript over the editor's globals (no JSX, no build step).
 * Fields are registered REST meta, saved in the same request as the post.
 * The server validates publishing (core/enforcement.php); the hints shown
 * here are only a convenience and never the authority.
 *
 * Dates are wall times in the event timezone. They travel as plain strings
 * ('YYYY-MM-DD HH:MM:SS'); the browser timezone is never involved.
 */
( function ( wp, config ) {
	'use strict';

	if ( ! wp || ! config || ! wp.plugins || ! wp.data || ! wp.element || ! wp.components ) {
		return;
	}

	var PluginDocumentSettingPanel =
		( wp.editor && wp.editor.PluginDocumentSettingPanel ) ||
		( wp.editPost && wp.editPost.PluginDocumentSettingPanel );
	if ( ! PluginDocumentSettingPanel ) {
		return;
	}

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useSelect = wp.data.useSelect;
	var useDispatch = wp.data.useDispatch;
	var components = wp.components;
	var __ = wp.i18n.__;
	var sprintf = wp.i18n.sprintf;
	var K = config.keys;

	var common = { __nextHasNoMarginBottom: true };
	var sized = { __nextHasNoMarginBottom: true, __next40pxDefaultSize: true };

	function assign() {
		var out = {};
		for ( var i = 0; i < arguments.length; i++ ) {
			var src = arguments[ i ] || {};
			for ( var k in src ) {
				if ( Object.prototype.hasOwnProperty.call( src, k ) ) {
					out[ k ] = src[ k ];
				}
			}
		}
		return out;
	}

	// ----- Wall-time helpers (no Date parsing in the browser timezone) ------

	var DATETIME_RE = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/;

	/** Stored 'YYYY-MM-DD HH:MM:SS' → input 'YYYY-MM-DDTHH:MM'. */
	function toInput( stored ) {
		var m = DATETIME_RE.exec( stored || '' );
		return m ? m[ 1 ] + '-' + m[ 2 ] + '-' + m[ 3 ] + 'T' + m[ 4 ] + ':' + m[ 5 ] : '';
	}

	/** Input 'YYYY-MM-DDTHH:MM' → stored 'YYYY-MM-DD HH:MM:00' ('' when empty). */
	function fromInput( value ) {
		var m = DATETIME_RE.exec( value || '' );
		return m ? m[ 1 ] + '-' + m[ 2 ] + '-' + m[ 3 ] + ' ' + m[ 4 ] + ':' + m[ 5 ] + ':00' : '';
	}

	/** Wall-time arithmetic in minutes, computed in UTC so no DST applies. */
	function addMinutes( stored, minutes ) {
		var m = DATETIME_RE.exec( stored || '' );
		if ( ! m ) {
			return '';
		}
		var d = new Date( Date.UTC( +m[ 1 ], +m[ 2 ] - 1, +m[ 3 ], +m[ 4 ], +m[ 5 ] ) + minutes * 60000 );
		function p( n ) {
			return ( n < 10 ? '0' : '' ) + n;
		}
		return d.getUTCFullYear() + '-' + p( d.getUTCMonth() + 1 ) + '-' + p( d.getUTCDate() ) +
			' ' + p( d.getUTCHours() ) + ':' + p( d.getUTCMinutes() ) + ':00';
	}

	/** 'YYYY-MM-DD HH:MM:SS' → 'DD/MM/YYYY HH:MM'. */
	function display( stored ) {
		var m = DATETIME_RE.exec( stored || '' );
		return m ? m[ 3 ] + '/' + m[ 2 ] + '/' + m[ 1 ] + ' ' + m[ 4 ] + ':' + m[ 5 ] : '';
	}

	function withEmpty( options, label ) {
		return [ { value: '', label: label } ].concat( options );
	}

	// ----- Data ---------------------------------------------------------------

	function useEvent() {
		return useSelect( function ( select ) {
			var editor = select( 'core/editor' );
			return {
				postType: editor.getCurrentPostType(),
				meta: editor.getEditedPostAttribute( 'meta' ) || {},
				savedMeta: editor.getCurrentPostAttribute( 'meta' ) || {},
				info: editor.getCurrentPostAttribute( 'casa_event' ) || {},
				title: editor.getEditedPostAttribute( 'title' ) || '',
				terms: editor.getEditedPostAttribute( config.taxonomyRestBase ) || [],
			};
		}, [] );
	}

	function useSetMeta() {
		var editPost = useDispatch( 'core/editor' ).editPost;
		return function ( key, value ) {
			var meta = {};
			meta[ key ] = value;
			editPost( { meta: meta } );
		};
	}

	/** Client-side hints of what the server will require to publish. */
	function publishHints( data ) {
		var meta = data.meta;
		var hints = [];
		var start = meta[ K.start ] || '';
		var end = meta[ K.end ] || '';
		if ( ! String( data.title ).trim() ) {
			hints.push( __( 'el título', 'casa-eventos' ) );
		}
		if ( ! start ) {
			hints.push( __( 'la fecha y hora de inicio', 'casa-eventos' ) );
		}
		if ( ! meta[ K.accessMode ] ) {
			hints.push( __( 'la modalidad de acceso', 'casa-eventos' ) );
		}
		if ( meta[ K.accessMode ] === config.values.external && ! String( meta[ K.externalUrl ] || '' ).trim() ) {
			hints.push( __( 'el enlace de venta externa', 'casa-eventos' ) );
		}
		if ( data.terms.length !== 1 ) {
			hints.push( __( 'una sola categoría', 'casa-eventos' ) );
		}
		if ( start && end && end <= start ) {
			hints.push( __( 'un fin posterior al inicio', 'casa-eventos' ) );
		}
		return hints;
	}

	// ----- Panel: Datos del evento -------------------------------------------

	function DataPanel() {
		var data = useEvent();
		var setMeta = useSetMeta();
		if ( data.postType !== config.postType ) {
			return null;
		}

		var meta = data.meta;
		var start = meta[ K.start ] || '';
		var end = meta[ K.end ] || '';
		var close = meta[ K.salesClose ] || '';
		var accessMode = meta[ K.accessMode ] || '';
		var timezone = data.info.timezone || config.defaults.timezone;
		var effectiveEnd = start && end && end > start ? end : addMinutes( start, config.defaults.durationMinutes );
		var capacity = meta[ K.capacity ];
		var hints = publishHints( data );
		var notices = [];

		if ( close && start && close > start ) {
			if ( effectiveEnd && close > effectiveEnd ) {
				notices.push( el( components.Notice, { key: 'close-end', status: 'error', isDismissible: false },
					__( 'El cierre de venta no puede ser posterior al fin del evento.', 'casa-eventos' ) ) );
			} else {
				notices.push( el( components.Notice, { key: 'close-start', status: 'warning', isDismissible: false },
					__( 'El cierre de venta es posterior al inicio del evento.', 'casa-eventos' ) ) );
			}
		}
		if ( accessMode === config.values.tickets ) {
			// Informational only: tickets mode stays valid and publishable.
			notices.push( el( components.Notice, { key: 'tickets', status: 'info', isDismissible: false },
				__( 'La venta propia todavía no está disponible; el evento se publica sin botón de compra.', 'casa-eventos' ) ) );
		}
		if ( hints.length ) {
			notices.push( el( components.Notice, { key: 'hints', status: 'info', isDismissible: false },
				sprintf( __( 'Para publicar falta: %s.', 'casa-eventos' ), hints.join( ', ' ) ) ) );
		}

		return el(
			PluginDocumentSettingPanel,
			{ name: 'casa-eventos-datos', title: __( 'Datos del evento', 'casa-eventos' ), className: 'casa-eventos-panel' },
			el( components.TextControl, assign( sized, {
				label: __( 'Inicio *', 'casa-eventos' ),
				type: 'datetime-local',
				value: toInput( start ),
				help: sprintf( __( 'Hora local (%s).', 'casa-eventos' ), timezone ),
				onChange: function ( v ) {
					setMeta( K.start, fromInput( v ) );
				},
			} ) ),
			el( components.TextControl, assign( sized, {
				label: __( 'Fin', 'casa-eventos' ),
				type: 'datetime-local',
				value: toInput( end ),
				help: start && ! end
					? sprintf( __( 'Opcional. Sin fin, se considera terminado a las %s.', 'casa-eventos' ), display( effectiveEnd ) )
					: __( 'Opcional.', 'casa-eventos' ),
				onChange: function ( v ) {
					setMeta( K.end, fromInput( v ) );
				},
			} ) ),
			el( components.SelectControl, assign( sized, {
				label: __( 'Modalidad de acceso *', 'casa-eventos' ),
				value: accessMode,
				options: withEmpty( config.accessModes, __( '— Elegir —', 'casa-eventos' ) ),
				onChange: function ( v ) {
					setMeta( K.accessMode, v );
				},
			} ) ),
			accessMode === config.values.external
				? el( components.TextControl, assign( sized, {
					label: __( 'Enlace de venta externa *', 'casa-eventos' ),
					type: 'url',
					value: meta[ K.externalUrl ] || '',
					help: __( 'Por ejemplo, la página del evento en Passline.', 'casa-eventos' ),
					onChange: function ( v ) {
						setMeta( K.externalUrl, v );
					},
				} ) )
				: null,
			el( components.SelectControl, assign( sized, {
				label: __( 'Tipo de entrada', 'casa-eventos' ),
				value: meta[ K.entryKind ] || '',
				options: withEmpty( config.entryKinds, __( '— Sin especificar —', 'casa-eventos' ) ),
				onChange: function ( v ) {
					setMeta( K.entryKind, v );
				},
			} ) ),
			el( components.TextControl, assign( sized, {
				label: __( 'Texto de entrada', 'casa-eventos' ),
				value: meta[ K.entryLabel ] || '',
				help: __( 'Opcional. Texto corto que se muestra en la tarjeta (por ejemplo "Por Passline").', 'casa-eventos' ),
				onChange: function ( v ) {
					setMeta( K.entryLabel, v );
				},
			} ) ),
			el( components.TextControl, assign( sized, {
				label: __( 'Aforo', 'casa-eventos' ),
				type: 'number',
				min: 1,
				step: 1,
				value: capacity ? String( capacity ) : '',
				placeholder: String( config.defaults.capacity ),
				help: sprintf( __( 'Personas. Vacío: %d.', 'casa-eventos' ), config.defaults.capacity ),
				onChange: function ( v ) {
					var n = parseInt( v, 10 );
					setMeta( K.capacity, v === '' || isNaN( n ) ? null : n );
				},
			} ) ),
			el( components.TextControl, assign( sized, {
				label: __( 'Cierre de venta', 'casa-eventos' ),
				type: 'datetime-local',
				value: toInput( close ),
				help: start
					? sprintf( __( 'Vacío: %1$d minutos antes del inicio (%2$s).', 'casa-eventos' ),
						config.defaults.salesCloseMinutes, display( addMinutes( start, -config.defaults.salesCloseMinutes ) ) )
					: sprintf( __( 'Vacío: %d minutos antes del inicio.', 'casa-eventos' ), config.defaults.salesCloseMinutes ),
				onChange: function ( v ) {
					setMeta( K.salesClose, fromInput( v ) );
				},
			} ) ),
			el( 'p', { className: 'casa-eventos-panel__note' },
				__( 'La bajada se escribe en el panel Extracto; el afiche es la imagen destacada.', 'casa-eventos' ) ),
			el( Fragment, null, notices )
		);
	}

	// ----- Panel: Publicación del evento -------------------------------------

	function PublicationPanel() {
		var data = useEvent();
		var setMeta = useSetMeta();
		if ( data.postType !== config.postType ) {
			return null;
		}

		var meta = data.meta;
		var cancelled = config.values.cancelled;
		var status = meta[ K.status ] || 'active';
		var savedStatus = data.savedMeta[ K.status ] || '';
		var wasCancelled = savedStatus === cancelled;
		var info = data.info;

		function onStatus( value ) {
			if ( value === status ) {
				return;
			}
			if ( value === cancelled && ! window.confirm(
				__( '¿Cancelar este evento? Queda publicado como cancelado y se bloquean sus acciones. No se hace ningún reembolso automático.', 'casa-eventos' ) ) ) {
				return;
			}
			if ( wasCancelled && value !== cancelled && ! window.confirm(
				__( 'Este evento fue cancelado. Reactivarlo vuelve a habilitar sus acciones. Las personas avisadas de la cancelación no reciben otro aviso. ¿Continuar?', 'casa-eventos' ) ) ) {
				return;
			}
			setMeta( K.status, value );
		}

		var statusControl = wasCancelled && ! config.canReactivate
			? el( components.Notice, { status: 'warning', isDismissible: false },
				__( 'Evento cancelado. Solo un administrador puede reactivarlo.', 'casa-eventos' ) )
			: el( components.RadioControl, {
				label: __( 'Estado', 'casa-eventos' ),
				selected: status,
				options: config.statuses,
				onChange: onStatus,
			} );

		return el(
			PluginDocumentSettingPanel,
			{ name: 'casa-eventos-publicacion', title: __( 'Publicación del evento', 'casa-eventos' ), className: 'casa-eventos-panel' },
			statusControl,
			info.effective_state_label
				? el( 'p', { className: 'casa-eventos-panel__note' },
					sprintf( __( 'Estado efectivo (al guardar): %s.', 'casa-eventos' ), info.effective_state_label ) )
				: null,
			el( components.ToggleControl, assign( common, {
				label: __( 'Visible en la Agenda', 'casa-eventos' ),
				help: __( 'Si está apagado, el evento no aparece en Agenda, Home ni relacionados, pero su página sigue accesible por enlace.', 'casa-eventos' ),
				checked: meta[ K.listed ] !== false,
				onChange: function ( v ) {
					setMeta( K.listed, !! v );
				},
			} ) ),
			el( components.ToggleControl, assign( common, {
				label: __( 'Destacado en la Home', 'casa-eventos' ),
				checked: !! meta[ K.featured ],
				onChange: function ( v ) {
					setMeta( K.featured, !! v );
				},
			} ) ),
			meta[ K.featured ] && meta[ K.listed ] === false
				? el( components.Notice, { status: 'warning', isDismissible: false },
					__( 'Un evento destacado tiene que estar visible en la Agenda para aparecer en la Home.', 'casa-eventos' ) )
				: null,
			el( components.TextControl, assign( sized, {
				label: __( 'ID público (UUID)', 'casa-eventos' ),
				value: info.uuid || '',
				placeholder: __( 'Se asigna al guardar por primera vez.', 'casa-eventos' ),
				readOnly: true,
				onChange: function () {},
			} ) )
		);
	}

	wp.plugins.registerPlugin( 'casa-eventos-datos', { render: DataPanel } );
	wp.plugins.registerPlugin( 'casa-eventos-publicacion', { render: PublicationPanel } );
}( window.wp, window.casaEventosPanel ) );
