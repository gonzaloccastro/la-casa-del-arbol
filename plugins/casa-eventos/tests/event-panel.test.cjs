/**
 * Node test of the block editor panels (no browser, no dependencies).
 *
 *     node plugins/casa-eventos/tests/event-panel.test.cjs
 *
 * Loads assets/admin/event-panel.js against a fake `wp` global, renders
 * both panels to a plain element tree and drives their onChange handlers.
 * Real rendering inside Gutenberg is verified in runtime QA.
 */
'use strict';

const fs = require( 'fs' );
const path = require( 'path' );
const vm = require( 'vm' );
const assert = require( 'assert' );

const source = fs.readFileSync( path.join( __dirname, '..', 'assets', 'admin', 'event-panel.js' ), 'utf8' );

const keys = {
	start: '_casa_start', end: '_casa_end', accessMode: '_casa_access_mode', entryKind: '_casa_entry_kind',
	entryLabel: '_casa_entry_label', externalUrl: '_casa_external_url', capacity: '_casa_capacity',
	salesClose: '_casa_sales_close', status: '_casa_status', listed: '_casa_listed', featured: '_casa_featured',
};

function setup( state, configOver ) {
	const plugins = {};
	const edits = [];
	const confirms = [];
	const Panel = function Panel() {};
	const component = ( name ) => {
		const c = function () {};
		c.displayName = name;
		return c;
	};
	const wp = {
		plugins: { registerPlugin: ( name, settings ) => { plugins[ name ] = settings.render; } },
		editor: { PluginDocumentSettingPanel: Panel },
		element: { createElement: ( type, props, ...children ) => ( { type, props: props || {}, children } ), Fragment: 'Fragment' },
		data: {
			useSelect: ( fn ) => fn( () => ( {
				getCurrentPostType: () => state.postType || 'casa_evento',
				getEditedPostAttribute: ( a ) => ( a === 'meta' ? state.meta : a === 'title' ? state.title : a === 'casa_categoria' ? state.terms : undefined ),
				getCurrentPostAttribute: ( a ) => ( a === 'meta' ? state.savedMeta : a === 'casa_event' ? state.info : undefined ),
			} ) ),
			useDispatch: () => ( { editPost: ( e ) => edits.push( e ) } ),
		},
		components: {
			TextControl: component( 'TextControl' ), SelectControl: component( 'SelectControl' ),
			RadioControl: component( 'RadioControl' ), ToggleControl: component( 'ToggleControl' ), Notice: component( 'Notice' ),
		},
		i18n: { __: ( s ) => s, sprintf: ( f, ...a ) => { let i = 0; return f.replace( /%(\d\$)?[sd]/g, ( m, pos ) => String( pos ? a[ parseInt( pos, 10 ) - 1 ] : a[ i++ ] ) ); } },
	};
	const config = Object.assign( {
		postType: 'casa_evento', taxonomyRestBase: 'casa_categoria', keys,
		values: { external: 'external', tickets: 'tickets', cancelled: 'cancelled' },
		defaults: { capacity: 100, salesCloseMinutes: 60, durationMinutes: 180, timezone: 'America/Argentina/Buenos_Aires' },
		accessModes: [ { value: 'tickets', label: 'Venta de entradas' }, { value: 'whatsapp', label: 'Reserva por WhatsApp' }, { value: 'external', label: 'Venta externa' } ],
		entryKinds: [ { value: 'paid', label: 'Paga' } ],
		statuses: [ { value: 'active', label: 'Activo' }, { value: 'paused', label: 'Pausado' }, { value: 'cancelled', label: 'Cancelado' } ],
		canReactivate: false,
	}, configOver || {} );
	const window = { wp, casaEventosPanel: config, confirm: ( m ) => { confirms.push( m ); return state.confirm !== false; } };
	vm.runInNewContext( source, { window, Date, Object, String, isNaN, parseInt } );
	return { plugins, edits, confirms, Panel };
}

function flatten( node, out = [] ) {
	if ( ! node || typeof node !== 'object' ) {
		return out;
	}
	if ( Array.isArray( node ) ) {
		node.forEach( ( n ) => flatten( n, out ) );
		return out;
	}
	out.push( node );
	( node.children || [] ).forEach( ( c ) => flatten( c, out ) );
	return out;
}

