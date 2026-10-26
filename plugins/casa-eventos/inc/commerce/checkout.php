<?php
/** T3 direct guest checkout. No cart, gateway call, or public sales activation. */
namespace CasaEventos\Commerce;
use CasaEventos\Core;
if ( ! defined( 'ABSPATH' ) ) { exit; }

const CHECKOUT_COOKIE = 'lcda_ticket_session';
const CHECKOUT_CONSENT_VERSION = 'v1';

function checkout_enabled() { return (bool) apply_filters( 'casa_eventos/t3_checkout_enabled', false ); }
function checkout_session_hash( $secret ) {
	return is_string( $secret ) && preg_match( '/^[a-f0-9]{64}$/D', $secret )
		? hash_hmac( 'sha256', $secret, wp_salt( 'auth' ) ) : null;
}
function checkout_csrf( $action, $event_id, $request_key, $secret ) {
	return hash_hmac( 'sha256', $action . ':' . $event_id . ':' . $request_key, wp_salt( 'nonce' ) . $secret );
}
function checkout_buyer( $input ) {
	if ( ! is_array( $input ) ) { return error( 'buyer', 'Completa los datos del comprador.' ); }
	$buyer = array();
	foreach ( array( 'first_name', 'last_name' ) as $field ) {
		$value = $input[ $field ] ?? null;
		if ( ! is_string( $value ) ) { return error( 'buyer', 'Completa nombre y apellido.' ); }
		$value = sanitize_text_field( wp_unslash( $value ) );
		if ( '' === $value || strlen( $value ) > 100 ) { return error( 'buyer', 'Nombre o apellido invalido.' ); }
		$buyer[ $field ] = $value;
	}
	$email = $input['email'] ?? null;
	if ( ! is_string( $email ) || strlen( $email ) > 254 || ! is_email( wp_unslash( $email ) ) ) {
		return error( 'buyer', 'Ingresa un email valido.' );
	}
	$buyer['email'] = sanitize_email( wp_unslash( $email ) );
	$phone = $input['phone'] ?? null;
	if ( ! is_string( $phone ) ) { return error( 'buyer', 'Ingresa un telefono.' ); }
	$phone = trim( wp_unslash( $phone ) );
	if ( strlen( $phone ) > 40 || ! preg_match( '/^[+0-9 ()-]+$/D', $phone )
		|| strlen( preg_replace( '/\D/', '', $phone ) ) < 6 || strlen( preg_replace( '/\D/', '', $phone ) ) > 20 ) {
		return error( 'buyer', 'Ingresa un telefono valido.' );
	}
	$buyer['phone'] = $phone;
	$consent = $input['newsletter'] ?? '0';
	if ( ! in_array( $consent, array( '0', '1', 0, 1 ), true ) ) { return error( 'buyer', 'El consentimiento no es valido.' ); }
	$buyer['newsletter'] = '1' === (string) $consent;
	return $buyer;
}
function checkout_buyer_hash( $buyer ) {
	return hash_hmac( 'sha256', wp_json_encode( $buyer ), wp_salt( 'auth' ) );
}
function checkout_order_key( $event_id, $reservation_id ) {
	return 'wc_' . substr( hash_hmac( 'sha256', $event_id . ':' . $reservation_id, wp_salt( 'auth' ) ), 0, 19 );
}

/** Owner-bound, replayable entry to the 20-minute T2 reservation. */
function start_guest_checkout( $event_id, $lines, $request_key, $secret, $now = null ) {
	$session_hash = checkout_session_hash( $secret );
	if ( ! $session_hash ) { return error( 'owner', 'La sesion de compra no es valida.' ); }
	if ( function_exists( 'wc_tax_enabled' ) && wc_tax_enabled() ) { return error( 'tax_policy', 'La configuracion fiscal requiere validacion antes de vender entradas.' ); }
	$result = reserve( $event_id, $lines, $request_key, $now, $session_hash );
	if ( is_wp_error( $result ) ) { return $result; }
	if ( 'checkout_active' !== $result['status'] ) { return error( 'expired', 'Esta reserva no admite checkout nuevo.' ); }
	return $result;
}

