#!/usr/bin/env node

/**
 * Print Agent — chạy trên máy quầy (Windows/Linux) trong mạng LAN của quán.
 *
 * Nhận công việc in từ hàng đợi Laravel (file .bin trong thư mục queue),
 * gửi đến máy in nhiệt qua USB hoặc mạng.
 *
 * Cách chạy:
 *   npm install
 *   npm start
 *
 * Hoặc chạy nền trên Windows (Task Scheduler) hoặc Linux (systemd).
 */

require('dotenv').config();
const fs = require('fs');
const path = require('path');
const usb = require('usb');

const QUEUE_DIR = process.env.QUEUE_DIR || './queue';
const PRINTER_VENDOR_ID = parseInt(process.env.PRINTER_VENDOR_ID || '0x04b8', 16);
const PRINTER_PRODUCT_ID = parseInt(process.env.PRINTER_PRODUCT_ID || '0x0202', 16);
const POLL_INTERVAL = parseInt(process.env.POLL_INTERVAL || '1000');
const LOG_DIR = process.env.LOG_DIR || './logs';

// Tạo thư mục nếu chưa tồn tại
[QUEUE_DIR, LOG_DIR].forEach(dir => {
    if (!fs.existsSync(dir)) {
        fs.mkdirSync(dir, { recursive: true });
    }
});

function log(msg) {
    const timestamp = new Date().toISOString();
    const line = `[${timestamp}] ${msg}`;
    console.log(line);
    fs.appendFileSync(path.join(LOG_DIR, 'print-agent.log'), line + '\n');
}

function findPrinter() {
    const devices = usb.getDeviceList();
    return devices.find(dev => dev.deviceDescriptor.idVendor === PRINTER_VENDOR_ID
        && dev.deviceDescriptor.idProduct === PRINTER_PRODUCT_ID);
}

function sendToPrinter(data) {
    try {
        const device = findPrinter();
        if (!device) {
            log('❌ Không tìm thấy máy in (VID:0x' + PRINTER_VENDOR_ID.toString(16) + ' PID:0x' + PRINTER_PRODUCT_ID.toString(16) + ')');
            return false;
        }

        device.open();
        const iface = device.interface(0);
        iface.claim();

        // Tìm endpoint gửi (OUT)
        const outEndpoint = iface.endpoints.find(ep => !ep.direction || ep.direction === 'out');
        if (!outEndpoint) {
            log('❌ Không tìm thấy endpoint gửi dữ liệu');
            device.close();
            return false;
        }

        outEndpoint.transfer(data, (err) => {
            if (err) {
                log('❌ Lỗi gửi dữ liệu: ' + err.message);
                device.close();
                return;
            }
            log('✅ Gửi dữ liệu thành công');
            setTimeout(() => device.close(), 500);
        });

        return true;
    } catch (err) {
        log('❌ Lỗi máy in: ' + err.message);
        return false;
    }
}

function processPrintJobs() {
    try {
        if (!fs.existsSync(QUEUE_DIR)) {
            return;
        }

        const files = fs.readdirSync(QUEUE_DIR).filter(f => f.endsWith('.bin'));

        files.forEach(file => {
            const filePath = path.join(QUEUE_DIR, file);
            try {
                const data = fs.readFileSync(filePath);
                log(`⏳ In: ${file}`);

                if (sendToPrinter(data)) {
                    fs.unlinkSync(filePath);
                    log(`✅ Đã in: ${file}`);
                } else {
                    log(`⚠️ Không thể in ${file}, để lại trong queue để thử lại`);
                }
            } catch (err) {
                log(`❌ Lỗi xử lý ${file}: ${err.message}`);
            }
        });
    } catch (err) {
        log(`❌ Lỗi quét queue: ${err.message}`);
    }
}

function main() {
    log('🚀 Print Agent khởi động');
    log(`📁 Queue dir: ${QUEUE_DIR}`);
    log(`🖨️  Máy in: VID=0x${PRINTER_VENDOR_ID.toString(16)} PID=0x${PRINTER_PRODUCT_ID.toString(16)}`);
    log(`⏱️  Polling interval: ${POLL_INTERVAL}ms`);

    // Chạy liên tục
    setInterval(() => {
        processPrintJobs();
    }, POLL_INTERVAL);

    log('✅ Agent sẵn sàng. Chờ công việc in...');
}

main();
