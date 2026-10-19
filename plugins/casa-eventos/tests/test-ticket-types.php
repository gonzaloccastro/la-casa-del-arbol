<?php
/** T1 service tests using the existing unit runner. No WP/Woo runtime or SQL emulation. */
use CasaEventos\Core;
use CasaEventos\Commerce;
use CasaEventos\Admin;

require_once dirname( __DIR__ ) . '/inc/commerce/bootstrap.php';
require_once dirname( __DIR__ ) . '/inc/admin/ticket-types.php';

function __return_false() { return false; }
t_group( 'T1/dependency absent' );
t_eq( false, Commerce\available(), 'dependency absent before doubles are enabled' );
t_eq( 'casa_tickets_unavailable', Commerce\parent_product( 1 )->get_error_code(), 'actionable dependency error without fatal' );
t_reset_store();
$GLOBALS['t_caps'] = array_fill_keys( array( 'edit_post', 'manage_casa_ticket_types', 'edit_casa_ticket_prices', 'edit_casa_ticket_capacities' ), true );
$absent_id = t_add_post( array( 'post_type' => 'casa_evento', 'post_status' => 'draft', 'post_title' => 'Sin dependencia' ) );
t_ok( ! is_wp_error( Core\save_event( $absent_id, array( 'title' => 'Evento operativo' ) ) ), 'Core save works with commerce loaded but Woo absent' );
ob_start(); Admin\render_ticket_types( $absent_id ); $absent_html = ob_get_clean();
t_ok( str_contains( $absent_html, 'WooCommerce' ) && ! str_contains( $absent_html, '<form' ), 'missing dependency UI is informational without fake save' );
$GLOBALS['t_options'][ 'lcda_ticket_event_' . $absent_id ] = 1;
t_eq( false, update_post_meta( $absent_id, '_casa_capacity', 50 ), 'deactivated dependency retains commercial capacity guard' );
unset( $GLOBALS['t_options'][ 'lcda_ticket_event_' . $absent_id ] );