function checkout_order_matches( $order, $row, $buyer_hash ) {
	if ( ! $order || 'lcda-ticket' !== $order->get_created_via() || 'pending' !== $order->get_status()
		|| (int) $order->get_meta( '_lcda_ticket_event_id' ) !== (int) $row->event_id
		|| (int) $order->get_meta( '_lcda_ticket_reservation_id' ) !== (int) $row->id
		|| ! hash_equals( (string) $row->request_hash, (string) $order->get_meta( '_lcda_ticket_request_hash' ) )
		|| ! hash_equals( $buyer_hash, (string) $order->get_meta( '_lcda_ticket_buyer_hash' ) )
		|| ! hash_equals( (string) $row->session_hash, (string) $order->get_meta( '_lcda_ticket_session_hash' ) ) ) { return false; }
	$expected = json_decode( (string) $row->quantities, true );
	$snapshot = json_decode( (string) $row->snapshot, true );
	$items = $order->get_items( 'line_item' );
	if ( ! is_array( $expected ) || ! is_array( $snapshot ) || count( $items ) !== count( $expected ) ) { return false; }
	$seen = array();
	$total = 0;
	foreach ( $items as $item ) {
		$type_id = (int) $item->get_variation_id();
		if ( isset( $seen[ $type_id ] ) || ! isset( $expected[ $type_id ] ) || (int) $expected[ $type_id ] !== (int) $item->get_quantity() ) { return false; }
		$line = $snapshot[ $type_id ] ?? null;
		$type = wc_get_product( $type_id );
		if ( ! is_array( $line ) || ! $type || ! hash_equals( (string) ( $line['price'] ?? '' ), (string) $item->get_meta( '_lcda_ticket_unit_price' ) )
			|| (string) ( $line['name'] ?? '' ) !== $item->get_name()
			|| (string) ( $line['currency'] ?? '' ) !== $order->get_currency()
			|| (string) $item->get_total() !== (string) $item->get_subtotal() ) { return false; }
		$expected_total = wc_get_price_excluding_tax( $type, array( 'qty' => (int) $item->get_quantity(), 'price' => $line['price'] ) );
		if ( wc_format_decimal( $expected_total ) !== wc_format_decimal( $item->get_total() ) ) { return false; }
		$total += (float) $item->get_total();
		$seen[ $type_id ] = true;
	}
	return wc_format_decimal( $total, wc_get_price_decimals() ) === wc_format_decimal( $order->get_total(), wc_get_price_decimals() );
}

/** Preserve the T2 price and event snapshot in one HPOS-compatible Woo order. */
function checkout_create_order( $row, $buyer, $order_key ) {
	$snapshot = json_decode( (string) $row->snapshot, true );
	$lines = json_decode( (string) $row->quantities, true );
	if ( ! is_array( $snapshot ) || ! isset( $snapshot['_event'] ) || ! is_array( $lines ) || ! $lines ) {
		return error( 'snapshot', 'La reserva no tiene un snapshot completo.' );
	}
	$parent = parent_product( (int) $row->event_id );
	if ( is_wp_error( $parent ) || ! $parent ) { return error( 'unknown', 'No se puede verificar el producto del evento.' ); }
	$types = array();
	foreach ( variations( $parent ) as $type ) { $types[ $type->get_id() ] = $type; }
	$order = new \WC_Order();
	$order->set_order_key( $order_key );
	$order->set_created_via( 'lcda-ticket' );
	$order->set_status( 'pending' );
	$order->set_customer_id( 0 );
	$order->set_prices_include_tax( false );
	$order->set_billing_first_name( $buyer['first_name'] );
	$order->set_billing_last_name( $buyer['last_name'] );
	$order->set_billing_email( $buyer['email'] );
	$order->set_billing_phone( $buyer['phone'] );
	$currency = null;
	foreach ( $lines as $type_id => $quantity ) {
		$type = $types[ $type_id ] ?? null;
		$line = $snapshot[ $type_id ] ?? null;
		if ( ! $type || ! is_array( $line ) || (int) ( $line['quantity'] ?? 0 ) !== (int) $quantity
			|| ! isset( $line['uuid'], $line['name'], $line['price'], $line['currency'] )
			|| ! hash_equals( (string) $line['uuid'], (string) $type->get_meta( '_lcda_ticket_uuid' ) )
			|| ! is_numeric( $line['price'] ) || (float) $line['price'] < 0 ) {
			return error( 'snapshot', 'El snapshot de un tipo no es valido.' );
		}
		if ( null !== $currency && $currency !== $line['currency'] ) { return error( 'currency', 'Los tipos tienen distintas monedas.' ); }
		$currency = $line['currency'];
		$item = new \WC_Order_Item_Product();
		$item->set_product_id( $parent->get_id() );
		$item->set_variation_id( (int) $type_id );
		$item->set_name( (string) $line['name'] );
		$item->set_quantity( (int) $quantity );
		$total = wc_get_price_excluding_tax( $type, array( 'qty' => (int) $quantity, 'price' => $line['price'] ) );
		$item->set_subtotal( $total );
		$item->set_total( $total );
		$item->add_meta_data( '_lcda_ticket_type_uuid', $line['uuid'], true );
		$item->add_meta_data( '_lcda_ticket_unit_price', (string) $line['price'], true );
		$item->add_meta_data( '_lcda_ticket_currency', $currency, true );
		$order->add_item( $item );
	}
	$order->set_currency( $currency );
	$order->update_meta_data( '_lcda_ticket_event_id', (int) $row->event_id );
	$order->update_meta_data( '_lcda_ticket_reservation_id', (int) $row->id );
	$order->update_meta_data( '_lcda_ticket_request_hash', (string) $row->request_hash );
	$order->update_meta_data( '_lcda_ticket_session_hash', (string) $row->session_hash );
	$order->update_meta_data( '_lcda_ticket_buyer_hash', checkout_buyer_hash( $buyer ) );
	$order->update_meta_data( '_lcda_ticket_event_snapshot', $snapshot['_event'] );
	$order->update_meta_data( '_lcda_ticket_expires_at', (int) $row->expires_at );
	$order->update_meta_data( '_lcda_ticket_schema', 1 );
	$order->update_meta_data( '_lcda_newsletter_opt_in', $buyer['newsletter'] ? 'yes' : 'no' );
	$order->update_meta_data( '_lcda_newsletter_consent_at', gmdate( 'Y-m-d H:i:s' ) );
	$order->update_meta_data( '_lcda_newsletter_text_version', CHECKOUT_CONSENT_VERSION );
	$order->calculate_totals( false ); // Woo CRUD persists order, lines and metadata. No payment attempt.
	return $order;
}

