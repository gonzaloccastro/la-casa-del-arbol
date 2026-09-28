<?php
/**
 * Operational state rules.
 *
 * WordPress post_status handles draft/pending/scheduled/published.
 * _casa_status handles active/paused/cancelled. "Finished" is derived from
 * the effective end and is never stored.
 *
 * @package CasaEventos
 */

namespace CasaEventos\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalize a stored operational status.
 *
 * @param mixed $status Raw value.
 * @return string One of statuses(); unknown values read as active.
 */
function normalize_status( $status ) {
	return in_array( $status, statuses(), true ) ? $status : STATUS_ACTIVE;
}

/**
 * Effective state of an event.
 *
 * Order: scheduled/draft (not public yet) → cancelled (wins over finished,
 * so a past cancelled event still reads as cancelled) → finished → paused →
 * active.
 *
 * @param string $post_status WordPress post status.
 * @param string $status      Stored operational status.
 * @param string $ends_gmt    Derived effective end (GMT), '' when unknown.
 * @param string $now_gmt     Current instant (GMT).
 * @return string One of the STATE_* constants.
 */
function effective_state( $post_status, $status, $ends_gmt, $now_gmt ) {
	if ( 'future' === $post_status ) {
		return STATE_SCHEDULED;
	}
	if ( 'publish' !== $post_status ) {
		return STATE_DRAFT;
	}

	$status = normalize_status( $status );

	if ( STATUS_CANCELLED === $status ) {
		return STATE_CANCELLED;
	}
	if ( is_finished( $ends_gmt, $now_gmt ) ) {
		return STATE_FINISHED;
	}
	if ( STATUS_PAUSED === $status ) {
		return STATE_PAUSED;
	}
	return STATE_ACTIVE;
}

/**
 * Whether the effective end has passed.
 *
 * @param string $ends_gmt Derived effective end (GMT).
 * @param string $now_gmt  Current instant (GMT).
 * @return bool False when the event has no date.
 */
function is_finished( $ends_gmt, $now_gmt ) {
	return '' !== (string) $ends_gmt && $now_gmt >= $ends_gmt;
}

/**
 * Whether a live call to action may be offered in this state.
 *
 * Paused removes every actionable CTA (WhatsApp included), cancelled blocks
 * actions, finished (historical) events have no active action.
 *
 * @param string $state Effective state.
 * @return bool
 */
function is_actionable_state( $state ) {
	return STATE_ACTIVE === $state;
}

/**
 * Human label of an effective state (admin).
 *
 * @param string $state Effective state.
 * @return string
 */
function state_label( $state ) {
	$labels = array(
		STATE_DRAFT     => __( 'Borrador', 'casa-eventos' ),
		STATE_SCHEDULED => __( 'Programado', 'casa-eventos' ),
		STATE_ACTIVE    => __( 'Activo', 'casa-eventos' ),
		STATE_PAUSED    => __( 'Pausado', 'casa-eventos' ),
		STATE_CANCELLED => __( 'Cancelado', 'casa-eventos' ),
		STATE_FINISHED  => __( 'Finalizado', 'casa-eventos' ),
	);
	return $labels[ $state ] ?? $state;
}

/**
 * Human labels of the access modes.
 *
 * @return array<string,string>
 */
function access_mode_labels() {
	return array(
		ACCESS_TICKETS  => __( 'Venta de entradas', 'casa-eventos' ),
		ACCESS_WHATSAPP => __( 'Reserva por WhatsApp', 'casa-eventos' ),
		ACCESS_EXTERNAL => __( 'Venta externa', 'casa-eventos' ),
	);
}

/**
 * Human labels of the entry kinds.
 *
 * @return array<string,string>
 */
function entry_kind_labels() {
	return array(
		ENTRY_PAID  => __( 'Paga', 'casa-eventos' ),
		ENTRY_FREE  => __( 'Entrada libre', 'casa-eventos' ),
		ENTRY_GORRA => __( 'A la gorra', 'casa-eventos' ),
	);
}

/**
 * Human labels of the operational statuses.
 *
 * @return array<string,string>
 */
function status_labels() {
	return array(
		STATUS_ACTIVE    => __( 'Activo', 'casa-eventos' ),
		STATUS_PAUSED    => __( 'Pausado', 'casa-eventos' ),
		STATUS_CANCELLED => __( 'Cancelado', 'casa-eventos' ),
	);
}

/**
 * Capability required to reactivate a cancelled event.
 *
 * @return string
 */
function reactivate_capability() {
	return (string) apply_filters( 'casa_eventos/reactivate_capability', 'manage_options' );
}
