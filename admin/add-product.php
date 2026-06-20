<?php
session_start();

// Kiểm tra đăng nhập
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_username'])) {
    header('Location: login.php');
    exit;
}

$fullname = $_SESSION['admin_fullname'] ?? 'Admin';
$success = '';
$error = '';

// Kết nối database
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'cottonusa_db';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

// Xử lý thêm sản phẩm
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = trim($_POST['product_id'] ?? '');
    $product_name = trim($_POST['product_name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $price = trim($_POST['price'] ?? '');
    $sizes = $_POST['sizes'] ?? [];
    $colors = $_POST['colors'] ?? [];
    $stock = (int)($_POST['stock'] ?? 0);
    
    // Xử lý upload ảnh
    $main_image = '';
    $color_images = [];
    
    if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] === 0) {
        $upload_dir = '../uploads/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $file_ext = pathinfo($_FILES['main_image']['name'], PATHINFO_EXTENSION);
        $main_image = $product_id . '_main.' . $file_ext;
        move_uploaded_file($_FILES['main_image']['tmp_name'], $upload_dir . $main_image);
    }
    
    // Xử lý ảnh màu
    if (isset($_FILES['color_images']) && is_array($_FILES['color_images']['name'])) {
        for ($i = 0; $i < count($_FILES['color_images']['name']); $i++) {
            if ($_FILES['color_images']['error'][$i] === 0) {
                $file_ext = pathinfo($_FILES['color_images']['name'][$i], PATHINFO_EXTENSION);
                $color_name = $colors[$i] ?? 'color' . $i;
                $color_img = $product_id . '_' . $color_name . '.' . $file_ext;
                move_uploaded_file($_FILES['color_images']['tmp_name'][$i], $upload_dir . $color_img);
                $color_images[] = [
                    'name' => $color_name,
                    'image' => $color_img
                ];
            }
        }
    }
    
    // Lưu vào database
    if ($product_id && $product_name) {
        // Thêm sản phẩm
        $stmt = $conn->prepare("INSERT INTO products (id, name, category, price, main_image) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssds", $product_id, $product_name, $category, $price, $main_image);
        
        if ($stmt->execute()) {
            // ===== LƯU SIZE + MÀU + SỐ LƯỢNG RIÊNG BIỆT =====
            // Lọc bỏ giá trị rỗng
            $valid_sizes = array_filter($sizes, function($s) { return trim($s) !== ''; });
            $valid_colors = array_filter($colors, function($c) { return trim($c) !== ''; });
            
            // Nếu có cả size và màu
            if (!empty($valid_sizes) && !empty($valid_colors)) {
                foreach ($valid_sizes as $size) {
                    foreach ($valid_colors as $color) {
                        $stmt2 = $conn->prepare("INSERT INTO product_stock (product_id, size_name, color_name, stock) VALUES (?, ?, ?, ?)");
                        $stmt2->bind_param("sssi", $product_id, trim($size), trim($color), $stock);
                        $stmt2->execute();
                        $stmt2->close();
                    }
                }
            }
            // Nếu chỉ có size (không có màu)
            elseif (!empty($valid_sizes) && empty($valid_colors)) {
                foreach ($valid_sizes as $size) {
                    $stmt2 = $conn->prepare("INSERT INTO product_stock (product_id, size_name, color_name, stock) VALUES (?, ?, ?, ?)");
                    $color_name = 'Mặc định';
                    $stmt2->bind_param("sssi", $product_id, trim($size), $color_name, $stock);
                    $stmt2->execute();
                    $stmt2->close();
                }
            }
            // Nếu chỉ có màu (không có size)
            elseif (empty($valid_sizes) && !empty($valid_colors)) {
                foreach ($valid_colors as $color) {
                    $stmt2 = $conn->prepare("INSERT INTO product_stock (product_id, size_name, color_name, stock) VALUES (?, ?, ?, ?)");
                    $size_name = 'Mặc định';
                    $stmt2->bind_param("sssi", $product_id, $size_name, trim($color), $stock);
                    $stmt2->execute();
                    $stmt2->close();
                }
            }
            
            // Thêm màu sắc vào bảng product_colors (để hiển thị)
            if (!empty($color_images)) {
                foreach ($color_images as $color_data) {
                    $stmt3 = $conn->prepare("INSERT INTO product_colors (product_id, color_name, image_url) VALUES (?, ?, ?)");
                    $stmt3->bind_param("sss", $product_id, $color_data['name'], $color_data['image']);
                    $stmt3->execute();
                    $stmt3->close();
                }
            }
            
            $success = '✅ Thêm sản phẩm thành công! Mã SP: ' . $product_id;
        } else {
            $error = '❌ Lỗi: ' . $conn->error;
        }
        $stmt->close();
    } else {
        $error = '❌ Vui lòng nhập đầy đủ thông tin!';
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thêm sản phẩm - CottonUSA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ===== RESET & BASE ===== */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background: #f0f2f5;
            display: flex;
            min-height: 100vh;
        }

        /* ===== SIDEBAR ===== */
        .sidebar {
            width: 260px;
            background: #1a1a2e;
            color: #fff;
            padding: 0;
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            overflow-y: auto;
            z-index: 100;
            transition: transform 0.3s ease;
        }
        .sidebar-brand {
            padding: 24px 24px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .sidebar-brand img {
            height: 40px;
        }
        .sidebar-brand span {
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 0.3px;
        }
        .sidebar-brand small {
            font-size: 11px;
            color: rgba(255,255,255,0.4);
            font-weight: 400;
            display: block;
            margin-top: 2px;
        }
        .sidebar-nav {
            flex: 1;
            padding: 16px 0;
        }
        .sidebar-nav .nav-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: rgba(255,255,255,0.25);
            padding: 8px 24px;
            margin-top: 8px;
        }
        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 24px;
            color: rgba(255,255,255,0.6);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
            border-left: 3px solid transparent;
        }
        .sidebar-nav a:hover {
            background: rgba(255,255,255,0.05);
            color: #fff;
        }
        .sidebar-nav a.active {
            background: rgba(227, 6, 19, 0.15);
            color: #fff;
            border-left-color: #e30613;
        }
        .sidebar-nav a i {
            width: 20px;
            text-align: center;
            font-size: 15px;
        }
        .sidebar-nav a .badge {
            background: #e30613;
            color: #fff;
            font-size: 10px;
            padding: 2px 8px;
            border-radius: 12px;
            margin-left: auto;
        }
        .sidebar-footer {
            padding: 16px 24px;
            border-top: 1px solid rgba(255,255,255,0.06);
        }
        .sidebar-footer .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 10px;
        }
        .sidebar-footer .user-info .avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #e30613;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
        }
        .sidebar-footer .user-info .name {
            font-size: 14px;
            font-weight: 600;
        }
        .sidebar-footer .user-info .role {
            font-size: 12px;
            color: rgba(255,255,255,0.4);
        }
        .sidebar-footer a {
            color: rgba(255,255,255,0.5);
            text-decoration: none;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: color 0.2s;
        }
        .sidebar-footer a:hover { color: #e30613; }

        /* ===== MAIN CONTENT ===== */
        .main-content {
            margin-left: 260px;
            flex: 1;
            padding: 24px 32px 40px;
            min-height: 100vh;
        }

        /* ===== HEADER ===== */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .page-header h1 {
            font-size: 24px;
            font-weight: 700;
            color: #1a1a2e;
        }
        .page-header h1 span {
            color: #e30613;
        }
        .page-header .header-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        /* ===== BUTTONS ===== */
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            text-decoration: none;
        }
        .btn-primary {
            background: #e30613;
            color: #fff;
        }
        .btn-primary:hover {
            background: #c70510;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(227, 6, 19, 0.3);
        }
        .btn-secondary {
            background: #e8e8e8;
            color: #333;
        }
        .btn-secondary:hover {
            background: #ddd;
        }
        .btn-success {
            background: #22c55e;
            color: #fff;
        }
        .btn-success:hover {
            background: #16a34a;
        }
        .btn-danger {
            background: #ef4444;
            color: #fff;
        }
        .btn-danger:hover {
            background: #dc2626;
        }
        .btn-outline {
            background: transparent;
            color: #666;
            border: 2px solid #e0e0e0;
        }
        .btn-outline:hover {
            border-color: #e30613;
            color: #e30613;
        }
        .btn-sm {
            padding: 6px 14px;
            font-size: 12px;
            border-radius: 8px;
        }

        /* ===== FORM ===== */
        .form-card {
            background: #fff;
            border-radius: 14px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .form-group {
            margin-bottom: 18px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 13px;
            color: #333;
            margin-bottom: 6px;
        }
        .form-group label .required {
            color: #e30613;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid #e8e8e8;
            border-radius: 10px;
            font-size: 14px;
            outline: none;
            transition: border 0.3s;
            font-family: inherit;
            background: #fafafa;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #e30613;
            background: #fff;
        }
        .form-group textarea {
            resize: vertical;
            min-height: 60px;
        }
        .full-width {
            grid-column: 1 / -1;
        }

        /* ===== DYNAMIC FIELDS ===== */
        .dynamic-fields {
            border: 2px dashed #e0e0e0;
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 12px;
        }
        .dynamic-fields .field-row {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 8px;
        }
        .dynamic-fields .field-row input {
            flex: 1;
            padding: 8px 12px;
            border: 2px solid #e8e8e8;
            border-radius: 8px;
            font-size: 14px;
            outline: none;
        }
        .dynamic-fields .field-row input:focus {
            border-color: #e30613;
        }
        .dynamic-fields .btn-remove {
            background: #fee2e2;
            color: #dc2626;
            border: none;
            border-radius: 8px;
            padding: 6px 12px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
        }
        .dynamic-fields .btn-remove:hover {
            background: #fecaca;
        }

        /* ===== COLOR PREVIEW ===== */
        .color-preview {
            display: inline-block;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid #e0e0e0;
            vertical-align: middle;
            margin-right: 6px;
        }

        /* ===== UPLOAD IMAGE ===== */
        .upload-area {
            border: 2px dashed #e0e0e0;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: #fafafa;
        }
        .upload-area:hover {
            border-color: #e30613;
            background: #fef0f0;
        }
        .upload-area i {
            font-size: 40px;
            color: #ccc;
            display: block;
            margin-bottom: 10px;
        }
        .upload-area p {
            color: #888;
            font-size: 14px;
        }
        .upload-area .file-name {
            color: #e30613;
            font-weight: 600;
            margin-top: 8px;
        }
        .upload-area input[type="file"] {
            display: none;
        }

        .image-preview-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-top: 12px;
        }
        .image-preview-item {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            border: 2px solid #e8e8e8;
            aspect-ratio: 1;
        }
        .image-preview-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .image-preview-item .remove-img {
            position: absolute;
            top: 4px;
            right: 4px;
            background: rgba(0,0,0,0.7);
            color: #fff;
            border: none;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            cursor: pointer;
            font-size: 12px;
        }
        .image-preview-item .remove-img:hover {
            background: #e30613;
        }

        /* ===== ALERT ===== */
        .alert {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
        }
        .alert-success {
            background: #dcfce7;
            color: #16a34a;
            border: 1px solid #bbf7d0;
        }
        .alert-error {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 1024px) {
            .form-grid { grid-template-columns: 1fr; }
            .full-width { grid-column: 1; }
        }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 16px; }
            .image-preview-grid { grid-template-columns: repeat(2, 1fr); }
        }
        .menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 24px;
            color: #333;
            cursor: pointer;
            padding: 4px;
        }
        @media (max-width: 768px) {
            .menu-toggle { display: block; }
        }
    </style>