/** One order per reservation. An uncertain Woo write keeps the T2 fence and capacity. */
function submit_guest_checkout( $event_id, $reservation_id, $request_key, $secret, $buyer_input, $now = null ) {
	$buyer = checkout_buyer( $buyer_input );
	if ( is_wp_error( $buyer ) ) { return $buyer; }
	$session_hash = checkout_session_hash( $secret );
	$event_id = integer_value( $event_id, 1 );
	$reservation_id = integer_value( $reservation_id, 1 );
	$now = null === $now ? time() : integer_value( $now, 0 );
	if ( ! $session_hash || null === $event_id || null === $reservation_id || null === $now ) { return error( 'request', 'El checkout no es valido.' ); }
	if ( function_exists( 'wc_tax_enabled' ) && wc_tax_enabled() ) { return error( 'tax_policy', 'La configuracion fiscal requiere validacion.' ); }
	$owned_before = ! empty( $GLOBALS['lcda_config_locks'][ $event_id ] );
	$lock = acquire( $event_id );
	if ( is_wp_error( $lock ) ) { return $lock; }
	try {
		$row = reservation_owner_row( $event_id, $reservation_id, $request_key );
		if ( is_wp_error( $row ) ) { return $row; }
		if ( ! hash_equals( (string) $row->session_hash, $session_hash ) ) { return error( 'owner', 'La sesion no corresponde a la reserva.' ); }
		$key = checkout_order_key( $event_id, $reservation_id );
		$order_id = wc_get_order_id_by_order_key( $key );
		$order = $order_id ? wc_get_order( $order_id ) : null;
		if ( $row->order_id && (int) $row->order_id !== (int) $order_id ) { return error( 'uncertain', 'El pedido vinculado requiere conciliacion.' ); }
		if ( $order ) {
			if ( ! checkout_order_matches( $order, $row, checkout_buyer_hash( $buyer ) ) ) { return error( 'conflict', 'El pedido existente requiere conciliacion.' ); }
			if ( ! $row->order_id ) {
				$bound = bind_reservation_order( $event_id, $reservation_id, $request_key, $order_id );
				if ( is_wp_error( $bound ) ) { return $bound; }
			}
			return array( 'order_id' => (int) $order_id, 'reservation_id' => $reservation_id,
				'expires_at' => (int) $row->expires_at, 'status' => 'pending', 'replayed' => true );
		}
		if ( 'order_creating' === $row->status ) { return error( 'uncertain', 'Un intento de creacion requiere conciliacion; no se crea otro pedido.' ); }
		if ( 'checkout_active' !== $row->status || (int) $row->expires_at <= $now ) { return error( 'expired', 'El checkout vencio.' ); }
		$event = Core\Event::get( $event_id );
		$start = $event ? Core\gmt_datetime( $event->start_gmt() ) : null;
		if ( ! $event || ! $start || $start->getTimestamp() <= $now || ! $event->is_actionable( $now )
			|| Core\ACCESS_TICKETS !== $event->access_mode() || ! $event->is_before_sales_close( $now ) ) {
			return error( 'closed', 'El evento ya no admite checkout.' );
		}
		$parent = parent_product( $event_id );
		if ( is_wp_error( $parent ) || ! $parent ) { return error( 'unknown', 'No se puede verificar los tipos.' ); }
		$types = array();
		foreach ( variations( $parent ) as $type ) { $types[ $type->get_id() ] = $type; }
		foreach ( (array) json_decode( (string) $row->quantities, true ) as $type_id => $quantity ) {
			$type = $types[ $type_id ] ?? null;
			$cutoff = $type ? (string) $type->get_meta( '_lcda_ticket_cutoff_gmt' ) : '';
			$cutoff_time = $cutoff ? Core\gmt_datetime( $cutoff ) : null;
			if ( ! $type || '1' !== (string) $type->get_meta( '_lcda_ticket_active' )
				|| ( $cutoff && ( ! $cutoff_time || $cutoff_time->getTimestamp() <= $now ) ) ) {
				return error( 'closed', 'Un tipo ya no admite checkout.' );
			}
		}
		$claim = claim_order_creation( $event_id, $reservation_id, $request_key, $session_hash, $now );
		if ( is_wp_error( $claim ) ) { return $claim; }
		$order = checkout_create_order( $row, $buyer, $key );
		if ( is_wp_error( $order ) || ! $order->get_id() ) { return error( 'uncertain', 'No se pudo confirmar el pedido. Reintenta con la misma reserva.' ); }
		if ( ! checkout_order_matches( $order, $row, checkout_buyer_hash( $buyer ) ) ) { return error( 'uncertain', 'El pedido quedo incompleto.' ); }
		$bound = bind_reservation_order( $event_id, $reservation_id, $request_key, $order->get_id() );
		if ( is_wp_error( $bound ) ) { return $bound; }
		return array( 'order_id' => (int) $order->get_id(), 'reservation_id' => $reservation_id,
			'expires_at' => (int) $row->expires_at, 'status' => 'pending', 'replayed' => false );
	} catch ( \Throwable $exception ) { return error( 'uncertain', 'El pedido requiere conciliacion. Reintenta con la misma reserva.' ); }
	finally { if ( ! $owned_before ) { release( $event_id ); } }
}

