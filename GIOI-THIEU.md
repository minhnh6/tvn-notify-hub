# TVN Notify Hub — Biết mọi chuyện xảy ra trên website của bạn, ngay lập tức

> Mỗi khi có người đăng ký tài khoản, một đơn hàng được hoàn tất, một bài viết lên sóng hay ai đó cố đăng nhập sai nhiều lần — bạn có muốn biết **ngay tức khắc** mà không phải ngồi canh trang quản trị?

**TVN Notify Hub** là plugin WordPress giúp bạn làm đúng điều đó: tự động gửi thông báo tới **Slack, Discord, Telegram và Email** mỗi khi người dùng thực hiện một hành động mà bạn quan tâm.

---

## Vấn đề

Website càng đông người dùng, càng nhiều việc diễn ra sau lưng bạn: thành viên mới, bình luận, đơn hàng, ticket hỗ trợ, lượt đăng nhập đáng ngờ... Đăng nhập wp-admin để kiểm tra liên tục vừa mất thời gian, vừa dễ bỏ sót những việc cần phản hồi nhanh.

Bạn cần một “người gác cổng” lặng lẽ: chuyện gì quan trọng xảy ra, nó nhắn cho bạn ngay — tại nơi bạn vốn đã làm việc mỗi ngày (Slack của team, nhóm Telegram, hộp thư...).

## Giải pháp

TVN Notify Hub lắng nghe các **hook** của WordPress và bắn thông báo tới kênh bạn chọn. Bạn quyết định **việc gì** đáng báo và **gửi tới đâu** — phần còn lại plugin lo.

---

## Tính năng nổi bật

### 🔔 Thông báo theo đúng hành động bạn chọn
Một danh sách action dựng sẵn, gom theo nhóm dễ hiểu — chỉ việc tick:
- **Người dùng:** đăng nhập, đăng xuất, **đăng nhập thất bại**, đăng ký mới, cập nhật hồ sơ, xóa tài khoản, đặt lại mật khẩu.
- **Nội dung:** bài viết đăng/đổi trạng thái, vào thùng rác, xóa vĩnh viễn, bình luận mới, tải tệp lên.
- **Hệ thống:** kích hoạt/vô hiệu hóa plugin, đổi giao diện.

### 🧩 Bắt được cả hook của plugin khác
Đây là điểm mạnh hiếm có: bạn không bị giới hạn trong danh sách dựng sẵn. Cần báo khi **WooCommerce** có đơn mới, **Contact Form 7** gửi form thành công, hay **Awesome Support** mở ticket? Chỉ cần dán tên hook vào ô tùy chỉnh:

```
woocommerce_order_status_completed|1
wpcf7_mail_sent
wpas_open_ticket|2
```

Vì hook trong WordPress là toàn cục, **mọi plugin** đều có thể được “theo dõi”.

### 📡 Đa kênh — gửi nhiều nơi cùng lúc
Một sự kiện có thể đồng thời bay tới nhiều kênh:
- **Slack** — Bot name, emoji, icon tùy chỉnh, chọn channel.
- **Discord** — webhook + avatar riêng.
- **Telegram** — gửi qua bot tới cá nhân, nhóm hoặc channel.
- **Email** — mẫu HTML soạn bằng trình editor quen thuộc, người nhận chọn từ danh sách admin của site.

### ✍️ Nội dung tin nhắn tùy biến hoàn toàn
Soạn mẫu tin theo ý bạn với hệ thống **placeholder**: `{action}`, `{user}`, `{user_email}`, `{role}`, `{ip}`, `{time}`, `{summary}`, `{site}`, `{url}`... Tự động điền theo từng sự kiện.

### 🧪 Gửi thử & Nhật ký
Một nút **Gửi thử** để kiểm tra cấu hình ngay, và bảng **Nhật ký** lưu 50 lần gửi gần nhất kèm trạng thái thành công/lỗi — biết chính xác điều gì đã xảy ra.

### 🌍 Hỗ trợ đa ngôn ngữ
Giao diện được quốc tế hóa hoàn chỉnh (i18n) với ngôn ngữ gốc là **tiếng Anh**, kèm sẵn **12 ngôn ngữ**:
**Tiếng Việt, Anh, Đức, Pháp, Tây Ban Nha, Ý, Bồ Đào Nha (Brazil), Nga, Trung (giản thể), Nhật, Hàn, Hà Lan và Indonesia.**
Plugin tự động hiển thị theo ngôn ngữ của site (Settings → General → Site Language). Đã kèm file `.pot` để bạn (hoặc cộng đồng) dễ dàng dịch thêm bất kỳ ngôn ngữ nào.

---

## Bảo mật đặt lên hàng đầu

Một plugin gửi dữ liệu ra ngoài bắt buộc phải an toàn. TVN Notify Hub được thiết kế kỹ:

- **Không bao giờ rò mật khẩu** — các tham số nhạy cảm (như mật khẩu mới khi reset) luôn bị che.
- **Che secret trong giao diện** — webhook URL và bot token không bị in lại ra trình duyệt.
- **Chống lạm dụng mention** — escape nội dung để không ai chèn được lệnh ping toàn kênh từ dữ liệu nhập vào.
- **IP đáng tin cậy**, escape toàn bộ đầu ra, kiểm tra quyền & nonce đầy đủ, dọn sạch dữ liệu khi gỡ cài đặt.

Plugin tuân thủ tiêu chuẩn của WordPress.org và vượt qua công cụ **Plugin Check**.

---

## Mở rộng không giới hạn (dành cho developer)

Kiến trúc tách lõi khỏi kênh gửi: thêm một dịch vụ mới (**WhatsApp, X, Mastodon, Microsoft Teams**, hay API nội bộ của bạn) chỉ cần viết một class và đăng ký qua filter — không phải đụng vào lõi:

```php
add_filter( 'tvn_notify_hub_channels', function ( $channels ) {
    $channels[] = new My_Channel(); // implements Tvn_Notify_Channel_Interface
    return $channels;
} );
```

Kèm theo các filter để thêm action, can thiệp sự kiện trước khi gửi, khai báo dữ liệu nhạy cảm và tùy biến cách lấy IP.

---

## Ai nên dùng?

- **Chủ website & quản trị viên** muốn nắm bắt hoạt động quan trọng theo thời gian thực.
- **Đội ngũ hỗ trợ / vận hành** muốn nhận ticket, đơn hàng, form ngay trong Slack/Telegram.
- **Cửa hàng WooCommerce** muốn báo đơn mới vào nhóm chat.
- **Quản trị an ninh** muốn theo dõi đăng nhập thất bại, thay đổi plugin/theme.
- **Lập trình viên** cần một “xương sống thông báo” đa kênh, dễ mở rộng cho dự án của mình.

---

## Bắt đầu trong 3 phút

1. Cài đặt & kích hoạt plugin.
2. Mở menu **Notify Hub**, tick các action cần theo dõi.
3. Bật một kênh (vd dán Webhook URL của Slack), lưu lại và bấm **Gửi thử**.

Xong — từ giờ website sẽ tự “báo cáo” cho bạn.

---

**TVN Notify Hub** — *Đặt một lần, yên tâm mãi: mọi hành động quan trọng đều đến tay bạn, ở đúng nơi bạn cần.*
