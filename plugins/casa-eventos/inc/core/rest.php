<?php
/**
 * Read-only REST field "casa_event" on the Event endpoint (edit context).
 *
 * Gives the editor panels the identity and derived values (UUID, timezone,
 * effective state, effective end and sales cutoff, validation) computed by
 * the read model. It has no update callback: nothing here can be written,
 * and the editor never sends it back.
 *
 * @package CasaEventos
 */

namespace CasaEventos\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the read-only field.
 */
function register_rest_fields() {
	register_rest_field(
		POST_TYPE,
		'casa_event',
		array(
			'get_callback' => __NAMESPACE__ . '\\rest_event_field',
			'schema'       => array(
				'description' => __( 'Datos derivados del evento (solo lectura).', 'casa-eventos' ),
				'type'        => 'object',
				'context'     => array( 'edit' ),
				'readonly'    => true,
			),
		)
	);
}
add_action( 'rest_api_init', __NAMESPACE__ . '\\register_rest_fields' );

/**
 * Value of the read-only field.
 *
 * @param array            $object  Prepared post data.
 * @param string           $field   Field name.
 * @param \WP_REST_Request $request Request.
 * @return array|null
 */
function rest_event_field( $object, $field, $request ) {
	if ( 'edit' !== $request['context'] ) {
		return null;
	}
	$event = Event::get( (int) $object['id'] );
	if ( ! $event ) {
		return null;
	}

	$state      = $event->effective_state();
	$validation = $event->validation();

	return array(
		'uuid'                   => $event->uuid(),
		'timezone'               => $event->timezone(),
		'effective_state'        => $state,
		'effective_state_label'  => state_label( $state ),
		'start_gmt'              => $event->start_gmt(),
		'ends_gmt'               => $event->ends_gmt(),
		'effective_end'          => gmt_to_local( $event->ends_gmt(), $event->timezone() ),
		'sales_close'            => gmt_to_local( $event->sales_close_gmt(), $event->timezone() ),
		'sales_close_is_default' => $event->sales_close_is_default(),
		'cancelled_gmt'          => $event->cancelled_gmt(),
		'publish_errors'         => array_values( $validation['errors'] ),
		'warnings'               => array_values( $validation['warnings'] ),
		'can_reactivate'         => current_user_can( reactivate_capability() ),
	);
}