/** T4 handoff: persist a sent/uncertain attempt before any external call. */
function prepare_gateway_handoff( $event_id, $reservation_id, $request_key, $secret, $attempt_key, $now = null ) {
	$session_hash = checkout_session_hash( $secret );
	$event_id = integer_value( $event_id, 1 );
	$reservation_id = integer_value( $reservation_id, 1 );
	if ( ! $session_hash || null === $event_id || null === $reservation_id ) { return error( 'owner', 'La sesion no corresponde al pedido.' ); }
	$owned_before = ! empty( $GLOBALS['lcda_config_locks'][ $event_id ] );
	$lock = acquire( $event_id );
	if ( is_wp_error( $lock ) ) { return $lock; }
	try {
		$row = reservation_owner_row( $event_id, $reservation_id, $request_key );
		if ( is_wp_error( $row ) ) { return $row; }
		if ( ! hash_equals( (string) $row->session_hash, $session_hash ) || ! $row->order_id ) { return error( 'owner', 'El pedido no corresponde a esta sesion.' ); }
		$order = wc_get_order( (int) $row->order_id );
		if ( ! $order || 'pending' !== $order->get_status() || 'lcda-ticket' !== $order->get_created_via()
			|| (int) $order->get_meta( '_lcda_ticket_reservation_id' ) !== $reservation_id
			|| ! hash_equals( (string) $row->request_hash, (string) $order->get_meta( '_lcda_ticket_request_hash' ) ) ) {
			return error( 'unknown', 'El pedido requiere conciliacion antes del pago.' );
		}
		return retain_unknown_payment( $event_id, $reservation_id, $request_key, $attempt_key, $now );
	} catch ( \Throwable $exception ) { return error( 'uncertain', 'El intento requiere conciliacion.' ); }
	finally { if ( ! $owned_before ) { release( $event_id ); } }
}

