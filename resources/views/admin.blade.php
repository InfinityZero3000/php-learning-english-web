@extends('layouts.app')

@section('title', 'Khu vực quản trị')

@section('content')
    <div class="alert alert-warning">
        <h1 class="h3">Khu vực quản trị</h1>
        <p class="mb-0">Xác thực và phân quyền chưa được triển khai trong giai đoạn skeleton.</p>
    </div>
    <!-- BẢNG 2: QUẢN LÝ NỘI DUNG HỌC TẬP -->
        <div class="card shadow-sm mt-4 mb-5">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 fw-bold text-primary">Quản lý Nội dung & Bài học</h5>
                <button class="btn btn-sm btn-success">+ Thêm bài học mới</button>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#ID</th>
                            <th>Tên bài học / Chủ đề</th>
                            <th>Loại nội dung</th>
                            <th>Trạng thái</th>
                            <th class="text-center">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>1</td>
                            <td class="fw-semibold">Chủ đề 3000 từ vựng Oxford cơ bản</td>
                            <td><span class="badge bg-info text-dark">Vocabulary</span></td>
                            <td><span class="badge bg-success">Đã xuất bản</span></td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-outline-secondary">Sửa</button>
                                <button class="btn btn-sm btn-outline-danger">Xóa</button>
                            </td>
                        </tr>
                        <tr>
                            <td>2</td>
                            <td class="fw-semibold">Ngữ pháp Thì Hiện Tại Hoàn Thành</td>
                            <td><span class="badge bg-warning text-dark">Grammar</span></td>
                            <td><span class="badge bg-success">Đã xuất bản</span></td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-outline-secondary">Sửa</button>
                                <button class="btn btn-sm btn-outline-danger">Xóa</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
@endsection
