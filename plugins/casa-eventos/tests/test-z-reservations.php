<?php
/** T2 coordinator decisions with the existing Woo doubles and a deterministic SQL stand-in. */
use CasaEventos\Commerce;
use CasaEventos\Core;

class T2_Db {
	public $prefix = 'unit_';
	public $last_error = '';
	public $busy = false;
	public $rows = array();
	public $next_id = 1;
	public $lose_insert_response = false;

	public function prepare( $sql, ...$args ) {
		$escaped = array_map( static function ( $value ) {
			return is_int( $value ) ? $value : "'" . str_replace( "'", "''", (string) $value ) . "'";
		}, $args );
		return vsprintf( $sql, $escaped );
	}
	public function get_var( $sql ) {
		if ( str_contains( $sql, 'GET_LOCK(' ) ) { return $this->busy ? '0' : '1'; }
		if ( str_contains( $sql, 'IS_USED_LOCK(' ) ) { return $this->busy ? '0' : '1'; }
		if ( str_contains( $sql, 'RELEASE_LOCK(' ) ) { return '1'; }
		if ( preg_match( '/COUNT\(\*\).*event_id = ([0-9]+)/', $sql, $match ) ) {
			return count( array_filter( $this->rows, static fn( $r ) => (int) $r->event_id === (int) $match[1] ) );
		}
		return null;
	}
	public function get_row( $sql ) {
		if ( ! preg_match( '/event_id = ([0-9]+) AND (request_hash = \'([a-f0-9]+)\'|id = ([0-9]+))/', $sql, $match ) ) { return null; }
		foreach ( $this->rows as $row ) {
			if ( (int) $row->event_id === (int) $match[1]
				&& ( isset( $match[3] ) && $match[3] !== '' ? $row->request_hash === $match[3] : (int) $row->id === (int) $match[4] ) ) {
				return clone $row;
			}
		}
		return null;
	}
	public function get_results( $sql ) {
		preg_match( '/event_id = ([0-9]+)/', $sql, $match );
		return array_values( array_filter( $this->rows, static fn( $r ) => (int) $r->event_id === (int) ( $match[1] ?? 0 ) ) );
	}
	public function insert( $table, $data, $formats ) {
		foreach ( $this->rows as $row ) {
			if ( (int) $row->event_id === (int) $data['event_id'] && $row->request_hash === $data['request_hash'] ) { return false; }
		}
		$data['id'] = $this->next_id++;
		$this->rows[] = (object) $data;
		if ( $this->lose_insert_response ) { $this->lose_insert_response = false; return false; }
		return 1;
	}
	public function update( $table, $data, $where, $formats, $where_formats ) {
		foreach ( $this->rows as $row ) {
			if ( (int) $row->id === (int) $where['id'] && (int) $row->event_id === (int) $where['event_id']
				&& $row->status === $where['status'] ) {
				foreach ( $data as $key => $value ) { $row->$key = $value; }
				return 1;
			}
		}
		return 0;
	}
	public function query( $sql ) {
		if ( $this->busy ) { return 0; }
		if ( str_starts_with( $sql, 'INSERT INTO ' ) ) {
			if ( ! preg_match( "/SELECT ([0-9]+),'([a-f0-9]+)','([a-f0-9]+)','([^']+)','([^']+)','checkout_active',([0-9]+),([0-9]+),'' WHERE/", $sql, $m ) ) { return false; }
			$result = $this->insert( '', array( 'event_id' => (int) $m[1], 'request_hash' => $m[2], 'payload_hash' => $m[3],
				'quantities' => $m[4], 'snapshot' => $m[5], 'status' => 'checkout_active', 'created_at' => (int) $m[6],
				'expires_at' => (int) $m[7], 'attempt_hash' => '', 'order_id' => null, 'payment_hash' => '', 'refund_hash' => '' ), array() );
			return $result;
		}
		if ( preg_match( "/SET order_id = ([0-9]+) WHERE event_id = ([0-9]+) AND id = ([0-9]+)/", $sql, $m ) ) {
			foreach ( $this->rows as $row ) {
				if ( (int) $row->event_id === (int) $m[2] && (int) $row->id === (int) $m[3] && null === $row->order_id ) {
					$row->order_id = (int) $m[1]; return 1;
				}
			}
			return 0;
		}
		if ( ! preg_match( "/SET status = '([a-z_]+)'(.*?) WHERE event_id = ([0-9]+) AND id = ([0-9]+) AND status = '([a-z_]+)'/", $sql, $match ) ) { return false; }
		foreach ( $this->rows as $row ) {
			if ( (int) $row->event_id === (int) $match[3] && (int) $row->id === (int) $match[4]
				&& $row->status === $match[5]
				&& ( ! preg_match( '/expires_at <= ([0-9]+)/', $sql, $expiry ) || (int) $row->expires_at <= (int) $expiry[1] ) ) {
				$row->status = $match[1];
				preg_match_all( "/, (attempt_hash|payment_hash|refund_hash) = '([a-f0-9]*)'/", $match[2], $fields, PREG_SET_ORDER );
				foreach ( $fields as $field ) { $row->{$field[1]} = $field[2]; }
				return 1;
			}
		}
		return 0;
	}
}

