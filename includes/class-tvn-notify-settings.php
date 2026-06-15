<?php
/**
 * Manages the plugin configuration (core settings + per-channel settings).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tvn_Notify_Settings {

	const OPTION = 'tvn_notify_hub_settings';
	const GROUP  = 'tvn_notify_hub_group';

	/**
	 * Default values for the core settings + merged defaults from each channel.
	 *
	 * @return array
	 */
	public static function defaults() {
		$core = array(
			'enabled'          => 1,
			'hooks'            => array( 'wp_login', 'user_register', 'transition_post_status' ),
			'custom_hooks'     => '',
			'message_template' => self::default_template(),
			'include_args'     => 1,
			'only_logged_in'   => 0,
			'log_enabled'      => 1,
			'channels'         => array(),
		);

		foreach ( Tvn_Notify_Channels::all() as $id => $channel ) {
			$core['channels'][ $id ] = $channel->get_defaults();
		}

		return $core;
	}

	/**
	 * Default message template.
	 *
	 * @return string
	 */
	public static function default_template() {
		return ":bell: *{action}*\n" .
			"• User: *{user}* ({user_login}) – {role}\n" .
			"• Time: {time}\n" .
			"• IP: {ip}\n" .
			"• Details: {summary}\n" .
			"• Website: {site}";
	}

	/**
	 * Get the full configuration (merged with defaults, including per-channel defaults).
	 *
	 * @return array
	 */
	public static function get() {
		$saved = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		$opts = wp_parse_args( $saved, self::defaults() );

		// Ensure every channel has all default fields (including channels installed later).
		if ( ! is_array( $opts['channels'] ) ) {
			$opts['channels'] = array();
		}
		foreach ( Tvn_Notify_Channels::all() as $id => $channel ) {
			$existing               = isset( $opts['channels'][ $id ] ) && is_array( $opts['channels'][ $id ] )
				? $opts['channels'][ $id ]
				: array();
			$opts['channels'][ $id ] = wp_parse_args( $existing, $channel->get_defaults() );
		}

		return $opts;
	}

	/**
	 * Get the configuration for a single channel.
	 *
	 * @param string $id
	 * @return array
	 */
	public static function get_channel( $id ) {
		$opts = self::get();
		return isset( $opts['channels'][ $id ] ) ? $opts['channels'][ $id ] : array();
	}

	/**
	 * List of available actions/hooks for the Admin to tick (grouped).
	 *
	 * @return array group => array( hook => array( label, args ) )
	 */
	public static function preset_hooks() {
		$presets = array(
			__( 'Users', 'tvn-notify-hub' ) => array(
				'wp_login'        => array(
					'label' => __( 'Successful login', 'tvn-notify-hub' ),
					'args'  => 2,
				),
				'wp_logout'       => array(
					'label' => __( 'Logout', 'tvn-notify-hub' ),
					'args'  => 1,
				),
				'wp_login_failed' => array(
					'label' => __( 'Failed login', 'tvn-notify-hub' ),
					'args'  => 1,
				),
				'user_register'   => array(
					'label' => __( 'New user registration', 'tvn-notify-hub' ),
					'args'  => 1,
				),
				'profile_update'  => array(
					'label' => __( 'Profile update', 'tvn-notify-hub' ),
					'args'  => 2,
				),
				'delete_user'     => array(
					'label' => __( 'User deleted', 'tvn-notify-hub' ),
					'args'  => 1,
				),
				'password_reset'  => array(
					'label' => __( 'Password reset', 'tvn-notify-hub' ),
					'args'  => 2,
				),
			),
			__( 'Content', 'tvn-notify-hub' )   => array(
				'transition_post_status' => array(
					'label' => __( 'Post status changed (publish / draft / pending...)', 'tvn-notify-hub' ),
					'args'  => 3,
				),
				'wp_trash_post'          => array(
					'label' => __( 'Post moved to trash', 'tvn-notify-hub' ),
					'args'  => 1,
				),
				'before_delete_post'     => array(
					'label' => __( 'Post permanently deleted', 'tvn-notify-hub' ),
					'args'  => 1,
				),
				'comment_post'           => array(
					'label' => __( 'New comment', 'tvn-notify-hub' ),
					'args'  => 3,
				),
				'add_attachment'         => array(
					'label' => __( 'Media uploaded', 'tvn-notify-hub' ),
					'args'  => 1,
				),
			),
			__( 'System', 'tvn-notify-hub' )    => array(
				'activated_plugin'   => array(
					'label' => __( 'Plugin activated', 'tvn-notify-hub' ),
					'args'  => 2,
				),
				'deactivated_plugin' => array(
					'label' => __( 'Plugin deactivated', 'tvn-notify-hub' ),
					'args'  => 2,
				),
				'switch_theme'       => array(
					'label' => __( 'Theme switched', 'tvn-notify-hub' ),
					'args'  => 3,
				),
			),
		);

		/**
		 * Allow extending the list of available actions.
		 */
		return apply_filters( 'tvn_notify_hub_preset_hooks', $presets );
	}

	/**
	 * Flat map of hook => number of args, combining selected presets + custom hooks.
	 *
	 * @return array hook_name => accepted_args
	 */
	public static function get_active_hooks() {
		$opts   = self::get();
		$active = array();

		$selected = is_array( $opts['hooks'] ) ? $opts['hooks'] : array();
		foreach ( self::preset_hooks() as $group ) {
			foreach ( $group as $hook => $info ) {
				if ( in_array( $hook, $selected, true ) ) {
					$active[ $hook ] = isset( $info['args'] ) ? (int) $info['args'] : 1;
				}
			}
		}

		foreach ( self::parse_custom_hooks( $opts['custom_hooks'] ) as $hook => $args ) {
			$active[ $hook ] = $args;
		}

		return $active;
	}

	/**
	 * Descriptive label for a hook (preferring the preset, falling back to the hook name itself).
	 *
	 * @param string $hook
	 * @return string
	 */
	public static function hook_label( $hook ) {
		foreach ( self::preset_hooks() as $group ) {
			if ( isset( $group[ $hook ]['label'] ) ) {
				return $group[ $hook ]['label'];
			}
		}
		return $hook;
	}

	/**
	 * Parse the custom hooks textarea -> map of hook => args.
	 *
	 * Each line: "hook_name" or "hook_name|num_args". Lines starting with # are ignored.
	 *
	 * @param string $raw
	 * @return array
	 */
	public static function parse_custom_hooks( $raw ) {
		$out = array();
		if ( empty( $raw ) ) {
			return $out;
		}

		$lines = preg_split( '/[\r\n]+/', (string) $raw );
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line || 0 === strpos( $line, '#' ) ) {
				continue;
			}

			$args = 1;
			if ( false !== strpos( $line, '|' ) ) {
				list( $name, $maybe_args ) = array_map( 'trim', explode( '|', $line, 2 ) );
				$line                      = $name;
				$args                      = max( 1, (int) $maybe_args );
			}

			if ( preg_match( '/^[a-zA-Z0-9_\-.:\/]+$/', $line ) ) {
				$out[ $line ] = $args;
			}
		}

		return $out;
	}

	/**
	 * Register the Settings API.
	 */
	public static function register() {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
			)
		);
	}

	/**
	 * Sanitize the data before saving — core settings + delegating per-channel parts to each channel.
	 *
	 * @param array $input
	 * @return array
	 */
	public static function sanitize( $input ) {
		$defaults = self::defaults();
		$out      = self::get();

		if ( ! is_array( $input ) ) {
			$input = array();
		}

		$out['enabled']        = empty( $input['enabled'] ) ? 0 : 1;
		$out['include_args']   = empty( $input['include_args'] ) ? 0 : 1;
		$out['only_logged_in'] = empty( $input['only_logged_in'] ) ? 0 : 1;
		$out['log_enabled']    = empty( $input['log_enabled'] ) ? 0 : 1;

		// Preset hooks.
		$out['hooks'] = array();
		if ( ! empty( $input['hooks'] ) && is_array( $input['hooks'] ) ) {
			$valid = array();
			foreach ( self::preset_hooks() as $group ) {
				$valid = array_merge( $valid, array_keys( $group ) );
			}
			foreach ( $input['hooks'] as $hook ) {
				$hook = sanitize_text_field( wp_unslash( $hook ) );
				if ( in_array( $hook, $valid, true ) ) {
					$out['hooks'][] = $hook;
				}
			}
		}

		$out['custom_hooks'] = isset( $input['custom_hooks'] )
			? sanitize_textarea_field( wp_unslash( $input['custom_hooks'] ) )
			: '';

		if ( isset( $input['message_template'] ) ) {
			$tpl                     = sanitize_textarea_field( wp_unslash( $input['message_template'] ) );
			$out['message_template'] = '' !== trim( $tpl ) ? $tpl : $defaults['message_template'];
		}

		// Each channel sanitizes its own part.
		$out['channels'] = array();
		foreach ( Tvn_Notify_Channels::all() as $id => $channel ) {
			$channel_input           = isset( $input['channels'][ $id ] ) ? $input['channels'][ $id ] : array();
			$out['channels'][ $id ] = $channel->sanitize( $channel_input );
		}

		return $out;
	}
}
