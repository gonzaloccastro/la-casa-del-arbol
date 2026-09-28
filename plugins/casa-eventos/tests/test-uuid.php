<?php
/**
 * UUID immutability and collision protection.
 *
 * @package CasaEventos
 */

use CasaEventos\Core\Queries;
use function CasaEventos\Core\sync_event;

t_reset_store();

$a = t_add_post( array( 'post_status' => 'publish' ) );
sync_event( $a );
$ua = get_post_meta( $a, '_casa_uuid' );

t_group( 'uuid/immutable' );
t_eq( false, update_post_meta( $a, '_casa_uuid', 'a3bb189e-8bf9-4888-9912-ace4e6543002' ), 'replacing a UUID is refused' );
t_eq( $ua, get_post_meta( $a, '_casa_uuid' ), 'UUID unchanged' );
t_eq( false, add_post_meta( $a, '_casa_uuid', 'a3bb189e-8bf9-4888-9912-ace4e6543002' ), 'adding a second UUID is refused' );
t_eq( false, delete_post_meta( $a, '_casa_uuid' ), 'deleting the UUID is refused' );
t_eq( $ua, get_post_meta( $a, '_casa_uuid' ), 'UUID still there' );

t_group( 'uuid/copied post (duplicate-post plugins)' );
$b = t_add_post( array( 'post_status' => 'draft' ) );
sync_event( $b ); // wp_after_insert_post of the copy.
$ub = get_post_meta( $b, '_casa_uuid' );
t_ok( '' !== $ub && $ub !== $ua, 'the copy gets its own UUID on insert' );
update_post_meta( $b, '_casa_uuid', $ua ); // The plugin copies the original's meta.
t_eq( $ub, get_post_meta( $b, '_casa_uuid' ), 'copying the original UUID over it is refused' );

t_group( 'uuid/import before the first sync' );
$c = t_add_post( array( 'post_status' => 'draft' ) );
add_post_meta( $c, '_casa_uuid', $ua ); // An importer writes a duplicate on a fresh post.
$uc = get_post_meta( $c, '_casa_uuid' );
t_ok( '' !== $uc && $uc !== $ua, 'the duplicate is replaced as soon as it is written' );
t_eq( $ua, get_post_meta( $a, '_casa_uuid' ), 'the older event keeps its UUID' );
$regen = t_actions( 'casa_eventos/uuid_regenerated' );
t_eq( 1, count( $regen ), 'uuid_regenerated fired' );
t_eq( array( $c, $ua, $uc ), $regen[0][1], 'with the event, old and new UUID' );

t_group( 'uuid/raw duplicate (SQL import) resolved on sync' );
$d = t_add_post( array( 'post_status' => 'draft' ) );
t_raw_meta( $d, '_casa_uuid', $ua );
sync_event( $a ); // Syncing the OLDER event also resolves it.
t_eq( $ua, get_post_meta( $a, '_casa_uuid' ), 'the lowest ID keeps the UUID' );
t_ok( get_post_meta( $d, '_casa_uuid' ) !== $ua, 'the newer duplicate got a new UUID' );
t_eq( 1, count( get_posts( array( 'post_type' => 'casa_evento', 'meta_key' => '_casa_uuid', 'meta_value' => $ua ) ) ), 'the UUID is unique again' );

t_group( 'uuid/lookup and other post types' );
t_eq( $a, Queries::find_by_uuid( strtoupper( $ua ) )->id(), 'find_by_uuid (case-insensitive)' );
t_eq( null, Queries::find_by_uuid( 'not-a-uuid' ), 'unknown UUID' );
$post = t_add_post( array( 'post_type' => 'post' ) );
add_post_meta( $post, '_casa_uuid', 'x' );
t_ok( update_post_meta( $post, '_casa_uuid', 'y' ), 'other post types are not guarded' );
