<?php
/**
 * Event presentation helpers (E2).
 *
 * The theme renders events; casa-eventos owns them. Everything here reads
 * the plugin's documented public API only: CasaEventos\Core\Event (the read
 * model), CasaEventos\Core\Queries and CasaEventos\Core\POST_TYPE. No event
 * meta, no event queries, and no business rule is decided here: state,
 * eligibility and whether a call to action exists come from the plugin
 * (Event::cta(), the queries). This file only formats values and chooses
 * wording and markup. Enforced by the plugin's architecture check (rule 9).
 *
 * Contracts: docs/implementation/single-event-contract.md and
 * event-markup-contract.md.
 *
 * @package LaCasaDelArbol
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether casa-eventos is active and exposes its public API.
 *
 * @return bool
 */
function lcda_events_available() {
	return class_exists( 'CasaEventos\Core\Event' ) && class_exists( 'CasaEventos\Core\Queries' ) && defined( 'CasaEventos\Core\POST_TYPE' );
}

/**
 * Whether the current request is an Event single (/evento/{slug}/).
 *
 * @return bool
 */
function lcda_is_event_single() {
	return lcda_events_available() && is_singular( \CasaEventos\Core\POST_TYPE );
}

/**
 * Format an instant in its own timezone with WordPress's localized dates
 * (never PHP's default timezone).
 *
 * @param DateTimeImmutable $datetime Date in the event timezone.
 * @param string            $format   wp_date() format.
 * @return string
 */
function lcda_event_format( DateTimeImmutable $datetime, $format ) {
	return (string) wp_date( $format, $datetime->getTimestamp(), $datetime->getTimezone() );
}

/**
 * Capitalized weekday + date, e.g. "Sábado 05/09". Spanish weekday names
 * start with an ASCII letter, so ucfirst() is enough.
 *
 * @param DateTimeImmutable $datetime Date.
 * @return string
 */
function lcda_event_day( DateTimeImmutable $datetime ) {
	return ucfirst( lcda_event_format( $datetime, 'l d/m' ) );
}

/**
 * A <time> element with an ISO 8601 datetime (with its offset).
 *
 * @param DateTimeImmutable $datetime Date.
 * @param string            $text     Visible text (unescaped).
 * @return string
 */
function lcda_event_time_tag( DateTimeImmutable $datetime, $text ) {
	return '<time datetime="' . esc_attr( $datetime->format( 'c' ) ) . '">' . esc_html( $text ) . '</time>';
}

/**
 * "Cuándo" markup. Only the start and, when the event declares one, its
 * end: the derived default end (start + 3 h) is a state rule, never shown.
 *
 * - start only: "Sábado 05/09 · 21:00hs"
 * - same-day end: "Sábado 05/09 · 21:00 → 23:30hs"
 * - end on another day: "Viernes 30/10 · 21:00hs → Lunes 02/11 · 03:00hs"
 *
 * @param \CasaEventos\Core\Event $event Event.
 * @return string HTML, '' without a start.
 */
function lcda_event_when_html( $event ) {
	$start = $event->start();
	if ( ! $start ) {
		return '';
	}
	$end   = $event->has_declared_end() ? $event->end() : null;
	$arrow = '<span aria-hidden="true"> → </span><span class="screen-reader-text"> ' . esc_html__( 'hasta', 'la-casa-del-arbol' ) . ' </span>';

	if ( ! $end ) {
		return lcda_event_time_tag( $start, lcda_event_day( $start ) . ' · ' . lcda_event_format( $start, 'H:i' ) . 'hs' );
	}
	if ( $start->format( 'Y-m-d' ) === $end->format( 'Y-m-d' ) ) {
		return lcda_event_time_tag( $start, lcda_event_day( $start ) . ' · ' . lcda_event_format( $start, 'H:i' ) )
			. $arrow . lcda_event_time_tag( $end, lcda_event_format( $end, 'H:i' ) ) . 'hs';
	}
	return lcda_event_time_tag( $start, lcda_event_day( $start ) . ' · ' . lcda_event_format( $start, 'H:i' ) . 'hs' )
		. $arrow . lcda_event_time_tag( $end, lcda_event_day( $end ) . ' · ' . lcda_event_format( $end, 'H:i' ) . 'hs' );
}

