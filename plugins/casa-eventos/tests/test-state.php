<?php
/**
 * Operational state rules.
 *
 * @package CasaEventos
 */

use function CasaEventos\Core\effective_state;
use function CasaEventos\Core\is_actionable_state;
use function CasaEventos\Core\normalize_status;

$ends   = '2026-09-06 03:00:00';
$before = '2026-09-06 02:59:59';
$after  = '2026-09-06 03:00:00';

t_group( 'state/effective_state' );
t_eq( 'draft', effective_state( 'draft', 'active', $ends, $before ), 'draft' );
t_eq( 'draft', effective_state( 'pending', 'active', $ends, $before ), 'pending reads as draft' );
t_eq( 'draft', effective_state( 'private', 'active', $ends, $before ), 'private is not public' );
t_eq( 'draft', effective_state( 'trash', 'cancelled', $ends, $before ), 'trash is not public' );
t_eq( 'scheduled', effective_state( 'future', 'active', $ends, $before ), 'future = scheduled' );
t_eq( 'scheduled', effective_state( 'future', 'cancelled', $ends, $after ), 'scheduled until published' );
t_eq( 'active', effective_state( 'publish', 'active', $ends, $before ), 'active before the end' );
t_eq( 'finished', effective_state( 'publish', 'active', $ends, $after ), 'finished exactly at the effective end' );
t_eq( 'paused', effective_state( 'publish', 'paused', $ends, $before ), 'paused' );
t_eq( 'finished', effective_state( 'publish', 'paused', $ends, $after ), 'paused and past reads finished' );
t_eq( 'cancelled', effective_state( 'publish', 'cancelled', $ends, $before ), 'cancelled' );
t_eq( 'cancelled', effective_state( 'publish', 'cancelled', $ends, $after ), 'cancelled wins over finished' );
t_eq( 'active', effective_state( 'publish', 'active', '', $after ), 'no date is never finished' );
t_eq( 'active', effective_state( 'publish', 'bogus', $ends, $before ), 'unknown status reads active' );

t_group( 'state/actionable' );
t_ok( is_actionable_state( 'active' ), 'active is actionable' );
foreach ( array( 'paused', 'cancelled', 'finished', 'scheduled', 'draft' ) as $state ) {
	t_ok( ! is_actionable_state( $state ), $state . ' is not actionable' );
}

t_group( 'state/normalize' );
t_eq( 'paused', normalize_status( 'paused' ), 'known value kept' );
t_eq( 'active', normalize_status( '' ), 'empty reads active' );
t_eq( 'active', normalize_status( 'Cancelled' ), 'case-sensitive enum' );
