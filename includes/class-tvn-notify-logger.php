<?php
/**
 * Logs notification send attempts (for the Admin to monitor / debug).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tvn_Notify_Logger {

	const DB_VERSION_OPTION = 'tvn_notify_hub_db_version';
	const DB_VERSION        = '1.0.0';

	/**
	 * Log table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'tvn_notify_log';
	}

	/**
	 * Create the log table.
	 */
	public static function create_table() {
		global $wpdb;

		$table           = self::table();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at DATETIME NOT NULL,
			hook VARCHAR(191) NOT NULL DEFAULT '',
			channel VARCHAR(60) NOT NULL DEFAULT '',
			user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			status VARCHAR(20) NOT NULL DEFAULT '',
			message TEXT NULL,
			error TEXT NULL,
			PRIMARY KEY  (id),
			KEY hook (hook),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	/**
	 * Create / upgrade the table when needed.
	 */
	public static function maybe_upgrade() {
		if ( get_option( self::DB_VERSION_OPTION ) !== self::DB_VERSION ) {
			self::create_table();
		}
	}

	/**
	 * Write a single log row.
	 *
	 * @param array $row hook, channel, user_id, status, message, error
	 */
	public static function add( $row ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The plugin's own log table; writing a single row, not suitable for caching.
		$wpdb->insert(
			self::table(),
			array(
				'created_at' => current_time( 'mysql' ),
				'hook'       => isset( $row['hook'] ) ? substr( (string) $row['hook'], 0, 191 ) : '',
				'channel'    => isset( $row['channel'] ) ? substr( (string) $row['channel'], 0, 60 ) : '',
				'user_id'    => isset( $row['user_id'] ) ? (int) $row['user_id'] : 0,
				'status'     => isset( $row['status'] ) ? substr( (string) $row['status'], 0, 20 ) : '',
				'message'    => isset( $row['message'] ) ? (string) $row['message'] : '',
				'error'      => isset( $row['error'] ) ? (string) $row['error'] : '',
			),
			array( '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);
	}

	/**
	 * Get the most recent log rows.
	 *
	 * @param int $limit
	 * @return array
	 */
	public static function get_recent( $limit = 50 ) {
		global $wpdb;
		$limit = max( 1, (int) $limit );
		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name built from $wpdb->prefix (safe, no user input); LIMIT is prepared; admin page views logs in real time so no caching.
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit ) );
	}

	/**
	 * Clear all logs.
	 */
	public static function clear() {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name built from $wpdb->prefix (safe); TRUNCATE does not accept prepared parameters, not related to caching.
		$wpdb->query( "TRUNCATE TABLE {$table}" );
	}
}
