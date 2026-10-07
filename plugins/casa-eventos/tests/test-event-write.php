<?php
/**
 * E3.1 operational write API: authorization, lossless PATCH semantics,
 * complete-candidate validation and persistence/lifecycle failure boundaries.
 * Stand-ins exercise plugin behavior, not actual WordPress SQL/revisions.
 *
 * @package CasaEventos
 */

use CasaEventos\Core;

$prior_caps = $GLOBALS['t_caps'];
$GLOBALS['t_caps'] = array_fill_keys( Core\programador_capabilities(), true ) + array( 'edit_post' => true, 'read_post' => true );
$GLOBALS['t_is_admin'] = true;
t_reset_store();

function t_write_state() {
	return serialize( array( $GLOBALS['t_posts'], $GLOBALS['t_meta'], $GLOBALS['t_terms'], $GLOBALS['t_actions'] ) );
}
function t_write_rejected( $id, array $fields, $action, $label ) {
	$before = t_write_state();
	$r = Core\save_event( $id, $fields, $action );
	t_ok( is_wp_error( $r ), $label . ': refused' );
	t_eq( $before, t_write_state(), $label . ': zero mutations/lifecycle actions' );
}
function t_write_fields() {
	return array(
		'title' => 'Evento', 'excerpt' => 'Bajada', 'content' => '<p>Descripción.</p>',
		'start' => '2030-09-05T21:00', 'end' => '', 'access_mode' => 'whatsapp',
		'entry_kind' => 'gorra', 'entry_label' => 'A la gorra', 'capacity' => 250,
		'sales_close' => '', 'status' => 'active', 'listed' => true, 'featured' => false,
		'category_ids' => array( 4 ),
	);
}

t_group( 'event-write/authorization and allowlists' );
$GLOBALS['t_caps']['edit_casa_eventos'] = false;
t_write_rejected( 0, array(), null, 'create capability required' );
$GLOBALS['t_caps']['edit_casa_eventos'] = true;
t_write_rejected( '0', array(), null, 'string ID rejected' );
t_write_rejected( -1, array(), null, 'negative ID rejected' );
t_write_rejected( 98765, array(), null, 'missing ID rejected' );
$page = t_add_post( array( 'post_type' => 'page' ) );
t_write_rejected( $page, array(), null, 'another post type rejected' );
$draft = t_add_post( array( 'post_title' => 'Borrador' ) );
$GLOBALS['t_denied_posts'] = array( $draft );
t_write_rejected( $draft, array(), null, 'per-event edit capability enforced' );
t_ok( is_wp_error( Core\event_edit_snapshot( $draft ) ), 'snapshot also requires per-event authorization' );
unset( $GLOBALS['t_denied_posts'] );
foreach ( array( 'post_status', 'post_date', 'post_author', 'uuid', 'timezone', '_casa_status', 'start_gmt', 'price' ) as $key ) {
	t_write_rejected( $draft, array( $key => 'x' ), null, 'forbidden input ' . $key );
}
foreach ( array( 'future', 'private', 'pending', '', false, true, 1, array() ) as $action ) {
	t_write_rejected( $draft, array(), $action, 'unknown publication action' );
}
t_write_rejected( $draft, array(), 'update', 'update requires a published Event' );
t_write_rejected( 0, array(), 'return_to_draft', 'return-to-draft requires an existing Event' );
$GLOBALS['t_caps']['publish_casa_eventos'] = false;
t_write_rejected( 0, t_write_fields(), 'publish', 'publication capability required' );
$GLOBALS['t_caps']['publish_casa_eventos'] = true;

