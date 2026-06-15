# TVN Notify Hub — Hướng dẫn sử dụng chi tiết

Plugin gửi thông báo (ping) tới **Slack, Discord, Telegram, Email** mỗi khi người dùng thực hiện một action/hook mà Admin chọn — bao gồm cả hook do **plugin khác** đăng ký (WooCommerce, Contact Form 7, Awesome Support...).

Kiến trúc **đa kênh**: một sự kiện có thể được gửi đồng thời tới nhiều kênh đang bật.

---

## Mục lục

1. [Cài đặt](#1-cài-đặt)
2. [Tổng quan trang cấu hình](#2-tổng-quan-trang-cấu-hình)
3. [Chọn action cần thông báo](#3-chọn-action-cần-thông-báo)
4. [Bắt hook của plugin khác (Hook tùy chỉnh)](#4-bắt-hook-của-plugin-khác-hook-tùy-chỉnh)
5. [Tùy chọn chung](#5-tùy-chọn-chung)
6. [Mẫu tin nhắn & placeholder](#6-mẫu-tin-nhắn--placeholder)
7. [Cấu hình từng kênh](#7-cấu-hình-từng-kênh)
   - [Slack](#71-slack)
   - [Discord](#72-discord)
   - [Telegram](#73-telegram)
   - [Email](#74-email)
8. [Gửi thử & xem Nhật ký](#8-gửi-thử--xem-nhật-ký)
9. [Bảo mật — những điều nên biết](#9-bảo-mật--những-điều-nên-biết)
10. [Khắc phục sự cố](#10-khắc-phục-sự-cố)
11. [Dành cho lập trình viên](#11-dành-cho-lập-trình-viên)
12. [Câu hỏi thường gặp](#12-câu-hỏi-thường-gặp)

---

## 1. Cài đặt

1. Chép thư mục `tvn-notify-hub` vào `wp-content/plugins/` (hoặc vào **Plugins → Add New → Upload Plugin** rồi tải file ZIP).
2. Vào **Plugins**, kích hoạt **TVN Notify Hub**.
3. Một menu **Notify Hub** (biểu tượng loa 📣) xuất hiện ở thanh bên trái admin.

> Yêu cầu: WordPress 5.3+, PHP 7.2+.

---

## 2. Tổng quan trang cấu hình

Mở **Notify Hub**. Trang gồm các khối theo thứ tự:

| Khối | Chức năng |
|------|-----------|
| **Tổng quan** | Công tắc tổng (bật/tắt toàn bộ plugin) |
| **Active Notifications** | Tick chọn các action có sẵn để được thông báo |
| **Hook tùy chỉnh** | Thêm hook bất kỳ (kể cả của plugin khác) |
| **Tùy chọn** | Chỉ user đăng nhập / đính kèm tham số / ghi log |
| **Mẫu tin nhắn** | Nội dung gửi tới các kênh **chat** (Slack/Discord/Telegram) |
| **Kênh gửi** | Cấu hình riêng Slack, Discord, Telegram, Email |
| **Gửi thử** | Bắn 1 tin thử tới mọi kênh đang bật |
| **Nhật ký gửi gần đây** | Lịch sử 50 lần gửi gần nhất + lỗi (nếu có) |

> Nhớ bấm **Lưu cấu hình** sau khi chỉnh, rồi mới dùng **Gửi thử**.

---

## 3. Chọn action cần thông báo

Trong khối **Active Notifications**, tick các action muốn nhận thông báo. Chúng được gom 3 nhóm:

**Người dùng**
- Đăng nhập thành công (`wp_login`)
- Đăng xuất (`wp_logout`)
- Đăng nhập thất bại (`wp_login_failed`)
- Đăng ký tài khoản mới (`user_register`)
- Cập nhật hồ sơ (`profile_update`)
- Xóa người dùng (`delete_user`)
- Đặt lại mật khẩu (`password_reset`)

**Nội dung**
- Bài viết đổi trạng thái — đăng/nháp/chờ duyệt... (`transition_post_status`)
- Đưa bài vào thùng rác (`wp_trash_post`)
- Xóa vĩnh viễn bài (`before_delete_post`)
- Bình luận mới (`comment_post`)
- Tải tệp lên thư viện (`add_attachment`)

**Hệ thống**
- Kích hoạt plugin (`activated_plugin`)
- Vô hiệu hóa plugin (`deactivated_plugin`)
- Đổi giao diện (`switch_theme`)

> **Lưu ý:** `transition_post_status` chỉ gửi khi trạng thái **thực sự thay đổi** (không spam mỗi lần lưu bài cùng trạng thái).

---

## 4. Bắt hook của plugin khác (Hook tùy chỉnh)

Vì hook trong WordPress là **toàn cục**, bạn có thể lắng nghe hook do bất kỳ plugin nào đăng ký.

Trong ô **Hook tùy chỉnh**, mỗi dòng một hook:

```
# Mỗi dòng một hook. Dòng bắt đầu bằng # là ghi chú.
woocommerce_order_status_completed|1
wpcf7_mail_sent
wpas_open_ticket|2
```

Cú pháp: `tên_hook` hoặc `tên_hook|số_tham_số`.

- `số_tham_số` (tùy chọn) = số đối số mà hook truyền sang. Nếu không ghi, mặc định là `1`.
- Đặt đúng số tham số giúp phần **Chi tiết** của thông báo hiển thị đủ dữ liệu (vd `comment_post` truyền 3 tham số).

**Làm sao biết tên hook & số tham số?**
- Tìm trong code plugin đó dòng `do_action( 'ten_hook', $arg1, $arg2, ... )`.
- Hoặc tra tài liệu plugin / dùng plugin như *Query Monitor*, *Simply Show Hooks*.

---

## 5. Tùy chọn chung

Trong khối **Tùy chọn**:

- **Chỉ thông báo khi người thực hiện đã đăng nhập** — bỏ qua hành động của khách vãng lai.
- **Đính kèm tham số kỹ thuật của hook** — thêm các đối số thô của hook vào tin (hữu ích để *debug* khi viết hook tùy chỉnh).
  > Tham số nhạy cảm (vd mật khẩu mới ở `password_reset`) **luôn bị che** thành `***`, không bao giờ bị gửi đi.
- **Ghi log mỗi lần gửi** — lưu lịch sử để theo dõi/khắc phục lỗi.

---

## 6. Mẫu tin nhắn & placeholder

Khối **Mẫu tin nhắn** quy định nội dung gửi tới **các kênh chat** (Slack, Discord, Telegram). Email dùng **mẫu riêng** (xem [phần Email](#74-email)).

Các placeholder có thể dùng (áp dụng cho cả mẫu chat lẫn email):

| Placeholder | Ý nghĩa |
|-------------|---------|
| `{action}` | Tên action dễ đọc (vd "Đăng nhập thành công") |
| `{hook}` | Tên hook kỹ thuật (vd `wp_login`) |
| `{user}` | Tên hiển thị của người dùng |
| `{user_login}` | Tên đăng nhập |
| `{user_email}` | Email người dùng |
| `{role}` | Vai trò (role) |
| `{ip}` | Địa chỉ IP của request |
| `{time}` | Thời gian (theo múi giờ site) |
| `{summary}` | Mô tả ngắn về sự kiện (tự sinh theo hook) |
| `{site}` | Tên site + URL |
| `{url}` | URL của request |

Mẫu mặc định:

```
:bell: *{action}*
• Người dùng: *{user}* ({user_login}) – {role}
• Thời gian: {time}
• IP: {ip}
• Chi tiết: {summary}
• Website: {site}
```

> Định dạng `*đậm*` và `:emoji:` hiển thị tốt trên Slack/Discord. Telegram tự chuyển `:bell:` → 🔔 và xử lý đậm; nếu định dạng lỗi, plugin tự gửi lại dạng văn bản thuần để không mất thông báo.

---

## 7. Cấu hình từng kênh

Bật và điền thông tin cho ít nhất một kênh, rồi **Lưu cấu hình**.

### 7.1 Slack

**Lấy Webhook URL:**
1. Truy cập https://api.slack.com/apps → **Create New App** (hoặc dùng app sẵn có).
2. Vào **Incoming Webhooks** → bật **Activate Incoming Webhooks**.
3. **Add New Webhook to Workspace** → chọn channel → **Allow**.
4. Copy URL dạng `https://hooks.slack.com/services/T000/B000/XXXX`.

**Trong plugin:**
- **Bật Slack** ✔
- **Webhook URL**: dán URL vừa copy.
- **Bot Name**: tên hiển thị trong Slack (vd `WordPress`).
- **Bot Emoji**: vd `:zap:` (bỏ qua nếu đã đặt Bot Custom Icon).
- **Bot Custom Icon**: chọn ảnh từ Thư viện (ưu tiên hơn emoji).
- **Channel (tùy chọn)**: ghi đè channel mặc định của webhook, vd `#general` hoặc `@user`.

### 7.2 Discord

**Lấy Webhook URL:**
1. Mở server Discord → **Server Settings → Integrations → Webhooks**.
2. **New Webhook** → chọn channel → **Copy Webhook URL**.

**Trong plugin:**
- **Bật Discord** ✔
- **Webhook URL**: dán URL (`https://discord.com/api/webhooks/...`).
- **Bot Name**: tên hiển thị.
- **Bot Custom Icon**: ảnh avatar (Discord không dùng emoji làm icon).

### 7.3 Telegram

**Tạo bot & lấy Token:**
1. Trên Telegram, chat với **@BotFather** → gửi `/newbot` → đặt tên → nhận **token** dạng `123456789:AA...`.

**Lấy Chat ID:**
- **Cá nhân/nhóm:** thêm bot vào nhóm, gửi 1 tin bất kỳ, rồi mở:
  `https://api.telegram.org/bot<TOKEN>/getUpdates` → tìm `"chat":{"id":...}`.
- **Channel:** thêm bot làm **admin** của channel, dùng `@username_channel` làm Chat ID.
- ID nhóm thường là **số âm** (vd `-1001234567890`).

**Trong plugin:**
- **Bật Telegram** ✔
- **Bot Token**: dán token.
- **Chat ID**: id người dùng / id nhóm / `@username`.
- **Gửi im lặng** (tùy chọn): gửi không kèm âm báo.

### 7.4 Email

Email dùng `wp_mail()` của WordPress và có **mẫu riêng (tiêu đề + nội dung)** áp dụng **chung cho mọi action**.

- **Bật Email** ✔
- **Người nhận (admin users)**: tick chọn các tài khoản administrator (hiển thị `username` + email).
- **Email khác (tùy chọn)**: danh sách email phân tách bằng dấu phẩy (gửi thêm ngoài admin đã chọn).
- **Tiêu đề**: hỗ trợ placeholder, vd `Thông báo: {action}`.
- **Nội dung**: trình soạn thảo (Visual/Code + Add Media), dùng được mọi placeholder ở [mục 6](#6-mẫu-tin-nhắn--placeholder).

> Nếu site chưa cấu hình gửi mail, `wp_mail()` có thể thất bại — hãy cài plugin SMTP (vd *WP Mail SMTP*). Lỗi sẽ hiện trong cột **Lỗi** của Nhật ký.

---

## 8. Gửi thử & xem Nhật ký

- **Gửi thử**: bấm **Gửi tin nhắn thử** — plugin bắn 1 tin mẫu tới **mọi kênh đang bật & hợp lệ**. Kết quả (số kênh thành công/lỗi) hiện ở thông báo phía trên.
  > Nhớ **Lưu cấu hình** trước khi gửi thử.
- **Nhật ký gửi gần đây**: bảng 50 dòng gần nhất gồm Thời điểm, Hook, Kênh, User, Trạng thái (OK/Lỗi), và nội dung lỗi. Dùng **Xóa toàn bộ log** để dọn.

---

## 9. Bảo mật — những điều nên biết

- **Secret được che**: Webhook URL (Slack/Discord) và Bot Token (Telegram) **không bao giờ** in lại ra trình duyệt. Ô nhập để trống + gợi ý vài ký tự cuối. **Để trống khi lưu = giữ nguyên** giá trị cũ; gõ giá trị mới để thay thế.
- **Không rò mật khẩu**: tham số nhạy cảm của các hook như `password_reset` luôn bị che `***`.
- **Chống mention injection**: nội dung gửi Slack được escape (`< > &`) để không ai chèn được `@channel`; Discord tắt toàn bộ mention tự động.
- **IP đáng tin**: mặc định lấy từ `REMOTE_ADDR` (không tin header proxy dễ giả mạo). Site sau CDN xem [mục 11](#11-dành-cho-lập-trình-viên).
- **Gỡ sạch khi xóa plugin**: cấu hình + bảng log bị xóa hoàn toàn khi uninstall.

---

## 10. Khắc phục sự cố

| Triệu chứng | Nguyên nhân thường gặp & cách xử lý |
|-------------|--------------------------------------|
| Không nhận được thông báo | Chưa bật công tắc **Tổng quan**; chưa tick action; chưa bật/cấu hình kênh; chưa **Lưu**. |
| "Không có kênh nào sẵn sàng" khi gửi thử | Chưa bật kênh nào, hoặc webhook/token trống/sai. |
| Slack báo lỗi HTTP | Webhook bị thu hồi/sai. Tạo lại Incoming Webhook. |
| Telegram lỗi "chat not found" | Sai Chat ID, hoặc bot chưa được thêm vào nhóm/channel. |
| Email không tới | `wp_mail()` thất bại — cấu hình SMTP. Kiểm tra cột **Lỗi** trong Nhật ký. |
| Bị thông báo quá nhiều | Bỏ bớt action; tránh các hook nổ liên tục. |
| Trang chậm khi user thao tác | Webhook đích phản hồi chậm (mỗi lần gửi chờ tối đa ~8s). Cân nhắc giảm số kênh. |

---

## 11. Dành cho lập trình viên

Plugin mở rộng qua **filter**, không cần sửa lõi.

**Thêm một kênh mới** (vd WhatsApp, X, Mastodon, Microsoft Teams):

```php
add_filter( 'tvn_notify_hub_channels', function ( $channels ) {
    // My_Channel implements Tvn_Notify_Channel_Interface
    $channels[] = new My_Channel();
    return $channels;
} );
```

Interface yêu cầu: `get_id()`, `get_label()`, `get_defaults()`, `is_ready($cfg)`, `render_fields($cfg, $name_prefix)`, `sanitize($input)`, `send($text, $event, $cfg)`.

**Bổ sung action vào danh sách có sẵn:**

```php
add_filter( 'tvn_notify_hub_preset_hooks', function ( $presets ) {
    $presets['WooCommerce']['woocommerce_new_order'] = array(
        'label' => 'Đơn hàng mới',
        'args'  => 1,
    );
    return $presets;
} );
```

**Can thiệp / chặn một sự kiện trước khi gửi** (trả `false` để bỏ qua):

```php
add_filter( 'tvn_notify_hub_event', function ( $event, $hook, $args ) {
    if ( 'administrator' === $event['role'] ) {
        return false; // Không thông báo hành động của admin.
    }
    return $event;
}, 10, 3 );
```

**Khai báo thêm tham số nhạy cảm cần che:**

```php
add_filter( 'tvn_notify_hub_sensitive_args', function ( $map, $hook ) {
    $map['my_custom_hook'] = array( 2 ); // che tham số index 2
    return $map;
}, 10, 2 );
```

**Lấy IP thật khi đứng sau proxy/CDN (Cloudflare...):**

```php
add_filter( 'tvn_notify_hub_client_ip', function ( $ip ) {
    if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
        return $_SERVER['HTTP_CF_CONNECTING_IP'];
    }
    return $ip;
} );
```
> Chỉ tin header proxy khi bạn thực sự kiểm soát tầng proxy.

---

## 12. Câu hỏi thường gặp

**Một action gửi được nhiều kênh cùng lúc không?**
Có. Mọi kênh đang bật & hợp lệ đều nhận thông báo cho cùng một sự kiện.

**Email khác gì các kênh chat?**
Email có **tiêu đề + nội dung HTML riêng** (mẫu dùng chung cho mọi action), còn Slack/Discord/Telegram dùng chung **Mẫu tin nhắn** ở mục 6.

**Có cần GitHub/website để dùng không?**
Không. Plugin chạy độc lập; chỉ cần webhook/token của dịch vụ bạn muốn gửi tới.

**Dữ liệu gì được gửi ra ngoài?**
Khi bạn bật một kênh chat, dữ liệu sự kiện (action, thông tin user, IP, thời gian, site, mô tả) được gửi tới webhook/bot bạn cấu hình. Xem mục **External services** trong `readme.txt`.

**Tắt thông báo tạm thời?**
Bỏ tick công tắc ở khối **Tổng quan** rồi Lưu — toàn bộ ngừng gửi, cấu hình vẫn giữ.

---

## 13. Đa ngôn ngữ

Ngôn ngữ gốc của plugin là **tiếng Anh**, kèm sẵn bản dịch:

| Ngôn ngữ | Mã (locale) |
|----------|-------------|
| Tiếng Việt | `vi` |
| Tiếng Đức | `de_DE` |
| Tiếng Pháp | `fr_FR` |
| Tiếng Tây Ban Nha | `es_ES` |
| Tiếng Ý | `it_IT` |
| Tiếng Bồ Đào Nha (Brazil) | `pt_BR` |
| Tiếng Nga | `ru_RU` |
| Tiếng Trung (giản thể) | `zh_CN` |
| Tiếng Nhật | `ja` |
| Tiếng Hàn | `ko_KR` |
| Tiếng Hà Lan | `nl_NL` |
| Tiếng Indonesia | `id_ID` |

Giao diện tự hiển thị theo **Ngôn ngữ site** (Settings → General → Site Language). Để thêm ngôn ngữ mới:

1. Mở `languages/tvn-notify-hub.pot` bằng **Poedit** (hoặc copy một file `.po` có sẵn).
2. Dịch và lưu thành `tvn-notify-hub-{locale}.po` (vd `tvn-notify-hub-ja.po`).
3. Xuất/biên dịch ra `.mo`, đặt cả `.po` và `.mo` vào thư mục `languages/` của plugin.
