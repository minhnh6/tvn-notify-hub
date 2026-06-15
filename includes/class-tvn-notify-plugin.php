<?php
/**
 * Initialize the plugin (singleton) + handle activation / deactivation.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tvn_Notify_Plugin {

	/**
	 * @var Tvn_Notify_Plugin|null
	 */
	protected static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Translations are loaded automatically by WordPress (4.6+) from the
		// plugin's "Domain Path" (/languages) and from WordPress.org language
		// packs — no manual load_plugin_textdomain() call is needed.

		// Register the hook listeners on `init` (not earlier) so any translation
		// lookups (preset labels, channel defaults) happen at/after init — this
		// avoids the WP 6.7+ "translation loading triggered too early" notice.
		// All actions we watch (wp_login, transition_post_status, comment_post...)
		// fire after init, so this timing is safe.
		add_action( 'init', array( 'Tvn_Notify_Listener', 'init' ) );

		if ( is_admin() ) {
			Tvn_Notify_Logger::maybe_upgrade();
			Tvn_Notify_Admin::init();
		}
	}

	/**
	 * On activation: create the log table + save the default settings.
	 */
	public static function activate() {
		Tvn_Notify_Logger::create_table();
		add_option( Tvn_Notify_Settings::OPTION, Tvn_Notify_Settings::get() );
	}

	/**
	 * On deactivation: do not delete data (leave that to uninstall.php).
	 */
	public static function deactivate() {
		// no-op
	}
}