</head>
<body>

    <!-- ===== SIDEBAR ===== -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <img src="../images/logo.avif" alt="CottonUSA">
            <div>
                <span>CottonUSA</span>
                <small>Quản trị hệ thống</small>
            </div>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-label">Tổng quan</div>
            <a href="dashboard.php">
                <i class="fas fa-chart-pie"></i> Thống kê
            </a>
            <a href="products.php" class="active">
                <i class="fas fa-tshirt"></i> Sản phẩm
            </a>
            <a href="orders.php">
                <i class="fas fa-shopping-cart"></i> Đơn hàng
                <span class="badge">12</span>
            </a>
            <a href="#">
                <i class="fas fa-warehouse"></i> Kho hàng
            </a>
            <div class="nav-label" style="margin-top:16px;">Nội dung</div>
            <a href="#">
                <i class="fas fa-newspaper"></i> Bài viết
            </a>
            <a href="statistics.php">
                <i class="fas fa-chart-line"></i> Thống kê doanh thu
            </a>
        </nav>
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="avatar"><?php echo strtoupper(substr($fullname, 0, 1)); ?></div>
                <div>
                    <div class="name"><?php echo htmlspecialchars($fullname); ?></div>
                    <div class="role">Administrator</div>
                </div>
            </div>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Đăng xuất</a>
        </div>
    </aside>

    <!-- ===== MAIN CONTENT ===== -->
    <main class="main-content">
        <!-- Header -->
        <div class="page-header">
            <div style="display:flex;align-items:center;gap:12px;">
                <button class="menu-toggle" id="menuToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h1>➕ Thêm <span>Sản phẩm</span></h1>
            </div>
            <div class="header-actions">
                <a href="products.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Quay lại</a>
            </div>
        </div>

        <!-- Form -->
        <div class="form-card">
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="form-grid">
                    <!-- Mã sản phẩm -->
                    <div class="form-group">
                        <label>Mã sản phẩm <span class="required">*</span></label>
                        <input type="text" name="product_id" placeholder="VD: SP001" required>
                    </div>

                    <!-- Danh mục -->
                    <div class="form-group">
                        <label>Danh mục <span class="required">*</span></label>
                        <select name="category" required>
                            <option value="">Chọn danh mục</option>
                            <option value="Quần">Quần</option>
                            <option value="Sweater">Sweater</option>
                            <option value="Hoodies">Hoodies</option>
                            <option value="Áo Thun">Áo Thun</option>
                            <option value="Áo Thun Dài Tay">Áo Thun Dài Tay</option>
                        </select>
                    </div>

                    <!-- Tên sản phẩm (full width) -->
                    <div class="form-group full-width">
                        <label>Tên sản phẩm <span class="required">*</span></label>
                        <input type="text" name="product_name" placeholder="Nhập tên sản phẩm" required>
                    </div>

                    <!-- Giá -->
                    <div class="form-group">
                        <label>Giá (VNĐ) <span class="required">*</span></label>
                        <input type="number" name="price" placeholder="399000" required>
                    </div>

                    <!-- Tồn kho -->
                    <div class="form-group">
                        <label>Tồn kho</label>
                        <input type="number" name="stock" value="0" min="0">
                        <small style="color:#888;font-size:12px;display:block;margin-top:4px;">
                            Số lượng sẽ được áp dụng cho tất cả các size và màu
                        </small>
                    </div>

                    <!-- ===== SIZE ===== -->
                    <div class="form-group full-width">
                        <label>Size</label>
                        <div class="dynamic-fields" id="sizeContainer">
                            <div class="field-row">
                                <input type="text" placeholder="Nhập size (VD: S, M, L...)" name="sizes[]">
                                <button type="button" class="btn-remove" onclick="removeField(this)">✕</button>
                            </div>
                            <div class="field-row">
                                <input type="text" placeholder="Nhập size (VD: S, M, L...)" name="sizes[]">
                                <button type="button" class="btn-remove" onclick="removeField(this)">✕</button>
                            </div>
                            <div class="field-row">
                                <input type="text" placeholder="Nhập size (VD: S, M, L...)" name="sizes[]">
                                <button type="button" class="btn-remove" onclick="removeField(this)">✕</button>
                            </div>
                        </div>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="addField('sizeContainer', 'Nhập size (VD: S, M, L...)', 'sizes[]')">
                            <i class="fas fa-plus"></i> Thêm size
                        </button>
                    </div>

                    <!-- ===== MÀU SẮC ===== -->
                    <div class="form-group full-width">
                        <label>Màu sắc</label>
                        <div class="dynamic-fields" id="colorContainer">
                            <div class="field-row">
                                <input type="text" placeholder="Nhập màu (VD: Đen, Trắng...)" name="colors[]">
                                <button type="button" class="btn-remove" onclick="removeField(this)">✕</button>
                            </div>
                            <div class="field-row">
                                <input type="text" placeholder="Nhập màu (VD: Đen, Trắng...)" name="colors[]">
                                <button type="button" class="btn-remove" onclick="removeField(this)">✕</button>
                            </div>
                            <div class="field-row">
                                <input type="text" placeholder="Nhập màu (VD: Đen, Trắng...)" name="colors[]">
                                <button type="button" class="btn-remove" onclick="removeField(this)">✕</button>
                            </div>
                        </div>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="addField('colorContainer', 'Nhập màu (VD: Đen, Trắng...)', 'colors[]')">
                            <i class="fas fa-plus"></i> Thêm màu
                        </button>
                    </div>

                    <!-- ===== ẢNH CHÍNH ===== -->
                    <div class="form-group full-width">
                        <label>Ảnh chính sản phẩm</label>
                        <div class="upload-area" id="mainImageUpload" onclick="document.getElementById('mainImageInput').click()">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <p>Nhấp để tải ảnh chính</p>
                            <div class="file-name" id="mainImageName"></div>
                            <input type="file" name="main_image" id="mainImageInput" accept="image/*">
                        </div>
                        <div class="image-preview-grid" id="mainImagePreview"></div>
                    </div>

                    <!-- ===== ẢNH MÀU ===== -->
                    <div class="form-group full-width">
                        <label>Ảnh cho từng màu</label>
                        <p style="font-size:13px;color:#888;margin-bottom:10px;">Chọn ảnh tương ứng với màu sắc đã nhập ở trên</p>
                        <div class="upload-area" id="colorImageUpload" onclick="document.getElementById('colorImagesInput').click()">
                            <i class="fas fa-images"></i>
                            <p>Nhấp để tải ảnh màu (chọn nhiều ảnh)</p>
                            <div class="file-name" id="colorImagesName"></div>
                            <input type="file" name="color_images[]" id="colorImagesInput" accept="image/*" multiple>
                        </div>
                        <div class="image-preview-grid" id="colorImagesPreview"></div>
                    </div>
                </div>

                <!-- Submit -->
                <div style="display:flex;gap:12px;margin-top:24px;">
                    <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Lưu sản phẩm</button>
                    <button type="reset" class="btn btn-secondary"><i class="fas fa-undo"></i> Làm mới</button>
                </div>
            </form>
        </div>
    </main>

    <script>
        // ===== TOGGLE SIDEBAR =====
        document.getElementById('menuToggle')?.addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('open');
        });

        // ===== ADD FIELD (Size / Color) =====
        function addField(containerId, placeholder, name) {
            const container = document.getElementById(containerId);
            const row = document.createElement('div');
            row.className = 'field-row';
            row.innerHTML = `
                <input type="text" placeholder="${placeholder}" name="${name}">
                <button type="button" class="btn-remove" onclick="removeField(this)">✕</button>
            `;
            container.appendChild(row);
        }

        // ===== REMOVE FIELD =====
        function removeField(btn) {
            const row = btn.parentElement;
            const container = row.parentElement;
            if (container.children.length > 1) {
                row.remove();
            } else {
                alert('Cần ít nhất 1 trường!');
            }
        }

        // ===== PREVIEW MAIN IMAGE =====
        document.getElementById('mainImageInput')?.addEventListener('change', function(e) {
            const file = this.files[0];
            if (file) {
                document.getElementById('mainImageName').textContent = file.name;
                const reader = new FileReader();
                reader.onload = function(event) {
                    const preview = document.getElementById('mainImagePreview');
                    preview.innerHTML = `
                        <div class="image-preview-item">
                            <img src="${event.target.result}" alt="Main image">
                        </div>
                    `;
                };
                reader.readAsDataURL(file);
            }
        });

        // ===== PREVIEW COLOR IMAGES =====
        document.getElementById('colorImagesInput')?.addEventListener('change', function(e) {
            const files = this.files;
            const preview = document.getElementById('colorImagesPreview');
            preview.innerHTML = '';
            const names = [];
            
            for (let i = 0; i < files.length; i++) {
                names.push(files[i].name);
                const reader = new FileReader();
                reader.onload = function(event) {
                    const div = document.createElement('div');
                    div.className = 'image-preview-item';
                    div.innerHTML = `
                        <img src="${event.target.result}" alt="Color image ${i+1}">
                        <button class="remove-img" onclick="this.parentElement.remove()">✕</button>
                    `;
                    preview.appendChild(div);
                };
                reader.readAsDataURL(files[i]);
            }
            document.getElementById('colorImagesName').textContent = names.join(', ');
        });

        // ===== UPLOAD AREA CLICK =====
        document.querySelectorAll('.upload-area').forEach(area => {
            area.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.style.borderColor = '#e30613';
                this.style.background = '#fef0f0';
            });
            area.addEventListener('dragleave', function(e) {
                e.preventDefault();
                this.style.borderColor = '#e0e0e0';
                this.style.background = '#fafafa';
            });
            area.addEventListener('drop', function(e) {
                e.preventDefault();
                this.style.borderColor = '#e0e0e0';
                this.style.background = '#fafafa';
                const input = this.querySelector('input[type="file"]');
                if (input) {
                    input.files = e.dataTransfer.files;
                    input.dispatchEvent(new Event('change'));
                }
            });
        });
    </script>
</body>
</html>