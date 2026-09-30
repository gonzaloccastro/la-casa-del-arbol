<?php
/**
 * Static test bootstrap: loads the Event Core files with a minimal
 * WordPress stand-in (hooks, options, an in-memory post/meta/term store).
 *
 * This does NOT replace runtime QA in a real WordPress: queries (WP_Query),
 * REST dispatch, the block editor and the admin screens are verified there.
 * What runs here is the plugin's own logic: datetime rules, state,
 * validation, sanitizers, sync/derivation, UUID guards and the REST
 * enforcement decision.
 *
 * CLI only.
 *
 * @package CasaEventos
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

// Prove the plugin never relies on PHP's default timezone.
date_default_timezone_set( 'Pacific/Kiritimati' ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.timezone_change_date_default_timezone_set

define( 'ABSPATH', __DIR__ . '/' );

// ----- Hooks -------------------------------------------------------------------

$GLOBALS['t_hooks']   = array();
$GLOBALS['t_actions'] = array();

function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['t_hooks'][ $hook ][ $priority ][] = array( $callback, $args );
	return true;
}
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	return add_filter( $hook, $callback, $priority, $args );
}
function remove_all_filters( $hook ) {
	unset( $GLOBALS['t_hooks'][ $hook ] );
}
function apply_filters( $hook, $value, ...$rest ) {
	if ( empty( $GLOBALS['t_hooks'][ $hook ] ) ) {
		return $value;
	}
	ksort( $GLOBALS['t_hooks'][ $hook ] );
	foreach ( $GLOBALS['t_hooks'][ $hook ] as $callbacks ) {
		foreach ( $callbacks as list( $callback, $n ) ) {
			$value = call_user_func_array( $callback, array_slice( array_merge( array( $value ), $rest ), 0, $n ) );
		}
	}
	return $value;
}
function do_action( $hook, ...$args ) {
	$GLOBALS['t_actions'][] = array( $hook, $args );
	if ( empty( $GLOBALS['t_hooks'][ $hook ] ) ) {
		return;
	}
	ksort( $GLOBALS['t_hooks'][ $hook ] );
	foreach ( $GLOBALS['t_hooks'][ $hook ] as $callbacks ) {
		foreach ( $callbacks as list( $callback, $n ) ) {
			call_user_func_array( $callback, array_slice( $args, 0, $n ) );
		}
	}
}
function t_actions( $hook ) {
	return array_values( array_filter( $GLOBALS['t_actions'], static fn( $a ) => $a[0] === $hook ) );
}

// ----- i18n / formatting ---------------------------------------------------------

function __( $text ) {
	return $text;
}
function esc_html__( $text ) {
	return $text;
}
function sanitize_text_field( $str ) {
	$str = strip_tags( (string) $str );
	$str = preg_replace( '/[\r\n\t ]+/', ' ', $str );
	return trim( $str );
}
function esc_url_raw( $url ) {
	return (string) $url;
}
/** Like core: percent-encoded octets (non-ASCII slugs) are kept, lowercased. */
function sanitize_title( $title ) {
	return strtolower( preg_replace( '/(?:%(?![a-f0-9]{2})|[^a-z0-9%-])+/i', '-', (string) $title ) );
}
function wp_unslash( $value ) {
	return is_string( $value ) ? stripslashes( $value ) : $value;
}
function wp_generate_uuid4() {
	$b    = random_bytes( 16 );
	$b[6] = chr( ord( $b[6] ) & 0x0f | 0x40 );
	$b[8] = chr( ord( $b[8] ) & 0x3f | 0x80 );
	return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $b ), 4 ) );
}

// ----- Options / users -------------------------------------------------------------

$GLOBALS['t_options'] = array( 'timezone_string' => 'America/Argentina/Buenos_Aires' );
$GLOBALS['t_caps']    = array( 'manage_options' => false, 'edit_post' => true );

