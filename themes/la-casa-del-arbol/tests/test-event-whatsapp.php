<?php
/**
 * Event WhatsApp CTA (theme markup): destination, message encoding, link
 * security, and that it only appears where the plugin's CTA decision allows.
 *
 *     php themes/la-casa-del-arbol/tests/test-event-whatsapp.php
 *
 * Reuses the plugin's static bootstrap (WordPress stand-in + Event Core).
 *
 * @package LaCasaDelArbol
 */

require dirname( __DIR__, 3 ) . '/plugins/casa-eventos/tests/bootstrap.php';

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return str_replace( array( '&', "'" ), array( '&#038;', '&#039;' ), $url );
	}
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES );
	}
}

require_once dirname( __DIR__ ) . '/inc/template-tags.php';
require_once dirname( __DIR__ ) . '/inc/events.php';

use CasaEventos\Core\Event;
use function CasaEventos\Core\sync_event;

$ev  = static function ( $title, array $meta, $status = 'publish' ) {
	$id = t_add_post( array( 'post_status' => $status, 'post_title' => $title ) );
	foreach ( array_merge( array( '_casa_start' => '2099-10-20 21:00:00' ), $meta ) as $k => $v ) {
		update_post_meta( $id, $k, $v );
	}
	sync_event( $id );
	return Event::get( $id );
};

t_group( 'whatsapp/url' );
t_reset_store();
$e = $ev( 'Noche de Jazz', array( '_casa_access_mode' => 'whatsapp' ) );
t_eq( 'https://wa.me/5491140385603?text=Hola%21%20Me%20interesaba%20la%20actividad%20Noche%20de%20Jazz', lcda_event_whatsapp_url( $e ), 'exact URL' );

foreach ( array( 'Taller de Cerámica & Arte', 'Ñandú ¿Qué? #1', "L'Éclat \"live\" 100% 🎶", 'a+b=c/d' ) as $title ) {
	$url = lcda_event_whatsapp_url( $ev( $title, array( '_casa_access_mode' => 'whatsapp' ) ) );
	parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $q );
	t_eq( 'Hola! Me interesaba la actividad ' . $title, $q['text'], 'message round-trips: ' . $title );
	t_eq( 1, preg_match( '#^https://wa\.me/5491140385603\?text=[A-Za-z0-9%._~-]+$#', $url ), 'only safe characters in URL: ' . $title );
}

t_group( 'whatsapp/link attributes' );
$a = lcda_event_action( $e );
t_eq( 'link', $a['type'], 'active whatsapp event → link' );
t_eq( 'Reservar', $a['label'], 'label unchanged' );
t_ok( false !== strpos( $a['attributes'], 'href="https://wa.me/5491140385603?text=Hola%21%20Me%20interesaba%20la%20actividad%20Noche%20de%20Jazz"' ), 'href' );
t_ok( false !== strpos( $a['attributes'], ' target="_blank"' ), 'target _blank' );
t_ok( false !== strpos( $a['attributes'], ' rel="noopener noreferrer"' ), 'rel noopener noreferrer' );
$amp = lcda_event_action( $ev( 'Rock & Roll', array( '_casa_access_mode' => 'whatsapp' ) ) );
t_ok( false !== strpos( $amp['attributes'], '%26' ) && false === strpos( $amp['attributes'], ' & ' ), 'ampersand in title stays encoded in the attribute' );

t_group( 'whatsapp/only where the CTA contract allows' );
foreach ( array( 'paused' => 'Reservas pausadas', 'cancelled' => 'Evento cancelado' ) as $status => $note ) {
	$r = lcda_event_action( $ev( 'X', array( '_casa_access_mode' => 'whatsapp', '_casa_status' => $status ) ) );
	t_eq( array( 'note', $note ), array( $r['type'], $r['text'] ), "{$status}: note, no link" );
}
t_eq( null, lcda_event_action( $ev( 'X', array( '_casa_access_mode' => 'whatsapp' ), 'draft' ) ), 'draft: nothing' );
t_eq( 'note', lcda_event_action( $ev( 'X', array( '_casa_access_mode' => 'whatsapp', '_casa_start' => '2020-01-01 21:00:00' ) ) )['type'], 'finished: note, no link' );
$ext = lcda_event_action( $ev( 'X', array( '_casa_access_mode' => 'external', '_casa_external_url' => 'https://www.passline.com/x' ) ) );
t_eq( 'href="https://www.passline.com/x"', $ext['attributes'], 'external: unchanged, no WhatsApp' );
t_eq( array( 'note', 'Entradas a la venta próximamente' ), array( lcda_event_action( $ev( 'X', array( '_casa_access_mode' => 'tickets' ) ) )['type'], lcda_event_action( $ev( 'X', array( '_casa_access_mode' => 'tickets' ) ) )['text'] ), 'tickets: unchanged (note, no link)' );

foreach ( $GLOBALS['t_fail'] as $m ) {
	fwrite( STDERR, "FAIL  {$m}\n" );
}
printf( "%d passed, %d failed\n", $GLOBALS['t_pass'], count( $GLOBALS['t_fail'] ) );
exit( $GLOBALS['t_fail'] ? 1 : 0 );
