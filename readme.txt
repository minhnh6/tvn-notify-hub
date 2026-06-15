=== TVN Notify Hub ===
Contributors: minhnh6
Tags: notifications, slack, discord, telegram, webhook
Requires at least: 5.3
Tested up to: 7.0
Requires PHP: 7.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.txt

Send notifications to Slack, Discord, Telegram or Email when users perform WordPress actions/hooks that an admin selects.

== Description ==

TVN Notify Hub listens to the WordPress actions/hooks you choose and pings your chat apps or email whenever one of them fires.

Key features:

1. **Ping on user actions** — get notified each time a user performs an action that the admin has enabled.
2. **Flexible settings** — pick one or many built‑in actions, choose recipients, and configure each channel independently.
3. **Catch hooks from other plugins** — besides the built‑in action list, you can add ANY hook name (e.g. `woocommerce_order_status_completed`, `wpcf7_mail_sent`, `wpas_open_ticket`). Because WordPress hooks are global, hooks registered by other plugins work too.
4. **Multi‑channel architecture** — the core is decoupled from the delivery channels. Built‑in channels: **Slack, Discord, Telegram, Email**. Adding a new one (WhatsApp, X, Mastodon, Microsoft Teams...) only requires implementing `Tvn_Notify_Channel_Interface` and registering it via the `tvn_notify_hub_channels` filter.
5. **Translation ready (i18n)** — the source language is English and the plugin ships with translations for Vietnamese, German, French, Spanish, Italian, Brazilian Portuguese, Russian, Simplified Chinese, Japanese, Korean, Dutch and Indonesian. A `.pot` template is included so you can translate it into any language. The UI follows your site language automatically.

= Channels =

* **Slack** — Incoming Webhook (Bot name, Bot emoji, custom icon, channel override).
* **Discord** — Incoming Webhook (Bot name, avatar).
* **Telegram** — Bot API (Bot token + Chat ID, silent option).
* **Email** — `wp_mail()` with a shared subject/HTML template; recipients are picked from admin users (username — email) plus optional extra addresses.

== External services ==

This plugin connects to third‑party services ONLY for the channels you explicitly enable and configure. Nothing is sent until you turn a channel on, save a valid webhook/token, and a selected action fires (or you click “Send test”).

What is sent: the event data used to build the notification — the action/hook name, the acting user's display name, login, email and role, the IP address of the request, the timestamp, your site name and URL, and a short summary of the event.

* **Slack** — data is sent to the Incoming Webhook URL you provide (host: hooks.slack.com). Terms: https://slack.com/terms-of-service — Privacy: https://slack.com/trust/privacy/privacy-policy
* **Discord** — data is sent to the Webhook URL you provide (host: discord.com). Terms: https://discord.com/terms — Privacy: https://discord.com/privacy
* **Telegram** — data is sent to the Telegram Bot API (host: api.telegram.org) using your bot token and chat id. Terms: https://telegram.org/tos — Privacy: https://telegram.org/privacy

The **Email** channel uses WordPress' own `wp_mail()` and does not send data to any service controlled by this plugin (delivery depends on your server/SMTP configuration).

== Privacy ==

The plugin stores a local log of recent sends (hook, channel, user id, status, error and the rendered message) in a custom database table so admins can troubleshoot delivery. Logging can be turned off in the settings, and the log can be cleared at any time. All plugin data (settings + log table) is removed on uninstall.

== Installation ==

1. Upload the `tvn-notify-hub` folder to `/wp-content/plugins/` (or install the ZIP via Plugins → Add New → Upload).
2. Activate the plugin through the “Plugins” menu in WordPress.
3. Open the **Notify Hub** menu (megaphone icon in the admin sidebar).
4. In **Active Notifications**, tick the actions you want to be notified about. To listen to a hook from another plugin, add it in the **Custom hooks** box (one per line).
5. In **Channels**, enable and configure at least one channel (Slack, Discord, Telegram or Email).
6. (Chat channels) Adjust the **Message template** if you like — it supports placeholders such as `{action}`, `{user}`, `{ip}`, `{summary}`, `{time}`, `{site}`.
7. Click **Save settings**, then use **Send test** to confirm delivery. Results and any errors appear in the **Recent send log**.

