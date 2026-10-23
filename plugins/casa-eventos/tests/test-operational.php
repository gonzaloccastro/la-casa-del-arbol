<?php
/**
 * E3.2 transport, routing and rendering tests over real E3.1 domain code.
 * Stand-ins are not native WordPress dispatch, KSES, media or revision tests.
 *
 * @package CasaEventos
 */
use CasaEventos\Core;
use CasaEventos\Admin;

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) { return 'http://site.test/wp-admin/' . $path; }
}
require_once dirname( __DIR__ ) . '/inc/admin/operational.php';
require_once dirname( __DIR__ ) . '/inc/admin/event-form.php';
require_once dirname( __DIR__ ) . '/inc/admin/list-table.php';

$e32_prior_caps = $GLOBALS['t_caps'];
$e32_prior_user = $GLOBALS['t_current_user'] ?? null;
$e32_prior_admin = $GLOBALS['t_is_admin'] ?? false;
$GLOBALS['t_caps'] = array_fill_keys( Core\programador_capabilities(), true ) + array( 'edit_post' => true, 'read_post' => true );
$GLOBALS['t_current_user'] = new WP_User( 21, array( 'casa_programador' ), array_keys( array_filter( $GLOBALS['t_caps'] ) ) );
$GLOBALS['t_is_admin'] = true;
t_reset_store();

function t_op_request( $id, $intent, array $fields = array() ) {
	$snapshot = Admin\operational_snapshot( $id );
	$state = Admin\operational_state( $snapshot );
	return array( 'event_id' => (string) $id, 'intent' => $intent, 'state' => $state, 'nonces' => array( $intent => wp_create_nonce( Admin\operational_nonce_action( $id, $intent, $state ) ) ), 'fields' => $fields );
}
function t_op_rejected( $request, $label, $method = 'POST', $code = null ) {
	$before = serialize( array( $GLOBALS['t_posts'], $GLOBALS['t_meta'], $GLOBALS['t_terms'], $GLOBALS['t_actions'] ) );
	$result = Admin\operational_submit( $request, $method );
	t_ok( is_wp_error( $result ), $label . ': error' );
	if ( $code && is_wp_error( $result ) ) { t_eq( $code, $result->get_error_code(), $label . ': code' ); }
	t_eq( $before, serialize( array( $GLOBALS['t_posts'], $GLOBALS['t_meta'], $GLOBALS['t_terms'], $GLOBALS['t_actions'] ) ), $label . ': no mutations' );
}
function t_op_html( $snapshot, $result = null, $request = array() ) {
	ob_start();
	Admin\render_operational_form( $snapshot, $result, $request );
	return ob_get_clean();
}
function t_op_complete() {
	return array( 'title' => 'Noche Árbol', 'content' => '<p>Descripción</p>', 'start' => '2032-10-09T21:00:07', 'access_mode' => 'whatsapp', 'category_ids' => '4' );
}

t_group( 'operational/read and strict transport' );
t_eq( 0, count( $GLOBALS['t_posts'] ), 'initial store empty' );
$new = Admin\operational_snapshot( 0 );
t_ok( ! is_wp_error( $new ), 'new snapshot allowed' );
t_eq( 0, count( $GLOBALS['t_posts'] ), 'GET does not create auto-draft' );
foreach ( array( null, array(), true, -1, 0, '01', '1abc', '1.5', '-1', ' 1', '99999999999999999999999999999' ) as $bad ) {
	t_ok( is_wp_error( Admin\operational_id( $bad ) ), 'strict malformed ID rejected' );
}
t_eq( 0, Admin\operational_id( '0' ), 'create ID zero explicit' );
t_eq( 42, Admin\operational_id( '42' ), 'positive ID parsed' );
$GLOBALS['t_caps']['edit_casa_eventos'] = false;
t_ok( is_wp_error( Admin\operational_snapshot( 0 ) ), 'create permission enforced at read' );
$GLOBALS['t_caps']['edit_casa_eventos'] = true;

t_group( 'operational/create draft and publication' );
$request = t_op_request( 0, 'save_draft' );
$result = Admin\operational_submit( $request, 'POST' );
t_ok( ! is_wp_error( $result ), 'empty draft created' );
$id = $result['id'];
t_eq( 'draft', get_post( $id )->post_status, 'draft publication explicit' );
t_eq( 1, count( $GLOBALS['t_posts'] ), 'one created Event' );
t_op_rejected( t_op_request( $id, 'publish' ), 'incomplete publish', 'POST', 'casa_eventos_invalid_event' );
$result = Admin\operational_submit( t_op_request( $id, 'publish', t_op_complete() ), 'POST' );
t_ok( ! is_wp_error( $result ), 'complete Event publishes' );
t_eq( 'publish', get_post( $id )->post_status, 'publish immediately' );
t_eq( '2032-10-09 21:00:07', get_post_meta( $id, '_casa_start', true ), 'seconds preserved' );
$result = Admin\operational_submit( t_op_request( $id, 'update', array( 'entry_label' => "Rock 'n' roll \\ Árbol" ) ), 'POST' );
t_ok( ! is_wp_error( $result ), 'published update' );
t_eq( "Rock 'n' roll \\ Árbol", get_post_meta( $id, '_casa_entry_label', true ), 'unslashed Unicode text persists' );
t_eq( 1, count( $GLOBALS['t_posts'] ), 'updates do not create another Event' );

