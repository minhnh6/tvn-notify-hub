<?php
/**
 * Plugin Name:       TVN Notify Hub
 * Plugin URI: https://github.com/minhnh6/tvn-notify-hub
 * Author URI: https://github.com/minhnh6
 * Description:        Send notifications (pings) to chat apps whenever a user performs an action/hook chosen by the admin. Multi-channel architecture: supports Slack, Discord, Telegram, Email. Admins can pick built-in actions or add any custom hook — including hooks registered by other plugins.
 * Version:           1.0.0
 * Author:            minhnh6
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       tvn-notify-hub
 * Domain Path:       /languages
 * Requires at least: 5.3
 * Requires PHP:      7.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Block direct access.
}

define( 'TVN_NOTIFY_HUB_VERSION', '1.0.0' );
define( 'TVN_NOTIFY_HUB_FILE', __FILE__ );
define( 'TVN_NOTIFY_HUB_DIR', plugin_dir_path( __FILE__ ) );
define( 'TVN_NOTIFY_HUB_URL', plugin_dir_url( __FILE__ ) );
define( 'TVN_NOTIFY_HUB_BASENAME', plugin_basename( __FILE__ ) );

require_once TVN_NOTIFY_HUB_DIR . 'includes/class-tvn-notify-secret.php';
require_once TVN_NOTIFY_HUB_DIR . 'includes/channels/interface-tvn-notify-channel.php';
require_once TVN_NOTIFY_HUB_DIR . 'includes/channels/class-tvn-notify-channel-slack.php';
require_once TVN_NOTIFY_HUB_DIR . 'includes/channels/class-tvn-notify-channel-discord.php';
require_once TVN_NOTIFY_HUB_DIR . 'includes/channels/class-tvn-notify-channel-telegram.php';
require_once TVN_NOTIFY_HUB_DIR . 'includes/channels/class-tvn-notify-channel-email.php';
require_once TVN_NOTIFY_HUB_DIR . 'includes/class-tvn-notify-channels.php';
require_once TVN_NOTIFY_HUB_DIR . 'includes/class-tvn-notify-settings.php';
require_once TVN_NOTIFY_HUB_DIR . 'includes/class-tvn-notify-logger.php';
require_once TVN_NOTIFY_HUB_DIR . 'includes/class-tvn-notify-listener.php';
require_once TVN_NOTIFY_HUB_DIR . 'includes/class-tvn-notify-admin.php';
require_once TVN_NOTIFY_HUB_DIR . 'includes/class-tvn-notify-plugin.php';

register_activation_hook( __FILE__, array( 'Tvn_Notify_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Tvn_Notify_Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'Tvn_Notify_Plugin', 'instance' ) );
