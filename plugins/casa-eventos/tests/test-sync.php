<?php
/**
 * Sync (derived fields, defaults, lifecycle actions) and the read model.
 *
 * @package CasaEventos
 */

use CasaEventos\Core\Event;
use function CasaEventos\Core\sync_event;

t_reset_store();

t_group( 'sync/auto-draft' );
$auto = t_add_post( array( 'post_status' => 'auto-draft' ) );
t_eq( null, sync_event( $auto ), 'auto-drafts are skipped' );
t_ok( ! metadata_exists( 'post', $auto, '_casa_uuid' ), 'no UUID for an auto-draft' );
$not_event = t_add_post( array( 'post_type' => 'post' ) );
t_eq( null, sync_event( $not_event ), 'other post types are skipped' );

t_group( 'sync/first save' );
$id = t_add_post( array( 'post_status' => 'draft', 'post_title' => 'Lucernaria' ) );
update_post_meta( $id, '_casa_start', '2026-09-05T21:00' );
$changes = sync_event( $id, gmmktime( 12, 0, 0, 9, 1, 2026 ) );
t_ok( 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', get_post_meta( $id, '_casa_uuid' ) ), 'UUID v4 generated' );
t_eq( 'America/Argentina/Buenos_Aires', get_post_meta( $id, '_casa_timezone' ), 'timezone captured' );
t_eq( 'active', $GLOBALS['t_meta'][ $id ]['_casa_status'], 'status materialized' );
t_eq( '1', $GLOBALS['t_meta'][ $id ]['_casa_listed'], 'listed materialized' );
t_eq( '0', $GLOBALS['t_meta'][ $id ]['_casa_featured'], 'featured materialized' );
t_eq( '100', $GLOBALS['t_meta'][ $id ]['_casa_capacity'], 'capacity materialized as 100' );
t_eq( '2026-09-05 21:00:00', get_post_meta( $id, '_casa_start' ), 'start normalized on write' );
t_eq( '2026-09-06 00:00:00', get_post_meta( $id, '_casa_start_gmt' ), 'start GMT derived' );
t_eq( '', get_post_meta( $id, '_casa_end_gmt' ), 'no declared end' );
t_ok( metadata_exists( 'post', $id, '_casa_end_gmt' ), 'empty end GMT still stored' );
t_eq( '2026-09-06 03:00:00', get_post_meta( $id, '_casa_ends_gmt' ), 'effective end = start + 3h' );
t_eq( '', get_post_meta( $id, '_casa_cancelled_gmt' ), 'not cancelled' );
t_eq( 1, count( t_actions( 'casa_eventos/schedule_changed' ) ), 'schedule_changed fired for the first date' );
t_eq( 1, count( t_actions( 'casa_eventos/event_synced' ) ), 'event_synced fired' );
t_ok( isset( $changes['uuid'], $changes['timezone'], $changes['schedule'] ), 'changes reported' );

t_group( 'sync/idempotent' );
$uuid = get_post_meta( $id, '_casa_uuid' );
sync_event( $id );
t_eq( 1, count( t_actions( 'casa_eventos/schedule_changed' ) ), 'no schedule_changed without a change' );
t_eq( $uuid, get_post_meta( $id, '_casa_uuid' ), 'UUID stable across saves' );

t_group( 'sync/schedule change' );
update_post_meta( $id, '_casa_start', '2026-09-12 21:00:00' );
update_post_meta( $id, '_casa_end', '2026-09-13 01:30:00' );
sync_event( $id );
$fired = t_actions( 'casa_eventos/schedule_changed' );
t_eq( 2, count( $fired ), 'schedule_changed fired on the new date' );
t_eq( '2026-09-06 00:00:00', $fired[1][1][1]['start_gmt'], 'old start passed' );
t_eq( '2026-09-13 00:00:00', $fired[1][1][2]['start_gmt'], 'new start passed' );
t_eq( '2026-09-13 04:30:00', get_post_meta( $id, '_casa_ends_gmt' ), 'effective end = declared end' );

t_group( 'sync/defaults are materialized, not resolved later' );
add_filter( 'casa_eventos/default_capacity', static fn() => 150 );
sync_event( $id );
t_eq( '100', get_post_meta( $id, '_casa_capacity' ), 'an existing event keeps its capacity when the default changes' );
$new = t_add_post( array( 'post_status' => 'draft' ) );
sync_event( $new );
t_eq( '150', get_post_meta( $new, '_casa_capacity' ), 'a new event gets the new default' );
remove_all_filters( 'casa_eventos/default_capacity' );

t_group( 'sync/timezone is kept' );
$GLOBALS['t_options']['timezone_string'] = 'Europe/Madrid';
sync_event( $id );
t_eq( 'America/Argentina/Buenos_Aires', get_post_meta( $id, '_casa_timezone' ), 'site timezone change does not reinterpret an event' );
t_eq( '2026-09-13 00:00:00', get_post_meta( $id, '_casa_start_gmt' ), 'derived GMT unchanged' );
$GLOBALS['t_options']['timezone_string'] = '';
$offset_site = t_add_post( array( 'post_status' => 'draft' ) );
sync_event( $offset_site );
t_eq( 'America/Argentina/Buenos_Aires', get_post_meta( $offset_site, '_casa_timezone' ), 'offset site: venue timezone' );
$GLOBALS['t_options']['timezone_string'] = 'America/Argentina/Buenos_Aires';

t_group( 'sync/cancellation lifecycle' );
update_post_meta( $id, '_casa_status', 'cancelled' );
sync_event( $id, gmmktime( 15, 0, 0, 9, 10, 2026 ) );
t_eq( '2026-09-10 15:00:00', get_post_meta( $id, '_casa_cancelled_gmt' ), 'cancellation instant recorded' );
t_eq( 1, count( t_actions( 'casa_eventos/event_cancelled' ) ), 'event_cancelled fired' );
sync_event( $id );
t_eq( 1, count( t_actions( 'casa_eventos/event_cancelled' ) ), 'not fired twice' );
update_post_meta( $id, '_casa_status', 'active' );
sync_event( $id );
t_eq( '', get_post_meta( $id, '_casa_cancelled_gmt' ), 'reactivation clears the instant' );
$re = t_actions( 'casa_eventos/event_reactivated' );
t_eq( 1, count( $re ), 'event_reactivated fired' );
t_eq( '2026-09-10 15:00:00', $re[0][1][1], 'with the previous cancellation instant' );

t_group( 'sync/no date' );
$nodate = t_add_post( array( 'post_status' => 'draft' ) );
sync_event( $nodate );
t_eq( '', get_post_meta( $nodate, '_casa_start_gmt' ), 'no start GMT' );
t_ok( metadata_exists( 'post', $nodate, '_casa_start_gmt' ), 'but stored, so admin ordering keeps the event' );

t_group( 'event/read model' );
$pub = t_add_post( array( 'post_status' => 'publish', 'post_title' => 'Cine Club', 'post_excerpt' => 'Una bajada.' ) );
update_post_meta( $pub, '_casa_start', '2026-09-05 21:00:00' );
update_post_meta( $pub, '_casa_access_mode', 'whatsapp' );
update_post_meta( $pub, '_casa_external_url', 'https://www.passline.com/x' );
update_post_meta( $pub, '_casa_entry_kind', 'gorra' );
t_set_terms( $pub, array( 9 ) );
$GLOBALS['t_term_meta'][9]['_casa_color'] = 'yellow';
sync_event( $pub );
$e = Event::get( $pub );
t_ok( $e instanceof Event, 'Event::get' );
t_eq( null, Event::get( $not_event ), 'not an event' );
t_eq( 'Una bajada.', $e->subtitle(), 'subtitle is the raw excerpt' );
t_eq( '2026-09', $e->month(), 'month of the local start' );
t_eq( '2026-09-05 21:00', $e->start()->format( 'Y-m-d H:i' ), 'start in the event timezone' );
t_eq( '-03:00', $e->start()->format( 'P' ), 'with the Buenos Aires offset' );
t_eq( '2026-09-06 00:00', $e->effective_end()->format( 'Y-m-d H:i' ), 'effective end local' );
t_eq( 100, $e->capacity(), 'capacity' );
t_eq( '', $e->external_url(), 'external URL ignored outside external mode' );
t_eq( 'gorra', $e->entry_kind(), 'entry kind' );
t_eq( 'yellow', $e->category_color(), 'color comes from the category' );
t_eq( '2026-09-05 23:00:00', $e->sales_close_gmt(), 'default cutoff (GMT)' );
t_ok( $e->sales_close_is_default(), 'default cutoff flagged' );
t_eq( 'active', $e->effective_state( gmmktime( 23, 0, 0, 9, 5, 2026 ) ), 'active while it runs' );
t_ok( $e->is_actionable( gmmktime( 23, 0, 0, 9, 5, 2026 ) ), 'actionable while active' );
t_eq( 'finished', $e->effective_state( gmmktime( 3, 0, 0, 9, 6, 2026 ) ), 'finished after the effective end' );
t_ok( ! $e->is_actionable( gmmktime( 3, 0, 0, 9, 6, 2026 ) ), 'historical events have no action' );
t_ok( $e->is_before_sales_close( gmmktime( 22, 59, 0, 9, 5, 2026 ) ), 'before the cutoff' );
t_ok( ! $e->is_before_sales_close( gmmktime( 23, 0, 0, 9, 5, 2026 ) ), 'at the cutoff sales are closed' );
update_post_meta( $pub, '_casa_status', 'paused' );
t_ok( ! $e->is_actionable( gmmktime( 23, 0, 0, 9, 5, 2026 ) ), 'paused removes the action (WhatsApp included)' );
t_eq( array(), $e->validation()['errors'], 'stored data is publishable' );
t_eq( 'La Casa del Árbol', $e->venue_name(), 'venue from settings' );
t_eq( 'Av. Córdoba 5217', $e->venue_address(), 'venue address from settings' );