function t2_fixture() {
	Commerce\release_all();
	$GLOBALS['wpdb'] = new T2_Db();
	$id = tt_fixture();
	$GLOBALS['t_posts'][ $id ]->post_status = 'publish';
	$GLOBALS['t_meta'][ $id ]['_casa_start'] = '2099-11-20 21:00:00';
	$GLOBALS['t_meta'][ $id ]['_casa_access_mode'] = 'tickets';
	$GLOBALS['t_options'][ Commerce\RESERVATION_SCHEMA_OPTION ] = Commerce\RESERVATION_SCHEMA;
	return $id;
}
function t2_key() { return wp_generate_uuid4(); }
function t2_error( $code, $result, $message ) { t_ok( is_wp_error( $result ) && 'casa_tickets_' . $code === $result->get_error_code(), $message ); }

t_group( 'T2/reservation admission and replay' );
$id = t2_fixture();
$first = tt_save( $id, 0, tt_fields( array( 'capacity' => '8', 'active' => '1' ) ) );
$second = tt_save( $id, 0, tt_fields( array( 'name' => 'Anticipada', 'capacity' => '8', 'active' => '1' ) ) );
t_ok( ! is_wp_error( $first ) && ! is_wp_error( $second ), 'two active T1 types configured' );
$a = $first['type_id']; $b = $second['type_id'];
$now = strtotime( '2030-01-01 00:00:00 UTC' );
$key = t2_key();
$one = Commerce\reserve( $id, array( $a => 6, $b => 4 ), $key, $now );
t_ok( ! is_wp_error( $one ) && 'checkout_active' === $one['status'], 'vector admitted as one reservation' );
t_eq( $now + 1200, $one['expires_at'], '20 minute fixed server expiry' );
t_eq( true, Commerce\reserve( $id, array( $b => 4, $a => 6 ), $key, $now + 60 )['replayed'], 'same key and vector replayed' );
t_eq( 1, count( $GLOBALS['wpdb']->rows ), 'replay never inserts' );
t2_error( 'conflict', Commerce\reserve( $id, array( $a => 5 ), $key, $now ), 'same key different vector rejected' );
t2_error( 'quantity', Commerce\reserve( $id, array( $a => 7, $b => 4 ), t2_key(), $now ), 'aggregate ten maximum enforced' );
t_eq( 10, Commerce\reservation_held( $GLOBALS['wpdb']->rows, $now )['global'], 'both types count toward one global hold' );

t_group( 'T2/type and global capacity' );
t2_error( 'capacity', Commerce\reserve( $id, array( $a => 3 ), t2_key(), $now ), 'per-type remaining stock excludes hold' );
$GLOBALS['t_meta'][ $id ]['_casa_capacity'] = 10;
t2_error( 'capacity', Commerce\reserve( $id, array( $b => 1 ), t2_key(), $now ), 'global capacity overrides summed type stock' );
t_eq( false, Commerce\zero_consumption( Commerce\parent_product( $id ) ), 'T1 stock edits stop when ledger has history' );