/** Prevent Woo stock changes or unpaid-order cancellation for coordinator orders. */
function checkout_woo_stock_guard( $allowed, $order ) {
	return $order && 'lcda-ticket' === $order->get_created_via() ? false : $allowed;
}
add_filter( 'woocommerce_can_reduce_order_stock', __NAMESPACE__ . '\\checkout_woo_stock_guard', PHP_INT_MAX, 2 );
add_filter( 'woocommerce_cancel_unpaid_order', __NAMESPACE__ . '\\checkout_woo_stock_guard', PHP_INT_MAX, 2 );
function checkout_payment_stock_guard( $allowed, $order_id ) {
	$order = wc_get_order( $order_id );
	return checkout_woo_stock_guard( $allowed, $order );
}
add_filter( 'woocommerce_payment_complete_reduce_order_stock', __NAMESPACE__ . '\\checkout_payment_stock_guard', PHP_INT_MAX, 2 );

function checkout_cookie_secret( $create = false ) {
	$secret = $_COOKIE[ CHECKOUT_COOKIE ] ?? null;
	if ( checkout_session_hash( $secret ) ) { return $secret; }
	if ( ! $create || headers_sent() ) { return null; }
	$secret = bin2hex( random_bytes( 32 ) );
	setcookie( CHECKOUT_COOKIE, $secret, array( 'expires' => 0, 'path' => '/', 'secure' => is_ssl(),
		'httponly' => true, 'samesite' => 'Lax' ) );
	$_COOKIE[ CHECKOUT_COOKIE ] = $secret;
	return $secret;
}

/** Both gates must be explicitly open before any public ticket control appears. */
function checkout_sales_enabled( $event_id ) {
	$event_id = integer_value( $event_id, 1 );
	if ( null === $event_id || ! checkout_enabled() ) { return false; }
	$event = Core\Event::get( $event_id );
	if ( ! $event ) { return false; }
	$cta = $event->cta();
	return ! empty( $cta['available'] ) && Core\ACCESS_TICKETS === $cta['mode'];
}