t_group( 'event-write/complete candidate validation before persistence' );
foreach ( array(
	array( 'start' => '2030-02-30T21:00' ),
	array( 'end' => '2030-09-05 20:00:00' ),
	array( 'access_mode' => 'invalid' ),
	array( 'entry_kind' => 'invalid' ),
	array( 'entry_label' => str_repeat( 'x', 121 ) ),
	array( 'external_url' => 'javascript:alert(1)' ),
	array( 'access_mode' => 'external', 'external_url' => '' ),
	array( 'capacity' => 0 ),
	array( 'capacity' => 1.5 ),
	array( 'status' => 'finished' ),
	array( 'listed' => 'perhaps' ),
	array( 'sales_close' => '2030-09-06 00:01:00' ),
	array( 'category_ids' => array() ),
	array( 'category_ids' => array( 4, 5 ) ),
	array( 'title' => array() ),
) as $bad ) {
	t_write_rejected( 0, array_merge( t_write_fields(), $bad ), 'publish', 'invalid complete candidate ' . implode( ',', array_keys( $bad ) ) );
}
t_write_rejected( 0, array( 'start' => 'bad' ), 'save_draft', 'draft still validates format' );
foreach ( array_keys( Core\event_write_meta_fields() ) as $field ) {
	t_write_rejected( 0, array( $field => array( 'unexpected' ) ), 'save_draft', 'malformed meta type: ' . $field );
}
add_filter( 'casa_eventos/validate_event', static function ( $r ) { $r['errors']['extension'] = 'Extension guard.'; return $r; } );
t_write_rejected( 0, t_write_fields(), 'publish', 'shared domain extension filter enforced' );
remove_all_filters( 'casa_eventos/validate_event' );
$empty = Core\save_event( 0, array(), 'save_draft' );
t_ok( ! is_wp_error( $empty ), 'valid empty draft can be created' );
t_eq( 'draft', get_post( $empty['id'] )->post_status, 'empty draft remains draft' );