function get_option( $name, $default = false ) {
	return $GLOBALS['t_options'][ $name ] ?? $default;
}
function current_user_can( $cap ) {
	return ! empty( $GLOBALS['t_caps'][ $cap ] );
}
function user_can( $user, $cap ) {
	if ( $user instanceof WP_User ) {
		return ! empty( $user->allcaps[ $cap ] );
	}
	return current_user_can( $cap );
}
/** User stand-in: roles + effective capabilities. */
class WP_User {
	public $ID;
	public $roles;
	public $allcaps;
	public function __construct( $id, array $roles, array $caps ) {
		$this->ID      = $id;
		$this->roles   = $roles;
		$this->allcaps = array_fill_keys( $caps, true );
	}
}
function wp_get_current_user() {
	return $GLOBALS['t_current_user'] ?? new WP_User( 0, array(), array() );
}
function is_admin() {
	return ! empty( $GLOBALS['t_is_admin'] );
}
function wp_doing_cron() {
	return false;
}
function esc_html( $text ) {
	return $text;
}
function wp_die( $message ) {
	throw new RuntimeException( (string) $message );
}
function get_the_terms( $post ) {
	$post = get_post( $post );
	$out  = array();
	foreach ( wp_get_object_terms( $post->ID ) as $id ) {
		$out[] = $GLOBALS['t_term_objects'][ $id ] ?? new WP_Term( $id, 'T' . $id );
	}
	return $out ?: false;
}
/** All terms of $GLOBALS['t_term_objects'] (unordered, like core without orderby meta). */
function get_terms( array $args = array() ) {
	return array_values( $GLOBALS['t_term_objects'] ?? array() );
}
function get_term_meta( $id, $key ) {
	return $GLOBALS['t_term_meta'][ (int) $id ][ $key ] ?? ( '_casa_color' === $key ? 'mint' : '' );
}

// ----- Minimal core classes --------------------------------------------------------

class WP_Post {
	public $ID;
	public $post_type    = 'casa_evento';
	public $post_status  = 'draft';
	public $post_title   = '';
	public $post_excerpt = '';
	public $post_author  = 0;
	public function __construct( array $data ) {
		foreach ( $data as $k => $v ) {
			$this->$k = $v;
		}
	}
}
class WP_Term {
	public $term_id;
	public $name;
	public $slug;
	public function __construct( $id, $name, $slug = null ) {
		$this->term_id = $id;
		$this->name    = $name;
		$this->slug    = null === $slug ? sanitize_title( $name ) : $slug;
	}
}
class WP_Error {
	private $code;
	private $message;
	private $data;
	public function __construct( $code = '', $message = '', $data = '' ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}
	public function get_error_code() {
		return $this->code;
	}
	public function get_error_message() {
		return $this->message;
	}
	public function get_error_data() {
		return $this->data;
	}
}
function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}
class WP_REST_Request {
	private $params;
	public function __construct( array $params ) {
		$this->params = $params;
	}
	public function get_param( $key ) {
		return $this->params[ $key ] ?? null;
	}
}

// ----- In-memory store ---------------------------------------------------------------

function t_reset_store() {
	$GLOBALS['t_posts']   = array();
	$GLOBALS['t_meta']    = array();
	$GLOBALS['t_terms']   = array();
	$GLOBALS['t_actions'] = array();
}
t_reset_store();

