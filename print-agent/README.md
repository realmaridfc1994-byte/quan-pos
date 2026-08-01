# Print Agent — Ứng dụng in ấn cho POS Quán Nhậu

Print Agent chạy trên máy quầy (Windows hoặc Linux) trong mạng nội bộ của quán. Nó nhận công việc in từ hàng đợi Laravel và gửi đến máy in nhiệt qua cổng USB.

## Tại sao cần Print Agent?

- **Không dùng internet**: Mất mạng thì bếp vẫn nhận được tem. Bếp không phụ thuộc vào một máy chủ phía ngoài.
- **In ngay lập tức**: Công việc in được xử lý trên máy quầy, không chờ API external.
- **Đơn giản**: Chỉ là một Node.js script nhỏ, dễ cài và dễ debug.

## Chuẩn bị

### 1. Chuẩn bị máy in nhiệt

Máy in ESC/POS 80mm (ví dụ: QPRINTER-80, Xprinter XP-58IIH, Sewoo LK-T23).

**Kết nối**:
- **USB**: Cắm trực tiếp vào máy quầy (Windows/Linux tự nhận driver)
- **Network (Ethernet/WiFi)**: Cấu hình IP, sau đó test bằng `nc` hoặc socat

### 2. Tìm mã định danh máy in

**Trên Windows**:
1. Mở Device Manager → Universal Serial Bus devices
2. Tìm máy in → Properties → Details → Hardware Ids
3. Tìm các số `VID_xxxx` và `PID_xxxx`
4. Ghi thành hex: `VID_04B8` → `0x04b8`

**Trên Linux**:
```bash
lsusb
# Output: Bus 001 Device 003: ID 04b8:0202 Seiko Epson Corp. Receipt Printer
#                             ↑ VID  ↑ PID

dmesg | grep -i usb  # Kiểm tra log kết nối
```

### 3. Cài đặt Node.js

- **Windows**: Tải từ https://nodejs.org, version LTS
- **Linux**: `sudo apt-get install nodejs npm`

Kiểm tra:
```bash
node --version  # v18.0.0 hoặc cao hơn
npm --version
```

## Cài đặt Print Agent

### 1. Chép file cấu hình

```bash
cd print-agent
cp .env.example .env
```

### 2. Chỉnh sửa `.env` với mã máy in

```env
PRINTER_VENDOR_ID=0x04b8
PRINTER_PRODUCT_ID=0x0202
```

### 3. Cài dependencies

```bash
npm install
```

## Chạy Print Agent

### Cách 1: Chạy trực tiếp

```bash
npm start
# hoặc
node index.js
```

Khi chạy, sẽ thấy:
```
[2026-08-01T10:23:45.123Z] 🚀 Print Agent khởi động
[2026-08-01T10:23:45.124Z] 📁 Queue dir: ./queue
[2026-08-01T10:23:45.125Z] ✅ Agent sẵn sàng. Chờ công việc in...
```

### Cách 2: Chạy nền trên Windows

Dùng Task Scheduler:
1. Mở Task Scheduler
2. Create Basic Task → tên "Print Agent"
3. Trigger: "At startup"
4. Action: Program = `C:\Program Files\nodejs\node.exe`, Arguments = `C:\path\to\print-agent\index.js`
5. Finish, và set "Run with highest privileges"

### Cách 3: Chạy nền trên Linux

Tạo systemd service:

```bash
sudo cat > /etc/systemd/system/pos-print-agent.service <<'EOF'
[Unit]
Description=POS Print Agent
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/pos-quan/print-agent
ExecStart=/usr/bin/node /var/www/pos-quan/print-agent/index.js
Restart=on-failure
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF

sudo systemctl enable pos-print-agent
sudo systemctl start pos-print-agent
sudo systemctl status pos-print-agent
```

## Cách hoạt động

1. **Laravel sinh ESC/POS**: Controller gọi `KitchenSlipTemplate::render()` → binary ESC/POS
2. **Lưu vào queue**: Binary lưu vào `storage/app/print-queue/kitchen-2024-08-01-001.bin`
3. **Print Agent quét**: Mỗi 1 giây, kiểm tra thư mục queue
4. **In ngay lập tức**: Đọc file .bin, gửi qua USB đến máy in
5. **Xoá file**: Sau khi in thành công, xoá file khỏi queue

## Log

Print Agent ghi log vào `print-agent/logs/print-agent.log`:

```
[2026-08-01T10:23:50.456Z] ⏳ In: kitchen-2024-08-01-001.bin
[2026-08-01T10:23:51.123Z] ✅ Gửi dữ liệu thành công
[2026-08-01T10:23:51.456Z] ✅ Đã in: kitchen-2024-08-01-001.bin
```

Kiểm tra log:
```bash
tail -f print-agent/logs/print-agent.log
```

## Troubleshoot

### Máy in không được tìm thấy

```
❌ Không tìm thấy máy in (VID:0x04b8 PID:0x0202)
```

**Kiểm tra**:
1. Máy in đã cắm USB chưa?
2. Mã VID/PID có đúng không? (Xem phần "Tìm mã định danh")
3. Driver đã cài chưa? (Windows cần Seiko Epson driver hoặc WinUSB)

### In không hoạt động, file vẫn trong queue

Kiểm tra log:
```bash
tail -50 print-agent/logs/print-agent.log
```

Nếu thấy:
- `⚠️ Không thể in, để lại trong queue` → Máy in mất kết nối, kiểm tra USB
- `❌ Lỗi máy in` → Driver hoặc cấu hình sai

### Máy in in rác

- Mã ESC/POS sinh sai → Kiểm tra lại template PHP
- Hoặc reset máy in: tắt, chờ 10 giây, bật lại

## Test

Trước khi in trên máy thật, test bằng Laravel:

```bash
php artisan pos:print-test
```

Lệnh này sinh 4 mẫu ESC/POS vào `storage/app/print-test/`:
- `kitchen-slip.bin` — tem bếp
- `provisional-bill.bin` — tạm tính
- `final-bill-cash.bin` — hoá đơn (tiền mặt)
- `final-bill-transfer.bin` — hoá đơn (chuyển khoản)

Sau đó, copy các file `.bin` vào thư mục queue để Print Agent in:

```bash
cp storage/app/print-test/*.bin print-agent/queue/
```

Xem log:
```bash
tail -f print-agent/logs/print-agent.log
```

Nếu in ra giấy → tất cả OK! 🎉

## Tắt Print Agent

```bash
# Nếu chạy trực tiếp: Ctrl+C

# Nếu chạy nền (Linux):
sudo systemctl stop pos-print-agent

# Nếu chạy nền (Windows): Mở Task Manager, tìm node.exe, End Task
```
