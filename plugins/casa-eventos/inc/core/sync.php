<?php
/**
 * Derived fields and lifecycle actions.
 *
 * Runs on wp_after_insert_post (after the post, its terms and its meta are
 * saved, in REST and non-REST saves) and after a revision restore. It:
 * - ensures the UUID (uuid.php) and captures the event timezone once;
 * - materializes the stored defaults (status, listed, featured, capacity);
 * - recomputes the GMT copies and the effective end;
 * - records the cancellation instant;
 * - fires the actions later modules listen to.
 *
 * Later modules that change Event meta outside a post save call
 * sync_event() themselves.
 *
 * @package CasaEventos
 */

namespace CasaEventos\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Recompute an event's derived data and fire lifecycle actions.
 *
 * @param int      $post_id   Event ID.
 * @param int|null $timestamp Now (unix); defaults to time(). For tests.
 * @return array|null Changes applied, or null when the post is not a real event.
 */
function sync_event( $post_id, $timestamp = null ) {
	$post = get_post( $post_id );
	if ( ! $post || POST_TYPE !== $post->post_type || 'auto-draft' === $post->post_status ) {
		return null;
	}
	$post_id = (int) $post->ID;
	$changes = array();

	// Identity.
	$uuid_change = ensure_uuid( $post_id );
	if ( $uuid_change ) {
		$changes['uuid'] = $uuid_change;
	}

	$tz = sanitize_timezone( get_post_meta( $post_id, META_TIMEZONE, true ) );
	if ( '' === $tz ) {
		$tz = timezone_for_new_event();
		update_post_meta( $post_id, META_TIMEZONE, $tz );
		$changes['timezone'] = $tz;
	}

	// Stored defaults: always explicit, so queries use plain equality.
	$defaults = array(
		META_STATUS   => STATUS_ACTIVE,
		META_LISTED   => '1',
		META_FEATURED => '0',
		META_CAPACITY => default_capacity(),
	);
	foreach ( $defaults as $key => $default ) {
		if ( ! metadata_exists( 'post', $post_id, $key ) ) {
			update_post_meta( $post_id, $key, $default );
			$changes['materialized'][] = $key;
		}
	}

	// Derived dates.
	$old = array(
		'start_gmt' => (string) get_post_meta( $post_id, META_START_GMT, true ),
		'end_gmt'   => (string) get_post_meta( $post_id, META_END_GMT, true ),
		'ends_gmt'  => (string) get_post_meta( $post_id, META_ENDS_GMT, true ),
	);
	$new = derive_gmt(
		sanitize_local_datetime( get_post_meta( $post_id, META_START, true ) ),
		sanitize_local_datetime( get_post_meta( $post_id, META_END, true ) ),
		$tz,
		default_duration_minutes()
	);
	$keys = array(
		'start_gmt' => META_START_GMT,
		'end_gmt'   => META_END_GMT,
		'ends_gmt'  => META_ENDS_GMT,
	);
	foreach ( $keys as $field => $key ) {
		// Stored even when '' so admin ordering by start never drops an event.
		if ( $old[ $field ] !== $new[ $field ] || ! metadata_exists( 'post', $post_id, $key ) ) {
			update_post_meta( $post_id, $key, $new[ $field ] );
		}
	}
	$schedule_changed = $old !== $new;
	if ( $schedule_changed ) {
		$changes['schedule'] = array(
			'old' => $old,
			'new' => $new,
		);
	}

	// Cancellation instant.
	$status        = normalize_status( get_post_meta( $post_id, META_STATUS, true ) );
	$cancelled_gmt = sanitize_gmt_datetime( get_post_meta( $post_id, META_CANCELLED_GMT, true ) );
	$lifecycle     = '';
	if ( STATUS_CANCELLED === $status && '' === $cancelled_gmt ) {
		update_post_meta( $post_id, META_CANCELLED_GMT, now_gmt( $timestamp ) );
		$lifecycle = 'cancelled';
	} elseif ( STATUS_CANCELLED !== $status && '' !== $cancelled_gmt ) {
		update_post_meta( $post_id, META_CANCELLED_GMT, '' );
		$lifecycle = 'reactivated';
	} elseif ( ! metadata_exists( 'post', $post_id, META_CANCELLED_GMT ) ) {
		update_post_meta( $post_id, META_CANCELLED_GMT, '' );
	}
	if ( $lifecycle ) {
		$changes['lifecycle'] = $lifecycle;
	}

	// Actions fire after every write, so listeners read consistent data.
	if ( $schedule_changed ) {
		/**
		 * Start, declared end or effective end (GMT) changed, including the
		 * first time a date is set. Reminders reschedule from here.
		 *
		 * @param int   $post_id Event ID.
		 * @param array $old     start_gmt, end_gmt, ends_gmt before.
		 * @param array $new     start_gmt, end_gmt, ends_gmt after.
		 */
		do_action( 'casa_eventos/schedule_changed', $post_id, $old, $new );
	}
	if ( 'cancelled' === $lifecycle ) {
		/**
		 * The event became cancelled. Never refunds anything by itself.
		 *
		 * @param int $post_id Event ID.
		 */
		do_action( 'casa_eventos/event_cancelled', $post_id );
	} elseif ( 'reactivated' === $lifecycle ) {
		/**
		 * A cancelled event became active or paused again.
		 *
		 * @param int    $post_id       Event ID.
		 * @param string $cancelled_gmt When it had been cancelled.
		 */
		do_action( 'casa_eventos/event_reactivated', $post_id, $cancelled_gmt );
	}

	/**
	 * Derived data is up to date.
	 *
	 * @param int   $post_id Event ID.
	 * @param array $changes What this sync changed.
	 */
	do_action( 'casa_eventos/event_synced', $post_id, $changes );

	return $changes;
}

/**
 * wp_after_insert_post handler.
 *
 * @param int      $post_id Post ID.
 * @param \WP_Post $post    Post.
 */
function sync_after_insert( $post_id, $post ) {
	if ( $post && POST_TYPE === $post->post_type ) {
		sync_event( $post_id );
	}
}
add_action( 'wp_after_insert_post', __NAMESPACE__ . '\\sync_after_insert', 20, 2 );

/**
 * Re-sync after a revision restore (core restores revisioned meta at
 * priority 10 of this action, after the post itself was updated).
 *
 * @param int $post_id Restored post ID.
 */
function sync_after_restore( $post_id ) {
	if ( POST_TYPE === get_post_type( $post_id ) ) {
		sync_event( $post_id );
	}
}
add_action( 'wp_restore_post_revision', __NAMESPACE__ . '\\sync_after_restore', 20 );