function t_add_post( array $data ) {
	$id                        = $data['ID'] ?? ( count( $GLOBALS['t_posts'] ) ? max( array_keys( $GLOBALS['t_posts'] ) ) + 1 : 1 );
	$data['ID']                = $id;
	$GLOBALS['t_posts'][ $id ] = new WP_Post( $data );
	return $id;
}
function get_post( $post ) {
	if ( $post instanceof WP_Post ) {
		return $post;
	}
	return $GLOBALS['t_posts'][ (int) $post ] ?? null;
}
function get_post_type( $id ) {
	$post = get_post( $id );
	return $post ? $post->post_type : false;
}
function get_post_stati() {
	return array( 'publish' => 'publish', 'future' => 'future', 'draft' => 'draft', 'pending' => 'pending', 'private' => 'private', 'trash' => 'trash', 'auto-draft' => 'auto-draft' );
}
function t_meta_sanitizer( $key, $id = 0 ) {
	static $map = null;
	if ( $id && 'casa_evento' !== get_post_type( $id ) ) {
		return null; // Meta is registered for the casa_evento subtype only.
	}
	if ( null === $map ) {
		$map = array();
		foreach ( CasaEventos\Core\editorial_meta_definitions() as $k => $def ) {
			$map[ $k ] = $def['sanitize'];
		}
		foreach ( CasaEventos\Core\system_meta_definitions() as $k => $def ) {
			$map[ $k ] = $def[1];
		}
	}
	return $map[ $key ] ?? null;
}
function t_meta_default( $key ) {
	$defs = CasaEventos\Core\editorial_meta_definitions();
	return isset( $defs[ $key ] ) && array_key_exists( 'default', $defs[ $key ] ) ? $defs[ $key ]['default'] : '';
}
function metadata_exists( $type, $id, $key ) {
	return 'post' === $type ? array_key_exists( $key, $GLOBALS['t_meta'][ (int) $id ] ?? array() ) : false;
}
function get_post_meta( $id, $key, $single = false ) {
	if ( metadata_exists( 'post', $id, $key ) ) {
		return $GLOBALS['t_meta'][ (int) $id ][ $key ];
	}
	return t_meta_default( $key ); // Registered default, like get_metadata_default().
}
function update_post_meta( $id, $key, $value, $prev = '' ) {
	$check = apply_filters( 'update_post_metadata', null, $id, $key, $value, $prev );
	if ( null !== $check ) {
		return (bool) $check;
	}
	$sanitizer = t_meta_sanitizer( $key, $id );
	$value     = $sanitizer ? call_user_func( $sanitizer, $value ) : $value;
	$existed   = metadata_exists( 'post', $id, $key );
	$old       = $existed ? $GLOBALS['t_meta'][ (int) $id ][ $key ] : null;
	if ( $existed && (string) $old === (string) $value ) {
		return false;
	}
	if ( ! $existed ) {
		$check = apply_filters( 'add_post_metadata', null, $id, $key, $value, true );
		if ( null !== $check ) {
			return (bool) $check;
		}
	}
	$GLOBALS['t_meta'][ (int) $id ][ $key ] = is_int( $value ) ? (string) $value : $value;
	do_action( $existed ? 'updated_post_meta' : 'added_post_meta', 1, $id, $key, $value );
	return true;
}
function add_post_meta( $id, $key, $value, $unique = false ) {
	$check = apply_filters( 'add_post_metadata', null, $id, $key, $value, $unique );
	if ( null !== $check ) {
		return (bool) $check;
	}
	$sanitizer                              = t_meta_sanitizer( $key, $id );
	$GLOBALS['t_meta'][ (int) $id ][ $key ] = $sanitizer ? call_user_func( $sanitizer, $value ) : $value;
	do_action( 'added_post_meta', 1, $id, $key, $value );
	return true;
}
function delete_post_meta( $id, $key ) {
	$check = apply_filters( 'delete_post_metadata', null, $id, $key, '', false );
	if ( null !== $check ) {
		return (bool) $check;
	}
	unset( $GLOBALS['t_meta'][ (int) $id ][ $key ] );
	return true;
}
/** Direct write that bypasses every hook (like an SQL import). */
function t_raw_meta( $id, $key, $value ) {
	$GLOBALS['t_meta'][ (int) $id ][ $key ] = $value;
}
function get_posts( array $args ) {
	$ids = array();
	foreach ( $GLOBALS['t_posts'] as $id => $post ) {
		if ( isset( $args['post_type'] ) && $post->post_type !== $args['post_type'] ) {
			continue;
		}
		if ( isset( $args['meta_key'] ) && ( ! metadata_exists( 'post', $id, $args['meta_key'] ) || (string) get_post_meta( $id, $args['meta_key'] ) !== (string) $args['meta_value'] ) ) {
			continue;
		}
		$ids[] = $id;
	}
	sort( $ids );
	return $ids;
}
function t_set_terms( $id, array $term_ids ) {
	$GLOBALS['t_terms'][ (int) $id ] = $term_ids;
}
function wp_get_object_terms( $id ) {
	return $GLOBALS['t_terms'][ (int) $id ] ?? array();
}
/** Terms listed in $GLOBALS['t_missing_terms'] do not exist; every other positive ID does. */
function term_exists( $id ) {
	return in_array( (int) $id, $GLOBALS['t_missing_terms'] ?? array(), true ) ? null : array( 'term_id' => (int) $id );
}
/** Minimal subset of core's schema validation: type, enum, pattern, maxLength. */
function rest_validate_value_from_schema( $value, $schema, $param = '' ) {
	$type = $schema['type'] ?? 'string';
	$ok   = true;
	if ( 'string' === $type ) {
		$ok = is_string( $value );
	} elseif ( 'integer' === $type ) {
		$ok = is_int( $value ) || ( is_string( $value ) && preg_match( '/^-?\d+$/', $value ) );
	} elseif ( 'boolean' === $type ) {
		$ok = in_array( $value, array( true, false, 'true', 'false', '1', '0', 1, 0 ), true );
	}
	if ( $ok && isset( $schema['enum'] ) ) {
		$ok = in_array( $value, $schema['enum'], true );
	}
	if ( $ok && isset( $schema['pattern'] ) ) {
		$ok = (bool) preg_match( '#' . $schema['pattern'] . '#u', $value );
	}
	if ( $ok && isset( $schema['maxLength'] ) ) {
		$ok = ( function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value ) ) <= $schema['maxLength'];
	}
	return $ok ? true : new WP_Error( 'rest_invalid_param', $param );
}
function get_taxonomy( $name ) {
	return (object) array(
		'name'      => $name,
		'rest_base' => $name,
		'cap'       => (object) CasaEventos\Core\category_capabilities(),
	);
}