/**
 * Card meta: "Sábado · 21:00" (visual) + the full date for screen readers,
 * inside one <time>. With $with_date (the Home featured card, whose events
 * span several months) the visual text is "Sáb 03/10 · 21:00".
 *
 * @param \CasaEventos\Core\Event $event     Event.
 * @param bool                    $with_date Short weekday + dd/mm in the visual text.
 * @return string HTML, '' without a start.
 */
function lcda_event_card_meta_html( $event, $with_date = false ) {
	$start = $event->start();
	if ( ! $start ) {
		return '';
	}
	$day    = $with_date ? lcda_event_format( $start, 'D d/m' ) : lcda_event_format( $start, 'l' );
	$visual = ucfirst( $day ) . ' · ' . lcda_event_format( $start, 'H:i' );
	$full   = ucfirst( lcda_event_format( $start, 'l j \d\e F, H:i' ) );

	return '<time datetime="' . esc_attr( $start->format( 'c' ) ) . '"><span aria-hidden="true">' . esc_html( $visual ) . '</span><span class="screen-reader-text">' . esc_html( $full ) . '</span></time>';
}

/**
 * Day of month for the burst ("05").
 *
 * @param \CasaEventos\Core\Event $event Event.
 * @return string '' without a start.
 */
function lcda_event_burst_day( $event ) {
	$start = $event->start();
	return $start ? lcda_event_format( $start, 'd' ) : '';
}

/**
 * Public entry text: the editorial entry label; otherwise the wording of a
 * free or "a la gorra" entry; otherwise nothing (a paid event without a
 * label shows no invented price and no admin wording).
 *
 * @param \CasaEventos\Core\Event $event Event.
 * @return string
 */
function lcda_event_entry_text( $event ) {
	$label = trim( $event->entry_label() );
	if ( '' !== $label ) {
		return $label;
	}
	switch ( $event->entry_kind() ) {
		case 'free':
			return __( 'Entrada libre', 'la-casa-del-arbol' );
		case 'gorra':
			return __( 'A la gorra', 'la-casa-del-arbol' );
	}
	return '';
}

/**
 * Stamp text for the full card: only free and "a la gorra" entries.
 *
 * @param \CasaEventos\Core\Event $event Event.
 * @return string
 */
function lcda_event_stamp_text( $event ) {
	switch ( $event->entry_kind() ) {
		case 'free':
			return __( 'Entrada libre', 'la-casa-del-arbol' );
		case 'gorra':
			return __( 'A la gorra', 'la-casa-del-arbol' );
	}
	return '';
}

/**
 * Category tag classes ("lcda-tag", + "lcda-tag--yellow" when the category
 * is yellow; mint is the default look).
 *
 * @param \CasaEventos\Core\Event $event Event.
 * @param string                  $extra Extra classes.
 * @return string
 */
function lcda_event_tag_class( $event, $extra = '' ) {
	$classes = trim( 'lcda-tag ' . $extra );
	return 'yellow' === $event->category_color() ? $classes . ' lcda-tag--yellow' : $classes;
}

/**
 * Poster <img>, or '' when the event has no poster (the frame then keeps
 * its 4:5 placeholder). Width/height are intrinsic, so the poster keeps its
 * ratio; the alt text is the attachment's own (often empty on purpose).
 *
 * @param \CasaEventos\Core\Event $event   Event.
 * @param string                  $context 'detail' (the page's main image), 'card'
 *                                         (Home / Related scroller cards) or 'wall'
 *                                         (Agenda masonry: full gutter width on
 *                                         mobile, 2 then 3 columns).
 * @return string
 */
