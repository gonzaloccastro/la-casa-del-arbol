<?php
/**
 * Public call-to-action decision (E2.1): cta_decision() and Event::cta().
 *
 * Closed rules: only an actionable (active) event has an action; external
 * needs a usable URL; whatsapp is allowed (the site resolves the target);
 * tickets are unavailable until commerce exists, and only own ticket sales
 * use the sales cutoff.
 *
 * @package CasaEventos
 */

use CasaEventos\Core\Event;
use function CasaEventos\Core\cta_decision;
use function CasaEventos\Core\sync_event;

t_group( 'cta/decision matrix' );
$url = 'https://www.passline.com/qa';
foreach ( array( 'paused', 'cancelled', 'finished', 'draft', 'scheduled' ) as $state ) {
	foreach ( array( 'tickets', 'whatsapp', 'external' ) as $mode ) {
		$d = cta_decision( $state, $mode, $url, true, true );
		t_eq( array( false, $state, '' ), array( $d['available'], $d['reason'], $d['url'] ), "$state × $mode → no action, reason = state, no target" );
	}
}
$d = cta_decision( 'active', 'whatsapp', '', false, false );
t_eq( array( true, 'available', 'whatsapp', '' ), array( $d['available'], $d['reason'], $d['mode'], $d['url'] ), 'active whatsapp → available, target resolved by the site (no URL here)' );
$d = cta_decision( 'active', 'whatsapp', $url, false, false );
t_eq( '', $d['url'], 'whatsapp never exposes an external URL' );
$d = cta_decision( 'active', 'external', $url, false, false );
t_eq( array( true, 'available', $url ), array( $d['available'], $d['reason'], $d['url'] ), 'active external with URL → available with that target' );
$d = cta_decision( 'active', 'external', '', false, true );
t_eq( array( false, 'no_target' ), array( $d['available'], $d['reason'] ), 'active external without URL → no_target' );
$d = cta_decision( 'active', 'tickets', '', false, true );
t_eq( array( false, 'not_on_sale', 'tickets' ), array( $d['available'], $d['reason'], $d['mode'] ), 'active tickets before commerce → not_on_sale' );
$d = cta_decision( 'active', 'tickets', '', true, false );
t_eq( array( false, 'sales_closed' ), array( $d['available'], $d['reason'] ), 'tickets on sale after the cutoff → sales_closed' );
$d = cta_decision( 'active', 'tickets', '', true, true );
t_eq( array( true, 'available' ), array( $d['available'], $d['reason'] ), 'tickets on sale before the cutoff → available (future commerce)' );
foreach ( array( '', 'bogus' ) as $mode ) {
	$d = cta_decision( 'active', $mode, $url, true, true );
	t_eq( array( false, 'no_mode', '' ), array( $d['available'], $d['reason'], $d['mode'] ), "active with mode '$mode' → no_mode" );
}
t_eq( array( 'available', 'mode', 'reason', 'state', 'url' ), array_keys( cta_decision( 'active', 'whatsapp', '', false, false ) ), 'stable shape' );

t_group( 'cta/sales cutoff applies to own tickets only (N10)' );
foreach ( array( 'whatsapp', 'external' ) as $mode ) {
	$d = cta_decision( 'active', $mode, $url, false, false );
	t_ok( $d['available'], "$mode stays available after the ticket cutoff" );
}

t_group( 'cta/Event::cta() on stored events' );
t_reset_store();
$tz  = new DateTimeZone( 'America/Argentina/Buenos_Aires' );
$now = ( new DateTimeImmutable( '2026-10-15 20:00:00', $tz ) )->getTimestamp();
$ev  = static function ( array $meta, $status = 'publish' ) {
	$id = t_add_post( array( 'post_status' => $status, 'post_title' => 'CTA' ) );
	foreach ( array_merge( array( '_casa_start' => '2026-10-20 21:00:00' ), $meta ) as $k => $v ) {
		update_post_meta( $id, $k, $v );
	}
	sync_event( $id );
	return Event::get( $id );
};
$e = $ev( array( '_casa_access_mode' => 'whatsapp' ) );
t_eq( array( true, 'whatsapp', 'available' ), array( $e->cta( $now )['available'], $e->cta( $now )['mode'], $e->cta( $now )['reason'] ), 'active whatsapp event' );
$late = ( new DateTimeImmutable( '2026-10-20 20:30:00', $tz ) )->getTimestamp(); // after the default cutoff (20:00), before the start.
t_ok( $e->cta( $late )['available'], 'whatsapp after the default cutoff: still available' );
$e = $ev( array( '_casa_access_mode' => 'external', '_casa_external_url' => 'https://www.passline.com/x' ) );
t_eq( array( true, 'https://www.passline.com/x' ), array( $e->cta( $late )['available'], $e->cta( $late )['url'] ), 'external after the default cutoff: available with its URL' );
$e = $ev( array( '_casa_access_mode' => 'external' ) );
t_eq( 'no_target', $e->cta( $now )['reason'], 'external without URL' );
$e = $ev( array( '_casa_access_mode' => 'whatsapp', '_casa_external_url' => 'https://www.passline.com/x' ) );
t_eq( '', $e->cta( $now )['url'], 'a leftover external URL is ignored outside external mode' );
$e = $ev( array( '_casa_access_mode' => 'tickets' ) );
t_eq( array( false, 'not_on_sale' ), array( $e->cta( $now )['available'], $e->cta( $now )['reason'] ), 'tickets before commerce' );
add_filter( 'casa_eventos/ticket_sales_available', '__return_true' );
function __return_true() {
	return true;
}
t_eq( array( true, 'sales_closed' ), array( $e->cta( $now )['available'], $e->cta( $late )['reason'] ), 'commerce filter on: available before the cutoff, sales_closed after it' );
remove_all_filters( 'casa_eventos/ticket_sales_available' );
$e = $ev( array( '_casa_access_mode' => 'whatsapp', '_casa_status' => 'paused' ) );
t_eq( 'paused', $e->cta( $now )['reason'], 'paused' );
$e = $ev( array( '_casa_access_mode' => 'whatsapp', '_casa_status' => 'cancelled' ) );
t_eq( 'cancelled', $e->cta( $now )['reason'], 'cancelled' );
$e = $ev( array( '_casa_access_mode' => 'whatsapp' ) );
t_eq( 'finished', $e->cta( strtotime( '2026-10-21 03:30:00 UTC' ) )['reason'], 'finished (after the derived +3h end)' );
$e = $ev( array( '_casa_access_mode' => 'whatsapp' ), 'draft' );
t_eq( 'draft', $e->cta( $now )['reason'], 'draft' );
t_reset_store();