function find( tree, type, label ) {
	return flatten( tree ).find( ( n ) => n.type && n.type.displayName === type && ( ! label || String( n.props.label ).startsWith( label ) ) );
}

function texts( tree ) {
	return flatten( tree ).filter( ( n ) => n.type && n.type.displayName === 'Notice' ).map( ( n ) => n.children.join( '' ) );
}

/** Normalize objects created inside the vm context (other Object prototype). */
const plain = ( v ) => JSON.parse( JSON.stringify( v ) );

let passed = 0;
function test( name, fn ) {
	fn();
	passed++;
}

const base = () => ( {
	title: 'Lucernaria',
	terms: [ 4 ],
	meta: { _casa_start: '2026-09-05 21:00:00', _casa_end: '', _casa_access_mode: 'whatsapp', _casa_capacity: 100, _casa_sales_close: '', _casa_status: 'active', _casa_listed: true, _casa_featured: false },
	savedMeta: { _casa_status: 'active' },
	info: { uuid: 'a3bb189e-8bf9-4888-9912-ace4e6543002', timezone: 'America/Argentina/Buenos_Aires', effective_state_label: 'Activo' },
} );

test( 'registers both panels', () => {
	const { plugins } = setup( base() );
	assert.deepStrictEqual( plain( Object.keys( plugins ).sort() ), [ 'casa-eventos-datos', 'casa-eventos-publicacion' ] );
} );

test( 'renders nothing on other post types', () => {
	const { plugins } = setup( Object.assign( base(), { postType: 'page' } ) );
	assert.strictEqual( plugins[ 'casa-eventos-datos' ](), null );
	assert.strictEqual( plugins[ 'casa-eventos-publicacion' ](), null );
} );

test( 'start input converts wall time without the browser timezone', () => {
	const { plugins, edits } = setup( base() );
	const tree = plugins[ 'casa-eventos-datos' ]();
	const start = find( tree, 'TextControl', 'Inicio' );
	assert.strictEqual( start.props.value, '2026-09-05T21:00' );
	assert.strictEqual( start.props.type, 'datetime-local' );
	start.props.onChange( '2026-09-06T00:30' );
	assert.deepStrictEqual( plain( edits.pop() ), { meta: { _casa_start: '2026-09-06 00:30:00' } } );
	start.props.onChange( '' );
	assert.deepStrictEqual( plain( edits.pop() ), { meta: { _casa_start: '' } } );
} );

test( 'default cutoff and effective end hints use wall-time arithmetic', () => {
	const { plugins } = setup( base() );
	const tree = plugins[ 'casa-eventos-datos' ]();
	assert.match( find( tree, 'TextControl', 'Cierre de venta' ).props.help, /05\/09\/2026 20:00/ );
	assert.match( find( tree, 'TextControl', 'Fin' ).props.help, /06\/09\/2026 00:00/ );
} );

test( 'capacity: blank sends null (server materializes 100)', () => {
	const { plugins, edits } = setup( base() );
	const cap = find( plugins[ 'casa-eventos-datos' ](), 'TextControl', 'Aforo' );
	cap.props.onChange( '' );
	assert.deepStrictEqual( plain( edits.pop() ), { meta: { _casa_capacity: null } } );
	cap.props.onChange( '250' );
	assert.deepStrictEqual( plain( edits.pop() ), { meta: { _casa_capacity: 250 } } );
} );

test( 'external URL field only in external mode', () => {
	let s = base();
	assert.strictEqual( find( setup( s ).plugins[ 'casa-eventos-datos' ](), 'TextControl', 'Enlace' ), undefined );
	s.meta._casa_access_mode = 'external';
	const tree = setup( s ).plugins[ 'casa-eventos-datos' ]();
	assert.ok( find( tree, 'TextControl', 'Enlace de venta externa' ) );
	assert.ok( texts( tree ).some( ( t ) => /enlace de venta externa/.test( t ) ), 'missing URL hinted' );
} );

