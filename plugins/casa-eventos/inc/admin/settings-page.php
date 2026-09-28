<?php
/**
 * Settings screen (Eventos → Ajustes): the single venue.
 *
 * @package CasaEventos
 */

namespace CasaEventos\Admin;

use CasaEventos\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const SETTINGS_PAGE = 'casa-eventos-ajustes';

/**
 * Add the submenu page.
 */
function add_settings_page() {
	add_submenu_page(
		'edit.php?post_type=' . Core\POST_TYPE,
		__( 'Ajustes de eventos', 'casa-eventos' ),
		__( 'Ajustes', 'casa-eventos' ),
		'manage_options',
		SETTINGS_PAGE,
		__NAMESPACE__ . '\\render_settings_page'
	);
}
add_action( 'admin_menu', __NAMESPACE__ . '\\add_settings_page' );

/**
 * Sections and fields.
 */
function settings_fields_init() {
	add_settings_section(
		'casa_eventos_venue',
		__( 'Lugar', 'casa-eventos' ),
		static function () {
			echo '<p>' . esc_html__( 'Todos los eventos se realizan en este lugar (un solo espacio en V1).', 'casa-eventos' ) . '</p>';
		},
		SETTINGS_PAGE
	);

	$fields = array(
		'venue_name'    => __( 'Nombre', 'casa-eventos' ),
		'venue_address' => __( 'Dirección', 'casa-eventos' ),
	);
	foreach ( $fields as $key => $label ) {
		add_settings_field(
			'casa_eventos_' . $key,
			$label,
			__NAMESPACE__ . '\\render_text_field',
			SETTINGS_PAGE,
			'casa_eventos_venue',
			array(
				'key'       => $key,
				'label_for' => 'casa-eventos-' . $key,
			)
		);
	}
}
add_action( 'admin_init', __NAMESPACE__ . '\\settings_fields_init' );

/**
 * Text input for one setting.
 *
 * @param array $args Field args.
 */
function render_text_field( $args ) {
	$settings = Core\settings();
	printf(
		'<input type="text" class="regular-text" id="%1$s" name="%2$s[%3$s]" value="%4$s">',
		esc_attr( $args['label_for'] ),
		esc_attr( Core\SETTINGS ),
		esc_attr( $args['key'] ),
		esc_attr( $settings[ $args['key'] ] )
	);
}

/**
 * Render the page.
 */
function render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Ajustes de eventos', 'casa-eventos' ); ?></h1>
		<form action="options.php" method="post">
			<?php
			settings_fields( 'casa_eventos' );
			do_settings_sections( SETTINGS_PAGE );
			submit_button();
			?>
		</form>
		<h2><?php esc_html_e( 'Zona horaria', 'casa-eventos' ); ?></h2>
		<p>
			<?php
			$named = Core\site_named_timezone();
			echo esc_html(
				'' !== $named
					/* translators: %s: timezone identifier. */
					? sprintf( __( 'Zona horaria del sitio: %s. Cada evento guarda la zona con la que se creó.', 'casa-eventos' ), $named )
					/* translators: %s: timezone identifier. */
					: sprintf( __( 'El sitio usa un desfase manual. Los eventos nuevos usan %s.', 'casa-eventos' ), Core\timezone_for_new_event() )
			);
			?>
		</p>
	</div>
	<?php
}
