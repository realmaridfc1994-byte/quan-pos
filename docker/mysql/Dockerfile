# MySQL 8.4 + cấu hình riêng cho quán nhậu.
#
# Vì sao phải có file này thay vì gắn thẳng file cấu hình vào container?
#   Windows chia sẻ file vào Docker với quyền "ai cũng sửa được". MySQL coi đó
#   là rủi ro bảo mật nên NÍN LẶNG BỎ QUA file cấu hình — múi giờ, chế độ
#   nghiêm ngặt, bảng mã đều không có tác dụng mà không báo lỗi gì.
#   Chép file vào trong image rồi đặt lại quyền là cách xử lý dứt điểm.

FROM mysql:8.4

COPY conf.d/quanpos.cnf /etc/mysql/conf.d/quanpos.cnf

RUN chown root:root /etc/mysql/conf.d/quanpos.cnf \
 && chmod 644       /etc/mysql/conf.d/quanpos.cnf