A full step‑by‑step guide (including how to obtain each webhook/token) is included in the plugin folder as `USAGE.md` (English) and `HUONG-DAN-SU-DUNG.md` (Vietnamese).

== Frequently Asked Questions ==

= Can one action notify several channels at once? =
Yes. Every enabled and valid channel receives the notification for the same event.

= Can I notify on a hook from another plugin? =
Yes. In “Custom hooks”, add one hook name per line. Optionally append `|N` to set how many arguments the hook passes, e.g. `woocommerce_order_status_completed|1`. Lines starting with `#` are treated as comments.

= How do I find a hook name and its argument count? =
Look in the other plugin's code for `do_action( 'hook_name', $arg1, $arg2, ... )`, or use a tool such as Query Monitor / Simply Show Hooks. The number of arguments after the comma is the value you put after `|`.

= Where do I get a Slack webhook? =
Go to https://api.slack.com/apps → create/select an app → Incoming Webhooks → Activate → Add New Webhook to Workspace → choose a channel → copy the URL (`https://hooks.slack.com/services/...`).

= Where do I get a Discord webhook? =
In your server: Server Settings → Integrations → Webhooks → New Webhook → Copy Webhook URL.

= How do I get a Telegram bot token and chat id? =
Talk to @BotFather and send `/newbot` to create a bot and get the token. To find a chat id, add the bot to the chat, send a message, then open `https://api.telegram.org/bot<TOKEN>/getUpdates` and read `"chat":{"id":...}`. For a channel, add the bot as an admin and use `@channel_username`.

= How is the Email channel different from chat channels? =
Email has its own subject + HTML body (one template shared by all actions), while Slack/Discord/Telegram share the “Message template”. Email recipients are picked from your admin users (username — email) plus optional extra addresses.

= The email channel returns an error. =
`wp_mail()` returned false, which usually means the server cannot send mail. Configure an SMTP plugin/service (e.g. WP Mail SMTP), then test again.

= Are my webhook URLs and tokens kept secret? =
Yes. They are never printed back into the settings form — the field is shown blank with a short hint of the last characters. Leave it blank to keep the stored value, or type a new value to replace it.

= How do I pause notifications temporarily? =
Untick the master switch in the **Overview** block and save. Everything stops sending while your settings are preserved.

= How can a developer add a new channel? =
Implement `Tvn_Notify_Channel_Interface` and register it via the `tvn_notify_hub_channels` filter. See `USAGE.md` for code examples and other available filters.

= What languages are available? =
English (source) plus bundled translations for Vietnamese, German, French, Spanish, Italian, Brazilian Portuguese, Russian, Simplified Chinese, Japanese, Korean, Dutch and Indonesian. The plugin follows your site language (Settings → General → Site Language). To add another language, translate the included `languages/tvn-notify-hub.pot` (e.g. with Poedit) and drop the resulting `.po`/`.mo` into the `languages` folder.

== For developers ==

Add a channel:

`
add_filter( 'tvn_notify_hub_channels', function ( $channels ) {
    $channels[] = new My_Channel(); // implements Tvn_Notify_Channel_Interface
    return $channels;
} );
`

Other filters:

* `tvn_notify_hub_preset_hooks` — add built‑in actions to the settings screen.
* `tvn_notify_hub_event` — inspect/modify an event before sending (return false to skip).

== Changelog ==

= 1.0.0 =
* Initial release: multi‑channel core with Slack, Discord, Telegram and Email channels; action picker, custom hooks, shared templates, test sending and a delivery log.
* Full internationalization (English source) with bundled translations: Vietnamese, German, French, Spanish, Italian, Brazilian Portuguese, Russian, Simplified Chinese, Japanese, Korean, Dutch, Indonesian.
