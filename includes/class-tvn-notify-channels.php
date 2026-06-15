<?php
/**
 * Registry of notification channels.
 *
 * The core only knows about the interface; the actual channel list is retrieved
 * via the 'tvn_notify_hub_channels' filter, so it is fully extensible from outside.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tvn_Notify_Channels {

	/**
	 * @var Tvn_Notify_Channel_Interface[]|null id => instance
	 */
	protected static $channels = null;

	/**
	 * Get all registered channels.
	 *
	 * @return Tvn_Notify_Channel_Interface[] id => instance
	 */
	public static function all() {
		if ( null !== self::$channels ) {
			return self::$channels;
		}

		$defaults = array(
			new Tvn_Notify_Channel_Slack(),
			new Tvn_Notify_Channel_Discord(),
			new Tvn_Notify_Channel_Telegram(),
			new Tvn_Notify_Channel_Email(),
			// Future: new Tvn_Notify_Channel_Whatsapp(), new Tvn_Notify_Channel_Mastodon()...
		);

		/**
		 * Allow other plugins/code to add new channels.
		 *
		 * @param Tvn_Notify_Channel_Interface[] $defaults
		 */
		$list = apply_filters( 'tvn_notify_hub_channels', $defaults );

		self::$channels = array();
		foreach ( $list as $channel ) {
			if ( $channel instanceof Tvn_Notify_Channel_Interface ) {
				self::$channels[ $channel->get_id() ] = $channel;
			}
		}

		return self::$channels;
	}

	/**
	 * Get a single channel by id.
	 *
	 * @param string $id
	 * @return Tvn_Notify_Channel_Interface|null
	 */
	public static function get( $id ) {
		$all = self::all();
		return isset( $all[ $id ] ) ? $all[ $id ] : null;
	}
}
