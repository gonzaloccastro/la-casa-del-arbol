<?php
/**
 * Validation rules and sanitizers.
 *
 * @package CasaEventos
 */

use function CasaEventos\Core\parse_capacity;
use function CasaEventos\Core\sanitize_access_mode;
use function CasaEventos\Core\sanitize_capacity;
use function CasaEventos\Core\sanitize_entry_label;
use function CasaEventos\Core\sanitize_external_url;
use function CasaEventos\Core\sanitize_local_datetime;
use function CasaEventos\Core\sanitize_uuid;
use function CasaEventos\Core\to_flag;
use function CasaEventos\Core\validate_event_data;

/**
 * A complete, publishable event.
 *
 * @param array $over Overrides.
 * @return array
 */
function t_valid_event( array $over = array() ) {
	return array_merge(
		array(
			'target_status' => 'publish',
			'title'         => 'Lucernaria',
			'start'         => '2026-09-05 21:00:00',
			'access_mode'   => 'whatsapp',
			'category_ids'  => array( 7 ),
		),
		$over
	);
}

function t_codes( array $data, $kind = 'errors' ) {
	$result = validate_event_data( $data );
	$codes  = array_keys( $result[ $kind ] );
	sort( $codes );
	return $codes;
}

t_group( 'validation/drafts' );
t_eq( array(), t_codes( array( 'target_status' => 'draft' ) ), 'an empty draft saves' );
t_eq( array(), t_codes( array( 'target_status' => 'pending' ) ), 'an empty pending review saves' );
t_eq( array( 'start_invalid' ), t_codes( array( 'target_status' => 'draft', 'start' => '2026-02-30 20:00' ) ), 'an invalid date blocks even a draft' );
t_eq( array( 'capacity_invalid' ), t_codes( array( 'target_status' => 'draft', 'capacity' => 0 ) ), 'capacity 0 blocks even a draft' );
t_eq( array( 'access_mode_invalid' ), t_codes( array( 'target_status' => 'draft', 'access_mode' => 'gratis' ) ), 'unknown access mode' );
t_eq( array( 'entry_kind_invalid' ), t_codes( array( 'target_status' => 'draft', 'entry_kind' => 'libre' ) ), 'unknown entry kind' );
t_eq( array( 'status_invalid' ), t_codes( array( 'target_status' => 'draft', 'status' => 'finished' ) ), 'finished is never stored' );
t_eq( array( 'external_url_invalid' ), t_codes( array( 'target_status' => 'draft', 'external_url' => 'ftp://x.test/a' ) ), 'non-http URL' );

t_group( 'validation/publish' );
t_eq( array(), t_codes( t_valid_event() ), 'a complete event publishes' );
t_eq( array(), t_codes( t_valid_event( array( 'target_status' => 'future' ) ) ), 'a complete event can be scheduled' );
t_eq(
	array( 'access_mode_required', 'category_count', 'start_required', 'title_required' ),
	t_codes( array( 'target_status' => 'publish' ) ),
	'publishing an empty event lists every missing field'
);
t_eq( array( 'access_mode_required', 'category_count', 'start_required', 'title_required' ), t_codes( array( 'target_status' => 'future' ) ), 'scheduling is validated too' );
t_eq( array( 'title_required' ), t_codes( t_valid_event( array( 'title' => '   ' ) ) ), 'blank title' );
t_eq( array( 'end_before_start' ), t_codes( t_valid_event( array( 'end' => '2026-09-05 20:00:00' ) ) ), 'end before start' );
t_eq( array( 'end_before_start' ), t_codes( t_valid_event( array( 'end' => '2026-09-05 21:00:00' ) ) ), 'end equal to start' );
t_eq( array(), t_codes( t_valid_event( array( 'end' => '2026-09-06 02:00:00' ) ) ), 'end after midnight' );
t_eq( array( 'category_count' ), t_codes( t_valid_event( array( 'category_ids' => array( 3, 4 ) ) ) ), 'two categories' );
t_eq( array(), t_codes( t_valid_event( array( 'category_ids' => array( 3, '3' ) ) ) ), 'the same category twice counts once' );
t_eq( array( 'external_url_required' ), t_codes( t_valid_event( array( 'access_mode' => 'external' ) ) ), 'external mode needs a URL' );
t_eq( array( 'external_url_invalid' ), t_codes( t_valid_event( array( 'access_mode' => 'external', 'external_url' => 'passline' ) ) ), 'external mode with a bad URL' );
t_eq( array(), t_codes( t_valid_event( array( 'access_mode' => 'external', 'external_url' => 'https://www.passline.com/eventos/lucernaria' ) ) ), 'external mode with a URL' );
t_eq( array(), t_codes( t_valid_event( array( 'access_mode' => 'tickets' ) ) ), 'tickets mode needs nothing commercial yet' );
t_eq( array(), t_codes( t_valid_event( array( 'capacity' => '' ) ) ), 'blank capacity is fine (default applies)' );
t_eq( array(), t_codes( t_valid_event( array( 'capacity' => '250' ) ) ), 'capacity as a numeric string' );
t_eq( array( 'capacity_invalid' ), t_codes( t_valid_event( array( 'capacity' => -5 ) ) ), 'negative capacity' );
t_eq( array( 'capacity_invalid' ), t_codes( t_valid_event( array( 'capacity' => 1.5 ) ) ), 'fractional capacity' );