function lcda_event_poster_html( $event, $context = 'card' ) {
	$id = $event->poster_id();
	if ( ! $id ) {
		return '';
	}
	if ( 'detail' === $context ) {
		return (string) wp_get_attachment_image(
			$id,
			'full',
			false,
			array(
				'class'         => 'lcda-event-card__image',
				'sizes'         => '(max-width: 767px) 100vw, 580px',
				'loading'       => false,
				'fetchpriority' => 'high',
			)
		);
	}
	return (string) wp_get_attachment_image(
		$id,
		'medium_large',
		false,
		array(
			'class'   => 'lcda-event-card__image',
			'sizes'   => 'wall' === $context
				? '(max-width: 767px) calc(100vw - 32px), (max-width: 1023px) calc(50vw - 44px), (max-width: 1439px) calc(33.3vw - 37px), 443px'
				: '(max-width: 767px) 70vw, (max-width: 1023px) 50vw, 460px',
			'loading' => 'lazy',
		)
	);
}

/**
 * WhatsApp destination of an Event's reservation CTA: the La Casa number
 * (+54 9 11 4038-5603) with the event title in the opening message.
 *
 * @param \CasaEventos\Core\Event $event Event.
 * @return string Unescaped URL (escape on output).
 */
function lcda_event_whatsapp_url( $event ) {
	$message = sprintf( 'Hola! Me interesaba la actividad %s', $event->title() );
	return 'https://wa.me/5491140385603?text=' . rawurlencode( $message );
}

/**
 * The primary action of the Single, from the plugin's CTA decision.
 *
 * The plugin decides whether an action exists (state, mode, target,
 * tickets before commerce). The theme only words it and resolves the
 * WhatsApp destination (lcda_event_whatsapp_url()).
 *
 * @param \CasaEventos\Core\Event $event Event.
 * @return array{type: string, label?: string, attributes?: string, text?: string}|null
 *         link: a real link; note: a non-interactive state note; null: nothing.
 */
function lcda_event_action( $event ) {
	$cta = $event->cta();

	if ( $cta['available'] ) {
		if ( 'external' === $cta['mode'] && '' !== $cta['url'] ) {
			return array(
				'type'       => 'link',
				'label'      => __( 'Comprar entradas', 'la-casa-del-arbol' ),
				'attributes' => 'href="' . esc_url( $cta['url'] ) . '"',
			);
		}
		if ( 'whatsapp' === $cta['mode'] ) {
			return array(
				'type'       => 'link',
				'label'      => __( 'Reservar', 'la-casa-del-arbol' ),
				'attributes' => lcda_link_attributes(
					array(
						'url'    => lcda_event_whatsapp_url( $event ),
						'target' => '_blank',
						'rel'    => 'noopener noreferrer',
					)
				),
			);
		}
		// Own ticket sales (commerce phase) render their purchase control here.
		return null;
	}

	$notes = array(
		'cancelled'   => __( 'Evento cancelado', 'la-casa-del-arbol' ),
		'paused'      => __( 'Reservas pausadas', 'la-casa-del-arbol' ),
		'finished'    => __( 'Este evento ya pasó', 'la-casa-del-arbol' ),
		'not_on_sale' => __( 'Entradas a la venta próximamente', 'la-casa-del-arbol' ),
	);

	return isset( $notes[ $cta['reason'] ] ) ? array( 'type' => 'note', 'text' => $notes[ $cta['reason'] ] ) : null;
}

/**
 * "← Volver a la agenda" destination: the published Agenda page (slug
 * "agenda"), on the event's own month when that is not the current month.
 *
 * @param \CasaEventos\Core\Event $event Event.
 * @return string '' when there is no Agenda page (the link is then omitted).
 */
function lcda_event_back_url( $event ) {
	$agenda = get_page_by_path( 'agenda' );
	if ( ! $agenda || 'publish' !== $agenda->post_status ) {
		return '';
	}
	$url   = (string) get_permalink( $agenda );
	$month = $event->month();
	if ( '' !== $month && \CasaEventos\Core\Queries::current_month() !== $month ) {
		$url = add_query_arg( 'mes', $month, $url );
	}
	return $url;
}
