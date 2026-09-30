<?php
/**
 * Architecture and ownership checks over the source tree.
 *
 *     php plugins/casa-eventos/tests/check-architecture.php
 *
 * @package CasaEventos
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

$plugin = dirname( __DIR__ );
$repo   = dirname( $plugin, 2 );
$theme  = $repo . '/themes/la-casa-del-arbol';
$errors = array();
$checks = 0;

/**
 * Source files of a tree, excluding tests.
 *
 * @param string   $root Root.
 * @param string[] $ext  Extensions.
 * @return string[]
 */
function source_files( $root, array $ext ) {
	$out = array();
	$it  = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $file ) {
		$path = str_replace( '\\', '/', $file->getPathname() );
		if ( false !== strpos( $path, '/tests/' ) || ! in_array( $file->getExtension(), $ext, true ) ) {
			continue;
		}
		$out[] = $path;
	}
	sort( $out );
	return $out;
}

/**
 * Source without comments (PHP tokens or JS/CSS block and line comments).
 *
 * @param string $path File.
 * @return string
 */
function code_only( $path ) {
	$src = file_get_contents( $path );
	if ( '.php' === substr( $path, -4 ) ) {
		$code = '';
		foreach ( token_get_all( $src ) as $token ) {
			if ( is_array( $token ) && in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}
			$code .= is_array( $token ) ? $token[1] : $token;
		}
		return $code;
	}
	return preg_replace( '#/\*.*?\*/|(?<![:\'"])//[^\n]*#s', '', $src );
}

$php = source_files( $plugin, array( 'php' ) );
$js  = source_files( $plugin, array( 'js' ) );
$rel = static fn( $p ) => substr( $p, strlen( $plugin ) + 1 );

// 1. Only inc/core/ names the meta schema.
foreach ( $php as $file ) {
	++$checks;
	if ( 0 === strpos( $rel( $file ), 'inc/core/' ) ) {
		continue;
	}
	$code = code_only( $file );
	if ( preg_match( '/_casa_|\bMETA_[A-Z_]+|\bTERM_(COLOR|ORDER)\b|get_post_meta|update_post_meta|get_term_meta|update_term_meta/', $code, $m ) ) {
		$errors[] = $rel( $file ) . ': reads/writes Event meta outside inc/core/ (' . $m[0] . ')';
	}
}

// 2. No WooCommerce dependency in Event Core (code, not docs).
foreach ( array_merge( $php, $js ) as $file ) {
	++$checks;
	if ( preg_match( '/woocommerce|\bWC_|\bwc_[a-z]|\bWC\(\)/i', code_only( $file ), $m ) ) {
		$errors[] = $rel( $file ) . ': WooCommerce reference (' . $m[0] . ')';
	}
}

// 3. Every PHP file is guarded against direct access.
foreach ( $php as $file ) {
	++$checks;
	$src = file_get_contents( $file );
	if ( false === strpos( $src, "defined( 'ABSPATH' )" ) && false === strpos( $src, "defined( 'WP_UNINSTALL_PLUGIN' )" ) ) {
		$errors[] = $rel( $file ) . ': no ABSPATH / WP_UNINSTALL_PLUGIN guard';
	}
}

// 4. Never the server default timezone; never a generated excerpt.
foreach ( $php as $file ) {
	++$checks;
	if ( preg_match( '/(?<![\w>:$])(date|strtotime|mktime|current_time|date_default_timezone_set|get_the_excerpt|the_excerpt)\s*\(/', code_only( $file ), $m ) ) {
		$errors[] = $rel( $file ) . ': forbidden call ' . $m[1] . '()';
	}
}

// 5. Plain JavaScript: no modules, no JSX, no bundler output.
foreach ( $js as $file ) {
	++$checks;
	$code = code_only( $file );
	if ( preg_match( '/^\s*import\s|\brequire\s*\(|<\s*[A-Z][A-Za-z]*[\s>\/]/m', $code, $m ) ) {
		$errors[] = $rel( $file ) . ': module syntax or JSX (' . trim( $m[0] ) . ')';
	}
}

// 6. No WhatsApp number or URL in the plugin (single source: the theme menu).
foreach ( array_merge( $php, $js ) as $file ) {
	++$checks;
	if ( preg_match( '#wa\.me|api\.whatsapp|whatsapp\.com#i', code_only( $file ), $m ) ) {
		$errors[] = $rel( $file ) . ': WhatsApp URL (' . $m[0] . ')';
	}
}

// 7. Version 0.2.0 everywhere.
++$checks;
$main = file_get_contents( $plugin . '/casa-eventos.php' );
preg_match( '/^\s*\*\s*Version:\s*(\S+)/m', $main, $header );
preg_match( "/define\( 'CASA_EVENTOS_VERSION', '([^']+)' \)/", $main, $constant );
preg_match( '/Version:\s*\*{0,2}\s*([0-9.]+)/', (string) @file_get_contents( $plugin . '/readme.md' ), $readme );
$versions = array( $header[1] ?? '?', $constant[1] ?? '?', $readme[1] ?? '?' );
if ( array( '0.2.0', '0.2.0', '0.2.0' ) !== $versions ) {
	$errors[] = 'version mismatch (header, constant, readme): ' . implode( ', ', $versions );
}