t_group( 'validation/sales_close' );
t_eq( array(), t_codes( t_valid_event( array( 'sales_close' => '2026-09-05 20:00:00' ) ) ), 'cutoff before start' );
t_eq( array( 'sales_close_after_start' ), t_codes( t_valid_event( array( 'sales_close' => '2026-09-05 23:00:00' ) ), 'warnings' ), 'cutoff after start warns (no end: effective end 00:00)' );
t_eq( array(), t_codes( t_valid_event( array( 'sales_close' => '2026-09-05 23:00:00' ) ) ), 'cutoff after start does not block' );
t_eq( array( 'sales_close_after_end' ), t_codes( t_valid_event( array( 'sales_close' => '2026-09-06 00:30:00' ) ) ), 'cutoff after the default effective end blocks' );
t_eq( array(), t_codes( t_valid_event( array( 'end' => '2026-09-06 02:00:00', 'sales_close' => '2026-09-06 01:30:00' ) ) ), 'cutoff inside a declared end' );
t_eq( array( 'sales_close_after_end' ), t_codes( t_valid_event( array( 'end' => '2026-09-06 02:00:00', 'sales_close' => '2026-09-06 02:01:00' ) ) ), 'cutoff after a declared end' );
t_eq( array(), t_codes( t_valid_event( array( 'end' => '2026-09-06 02:00:00', 'sales_close' => '2026-09-06 02:00:00' ) ) ), 'cutoff equal to the end' );

t_group( 'validation/reactivation' );
$cancelled = t_valid_event( array( 'previous_status' => 'cancelled', 'status' => 'active' ) );
t_eq( array( 'reactivate_forbidden' ), t_codes( $cancelled ), 'an editor cannot reactivate' );
t_eq( array( 'reactivate_forbidden' ), t_codes( array_merge( $cancelled, array( 'target_status' => 'draft' ) ) ), 'not even on a draft' );
t_eq( array( 'reactivate_forbidden' ), t_codes( array_merge( $cancelled, array( 'status' => 'paused' ) ) ), 'nor to paused' );
t_eq( array(), t_codes( array_merge( $cancelled, array( 'can_reactivate' => true ) ) ), 'an administrator can' );
t_eq( array(), t_codes( t_valid_event( array( 'previous_status' => 'active', 'status' => 'cancelled' ) ) ), 'anyone who can edit can cancel' );

t_group( 'validation/warnings_and_filter' );
t_eq( array( 'featured_not_listed' ), t_codes( t_valid_event( array( 'featured' => true, 'listed' => false ) ), 'warnings' ), 'featured but unlisted' );
t_eq( array( 'already_finished' ), t_codes( t_valid_event( array( 'now_gmt' => '2026-09-07 00:00:00' ) ), 'warnings' ), 'publishing a past event warns' );
add_filter(
	'casa_eventos/validate_event',
	static function ( $result ) {
		$result['errors']['later_module'] = 'Guard from a later module.';
		return $result;
	}
);
t_eq( array( 'later_module' ), t_codes( t_valid_event() ), 'later modules add guards through the filter' );
remove_all_filters( 'casa_eventos/validate_event' );

t_group( 'sanitize' );
t_eq( '1', to_flag( true ), 'true' );
t_eq( '0', to_flag( false ), 'false' );
t_eq( '0', to_flag( '0' ), "'0'" );
t_eq( '0', to_flag( '' ), 'empty' );
t_eq( '1', to_flag( 'true' ), "'true'" );
t_eq( 100, sanitize_capacity( '' ), 'blank capacity → 100' );
t_eq( 100, sanitize_capacity( null ), 'null capacity → 100' );
t_eq( 100, sanitize_capacity( 0 ), 'zero capacity → 100 (validation rejects it first)' );
t_eq( 7, sanitize_capacity( '7' ), 'numeric string' );
t_eq( null, parse_capacity( '' ), 'blank is "use default"' );
t_eq( false, parse_capacity( 'cien' ), 'text is invalid' );
t_eq( '', sanitize_access_mode( 'free' ), 'unknown mode' );
t_eq( 'external', sanitize_access_mode( 'external' ), 'external mode' );
t_eq( '', sanitize_external_url( 'javascript:alert(1)' ), 'script URL dropped' );
t_eq( 'https://www.passline.com/x', sanitize_external_url( ' https://www.passline.com/x ' ), 'https kept, trimmed' );
t_eq( 'Por Passline', sanitize_entry_label( " Por <b>Passline</b>\n" ), 'label is plain text' );
t_eq( '2026-09-05 21:00:00', sanitize_local_datetime( '2026-09-05T21:00' ), 'datetime normalized' );
t_eq( '', sanitize_local_datetime( '2026-02-30 21:00' ), 'invalid datetime stored as empty' );
t_eq( 'a3bb189e-8bf9-4888-9912-ace4e6543002', sanitize_uuid( 'A3BB189E-8BF9-4888-9912-ACE4E6543002' ), 'UUID lowercased' );
t_eq( '', sanitize_uuid( 'a3bb189e-8bf9-1888-9912-ace4e6543002' ), 'non-v4 UUID rejected' );
