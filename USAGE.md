# TVN Notify Hub — Detailed Usage Guide

Send notifications (pings) to **Slack, Discord, Telegram, Email** whenever a user performs an action/hook that the admin selects — including hooks registered by **other plugins** (WooCommerce, Contact Form 7, Awesome Support...).

**Multi‑channel** by design: one event can be delivered to several enabled channels at once.

---

## Table of contents

1. [Installation](#1-installation)
2. [Settings screen overview](#2-settings-screen-overview)
3. [Choosing actions to notify on](#3-choosing-actions-to-notify-on)
4. [Catching hooks from other plugins (Custom hooks)](#4-catching-hooks-from-other-plugins-custom-hooks)
5. [General options](#5-general-options)
6. [Message template & placeholders](#6-message-template--placeholders)
7. [Configuring each channel](#7-configuring-each-channel)
   - [Slack](#71-slack)
   - [Discord](#72-discord)
   - [Telegram](#73-telegram)
   - [Email](#74-email)
8. [Send test & view Log](#8-send-test--view-log)
9. [Security — what you should know](#9-security--what-you-should-know)
10. [Troubleshooting](#10-troubleshooting)
11. [For developers](#11-for-developers)
12. [FAQ](#12-faq)

---

## 1. Installation

1. Copy the `tvn-notify-hub` folder into `wp-content/plugins/` (or go to **Plugins → Add New → Upload Plugin** and upload the ZIP).
2. Go to **Plugins** and activate **TVN Notify Hub**.
3. A **Notify Hub** menu (megaphone icon 📣) appears in the left admin sidebar.

> Requires WordPress 5.3+ and PHP 7.2+.

---

## 2. Settings screen overview

Open **Notify Hub**. The page contains these blocks, in order:

| Block | Purpose |
|-------|---------|
| **Overview** | Master switch (enable/disable the whole plugin) |
| **Active Notifications** | Tick the built‑in actions you want to be notified about |
| **Custom hooks** | Add any hook name (including from other plugins) |
| **Options** | Logged‑in only / attach args / write log |
| **Message template** | Body sent to **chat** channels (Slack/Discord/Telegram) |
| **Channels** | Per‑channel settings: Slack, Discord, Telegram, Email |
| **Send test** | Fire one test message to all enabled channels |
| **Recent send log** | Last 50 sends + error details (if any) |

> Always click **Save settings** after editing, then use **Send test**.

---

## 3. Choosing actions to notify on

In the **Active Notifications** block, tick the actions you want. They are grouped into three sets:

**Users**
- Successful login (`wp_login`)
- Logout (`wp_logout`)
- Failed login (`wp_login_failed`)
- New user registration (`user_register`)
- Profile update (`profile_update`)
- User deleted (`delete_user`)
- Password reset (`password_reset`)

**Content**
- Post status changed — published/draft/pending... (`transition_post_status`)
- Post moved to trash (`wp_trash_post`)
- Post permanently deleted (`before_delete_post`)
- New comment (`comment_post`)
- Media uploaded (`add_attachment`)

**System**
- Plugin activated (`activated_plugin`)
- Plugin deactivated (`deactivated_plugin`)
- Theme switched (`switch_theme`)

> **Note:** `transition_post_status` only fires a notification when the status **actually changes** (no spam on every save with the same status).

---

## 4. Catching hooks from other plugins (Custom hooks)

Because WordPress hooks are **global**, you can listen to a hook registered by any plugin.

In the **Custom hooks** box, add one hook per line:

```
# One hook per line. Lines starting with # are comments.
woocommerce_order_status_completed|1
wpcf7_mail_sent
wpas_open_ticket|2
```

Syntax: `hook_name` or `hook_name|number_of_args`.

- `number_of_args` (optional) = how many arguments the hook passes. Defaults to `1` if omitted.
- Setting the correct count lets the **Summary** part of the notification show full data (e.g. `comment_post` passes 3 args).

**How do I find the hook name & arg count?**
- Look in the other plugin's code for `do_action( 'hook_name', $arg1, $arg2, ... )`.
- Or check the plugin docs / use tools like *Query Monitor*, *Simply Show Hooks*.

---

## 5. General options

In the **Options** block:

- **Only notify when the actor is logged in** — ignore actions by anonymous visitors.
- **Attach the hook's technical arguments** — add the hook's raw args to the message (useful for *debugging* custom hooks).
  > Sensitive arguments (e.g. the new password from `password_reset`) are **always masked** as `***` and never sent.
- **Write a log on each send** — keep history for monitoring/troubleshooting.

---

## 6. Message template & placeholders

The **Message template** block defines the body sent to **chat channels** (Slack, Discord, Telegram). Email uses its **own template** (see [the Email section](#74-email)).

Available placeholders (apply to both the chat template and the email template):

| Placeholder | Meaning |
|-------------|---------|
| `{action}` | Human‑readable action name (e.g. "Successful login") |
| `{hook}` | Technical hook name (e.g. `wp_login`) |
| `{user}` | User display name |
| `{user_login}` | Username |
| `{user_email}` | User email |
| `{role}` | Role |
| `{ip}` | Request IP address |
| `{time}` | Time (site timezone) |
| `{summary}` | Short, auto‑generated description of the event |
| `{site}` | Site name + URL |
| `{url}` | Request URL |

Default template:

```
:bell: *{action}*
• User: *{user}* ({user_login}) – {role}
• Time: {time}
• IP: {ip}
• Details: {summary}
• Website: {site}
```

> `*bold*` and `:emoji:` render well on Slack/Discord. Telegram converts `:bell:` → 🔔 and handles bold; if formatting fails, the plugin automatically resends as plain text so the notification is never lost.

---

## 7. Configuring each channel

Enable and fill in at least one channel, then **Save settings**.

### 7.1 Slack

**Get a Webhook URL:**
1. Go to https://api.slack.com/apps → **Create New App** (or use an existing one).
2. Open **Incoming Webhooks** → toggle **Activate Incoming Webhooks**.
3. **Add New Webhook to Workspace** → pick a channel → **Allow**.
4. Copy the URL, shaped like `https://hooks.slack.com/services/T000/B000/XXXX`.

**In the plugin:**
- **Enable Slack** ✔
- **Webhook URL**: paste the URL.
- **Bot Name**: display name in Slack (e.g. `WordPress`).
- **Bot Emoji**: e.g. `:zap:` (ignored if a Bot Custom Icon is set).
- **Bot Custom Icon**: pick an image from the Media Library (takes priority over the emoji).
- **Channel (optional)**: override the webhook's default channel, e.g. `#general` or `@user`.

### 7.2 Discord

**Get a Webhook URL:**
1. Open your Discord server → **Server Settings → Integrations → Webhooks**.
2. **New Webhook** → pick a channel → **Copy Webhook URL**.

**In the plugin:**
- **Enable Discord** ✔
- **Webhook URL**: paste the URL (`https://discord.com/api/webhooks/...`).
- **Bot Name**: display name.
- **Bot Custom Icon**: avatar image (Discord does not use emoji as the icon).

### 7.3 Telegram

**Create a bot & get the Token:**
1. On Telegram, chat with **@BotFather** → send `/newbot` → set a name → receive a **token** like `123456789:AA...`.

**Get the Chat ID:**
- **DM/group:** add the bot to the group, send any message, then open:
  `https://api.telegram.org/bot<TOKEN>/getUpdates` → find `"chat":{"id":...}`.
- **Channel:** add the bot as a channel **admin**, use `@channel_username` as the Chat ID.
- Group IDs are usually **negative** (e.g. `-1001234567890`).

**In the plugin:**
- **Enable Telegram** ✔
- **Bot Token**: paste the token.
- **Chat ID**: a user id / group id / `@username`.
- **Silent send** (optional): deliver without a notification sound.

### 7.4 Email

Email uses WordPress' `wp_mail()` and has its **own subject + body template** that applies **to all actions**.

- **Enable Email** ✔
- **Recipients (admin users)**: tick administrator accounts (shown as `username` + email).
- **Other emails (optional)**: a comma‑separated list (sent in addition to the selected admins).
- **Subject**: supports placeholders, e.g. `Notification: {action}`.
- **Content**: rich editor (Visual/Code + Add Media), supports all placeholders from [section 6](#6-message-template--placeholders).

> If your site can't send mail yet, `wp_mail()` may fail — install an SMTP plugin (e.g. *WP Mail SMTP*). Errors appear in the **Error** column of the Log.

---

## 8. Send test & view Log

- **Send test**: click **Send test message** — the plugin fires a sample message to **all enabled & valid channels**. The result (channels succeeded/failed) shows in the notice at the top.
  > Remember to **Save settings** before testing.
- **Recent send log**: a 50‑row table with Time, Hook, Channel, User, Status (OK/Error), and the error text. Use **Clear all log** to wipe it.

---

## 9. Security — what you should know

- **Secrets are masked**: Webhook URLs (Slack/Discord) and the Bot Token (Telegram) are **never** printed back to the browser. The input is blank with a hint of the last few characters. **Leaving it blank on save keeps the current value**; type a new value to replace it.
- **No password leakage**: sensitive arguments of hooks like `password_reset` are always masked as `***`.
- **Mention‑injection protection**: Slack output is escaped (`< > &`) so nobody can inject `@channel`; Discord disables all automatic mentions.
- **Trustworthy IP**: by default taken from `REMOTE_ADDR` (proxy headers, which are easily spoofed, are not trusted). For sites behind a CDN, see [section 11](#11-for-developers).
- **Clean uninstall**: settings + the log table are fully removed on uninstall.

---

## 10. Troubleshooting

| Symptom | Common cause & fix |
|---------|--------------------|
| No notifications arrive | Master **Overview** switch off; no action ticked; no channel enabled/configured; not **Saved**. |
| "No channel is ready" on test | No channel enabled, or webhook/token empty/invalid. |
| Slack returns an HTTP error | Webhook revoked/wrong. Recreate the Incoming Webhook. |
| Telegram "chat not found" | Wrong Chat ID, or the bot isn't in the group/channel. |
| Email never arrives | `wp_mail()` failed — configure SMTP. Check the **Error** column in the Log. |
| Too many notifications | Untick some actions; avoid hooks that fire constantly. |
| Slow page on user actions | The target endpoint is slow (each send waits up to ~8s). Consider fewer channels. |

---

## 11. For developers

The plugin is extended via **filters** — no core edits required.

**Add a new channel** (e.g. WhatsApp, X, Mastodon, Microsoft Teams):

```php
add_filter( 'tvn_notify_hub_channels', function ( $channels ) {
    // My_Channel implements Tvn_Notify_Channel_Interface
    $channels[] = new My_Channel();
    return $channels;
} );
```

The interface requires: `get_id()`, `get_label()`, `get_defaults()`, `is_ready($cfg)`, `render_fields($cfg, $name_prefix)`, `sanitize($input)`, `send($text, $event, $cfg)`.

**Add an action to the built‑in list:**

```php
add_filter( 'tvn_notify_hub_preset_hooks', function ( $presets ) {
    $presets['WooCommerce']['woocommerce_new_order'] = array(
        'label' => 'New order',
        'args'  => 1,
    );
    return $presets;
} );
```

**Inspect / block an event before sending** (return `false` to skip):

```php
add_filter( 'tvn_notify_hub_event', function ( $event, $hook, $args ) {
    if ( 'administrator' === $event['role'] ) {
        return false; // Don't notify on admin actions.
    }
    return $event;
}, 10, 3 );
```

**Declare extra sensitive arguments to mask:**

```php
add_filter( 'tvn_notify_hub_sensitive_args', function ( $map, $hook ) {
    $map['my_custom_hook'] = array( 2 ); // mask arg at index 2
    return $map;
}, 10, 2 );
```

**Resolve the real IP behind a proxy/CDN (Cloudflare...):**

```php
add_filter( 'tvn_notify_hub_client_ip', function ( $ip ) {
    if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
        return $_SERVER['HTTP_CF_CONNECTING_IP'];
    }
    return $ip;
} );
```
> Only trust proxy headers when you actually control the proxy layer.

---

## 12. FAQ

**Can one action notify several channels at once?**
Yes. Every enabled and valid channel receives the notification for the same event.

**How is Email different from chat channels?**
Email has its **own HTML subject + body** (one template shared by all actions), while Slack/Discord/Telegram share the **Message template** from section 6.

**Do I need a GitHub/website to use it?**
No. The plugin runs standalone; you only need the webhook/token of the service you want to send to.

**What data leaves my site?**
When you enable a chat channel, event data (action, user info, IP, time, site, summary) is sent to the webhook/bot you configure. See the **External services** section in `readme.txt`.

**How do I pause notifications temporarily?**
Untick the master switch in the **Overview** block and Save — everything stops, settings are preserved.

---

## 13. Languages

The plugin's source language is **English** and it ships with ready‑to‑use translations:

| Language | Locale |
|----------|--------|
| Vietnamese | `vi` |
| German | `de_DE` |
| French | `fr_FR` |
| Spanish | `es_ES` |
| Italian | `it_IT` |
| Portuguese (Brazil) | `pt_BR` |
| Russian | `ru_RU` |
| Simplified Chinese | `zh_CN` |
| Japanese | `ja` |
| Korean | `ko_KR` |
| Dutch | `nl_NL` |
| Indonesian | `id_ID` |

The interface follows your **Site Language** (Settings → General). To add a new language:

1. Open `languages/tvn-notify-hub.pot` in a tool like **Poedit** (or copy an existing `.po`).
2. Translate the strings and save as `tvn-notify-hub-{locale}.po` (e.g. `tvn-notify-hub-ja.po`).
3. Export/compile to `.mo` and place both files in the plugin's `languages/` folder.

Contributions via translate.wordpress.org are also welcome once the plugin is published.
