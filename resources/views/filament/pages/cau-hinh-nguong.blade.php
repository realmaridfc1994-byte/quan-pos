<x-filament-panels::page>
    <form wire:submit="luu">
        {{ $this->form }}

        <div class="mt-6">
            <x-filament::button type="submit">Lưu</x-filament::button>
        </div>
    </form>

    <x-filament::section collapsible collapsed>
        <x-slot name="heading">Vì sao lại là hai con số này</x-slot>

        <div class="prose prose-sm dark:prose-invert max-w-none">
            <p>
                Cả hai đều là <strong>điểm khởi đầu</strong>, không phải con số cuối cùng. Anh nhìn dữ liệu
                thật vài tuần rồi tự kéo lên hoặc kéo xuống.
            </p>
            <p>
                <strong>Lãi thấp 25%.</strong> Bia lãi khoảng 20-30%, món nấu lãi 50-70%. Nếu để ngưỡng 15%
                thì gần như không món nào rơi vào, mà một cảnh báo không bao giờ kêu là một cảnh báo vô dụng.
                25% cắt đúng vào nhóm đồ uống lãi mỏng — nhóm cần nhìn kỹ nhất.
            </p>
            <p>
                <strong>Hao hụt 200.000đ.</strong> Tính theo tiền chứ không theo số lượng: 5 lon bia và 5 con
                cua đều là "5" nhưng một bên vài chục nghìn, một bên vài triệu. Dưới mức này thu ngân tự ghi
                để nhân viên không ngại ghi — hao hụt <em>không</em> ghi còn tệ hơn hao hụt ghi mà không ai
                duyệt, vì nó biến thành chênh lệch kiểm kê không rõ nguyên nhân ba tháng sau.
            </p>
            <p>
                Ngưỡng chỉ chặn được từng lần lớn. Thứ bắt được kiểu "5 lon vỡ mỗi tối" là bảng
                <strong>Hao hụt theo người ghi</strong> trên trang Báo cáo — nhìn cột "số lần ghi" và
                "trung bình mỗi lần" cạnh nhau.
            </p>
        </div>
    </x-filament::section>
</x-filament-panels::page>
