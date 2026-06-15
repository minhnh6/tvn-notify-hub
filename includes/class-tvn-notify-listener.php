<?php
/**
 * Listens to the selected hooks and sends notifications to all enabled channels.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tvn_Notify_Listener {

	/**
	 * Anti-recursion flag: prevents sending a notification from re-triggering the hook being processed.
	 *
	 * @var bool
	 */
	protected static $busy = false;

	/**
	 * Attach the listener to all active hooks.
	 */
	public static function init() {
		$opts = Tvn_Notify_Settings::get();
		if ( empty( $opts['enabled'] ) ) {
			return;
		}

		foreach ( Tvn_Notify_Settings::get_active_hooks() as $hook => $accepted_args ) {
			add_action( $hook, array( __CLASS__, 'handle' ), 10, max( 1, (int) $accepted_args ) );
		}
	}

	/**
	 * Shared callback for every hook. Uses current_action() to know which hook is running.
	 *
	 * Returns the first argument unchanged to stay safe even when the user accidentally
	 * attaches it to a filter (do_action vs apply_filters).
	 *
	 * @return mixed
	 */
	public static function handle() {
		$args        = func_get_args();
		$passthrough = isset( $args[0] ) ? $args[0] : null;

		if ( self::$busy ) {
			return $passthrough;
		}

		$hook = current_action();
		if ( empty( $hook ) ) {
			return $passthrough;
		}

		self::$busy = true;
		try {
			self::dispatch( $hook, $args );
		} catch ( Exception $e ) {
			// Don't let a notification error break the site's main flow.
			unset( $e );
		}
		self::$busy = false;

		return $passthrough;
	}

	/**
	 * Build the context, render the message and send it to the channels.
	 *
	 * @param string $hook
	 * @param array  $args
	 */
	protected static function dispatch( $hook, $args ) {
		$opts = Tvn_Notify_Settings::get();

		// Reduce noise: transition_post_status fires on every save; skip when the status is unchanged.
		if ( 'transition_post_status' === $hook && isset( $args[0], $args[1] ) && $args[0] === $args[1] ) {
			return;
		}

		$event = self::build_event( $hook, $args, $opts );

		// Only notify for actions by logged-in users (if the Admin enabled this option).
		if ( ! empty( $opts['only_logged_in'] ) && empty( $event['user_id'] ) ) {
			return;
		}

		/**
		 * Allow modifying / blocking an event before sending.
		 * Return false to skip sending.
		 *
		 * @param array  $event
		 * @param string $hook
		 * @param array  $args
		 */
		$event = apply_filters( 'tvn_notify_hub_event', $event, $hook, $args );
		if ( false === $event || empty( $event ) ) {
			return;
		}

		$text = self::render_template( $opts['message_template'], $event );

		foreach ( Tvn_Notify_Channels::all() as $id => $channel ) {
			$cfg = isset( $opts['channels'][ $id ] ) ? $opts['channels'][ $id ] : array();
			if ( ! $channel->is_ready( $cfg ) ) {
				continue;
			}

			$result = $channel->send( $text, $event, $cfg );

			if ( ! empty( $opts['log_enabled'] ) ) {
				Tvn_Notify_Logger::add(
					array(
						'hook'    => $hook,
						'channel' => $id,
						'user_id' => $event['user_id'],
						'status'  => ! empty( $result['ok'] ) ? 'success' : 'error',
						'message' => $text,
						'error'   => isset( $result['error'] ) ? $result['error'] : '',
					)
				);
			}
		}
	}

	/**
	 * Build the event context array from the hook + arguments.
	 *
	 * @param string $hook
	 * @param array  $args
	 * @param array  $opts
	 * @return array
	 */
	protected static function build_event( $hook, $args, $opts ) {
		$user = self::resolve_user( $hook, $args );

		$event = array(
			'hook'       => $hook,
			'action'     => Tvn_Notify_Settings::hook_label( $hook ),
			'user_id'    => $user ? $user->ID : 0,
			'user'       => $user ? $user->display_name : __( 'Guest', 'tvn-notify-hub' ),
			'user_login' => $user ? $user->user_login : '-',
			'user_email' => $user ? $user->user_email : '-',
			'role'       => $user ? implode( ', ', (array) $user->roles ) : '-',
			'ip'         => self::get_ip(),
			'time'       => wp_date( 'Y-m-d H:i:s' ),
			'site'       => get_bloginfo( 'name' ) . ' (' . home_url() . ')',
			'url'        => self::current_url(),
			'summary'    => self::build_summary( $hook, $args ),
		);

		if ( ! empty( $opts['include_args'] ) ) {
			$event['summary'] = trim( $event['summary'] . "\n" . self::dump_args( $args, $hook ) );
		}

		return $event;
	}

	/**
	 * Try to determine the user related to the event (preferring the hook's arguments).
	 *
	 * @param string $hook
	 * @param array  $args
	 * @return WP_User|null
	 */
	protected static function resolve_user( $hook, $args ) {
		$candidate = isset( $args[0] ) ? $args[0] : null;

		switch ( $hook ) {
			case 'wp_login':
				// ($user_login, $user)
				if ( isset( $args[1] ) && $args[1] instanceof WP_User ) {
					return $args[1];
				}
				break;

			case 'wp_logout':
			case 'user_register':
			case 'delete_user':
			case 'profile_update':
				if ( is_numeric( $candidate ) ) {
					$u = get_user_by( 'id', (int) $candidate );
					if ( $u ) {
						return $u;
					}
				}
				break;

			case 'password_reset':
				if ( $candidate instanceof WP_User ) {
					return $candidate;
				}
				break;
		}

		if ( $candidate instanceof WP_User ) {
			return $candidate;
		}

		$current = wp_get_current_user();
		return ( $current && $current->ID ) ? $current : null;
	}

	/**
	 * A short, human-readable description for some common hooks.
	 *
	 * @param string $hook
	 * @param array  $args
	 * @return string
	 */
	protected static function build_summary( $hook, $args ) {
		switch ( $hook ) {
			case 'transition_post_status':
				// ($new_status, $old_status, $post)
				$new  = isset( $args[0] ) ? $args[0] : '';
				$old  = isset( $args[1] ) ? $args[1] : '';
				$post = isset( $args[2] ) ? $args[2] : null;
				if ( $post instanceof WP_Post ) {
					if ( $new === $old ) {
						return '';
					}
					return sprintf(
						/* translators: 1: title, 2: old status, 3: new status */
						__( '“%1$s”: %2$s → %3$s', 'tvn-notify-hub' ),
						$post->post_title,
						$old,
						$new
					) . ' — ' . get_permalink( $post );
				}
				break;

			case 'wp_trash_post':
			case 'before_delete_post':
				$post = isset( $args[0] ) ? get_post( (int) $args[0] ) : null;
				if ( $post ) {
					return sprintf( '%s (#%d, %s)', $post->post_title, $post->ID, $post->post_type );
				}
				break;

			case 'comment_post':
				// ($comment_id, $approved, $commentdata)
				$comment = isset( $args[0] ) ? get_comment( (int) $args[0] ) : null;
				if ( $comment ) {
					$post = get_post( $comment->comment_post_ID );
					return sprintf(
						/* translators: 1: author name, 2: post title */
						__( '%1$s commented on “%2$s”', 'tvn-notify-hub' ),
						$comment->comment_author,
						$post ? $post->post_title : '#' . $comment->comment_post_ID
					);
				}
				break;

			case 'add_attachment':
				$post = isset( $args[0] ) ? get_post( (int) $args[0] ) : null;
				if ( $post ) {
					return $post->post_title . ' — ' . wp_get_attachment_url( $post->ID );
				}
				break;

			case 'activated_plugin':
			case 'deactivated_plugin':
				return isset( $args[0] ) ? (string) $args[0] : '';

			case 'switch_theme':
				return isset( $args[0] ) ? (string) $args[0] : '';

			case 'wp_login_failed':
				if ( ! isset( $args[0] ) ) {
					return '';
				}
				/* translators: %s: attempted username */
				return sprintf( __( 'Attempted username: %s', 'tvn-notify-hub' ), (string) $args[0] );
		}

		return '';
	}

	/**
	 * SENSITIVE argument positions that must be masked (e.g. hooks passing a plaintext password).
	 *
	 * WordPress core: do_action( 'password_reset', $user, $new_pass ) and
	 * do_action( 'after_password_reset', $user, $new_pass ) — argument [1] is
	 * the new password in plaintext, which must NEVER be sent/logged.
	 *
	 * @param string $hook
	 * @return int[] list of masked indexes
	 */
	protected static function sensitive_arg_indexes( $hook ) {
		$map = array(
			'password_reset'       => array( 1 ),
			'after_password_reset' => array( 1 ),
		);

		/**
		 * Allow declaring additional sensitive argument positions for any hook.
		 *
		 * @param array  $map  hook => array of masked indexes.
		 * @param string $hook the current hook.
		 */
		$map = apply_filters( 'tvn_notify_hub_sensitive_args', $map, $hook );

		return isset( $map[ $hook ] ) ? array_map( 'intval', (array) $map[ $hook ] ) : array();
	}

	/**
	 * Compact representation of the hook's arguments (length-limited), masking sensitive ones.
	 *
	 * @param array  $args
	 * @param string $hook
	 * @return string
	 */
	protected static function dump_args( $args, $hook = '' ) {
		$redact = self::sensitive_arg_indexes( $hook );
		$parts  = array();
		foreach ( $args as $i => $arg ) {
			if ( in_array( (int) $i, $redact, true ) ) {
				$parts[] = '[' . $i . '] ***';
				continue;
			}
			if ( is_scalar( $arg ) ) {
				$val = (string) $arg;
			} elseif ( $arg instanceof WP_Post ) {
				$val = 'WP_Post#' . $arg->ID;
			} elseif ( $arg instanceof WP_User ) {
				$val = 'WP_User#' . $arg->ID;
			} elseif ( is_array( $arg ) ) {
				$val = 'array(' . count( $arg ) . ')';
			} elseif ( is_object( $arg ) ) {
				$val = get_class( $arg );
			} else {
				$val = gettype( $arg );
			}
			$parts[] = '[' . $i . '] ' . self::truncate( $val, 120 );
		}
		return $parts ? '`' . implode( ' | ', $parts ) . '`' : '';
	}

	/**
	 * Replace the placeholders in the message template.
	 *
	 * @param string $template
	 * @param array  $event
	 * @return string
	 */
	protected static function render_template( $template, $event ) {
		$map = array(
			'{action}'     => $event['action'],
			'{hook}'       => $event['hook'],
			'{user}'       => $event['user'],
			'{user_login}' => $event['user_login'],
			'{user_email}' => $event['user_email'],
			'{role}'       => $event['role'],
			'{ip}'         => $event['ip'],
			'{time}'       => $event['time'],
			'{site}'       => $event['site'],
			'{url}'        => $event['url'],
			'{summary}'    => '' !== $event['summary'] ? $event['summary'] : '-',
		);

		return strtr( $template, $map );
	}

	/**
	 * Get the user's IP (taking common proxies into account).
	 *
	 * @return string
	 */
	protected static function get_ip() {
		// Default to REMOTE_ADDR — do NOT trust proxy headers (X-Forwarded-For,
		// CF-Connecting-IP...) because they are sent by the client and can be spoofed.
		$ip = isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
			: '';

		/**
		 * A site behind a proxy/CDN can determine the real IP itself via this filter.
		 * Only trust proxy headers when you control that proxy layer.
		 *
		 * @param string $ip IP taken from REMOTE_ADDR.
		 */
		$ip = apply_filters( 'tvn_notify_hub_client_ip', $ip );
		$ip = is_string( $ip ) ? trim( $ip ) : '';

		return ( '' !== $ip && filter_var( $ip, FILTER_VALIDATE_IP ) ) ? $ip : '-';
	}

	/**
	 * Current URL of the request.
	 *
	 * @return string
	 */
	protected static function current_url() {
		if ( is_admin() ) {
			return admin_url();
		}
		$path = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		return $path ? home_url( $path ) : home_url();
	}

	/**
	 * Truncate a string to keep it short.
	 *
	 * @param string $str
	 * @param int    $len
	 * @return string
	 */
	protected static function truncate( $str, $len ) {
		$str = trim( preg_replace( '/\s+/', ' ', (string) $str ) );
		if ( strlen( $str ) <= $len ) {
			return $str;
		}
		return substr( $str, 0, $len - 1 ) . '…';
	}

	/**
	 * Send a test message to all ready channels (used by the "Send test" button).
	 *
	 * @return array channel_id => result
	 */
	public static function send_test() {
		$opts    = Tvn_Notify_Settings::get();
		$user    = wp_get_current_user();
		$results = array();

		$event = array(
			'hook'       => 'tvn_notify_hub_test',
			'action'     => __( 'Test message', 'tvn-notify-hub' ),
			'user_id'    => $user ? $user->ID : 0,
			'user'       => $user ? $user->display_name : '-',
			'user_login' => $user ? $user->user_login : '-',
			'user_email' => $user ? $user->user_email : '-',
			'role'       => $user ? implode( ', ', (array) $user->roles ) : '-',
			'ip'         => self::get_ip(),
			'time'       => wp_date( 'Y-m-d H:i:s' ),
			'site'       => get_bloginfo( 'name' ) . ' (' . home_url() . ')',
			'url'        => admin_url(),
			'summary'    => __( 'This is a test message for the TVN Notify Hub configuration.', 'tvn-notify-hub' ),
		);

		$text = self::render_template( $opts['message_template'], $event );

		foreach ( Tvn_Notify_Channels::all() as $id => $channel ) {
			$cfg = isset( $opts['channels'][ $id ] ) ? $opts['channels'][ $id ] : array();
			if ( ! $channel->is_ready( $cfg ) ) {
				continue;
			}
			$results[ $id ] = $channel->send( $text, $event, $cfg );
		}

		return $results;
	}
}
