<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Support;

use App\Exceptions\GuestTokenException;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * Token phiên cho khách tự gọi món — Phase 4.
 *
 * ── VÌ SAO KHÔNG LƯU TOKEN VÀO DATABASE ───────────────────────────────────
 * Đường đổi mã bàn lấy token là đường KHÔNG CẦN ĐĂNG NHẬP — ai cũng gọi
 * được. Cho nó ghi database là tự mở cửa cho việc bơm rác: một máy bơm
 * 100.000 lượt quét là bảng đầy, và cái đầy đó nằm ngay trong database đang
 * chạy quán. Nên toàn bộ nội dung token nằm trong chính chuỗi token, mã hoá
 * bằng khoá của server (APP_KEY). Sửa một ký tự là giải mã hỏng.
 *
 * Đánh đổi đã cân nhắc: KHÔNG thu hồi được lẻ một token. Trong quán 15 bàn
 * không có nhu cầu đó — muốn cắt thì thu ngân đóng bàn, cả bàn chết token
 * ngay lập tức.
 *
 * ── KHÔNG LỘ table_session_id RA CLIENT ───────────────────────────────────
 * Id thật nằm trong phần đã mã hoá, client chỉ thấy một chuỗi vô nghĩa. Thứ
 * duy nhất client cầm được để đối chiếu là `maDoiChieu()` — 12 ký tự đầu của
 * dấu vân tay token, dùng khi khách kêu "máy tôi báo lỗi" để thu ngân tra,
 * KHÔNG dùng để xác thực và không suy ngược ra id nào.
 *
 * ── HAI CÁI CHẾT ĐỘC LẬP NHAU ─────────────────────────────────────────────
 * 1. Hết hạn giờ (mặc định 3 tiếng) — kiểm ở đây.
 * 2. Bàn đóng — KHÔNG kiểm được ở đây vì token không biết chuyện gì xảy ra
 *    sau khi nó được cấp; middleware đọc lại trạng thái lượt khách mỗi lần
 *    gọi. Xem App\Http\Middleware\EnsureGuestSessionToken.
 */
final class GuestSessionToken
{
    /** Nội dung bên trong token, sau khi giải mã. */
    public function __construct(
        public readonly int $tableSessionId,
        public readonly int $diningTableId,
        public readonly Carbon $hetHanLuc,
    ) {}

    public static function phatHanh(int $tableSessionId, int $diningTableId): string
    {
        $songBaoLau = (int) config('pos.khach_tu_goi.token_song_bao_lau_phut');

        return Crypt::encryptString(json_encode([
            's' => $tableSessionId,
            'b' => $diningTableId,
            'h' => Carbon::now()->addMinutes($songBaoLau)->getTimestamp(),
            // Hai khách cùng bàn quét cùng lúc phải ra hai token khác nhau,
            // nếu không thì mã đối chiếu trùng nhau và chặn gọi dồn dập tính
            // chung vào một rổ.
            'n' => Str::random(16),
        ], JSON_THROW_ON_ERROR));
    }

    /** @throws GuestTokenException */
    public static function doc(string $token): self
    {
        try {
            $noiDung = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            throw GuestTokenException::hong();
        }

        if (! is_array($noiDung)
            || ! isset($noiDung['s'], $noiDung['b'], $noiDung['h'])
            || ! is_int($noiDung['s']) || ! is_int($noiDung['b']) || ! is_int($noiDung['h'])) {
            throw GuestTokenException::hong();
        }

        $hetHan = Carbon::createFromTimestamp($noiDung['h']);

        if ($hetHan->isPast()) {
            throw GuestTokenException::hetHan();
        }

        return new self($noiDung['s'], $noiDung['b'], $hetHan);
    }

    /**
     * Mã đối chiếu cho khách đọc ra cho phục vụ nghe. KHÔNG phải mật khẩu,
     * không xác thực bằng nó, và không suy ngược ra id nào từ nó.
     */
    public static function maDoiChieu(string $token): string
    {
        return substr(hash('sha256', $token), 0, 12);
    }

    /** Khoá dùng để đếm số lần gọi — mỗi token một rổ riêng. */
    public static function khoaChanGoiDon(string $token): string
    {
        return 'guest:'.hash('sha256', $token);
    }

    public function conLaiGiay(): int
    {
        return max(0, $this->hetHanLuc->getTimestamp() - Carbon::now()->getTimestamp());
    }
}