/** Only the CRUD surface used by T1; these doubles do not certify Woo compatibility. */
function t_enable_ticket_doubles() {
	class WC_Product_Variable {
		public $data = array( 'id' => 0, 'parent_id' => 0, 'total_sales' => 0 );
		public $meta = array();
		public function __call( $method, $args ) {
			$key = substr( $method, 4 );
			if ( 0 === strpos( $method, 'set_' ) ) { $this->data[ $key ] = $args[0]; return; }
			if ( 0 === strpos( $method, 'get_' ) ) { return $this->data[ $key ] ?? ''; }
			throw new RuntimeException( 'Unexpected method ' . $method );
		}
		public function is_type( $type ) { return $type === ( $this instanceof WC_Product_Variation ? 'variation' : 'variable' ); }
		public function get_meta( $key ) { return $this->meta[ $key ] ?? ''; }
		public function update_meta_data( $key, $value ) { $this->meta[ $key ] = $value; }
		public function save() {
			do_action( 'woocommerce_before_product_object_save', $this );
			++$GLOBALS['tt_saves'];
			if ( $GLOBALS['tt_saves'] === ( $GLOBALS['tt_fail_at'] ?? -1 ) ) { throw new RuntimeException( 'Injected CRUD failure' ); }
			if ( ! $this->get_id() ) { $this->data['id'] = ++$GLOBALS['tt_next']; }
			$GLOBALS['tt_products'][ $this->get_id() ] = clone $this;
			return $this->get_id();
		}
		public static function sync( $product ) { $product->save(); }
	}
	class WC_Product_Variation extends WC_Product_Variable {}
	class WC_Product_Attribute {
		public $data = array();
		public function __call( $method, $args ) { $this->data[ substr( $method, 4 ) ] = $args[0]; }
	}
	function wc_get_product( $id ) { return isset( $GLOBALS['tt_products'][ $id ] ) ? clone $GLOBALS['tt_products'][ $id ] : false; }
	function wc_get_products( $args ) {
		return array_values( array_map( static fn( $p ) => clone $p, array_filter( $GLOBALS['tt_products'], static function ( $p ) use ( $args ) {
			return ( ! isset( $args['lcda_event'] ) || (int) $p->get_meta( '_lcda_ticket_event' ) === $args['lcda_event'] )
				&& ( ! isset( $args['parent'] ) || $p->get_parent_id() === $args['parent'] )
				&& ( ! isset( $args['type'] ) || $p->is_type( $args['type'] ) );
		} ) ) );
	}
	function wc_get_product_id_by_sku( $sku ) { foreach ( $GLOBALS['tt_products'] as $p ) { if ( $p->get_sku() === $sku ) { return $p->get_id(); } } return 0; }
	function wc_get_orders( $args ) {
		if ( ! empty( $GLOBALS['tt_orders_unavailable'] ) ) { throw new RuntimeException( 'Order datastore unavailable' ); }
		$orders = array_values( $GLOBALS['tt_orders'] );
		$limit = $args['limit'] ?? 50;
		$page = $args['paged'] ?? 1;
		return (object) array( 'orders' => array_slice( $orders, ( $page - 1 ) * $limit, $limit ),
			'max_num_pages' => (int) ceil( count( $orders ) / $limit ) );
	}
	function wc_get_order_statuses() { return array( 'wc-pending' => 'Pending', 'wc-completed' => 'Completed' ); }
	function wc_get_held_stock_quantity( $p ) { return $GLOBALS['tt_held'][ $p->get_id() ] ?? 0; }
	function get_woocommerce_currency() { return $GLOBALS['tt_currency']; }
	function wc_get_price_decimals() { return 2; }
}
t_enable_ticket_doubles();
$GLOBALS['wpdb'] = new class {
	public $prefix = 'unit_';
	public $busy = false;
	public $queries = array();
	public function prepare( $sql, ...$args ) { return $sql . ':' . implode( ',', $args ); }
	public function get_var( $sql ) { $this->queries[] = $sql; return $this->busy ? '0' : '1'; }
};
function tt_fixture() {
	Commerce\release_all();
	$GLOBALS['lcda_capacity_locks'] = array();
	t_reset_store();
	$GLOBALS['t_options'] = array( 'timezone_string' => 'America/Argentina/Buenos_Aires' );
	$GLOBALS['t_caps'] = array_fill_keys( array( 'edit_post', 'manage_casa_ticket_types', 'edit_casa_ticket_prices', 'edit_casa_ticket_capacities' ), true );
	$GLOBALS['t_denied_posts'] = array();
	$GLOBALS['tt_products'] = $GLOBALS['tt_orders'] = $GLOBALS['tt_held'] = array();
	$GLOBALS['tt_next'] = 10000; $GLOBALS['tt_saves'] = 0; $GLOBALS['tt_fail_at'] = -1;
	$GLOBALS['tt_currency'] = 'ARS'; $GLOBALS['wpdb']->busy = false;
	$id = t_add_post( array( 'post_type' => 'casa_evento', 'post_status' => 'draft', 'post_title' => 'Evento', 'post_content' => '<!-- wp:custom -->intact<!-- /wp:custom -->' ) );
	$GLOBALS['t_meta'][ $id ] = array( '_casa_uuid' => wp_generate_uuid4(), '_casa_capacity' => 100, '_casa_start' => '2026-11-20 21:00:00', '_casa_timezone' => 'America/Argentina/Buenos_Aires' );
	return $id;
}
function tt_fields( $overrides = array() ) { return array_merge( array( 'name' => 'General', 'price' => '1200,50', 'capacity' => '80', 'active' => '0', 'cutoff' => '', 'maximum' => '10' ), $overrides ); }
function tt_order( $product_id, $variation_id = 0 ) {
	$item = new class( $product_id, $variation_id ) {
		private $product_id; private $variation_id;
		public function __construct( $product_id, $variation_id ) { $this->product_id = $product_id; $this->variation_id = $variation_id; }
		public function get_product_id() { return $this->product_id; }
		public function get_variation_id() { return $this->variation_id; }
	};
	return new class( $item ) {
		private $item;
		public function __construct( $item ) { $this->item = $item; }
		public function get_items( $type = 'line_item' ) { return array( $this->item ); }
	};
}
function tt_save( $id, $tid = 0, $input = null, $key = null, $state = null ) {
	$view = Commerce\configuration( $id );
	return Commerce\save_type( $id, $tid, $input ?? tt_fields(), $state ?? $view['state'], $key ?? wp_generate_uuid4() );
}
function tt_error( $code, $result, $message ) { t_ok( is_wp_error( $result ) && $code === $result->get_error_code(), $message ); }

