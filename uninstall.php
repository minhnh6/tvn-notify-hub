<?php
/**
 * Cleanup on plugin uninstall: delete options + log table.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'tvn_notify_hub_settings' );
delete_option( 'tvn_notify_hub_db_version' );

global $wpdb;
$tvn_notify_hub_table = $wpdb->prefix . 'tvn_notify_log';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Drop the plugin's own table on uninstall; table name is built from $wpdb->prefix (safe, no user input).
$wpdb->query( "DROP TABLE IF EXISTS {$tvn_notify_hub_table}" );
