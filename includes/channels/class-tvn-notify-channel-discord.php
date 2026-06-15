<?php
/**
 * Discord channel — sends via Incoming Webhook.
 *
 * Unlike Slack: the payload uses "content" + "avatar_url", and Discord returns
 * HTTP 204 (No Content) on a successful send.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tvn_Notify_Channel_Discord implements Tvn_Notify_Channel_Interface {

	const MAX_LEN = 2000; // Discord content length limit.

	public function get_id() {
		return 'discord';
	}

	public function get_label() {
		return __( 'Discord', 'tvn-notify-hub' );
	}

	public function get_defaults() {
		return array(
			'enabled'     => 0,
			'webhook_url' => '',
			'username'    => 'WordPress',
			'avatar_url'  => '',
		);
	}

	public function is_ready( $cfg ) {
		return ! empty( $cfg['enabled'] ) && $this->is_valid_webhook( $cfg['webhook_url'] );
	}

	/**
	 * A valid webhook must be on discord.com / discordapp.com and on the correct /api/webhooks/ path.
	 *
	 * @param string $url
	 * @return bool
	 */
	public function is_valid_webhook( $url ) {
		if ( empty( $url ) ) {
			return false;
		}
		$host  = wp_parse_url( $url, PHP_URL_HOST );
		$path  = (string) wp_parse_url( $url, PHP_URL_PATH );
		$hosts = array( 'discord.com', 'discordapp.com', 'ptb.discord.com', 'canary.discord.com' );
		return in_array( $host, $hosts, true ) && false !== strpos( $path, '/api/webhooks/' );
	}

	public function render_fields( $cfg, $name_prefix ) {
		?>
		<tr>
			<th scope="row"><?php esc_html_e( 'Enable Discord', 'tvn-notify-hub' ); ?></th>
			<td>
				<label class="tvn-checkbox">
					<input type="checkbox" name="<?php echo esc_attr( $name_prefix ); ?>[enabled]" value="1"
						<?php checked( ! empty( $cfg['enabled'] ) ); ?> />
					<?php esc_html_e( 'Send notifications via Discord', 'tvn-notify-hub' ); ?>
				</label>
			</td>
		</tr>
		<tr>
			<th scope="row"><label><?php esc_html_e( 'Webhook URL', 'tvn-notify-hub' ); ?></label></th>
			<td>
				<input type="url" class="regular-text" autocomplete="off" value=""
					placeholder="<?php echo esc_attr( Tvn_Notify_Secret::placeholder( $cfg['webhook_url'], 'https://discord.com/api/webhooks/000/XXXX' ) ); ?>"
					name="<?php echo esc_attr( $name_prefix ); ?>[webhook_url]" />
				<p class="description">
					<?php esc_html_e( 'Webhook URL of the Discord channel. Create it at: Server Settings → Integrations → Webhooks → New Webhook → Copy Webhook URL.', 'tvn-notify-hub' ); ?>
					<?php if ( ! empty( $cfg['webhook_url'] ) ) : ?>
						<br /><em><?php esc_html_e( 'A webhook is already saved. Leave blank to keep it, or enter a new one to replace it.', 'tvn-notify-hub' ); ?></em>
					<?php endif; ?>
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label><?php esc_html_e( 'Bot Name', 'tvn-notify-hub' ); ?></label></th>
			<td>
				<input type="text" class="regular-text"
					name="<?php echo esc_attr( $name_prefix ); ?>[username]"
					value="<?php echo esc_attr( $cfg['username'] ); ?>" />
				<p class="description"><?php esc_html_e( 'Display name of the bot in Discord (overrides the webhook default name).', 'tvn-notify-hub' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label><?php esc_html_e( 'Bot Custom Icon', 'tvn-notify-hub' ); ?></label></th>
			<td>
				<div class="tvn-media">
					<input type="url" class="regular-text tvn-media-url"
						name="<?php echo esc_attr( $name_prefix ); ?>[avatar_url]"
						value="<?php echo esc_attr( $cfg['avatar_url'] ); ?>" />
					<button type="button" class="button tvn-media-pick"><?php esc_html_e( 'Select image', 'tvn-notify-hub' ); ?></button>
					<div class="tvn-media-preview">
						<?php if ( ! empty( $cfg['avatar_url'] ) ) : ?>
							<img src="<?php echo esc_url( $cfg['avatar_url'] ); ?>" alt="" />
						<?php endif; ?>
					</div>
				</div>
				<p class="description"><?php esc_html_e( 'Avatar image for the bot (public URL). Discord does not use emoji as an icon like Slack.', 'tvn-notify-hub' ); ?></p>
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

		// Secret: empty field -> keep the previous value.
		$existing  = Tvn_Notify_Settings::get_channel( $this->get_id() );
		$submitted = isset( $input['webhook_url'] ) ? esc_url_raw( trim( wp_unslash( $input['webhook_url'] ) ) ) : '';
		$existing_url = isset( $existing['webhook_url'] ) ? $existing['webhook_url'] : '';
		if ( '' === $submitted ) {
			$out['webhook_url'] = $existing_url;
		} elseif ( $this->is_valid_webhook( $submitted ) ) {
			$out['webhook_url'] = $submitted;
		} else {
			// Invalid format: keep the previous secret, report an error.
			$out['webhook_url'] = $existing_url;
			add_settings_error(
				Tvn_Notify_Settings::OPTION,
				'discord_invalid_webhook',
				__( 'Discord: Webhook URL is invalid. It must look like https://discord.com/api/webhooks/... (kept the previous value).', 'tvn-notify-hub' )
			);
		}

		$out['username']   = isset( $input['username'] ) ? sanitize_text_field( wp_unslash( $input['username'] ) ) : 'WordPress';
		$out['avatar_url'] = isset( $input['avatar_url'] ) ? esc_url_raw( trim( wp_unslash( $input['avatar_url'] ) ) ) : '';

		return $out;
	}

	public function send( $text, $event, $cfg ) {
		if ( ! $this->is_valid_webhook( $cfg['webhook_url'] ) ) {
			return array(
				'ok'    => false,
				'error' => __( 'Webhook URL is not configured or is invalid.', 'tvn-notify-hub' ),
			);
		}

		$payload = array(
			'content'          => $this->to_discord_markdown( $text ),
			'allowed_mentions' => array( 'parse' => array() ), // Avoid unintentionally pinging @everyone/@here.
		);

		if ( ! empty( $cfg['username'] ) ) {
			$payload['username'] = $cfg['username'];
		}
		if ( ! empty( $cfg['avatar_url'] ) ) {
			$payload['avatar_url'] = $cfg['avatar_url'];
		}

		$response = wp_remote_post(
			$cfg['webhook_url'],
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
				'ok'    => false,
				'error' => $response->get_error_message(),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		// Discord returns 204 (No Content) when OK; 200 if called with ?wait=true.
		if ( 200 === $code || 204 === $code ) {
			return array(
				'ok'    => true,
				'error' => '',
			);
		}

		$body = trim( (string) wp_remote_retrieve_body( $response ) );
		return array(
			'ok'    => false,
			/* translators: 1: HTTP code, 2: response body returned from Discord */
			'error' => sprintf( __( 'Discord returned an error (HTTP %1$d): %2$s', 'tvn-notify-hub' ), $code, $body ),
		);
	}

	/**
	 * Convert Slack-style markdown to Discord + truncate to the length limit.
	 *
	 * Slack: *bold* = bold. Discord: *italic* = italic, **bold** = bold.
	 * Convert *x* (single asterisk) to **x** to preserve the "bold" meaning for the user.
	 *
	 * @param string $text
	 * @return string
	 */
	protected function to_discord_markdown( $text ) {
		// Only convert single *...* pairs (not existing **...**) into **...**.
		$converted = preg_replace( '/(?<!\*)\*(?!\s)([^*\n]+?)(?<!\s)\*(?!\*)/', '**$1**', (string) $text );
		if ( null !== $converted ) {
			$text = $converted;
		}

		if ( function_exists( 'mb_strlen' ) && mb_strlen( $text ) > self::MAX_LEN ) {
			$text = mb_substr( $text, 0, self::MAX_LEN - 1 ) . '…';
		} elseif ( strlen( $text ) > self::MAX_LEN ) {
			$text = substr( $text, 0, self::MAX_LEN - 1 ) . '…';
		}

		return $text;
	}
}