// 8. No build tooling or dependency manifests.
foreach ( array( 'package.json', 'composer.json', 'node_modules', 'vendor', 'webpack.config.js', 'build' ) as $name ) {
	++$checks;
	if ( file_exists( $plugin . '/' . $name ) ) {
		$errors[] = 'unexpected build/dependency artifact: ' . $name;
	}
}

/**
 * Rule 9: the theme renders events only through the documented public API
 * (CasaEventos\Core\Event, CasaEventos\Core\Queries, and POST_TYPE for
 * template conditions). It never registers the domain, reads event meta,
 * queries events itself, names the schema, or re-derives Event Core rules
 * (dates → finished, sales cutoff, access-mode targets): those come from
 * Event::cta() / effective_state() and the queries.
 *
 * @param string $code Source without comments.
 * @return string[] Violations.
 */
function theme_domain_violations( $code ) {
	$out = array();
	$forbidden = array(
		'registers the domain'          => '/\bregister_(post_type|taxonomy|post_meta|term_meta|meta)\s*\(/',
		'names event meta keys'         => '/_casa_/',
		'reads or writes meta directly' => '/\b(get|update|add|delete)_(post|term)_meta\s*\(|\bget_metadata\s*\(/',
		'hard-codes the schema'         => '/[\'"]casa_(evento|categoria)[\'"]/',
		'duplicates an Event Core rule' => '/->\s*(is_before_sales_close|sales_close\w*|tickets_on_sale|ends_gmt|end_gmt|start_gmt|effective_end|is_finished|is_actionable|capacity|validation|external_url|access_mode|status)\s*\(/',
	);
	foreach ( $forbidden as $what => $re ) {
		if ( preg_match( $re, $code, $m ) ) {
			$out[] = $what . ' (' . trim( $m[0] ) . ')';
		}
	}
	if ( preg_match_all( '/\\\\?CasaEventos\\\\[A-Za-z_\\\\]+/', $code, $all ) ) {
		foreach ( array_unique( $all[0] ) as $symbol ) {
			if ( ! preg_match( '/^\\\\?CasaEventos\\\\Core\\\\(Event|Queries|POST_TYPE)$/', $symbol ) ) {
				$out[] = 'uses a non-public plugin symbol (' . $symbol . ')';
			}
		}
		if ( preg_match( '/\bnew\s+\\\\?WP_Query\b|\bget_posts\s*\(|\bquery_posts\s*\(|[\'"]pre_get_posts[\'"]/', $code, $m ) ) {
			$out[] = 'queries events directly (' . trim( $m[0] ) . ')';
		}
	}
	return $out;
}

// Self-test of rule 9 (allowed and forbidden samples).
$rule9_samples = array(
	'<?php use CasaEventos\Core\Event; $e = Event::get( get_post() ); echo $e->title(); $c = $e->cta();'           => 0,
	'<?php $r = \CasaEventos\Core\Queries::related_events( $e ); is_singular( \CasaEventos\Core\POST_TYPE );'      => 0,
	'<?php $x = new WP_Query( array( "post_type" => "page" ) );'                                                    => 0,
	'<?php register_post_type( "x", array() );'                                                                     => 1,
	'<?php get_post_meta( $id, "_casa_start", true );'                                                              => 2,
	'<?php is_singular( "casa_evento" );'                                                                           => 1,
	'<?php use CasaEventos\Core\Event; $q = new WP_Query( array() );'                                               => 1,
	'<?php use CasaEventos\Core\Queries; get_posts( array() );'                                                     => 1,
	'<?php \CasaEventos\Core\categories();'                                                                         => 1,
	'<?php $t = \CasaEventos\Core\Queries::categories(); $m = \CasaEventos\Core\Queries::parse_month( $raw );'     => 0,
	'<?php $m = \CasaEventos\Core\parse_month( $raw );'                                                             => 1,
	'<?php get_term_meta( $term->term_id, "order", true );'                                                         => 1,
	'<?php if ( $event->is_before_sales_close() ) {}'                                                               => 1,
	'<?php $u = $event->external_url(); $m = $event->access_mode();'                                                => 1,
);
foreach ( $rule9_samples as $sample => $expected ) {
	++$checks;
	if ( count( theme_domain_violations( $sample ) ) !== $expected ) {
		$errors[] = 'rule 9 self-test: expected ' . $expected . ' violation(s) for ' . $sample . ', got ' . implode( '; ', theme_domain_violations( $sample ) );
	}
}

// 9. The theme stays presentation-only (see theme_domain_violations()).
if ( is_dir( $theme ) ) {
	foreach ( source_files( $theme, array( 'php' ) ) as $file ) {
		++$checks;
		foreach ( theme_domain_violations( code_only( $file ) ) as $violation ) {
			$errors[] = 'theme ' . substr( $file, strlen( $theme ) + 1 ) . ': ' . $violation;
		}
	}
}

foreach ( $errors as $error ) {
	fwrite( STDERR, "FAIL  {$error}\n" );
}
printf( "architecture: %d checks, %d problems\n", $checks, count( $errors ) );
exit( $errors ? 1 : 0 );
