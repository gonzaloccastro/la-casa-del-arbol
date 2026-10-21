<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<!doctype html>
<html lang="es">
<body style="margin:0;background:#f4f1ec;color:#26231f;font-family:Arial,sans-serif;line-height:1.5">
	<table role="presentation" style="width:100%;border-collapse:collapse"><tr><td align="center" style="padding:24px">
		<table role="presentation" style="width:100%;max-width:600px;background:#fff;border-collapse:collapse"><tr><td style="padding:32px">
			<p style="font-size:13px;letter-spacing:.08em;text-transform:uppercase"><?php echo esc_html( $data['site_name'] ); ?></p>
			<h1 style="font-size:25px">Tu evento es mañana</h1>
			<p>Pedido #<?php echo esc_html( $data['order']->get_order_number() ); ?> · <?php echo esc_html( $data['event']['title'] ); ?></p>
			<p>Fecha y hora: <?php echo esc_html( $data['event']['start_local'] ); ?> (<?php echo esc_html( $data['event']['timezone'] ); ?>)</p>
			<p>Lugar: <?php echo esc_html( $data['event']['venue'] ); ?><?php if ( ! empty( $data['event']['venue_address'] ) ) : ?> · <?php echo esc_html( $data['event']['venue_address'] ); ?><?php endif; ?></p>
			<?php foreach ( $data['groups'] as $group ) : ?>
				<h2 style="font-size:18px"><?php echo esc_html( $group['name'] ); ?> · <?php echo esc_html( $group['quantity'] ); ?> entradas</h2>
				<ul><?php foreach ( $group['codes'] as $code ) : ?><li><code><?php echo esc_html( $code ); ?></code></li><?php endforeach; ?></ul>
			<?php endforeach; ?>
			<p>Guarda los identificadores de tus entradas para presentarlos al ingresar.</p>
		</td></tr></table>
	</td></tr></table>
</body>
</html>
