<?php
/**
 * Event read model: the typed access layer over an Event post.
 *
 * Later modules (frontend, commerce, notifications) read events ONLY through
 * this class and the query API. They never read _casa_* meta directly, so
 * the storage schema can change behind this seam.
 *
 * Values are raw data. Escaping and formatting belong to the caller.
 *
 * @package CasaEventos
 */

namespace CasaEventos\Core;

use DateTimeImmutable;
use WP_Post;
use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read model of one Event.
 */
final class Event {

	/**
	 * Underlying post.
	 *
	 * @var WP_Post
	 */
	private $post;

	/**
	 * Constructor. Use Event::get().
	 *
	 * @param WP_Post $post Event post.
	 */
	private function __construct( WP_Post $post ) {
		$this->post = $post;
	}

	/**
	 * Load an Event.
	 *
	 * @param int|WP_Post|null $post Post or ID.
	 * @return Event|null Null when it is not an Event.
	 */
	public static function get( $post ) {
		$post = get_post( $post );
		if ( ! $post instanceof WP_Post || POST_TYPE !== $post->post_type ) {
			return null;
		}
		return new self( $post );
	}

	/**
	 * Raw meta value.
	 *
	 * @param string $key Meta key.
	 * @return mixed
	 */
	private function meta( $key ) {
		return get_post_meta( $this->post->ID, $key, true );
	}

	/**
	 * String meta value.
	 *
	 * @param string $key Meta key.
	 * @return string
	 */
	private function meta_string( $key ) {
		$value = $this->meta( $key );
		return is_scalar( $value ) ? (string) $value : '';
	}

	// ----- Identity and editorial -------------------------------------------

	/** @return int */
	public function id() {
		return (int) $this->post->ID;
	}

	/** @return WP_Post */
	public function post() {
		return $this->post;
	}

	/** @return string UUID v4, '' until the first real save. */
	public function uuid() {
		return sanitize_uuid( $this->meta( META_UUID ) );
	}

	/** @return string Raw post title. */
	public function title() {
		return (string) $this->post->post_title;
	}

	/** @return string Subtitle (bajada): the raw excerpt, never generated from content. */
	public function subtitle() {
		return (string) $this->post->post_excerpt;
	}

	/** @return string WordPress post status. */
	public function post_status() {
		return (string) $this->post->post_status;
	}

	/** @return bool Whether the event is published (public). */
	public function is_published() {
		return 'publish' === $this->post->post_status;
	}

	/** @return string Permalink. */
	public function permalink() {
		return (string) get_permalink( $this->post );
	}

	/** @return int Poster attachment ID (featured image), 0 when none. */
	public function poster_id() {
		return (int) get_post_thumbnail_id( $this->post );
	}

	// ----- Category ---------------------------------------------------------

	/**
	 * Categories attached to the event, in chip order.
	 *
	 * @return WP_Term[]
	 */
	public function categories() {
		$terms = get_the_terms( $this->post, TAXONOMY );
		if ( ! is_array( $terms ) ) {
			return array();
		}
		usort( $terms, __NAMESPACE__ . '\\compare_categories' );
		return $terms;
	}

	/**
	 * The event's category. Exactly one is required to publish; if data was
	 * written around validation, the first in chip order is used.
	 *
	 * @return WP_Term|null
	 */
	public function category() {
		$terms = $this->categories();
		return $terms ? $terms[0] : null;
	}

	/** @return string mint | yellow, '' without a category. */
	public function category_color() {
		$term = $this->category();
		return $term ? category_color( $term ) : '';
	}

	// ----- Dates ------------------------------------------------------------

	/** @return string IANA timezone of the event. */
	public function timezone() {
		$tz = sanitize_timezone( $this->meta( META_TIMEZONE ) );
		return '' !== $tz ? $tz : timezone_for_new_event();
	}

	/** @return string Local start 'Y-m-d H:i:s', '' when not set. */
	public function start_local() {
		return sanitize_local_datetime( $this->meta( META_START ) );
	}

	/** @return string Local declared end, '' when not set. */
	public function end_local() {
		return sanitize_local_datetime( $this->meta( META_END ) );
	}