t_group( 'T1/validation' );
$id = tt_fixture();
foreach ( array( array( 'name' => '' ), array( 'name' => '<b>Bad</b>' ), array( 'name' => str_repeat( 'x', 121 ) ), array( 'price' => '-1' ), array( 'price' => '1e3' ), array( 'price' => '1.001' ), array( 'price' => array() ), array( 'capacity' => '1.5' ), array( 'capacity' => '-1' ), array( 'capacity' => '01' ), array( 'maximum' => '0' ), array( 'maximum' => '11' ), array( 'active' => 'yes' ), array( 'cutoff' => '2026-02-30T12:00' ), array( 'parent_id' => '2' ) ) as $bad ) {
	t_ok( is_wp_error( tt_save( $id, 0, tt_fields( $bad ) ) ), 'invalid input rejected: ' . json_encode( $bad ) );
}
t_eq( 0, $GLOBALS['tt_saves'], 'validation failures never start CRUD' );
tt_error( 'casa_tickets_free', tt_save( $id, 0, tt_fields( array( 'price' => '0', 'active' => '1' ) ) ), 'free active policy refused' );
$zero = tt_save( $id, 0, tt_fields( array( 'price' => '0' ) ) );
t_ok( ! is_wp_error( $zero ), 'zero inactive price representable' );

t_group( 'T1/relationships and retry' );
$id = tt_fixture();
$new_event = t_add_post( array( 'post_type' => 'casa_evento', 'post_status' => 'draft', 'post_title' => 'Inicial' ) );
t_ok( ! is_wp_error( Core\save_event( $new_event, array( 'title' => 'Inicializado' ) ) ), 'ordinary Event initialization works when Woo is available' );
$id = tt_fixture(); $key = wp_generate_uuid4(); $state = Commerce\configuration( $id )['state'];
t_eq( 0, count( $GLOBALS['tt_products'] ), 'GET has no side effects' );
$created = tt_save( $id, 0, tt_fields(), $key, $state );
t_ok( ! is_wp_error( $created ), 'create first type' );
if ( is_wp_error( $created ) ) { throw new RuntimeException( $created->get_error_message() ); }
$tid = $created['type_id']; $parent = Commerce\parent_product( $id ); $type = wc_get_product( $tid );
t_eq( 2, count( $GLOBALS['tt_products'] ), 'one parent and one type' );
t_eq( $parent->get_id(), $type->get_parent_id(), 'variation relation' );
t_eq( '1200.50', $type->get_regular_price(), 'localized decimal normalized without float arithmetic' );
t_eq( true, $type->get_manage_stock(), 'individual stock' );
t_eq( false, $parent->get_manage_stock(), 'parent does not manage stock' );
t_eq( 'no', $type->get_backorders(), 'backorders disabled' );
t_eq( 'private', $type->get_status(), 'private variation' );
t_eq( 'hidden', $parent->get_catalog_visibility(), 'parent hidden' );
t_eq( true, Commerce\zero_consumption( $parent ), 'signed new configuration with no orders has zero known consumption' );
$count = $GLOBALS['tt_saves'];
t_eq( true, tt_save( $id, 0, tt_fields(), $key, $state )['replayed'], 'identical retry returns original' );
t_eq( $count, $GLOBALS['tt_saves'], 'retry never writes' );
tt_error( 'casa_tickets_conflict', tt_save( $id, 0, tt_fields( array( 'price' => '2' ) ), $key, $state ), 'key reuse with changed payload conflicts' );
tt_error( 'casa_tickets_stale', tt_save( $id, 0, tt_fields( array( 'name' => 'Early' ) ), null, $state ), 'stale concurrent create rejected' );
tt_error( 'casa_tickets_duplicate', tt_save( $id, 0, tt_fields( array( 'name' => '  GENERAL  ' ) ) ), 'normalized duplicate rejected' );
$second = tt_save( $id, 0, tt_fields( array( 'name' => 'Anticipada', 'capacity' => '70' ) ) );
t_ok( ! is_wp_error( $second ), 'sum 150 allowed above event 100' );
t_eq( 3, count( $GLOBALS['tt_products'] ), 'second type reuses parent' );
$before = serialize( $GLOBALS['t_posts'] );
$edited = tt_save( $id, $tid, array( 'name' => 'General nueva', 'price' => '2000', 'active' => '1', 'cutoff' => '2026-12-20T21:00' ) );
t_ok( ! is_wp_error( $edited ), 'edit nominal, name, activation and valid cutoff' );
t_eq( $type->get_meta( '_lcda_ticket_uuid' ), wc_get_product( $tid )->get_meta( '_lcda_ticket_uuid' ), 'rename preserves identity' );
t_eq( 80, wc_get_product( $tid )->get_stock_quantity(), 'price/name edit preserves stock' );
t_eq( $before, serialize( $GLOBALS['t_posts'] ), 'commercial operation never writes Event content' );
$view = Commerce\configuration( $id );
t_eq( $view['event_cutoff_gmt'], $view['types'][0]['effective_cutoff_gmt'], 'late type cutoff capped by Event' );
t_eq( true, tt_save( $id, 0, tt_fields(), $key, $state )['replayed'], 'original create retry after edit remains idempotent' );
t_eq( '2000.00', wc_get_product( $tid )->get_regular_price(), 'old retry does not revert subsequent price' );
$GLOBALS['tt_currency'] = 'USD';
 t_ok( ! is_wp_error( tt_save( $id, $tid, array( 'name' => 'Nombre con moneda cambiada', 'price' => '2000.00', 'active' => '0', 'cutoff' => '' ) ) ), 'currency mismatch permits non-price changes with unchanged UI price' );
 t_eq( '2000.00', wc_get_product( $tid )->get_regular_price(), 'non-price change preserves old nominal and currency snapshot' );