/** A read-only, lock-consistent projection of the same stock and holds used by reserve(). */
function checkout_selector_data( $event_id, $now = null ) {
	global $wpdb;
	if ( ! checkout_sales_enabled( $event_id ) || ! available()
		|| (int) get_option( RESERVATION_SCHEMA_OPTION, 0 ) !== RESERVATION_SCHEMA
		|| ( function_exists( 'wc_tax_enabled' ) && wc_tax_enabled() ) ) {
		return error( 'closed', 'La venta de entradas no está disponible.' );
	}
	$now = null === $now ? time() : integer_value( $now, 0 );
	if ( null === $now ) { return error( 'request', 'El reloj no es válido.' ); }
	$owned_before = ! empty( $GLOBALS['lcda_config_locks'][ $event_id ] );
	$lock = acquire( $event_id );
	if ( is_wp_error( $lock ) ) { return $lock; }
	try {
		refresh_locked_reads( $event_id );
		if ( ! checkout_sales_enabled( $event_id ) ) { return error( 'closed', 'La venta de entradas no está disponible.' ); }
		$event = Core\Event::get( $event_id );
		$start = $event ? Core\gmt_datetime( $event->start_gmt() ) : null;
		if ( ! $event || ! $start || $start->getTimestamp() <= $now ) { return error( 'closed', 'La venta de entradas no está disponible.' ); }
		$parent = parent_product( $event_id );
		if ( is_wp_error( $parent ) || ! $parent || ! zero_consumption( $parent, true ) ) {
			return error( 'unknown', 'No se puede verificar la disponibilidad.' );
		}
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id,status,expires_at,quantities FROM ' . reservation_table() . ' WHERE event_id = %d', $event_id ) );
		if ( $wpdb->last_error ) { return error( 'ledger', 'No se puede verificar la disponibilidad.' ); }
		$has_paid_history = false;
		foreach ( (array) $rows as $row ) {
			if ( in_array( (string) $row->status, array( 'paid', 'refunded' ), true ) ) { $has_paid_history = true; break; }
		}
		$refunds = (int) get_option( REFUND_SCHEMA_OPTION, 0 ) !== REFUND_SCHEMA && ! $has_paid_history
			? array() : confirmed_refund_quantities( $event_id );
		if ( is_wp_error( $refunds ) ) { return $refunds; }
		$held = reservation_held( $rows, $now, $refunds );
		if ( is_wp_error( $held ) ) { return $held; }
		$global = max( 0, $event->capacity() - $held['global'] );
		$types = array();
		foreach ( variations( $parent ) as $type ) {
			if ( '1' !== (string) $type->get_meta( '_lcda_ticket_active' )
				|| '1' !== (string) $type->get_meta( '_lcda_ticket_request_complete' ) ) { continue; }
			$price = (string) $type->get_regular_price( 'edit' );
			$currency = (string) $type->get_meta( '_lcda_ticket_currency' );
			if ( ! is_numeric( $price ) || (float) $price < 0 || ! preg_match( '/^[A-Z]{3}$/D', $currency ) ) {
				return error( 'unknown', 'No se puede verificar el precio.' );
			}
			$cutoff = (string) $type->get_meta( '_lcda_ticket_cutoff_gmt' );
			$cutoff_time = $cutoff ? Core\gmt_datetime( $cutoff ) : null;
			$open = ! $cutoff || ( $cutoff_time && $cutoff_time->getTimestamp() > $now );
			$remaining = $open ? max( 0, min( $global, (int) $type->get_stock_quantity( 'edit' ) - ( $held['types'][ $type->get_id() ] ?? 0 ) ) ) : 0;
			$types[] = array(
				'id' => $type->get_id(), 'name' => (string) $type->get_meta( '_lcda_ticket_name' ),
				'price' => $price, 'currency' => $currency, 'available' => $remaining,
				'maximum' => min( 10, (int) $type->get_meta( '_lcda_ticket_maximum' ), $remaining ),
				'closed' => ! $open,
			);
		}
		return array( 'global_available' => $global, 'types' => $types );
	} catch ( \Throwable $exception ) {
		return error( 'unknown', 'No se puede verificar la disponibilidad.' );
	} finally {
		if ( ! $owned_before ) { release( $event_id ); }
	}
}

function checkout_prepare_page() {
	if ( ! checkout_enabled() || ! is_singular() ) { return; }
	$post = get_post( get_queried_object_id() );
	if ( ! $post ) { return; }
	if ( ( Core\POST_TYPE === $post->post_type && checkout_sales_enabled( $post->ID ) )
		|| has_shortcode( $post->post_content, 'lcda_ticket_checkout' ) ) {
		nocache_headers();
		checkout_cookie_secret( true );
	}
}
add_action( 'template_redirect', __NAMESPACE__ . '\\checkout_prepare_page', 5 );