	/** @return DateTimeImmutable|null Start in the event timezone. */
	public function start() {
		return local_datetime( $this->start_local(), $this->timezone() );
	}

	/** @return DateTimeImmutable|null Declared end in the event timezone (only when after the start). */
	public function end() {
		return '' !== $this->end_gmt() ? local_datetime( $this->end_local(), $this->timezone() ) : null;
	}

	/** @return bool Whether an end was declared (and is after the start). */
	public function has_declared_end() {
		return '' !== $this->end_gmt();
	}

	/**
	 * Derived GMT values. Read from storage (written by sync); recomputed on
	 * the fly if a stored value is missing (e.g. data written around hooks).
	 *
	 * @return array{start_gmt:string,end_gmt:string,ends_gmt:string}
	 */
	private function gmt() {
		$stored = array(
			'start_gmt' => sanitize_gmt_datetime( $this->meta( META_START_GMT ) ),
			'end_gmt'   => sanitize_gmt_datetime( $this->meta( META_END_GMT ) ),
			'ends_gmt'  => sanitize_gmt_datetime( $this->meta( META_ENDS_GMT ) ),
		);
		if ( '' !== $stored['start_gmt'] && '' !== $stored['ends_gmt'] ) {
			return $stored;
		}
		return derive_gmt( $this->start_local(), $this->end_local(), $this->timezone(), default_duration_minutes() );
	}

	/** @return string Start in GMT. */
	public function start_gmt() {
		return $this->gmt()['start_gmt'];
	}

	/** @return string Declared end in GMT, '' when none. */
	public function end_gmt() {
		return $this->gmt()['end_gmt'];
	}

	/** @return string Effective end in GMT (declared end, or start + default duration). */
	public function ends_gmt() {
		return $this->gmt()['ends_gmt'];
	}

	/** @return DateTimeImmutable|null Effective end in the event timezone. */
	public function effective_end() {
		$dt = gmt_datetime( $this->ends_gmt() );
		return $dt ? $dt->setTimezone( timezone_object( $this->timezone() ) ) : null;
	}

	/** @return string 'YYYY-MM' of the local start (the month the event belongs to), '' without a start. */
	public function month() {
		$start = $this->start_local();
		return '' !== $start ? substr( $start, 0, 7 ) : '';
	}

	// ----- State ------------------------------------------------------------

	/** @return string Stored operational status: active | paused | cancelled. */
	public function status() {
		return normalize_status( $this->meta( META_STATUS ) );
	}

	/** @return bool */
	public function is_cancelled() {
		return STATUS_CANCELLED === $this->status();
	}

	/** @return bool */
	public function is_paused() {
		return STATUS_PAUSED === $this->status();
	}

	/** @return string GMT instant of the cancellation, '' when not cancelled. */
	public function cancelled_gmt() {
		return sanitize_gmt_datetime( $this->meta( META_CANCELLED_GMT ) );
	}

	/**
	 * Whether the effective end has passed.
	 *
	 * @param int|null $timestamp Now (unix); defaults to time().
	 * @return bool
	 */
	public function is_finished( $timestamp = null ) {
		return is_finished( $this->ends_gmt(), now_gmt( $timestamp ) );
	}

	/**
	 * Effective state: draft | scheduled | active | paused | cancelled | finished.
	 *
	 * @param int|null $timestamp Now (unix); defaults to time().
	 * @return string
	 */
	public function effective_state( $timestamp = null ) {
		return effective_state( $this->post_status(), $this->status(), $this->ends_gmt(), now_gmt( $timestamp ) );
	}

	/**
	 * Whether a live call to action may be offered (effective state active).
	 *
	 * @param int|null $timestamp Now (unix); defaults to time().
	 * @return bool
	 */
	public function is_actionable( $timestamp = null ) {
		return is_actionable_state( $this->effective_state( $timestamp ) );
	}

	// ----- Visibility -------------------------------------------------------

	/** @return bool Eligible for Agenda / Home / Related. */
	public function is_listed() {
		return '1' === to_flag( $this->meta( META_LISTED ) );
	}

	/** @return bool Eligible for Home featured events. */
	public function is_featured() {
		return '1' === to_flag( $this->meta( META_FEATURED ) );
	}

	// ----- Access and entry -------------------------------------------------

