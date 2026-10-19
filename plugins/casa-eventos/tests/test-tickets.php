<?php
/** T5 identifiers are opaque, stable-length, and generated from cryptographic randomness. */
use CasaEventos\Commerce;

t_group( 'T5/ticket identifier' );
$ticket_code = Commerce\ticket_identifier();
t_ok( is_string( $ticket_code ) && preg_match( '/^[a-f0-9]{64}$/D', $ticket_code ), 'ticket identifier has 256 bits of opaque hex entropy' );
t_ok( $ticket_code !== Commerce\ticket_identifier(), 'ticket identifiers are independently generated' );
