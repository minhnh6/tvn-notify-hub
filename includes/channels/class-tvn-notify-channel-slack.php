<?php
/**
 * Slack channel — sends via Incoming Webhook.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tvn_Notify_Channel_Slack implements Tvn_Notify_Channel_Interface {

	public function get_id() {
		return 'slack';
	}

	public function get_label() {
		return __( 'Slack', 'tvn-notify-hub' );
	}

	public function get_defaults() {
		return array(
			'enabled'     => 0,
			'webhook_url' => '',
			'username'    => 'WordPress',
			'icon_emoji'  => ':bell:',
			'icon_url'    => '',
			'channel'     => '',
		);
	}

	public function is_ready( $cfg ) {
		return ! empty( $cfg['enabled'] ) && $this->is_valid_webhook( $cfg['webhook_url'] );
	}

	/**
	 * A valid webhook must be on the host hooks.slack.com.
	 *
	 * @param string $url
	 * @return bool
	 */
	public function is_valid_webhook( $url ) {
		if ( empty( $url ) ) {
			return false;
		}
		return ( 'hooks.slack.com' === wp_parse_url( $url, PHP_URL_HOST ) );
	}

	public function render_fields( $cfg, $name_prefix ) {
		?>
		<tr>
			<th scope="row"><?php esc_html_e( 'Enable Slack', 'tvn-notify-hub' ); ?></th>
			<td>
				<label class="tvn-checkbox">
					<input type="checkbox" name="<?php echo esc_attr( $name_prefix ); ?>[enabled]" value="1"
						<?php checked( ! empty( $cfg['enabled'] ) ); ?> />
					<?php esc_html_e( 'Send notifications via Slack', 'tvn-notify-hub' ); ?>
				</label>
			</td>
		</tr>
		<tr>
			<th scope="row"><label><?php esc_html_e( 'Webhook URL', 'tvn-notify-hub' ); ?></label></th>
			<td>
				<input type="url" class="regular-text" autocomplete="off" value=""
					placeholder="<?php echo esc_attr( Tvn_Notify_Secret::placeholder( $cfg['webhook_url'], 'https://hooks.slack.com/services/T000/B000/XXXX' ) ); ?>"
					name="<?php echo esc_attr( $name_prefix ); ?>[webhook_url]" />
				<p class="description">
					<?php esc_html_e( 'Webhook URL provided by Slack. Create a new Incoming Webhook in your Slack workspace.', 'tvn-notify-hub' ); ?>
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
				<p class="description"><?php esc_html_e( 'The name that will be shown in the Slack conversation.', 'tvn-notify-hub' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label><?php esc_html_e( 'Bot Emoji', 'tvn-notify-hub' ); ?></label></th>
			<td>
				<input type="text" class="regular-text" placeholder=":zap:"
					name="<?php echo esc_attr( $name_prefix ); ?>[icon_emoji]"
					value="<?php echo esc_attr( $cfg['icon_emoji'] ); ?>" />
				<p class="description"><?php esc_html_e( 'Emoji used as the icon shown in chat, e.g. :zap: (ignored if Bot Custom Icon is set).', 'tvn-notify-hub' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label><?php esc_html_e( 'Bot Custom Icon', 'tvn-notify-hub' ); ?></label></th>
			<td>
				<div class="tvn-media">
					<input type="url" class="regular-text tvn-media-url"
						name="<?php echo esc_attr( $name_prefix ); ?>[icon_url]"
						value="<?php echo esc_attr( $cfg['icon_url'] ); ?>" />
					<button type="button" class="button tvn-media-pick"><?php esc_html_e( 'Select image', 'tvn-notify-hub' ); ?></button>
					<div class="tvn-media-preview">
						<?php if ( ! empty( $cfg['icon_url'] ) ) : ?>
							<img src="<?php echo esc_url( $cfg['icon_url'] ); ?>" alt="" />
						<?php endif; ?>
					</div>
				</div>
				<p class="description"><?php esc_html_e( 'Custom icon image for the bot (takes priority over Bot Emoji). Should be ≤ 128px and ≤ 64KB.', 'tvn-notify-hub' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label><?php esc_html_e( 'Channel (optional)', 'tvn-notify-hub' ); ?></label></th>
			<td>
				<input type="text" class="regular-text" placeholder="#general"
					name="<?php echo esc_attr( $name_prefix ); ?>[channel]"
					value="<?php echo esc_attr( $cfg['channel'] ); ?>" />
				<p class="description"><?php esc_html_e( 'Override the webhook default channel (e.g. #general or @user). Leave blank to use the webhook channel.', 'tvn-notify-hub' ); ?></p>
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

		// Secret: empty field -> keep the previous value (the secret is never printed to HTML, so it cannot be resubmitted).
		$existing  = Tvn_Notify_Settings::get_channel( $this->get_id() );
		$submitted = isset( $input['webhook_url'] ) ? esc_url_raw( trim( wp_unslash( $input['webhook_url'] ) ) ) : '';
		$existing_url = isset( $existing['webhook_url'] ) ? $existing['webhook_url'] : '';
		if ( '' === $submitted ) {
			$out['webhook_url'] = $existing_url;
		} elseif ( $this->is_valid_webhook( $submitted ) ) {
			$out['webhook_url'] = $submitted;
		} else {
			// Invalid format: keep the previous secret, report an error (do not delete the running config).
			$out['webhook_url'] = $existing_url;
			add_settings_error(
				Tvn_Notify_Settings::OPTION,
				'slack_invalid_webhook',
				__( 'Slack: Webhook URL is invalid. It must look like https://hooks.slack.com/services/... (kept the previous value).', 'tvn-notify-hub' )
			);
		}

		$out['username']   = isset( $input['username'] ) ? sanitize_text_field( wp_unslash( $input['username'] ) ) : 'WordPress';
		$out['icon_emoji'] = isset( $input['icon_emoji'] ) ? sanitize_text_field( wp_unslash( $input['icon_emoji'] ) ) : '';
		$out['icon_url']   = isset( $input['icon_url'] ) ? esc_url_raw( trim( wp_unslash( $input['icon_url'] ) ) ) : '';
		$out['channel']    = isset( $input['channel'] ) ? sanitize_text_field( wp_unslash( $input['channel'] ) ) : '';

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
			'text'         => $this->escape_slack( $text ),
			'mrkdwn'       => true,
			'unfurl_links' => false,
		);

		if ( ! empty( $cfg['username'] ) ) {
			$payload['username'] = $cfg['username'];
		}
		if ( ! empty( $cfg['icon_url'] ) ) {
			$payload['icon_url'] = $cfg['icon_url'];
		} elseif ( ! empty( $cfg['icon_emoji'] ) ) {
			$payload['icon_emoji'] = $cfg['icon_emoji'];
		}
		if ( ! empty( $cfg['channel'] ) ) {
			$payload['channel'] = $cfg['channel'];
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
		$body = trim( (string) wp_remote_retrieve_body( $response ) );

		if ( 200 === $code && 'ok' === $body ) {
			return array(
				'ok'    => true,
				'error' => '',
			);
		}

		return array(
			'ok'    => false,
			/* translators: 1: HTTP code, 2: response body returned from Slack */
			'error' => sprintf( __( 'Slack returned an error (HTTP %1$d): %2$s', 'tvn-notify-hub' ), $code, $body ),
		);
	}

	/**
	 * Escape Slack control characters to block injected mentions (<!channel>, <@U..>) or
	 * link syntax <url|text> coming from user-entered data. Per Slack's recommendation,
	 * only 3 characters need escaping: & < >. The *bold* / :emoji: formatting is unaffected.
	 *
	 * @param string $text
	 * @return string
	 */
	protected function escape_slack( $text ) {
		return strtr(
			(string) $text,
			array(
				'&' => '&amp;',
				'<' => '&lt;',
				'>' => '&gt;',
			)
		);
	}
}