	/** @return string tickets | whatsapp | external, '' when not set. */
	public function access_mode() {
		return sanitize_access_mode( $this->meta( META_ACCESS_MODE ) );
	}

	/** @return string paid | free | gorra, '' when not specified. */
	public function entry_kind() {
		return sanitize_entry_kind( $this->meta( META_ENTRY_KIND ) );
	}

	/** @return string Optional editorial label; never interpreted as data. */
	public function entry_label() {
		return $this->meta_string( META_ENTRY_LABEL );
	}

	/** @return string External purchase URL, only in external mode ('' otherwise). */
	public function external_url() {
		return ACCESS_EXTERNAL === $this->access_mode() ? sanitize_external_url( $this->meta_string( META_EXTERNAL_URL ) ) : '';
	}

	// ----- Capacity and sales -----------------------------------------------

	/** @return int Capacity (materialized on save; the default only before the first save). */
	public function capacity() {
		return sanitize_capacity( $this->meta( META_CAPACITY ) );
	}

	/** @return string Explicit local sales cutoff, '' when the default applies. */
	public function sales_close_local_override() {
		return sanitize_local_datetime( $this->meta( META_SALES_CLOSE ) );
	}

	/** @return bool Whether the default cutoff (start − offset) applies. */
	public function sales_close_is_default() {
		return '' === $this->sales_close_local_override();
	}

	/** @return string Effective sales cutoff in GMT, '' without a start. */
	public function sales_close_gmt() {
		return sales_close_gmt( $this->sales_close_local_override(), $this->start_gmt(), $this->timezone(), default_sales_close_offset_minutes() );
	}

	/** @return DateTimeImmutable|null Effective sales cutoff in the event timezone. */
	public function sales_close() {
		$dt = gmt_datetime( $this->sales_close_gmt() );
		return $dt ? $dt->setTimezone( timezone_object( $this->timezone() ) ) : null;
	}

	/**
	 * Time part of the sales rule only: now is before the effective cutoff.
	 * Purchasability (state, mode, capacity, holds) is a commerce concern.
	 *
	 * @param int|null $timestamp Now (unix); defaults to time().
	 * @return bool
	 */
	public function is_before_sales_close( $timestamp = null ) {
		$close = $this->sales_close_gmt();
		return '' !== $close && now_gmt( $timestamp ) < $close;
	}

	// ----- Call to action ---------------------------------------------------

	/**
	 * Whether own ticket sales exist. False until the commerce phase, which
	 * enables it through the filter.
	 *
	 * @return bool
	 */
	public function tickets_on_sale() {
		return (bool) apply_filters( 'casa_eventos/ticket_sales_available', false, $this );
	}

	/**
	 * The public call-to-action decision (rules: cta_decision() in state.php).
	 * Frontends decide wording and markup; they resolve the WhatsApp
	 * destination themselves (the site's single WhatsApp CTA).
	 *
	 * @param int|null $timestamp Now (unix); defaults to time().
	 * @return array{available: bool, mode: string, reason: string, state: string, url: string}
	 *         mode: tickets | whatsapp | external | ''. reason: available,
	 *         no_mode, no_target, not_on_sale, sales_closed, or the
	 *         non-actionable effective state (draft, scheduled, paused,
	 *         cancelled, finished). url: the external target when available.
	 */
	public function cta( $timestamp = null ) {
		return cta_decision(
			$this->effective_state( $timestamp ),
			$this->access_mode(),
			$this->external_url(),
			$this->tickets_on_sale(),
			$this->is_before_sales_close( $timestamp )
		);
	}

	// ----- Venue (single venue in V1) ---------------------------------------

	/** @return string */
	public function venue_name() {
		return venue_name();
	}

	/** @return string */
	public function venue_address() {
		return venue_address();
	}

	// ----- Validation -------------------------------------------------------

	/**
	 * Validation of the stored data, as if it were published now.
	 *
	 * @param int|null $timestamp Now (unix); defaults to time().
	 * @return array{errors:array<string,string>,warnings:array<string,string>}
	 */
	public function validation( $timestamp = null ) {
		return validate_event_data( stored_validation_data( $this->post, 'publish', $timestamp ) );
	}
}
