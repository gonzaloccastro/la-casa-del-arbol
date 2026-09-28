<?php
/**
 * Event schema: the only place that names post types, taxonomies, meta keys,
 * enumerations and defaults. Code outside inc/core/ uses the read model
 * (Event) and the query API, never these meta keys.
 *
 * @package CasaEventos
 */

namespace CasaEventos\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const POST_TYPE = 'casa_evento';
const TAXONOMY  = 'casa_categoria';
const SETTINGS  = 'casa_eventos_settings';

// Editorial meta (editable in the block editor, revisioned).
const META_START         = '_casa_start';
const META_END           = '_casa_end';
const META_ACCESS_MODE   = '_casa_access_mode';
const META_ENTRY_KIND    = '_casa_entry_kind';
const META_ENTRY_LABEL   = '_casa_entry_label';
const META_EXTERNAL_URL  = '_casa_external_url';
const META_CAPACITY      = '_casa_capacity';
const META_SALES_CLOSE   = '_casa_sales_close';
const META_STATUS        = '_casa_status';
const META_LISTED        = '_casa_listed';
const META_FEATURED      = '_casa_featured';

// Identity and derived meta (written only by sync, never by the editor).
const META_UUID          = '_casa_uuid';
const META_TIMEZONE      = '_casa_timezone';
const META_START_GMT     = '_casa_start_gmt';
const META_END_GMT       = '_casa_end_gmt';
const META_ENDS_GMT      = '_casa_ends_gmt';
const META_CANCELLED_GMT = '_casa_cancelled_gmt';

// Category term meta.
const TERM_COLOR = '_casa_color';
const TERM_ORDER = '_casa_order';

const STATUS_ACTIVE    = 'active';
const STATUS_PAUSED    = 'paused';
const STATUS_CANCELLED = 'cancelled';

const ACCESS_TICKETS  = 'tickets';
const ACCESS_WHATSAPP = 'whatsapp';
const ACCESS_EXTERNAL = 'external';

const ENTRY_PAID  = 'paid';
const ENTRY_FREE  = 'free';
const ENTRY_GORRA = 'gorra';

const COLOR_MINT   = 'mint';
const COLOR_YELLOW = 'yellow';

// Effective states (derived, never stored).
const STATE_DRAFT     = 'draft';
const STATE_SCHEDULED = 'scheduled';
const STATE_ACTIVE    = 'active';
const STATE_PAUSED    = 'paused';
const STATE_CANCELLED = 'cancelled';
const STATE_FINISHED  = 'finished';

const FALLBACK_TIMEZONE = 'America/Argentina/Buenos_Aires';

/**
 * Operational statuses stored in META_STATUS.
 *
 * @return string[]
 */
function statuses() {
	return array( STATUS_ACTIVE, STATUS_PAUSED, STATUS_CANCELLED );
}

/**
 * Access modes stored in META_ACCESS_MODE.
 *
 * @return string[]
 */
function access_modes() {
	return array( ACCESS_TICKETS, ACCESS_WHATSAPP, ACCESS_EXTERNAL );
}

/**
 * Entry kinds stored in META_ENTRY_KIND.
 *
 * @return string[]
 */
function entry_kinds() {
	return array( ENTRY_PAID, ENTRY_FREE, ENTRY_GORRA );
}

/**
 * Category colors stored in TERM_COLOR.
 *
 * @return string[]
 */
function colors() {
	return array( COLOR_MINT, COLOR_YELLOW );
}

/**
 * Capacity stored when the editor leaves it blank. Materialized on save, so
 * changing this later never changes existing events.
 *
 * @return int
 */
function default_capacity() {
	$value = (int) apply_filters( 'casa_eventos/default_capacity', 100 );
	return $value >= 1 ? $value : 100;
}

/**
 * Duration used for the effective end when no end is declared.
 *
 * @return int Minutes.
 */
function default_duration_minutes() {
	$value = (int) apply_filters( 'casa_eventos/default_duration_minutes', 180 );
	return $value >= 1 ? $value : 180;
}

/**
 * Default sales cutoff, before the start, when none is set. Computed on read.
 *
 * @return int Minutes.
 */
function default_sales_close_offset_minutes() {
	$value = (int) apply_filters( 'casa_eventos/default_sales_close_offset_minutes', 60 );
	return $value >= 0 ? $value : 60;
}
