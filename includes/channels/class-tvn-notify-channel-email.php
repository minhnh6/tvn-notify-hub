<?php
/**
 * Email channel — sends via wp_mail().
 *
 * Unlike the chat channels: there is no webhook/token. Recipients are chosen from the list of
 * admin users (username — email) and/or by entering extra standalone emails. The subject + content
 * is ONE template shared by all actions (as required), rendered using placeholders.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tvn_Notify_Channel_Email implements Tvn_Notify_Channel_Interface {

	const EDITOR_ID = 'tvn_notify_email_content';

	public function get_id() {
		return 'email';
	}

	public function get_label() {
		return __( 'Email', 'tvn-notify-hub' );
	}

	public function get_defaults() {
		return array(
			'enabled'          => 0,
			'recipient_users'  => array(),
			'recipient_emails' => '',
			'subject'          => __( 'Notification: {action}', 'tvn-notify-hub' ),
			'content'          => self::default_content(),
		);
	}

	/**
	 * Default email content (HTML).
	 *
	 * @return string
	 */
	public static function default_content() {
		return "<p>Hello,</p>\n"
			. "<p>An action just occurred on the website <strong>{site}</strong>:</p>\n"
			. "<table cellpadding=\"6\" style=\"border-collapse:collapse;border:1px solid #e2e4e7\">\n"
			. "<tr><td><strong>Action</strong></td><td>{action}</td></tr>\n"
			. "<tr><td><strong>User</strong></td><td>{user} ({user_login}) – {role}</td></tr>\n"
			. "<tr><td><strong>Email</strong></td><td>{user_email}</td></tr>\n"
			. "<tr><td><strong>Time</strong></td><td>{time}</td></tr>\n"
			. "<tr><td><strong>IP</strong></td><td>{ip}</td></tr>\n"
			. "<tr><td><strong>Details</strong></td><td>{summary}</td></tr>\n"
			. "<tr><td><strong>URL</strong></td><td>{url}</td></tr>\n"
			. "</table>\n"
			. "<p>— {site}</p>";
	}

	public function is_ready( $cfg ) {
		if ( empty( $cfg['enabled'] ) ) {
			return false;
		}
		$has_recipient = ! empty( $cfg['recipient_users'] ) || '' !== trim( (string) $cfg['recipient_emails'] );
		return $has_recipient
			&& '' !== trim( (string) $cfg['subject'] )
			&& '' !== trim( wp_strip_all_tags( (string) $cfg['content'] ) );
	}

	/**
	 * List of admin users to choose recipients from.
	 *
	 * @return WP_User[]
	 */
	protected function admin_users() {
		return get_users(
			array(
				'role'    => 'administrator',
				'orderby' => 'display_name',
				'order'   => 'ASC',
			)
		);
	}

	public function render_fields( $cfg, $name_prefix ) {
		?>
		<tr>
			<th scope="row"><?php esc_html_e( 'Enable Email', 'tvn-notify-hub' ); ?></th>
			<td>
				<label class="tvn-checkbox">
					<input type="checkbox" name="<?php echo esc_attr( $name_prefix ); ?>[enabled]" value="1"
						<?php checked( ! empty( $cfg['enabled'] ) ); ?> />
					<?php esc_html_e( 'Send notifications via Email', 'tvn-notify-hub' ); ?>
				</label>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Recipients (admin users)', 'tvn-notify-hub' ); ?></th>
			<td>
				<fieldset class="tvn-recipients">
					<?php
					$selected = array_map( 'intval', (array) $cfg['recipient_users'] );
					$users    = $this->admin_users();
					if ( empty( $users ) ) :
						?>
						<em><?php esc_html_e( 'No admin users found.', 'tvn-notify-hub' ); ?></em>
					<?php else : ?>
						<?php foreach ( $users as $u ) : ?>
							<label class="tvn-checkbox">
								<input type="checkbox"
									name="<?php echo esc_attr( $name_prefix ); ?>[recipient_users][]"
									value="<?php echo esc_attr( $u->ID ); ?>"
									<?php checked( in_array( (int) $u->ID, $selected, true ) ); ?> />
								<?php echo esc_html( $u->user_login ); ?>
								<code><?php echo esc_html( $u->user_email ); ?></code>
							</label><br />
						<?php endforeach; ?>
					<?php endif; ?>
				</fieldset>
				<p class="description"><?php esc_html_e( 'Check the admins who should receive notifications.', 'tvn-notify-hub' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label><?php esc_html_e( 'Other emails (optional)', 'tvn-notify-hub' ); ?></label></th>
			<td>
				<input type="text" class="large-text"
					name="<?php echo esc_attr( $name_prefix ); ?>[recipient_emails]"
					value="<?php echo esc_attr( $cfg['recipient_emails'] ); ?>" />
				<p class="description"><?php esc_html_e( 'A list of emails, separated by commas (sent in addition to the selected admins).', 'tvn-notify-hub' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="tvn-email-subject"><?php esc_html_e( 'Subject', 'tvn-notify-hub' ); ?></label></th>
			<td>
				<input type="text" id="tvn-email-subject" class="large-text"
					name="<?php echo esc_attr( $name_prefix ); ?>[subject]"
					value="<?php echo esc_attr( $cfg['subject'] ); ?>" />
			</td>
		</tr>
		<tr>
			<th scope="row"><label><?php esc_html_e( 'Content', 'tvn-notify-hub' ); ?></label></th>
			<td>
				<?php
				wp_editor(
					$cfg['content'],
					self::EDITOR_ID,
					array(
						'textarea_name' => $name_prefix . '[content]',
						'textarea_rows' => 12,
						'media_buttons' => true,
						'tinymce'       => true,
						'quicktags'     => true,
					)
				);
				?>
				<p class="description">
					<?php esc_html_e( 'Email template shared by ALL actions. Available placeholders:', 'tvn-notify-hub' ); ?>
					<code>{action}</code> <code>{hook}</code> <code>{user}</code> <code>{user_login}</code>
					<code>{user_email}</code> <code>{role}</code> <code>{ip}</code> <code>{time}</code>
					<code>{summary}</code> <code>{site}</code> <code>{url}</code>
				</p>
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

		// Recipients are admin users — only keep valid IDs that have admin permissions.
		$out['recipient_users'] = array();
		if ( ! empty( $input['recipient_users'] ) && is_array( $input['recipient_users'] ) ) {
			foreach ( $input['recipient_users'] as $uid ) {
				$uid = (int) $uid;
				$u   = $uid ? get_userdata( $uid ) : false;
				if ( $u && user_can( $u, 'manage_options' ) && is_email( $u->user_email ) ) {
					$out['recipient_users'][] = $uid;
				}
			}
			$out['recipient_users'] = array_values( array_unique( $out['recipient_users'] ) );
		}

		// Standalone emails — only keep valid emails.
		$emails = array();
		if ( isset( $input['recipient_emails'] ) ) {
			foreach ( preg_split( '/[,;\s]+/', (string) wp_unslash( $input['recipient_emails'] ) ) as $e ) {
				$e = sanitize_email( trim( $e ) );
				if ( $e && is_email( $e ) ) {
					$emails[] = $e;
				}
			}
		}
		$out['recipient_emails'] = implode( ', ', array_unique( $emails ) );

		$out['subject'] = isset( $input['subject'] )
			? sanitize_text_field( wp_unslash( $input['subject'] ) )
			: $out['subject'];

		$out['content'] = isset( $input['content'] )
			? wp_kses_post( wp_unslash( $input['content'] ) )
			: $out['content'];

		return $out;
	}

	public function send( $text, $event, $cfg ) {
		$recipients = $this->resolve_recipients( $cfg );
		if ( empty( $recipients ) ) {
			return array(
				'ok'    => false,
				'error' => __( 'No valid recipient.', 'tvn-notify-hub' ),
			);
		}

		$subject = $this->render_subject( $cfg['subject'], $event );
		$body    = $this->render_body( $cfg['content'], $event );

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		$sent = wp_mail( $recipients, $subject, $body, $headers );

		if ( $sent ) {
			return array(
				'ok'    => true,
				'error' => '',
			);
		}

		return array(
			'ok'    => false,
			'error' => __( 'wp_mail() returned false — check the site mail / SMTP sending configuration.', 'tvn-notify-hub' ),
		);
	}

	/**
	 * Collect the list of recipient emails (from selected admin users + standalone emails).
	 *
	 * @param array $cfg
	 * @return string[]
	 */
	protected function resolve_recipients( $cfg ) {
		$emails = array();

		foreach ( (array) $cfg['recipient_users'] as $uid ) {
			$u = get_userdata( (int) $uid );
			if ( $u && is_email( $u->user_email ) ) {
				$emails[] = $u->user_email;
			}
		}

		if ( ! empty( $cfg['recipient_emails'] ) ) {
			foreach ( preg_split( '/[,;\s]+/', (string) $cfg['recipient_emails'] ) as $e ) {
				$e = trim( $e );
				if ( $e && is_email( $e ) ) {
					$emails[] = $e;
				}
			}
		}

		return array_values( array_unique( $emails ) );
	}

	/**
	 * Render the subject (plain text).
	 *
	 * @param string $template
	 * @param array  $event
	 * @return string
	 */
	protected function render_subject( $template, $event ) {
		return sanitize_text_field( strtr( (string) $template, $this->subject_map( $event ) ) );
	}

	/**
	 * Render the HTML content — placeholder values are escaped, {summary} line breaks -> <br>.
	 *
	 * @param string $template HTML composed by the admin (passed through wp_kses_post on save).
	 * @param array  $event
	 * @return string
	 */
	protected function render_body( $template, $event ) {
		$map = array();
		foreach ( $this->subject_map( $event ) as $k => $v ) {
			$map[ $k ] = esc_html( $v );
		}
		$summary        = '' !== $event['summary'] ? $event['summary'] : '-';
		$map['{summary}'] = nl2br( esc_html( $summary ) );

		return strtr( (string) $template, $map );
	}

	/**
	 * Map placeholder -> raw value (not escaped).
	 *
	 * @param array $event
	 * @return array
	 */
	protected function subject_map( $event ) {
		return array(
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
	}
}
