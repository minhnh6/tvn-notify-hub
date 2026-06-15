<?php
/**
 * Contract (interface) for a notification channel.
 *
 * Each chat application (Slack, Telegram, WhatsApp, X...) is an independent channel:
 * it declares its own config fields, renders its own UI, sanitizes its own data and sends on its own.
 * To add a new channel, just implement this interface and register it via
 * the 'tvn_notify_hub_channels' filter — no need to modify the core.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface Tvn_Notify_Channel_Interface {

	/**
	 * Unique identifier of the channel (e.g. 'slack', 'telegram').
	 *
	 * @return string
	 */
	public function get_id();

	/**
	 * Display name of the channel.
	 *
	 * @return string
	 */
	public function get_label();

	/**
	 * Default config values for this channel.
	 *
	 * @return array
	 */
	public function get_defaults();

	/**
	 * Is the channel enabled and configured enough to send?
	 *
	 * @param array $cfg The channel's own config.
	 * @return bool
	 */
	public function is_ready( $cfg );

	/**
	 * Print the channel's config <tr> rows in the admin page.
	 *
	 * @param array  $cfg         Current config of the channel.
	 * @param string $name_prefix Name prefix for inputs, e.g. option[channels][slack].
	 */
	public function render_fields( $cfg, $name_prefix );

	/**
	 * Sanitize the channel's own config data before saving.
	 *
	 * @param array $input
	 * @return array
	 */
	public function sanitize( $input );

	/**
	 * Send a message.
	 *
	 * @param string $text  The rendered content (supports light markdown).
	 * @param array  $event Event context (hook, user, summary, url...).
	 * @param array  $cfg   The channel's own config.
	 * @return array array( 'ok' => bool, 'error' => string )
	 */
	public function send( $text, $event, $cfg );
}