// ----- Options, roles, registration ------------------------------------------------------

function update_option( $name, $value ) {
	$GLOBALS['t_options'][ $name ] = $value;
	return true;
}
function delete_option( $name ) {
	unset( $GLOBALS['t_options'][ $name ] );
	return true;
}

/** Role stand-in: capabilities map + write log (like WP_Role, persisted in t_roles). */
class WP_Role {
	public $name;
	public $capabilities;
	public function __construct( $name, array $capabilities ) {
		$this->name         = $name;
		$this->capabilities = $capabilities;
	}
	public function add_cap( $cap, $grant = true ) {
		$this->capabilities[ $cap ] = $grant;
		$GLOBALS['t_role_writes'][] = array( 'add', $this->name, $cap );
	}
	public function remove_cap( $cap ) {
		unset( $this->capabilities[ $cap ] );
		$GLOBALS['t_role_writes'][] = array( 'remove', $this->name, $cap );
	}
}
function t_reset_roles( array $roles = array() ) {
	$GLOBALS['t_roles']       = array();
	$GLOBALS['t_role_names']  = array();
	$GLOBALS['t_role_writes'] = array();
	foreach ( $roles as $slug => $caps ) {
		$GLOBALS['t_roles'][ $slug ]      = new WP_Role( $slug, array_fill_keys( $caps, true ) );
		$GLOBALS['t_role_names'][ $slug ] = ucfirst( $slug );
	}
}
t_reset_roles();
function get_role( $slug ) {
	return $GLOBALS['t_roles'][ $slug ] ?? null;
}
function add_role( $slug, $display_name, array $caps ) {
	if ( isset( $GLOBALS['t_roles'][ $slug ] ) ) {
		return null;
	}
	$GLOBALS['t_roles'][ $slug ]      = new WP_Role( $slug, $caps );
	$GLOBALS['t_role_names'][ $slug ] = $display_name;
	$GLOBALS['t_role_writes'][]       = array( 'add_role', $slug, '' );
	return $GLOBALS['t_roles'][ $slug ];
}
function remove_role( $slug ) {
	unset( $GLOBALS['t_roles'][ $slug ], $GLOBALS['t_role_names'][ $slug ] );
	$GLOBALS['t_role_writes'][] = array( 'remove_role', $slug, '' );
}
function wp_roles() {
	return (object) array( 'role_objects' => $GLOBALS['t_roles'] );
}