function checkout_public_assets() {
	if ( ! checkout_enabled() || ! is_singular() ) { return; }
	$post = get_post( get_queried_object_id() );
	if ( ! $post || ( ! ( Core\POST_TYPE === $post->post_type && checkout_sales_enabled( $post->ID ) )
		&& ! has_shortcode( $post->post_content, 'lcda_ticket_checkout' ) ) ) { return; }
	wp_enqueue_style( 'casa-ticket-checkout', CASA_EVENTOS_URL . 'assets/public/ticket-checkout.css', array(), CASA_EVENTOS_VERSION );
	wp_enqueue_script( 'casa-ticket-selector', CASA_EVENTOS_URL . 'assets/public/ticket-selector.js', array(), CASA_EVENTOS_VERSION, true );
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\checkout_public_assets', 20 );

/** Existing plugin-owned selector, usable on the Event page or through its shortcode. */
function checkout_selector_shortcode( $attributes ) {
	$event_id = absint( $attributes['event_id'] ?? 0 );
	if ( ! checkout_sales_enabled( $event_id ) ) { return ''; }
	$secret = checkout_cookie_secret( true );
	if ( ! $secret ) { return ''; }
	$data = checkout_selector_data( $event_id );
	if ( is_wp_error( $data ) || ! $data['types'] ) {
		return '<p class="casa-ticket-selector__notice">Las entradas no están disponibles en este momento.</p>';
	}
	$key = wp_generate_uuid4();
	$nonce = checkout_csrf( 'start', $event_id, $key, $secret );
	ob_start();
	?>
	<form class="casa-ticket-selector" data-casa-ticket-selector data-global-available="<?php echo esc_attr( $data['global_available'] ); ?>" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="lcda_ticket_start">
		<input type="hidden" name="event_id" value="<?php echo esc_attr( $event_id ); ?>">
		<input type="hidden" name="request_key" value="<?php echo esc_attr( $key ); ?>">
		<input type="hidden" name="checkout_nonce" value="<?php echo esc_attr( $nonce ); ?>">
		<h2 class="casa-ticket-selector__title">Elegí tus entradas</h2>
		<p class="casa-ticket-selector__capacity">Aforo disponible ahora: <?php echo esc_html( $data['global_available'] ); ?>. La disponibilidad se confirma al reservar.</p>
		<div class="casa-ticket-selector__types">
			<?php foreach ( $data['types'] as $type ) : ?>
				<div class="casa-ticket-selector__type" data-price="<?php echo esc_attr( $type['price'] ); ?>" data-currency="<?php echo esc_attr( $type['currency'] ); ?>">
					<div class="casa-ticket-selector__description">
						<strong><?php echo esc_html( $type['name'] ); ?></strong>
						<span class="casa-ticket-selector__price"><?php echo esc_html( $type['currency'] . ' ' . number_format_i18n( (float) $type['price'], wc_get_price_decimals() ) ); ?></span>
						<span class="casa-ticket-selector__availability"><?php echo esc_html( $type['closed'] ? 'Venta cerrada' : ( $type['available'] ? 'Disponibles: ' . $type['available'] : 'Agotadas' ) ); ?></span>
					</div>
					<label for="casa-ticket-quantity-<?php echo esc_attr( $type['id'] ); ?>">Cantidad de <?php echo esc_html( $type['name'] ); ?></label>
					<input id="casa-ticket-quantity-<?php echo esc_attr( $type['id'] ); ?>" type="number" inputmode="numeric" name="lines[<?php echo esc_attr( $type['id'] ); ?>]" min="0" max="<?php echo esc_attr( $type['maximum'] ); ?>" step="1" value="0" <?php disabled( 0 === $type['maximum'] ); ?>>
				</div>
			<?php endforeach; ?>
		</div>
		<div class="casa-ticket-selector__summary">
			<span>Total de entradas</span>
			<output data-casa-ticket-total aria-live="polite">Elegí una cantidad para ver el total.</output>
		</div>
		<p class="casa-ticket-selector__feedback" data-casa-ticket-feedback aria-live="polite"></p>
		<button class="lcda-btn casa-ticket-selector__submit" type="submit">Continuar al checkout</button>
		<noscript><p>El total se mostrará al activar JavaScript; el servidor verificará precio y cupo antes de reservar.</p></noscript>
	</form>
	<?php
	return ob_get_clean();
}
if ( function_exists( 'add_shortcode' ) ) { add_shortcode( 'lcda_ticket_checkout', __NAMESPACE__ . '\\checkout_selector_shortcode' ); }

function checkout_document_start( $title ) {
	?><!doctype html>
	<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo esc_html( $title ); ?></title>
	<link rel="stylesheet" href="<?php echo esc_url( CASA_EVENTOS_URL . 'assets/public/ticket-checkout.css?ver=' . CASA_EVENTOS_VERSION ); ?>">
	</head><body class="casa-checkout-page"><main class="casa-checkout-page__main"><?php
}
function checkout_document_end() {
	?></main></body></html><?php
}
function checkout_guest_form( $event_id, $reservation, $request_key, $secret ) {
	$nonce = checkout_csrf( 'submit:' . $reservation['id'], $event_id, $request_key, $secret );
	checkout_document_start( 'Datos de compra' );
	?>
	<a class="casa-checkout-page__back" href="<?php echo esc_url( get_permalink( $event_id ) ); ?>">← Volver al evento</a>
	<h1>Datos de compra</h1>
	<p class="casa-checkout-page__notice">Tu reserva vence el <?php echo esc_html( wp_date( 'd/m/Y \a \l\a\s H:i', $reservation['expires_at'], wp_timezone() ) ); ?>.</p>
	<form class="casa-checkout-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="lcda_ticket_submit">
		<input type="hidden" name="event_id" value="<?php echo esc_attr( $event_id ); ?>">
		<input type="hidden" name="reservation_id" value="<?php echo esc_attr( $reservation['id'] ); ?>">
		<input type="hidden" name="request_key" value="<?php echo esc_attr( $request_key ); ?>">
		<input type="hidden" name="checkout_nonce" value="<?php echo esc_attr( $nonce ); ?>">
		<label>Nombre <input name="first_name" autocomplete="given-name" required maxlength="100"></label>
		<label>Apellido <input name="last_name" autocomplete="family-name" required maxlength="100"></label>
		<label>Email <input type="email" name="email" autocomplete="email" required maxlength="254"></label>
		<label>Teléfono <input type="tel" name="phone" autocomplete="tel" required maxlength="40"></label>
		<label class="casa-checkout-form__consent"><input type="checkbox" name="newsletter" value="1"> Quiero recibir novedades de La Casa</label>
		<button type="submit">Crear pedido pendiente</button>
	</form>
	<?php checkout_document_end();
}
function checkout_http_guard( $event_id ) {
	if ( ! checkout_sales_enabled( $event_id ) ) { wp_die( 'No disponible', '', array( 'response' => 404 ) ); }
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { wp_die( 'Método no permitido', '', array( 'response' => 405 ) ); }
	nocache_headers();
	$secret = checkout_cookie_secret();
	if ( ! $secret ) { wp_die( 'Sesión no disponible', '', array( 'response' => 403 ) ); }
	return $secret;
}
function checkout_post_start() {
	$event_id = absint( $_POST['event_id'] ?? 0 );
	$secret = checkout_http_guard( $event_id );
	$key = isset( $_POST['request_key'] ) && is_string( $_POST['request_key'] ) ? sanitize_text_field( wp_unslash( $_POST['request_key'] ) ) : '';
	$nonce = isset( $_POST['checkout_nonce'] ) && is_string( $_POST['checkout_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['checkout_nonce'] ) ) : '';
	if ( ! hash_equals( checkout_csrf( 'start', $event_id, $key, $secret ), $nonce ) ) { wp_die( 'Solicitud inválida', '', array( 'response' => 403 ) ); }
	$lines = $_POST['lines'] ?? array();
	if ( ! is_array( $lines ) ) { wp_die( 'Selección inválida', '', array( 'response' => 400 ) ); }
	$lines = array_filter( wp_unslash( $lines ), static fn( $quantity ) => '0' !== (string) $quantity );
	$result = start_guest_checkout( $event_id, $lines, $key, $secret );
	if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 400 ) ); }
	checkout_guest_form( $event_id, $result, $key, $secret );
	exit;
}
function checkout_post_submit() {
	$event_id = absint( $_POST['event_id'] ?? 0 );
	$secret = checkout_http_guard( $event_id );
	$reservation_id = absint( $_POST['reservation_id'] ?? 0 );
	$key = isset( $_POST['request_key'] ) && is_string( $_POST['request_key'] ) ? sanitize_text_field( wp_unslash( $_POST['request_key'] ) ) : '';
	$nonce = isset( $_POST['checkout_nonce'] ) && is_string( $_POST['checkout_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['checkout_nonce'] ) ) : '';
	if ( ! hash_equals( checkout_csrf( 'submit:' . $reservation_id, $event_id, $key, $secret ), $nonce ) ) { wp_die( 'Solicitud inválida', '', array( 'response' => 403 ) ); }
	$result = submit_guest_checkout( $event_id, $reservation_id, $key, $secret, $_POST );
	if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 400 ) ); }
	checkout_document_start( 'Pedido pendiente' );
	?>
	<a class="casa-checkout-page__back" href="<?php echo esc_url( get_permalink( $event_id ) ); ?>">← Volver al evento</a>
	<h1>Pedido pendiente</h1>
	<p>Pedido <?php echo esc_html( $result['order_id'] ); ?> creado. El pago aún no está disponible.</p>
	<?php
	checkout_document_end();
	exit;
}
add_action( 'admin_post_nopriv_lcda_ticket_start', __NAMESPACE__ . '\\checkout_post_start' );
add_action( 'admin_post_lcda_ticket_start', __NAMESPACE__ . '\\checkout_post_start' );
add_action( 'admin_post_nopriv_lcda_ticket_submit', __NAMESPACE__ . '\\checkout_post_submit' );
add_action( 'admin_post_lcda_ticket_submit', __NAMESPACE__ . '\\checkout_post_submit' );
