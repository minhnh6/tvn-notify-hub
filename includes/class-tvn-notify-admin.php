<?php
/**
 * Admin page: configure actions, channels, message template, send tests & view logs.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tvn_Notify_Admin {

	const SLUG = 'tvn-notify-hub';

	/**
	 * The page's hook suffix (to enqueue assets in the right place).
	 *
	 * @var string
	 */
	protected static $hook = '';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_init', array( 'Tvn_Notify_Settings', 'register' ) );

		add_action( 'admin_post_tvn_notify_hub_test', array( __CLASS__, 'handle_test' ) );
		add_action( 'admin_post_tvn_notify_hub_clear_log', array( __CLASS__, 'handle_clear_log' ) );

		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_filter( 'plugin_action_links_' . TVN_NOTIFY_HUB_BASENAME, array( __CLASS__, 'action_links' ) );
	}

	public static function add_menu() {
		self::$hook = add_menu_page(
			__( 'Notify Hub', 'tvn-notify-hub' ),
			__( 'Notify Hub', 'tvn-notify-hub' ),
			'manage_options',
			self::SLUG,
			array( __CLASS__, 'render' ),
			'dashicons-megaphone',
			81
		);
	}

	public static function action_links( $links ) {
		$url      = admin_url( 'admin.php?page=' . self::SLUG );
		$settings = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'tvn-notify-hub' ) . '</a>';
		array_unshift( $links, $settings );
		return $links;
	}

	public static function enqueue_assets( $hook ) {
		if ( $hook !== self::$hook ) {
			return;
		}
		wp_enqueue_media(); // For Bot Custom Icon.
		wp_enqueue_style(
			'tvn-notify-hub-admin',
			TVN_NOTIFY_HUB_URL . 'assets/admin.css',
			array(),
			TVN_NOTIFY_HUB_VERSION
		);
		wp_enqueue_script(
			'tvn-notify-hub-admin',
			TVN_NOTIFY_HUB_URL . 'assets/admin.js',
			array( 'jquery' ),
			TVN_NOTIFY_HUB_VERSION,
			true
		);
	}

	/* ----------------------------------------------------------------------
	 * Handle actions (admin-post.php)
	 * -------------------------------------------------------------------- */

	protected static function guard( $action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'tvn-notify-hub' ) );
		}
		check_admin_referer( $action );
	}

	public static function handle_test() {
		self::guard( 'tvn_notify_hub_test' );

		$results = Tvn_Notify_Listener::send_test();

		$ok   = 0;
		$fail = 0;
		foreach ( $results as $r ) {
			if ( ! empty( $r['ok'] ) ) {
				$ok++;
			} else {
				$fail++;
			}
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => self::SLUG,
					'tvn_test_ok'  => $ok,
					'tvn_test_err' => $fail,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public static function handle_clear_log() {
		self::guard( 'tvn_notify_hub_clear_log' );
		Tvn_Notify_Logger::clear();
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'            => self::SLUG,
					'tvn_log_cleared' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/* ----------------------------------------------------------------------
	 * Render
	 * -------------------------------------------------------------------- */

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$opts     = Tvn_Notify_Settings::get();
		$presets  = Tvn_Notify_Settings::preset_hooks();
		$opt_name = Tvn_Notify_Settings::OPTION;
		?>
		<div class="wrap tvn-notify-wrap">
			<h1><?php esc_html_e( 'TVN Notify Hub', 'tvn-notify-hub' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Send notifications to chat apps when a user performs an action you choose. Multi-channel architecture — currently supports Slack, ready to extend to Telegram, WhatsApp, X...', 'tvn-notify-hub' ); ?>
			</p>

			<?php self::render_notices(); ?>

			<form method="post" action="options.php">
				<?php settings_fields( Tvn_Notify_Settings::GROUP ); ?>

				<h2 class="title"><?php esc_html_e( 'Overview', 'tvn-notify-hub' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Activate', 'tvn-notify-hub' ); ?></th>
						<td>
							<label class="tvn-checkbox">
								<input type="checkbox" name="<?php echo esc_attr( $opt_name ); ?>[enabled]" value="1"
									<?php checked( ! empty( $opts['enabled'] ) ); ?> />
								<?php esc_html_e( 'Enable sending notifications (turning this off stops everything)', 'tvn-notify-hub' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Active Notifications — choose actions', 'tvn-notify-hub' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Which actions do you want to be notified about?', 'tvn-notify-hub' ); ?></p>
				<table class="form-table" role="presentation">
					<?php foreach ( $presets as $group_label => $hooks ) : ?>
						<tr>
							<th scope="row"><?php echo esc_html( $group_label ); ?></th>
							<td>
								<fieldset>
									<?php foreach ( $hooks as $hook => $info ) : ?>
										<label class="tvn-checkbox">
											<input type="checkbox"
												name="<?php echo esc_attr( $opt_name ); ?>[hooks][]"
												value="<?php echo esc_attr( $hook ); ?>"
												<?php checked( in_array( $hook, (array) $opts['hooks'], true ) ); ?> />
											<?php echo esc_html( $info['label'] ); ?>
											<code><?php echo esc_html( $hook ); ?></code>
										</label><br />
									<?php endforeach; ?>
								</fieldset>
							</td>
						</tr>
					<?php endforeach; ?>

					<tr>
						<th scope="row">
							<label for="tvn-custom-hooks"><?php esc_html_e( 'Custom hooks', 'tvn-notify-hub' ); ?></label>
						</th>
						<td>
							<textarea id="tvn-custom-hooks" rows="5" class="large-text code"
								name="<?php echo esc_attr( $opt_name ); ?>[custom_hooks]"><?php echo esc_textarea( $opts['custom_hooks'] ); ?></textarea>
							<p class="description">
								<?php esc_html_e( 'One hook per line to catch actions from OTHER PLUGINS (WooCommerce, CF7, Awesome Support...). Format: hook_name or hook_name|number_of_args. Lines starting with # are comments.', 'tvn-notify-hub' ); ?>
							</p>
							<p class="description">
								<?php esc_html_e( 'Example:', 'tvn-notify-hub' ); ?>
								<code>woocommerce_order_status_completed|1</code>,
								<code>wpcf7_mail_sent</code>,
								<code>wpas_open_ticket|2</code>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Options', 'tvn-notify-hub' ); ?></th>
						<td>
							<label class="tvn-checkbox">
								<input type="checkbox" name="<?php echo esc_attr( $opt_name ); ?>[only_logged_in]" value="1"
									<?php checked( ! empty( $opts['only_logged_in'] ) ); ?> />
								<?php esc_html_e( 'Only notify when the actor is logged in', 'tvn-notify-hub' ); ?>
							</label><br />
							<label class="tvn-checkbox">
								<input type="checkbox" name="<?php echo esc_attr( $opt_name ); ?>[include_args]" value="1"
									<?php checked( ! empty( $opts['include_args'] ) ); ?> />
								<?php esc_html_e( 'Attach the hook\'s technical arguments to the message (useful for debugging)', 'tvn-notify-hub' ); ?>
							</label><br />
							<label class="tvn-checkbox">
								<input type="checkbox" name="<?php echo esc_attr( $opt_name ); ?>[log_enabled]" value="1"
									<?php checked( ! empty( $opts['log_enabled'] ) ); ?> />
								<?php esc_html_e( 'Write a log on each send', 'tvn-notify-hub' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Message template', 'tvn-notify-hub' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="tvn-template"><?php esc_html_e( 'Content', 'tvn-notify-hub' ); ?></label></th>
						<td>
							<textarea id="tvn-template" rows="7" class="large-text code"
								name="<?php echo esc_attr( $opt_name ); ?>[message_template]"><?php echo esc_textarea( $opts['message_template'] ); ?></textarea>
							<p class="description">
								<?php esc_html_e( 'Available placeholders:', 'tvn-notify-hub' ); ?>
								<code>{action}</code> <code>{hook}</code> <code>{user}</code> <code>{user_login}</code>
								<code>{user_email}</code> <code>{role}</code> <code>{ip}</code> <code>{time}</code>
								<code>{summary}</code> <code>{site}</code> <code>{url}</code>
							</p>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Channels', 'tvn-notify-hub' ); ?></h2>
				<?php foreach ( Tvn_Notify_Channels::all() as $id => $channel ) : ?>
					<h3 class="tvn-channel-title"><?php echo esc_html( $channel->get_label() ); ?></h3>
					<table class="form-table" role="presentation">
						<?php
						$cfg         = isset( $opts['channels'][ $id ] ) ? $opts['channels'][ $id ] : array();
						$name_prefix = $opt_name . '[channels][' . $id . ']';
						$channel->render_fields( $cfg, $name_prefix );
						?>
					</table>
				<?php endforeach; ?>

				<?php submit_button( __( 'Save settings', 'tvn-notify-hub' ) ); ?>
			</form>

			<hr />

			<h2><?php esc_html_e( 'Send test', 'tvn-notify-hub' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Send a test message to every enabled and valid channel (save settings before testing).', 'tvn-notify-hub' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="tvn_notify_hub_test" />
				<?php wp_nonce_field( 'tvn_notify_hub_test' ); ?>
				<button type="submit" class="button button-secondary"><?php esc_html_e( 'Send test message', 'tvn-notify-hub' ); ?></button>
			</form>

			<?php self::render_log(); ?>
		</div>
		<?php
	}

	protected static function render_notices() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- only reading notice parameters after an internal redirect.
		settings_errors( Tvn_Notify_Settings::OPTION );

		if ( isset( $_GET['tvn_test_ok'] ) || isset( $_GET['tvn_test_err'] ) ) {
			$ok   = isset( $_GET['tvn_test_ok'] ) ? (int) $_GET['tvn_test_ok'] : 0;
			$err  = isset( $_GET['tvn_test_err'] ) ? (int) $_GET['tvn_test_err'] : 0;
			if ( 0 === $ok && 0 === $err ) {
				echo '<div class="notice notice-warning is-dismissible"><p>'
					. esc_html__( 'No channel is ready. Enable and configure at least one channel.', 'tvn-notify-hub' )
					. '</p></div>';
			} else {
				$cls = $err > 0 ? 'notice-warning' : 'notice-success';
				echo '<div class="notice ' . esc_attr( $cls ) . ' is-dismissible"><p>'
					. sprintf(
						/* translators: 1: number of channels sent successfully, 2: number of failed channels */
						esc_html__( 'Test sent: %1$d succeeded, %2$d failed. See error details in the log below.', 'tvn-notify-hub' ),
						(int) $ok,
						(int) $err
					)
					. '</p></div>';
			}
		}

		if ( isset( $_GET['tvn_log_cleared'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>'
				. esc_html__( 'All log entries cleared.', 'tvn-notify-hub' )
				. '</p></div>';
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	protected static function render_log() {
		$rows = Tvn_Notify_Logger::get_recent( 50 );
		?>
		<hr />
		<h2><?php esc_html_e( 'Recent send log', 'tvn-notify-hub' ); ?></h2>

		<?php if ( empty( $rows ) ) : ?>
			<p><em><?php esc_html_e( 'No log entries yet.', 'tvn-notify-hub' ); ?></em></p>
		<?php else : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:10px;">
				<input type="hidden" name="action" value="tvn_notify_hub_clear_log" />
				<?php wp_nonce_field( 'tvn_notify_hub_clear_log' ); ?>
				<button type="submit" class="button button-link-delete"><?php esc_html_e( 'Clear all log', 'tvn-notify-hub' ); ?></button>
			</form>

			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Time', 'tvn-notify-hub' ); ?></th>
						<th><?php esc_html_e( 'Hook', 'tvn-notify-hub' ); ?></th>
						<th><?php esc_html_e( 'Channel', 'tvn-notify-hub' ); ?></th>
						<th><?php esc_html_e( 'User', 'tvn-notify-hub' ); ?></th>
						<th><?php esc_html_e( 'Status', 'tvn-notify-hub' ); ?></th>
						<th><?php esc_html_e( 'Error', 'tvn-notify-hub' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td><?php echo esc_html( $row->created_at ); ?></td>
							<td><code><?php echo esc_html( $row->hook ); ?></code></td>
							<td><?php echo esc_html( $row->channel ); ?></td>
							<td>
								<?php
								if ( $row->user_id ) {
									$u = get_user_by( 'id', (int) $row->user_id );
									echo esc_html( $u ? $u->user_login : '#' . $row->user_id );
								} else {
									echo '&mdash;';
								}
								?>
							</td>
							<td>
								<?php if ( 'success' === $row->status ) : ?>
									<span class="tvn-badge tvn-ok"><?php esc_html_e( 'OK', 'tvn-notify-hub' ); ?></span>
								<?php else : ?>
									<span class="tvn-badge tvn-err"><?php esc_html_e( 'Error', 'tvn-notify-hub' ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo $row->error ? esc_html( $row->error ) : '&mdash;'; ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<?php
	}
}