t_group( 'T2/expiry and unknown payment' );
t_eq( 'expired', Commerce\reserve( $id, array( $a => 6, $b => 4 ), $key, $now + 1200 )['status'], 'expired retry never extends checkout' );
t_eq( 0, Commerce\reservation_held( $GLOBALS['wpdb']->rows, $now + 1200 )['global'], 'expired active hold is excluded immediately' );
t_eq( 1, Commerce\expire_reservation( $id, $one['id'], $now + 1200 ), 'worker records expiry once' );
t_eq( 0, Commerce\expire_reservation( $id, $one['id'], $now + 1200 ), 'worker retry has no effect' );
$two_key = t2_key();
$two = Commerce\reserve( $id, array( $a => 6, $b => 4 ), $two_key, $now + 1200 );
t_ok( ! is_wp_error( $two ), 'expired capacity may be reused' );
$attempt_key = t2_key();
$pending = Commerce\retain_unknown_payment( $id, $two['id'], $two_key, $attempt_key, $now + 1201 );
t_eq( 'payment_pending', $pending['status'], 'unknown payment retains hold' );
t_eq( 0, Commerce\expire_reservation( $id, $two['id'], $now + 2400 ), 'worker cannot expire unknown payment' );
t_eq( 10, Commerce\reservation_held( $GLOBALS['wpdb']->rows, $now + 3600 )['global'], 'pending hold survives original expiry' );
t2_error( 'capacity', Commerce\reserve( $id, array( $a => 1 ), t2_key(), $now + 3600 ), 'unknown outcome cannot oversell' );

t_group( 'T2/payment lifecycle and owner key' );
t2_error( 'owner', Commerce\record_payment_outcome( $id, $two['id'], t2_key(), $attempt_key, 'paid', 'receipt-1', $now + 3601 ), 'different owner cannot settle' );
$paid = Commerce\record_payment_outcome( $id, $two['id'], $two_key, $attempt_key, 'paid', 'receipt-1', $now + 3601 );
t_eq( 'paid', $paid['status'], 'verified adapter outcome transitions pending to paid' );
t_eq( true, Commerce\record_payment_outcome( $id, $two['id'], $two_key, $attempt_key, 'paid', 'receipt-1', $now + 3602 )['replayed'], 'paid result idempotent' );
t_eq( 10, Commerce\reservation_held( $GLOBALS['wpdb']->rows, $now + 3602 )['global'], 'paid capacity retained as sold occupancy' );
t2_error( 'conflict', Commerce\record_payment_outcome( $id, $two['id'], $two_key, $attempt_key, 'failed', 'receipt-2', $now + 3602 ), 'late failure cannot undo paid state' );
$refunded = Commerce\record_confirmed_refund( $id, $two['id'], $two_key, 'refund-1', $now + 3603 );
t_eq( 'refunded', $refunded['status'], 'confirmed full refund releases capacity' );
t_eq( true, Commerce\record_confirmed_refund( $id, $two['id'], $two_key, 'refund-1', $now + 3604 )['replayed'], 'refund idempotent' );
t_eq( 0, Commerce\reservation_held( $GLOBALS['wpdb']->rows, $now + 3604 )['global'], 'refund releases once' );

t_group( 'T2/interrupted write' );
$id = t2_fixture();
$created = tt_save( $id, 0, tt_fields( array( 'capacity' => '1', 'active' => '1' ) ) );
$GLOBALS['wpdb']->lose_insert_response = true;
$key = t2_key();
$lost = Commerce\reserve( $id, array( $created['type_id'] => 1 ), $key, $now );
t2_error( 'uncertain', $lost, 'lost insert receipt is not success' );
$retry = Commerce\reserve( $id, array( $created['type_id'] => 1 ), $key, $now + 1 );
t_ok( ! is_wp_error( $retry ) && $retry['replayed'], 'same key recovers committed insert' );
t_eq( 1, count( $GLOBALS['wpdb']->rows ), 'recovery inserts no duplicate' );
$bound = Commerce\bind_reservation_order( $id, $retry['id'], $key, 42 );
t_ok( is_array( $bound ) && ! $bound['replayed'], 'one order reference bound' );
t_eq( true, Commerce\bind_reservation_order( $id, $retry['id'], $key, 42 )['replayed'], 'order binding replays' );
t2_error( 'conflict', Commerce\bind_reservation_order( $id, $retry['id'], $key, 43 ), 'different order cannot replace binding' );
$GLOBALS['wpdb']->busy = true;
t2_error( 'busy', Commerce\reserve( $id, array( $created['type_id'] => 1 ), t2_key(), $now ), 'busy event lock fails closed' );
