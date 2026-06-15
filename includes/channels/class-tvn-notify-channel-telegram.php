<?php
/**
 * Telegram channel — sends via the Bot API.
 *
 * Unlike the webhook group (Slack/Discord): Telegram has no "webhook URL" to paste.
 * Instead it needs a Bot Token (from @BotFather) + Chat ID (a user id,
 * a negative numeric group id, or a channel @username). Send by POSTing to
 * https://api.telegram.org/bot<TOKEN>/sendMessage.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tvn_Notify_Channel_Telegram implements Tvn_Notify_Channel_Interface {

	const MAX_LEN = 4096; // Telegram message length limit.

	public function get_id() {
		return 'telegram';
	}

	public function get_label() {
		return __( 'Telegram', 'tvn-notify-hub' );
	}

	public function get_defaults() {
		return array(
			'enabled'   => 0,
			'bot_token' => '',
			'chat_id'   => '',
			'silent'    => 0,
		);
	}

	public function is_ready( $cfg ) {
		return ! empty( $cfg['enabled'] )
			&& $this->is_valid_token( $cfg['bot_token'] )
			&& '' !== trim( (string) $cfg['chat_id'] );
	}

	/**
	 * A Telegram bot token looks like "123456789:AA...".
	 *
	 * @param string $token
	 * @return bool
	 */
	public function is_valid_token( $token ) {
		return (bool) preg_match( '/^\d{6,}:[A-Za-z0-9_-]{30,}$/', (string) $token );
	}

	public function render_fields( $cfg, $name_prefix ) {
		?>
		<tr>
			<th scope="row"><?php esc_html_e( 'Enable Telegram', 'tvn-notify-hub' ); ?></th>
			<td>
				<label class="tvn-checkbox">
					<input type="checkbox" name="<?php echo esc_attr( $name_prefix ); ?>[enabled]" value="1"
						<?php checked( ! empty( $cfg['enabled'] ) ); ?> />
					<?php esc_html_e( 'Send notifications via Telegram', 'tvn-notify-hub' ); ?>
				</label>
			</td>
		</tr>
		<tr>
			<th scope="row"><label><?php esc_html_e( 'Bot Token', 'tvn-notify-hub' ); ?></label></th>
			<td>
				<input type="text" class="regular-text" autocomplete="off" value=""
					placeholder="<?php echo esc_attr( Tvn_Notify_Secret::placeholder( $cfg['bot_token'], '123456789:AA...' ) ); ?>"
					name="<?php echo esc_attr( $name_prefix ); ?>[bot_token]" />
				<p class="description">
					<?php esc_html_e( 'Create a bot by chatting with @BotFather on Telegram → /newbot → copy the token.', 'tvn-notify-hub' ); ?>
					<?php if ( ! empty( $cfg['bot_token'] ) ) : ?>
						<br /><em><?php esc_html_e( 'A token is already saved. Leave blank to keep it, or enter a new one to replace it.', 'tvn-notify-hub' ); ?></em>
					<?php endif; ?>
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label><?php esc_html_e( 'Chat ID', 'tvn-notify-hub' ); ?></label></th>
			<td>
				<input type="text" class="regular-text" placeholder="123456789 / -1001234567890 / @kenh_cua_ban"
					name="<?php echo esc_attr( $name_prefix ); ?>[chat_id]"
					value="<?php echo esc_attr( $cfg['chat_id'] ); ?>" />
				<p class="description">
					<?php esc_html_e( 'A user ID, a group ID (negative number), or a channel @username. Tip to get an ID: add the bot to a group then open https://api.telegram.org/bot<TOKEN>/getUpdates to see "chat":{"id":...}. For a channel, add the bot as an admin and use @username.', 'tvn-notify-hub' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Silent send', 'tvn-notify-hub' ); ?></th>
			<td>
				<label class="tvn-checkbox">
					<input type="checkbox" name="<?php echo esc_attr( $name_prefix ); ?>[silent]" value="1"
						<?php checked( ! empty( $cfg['silent'] ) ); ?> />
					<?php esc_html_e( 'Send without a notification sound (disable_notification)', 'tvn-notify-hub' ); ?>
				</label>
			</td>
		</tr>
		<?php
	}

	public function sanitize( $input ) {
		$out = $this->get_defaults();
		if ( ! is_array( $input ) ) {
			$input = array();
		}

		$out['enabled'] = empty( $input['enabled'] ) ? 0 : 1;
		$out['silent']  = empty( $input['silent'] ) ? 0 : 1;

		// Secret: empty field -> keep the previous token.
		$existing = Tvn_Notify_Settings::get_channel( $this->get_id() );
		$token    = isset( $input['bot_token'] ) ? trim( sanitize_text_field( wp_unslash( $input['bot_token'] ) ) ) : '';
		$existing_token = isset( $existing['bot_token'] ) ? $existing['bot_token'] : '';
		if ( '' === $token ) {
			$out['bot_token'] = $existing_token;
		} elseif ( $this->is_valid_token( $token ) ) {
			$out['bot_token'] = $token;
		} else {
			// Invalid format: keep the previous token, report an error.
			$out['bot_token'] = $existing_token;
			add_settings_error(
				Tvn_Notify_Settings::OPTION,
				'telegram_invalid_token',
				__( 'Telegram: Bot Token is invalid. It must look like 123456789:AA... (from @BotFather, kept the previous value).', 'tvn-notify-hub' )
			);
		}

		$out['chat_id'] = isset( $input['chat_id'] ) ? sanitize_text_field( wp_unslash( $input['chat_id'] ) ) : '';

		return $out;
	}

	public function send( $text, $event, $cfg ) {
		if ( ! $this->is_valid_token( $cfg['bot_token'] ) || '' === trim( (string) $cfg['chat_id'] ) ) {
			return array(
				'ok'    => false,
				'error' => __( 'Bot Token or Chat ID is not configured / is invalid.', 'tvn-notify-hub' ),
			);
		}

		$message = $this->prepare_text( $text );

		// Attempt 1: use Markdown (legacy) to preserve *bold* formatting from the message template.
		$result = $this->call_send( $cfg, $message, 'Markdown' );

		// If Telegram fails to parse the formatting (400), resend as plain text so the notification is not lost.
		if ( ! $result['ok'] && $result['parse_error'] ) {
			$result = $this->call_send( $cfg, $message, '' );
		}

		return array(
			'ok'    => $result['ok'],
			'error' => $result['error'],
		);
	}

	/**
	 * Call sendMessage once.
	 *
	 * @param array  $cfg
	 * @param string $message
	 * @param string $parse_mode '' = plain text.
	 * @return array array( ok, error, parse_error )
	 */
	protected function call_send( $cfg, $message, $parse_mode ) {
		$url = 'https://api.telegram.org/bot' . rawurlencode( $cfg['bot_token'] ) . '/sendMessage';
		$url = str_replace( '%3A', ':', $url ); // Keep the ':' in the token to match the API format.

		$payload = array(
			'chat_id'                  => $cfg['chat_id'],
			'text'                     => $message,
			'disable_web_page_preview' => true,
			'disable_notification'     => ! empty( $cfg['silent'] ),
		);
		if ( '' !== $parse_mode ) {
			$payload['parse_mode'] = $parse_mode;
		}

		$response = wp_remote_post(
			$url,
			array(
				'timeout'     => 8,
				'blocking'    => true,
				'headers'     => array( 'Content-Type' => 'application/json' ),
				'body'        => wp_json_encode( $payload ),
				'data_format' => 'body',
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'ok'          => false,
				'error'       => $response->get_error_message(),
				'parse_error' => false,
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( 200 === $code && is_array( $body ) && ! empty( $body['ok'] ) ) {
			return array(
				'ok'          => true,
				'error'       => '',
				'parse_error' => false,
			);
		}

		$desc        = is_array( $body ) && isset( $body['description'] ) ? (string) $body['description'] : '';
		$parse_error = ( 400 === $code && false !== stripos( $desc, 'parse' ) );

		return array(
			'ok'          => false,
			/* translators: 1: HTTP code, 2: error description from Telegram */
			'error'       => sprintf( __( 'Telegram returned an error (HTTP %1$d): %2$s', 'tvn-notify-hub' ), $code, $desc ),
			'parse_error' => $parse_error,
		);
	}

	/**
	 * Normalize the content for Telegram: convert a few common emoji shortcodes & truncate the length.
	 *
	 * @param string $text
	 * @return string
	 */
	protected function prepare_text( $text ) {
		// Telegram does not understand shortcodes like :bell: → convert the commonly used ones to unicode emoji.
		$emoji = array(
			':bell:'              => '🔔',
			':zap:'               => '⚡',
			':white_check_mark:'  => '✅',
			':x:'                 => '❌',
			':warning:'           => '⚠️',
			':rocket:'            => '🚀',
			':tada:'              => '🎉',
			':bust_in_silhouette:' => '👤',
		);
		$text = strtr( (string) $text, $emoji );

		if ( function_exists( 'mb_strlen' ) && mb_strlen( $text ) > self::MAX_LEN ) {
			$text = mb_substr( $text, 0, self::MAX_LEN - 1 ) . '…';
		} elseif ( strlen( $text ) > self::MAX_LEN ) {
			$text = substr( $text, 0, self::MAX_LEN - 1 ) . '…';
		}

		return $text;
	}
}
