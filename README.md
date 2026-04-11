### Hướng dẫn kết nối API

#### Bước 1. Đăng ký và thiết lập API TrumThe.vn

1. Truy cập vào [trumthe.vn](https://trumthe.vn/) và đăng nhập hoặc đăng ký tài khoản
2. Vào phần "KẾT NỐI API" trong menu chính
3. Điền các thông tin cấu hình:
   - **Loại API**: Chọn "Đổi thẻ cào"
   - **Chọn ví giao dịch**: (Ví của bạn)
   - **Kiểu**: GET hoặc POST (Plugin hỗ trợ cả hai, khuyên dùng POST)
   - **Đường dẫn nhận dữ liệu (Callback URL)**: Nhập URL máy chủ của bạn, ví dụ: `https://test.pmmp.io/trumthe/callback`
   - **Địa chỉ IP (không bắt buộc)**: Địa chỉ IP của máy chủ (nếu có)

> **Lưu ý về Callback URL**: Mặc dù plugin này không yêu cầu Callback URL để hoạt động, việc điền thông tin này giúp TrumThe xác định nguồn gốc giao dịch hợp lệ. Điều này sẽ giúp bảo vệ tài khoản của bạn trong trường hợp có nghi vấn về thẻ không rõ nguồn gốc, thẻ lừa đảo hoặc thẻ bị đánh cắp. Đối với máy chủ Minecraft, bạn có thể giải thích với admin TrumThe rằng đây là IP của server game, không phải website thông thường nên không thể truy cập trực tiếp qua trình duyệt.

4. Sau khi đăng ký, bạn sẽ nhận được:
   - **Partner ID**: Được cấp bởi TrumThe
   - **Partner Key**: Chìa khóa bảo mật API

5. **Quan trọng**: Liên hệ với admin TrumThe để kích hoạt API từ trạng thái "Đang tắt" sang "Hoạt động"

#### Bước 2. Cấu hình plugin

1. Mở file `src/Constant.php`
2. Điền thông tin đã đăng ký trực tiếp trong mã nguồn:
```php
public const PARTNER_ID = "PARTNER_ID_CUA_BAN";
public const PARTNER_KEY = "PARTNER_KEY_CUA_BAN";
```
3. Plugin đã nhúng sẵn `FormAPI` trong `src/utils/FormAPI`, nên không cần cài thêm `pmforms` hoặc plugin form bên ngoài.

#### Bước 3. Thiết lập Callback URL (Tham khảo - Có thể bỏ qua)

Nếu bạn muốn thiết lập một callback URL thực sự hoạt động (tùy chọn), bạn cần:
- Có domain trỏ đến IP máy chủ
- Thiết lập reverse proxy (nginx, apache) tới cổng máy chủ
- Đảm bảo địa chỉ callback có thể truy cập từ internet

Mẫu cấu hình Nginx:
```nginx
location /trumthe/callback {
    proxy_pass http://localhost:PORT/trumthe/callback;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
}
```