tt_error( 'casa_tickets_currency', tt_save( $id, $tid, array( 'price' => '3' ) ), 'currency change never converts' );
$GLOBALS['tt_currency'] = 'ARS';

t_group( 'T1/authorization and transport' );
foreach ( array( 'manage_casa_ticket_types', 'edit_casa_ticket_prices', 'edit_casa_ticket_capacities' ) as $cap ) {
	$GLOBALS['t_caps'][ $cap ] = false;
	$result = Commerce\save_type( $id, 0, tt_fields( array( 'name' => 'Forbidden' ) ), $view['state'], wp_generate_uuid4() );
	tt_error( 'casa_tickets_forbidden', $result, 'creation requires ' . $cap );
	$GLOBALS['t_caps'][ $cap ] = true;
}
$GLOBALS['t_denied_posts'] = array( $id );
t_ok( is_wp_error( Commerce\configuration( $id ) ), 'commercial caps do not override event denial' );
t_ok( is_wp_error( Commerce\save_type( $id, $tid, array( 'name' => 'Denied' ), $view['state'], wp_generate_uuid4() ) ), 'write also denies event' );
$GLOBALS['t_denied_posts'] = array();
tt_error( 'casa_tickets_type', tt_save( $id, 999, array( 'name' => 'Other event type' ) ), 'foreign variation rejected' );
$request = array( 'event_id' => (string) $id, 'type_id' => (string) $tid, 'ticket_fields' => array( 'name' => 'Transport' ), 'ticket_state' => Commerce\configuration( $id )['state'], 'ticket_key' => wp_generate_uuid4(), 'ticket_nonce' => 'forged' );
tt_error( 'casa_tickets_nonce', Admin\ticket_type_submit( $request, 'POST' ), 'CSRF refused' );
tt_error( 'casa_tickets_method', Admin\ticket_type_submit( $request, 'GET' ), 'GET writes refused' );
$request['ticket_nonce'] = wp_create_nonce( 'casa-ticket:' . $id . ':' . $tid );
t_ok( ! is_wp_error( Admin\ticket_type_submit( $request, 'POST' ) ), 'valid transport saves' );
$GLOBALS['t_caps']['edit_casa_ticket_prices'] = false;
t_ok( ! is_wp_error( tt_save( $id, $tid, array( 'name' => 'Only name' ) ) ), 'omitted unauthorized price remains intact' );
tt_error( 'casa_tickets_forbidden', tt_save( $id, $tid, array( 'price' => '1' ) ), 'revoked price permission enforced' );
$GLOBALS['t_caps']['edit_casa_ticket_prices'] = true;
ob_start(); Admin\render_ticket_types( $id ); $html = ob_get_clean();
t_ok( str_contains( $html, 'ticket_nonce' ) && str_contains( $html, 'ticket_state' ) && str_contains( $html, 'ticket_key' ), 'forms contain scoped transport controls' );
t_ok( str_contains( $html, 'venta propia' ) && ! str_contains( $html, 'Comprar' ), 'configuration UI does not offer sales' );
t_ok( isset( Admin\ticket_configuration_link( array(), get_post( $id ) )['casa_ticket_types'] ), 'authorized administrator or Programador has direct list link' );
$GLOBALS['t_denied_posts'] = array( $id );
t_eq( array(), Admin\ticket_configuration_link( array(), get_post( $id ) ), 'unauthorized event never gets commercial link' );
$GLOBALS['t_denied_posts'] = array();

