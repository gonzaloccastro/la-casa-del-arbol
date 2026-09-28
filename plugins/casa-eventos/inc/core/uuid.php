<?php
/**
 * Event UUID: stable, immutable, unique.
 *
 * - Generated once, on the first real save (never for auto-drafts).
 * - Immutable: once an event has a UUID, other code paths cannot replace,
 *   re-add or delete it (duplicate-post plugins, imports, custom-field UIs).
 * - Unique: when two events carry the same UUID, the one with the lower ID
 *   keeps it and the others get a new one (casa_eventos/uuid_regenerated).
 *
 * The UUID is a machine identity. It is never a QR code or an access
 * credential.
 *
 * @package CasaEventos
 */

namespace CasaEventos\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether the plugin itself is writing a UUID (bypasses the guards).
 *
 * @param bool|null $set New state; null to read.
 * @return bool
 */
function uuid_internal_write( $set = null ) {
	static $internal = false;
	if ( null !== $set ) {
		$internal = (bool) $set;
	}
	return $internal;
}

/**
 * Write a UUID as the plugin.
 *
 * @param int    $post_id Event ID.
 * @param string $uuid    UUID.
 */
function write_uuid( $post_id, $uuid ) {
	uuid_internal_write( true );
	update_post_meta( $post_id, META_UUID, $uuid );
	uuid_internal_write( false );
}

/**
 * A new UUID v4 that no event uses yet.
 *
 * @return string
 */
function new_uuid() {
	do {
		$uuid = sanitize_uuid( wp_generate_uuid4() );
	} while ( '' === $uuid || uuid_holders( $uuid ) );
	return $uuid;
}

/**
 * IDs of the events that carry a UUID, any post status, lowest ID first.
 *
 * @param string $uuid UUID.
 * @return int[]
 */
function uuid_holders( $uuid ) {
	if ( '' === $uuid ) {
		return array();
	}
	$ids = get_posts(
		array(
			'post_type'              => POST_TYPE,
			'post_status'            => array_keys( get_post_stati() ),
			'meta_key'               => META_UUID, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'             => $uuid, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'fields'                 => 'ids',
			'posts_per_page'         => -1,
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'suppress_filters'       => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);
	return array_map( 'intval', $ids );
}

/**
 * Give an event a UUID when it has none, and resolve duplicates.
 *
 * @param int $post_id Event ID.
 * @return array|null What changed: ['old' => ..., 'new' => ...], or null.
 */
function ensure_uuid( $post_id ) {
	$post_id = (int) $post_id;
	$current = sanitize_uuid( get_post_meta( $post_id, META_UUID, true ) );

	if ( '' === $current ) {
		$uuid = new_uuid();
		write_uuid( $post_id, $uuid );
		return array(
			'old' => '',
			'new' => $uuid,
		);
	}

	$changed = resolve_uuid_collision( $current );
	return $changed[ $post_id ] ?? null;
}

/**
 * Keep a duplicated UUID only on the oldest event (lowest ID).
 *
 * @param string $uuid UUID.
 * @return array<int,array{old:string,new:string}> Regenerated events.
 */
function resolve_uuid_collision( $uuid ) {
	$holders = uuid_holders( $uuid );
	$changed = array();
	if ( count( $holders ) < 2 ) {
		return $changed;
	}
	array_shift( $holders ); // The lowest ID keeps the UUID.
	foreach ( $holders as $holder_id ) {
		$fresh = new_uuid();
		write_uuid( $holder_id, $fresh );
		$changed[ $holder_id ] = array(
			'old' => $uuid,
			'new' => $fresh,
		);

		/**
		 * A duplicated UUID was replaced on a copy. Later modules clear any
		 * identity-bound pointers (e.g. a copied commerce link) here.
		 *
		 * @param int    $holder_id Event that got a new UUID.
		 * @param string $old       Duplicated UUID.
		 * @param string $new       New UUID.
		 */
		do_action( 'casa_eventos/uuid_regenerated', $holder_id, $uuid, $fresh );
	}
	return $changed;
}

/**
 * Whether a meta operation targets the UUID of an event.
 *
 * @param int    $object_id Post ID.
 * @param string $meta_key  Meta key.
 * @return bool
 */
function is_event_uuid_meta( $object_id, $meta_key ) {
	return META_UUID === $meta_key && POST_TYPE === get_post_type( (int) $object_id );
}

/**
 * Guard: an existing UUID cannot be replaced by other code.
 *
 * @param null|bool $check      Short-circuit value.
 * @param int       $object_id  Post ID.
 * @param string    $meta_key   Meta key.
 * @param mixed     $meta_value New value.
 * @return null|bool
 */
function guard_uuid_update( $check, $object_id, $meta_key, $meta_value ) {
	if ( null !== $check || uuid_internal_write() || ! is_event_uuid_meta( $object_id, $meta_key ) ) {
		return $check;
	}
	$current = sanitize_uuid( get_post_meta( (int) $object_id, META_UUID, true ) );
	if ( '' !== $current && sanitize_uuid( $meta_value ) !== $current ) {
		return false;
	}
	return $check;
}
add_filter( 'update_post_metadata', __NAMESPACE__ . '\\guard_uuid_update', 10, 4 );

/**
 * Guard: a second UUID row cannot be added to an event that has one.
 *
 * @param null|bool $check     Short-circuit value.
 * @param int       $object_id Post ID.
 * @param string    $meta_key  Meta key.
 * @return null|bool
 */
function guard_uuid_add( $check, $object_id, $meta_key ) {
	if ( null !== $check || uuid_internal_write() || ! is_event_uuid_meta( $object_id, $meta_key ) ) {
		return $check;
	}
	if ( metadata_exists( 'post', (int) $object_id, META_UUID ) ) {
		return false;
	}
	return $check;
}
add_filter( 'add_post_metadata', __NAMESPACE__ . '\\guard_uuid_add', 10, 3 );

/**
 * Guard: the UUID cannot be deleted from an event (deleting the whole post
 * is not affected: core removes its meta by ID).
 *
 * @param null|bool $check     Short-circuit value.
 * @param int       $object_id Post ID.
 * @param string    $meta_key  Meta key.
 * @return null|bool
 */
function guard_uuid_delete( $check, $object_id, $meta_key ) {
	if ( null !== $check || uuid_internal_write() || ! is_event_uuid_meta( $object_id, $meta_key ) ) {
		return $check;
	}
	return false;
}
add_filter( 'delete_post_metadata', __NAMESPACE__ . '\\guard_uuid_delete', 10, 3 );

/**
 * A UUID written by other code on an event that had none (e.g. an import or
 * a copied post): resolve a collision right away.
 *
 * @param int    $meta_id   Meta row ID.
 * @param int    $object_id Post ID.
 * @param string $meta_key  Meta key.
 * @param mixed  $value     Value.
 */
function check_written_uuid( $meta_id, $object_id, $meta_key, $value ) {
	if ( uuid_internal_write() || ! is_event_uuid_meta( $object_id, $meta_key ) ) {
		return;
	}
	$uuid = sanitize_uuid( $value );
	if ( '' !== $uuid ) {
		resolve_uuid_collision( $uuid );
	}
}
add_action( 'added_post_meta', __NAMESPACE__ . '\\check_written_uuid', 10, 4 );
add_action( 'updated_post_meta', __NAMESPACE__ . '\\check_written_uuid', 10, 4 );