/** Registration capture (register_post_type / register_taxonomy / term meta). */
function register_post_type( $name, array $args ) {
	$GLOBALS['t_registered']['post_type'][ $name ] = $args;
}
function register_taxonomy( $name, $object_type, array $args ) {
	$GLOBALS['t_registered']['taxonomy'][ $name ] = $args + array( 'object_type' => $object_type );
}
function register_term_meta( $taxonomy, $key, array $args ) {
	$GLOBALS['t_registered']['term_meta'][ $key ] = $args;
}

// ----- Front-end conditionals / caches used by visibility.php and queries.php ----------

function is_singular( $post_type = '' ) {
	return isset( $GLOBALS['t_singular'] ) && ( '' === $post_type || $GLOBALS['t_singular'] === $post_type );
}
function get_queried_object() {
	return get_post( $GLOBALS['t_queried'] ?? 0 );
}
/** Records each priming call with the IDs of the queried posts. */
function update_post_thumbnail_cache( $query ) {
	$GLOBALS['t_thumb_primed'][] = array_map( static fn( $p ) => $p->ID, $query->posts );
}

// ----- WP_Query stand-in: evaluates the meta queries the Query API builds -----------------

/**
 * Supports what inc/core/queries.php uses: post_type, post_status,
 * posts_per_page, post__not_in, nested meta clauses (AND / OR relations)
 * with =, !=, IN, NOT IN, >, >=, <, BETWEEN, EXISTS, NOT EXISTS (string
 * comparison, like type CHAR),
 * tax_query by slug (field 'slug', terms from $GLOBALS['t_term_objects'],
 * AND of its clauses), and orderby: an ordered list of named top-level meta
 * clauses and ID, each ASC or DESC (default start_order ASC, ID ASC).
 * The last query's args are kept in $GLOBALS['t_last_query'].
 */
