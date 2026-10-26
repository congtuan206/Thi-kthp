# Đề tài 5 — Hệ thống Quản lý Kho hàng (Inventory)

Ứng dụng thực hành quản lý sản phẩm, nhà cung cấp, tồn kho và giao dịch nhập/xuất. Stack: PHP + MySQL + phpMyAdmin + Nginx + Prometheus + Grafana + Loki + Promtail, chạy bằng Docker Compose.

## 1. Yêu cầu
- Docker Desktop (Windows/macOS) hoặc Docker Engine + Docker Compose.
- Đã bật Docker trước khi chạy lệnh.
- Dùng mật khẩu riêng trong `.env`, không dùng mật khẩu mẫu khi triển khai thật.

## 2. Khởi chạy
PowerShell:
```powershell
Copy-Item .env.example .env
notepad .env
docker compose config
docker compose up -d --build
docker compose ps
```
Sửa tất cả mật khẩu trong `.env` trước khi chạy lần đầu. Tài khoản exporter được tạo từ biến `EXPORTER_PASSWORD` trong `.env` bằng script khởi tạo. Hãy đổi các mật khẩu mẫu trước lần chạy đầu tiên. Script khởi tạo database chỉ chạy tự động khi volume MySQL được tạo lần đầu.

## 3. Địa chỉ
- Website qua Nginx: http://localhost:8080
- phpMyAdmin: http://localhost:8081 (chỉ bind localhost)
- Prometheus: http://localhost:9090 (chỉ bind localhost)
- Grafana: http://localhost:3000 (chỉ bind localhost; đăng nhập theo `.env`)

Loki không công khai cổng máy chủ; dùng datasource Loki trong Grafana Explore.

## 4. Kiểm tra
```powershell
docker compose ps
docker compose logs --tail=100
docker compose exec mysql mysql -u"$env:MYSQL_USER" -p inventory_db
```
Prometheus → Status → Targets: kiểm tra `prometheus`, `cadvisor`, `mysql`, `nginx` có trạng thái UP. Trong Grafana, datasource Prometheus và Loki được provision sẵn.

### LogQL mẫu
```logql
{job="docker"}
{job="docker"} |= "error"
{job="docker"} |~ "(?i)warning|exception|fatal"
```
Promtail đang thu thập Docker JSON logs. Nhãn có thể khác theo phiên bản và môi trường; kiểm tra Grafana → Explore → Loki → Label browser.

## 5. Hardening được cấu hình
- `.env` bị loại khỏi Git bởi `.gitignore`.
- Mạng backend và monitoring được khai báo `internal`.
- MySQL không publish cổng host; phpMyAdmin, Prometheus, Grafana chỉ bind `127.0.0.1`.
- Ứng dụng chạy với user `www-data`, filesystem read-only và `no-new-privileges`.
- Nginx thêm security headers.
- Ứng dụng dùng prepared statements, kiểm tra đầu vào, CSRF token và transaction khi nhập/xuất.

Lưu ý: cAdvisor cần quyền đọc một số thông tin host và trong cấu hình mẫu dùng `privileged: true`; đây là ngoại lệ quyền cao phục vụ giám sát container. Khi trình bày hardening, cần nêu rõ giới hạn này. Cấu hình mẫu chưa bật HTTPS; yêu cầu chung cho phép security headers cơ bản hoặc HTTPS tự ký.

## 6. Dừng hệ thống
```powershell
docker compose down
```
Không dùng `docker compose down -v` nếu muốn giữ dữ liệu MySQL/Grafana/Loki.

## 7. GitHub và 3 commit gợi ý
```bash
git init
git add .
git commit -m "feat: deploy inventory app with mysql and nginx"
git add prometheus grafana
git commit -m "feat: add prometheus and grafana monitoring"
git add loki promtail docker-compose.yml README.md
git commit -m "feat: add loki promtail and security hardening"
```
Sau đó tạo repository GitHub và thêm remote theo URL repository của bạn. Không commit file `.env`.

## 8. Ảnh cần chụp cho báo cáo
1. Website quản lý kho và danh sách sản phẩm.
2. phpMyAdmin hiển thị database/tables.
3. Nginx headers qua DevTools hoặc `curl -I`.
4. Prometheus Targets với trạng thái UP.
5. Grafana dashboard hoặc Explore có dữ liệu.
6. Loki Explore chạy 2–3 truy vấn LogQL.
7. `docker compose ps` và cấu hình hardening.
8. Repository GitHub và lịch sử commit.

## Lưu ý triển khai
Đây là bộ khung thực hành có thể cần điều chỉnh theo môi trường Docker/OS và phiên bản image. Hãy xác nhận `docker compose config`, kiểm tra logs, targets và datasource trước khi ghi nhận thành công trong báo cáo.