t_group( 'operational/nonces, capabilities and unknown inputs' );
t_op_rejected( t_op_request( $id, 'update' ), 'GET cannot write', 'GET' );
foreach ( array( 'event_id', 'state', 'intent', 'nonces' ) as $key ) {
	$r = t_op_request( $id, 'update' ); unset( $r[ $key ] );
	t_op_rejected( $r, 'missing ' . $key );
}
foreach ( array( 'event_id', 'state', 'intent' ) as $key ) {
	$r = t_op_request( $id, 'update' ); $r[ $key ] = array();
	t_op_rejected( $r, 'array ' . $key );
}
$r = t_op_request( $id, 'update' ); $r['nonces']['update'] = 'forged';
t_op_rejected( $r, 'forged nonce' );
$r = t_op_request( $id, 'update' ); $r['intent'] = 'return_to_draft';
t_op_rejected( $r, 'cross-action nonce' );
$other = Core\save_event( 0, array( 'title' => 'Other' ), 'save_draft' )['id'];
$r = t_op_request( $id, 'update' ); $r['event_id'] = (string) $other;
t_op_rejected( $r, 'cross-event nonce' );
$r = t_op_request( $id, 'update' ); $GLOBALS['t_session'] = 'different-session';
t_op_rejected( $r, 'cross-session nonce' ); unset( $GLOBALS['t_session'] );
$r = t_op_request( $id, 'update' ); $GLOBALS['t_denied_posts'] = array( $id );
t_op_rejected( $r, 'per-event edit denied' ); unset( $GLOBALS['t_denied_posts'] );
$GLOBALS['t_caps']['publish_casa_eventos'] = false;
t_op_rejected( t_op_request( $id, 'update' ), 'published write permission revoked' );
$GLOBALS['t_caps']['publish_casa_eventos'] = true;
foreach ( array( 'status', 'post_status', 'uuid', 'price' ) as $key ) {
	t_op_rejected( t_op_request( $id, 'update', array( $key => 'x' ) ), 'forbidden field ' . $key );
}
foreach ( array( array( 'poster_id' => '4x' ), array( 'category_ids' => array( '4' ) ), array( 'listed' => 'false' ), array( 'title' => array() ) ) as $bad ) {
	t_op_rejected( t_op_request( $id, 'update', $bad ), 'malformed transport field' );
}
$page = t_add_post( array( 'post_type' => 'page' ) );
$trash = t_add_post( array( 'post_status' => 'trash' ) );
foreach ( array( $page, $trash, 888888 ) as $invalid ) {
	t_op_rejected( array( 'event_id' => (string) $invalid ), 'wrong/missing Event ID' );
}