class WP_Query {
	public $posts       = array();
	public $found_posts = 0;
	public function __construct( array $args ) {
		$GLOBALS['t_last_query'] = $args;
		$ids                     = array();
		foreach ( $GLOBALS['t_posts'] as $id => $post ) {
			if ( isset( $args['post_type'] ) && $post->post_type !== $args['post_type'] ) {
				continue;
			}
			$stati = (array) ( $args['post_status'] ?? 'publish' );
			if ( ! in_array( $post->post_status, $stati, true ) ) {
				continue;
			}
			if ( in_array( $id, $args['post__not_in'] ?? array(), true ) ) {
				continue;
			}
			if ( isset( $args['meta_query'] ) && ! t_meta_query_matches( $id, $args['meta_query'] ) ) {
				continue;
			}
			if ( isset( $args['tax_query'] ) && ! t_tax_query_matches( $id, $args['tax_query'] ) ) {
				continue;
			}
			$ids[] = $id;
		}
		$orderby = (array) ( $args['orderby'] ?? array() );
		$orderby = $orderby ?: array( 'start_order' => 'ASC', 'ID' => 'ASC' );
		$meta    = (array) ( $args['meta_query'] ?? array() );
		usort(
			$ids,
			static function ( $a, $b ) use ( $orderby, $meta ) {
				foreach ( $orderby as $name => $order ) {
					if ( 'ID' === $name ) {
						$cmp = $a <=> $b;
					} else {
						// Without a named clause, start_order keeps the old default (start GMT).
						$key = $meta[ $name ]['key'] ?? ( 'start_order' === $name ? '_casa_start_gmt' : null );
						if ( null === $key ) {
							throw new LogicException( 'orderby names an unknown meta clause: ' . $name );
						}
						$cmp = strcmp( (string) get_post_meta( $a, $key ), (string) get_post_meta( $b, $key ) );
					}
					if ( 0 !== $cmp ) {
						return 'DESC' === strtoupper( (string) $order ) ? -$cmp : $cmp;
					}
				}
				return 0;
			}
		);
		$this->found_posts = count( $ids );
		$limit             = (int) ( $args['posts_per_page'] ?? -1 );
		if ( $limit > 0 ) {
			$ids = array_slice( $ids, 0, $limit );
		}
		$this->posts = array_map( 'get_post', $ids );
	}
}
function t_meta_query_matches( $id, array $query ) {
	$or      = 'OR' === strtoupper( (string) ( $query['relation'] ?? 'AND' ) );
	$results = array();
	foreach ( $query as $key => $clause ) {
		if ( 'relation' === $key ) {
			continue;
		}
		$results[] = isset( $clause['key'] ) ? t_meta_clause_matches( $id, $clause ) : t_meta_query_matches( $id, $clause );
	}
	if ( ! $results ) {
		return true;
	}
	return $or ? in_array( true, $results, true ) : ! in_array( false, $results, true );
}
function t_tax_query_matches( $id, array $query ) {
	$slugs = array();
	foreach ( wp_get_object_terms( $id ) as $term_id ) {
		if ( isset( $GLOBALS['t_term_objects'][ $term_id ] ) ) {
			$slugs[] = $GLOBALS['t_term_objects'][ $term_id ]->slug;
		}
	}
	foreach ( $query as $key => $clause ) {
		if ( 'relation' === $key ) {
			continue;
		}
		if ( 'slug' !== ( $clause['field'] ?? '' ) ) {
			throw new LogicException( 'tax_query stand-in supports field slug only' );
		}
		if ( ! array_intersect( array_map( 'strval', (array) $clause['terms'] ), $slugs ) ) {
			return false;
		}
	}
	return true;
}
function t_meta_clause_matches( $id, array $clause ) {
	$exists  = metadata_exists( 'post', $id, $clause['key'] );
	$compare = $clause['compare'] ?? '=';
	if ( 'EXISTS' === $compare ) {
		return $exists;
	}
	if ( 'NOT EXISTS' === $compare ) {
		return ! $exists;
	}
	if ( ! $exists ) {
		return false; // Like the SQL join: a missing key never matches.
	}
	$value = (string) get_post_meta( $id, $clause['key'] );
	$want  = $clause['value'];
	switch ( $compare ) {
		case '=':
			return $value === (string) $want;
		case '!=':
			return $value !== (string) $want;
		case 'IN':
			return in_array( $value, array_map( 'strval', (array) $want ), true );
		case 'NOT IN':
			return ! in_array( $value, array_map( 'strval', (array) $want ), true );
		case '>':
			return strcmp( $value, (string) $want ) > 0;
		case '>=':
			return strcmp( $value, (string) $want ) >= 0;
		case '<':
			return strcmp( $value, (string) $want ) < 0;
		case 'BETWEEN':
			return strcmp( $value, (string) $want[0] ) >= 0 && strcmp( $value, (string) $want[1] ) <= 0;
	}
	throw new LogicException( 'unsupported compare ' . $compare );
}

// ----- Load the plugin's core ----------------------------------------------------------

$core = dirname( __DIR__ ) . '/inc/core/';
foreach ( array( 'schema', 'datetime', 'state', 'validation', 'settings', 'capabilities', 'post-type', 'taxonomy', 'meta', 'class-event', 'sync', 'uuid', 'enforcement', 'rest', 'queries', 'visibility' ) as $file ) {
	require_once $core . $file . '.php';
}

// ----- Assertions --------------------------------------------------------------------

$GLOBALS['t_pass']  = 0;
$GLOBALS['t_fail']  = array();
$GLOBALS['t_group'] = '';

function t_group( $name ) {
	$GLOBALS['t_group'] = $name;
}
function t_ok( $condition, $message ) {
	if ( $condition ) {
		++$GLOBALS['t_pass'];
	} else {
		$GLOBALS['t_fail'][] = $GLOBALS['t_group'] . ' › ' . $message;
	}
}
function t_eq( $expected, $actual, $message ) {
	t_ok( $expected === $actual, $message . ' (expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) . ')' );
}