t_group( 'T1/capacity and history' );
t_eq( true, Commerce\capacity_allowed( true, $id, 50, 100 ), 'new untouched configuration permits global reduction' );
Commerce\release_all(); $GLOBALS['lcda_capacity_locks'] = array();
$GLOBALS['tt_orders'] = array( tt_order( 99999 ) );
t_eq( true, Commerce\zero_consumption( Commerce\parent_product( $id ) ), 'unrelated order does not make ticket consumption unknown' );
t_ok( ! is_wp_error( tt_save( $id, 0, tt_fields( array( 'name' => 'Con pedido ajeno' ) ) ) ), 'unrelated order does not block ticket type creation' );
t_ok( ! is_wp_error( tt_save( $id, $tid, array( 'capacity' => '90' ) ) ), 'unrelated order does not block capacity update' );
$GLOBALS['tt_orders_unavailable'] = true;
t_eq( false, Commerce\zero_consumption( Commerce\parent_product( $id ) ), 'order query failure cannot prove zero consumption' );
tt_error( 'casa_tickets_unknown', tt_save( $id, $tid, array( 'capacity' => '100' ) ), 'order query failure blocks stock set' );
$GLOBALS['tt_orders_unavailable'] = false;
$history = array( tt_order( $parent->get_id(), $tid ) ); $GLOBALS['tt_orders'] = $history;
tt_error( 'casa_tickets_unknown', tt_save( $id, $tid, array( 'capacity' => '100' ) ), 'unknown consumption blocks absolute increase' );
tt_error( 'casa_tickets_unknown', tt_save( $id, $tid, array( 'capacity' => '70' ) ), 'unknown consumption blocks reduction' );
tt_error( 'casa_tickets_unknown', tt_save( $id, 0, tt_fields( array( 'name' => 'New' ) ) ), 'unknown consumption blocks new stock' );
t_ok( ! is_wp_error( tt_save( $id, $tid, array( 'price' => '2400' ) ) ), 'history still permits future price editing' );
t_eq( $history, $GLOBALS['tt_orders'], 'historical order data untouched' );
$title_before = get_post( $id )->post_title;
tt_error( 'casa_tickets_unknown', Core\save_event( $id, array( 'capacity' => 50, 'title' => 'Must not persist' ) ), 'E3 adapter refuses capacity before any editorial write' );
t_eq( $title_before, get_post( $id )->post_title, 'refused mixed payload never writes title' );
$rest = Core\validate_rest_insert( (object) array( 'ID' => $id, 'post_status' => 'draft' ), new WP_REST_Request( array( 'meta' => array( '_casa_capacity' => 50 ) ) ) );
t_ok( is_wp_error( $rest ), 'native REST validates commercial capacity even in draft' );
t_eq( false, update_post_meta( $id, '_casa_capacity', 50 ), 'native metadata reduction blocked' );
t_eq( 100, Core\Event::get( $id )->capacity(), 'global unchanged' );
t_eq( true, update_post_meta( $id, '_casa_capacity', 120 ), 'global increase safe without stock mutation' );
$GLOBALS['t_caps']['edit_casa_ticket_capacities'] = false;
t_eq( false, update_post_meta( $id, '_casa_capacity', 150 ), 'native route requires commercial cap' );
$GLOBALS['t_caps']['edit_casa_ticket_capacities'] = true;
t_eq( false, delete_post_meta( $id, '_casa_capacity' ), 'default restoration cannot reduce occupied global capacity' );
$GLOBALS['tt_orders'] = array();
$GLOBALS['tt_products'][ $tid ]->data['stock_quantity'] = 3;
t_eq( false, Commerce\zero_consumption( Commerce\parent_product( $id ) ), 'external stock drift invalidates proof' );
tt_error( 'casa_tickets_unknown', tt_save( $id, $tid, array( 'capacity' => '80' ) ), 'cannot restore unverified stock' );