t_group( 'operational/omissions, clearing, media and categories' );
$before = Core\event_edit_snapshot( $id );
$r = Admin\operational_submit( t_op_request( $id, 'update', array( 'excerpt' => 'Bajada' ) ), 'POST' );
t_ok( ! is_wp_error( $r ), 'one-field update' );
t_eq( $before['fields']['content'], get_post( $id )->post_content, 'description omission preserved' );
t_eq( $before['fields']['start'], get_post_meta( $id, '_casa_start', true ), 'date omission preserved' );
$image = t_add_post( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_mime_type' => 'image/jpeg' ) );
$r = Admin\operational_submit( t_op_request( $id, 'update', array( 'poster_id' => (string) $image, 'category_ids' => '7' ) ), 'POST' );
t_ok( ! is_wp_error( $r ), 'image and category accepted through adapter' );
t_eq( $image, get_post_thumbnail_id( $id ), 'image selected' );
t_eq( array( 7 ), wp_get_object_terms( $id ), 'category assigned' );
foreach ( array( 'upload_files' => array( 'poster_id' => (string) $image ), 'assign_casa_categorias' => array( 'category_ids' => '4' ) ) as $cap => $fields ) {
	$GLOBALS['t_caps'][ $cap ] = false;
	// Choosing a different value exercises adapter auth, rather than omission.
	if ( 'upload_files' === $cap ) { $fields['poster_id'] = (string) t_add_post( array( 'post_type' => 'attachment', 'post_mime_type' => 'image/jpeg' ) ); }
	t_op_rejected( t_op_request( $id, 'update', $fields ), 'selection permission ' . $cap );
	$GLOBALS['t_caps'][ $cap ] = true;
}
$r = Admin\operational_submit( t_op_request( $id, 'update', array( 'excerpt' => '', 'content' => '', 'poster_id' => '0', 'capacity' => '', 'featured' => '0', 'listed' => '0' ) ), 'POST' );
t_ok( ! is_wp_error( $r ), 'explicit clears and default reset' );
t_eq( '', get_post( $id )->post_excerpt, 'excerpt cleared' );
t_eq( '', get_post( $id )->post_content, 'content cleared' );
t_eq( 0, get_post_thumbnail_id( $id ), 'poster association cleared' );
t_ok( get_post( $image ) !== null, 'attachment not deleted' );
t_eq( 100, Core\Event::get( $id )->capacity(), 'capacity default owned by Core' );
t_ok( ! Core\Event::get( $id )->is_listed(), 'explicit unchecked visibility' );
t_op_rejected( t_op_request( $id, 'update', array( 'category_ids' => '' ) ), 'published category cannot clear' );
t_set_terms( $other, array( 4, 7 ) );
$snapshot = Admin\operational_snapshot( $other );
t_eq( array(), Admin\operational_fields( array( 'category_ids' => '__preserve' ), $snapshot ), 'multiple categories preserved until explicit choice' );
t_eq( array( 'category_ids' => array( 4 ) ), Admin\operational_fields( array( 'category_ids' => '4' ), $snapshot ), 'explicit category replaces multiple' );
t_eq( array( 'category_ids' => array() ), Admin\operational_fields( array( 'category_ids' => '' ), $snapshot ), 'explicit empty category represented' );

t_group( 'operational/protected block content' );
$block = '<!-- wp:group --><div>Árbol \\</div><!-- /wp:group -->';
get_post( $id )->post_content = $block;
$r = Admin\operational_submit( t_op_request( $id, 'update', array( 'title' => 'Cambio' ) ), 'POST' );
t_ok( ! is_wp_error( $r ), 'other fields editable with protected description' );
t_eq( $block, get_post( $id )->post_content, 'blocks remain exact' );
t_op_rejected( t_op_request( $id, 'update', array( 'content' => '' ) ), 'cannot clear blocks' );
$html = t_op_html( Admin\operational_snapshot( $id ) );
t_ok( false === strpos( $html, 'name="fields[content]"' ), 'protected description never submitted by form' );
t_ok( false !== strpos( $html, 'pedí ayuda a un administrador' ), 'Programador fallback explanation' );
t_ok( false === strpos( $html, 'Abrir editor nativo' ), 'Programador receives no native fallback link' );

t_group( 'operational/stale-form detection beyond post_modified' );
t_raw_meta( $id, '_casa_sales_close', '' );
$changes = array(
	'title' => static function () use ( $id ) { get_post( $id )->post_title .= 'x'; },
	'description' => static function () use ( $id ) { get_post( $id )->post_content .= 'x'; },
	'excerpt' => static function () use ( $id ) { get_post( $id )->post_excerpt .= 'x'; },
	'modified' => static function () use ( $id ) { get_post( $id )->post_modified_gmt = '2030-01-01 00:00:00'; },
	'publication' => static function () use ( $id ) { get_post( $id )->post_status = 'private'; },
	'publication date' => static function () use ( $id ) { get_post( $id )->post_date_gmt = '2031-01-01 00:00:00'; },
	'meta' => static function () use ( $id ) { t_raw_meta( $id, '_casa_entry_label', 'Other edit' ); },
	'operational state' => static function () use ( $id ) { t_raw_meta( $id, '_casa_status', 'paused' ); },
	'category' => static function () use ( $id ) { t_set_terms( $id, array( 8 ) ); },
	'poster' => static function () use ( $id, $image ) { t_raw_meta( $id, '_thumbnail_id', $image ); },
	'presence' => static function () use ( $id ) { unset( $GLOBALS['t_meta'][ $id ]['_casa_sales_close'] ); },
	'identity' => static function () use ( $id ) { t_raw_meta( $id, '_casa_uuid', wp_generate_uuid4() ); },
);
foreach ( $changes as $name => $change ) {
	$posts = unserialize( serialize( $GLOBALS['t_posts'] ) ); $meta = $GLOBALS['t_meta']; $terms = $GLOBALS['t_terms'];
	$r = t_op_request( $id, 'update', array( 'title' => 'Stale overwrite' ) );
	$change();
	t_op_rejected( $r, 'stale after ' . $name, 'POST', 'casa_eventos_ui_stale' );
	$html = t_op_html( Admin\operational_snapshot( $id ), Admin\operational_error( 'stale', 'Reload' ), $r );
	t_ok( false === strpos( $html, '<form' ), 'stale form requires reload: ' . $name );
	$GLOBALS['t_posts'] = $posts; $GLOBALS['t_meta'] = $meta; $GLOBALS['t_terms'] = $terms;
}

t_group( 'operational/preservation of exceptional publication statuses' );
foreach ( array( 'future', 'private', 'pending' ) as $status ) {
	get_post( $id )->post_status = $status;
	get_post( $id )->post_date_gmt = '2099-01-01 00:00:00';
	$r = Admin\operational_submit( t_op_request( $id, 'preserve', array( 'entry_label' => $status ) ), 'POST' );
	t_ok( ! is_wp_error( $r ), 'preserving save: ' . $status );
	t_eq( $status, get_post( $id )->post_status, 'publication unchanged: ' . $status );
	t_eq( '2099-01-01 00:00:00', get_post( $id )->post_date_gmt, 'native scheduling date unchanged' );
}
get_post( $id )->post_status = 'publish';
$r = t_op_request( $id, 'return_to_draft' );
$confirmed = Admin\operational_submit( $r, 'POST' );
t_eq( 'return_to_draft', $confirmed['confirm'], 'unpublication requires confirmation' );
t_eq( 'publish', get_post( $id )->post_status, 'confirmation step does not write' );
$r['confirmed'] = '1';
t_ok( ! is_wp_error( Admin\operational_submit( $r, 'POST' ) ), 'explicit return to draft succeeds' );
t_eq( 'draft', get_post( $id )->post_status, 'draft target persisted' );

t_group( 'operational/operational lifecycle actions' );
foreach ( array( 'pause' => 'paused', 'resume' => 'active' ) as $intent => $status ) {
	$r = Admin\operational_submit( t_op_request( $id, $intent ), 'POST' );
	t_ok( ! is_wp_error( $r ), $intent . ' succeeds' );
	t_eq( $status, Core\Event::get( $id )->status(), $intent . ' correct state' );
	t_eq( 'draft', get_post( $id )->post_status, $intent . ' preserves publication' );
}
t_op_rejected( t_op_request( $id, 'pause', array( 'title' => 'Unintended edit' ) ), 'operational action cannot save editorial fields' );
$r = t_op_request( $id, 'cancel' );
t_eq( 'cancel', Admin\operational_submit( $r, 'POST' )['confirm'], 'cancellation confirmation server-side' );
$r['confirmed'] = '1';
t_ok( ! is_wp_error( Admin\operational_submit( $r, 'POST' ) ), 'cancellation succeeds' );
t_eq( 1, count( t_actions( 'casa_eventos/event_cancelled' ) ), 'cancel lifecycle once' );
t_op_rejected( t_op_request( $id, 'resume' ), 'cancelled cannot resume' );
t_op_rejected( t_op_request( $id, 'reactivate_active' ), 'Programador cannot reactivate' );
$GLOBALS['t_caps']['manage_options'] = true;
$GLOBALS['t_current_user'] = new WP_User( 1, array( 'administrator' ), array( 'manage_options' ) );
$html = t_op_html( Admin\operational_snapshot( $id ) );
t_ok( false !== strpos( $html, 'Abrir editor nativo / revisiones' ), 'administrator native fallback' );
$r = t_op_request( $id, 'reactivate_paused' );
t_eq( 'reactivate_paused', Admin\operational_submit( $r, 'POST' )['confirm'], 'reactivation confirmation' );
$r['confirmed'] = '1';
t_ok( ! is_wp_error( Admin\operational_submit( $r, 'POST' ) ), 'privileged reactivation succeeds' );
t_eq( 1, count( t_actions( 'casa_eventos/event_reactivated' ) ), 'reactivation lifecycle once' );
$GLOBALS['t_caps']['manage_options'] = false;
$GLOBALS['t_current_user'] = new WP_User( 21, array( 'casa_programador' ), array_keys( array_filter( $GLOBALS['t_caps'] ) ) );

t_group( 'operational/native route protection and administrator isolation' );
$revision = t_add_post( array( 'post_type' => 'revision', 'post_parent' => $id ) );
$cases = array(
	array( 'post.php', 'POST', array( 'post_ID' => (string) $id ) ),
	array( 'post.php', 'GET', array( 'post' => (string) $id, 'action' => 'trash' ) ),
	array( 'post-new.php', 'POST', array( 'post_type' => 'casa_evento' ) ),
	array( 'edit.php', 'GET', array( 'post_type' => 'casa_evento', 'action' => 'edit', 'post' => array( (string) $id ) ) ),
	array( 'edit.php', 'POST', array( 'post' => array( (string) $id ), 'post_type' => 'page', 'action' => 'edit' ) ),
	array( 'admin-ajax.php', 'POST', array( 'action' => 'inline-save', 'post_ID' => (string) $id, 'post_type' => 'page' ) ),
	array( 'admin-ajax.php', 'POST', array( 'action' => 'autosave', 'post_id' => (string) $id ) ),
	array( 'revision.php', 'GET', array( 'revision' => (string) $revision, 'action' => 'restore' ) ),
);
foreach ( $cases as list( $page_name, $method, $request ) ) {
	t_eq( array( 'deny' => true ), Admin\operational_native_route( $page_name, $method, $request ), 'native bypass denied: ' . $page_name );
}
t_eq( array( 'redirect' => Admin\operational_url( $id ) ), Admin\operational_native_route( 'post.php', 'GET', array( 'post' => (string) $id, 'action' => 'edit' ) ), 'native edit GET redirects' );
t_eq( array( 'redirect' => Admin\operational_url() ), Admin\operational_native_route( 'post-new.php', 'GET', array( 'post_type' => 'casa_evento' ) ), 'native create GET redirects' );
t_eq( Admin\operational_url(), Admin\operational_new_event_url( 'http://site.test/wp-admin/post-new.php?post_type=casa_evento', 'post-new.php?post_type=casa_evento' ), 'Programador Add New link targets operational form directly' );
t_eq( 'http://site.test/wp-admin/post-new.php?post_type=post', Admin\operational_new_event_url( 'http://site.test/wp-admin/post-new.php?post_type=post', 'post-new.php?post_type=post' ), 'other post types retain native URLs' );
t_eq( null, Admin\operational_native_route( 'edit.php', 'GET', array( 'post_type' => 'casa_evento' ) ), 'native list preserved' );
t_eq( null, Admin\operational_native_route( 'post.php', 'POST', array( 'post_ID' => (string) $image ) ), 'own attachment path unaffected' );
t_eq( null, Admin\operational_native_route( 'upload.php', 'GET', array() ), 'media unaffected' );
t_eq( array(), Admin\operational_bulk_actions( array( 'edit' => 'Edit' ) ), 'bulk UI absent for Programador' );
$row_actions = Admin\operational_row_actions( array(
	'edit' => '<a href="http://site.test/wp-admin/post.php?post=' . $id . '&action=edit">Edit</a>',
	'inline hide-if-no-js' => 'Quick', 'trash' => 'Trash', 'casa_ticket_types' => 'Tickets',
), get_post( $id ) );
t_ok( strpos( $row_actions['edit'], esc_url( Admin\operational_url( $id ) ) ) !== false, 'Programador Editar row action uses operational form' );
t_ok( ! isset( $row_actions['inline hide-if-no-js'] ) && ! isset( $row_actions['trash'] ) && 'Tickets' === $row_actions['casa_ticket_types'], 'native writers removed; ticket shortcut preserved' );
t_eq( Admin\operational_url( $id ), Admin\operational_edit_link( 'http://site.test/wp-admin/post.php?post=' . $id . '&action=edit', $id, 'display' ), 'Programador title edit link uses operational form' );
$GLOBALS['t_caps']['manage_options'] = true;
$GLOBALS['t_current_user'] = new WP_User( 1, array( 'administrator' ), array( 'manage_options' ) );
foreach ( $cases as list( $page_name, $method, $request ) ) { t_eq( null, Admin\operational_native_route( $page_name, $method, $request ), 'administrator native path retained' ); }
t_eq( 'http://site.test/wp-admin/post-new.php?post_type=casa_evento', Admin\operational_new_event_url( 'http://site.test/wp-admin/post-new.php?post_type=casa_evento', 'post-new.php?post_type=casa_evento' ), 'administrator native create URL retained' );
t_eq( array( 'edit' => 'Native' ), Admin\operational_row_actions( array( 'edit' => 'Native' ), get_post( $id ) ), 'administrator Editar unchanged' );
t_eq( 'native-link', Admin\operational_edit_link( 'native-link', $id, 'display' ), 'administrator title edit link unchanged' );

$GLOBALS['t_caps']['manage_options'] = false;
$GLOBALS['t_current_user'] = new WP_User( 21, array( 'casa_programador' ), array_keys( array_filter( $GLOBALS['t_caps'] ) ) );

t_eq( null, Admin\operational_native_route( 'edit.php', 'POST', array( 'post_type' => 'casa_evento', 'page' => Admin\OPERATIONAL_PAGE, 'event_id' => (string) $id ) ), 'operational POST reaches its nonce-protected handler' );
t_eq( array( 'deny' => true ), Admin\operational_native_route( 'edit.php', 'POST', array( 'post_type' => 'casa_evento', 'page' => 'another-page' ) ), 'other plugin-page parameter cannot enable native writes' );
t_group( 'operational/failure handling and recovery without duplicate create' );
$GLOBALS['t_persist_failure'] = 'post';
$r = Admin\operational_submit( t_op_request( 0, 'save_draft' ), 'POST' );
t_ok( is_wp_error( $r ), 'editorial DB failure not success' );
unset( $GLOBALS['t_persist_failure'] );
$block_uuid = static fn( $check, $object_id, $key ) => '_casa_uuid' === $key ? false : $check;
add_filter( 'update_post_metadata', $block_uuid, 5, 3 );
$before_count = count( $GLOBALS['t_posts'] );
$r = Admin\operational_submit( t_op_request( 0, 'save_draft' ), 'POST' );
remove_filter( 'update_post_metadata', $block_uuid, 5 );
t_ok( is_wp_error( $r ) && $r->get_error_data()['partial'], 'sync partial failure propagated' );
t_eq( $before_count + 1, count( $GLOBALS['t_posts'] ), 'partial create accounted once' );
$partial_id = $r->get_error_data()['id'];
$html = t_op_html( Admin\operational_snapshot( 0 ), $r );
t_ok( false === strpos( $html, '<form' ), 'partial failure offers no create resubmission' );
t_ok( false !== strpos( $html, 'event_id=' . $partial_id ), 'recovery uses persisted ID' );
t_ok( false === strpos( $html, 'notice-success' ), 'partial failure no success notice' );
$throw = static function () { throw new RuntimeException( 'Do not leak internal exception <secret>' ); };
add_action( 'save_post', $throw, 1 );
$r = Admin\operational_submit( t_op_request( 0, 'save_draft' ), 'POST' );
remove_filter( 'save_post', $throw, 1 );
t_eq( 'casa_eventos_ui_exception', $r->get_error_code(), 'exception becomes uncertain persistence error' );
$html = t_op_html( Admin\operational_snapshot( 0 ), $r );
t_ok( false === strpos( $html, '<form' ) && false === strpos( $html, '<secret>' ), 'unknown exception stops blind retry and hides details' );

t_group( 'operational/escaping, receipt integrity and warnings' );
$snapshot = Admin\operational_snapshot( $id );
get_post( $id )->post_content = '';
$snapshot = Admin\operational_snapshot( $id );
$error = new WP_Error( 'casa_eventos_invalid_event', '<script>bad</script>', array( 'errors' => array( 'title_required' => '<img onerror=x>' ) ) );
$html = t_op_html( $snapshot, $error, array( 'fields' => array( 'title' => '"><script>x</script>', 'excerpt' => '</textarea><script>x</script>' ) ) );
t_ok( false === strpos( $html, '<script>' ), 'errors and input values escaped' );
t_ok( false !== strpos( $html, '&lt;script&gt;' ), 'escaped inputs retained visibly' );
$success = array( 'id' => $id, 'intent' => 'update', 'warnings' => array( 'test' => '<script>warning</script>' ) );
$receipt = Admin\operational_receipt( $success );
t_eq( $id, Admin\operational_read_receipt( $receipt, $id )['id'], 'authentic receipt read' );
t_eq( null, Admin\operational_read_receipt( $receipt . 'x', $id ), 'forged receipt rejected' );
t_eq( null, Admin\operational_read_receipt( $receipt, $other ), 'cross-event receipt rejected' );
$GLOBALS['t_session'] = 'other';
t_eq( null, Admin\operational_read_receipt( $receipt, $id ), 'cross-session receipt rejected' ); unset( $GLOBALS['t_session'] );
ob_start(); Admin\operational_result_notice( $success ); $html = ob_get_clean();
t_ok( false !== strpos( $html, 'notice-success' ) && false !== strpos( $html, 'notice-warning' ), 'success and warnings separately visible' );
t_ok( false === strpos( $html, '<script>' ), 'warnings escaped' );


t_group( 'operational/dispatch guards and signed recovery PRG' );
$GLOBALS['t_current_user'] = new WP_User( 21, array( 'casa_programador' ), array_keys( array_filter( $GLOBALS['t_caps'] ) ) );
$old_get = $_GET; $old_post = $_POST; $old_server = $_SERVER;
$old_pagenow = $GLOBALS['pagenow'] ?? null;
$_GET = array( 'post' => (string) $id );
$_POST = array( 'post_ID' => (string) $id, 'post_type' => 'page', 'action' => 'editpost' );
$_SERVER['REQUEST_METHOD'] = 'POST';
$GLOBALS['pagenow'] = 'post.php';
$before = serialize( array( $GLOBALS['t_posts'], $GLOBALS['t_meta'], $GLOBALS['t_terms'] ) );
$denied = false;
try { Admin\guard_operational_native_paths(); } catch ( RuntimeException $error ) { $denied = true; }
t_ok( $denied, 'real registered admin guard refuses native Event POST before mutation' );
t_eq( $before, serialize( array( $GLOBALS['t_posts'], $GLOBALS['t_meta'], $GLOBALS['t_terms'] ) ), 'native guard causes no writes' );
t_eq( array( 'deny' => true ), Admin\operational_native_route( 'admin-ajax.php', 'POST', array( 'action' => 'heartbeat', 'data' => array( 'wp_autosave' => array( 'post_id' => (string) $id ) ) ) ), 'nested heartbeat autosave refused' );
t_eq( null, Admin\operational_native_route( 'admin-ajax.php', 'POST', array( 'action' => 'heartbeat', 'data' => array( 'wp_autosave' => array( 'post_id' => (string) $image ) ) ) ), 'unrelated heartbeat upload unaffected' );
$_GET = array( 'event_id' => (string) $id );
$_POST = array( 'event_id' => (string) $other );
$denied = false;
try { Admin\load_operational_page(); } catch ( RuntimeException $error ) { $denied = true; }
t_ok( $denied, 'crossed URL and form IDs refused by page load' );
$_GET = $old_get; $_POST = $old_post; $_SERVER = $old_server;
$GLOBALS['pagenow'] = $old_pagenow;
$partial = new WP_Error( 'casa_eventos_sync_failed', 'Incomplete', array( 'id' => $partial_id, 'partial' => true ) );
$redirect = Admin\operational_result_redirect( $partial, 0 );
parse_str( parse_url( $redirect, PHP_URL_QUERY ), $query );
t_eq( (string) $partial_id, $query['event_id'], 'partial create PRG targets created ID, not zero' );
$decoded = Admin\operational_read_receipt( $query['receipt'], $partial_id );
t_ok( is_wp_error( $decoded ) && $decoded->get_error_data()['partial'], 'partial receipt remains a failure' );
t_ok( false === strpos( t_op_html( Admin\operational_snapshot( $partial_id ), $decoded ), '<form' ), 'PRG recovery has no mutation form' );
$unknown = new WP_Error( 'casa_eventos_ui_exception', 'Review list', array( 'partial' => true, 'id' => 0 ) );
$redirect = Admin\operational_result_redirect( $unknown, 0 );
parse_str( parse_url( $redirect, PHP_URL_QUERY ), $query );
t_ok( is_wp_error( Admin\operational_read_receipt( $query['receipt'], 0 ) ), 'unknown create exception survives read-only PRG' );
t_eq( null, Admin\operational_result_redirect( $error, $id ), 'validation failure can retain editable values' );
$result = array( 'id' => $id, 'intent' => 'update', 'warnings' => array() );
$redirect = Admin\operational_result_redirect( $result, $id );
t_ok( false !== strpos( $redirect, 'receipt=' ) && false !== strpos( $redirect, 'event_id=' . $id ), 'success uses PRG edit URL' );
$record = array( 'id' => $id, 'intent' => 'update', 'warnings' => array(), 'time' => time() - 600 );
$payload = base64_encode( wp_json_encode( $record ) );
$old_receipt = $payload . '.' . hash_hmac( 'sha256', $payload, wp_salt( 'nonce' ) . get_current_user_id() . wp_get_session_token() );
t_eq( null, Admin\operational_read_receipt( $old_receipt, $id ), 'expired receipt cannot report historical success' );

$old_get = $_GET;
$_GET['receipt'] = 'forged';
t_ok( false === strpos( t_op_html( Admin\operational_snapshot( 0 ) ), '<form' ), 'invalid outcome cannot silently become a new create form' );
$_GET = $old_get;
t_ok( 1 === preg_match( '/^[A-Za-z0-9_-]+\.[a-f0-9]+$/D', Admin\operational_receipt( $result ) ), 'receipt is URL-safe without ambiguous base64 plus characters' );
t_group( 'operational/normal full form omits unchanged values' );
$snapshot = Admin\operational_snapshot( $id );
$raw = array();
foreach ( $snapshot['fields'] as $key => $value ) {
	if ( 'status' !== $key ) { $raw[ $key ] = Admin\operational_control_value( $key, $value ); }
}
t_eq( array(), Admin\operational_fields( $raw, $snapshot ), 'whole unchanged form becomes omission, including content, seconds and flags' );
t_eq( array( 'capacity' => '' ), Admin\operational_fields( array( 'capacity' => '' ), $snapshot ), 'blank capacity retains reset intent' );
$pdf = t_add_post( array( 'post_type' => 'attachment', 'post_mime_type' => 'application/pdf' ) );
t_op_rejected( t_op_request( $id, 'save_draft', array( 'poster_id' => (string) $pdf ) ), 'non-image rejected by adapter' );
$GLOBALS['t_missing_terms'] = array( 999 );
t_op_rejected( t_op_request( $id, 'save_draft', array( 'category_ids' => '999' ) ), 'nonexistent category rejected by adapter' );
unset( $GLOBALS['t_missing_terms'] );
get_post( $id )->post_status = 'publish';
$r = Admin\operational_submit( t_op_request( $id, 'update', array( 'featured' => '1', 'listed' => '0' ) ), 'POST' );
t_ok( ! is_wp_error( $r ) && isset( $r['warnings']['featured_not_listed'] ), 'real Core warning reaches UI transport' );
$snapshot = Admin\operational_snapshot( $id );
$r = t_op_request( $id, 'return_to_draft' );
$confirm = Admin\operational_submit( $r, 'POST' );
$html = t_op_html( $snapshot, $confirm );
t_ok( false !== strpos( $html, 'name="confirmed"' ) && false === strpos( $html, 'name="fields[title]"' ), 'confirmation is server-rendered without editorial inputs' );
get_post( $id )->post_title .= 'Concurrent change';
$r['confirmed'] = '1';
t_op_rejected( $r, 'concurrent change during confirmation', 'POST', 'casa_eventos_ui_stale' );
foreach ( array( 'editor', 'author', 'contributor' ) as $role ) {
	$GLOBALS['t_current_user'] = new WP_User( 22, array( $role ), array( 'read', 'edit_posts', 'upload_files' ) );
	$save_caps = $GLOBALS['t_caps'];
	$GLOBALS['t_caps'] = array( 'read' => true, 'edit_posts' => true, 'upload_files' => true );
	t_op_rejected( array( 'event_id' => '0' ), $role . ' generic post capability cannot create Event' );
	t_ok( false === strpos( t_op_html( $snapshot ), 'name="fields[title]"' ), $role . ' unauthorized render hides content' );
	$GLOBALS['t_caps'] = $save_caps;
}

t_group( 'operational/correction REST enforcement hook' );
Core\register_post_type();
$GLOBALS['t_current_user'] = new WP_User( 21, array( 'casa_programador' ), array_keys( array_filter( $GLOBALS['t_caps'] ) ) );
$GLOBALS['t_is_admin'] = false;
$plugin_source = file_get_contents( dirname( __DIR__ ) . '/casa-eventos.php' );
$guard_load = strpos( $plugin_source, "'inc/admin/operational.php'" );
$admin_only = strpos( $plugin_source, 'if ( is_admin() )' );
t_ok( false !== $guard_load && false !== $admin_only && $guard_load < $admin_only, 'REST guard loads outside is_admin' );
// Exercise registered enforcement, not only the helper. The callback counter
// models short-circuiting; this does not replace native WordPress dispatch QA.
$rest_calls = 0;
$dispatch = static function ( $method, $route ) use ( &$rest_calls ) {
	$response = apply_filters( 'rest_request_before_callbacks', null, array(), new WP_REST_Request( array(), $method, $route ) );
	if ( null === $response ) { ++$rest_calls; return array( 'callback' => true ); }
	return $response;
};
$mutations = array(
	array( 'POST', '/wp/v2/casa_evento' ),
	array( 'POST', '/wp/v2/casa_evento/' ),
	array( 'POST', '/wp/v2/casa_evento/12' ),
	array( 'PUT', '/wp/v2/casa_evento/12' ),
	array( 'PATCH', '/wp/v2/casa_evento/12' ),
	array( 'DELETE', '/wp/v2/casa_evento/12' ),
	array( 'POST', '/wp/v2/casa_evento/12/autosaves' ),
	array( 'DELETE', '/wp/v2/casa_evento/12/revisions/7' ),
	array( 'POST', '/wp/v2/casa_evento/12/revisions/7' ),
);
$before = serialize( array( $GLOBALS['t_posts'], $GLOBALS['t_meta'], $GLOBALS['t_terms'] ) );
foreach ( $mutations as list( $method, $route ) ) {
	$result = $dispatch( $method, $route );
	t_ok( is_wp_error( $result ), 'REST hook rejects ' . $method . ' ' . $route );
	t_eq( 403, $result->get_error_data()['status'], 'REST denial HTTP status' );
}
t_eq( 0, $rest_calls, 'no denied REST request reaches endpoint callback' );
t_eq( $before, serialize( array( $GLOBALS['t_posts'], $GLOBALS['t_meta'], $GLOBALS['t_terms'] ) ), 'REST guard does not mutate state' );
foreach ( array( 'GET', 'HEAD', 'OPTIONS' ) as $method ) {
	foreach ( array( '/wp/v2/casa_evento', '/wp/v2/casa_evento/12', '/wp/v2/casa_evento/12/revisions' ) as $route ) {
		t_eq( array( 'callback' => true ), $dispatch( $method, $route ), 'read retains native authorization: ' . $method . ' ' . $route );
	}
}
foreach ( array( '/wp/v2/posts/12', '/wp/v2/pages', '/wp/v2/media/12', '/wp/v2/casa_evento_other/12', '/other/v1/casa_evento' ) as $route ) {
	t_eq( array( 'callback' => true ), $dispatch( 'POST', $route ), 'unrelated endpoint unaffected: ' . $route );
}
$upstream = new WP_Error( 'existing_denial', 'Existing error' );
t_eq( $upstream, apply_filters( 'rest_request_before_callbacks', $upstream, array(), new WP_REST_Request( array(), 'POST', '/wp/v2/casa_evento' ) ), 'upstream REST error preserved' );
foreach ( array( '/wp/v2/casa_evento', '/wp/v2/casa_evento/12/autosaves' ) as $route ) {
	t_ok( is_wp_error( $dispatch( 'POST', $route ) ), 'batch subrequest receives same guard: ' . $route );
}
$registered = $GLOBALS['t_registered']['post_type'][ Core\POST_TYPE ];
$GLOBALS['t_registered']['post_type'][ Core\POST_TYPE ]['rest_base'] = 'events';
$GLOBALS['t_registered']['post_type'][ Core\POST_TYPE ]['rest_namespace'] = 'custom/v3';
t_ok( is_wp_error( $dispatch( 'POST', '/custom/v3/events/12/autosaves' ) ), 'registered REST namespace and base honored' );
$GLOBALS['t_registered']['post_type'][ Core\POST_TYPE ] = $registered;
// R1: use WordPress's anchored, case-insensitive route matching before the
// registered enforcement hook. This models dispatch, not a real REST server.
$r1_routes = array(
	array( '/wp/v2/casa_evento', '/WP/V2/CASA_EVENTO', 'POST' ),
	array( '/wp/v2/casa_evento/(?P<id>[\d]+)', '/wP/v2/Casa_Evento/12', 'PATCH' ),
	array( '/wp/v2/casa_evento/(?P<id>[\d]+)', '/WP/v2/casa_EVENTO/12', 'DELETE' ),
	array( '/wp/v2/casa_evento/(?P<parent>[\d]+)/autosaves', '/wp/V2/CASA_EVENTO/12/AutoSaves', 'POST' ),
	array( '/wp/v2/casa_evento/(?P<parent>[\d]+)/autosaves/(?P<id>[\d]+)', '/WP/V2/Casa_Evento/12/AUTOSAVES/7', 'POST' ),
	array( '/wp/v2/casa_evento/(?P<parent>[\d]+)/revisions', '/Wp/v2/CASA_EVENTO/12/Revisions', 'POST' ),
	array( '/wp/v2/casa_evento/(?P<parent>[\d]+)/revisions/(?P<id>[\d]+)', '/WP/V2/Casa_Evento/12/REVISIONS/7', 'DELETE' ),
);
foreach ( $r1_routes as list( $pattern, $route, $method ) ) {
	t_eq( 1, preg_match( '@^' . $pattern . '$@i', $route ), 'R1 native-compatible route match: ' . $route );
	$calls_before = $rest_calls;
	$result = $dispatch( $method, $route );
	t_ok( is_wp_error( $result ) && 403 === $result->get_error_data()['status'], 'R1 mixed-case mutation denied: ' . $route );
	t_eq( $calls_before, $rest_calls, 'R1 denied callback never runs: ' . $route );
	foreach ( array( 'GET', 'HEAD', 'OPTIONS' ) as $read_method ) {
		t_eq( array( 'callback' => true ), $dispatch( $read_method, $route ), 'R1 mixed-case read preserved: ' . $route );
	}
}
// Native batch dispatch applies the same hook to each matched subrequest.
// Exercise those paths separately; no claim of executing a real batch server.
foreach ( array_slice( $r1_routes, 0, 4 ) as list( $pattern, $route, $method ) ) {
	t_eq( 1, preg_match( '@^' . $pattern . '$@i', $route ), 'R1 batch subrequest matches native route' );
	t_ok( is_wp_error( $dispatch( $method, $route ) ), 'R1 mixed-case batch subrequest denied: ' . $route );
}
foreach ( array( '/WP/V2/POSTS/12', '/wP/v2/Media/12', '/WP/V2/CASA_EVENTO_OTHER/12', '/OTHER/v1/CASA_EVENTO', '/wp/v2x/CASA_EVENTO' ) as $route ) {
	t_eq( 0, preg_match( '@^/wp/v2/casa_evento(?:/|$)@i', $route ), 'R1 negative control is outside Event collection' );
	t_eq( array( 'callback' => true ), $dispatch( 'POST', $route ), 'R1 unrelated mixed-case route preserved: ' . $route );
}
$registered = $GLOBALS['t_registered']['post_type'][ Core\POST_TYPE ];
$GLOBALS['t_registered']['post_type'][ Core\POST_TYPE ]['rest_base'] = 'events';
$GLOBALS['t_registered']['post_type'][ Core\POST_TYPE ]['rest_namespace'] = 'custom/v3';
t_eq( 1, preg_match( '@^/custom/v3/events/(?P<parent>[\d]+)/autosaves$@i', '/CUSTOM/V3/Events/12/AutoSaves' ), 'R1 custom native-compatible route match' );
t_ok( is_wp_error( $dispatch( 'POST', '/CUSTOM/V3/Events/12/AutoSaves' ) ), 'R1 custom mixed-case namespace and base denied' );
$GLOBALS['t_registered']['post_type'][ Core\POST_TYPE ] = $registered;
$GLOBALS['t_current_user'] = new WP_User( 1, array( 'administrator', 'casa_programador' ), array( 'manage_options' ) );
foreach ( $mutations as list( $method, $route ) ) {
	t_eq( array( 'callback' => true ), $dispatch( $method, $route ), 'administrator REST preserved: ' . $method . ' ' . $route );
}
foreach ( $r1_routes as list( $pattern, $route, $method ) ) {
	t_eq( array( 'callback' => true ), $dispatch( $method, $route ), 'R1 administrator mixed-case operation preserved: ' . $route );
}
$GLOBALS['t_current_user'] = new WP_User( 21, array( 'casa_programador' ), array_keys( array_filter( $GLOBALS['t_caps'] ) ) );
$GLOBALS['t_is_admin'] = true;


t_group( 'operational/correction native thumbnail dispatch' );
foreach ( array( (string) $id, $id, '0' . $id, $id . 'suffix', '+' . $id, ' ' . $id ) as $native_id ) {
	foreach ( array( (string) $image, '-1' ) as $thumbnail ) {
		t_eq( array( 'deny' => true ), Admin\operational_native_route( 'admin-ajax.php', 'POST', array( 'action' => 'set-post-thumbnail', 'post_id' => $native_id, 'thumbnail_id' => $thumbnail, 'post_type' => 'page' ) ), 'native thumbnail target recognized using actual native ID cast' );
	}
}
$guard_before = serialize( array( $GLOBALS['t_posts'], $GLOBALS['t_meta'], $GLOBALS['t_terms'] ) );
$old_get = $_GET; $old_post = $_POST; $old_server = $_SERVER; $old_pagenow = $GLOBALS['pagenow'] ?? null;
$GLOBALS['pagenow'] = 'admin-ajax.php'; $_SERVER['REQUEST_METHOD'] = 'POST';
foreach ( array( (string) $image, '-1' ) as $thumbnail ) {
	foreach ( array( false, true ) as $split ) {
		$_GET = $split ? array( 'action' => 'set-post-thumbnail' ) : array();
		$_POST = array( 'post_id' => (string) $id, 'thumbnail_id' => $thumbnail );
		if ( ! $split ) { $_POST['action'] = 'set-post-thumbnail'; }
		$denied = false;
		try { do_action( 'admin_init' ); } catch ( RuntimeException $error ) { $denied = true; }
		t_ok( $denied, 'installed admin_init guard rejects thumbnail assignment/removal' );
	}
}
t_eq( $guard_before, serialize( array( $GLOBALS['t_posts'], $GLOBALS['t_meta'], $GLOBALS['t_terms'] ) ), 'thumbnail refusals perform no writes' );
foreach ( array( 'set-post-thumbnail', 'query-attachments', 'save-attachment', 'upload-attachment' ) as $action ) {
	t_eq( null, Admin\operational_native_route( 'admin-ajax.php', 'POST', array( 'action' => $action, 'post_id' => (string) $image ) ), 'legitimate attachment operation preserved: ' . $action );
}
$GLOBALS['t_current_user'] = new WP_User( 1, array( 'administrator' ), array( 'manage_options' ) );
foreach ( array( (string) $image, '-1' ) as $thumbnail ) {
	t_eq( null, Admin\operational_native_route( 'admin-ajax.php', 'POST', array( 'action' => 'set-post-thumbnail', 'post_id' => (string) $id, 'thumbnail_id' => $thumbnail ) ), 'administrator thumbnail operations preserved' );
}
$_GET = $old_get; $_POST = $old_post; $_SERVER = $old_server; $GLOBALS['pagenow'] = $old_pagenow;
$GLOBALS['t_current_user'] = new WP_User( 21, array( 'casa_programador' ), array_keys( array_filter( $GLOBALS['t_caps'] ) ) );


t_group( 'operational/correction related legacy media paths' );
foreach ( array( (string) $id, $id . 'suffix' ) as $target ) {
	t_eq( array( 'deny' => true ), Admin\operational_native_route( 'media-upload.php', 'POST', array( 'attachments' => array( $target => array( 'post_title' => 'Bypass' ) ) ) ), 'legacy media cannot write an Event through forged attachment key' );
}
t_eq( null, Admin\operational_native_route( 'media-upload.php', 'GET', array( 'post_id' => (string) $id ) ), 'legacy picker read for Event preserved' );
t_eq( null, Admin\operational_native_route( 'media-upload.php', 'POST', array( 'attachments' => array( $image => array( 'post_title' => 'Own image' ) ) ) ), 'actual legacy attachment edit preserved' );
$old_get = $_GET; $old_post = $_POST; $old_server = $_SERVER; $old_pagenow = $GLOBALS['pagenow'] ?? null;
$_GET = array(); $_POST = array( 'attachments' => array( $id => array( 'post_content' => 'Bypass' ) ) );
$_SERVER['REQUEST_METHOD'] = 'POST'; $GLOBALS['pagenow'] = 'media-upload.php';
$denied = false;
try { do_action( 'admin_init' ); } catch ( RuntimeException $error ) { $denied = true; }
t_ok( $denied, 'registered admin_init guard refuses legacy Event write before uploader' );
$GLOBALS['t_current_user'] = new WP_User( 1, array( 'administrator' ), array( 'manage_options' ) );
t_eq( null, Admin\operational_native_route( 'media-upload.php', 'POST', $_POST ), 'administrator legacy paths retained' );
$_GET = $old_get; $_POST = $old_post; $_SERVER = $old_server; $GLOBALS['pagenow'] = $old_pagenow;
$GLOBALS['t_current_user'] = new WP_User( 21, array( 'casa_programador' ), array_keys( array_filter( $GLOBALS['t_caps'] ) ) );

t_group( 'operational/correction retained description retry' );
$retry_id = Core\save_event( 0, array( 'title' => 'Retry', 'content' => '<p>Original</p>' ), 'save_draft' )['id'];
foreach ( array( '<p>Changed description</p>', '' ) as $content ) {
	Core\save_event( $retry_id, array( 'content' => '<p>Original</p>', 'start' => '', 'category_ids' => array() ), 'save_draft' );
	$request = t_op_request( $retry_id, 'publish', array( 'content' => $content ) );
	$error = Admin\operational_submit( $request, 'POST' );
	t_ok( is_wp_error( $error ), 'incomplete publication rejects changed description/clear' );
	$html = t_op_html( Admin\operational_snapshot( $retry_id ), $error, $request );
	t_ok( false !== strpos( $html, 'data-content-pending="1"' ), 'retained description/clear marked pending even with fresh editor' );
	$fields = t_op_complete(); $fields['content'] = $content;
	$result = Admin\operational_submit( t_op_request( $retry_id, 'publish', $fields ), 'POST' );
	t_ok( ! is_wp_error( $result ), 'corrected publication retry succeeds' );
	t_eq( $content, get_post( $retry_id )->post_content, 'retained description/clear persisted' );
	Core\save_event( $retry_id, array(), 'return_to_draft' );
}
// Return to an incomplete draft to reproduce validation, not a successful save.
Core\save_event( $retry_id, array( 'start' => '', 'category_ids' => array() ), 'save_draft' );
foreach ( array( array(), array( 'content' => '' ) ) as $fields ) {
	$request = t_op_request( $retry_id, 'publish', $fields );
	$error = Admin\operational_submit( $request, 'POST' );
	t_ok( is_wp_error( $error ), 'omitted/identical description publication still fails validation' );
	$html = t_op_html( Admin\operational_snapshot( $retry_id ), $error, $request );
	t_ok( false !== strpos( $html, 'data-content-pending="0"' ), 'omitted/identical description not marked pending' );
}
$block = '<!-- wp:paragraph --><p>Protected</p><!-- /wp:paragraph -->';
get_post( $retry_id )->post_content = $block;
$request = t_op_request( $retry_id, 'publish', array( 'content' => '' ) );
$error = Admin\operational_submit( $request, 'POST' );
$html = t_op_html( Admin\operational_snapshot( $retry_id ), $error, $request );
t_ok( false !== strpos( $html, 'data-content-pending="0"' ) && false === strpos( $html, 'name="fields[content]"' ), 'protected blocks remain absent from editable retry' );
t_eq( $block, get_post( $retry_id )->post_content, 'failed protected clear preserves blocks exactly' );


$GLOBALS['t_caps'] = $e32_prior_caps;
if ( null === $e32_prior_user ) { unset( $GLOBALS['t_current_user'] ); } else { $GLOBALS['t_current_user'] = $e32_prior_user; }
$GLOBALS['t_is_admin'] = $e32_prior_admin;
t_reset_store();
