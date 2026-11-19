# DevTrack — Hệ thống Theo dõi Lỗi và Quản lý Kế hoạch Phát hành (BTL_PTPMMNM)

Dự án Bài tập lớn môn **Phát triển phần mềm mã nguồn mở**, xây dựng ứng dụng quản lý dự án phần mềm, theo dõi lỗi (Bug Tracker), quản lý kế hoạch công việc (Sprint/Task Dependency Planning), lập phiên bản phát hành (Release Planning) và kiểm soát xung đột dữ liệu đồng thời.

---

## 1. Kiến trúc Backend (PHP MVC & REST API)

Hệ thống backend được thiết kế theo chuẩn **Mô hình MVC (Model - View - Controller)** kết hợp **RESTful API**, lưu trữ dữ liệu trên **SQLite (PDO)**:

```
D:\BTL_PTPMMNM\
├── controllers/              # Tầng điều khiển (Controller)
│   ├── AuthController.php    # Đăng nhập, đăng ký, đăng xuất, phiên làm việc
│   ├── ProjectController.php # Quản lý dự án, phân quyền RBAC
│   ├── MemberController.php  # Quản lý danh sách thành viên & vai trò
│   ├── TaskController.php    # Quản lý task, kiểm tra chu trình phụ thuộc (Cycle Detection)
│   ├── IssueController.php   # Quản lý lỗi, tóm tắt trạng thái, bình luận
│   ├── ReleaseController.php # Lập kế hoạch phát hành, kiểm tra điều kiện, xuất release note
│   └── ActivityController.php# Ghi nhận và truy vấn nhật ký hoạt động
│
├── models/                   # Tầng mô hình dữ liệu (Model)
│   ├── BaseModel.php         # Lớp cơ sở (PDO, logging, kiểm tra xung đột Optimistic Locking)
│   ├── UserModel.php         # Truy vấn và xác thực tài khoản người dùng
│   ├── ProjectModel.php      # CRUD dự án
│   ├── MemberModel.php       # CRUD thành viên
│   ├── TaskModel.php         # CRUD công việc, thuật toán phát hiện chu trình DFS
│   ├── IssueModel.php        # CRUD lỗi, thống kê và bình luận
│   ├── ReleaseModel.php      # CRUD phiên bản, xuất file ghi chú .txt
│   └── ActivityModel.php     # Nhật ký hệ thống
│
├── helpers/                  # Các hàm tiện ích dùng chung
│   ├── auth.php              # Quản lý phiên an toàn, kiểm tra quyền hạn (Admin, Manager, Member, Viewer)
│   └── utils.php             # Xử lý chuỗi, mã hóa JSON, định dạng dữ liệu, phản hồi JSON
│
├── data/                     # Thư mục lưu trữ SQLite database và session
│   ├── devtrack.sqlite       # Cơ sở dữ liệu SQLite
│   └── sessions/             # Lưu trữ phiên làm việc của PHP
│
├── api.php                   # Bộ định tuyến RESTful JSON API
├── admin.php                 # Trang Quản trị cấp cao (Admin Portal: quản lý user, reset mật khẩu, bảo trì CSDL)
├── db.php                    # Kết nối cơ sở dữ liệu và khởi tạo dữ liệu mẫu
├── dashboard.php             # Giao diện và xử lý trang quản trị chính
├── login.php                 # Trang đăng nhập
├── register.php              # Trang đăng ký
├── logout.php                # Xử lý đăng xuất
├── index.php                 # Trang giới thiệu (Landing page)
├── styles.css                # CSS giao diện hệ thống
├── php.ini                   # Cấu hình PHP (kích hoạt pdo_sqlite, session path)
├── test_backend.php          # Bộ kiểm thử tự động toàn bộ tầng Model & Controller (31 tests)
└── test_api.php              # Bộ kiểm thử tự động toàn bộ REST API (9 tests)
```

---

## 2. Tính năng chính của Backend

1. **Xác thực và Phân quyền (RBAC - Role-Based Access Control):**
   - **Admin**: Toàn quyền thêm, sửa, xóa dự án, thành viên, task, issue, release.
   - **Manager**: Quản lý dự án, công việc và phát hành; có quyền sửa/xóa.
   - **Member**: Thêm mới, cập nhật tiến độ công việc và báo cáo lỗi; không được phép xóa dữ liệu.
   - **Viewer**: Chỉ có quyền đọc, chặn tất cả thao tác ghi.

2. **Phát hiện chu trình phụ thuộc (Task Dependency Cycle Detection):**
   - Sử dụng thuật toán DFS trên đồ thị có hướng (Directed Graph) để phát hiện và ngăn chặn ngay lập tức nếu người dùng tạo phụ thuộc vòng (VD: A phụ thuộc B, B phụ thuộc A).
   - Kiểm tra trạng thái bị chặn (`taskBlocked`) nếu công việc tiên quyết chưa hoàn thành.

3. **Kiểm soát xung đột dữ liệu đồng thời (Optimistic Locking):**
   - Sử dụng trường `updated_at` đối chiếu khi lưu dữ liệu. Nếu dữ liệu đã bị sửa đổi bởi người khác trong cùng thời điểm, hệ thống báo lỗi xung đột và yêu cầu tải lại nội dung.

4. **Kế hoạch phát hành & Xuất ghi chú (Release Planning & Export):**
   - Gắn danh sách công việc, điều kiện phát hành (conditions), danh sách thay đổi (change list), checklist.
   - Xuất file `.txt` ghi chú phát hành theo định dạng chuẩn để gửi khách hàng/đối tác.

5. **RESTful API hoàn chỉnh (`api.php`):**
   - Cung cấp API dạng JSON cho các ứng dụng frontend (SPA, Mobile, Postman):
     - `POST /api.php?resource=auth&action=login`
     - `POST /api.php?resource=auth&action=register`
     - `GET/POST/PUT/DELETE /api.php?resource=projects`
     - `GET/POST/PUT/DELETE /api.php?resource=members`
     - `GET/POST/PUT/DELETE /api.php?resource=tasks`
     - `GET /api.php?resource=tasks&action=cycles`
     - `GET/POST/PUT/DELETE /api.php?resource=issues`
     - `POST /api.php?resource=issues&action=comment`
     - `GET/POST/PUT/DELETE /api.php?resource=releases`
     - `GET /api.php?resource=releases&action=export`
     - `GET /api.php?resource=summary`
     - `GET /api.php?resource=activity_log`

---

## 3. Hướng dẫn chạy và kiểm thử

### Khởi động Server:
Sử dụng PHP CLI hoặc XAMPP:
```powershell
# Chạy server cục bộ bằng PHP:
& "C:\xampp\php\php.exe" -S 127.0.0.1:8000
```
Truy cập qua trình duyệt: `http://127.0.0.1:8000/index.php` hoặc `http://127.0.0.1:8000/login.php`

**Tài khoản quản trị mặc định:**
- Tên đăng nhập: `admin`
- Mật khẩu: `admin123`

### Chạy kiểm thử tự động:
```powershell
# Kiểm tra backend MVC (Models, Controllers, Logic, RBAC, Chu trình):
& "C:\xampp\php\php.exe" test_backend.php

# Kiểm tra RESTful JSON API:
& "C:\xampp\php\php.exe" test_api.php
```
Tất cả các bài test đều đạt **100% PASS (40/40 tests)**.