t_group( 'T1/no sales and preservation' );
$type = wc_get_product( $tid );
t_eq( false, apply_filters( 'woocommerce_is_purchasable', true, $type ), 'purchasable denied' );
t_eq( false, apply_filters( 'woocommerce_variation_is_purchasable', true, $type ), 'variation denied' );
t_eq( false, apply_filters( 'woocommerce_add_to_cart_validation', true, $parent->get_id(), 1, $tid ), 'direct cart denied' );
t_eq( false, apply_filters( 'casa_eventos/ticket_sales_available', true ), 'public Event sales unavailable' );
t_eq( false, Commerce\guard_delete( null, (object) array( 'post_type' => 'product_variation', 'ID' => $tid ) ), 'type cannot be deleted' );
t_eq( false, Commerce\guard_delete( null, (object) array( 'post_type' => 'product', 'ID' => $parent->get_id() ) ), 'parent cannot be deleted' );
$threw = false; try { $type->set_regular_price( '1' ); $type->save(); } catch ( Exception $e ) { $threw = true; }
t_ok( $threw, 'external Woo write blocked outside service' );
$synced = false; try { WC_Product_Variable::sync( wc_get_product( $parent->get_id() ) ); $synced = true; } catch ( Exception $e ) {}
t_ok( $synced, 'Woo derived parent sync remains permitted after type save' );
$threw = false; try { $foreign = new WC_Product_Variation(); $foreign->set_parent_id( $parent->get_id() ); $foreign->save(); } catch ( Exception $e ) { $threw = true; }
t_ok( $threw, 'external new variation cannot bypass the Event service' );
$order = new class( wc_get_product( $tid ) ) {
	public $product; public function __construct( $p ) { $this->product = $p; }
	public function get_items() { return array( $this ); } public function get_product() { return $this->product; }
};
t_eq( false, Commerce\deny_order_payment( true, $order ), 'existing order pay refused' );
t_eq( true, Commerce\deny_purchase( true, new WC_Product_Variable() ), 'unrelated commerce unaffected' );

t_group( 'T1/concurrency and incomplete persistence' );
$id = tt_fixture(); $GLOBALS['wpdb']->busy = true;
tt_error( 'casa_tickets_busy', tt_save( $id ), 'busy or unsupported lock fails closed' );
t_eq( 0, $GLOBALS['tt_saves'], 'busy lock performs no CRUD' );
$GLOBALS['wpdb']->busy = false; $GLOBALS['tt_fail_at'] = 4; $key = wp_generate_uuid4();
tt_error( 'casa_tickets_partial', tt_save( $id, 0, tt_fields(), $key ), 'failure after type persistence never reports success' );
$GLOBALS['tt_fail_at'] = -1;
tt_error( 'casa_tickets_partial', tt_save( $id, 0, tt_fields(), $key ), 'partial create retry requires review' );
tt_error( 'casa_tickets_partial', tt_save( $id, 0, tt_fields(), wp_generate_uuid4() ), 'partial create with new key also requires review' );
t_eq( 2, count( $GLOBALS['tt_products'] ), 'partial retry never creates another type' );
$id = tt_fixture(); $created = tt_save( $id ); $tid = $created['type_id'];
$GLOBALS['tt_fail_at'] = $GLOBALS['tt_saves'] + 2;
tt_error( 'casa_tickets_partial', tt_save( $id, $tid, array( 'name' => 'Interrumpido' ) ), 'existing type partial write reports error' );
$GLOBALS['tt_fail_at'] = -1;
tt_error( 'casa_tickets_partial', tt_save( $id, $tid, array( 'name' => 'Otra solicitud' ), wp_generate_uuid4() ), 'new key cannot continue an incomplete existing type write' );
$id = tt_fixture(); $key = wp_generate_uuid4();
tt_error( 'casa_tickets_partial', ( function () use ( $id, $key ) {
	$GLOBALS['tt_fail_at'] = 4;
	return tt_save( $id, 0, tt_fields(), $key );
} )(), 'new fixture partial create still fails' );
$GLOBALS['tt_fail_at'] = -1;
$parent = Commerce\parent_product( $id );
$duplicate = clone $parent; $duplicate->data['id'] = ++$GLOBALS['tt_next']; $GLOBALS['tt_products'][ $duplicate->get_id() ] = $duplicate;
tt_error( 'casa_tickets_relationship', Commerce\configuration( $id ), 'multiple parents block rather than choose arbitrary one' );
Commerce\release_all(); $GLOBALS['lcda_capacity_locks'] = array();
$GLOBALS['tt_products'] = $GLOBALS['tt_orders'] = array();
$GLOBALS['t_caps'] = array( 'manage_options' => false, 'edit_post' => true );
$GLOBALS['t_options'] = array( 'timezone_string' => 'America/Argentina/Buenos_Aires' );
t_reset_store();