t_group( 'event-write/category and poster operations' );
$GLOBALS['t_caps']['assign_casa_categorias'] = false;
t_write_rejected( $draft, array( 'category_ids' => array( 4 ) ), null, 'category assignment capability' );
$GLOBALS['t_caps']['assign_casa_categorias'] = true;
foreach ( array( '4', array( '4' ), array( 0 ), array( -1 ) ) as $ids ) {
	t_write_rejected( $draft, array( 'category_ids' => $ids ), null, 'category IDs strictly typed' );
}
$GLOBALS['t_missing_terms'] = array( 999 );
t_write_rejected( $draft, array( 'category_ids' => array( 999 ) ), null, 'unknown/wrong-taxonomy term rejected even in a draft' );
unset( $GLOBALS['t_missing_terms'] );
$image = t_add_post( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_author' => 999, 'post_mime_type' => 'image/jpeg' ) );
$pdf = t_add_post( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_mime_type' => 'application/pdf' ) );
foreach ( array( '1', -1, $pdf, $page, 9999 ) as $poster ) {
	t_write_rejected( $draft, array( 'poster_id' => $poster ), null, 'invalid poster rejected' );
}
$GLOBALS['t_caps']['upload_files'] = false;
t_write_rejected( $draft, array( 'poster_id' => $image ), null, 'media-selection capability required' );
$GLOBALS['t_caps']['upload_files'] = true;
$GLOBALS['t_denied_posts'] = array( $image );
t_write_rejected( $draft, array( 'poster_id' => $image ), null, 'unreadable image rejected' );
unset( $GLOBALS['t_denied_posts'] );
$GLOBALS['t_unusable_images'][ $image ] = true;
t_write_rejected( $draft, array( 'poster_id' => $image ), null, 'unusable image rejected' );
unset( $GLOBALS['t_unusable_images'] );

t_group( 'event-write/publish and lifecycle after complete persistence' );
$fields = t_write_fields() + array( 'poster_id' => $image );
$fields['entry_label'] = 'Rock \'n\' roll \\ música';
$seen = array();
$observer = static function ( $id ) use ( &$seen ) {
	$seen[] = array( get_post_meta( $id, '_casa_start', true ), wp_get_object_terms( $id ), get_post_thumbnail_id( $id ) );
};
add_action( 'wp_after_insert_post', $observer, 10 );
$r = Core\save_event( 0, $fields, 'publish' );
t_ok( ! is_wp_error( $r ), 'new published Event persists' );
$id = $r['id'];
t_eq( array( array( '2030-09-05 21:00:00', array( 4 ), $image ) ), $seen, 'after-insert sees final metadata/category/poster' );
remove_filter( 'wp_after_insert_post', $observer, 10 );
t_eq( 1, count( array_filter( t_actions( 'casa_eventos/event_synced' ), static fn( $a ) => $a[1][0] === $id ) ), 'sync exactly once after successful persistence' );
t_eq( 'publish', get_post( $id )->post_status, 'Event date does not schedule publication' );
t_eq( '2030-09-06 00:00:00', get_post_meta( $id, '_casa_start_gmt', true ), 'start normalized and GMT derived' );
t_eq( $fields['entry_label'], get_post_meta( $id, '_casa_entry_label', true ), 'slashes and quotes survive WP slashing boundary' );
$snap = Core\event_edit_snapshot( $id );
t_eq( $fields['content'], $snap['fields']['content'], 'snapshot content exact' );
t_eq( $image, $snap['fields']['poster_id'], 'another author image can be selected without editing it' );
t_ok( $snap['meta_present']['capacity'], 'snapshot distinguishes stored/default meta' );
t_ok( ! $snap['can_reactivate'], 'snapshot uses actual reactivation capability' );
$uuid = $snap['uuid'];
$tz = $snap['timezone'];

t_group( 'event-write/omission versus explicit clearing or reset' );
Core\save_event( $id, array( 'external_url' => 'https://tickets.test/event' ), 'update' );
$snap = Core\event_edit_snapshot( $id );
t_eq( 'https://tickets.test/event', $snap['fields']['external_url'], 'snapshot retains URL hidden by WhatsApp mode' );
$before_meta = $GLOBALS['t_meta'][ $id ];
$before_post = clone get_post( $id );
$GLOBALS['t_options']['timezone_string'] = 'Europe/Madrid';
$r = Core\save_event( $id, array( 'featured' => true, 'listed' => false ), 'update' );
t_ok( ! is_wp_error( $r ) && isset( $r['warnings']['featured_not_listed'] ), 'warning does not block a valid update' );
foreach ( array( '_casa_start', '_casa_end', '_casa_capacity', '_casa_sales_close', '_casa_entry_kind', '_casa_entry_label', '_casa_external_url', '_casa_uuid', '_casa_timezone' ) as $key ) {
	t_eq( $before_meta[ $key ] ?? '', get_post_meta( $id, $key, true ), 'omitted storage preserved: ' . $key );
}
t_eq( $before_post->post_content, get_post( $id )->post_content, 'omitted description preserved' );
t_eq( array( 4 ), wp_get_object_terms( $id ), 'omitted category preserved' );
t_eq( $image, get_post_thumbnail_id( $id ), 'omitted poster preserved' );
t_eq( $uuid, Core\Event::get( $id )->uuid(), 'UUID stable' );
t_eq( $tz, Core\Event::get( $id )->timezone(), 'captured timezone stable after site setting changes' );
$GLOBALS['t_options']['timezone_string'] = 'America/Argentina/Buenos_Aires';
$r = Core\save_event( $id, array( 'excerpt' => '', 'content' => '', 'entry_label' => null, 'capacity' => '', 'sales_close' => null, 'poster_id' => 0, 'featured' => null, 'listed' => null ), 'update' );
t_ok( ! is_wp_error( $r ), 'explicit clears/resets save' );
t_eq( '', get_post( $id )->post_excerpt, 'excerpt clears' );
t_eq( '', get_post( $id )->post_content, 'simple description clears' );
t_eq( '', get_post_meta( $id, '_casa_entry_label', true ), 'optional label clears' );
t_eq( 100, Core\Event::get( $id )->capacity(), 'capacity resets to existing default' );
t_eq( 0, get_post_thumbnail_id( $id ), 'poster association clears without deleting attachment' );
t_ok( get_post( $image ) instanceof WP_Post, 'attachment remains' );
t_ok( Core\Event::get( $id )->is_listed() && ! Core\Event::get( $id )->is_featured(), 'flag null resets defaults' );
t_write_rejected( $id, array( 'start' => null ), 'update', 'clearing required published field rejected' );
Core\save_event( $id, array(), 'return_to_draft' );
$r = Core\save_event( $id, array( 'category_ids' => array(), 'start' => null ), 'save_draft' );
t_ok( ! is_wp_error( $r ), 'draft can explicitly clear category/start' );
t_eq( array(), wp_get_object_terms( $id ), 'empty category list clears assignment' );
$r = Core\save_event( $id, t_write_fields(), 'publish' );
t_ok( ! is_wp_error( $r ), 'publishing draft with submitted dates/meta passes admin guard (not stale stored values)' );

t_group( 'event-write/protected block content, including KSES preservation' );
$block = "<!-- wp:group {\"className\":\"x\"} -->\n<div><iframe src=\"x\"></iframe>\\\\</div>\n<!-- /wp:group -->";
get_post( $id )->post_content = $block;
t_ok( Core\event_edit_snapshot( $id )['content_protected'], 'block description marked protected' );
t_write_rejected( $id, array( 'content' => '<p>Replacement</p>' ), 'update', 'block replacement rejected' );
t_write_rejected( $id, array( 'content' => '' ), 'update', 'block clearing rejected' );
t_write_rejected( 0, array( 'content' => $block ), 'save_draft', 'new block input rejected by simple-editor API' );
$kses = static fn( $v ) => str_replace( 'iframe', 'removed', $v );
add_filter( 'post_content_save_pre', $kses );
$r = Core\save_event( $id, array( 'title' => 'Cambio' ), 'update' );
t_ok( ! is_wp_error( $r ), 'unrelated edit of protected description succeeds' );
t_eq( $block, get_post( $id )->post_content, 'omitted block bytes survive re-sanitization' );
$r = Core\save_event( $id, array( 'content' => $block ), 'update' );
t_ok( ! is_wp_error( $r ), 'identical block input is preservation' );
t_eq( $block, get_post( $id )->post_content, 'identical block bytes exact' );
remove_filter( 'post_content_save_pre', $kses, 10 );

t_group( 'event-write/explicit status transitions and reactivation' );
t_write_rejected( $id, array(), 'save_draft', 'published Event requires explicit return-to-draft' );
foreach ( array( 'future', 'private', 'pending' ) as $status ) {
	get_post( $id )->post_status = $status;
	get_post( $id )->post_date_gmt = '2000-01-01 00:00:00';
	$r = Core\save_event( $id, array( 'entry_label' => 'Texto' ) );
	t_ok( ! is_wp_error( $r ), 'edit other WordPress status: ' . $status );
	t_eq( $status, get_post( $id )->post_status, 'omitted action preserves ' . $status );
}
get_post( $id )->post_status = 'future';
get_post( $id )->post_date_gmt = '2099-01-01 00:00:00';
$r = Core\save_event( $id, array(), 'publish' );
t_ok( ! is_wp_error( $r ), 'explicit publish overrides inherited future publication date' );
t_eq( 'publish', get_post( $id )->post_status, 'publish means now' );
t_ok( get_post( $id )->post_date_gmt < '2099-01-01 00:00:00', 'old scheduling date removed' );
$r = Core\save_event( $id, array( 'status' => 'cancelled' ), 'update' );
t_ok( ! is_wp_error( $r ), 'Programador can cancel' );
t_eq( 1, count( t_actions( 'casa_eventos/event_cancelled' ) ), 'cancellation lifecycle once' );
foreach ( array( 'active', 'paused', null ) as $status ) {
	t_write_rejected( $id, array( 'status' => $status ), 'update', 'actual capability prevents reactivation/reset' );
}
Core\save_event( $id, array(), 'return_to_draft' );
t_write_rejected( $id, array( 'status' => 'active' ), 'save_draft', 'reactivation forbidden even in draft' );
$GLOBALS['t_caps']['manage_options'] = true;
$r = Core\save_event( $id, array( 'status' => 'paused' ), 'save_draft' );
t_ok( ! is_wp_error( $r ), 'Administrator capability permits reactivation' );
t_eq( 1, count( t_actions( 'casa_eventos/event_reactivated' ) ), 'reactivation lifecycle once' );
$GLOBALS['t_caps']['manage_options'] = false;

t_group( 'event-write/persistence failures never report success or sync' );
foreach ( array( 'post', 'meta', 'terms' ) as $failure ) {
	$actions = count( t_actions( 'casa_eventos/event_synced' ) );
	$GLOBALS['t_persist_failure'] = $failure;
	$r = Core\save_event( 0, t_write_fields(), 'publish' );
	t_ok( is_wp_error( $r ), $failure . ' failure reported' );
	t_eq( $actions, count( t_actions( 'casa_eventos/event_synced' ) ), $failure . ' failure does not sync' );
	if ( 'post' !== $failure ) {
		t_ok( $r->get_error_data()['partial'] && $r->get_error_data()['id'] > 0, 'partial persistence explicitly identified' );
	}
}
unset( $GLOBALS['t_persist_failure'] );
$callbacks = $GLOBALS['t_hooks']['wp_insert_post_data'][9] ?? array();
t_eq( array(), array_values( $callbacks ), 'temporary preservation filters removed on failure' );
t_eq( array(), array_values( $GLOBALS['t_hooks']['wp_insert_post_empty_content'][10] ?? array() ), 'temporary empty-draft filter removed on failure' );

t_group( 'event-write/synchronization persistence failures' );
function t_write_lifecycle_counts() {
	$counts = array();
	foreach ( array( 'event_synced', 'schedule_changed', 'event_cancelled', 'event_reactivated' ) as $hook ) {
		$counts[ $hook ] = count( t_actions( 'casa_eventos/' . $hook ) );
	}
	return $counts;
}
function t_write_sync_failure( $id, $fields, $action, $key, $label, $short_circuit = false ) {
	$before = t_write_lifecycle_counts();
	$block = static fn( $check, $object_id, $meta_key ) => $meta_key === $key ? $short_circuit : $check;
	add_filter( 'update_post_metadata', $block, 5, 3 );
	try {
		$r = Core\save_event( $id, $fields, $action );
	} finally {
		remove_filter( 'update_post_metadata', $block, 5 );
	}
	t_ok( is_wp_error( $r ), $label . ': no success result' );
	if ( is_wp_error( $r ) ) {
		t_eq( 'casa_eventos_sync_failed', $r->get_error_code(), $label . ': sync failure propagated' );
		$data = $r->get_error_data();
		t_ok( $data['partial'] && $data['id'] > 0, $label . ': partial persistence and ID' );
		t_eq( $key, $data['meta_key'], $label . ': failed key identified' );
		if ( $id ) {
			t_eq( $id, $data['id'], $label . ': existing ID retained' );
		}
	}
	t_eq( $before, t_write_lifecycle_counts(), $label . ': no successful lifecycle actions' );
	t_eq( array(), array_values( $GLOBALS['t_hooks']['casa_eventos/event_sync_failed'][10] ?? array() ), $label . ': failure observer cleaned up' );
	t_eq( array(), array_values( $GLOBALS['t_hooks']['wp_insert_post_data'][9] ?? array() ), $label . ': preservation filter cleaned up' );
	t_eq( array(), array_values( $GLOBALS['t_hooks']['wp_insert_post_empty_content'][10] ?? array() ), $label . ': empty filter cleaned up' );
	t_ok( ! Core\uuid_internal_write(), $label . ': UUID guard restored' );
	return $r;
}
$sync_keys = array(
	'_casa_uuid', '_casa_timezone', '_casa_status', '_casa_listed',
	'_casa_featured', '_casa_capacity', '_casa_start_gmt',
	'_casa_end_gmt', '_casa_ends_gmt', '_casa_cancelled_gmt',
);
foreach ( $sync_keys as $key ) {
	// Omitted editorial inputs exercise materialization, not meta_input failure.
	t_write_sync_failure( 0, array( 'title' => 'Draft', 'start' => '2030-09-05T21:00' ), 'save_draft', $key, 'create ' . $key );
	$created = Core\save_event( 0, t_write_fields(), 'publish' );
	t_ok( ! is_wp_error( $created ), 'update setup succeeds: ' . $key );
	$update_id = $created['id'];
	// Force an existing Event to require this sync write, including repair.
	unset( $GLOBALS['t_meta'][ $update_id ][ $key ] );
	t_write_sync_failure( $update_id, array( 'start' => '2030-09-12T21:00' ), 'update', $key, 'update ' . $key );
}
// A short-circuit claiming true without writing is also not persistence.
t_write_sync_failure( 0, array(), 'save_draft', '_casa_timezone', 'false-positive metadata return', true );

// Reproduce the review's published cancelled-create case.
t_write_sync_failure( 0, array_merge( t_write_fields(), array( 'status' => 'cancelled' ) ), 'publish', '_casa_uuid', 'review create identity failure' );
t_write_sync_failure( 0, array_merge( t_write_fields(), array( 'status' => 'cancelled' ) ), 'publish', '_casa_cancelled_gmt', 'create cancellation instant failure' );
$created = Core\save_event( 0, t_write_fields(), 'publish' );
$transition_id = $created['id'];
t_write_sync_failure( $transition_id, array( 'status' => 'cancelled', 'start' => '2030-09-12T21:00' ), 'update', '_casa_ends_gmt', 'cancellation with derived failure' );
t_eq( '', get_post_meta( $transition_id, '_casa_cancelled_gmt', true ), 'failed derivation does not record cancellation' );
t_write_sync_failure( $transition_id, array(), 'update', '_casa_cancelled_gmt', 'update cancellation instant failure' );
$before = t_write_lifecycle_counts();
$r = Core\save_event( $transition_id, array(), 'update' );
t_ok( ! is_wp_error( $r ), 'cancellation retry succeeds' );
$after = t_write_lifecycle_counts();
t_eq( $before['event_cancelled'] + 1, $after['event_cancelled'], 'successful cancellation exactly once' );
t_eq( $before['event_synced'] + 1, $after['event_synced'], 'successful cancellation sync exactly once' );
$cancelled_at = get_post_meta( $transition_id, '_casa_cancelled_gmt', true );
$GLOBALS['t_caps']['manage_options'] = true;
t_write_sync_failure( $transition_id, array( 'status' => 'paused', 'start' => '2030-09-19T21:00' ), 'update', '_casa_ends_gmt', 'reactivation with derived failure' );
t_eq( $cancelled_at, get_post_meta( $transition_id, '_casa_cancelled_gmt', true ), 'failed derivation keeps cancellation instant' );
t_write_sync_failure( $transition_id, array(), 'update', '_casa_cancelled_gmt', 'reactivation clear failure' );
t_eq( $cancelled_at, get_post_meta( $transition_id, '_casa_cancelled_gmt', true ), 'failed clear keeps cancellation instant' );
$before = t_write_lifecycle_counts();
$r = Core\save_event( $transition_id, array(), 'update' );
t_ok( ! is_wp_error( $r ), 'reactivation retry succeeds' );
$after = t_write_lifecycle_counts();
t_eq( $before['event_reactivated'] + 1, $after['event_reactivated'], 'successful reactivation exactly once' );
t_eq( $before['event_synced'] + 1, $after['event_synced'], 'successful reactivation sync exactly once' );
t_eq( '', get_post_meta( $transition_id, '_casa_cancelled_gmt', true ), 'successful reactivation clears instant' );
$before = t_write_lifecycle_counts();
$r = Core\save_event( $transition_id, array(), 'update' );
t_ok( ! is_wp_error( $r ), 'unchanged sync succeeds despite no-change meta returns' );
$after = t_write_lifecycle_counts();
t_eq( $before['event_synced'] + 1, $after['event_synced'], 'unchanged sync exactly once' );
t_eq( $before['event_reactivated'], $after['event_reactivated'], 'no duplicate reactivation' );
t_eq( $before['event_cancelled'], $after['event_cancelled'], 'no duplicate cancellation' );
$GLOBALS['t_caps']['manage_options'] = false;

// Exceptions propagate, but cannot leave either E3.1's observer or UUID bypass.
$throw_uuid = static function ( $check, $object_id, $key ) {
	if ( '_casa_uuid' === $key ) {
		throw new RuntimeException( 'Injected synchronization exception.' );
	}
	return $check;
};
$before = t_write_lifecycle_counts();
add_filter( 'update_post_metadata', $throw_uuid, 5, 3 );
$threw = false;
try {
	Core\save_event( 0, array(), 'save_draft' );
} catch ( RuntimeException $error ) {
	$threw = true;
} finally {
	remove_filter( 'update_post_metadata', $throw_uuid, 5 );
}
t_ok( $threw, 'sync exception propagates without a success result' );
t_eq( $before, t_write_lifecycle_counts(), 'exception emits no successful lifecycle' );
t_ok( ! Core\uuid_internal_write(), 'UUID bypass restored after exception' );
foreach ( array( 'casa_eventos/event_sync_failed' => 10, 'wp_insert_post_data' => 9, 'wp_insert_post_empty_content' => 10 ) as $hook => $priority ) {
	t_eq( array(), array_values( $GLOBALS['t_hooks'][ $hook ][ $priority ] ?? array() ), 'exception cleanup: ' . $hook );
}

// An unrelated Event's failed nested save cannot contaminate this save.
$other = t_add_post( array( 'post_title' => 'Other draft' ) );
$block_other = static fn( $check, $object_id, $key ) => $object_id === $other && '_casa_uuid' === $key ? false : $check;
$nested = static function ( $saved_id ) use ( $other ) {
	if ( $saved_id !== $other ) {
		Core\sync_event( $other );
	}
};
add_filter( 'update_post_metadata', $block_other, 5, 3 );
add_action( 'wp_after_insert_post', $nested, 10 );
$r = Core\save_event( 0, t_write_fields(), 'publish' );
remove_filter( 'wp_after_insert_post', $nested, 10 );
remove_filter( 'update_post_metadata', $block_other, 5 );
t_ok( ! is_wp_error( $r ), 'failure capture is scoped to saved Event ID' );
t_eq( array(), array_values( $GLOBALS['t_hooks']['casa_eventos/event_sync_failed'][10] ?? array() ), 'successful save cleans observer' );

$GLOBALS['t_caps'] = $prior_caps;
$GLOBALS['t_is_admin'] = false;
t_reset_store();