test( 'tickets mode shows the informational hint, and only tickets mode', () => {
	const s = base();
	s.meta._casa_access_mode = 'tickets';
	const notes = texts( setup( s ).plugins[ 'casa-eventos-datos' ]() );
	assert.ok( notes.includes( 'La venta propia todavía no está disponible; el evento se publica sin botón de compra.' ), 'hint shown' );
	[ 'whatsapp', 'external', '' ].forEach( ( mode ) => {
		const t = base();
		t.meta._casa_access_mode = mode;
		assert.ok( ! texts( setup( t ).plugins[ 'casa-eventos-datos' ]() ).some( ( x ) => /venta propia/.test( x ) ), 'no hint for ' + ( mode || 'empty' ) );
	} );
	const select = find( setup( s ).plugins[ 'casa-eventos-datos' ](), 'SelectControl', 'Modalidad' );
	assert.strictEqual( select.props.help, undefined, 'the old help text is gone (the hint replaces it)' );
} );

test( 'publish hints list what the server will require', () => {
	const s = base();
	s.title = '';
	s.terms = [];
	s.meta._casa_start = '';
	s.meta._casa_access_mode = '';
	const notes = texts( setup( s ).plugins[ 'casa-eventos-datos' ]() ).join( ' ' );
	[ 'el título', 'la fecha y hora de inicio', 'la modalidad de acceso', 'una sola categoría' ].forEach( ( h ) => assert.ok( notes.includes( h ), h ) );
	assert.strictEqual( texts( setup( base() ).plugins[ 'casa-eventos-datos' ]() ).length, 0, 'no hints when complete' );
} );

test( 'cutoff after start warns, after the effective end errors', () => {
	const s = base();
	s.meta._casa_sales_close = '2026-09-05 23:00:00';
	assert.ok( texts( setup( s ).plugins[ 'casa-eventos-datos' ]() ).some( ( t ) => /posterior al inicio/.test( t ) ) );
	s.meta._casa_sales_close = '2026-09-06 00:30:00';
	assert.ok( texts( setup( s ).plugins[ 'casa-eventos-datos' ]() ).some( ( t ) => /posterior al fin/.test( t ) ) );
} );

test( 'cancelling asks for confirmation', () => {
	const s = base();
	const { plugins, edits, confirms } = setup( s );
	find( plugins[ 'casa-eventos-publicacion' ](), 'RadioControl' ).props.onChange( 'cancelled' );
	assert.strictEqual( confirms.length, 1 );
	assert.deepStrictEqual( plain( edits.pop() ), { meta: { _casa_status: 'cancelled' } } );
	s.confirm = false;
	const second = setup( s );
	find( second.plugins[ 'casa-eventos-publicacion' ](), 'RadioControl' ).props.onChange( 'cancelled' );
	assert.strictEqual( second.edits.length, 0, 'declined confirmation changes nothing' );
} );

test( 'editors cannot reactivate; administrators are warned', () => {
	const s = base();
	s.meta._casa_status = 'cancelled';
	s.savedMeta._casa_status = 'cancelled';
	const editor = setup( s ).plugins[ 'casa-eventos-publicacion' ]();
	assert.strictEqual( find( editor, 'RadioControl' ), undefined );
	assert.ok( texts( editor ).some( ( t ) => /Solo un administrador/.test( t ) ) );
	const admin = setup( s, { canReactivate: true } );
	find( admin.plugins[ 'casa-eventos-publicacion' ](), 'RadioControl' ).props.onChange( 'active' );
	assert.strictEqual( admin.confirms.length, 1 );
	assert.deepStrictEqual( plain( admin.edits.pop() ), { meta: { _casa_status: 'active' } } );
} );

test( 'toggles send booleans; UUID is read-only', () => {
	const { plugins, edits } = setup( base() );
	const tree = plugins[ 'casa-eventos-publicacion' ]();
	find( tree, 'ToggleControl', 'Visible' ).props.onChange( false );
	assert.deepStrictEqual( plain( edits.pop() ), { meta: { _casa_listed: false } } );
	find( tree, 'ToggleControl', 'Destacado' ).props.onChange( true );
	assert.deepStrictEqual( plain( edits.pop() ), { meta: { _casa_featured: true } } );
	const uuid = find( tree, 'TextControl', 'ID público' );
	assert.strictEqual( uuid.props.readOnly, true );
	assert.strictEqual( uuid.props.value, 'a3bb189e-8bf9-4888-9912-ace4e6543002' );
} );

console.log( `event-panel: ${ passed } tests passed` );
