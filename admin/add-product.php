<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Kiểm tra đăng nhập
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_username'])) {
    header('Location: login.php');
    exit;
}

$fullname = $_SESSION['admin_fullname'] ?? 'Admin';
$current_page = basename($_SERVER['PHP_SELF']);
$success = '';
$error = '';

// Kết nối database
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'cottonusa';

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
    $stock = (int)($_POST['stock'] ?? 0);
    
    // Lấy size và màu
    $sizes = $_POST['sizes'] ?? [];
    $colors = $_POST['colors'] ?? [];
    $color_names = $_POST['color_names'] ?? [];
    
    // Tạo mã sản phẩm nếu để trống
    if (empty($product_id)) {
        $prefix = '';
        if ($category == 'Quần') $prefix = 'Q';
        else if ($category == 'Sweater') $prefix = 'SW';
        else if ($category == 'Hoodies') $prefix = 'H';
        else if ($category == 'Áo Thun') $prefix = 'AT';
        else if ($category == 'Áo Thun Dài Tay') $prefix = 'ATDT';
        else $prefix = 'SP';
        
        $countResult = $conn->query("SELECT COUNT(*) as total FROM products WHERE id LIKE '$prefix%'");
        $row = $countResult->fetch_assoc();
        $count = $row['total'] + 1;
        $product_id = $prefix . str_pad($count, 3, '0', STR_PAD_LEFT);
    }
    
    // Kiểm tra trùng
    $check = $conn->query("SELECT id FROM products WHERE id = '$product_id'");
    if ($check->num_rows > 0) {
        $error = "❌ Mã sản phẩm '$product_id' đã tồn tại!";
    } else {
        // Upload ảnh
        $main_image = '';
        $upload_dir = '../uploads/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Ảnh chính
        if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] == 0) {
            $main_image = $product_id . '_main.png';
            move_uploaded_file($_FILES['main_image']['tmp_name'], $upload_dir . $main_image);
        }
        
        // Ảnh màu
        $color_images = array();
        if (isset($_FILES['color_images']) && is_array($_FILES['color_images']['name'])) {
            $total = count($_FILES['color_images']['name']);
            for ($i = 0; $i < $total; $i++) {
                if ($_FILES['color_images']['error'][$i] == 0) {
                    $color_name = '';
                    if (isset($color_names[$i]) && trim($color_names[$i]) != '') {
                        $color_name = trim($color_names[$i]);
                    } else {
                        $color_name = 'color_' . ($i + 1);
                    }
                    $color_img = $product_id . '_' . $color_name . '.png';
                    move_uploaded_file($_FILES['color_images']['tmp_name'][$i], $upload_dir . $color_img);
                    $color_images[] = array('name' => $color_name, 'image' => $color_img);
                }
            }
        }
        
        // Lưu sản phẩm
        if (!empty($product_id) && !empty($product_name)) {
            $stmt = $conn->prepare("INSERT INTO products (id, name, category, price, main_image) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssds", $product_id, $product_name, $category, $price, $main_image);
            
            if ($stmt->execute()) {
                // Lọc size và màu hợp lệ
                $valid_sizes = array();
                foreach ($sizes as $s) {
                    if (trim($s) != '') {
                        $valid_sizes[] = trim($s);
                    }
                }
                
                $valid_colors = array();
                foreach ($colors as $c) {
                    if (trim($c) != '') {
                        $valid_colors[] = trim($c);
                    }
                }
                
                // Nếu có ảnh màu nhưng chưa có tên màu
                if (empty($valid_colors) && !empty($color_images)) {
                    foreach ($color_images as $img) {
                        $valid_colors[] = $img['name'];
                    }
                }
                
                // Lưu stock
                if (!empty($valid_sizes) && !empty($valid_colors)) {
                    foreach ($valid_sizes as $size) {
                        foreach ($valid_colors as $color) {
                            $stmt2 = $conn->prepare("INSERT INTO product_stock (product_id, size_name, color_name, stock) VALUES (?, ?, ?, ?)");
                            $stmt2->bind_param("sssi", $product_id, $size, $color, $stock);
                            $stmt2->execute();
                            $stmt2->close();
                        }
                    }
                } else if (!empty($valid_sizes) && empty($valid_colors)) {
                    foreach ($valid_sizes as $size) {
                        $stmt2 = $conn->prepare("INSERT INTO product_stock (product_id, size_name, color_name, stock) VALUES (?, ?, ?, ?)");
                        $default_color = 'Mặc định';
                        $stmt2->bind_param("sssi", $product_id, $size, $default_color, $stock);
                        $stmt2->execute();
                        $stmt2->close();
                    }
                } else if (empty($valid_sizes) && !empty($valid_colors)) {
                    foreach ($valid_colors as $color) {
                        $stmt2 = $conn->prepare("INSERT INTO product_stock (product_id, size_name, color_name, stock) VALUES (?, ?, ?, ?)");
                        $default_size = 'Mặc định';
                        $stmt2->bind_param("sssi", $product_id, $default_size, $color, $stock);
                        $stmt2->execute();
                        $stmt2->close();
                    }
                }
                
                // Lưu product_colors
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
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background: #f0f2f5;
            display: flex;
            min-height: 100vh;
        }
        .sidebar {
            width: 250px;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            overflow-y: auto;
            z-index: 100;
            box-shadow: 2px 0 12px rgba(0,0,0,0.08);
        }
        .sidebar-brand {
            padding: 24px 0 20px 0;
            border-bottom: 1px solid #f0f0f0;
            text-align: center;
        }
        .brand-link {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
        }
        .brand-logo {
            height: 70px;
            width: auto;
            display: block;
            object-fit: contain;
            transition: transform 0.3s ease;
        }
        .brand-logo:hover { transform: scale(1.05); }
        .sidebar-nav {
            flex: 1;
            padding: 16px 0;
        }
        .nav-label {
            font-size: 11px;
            text-transform: uppercase;
            color: #aaa;
            padding: 12px 24px 8px 24px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 11px 24px;
            color: #666;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.2s;
            border-left: 3px solid transparent;
        }
        .sidebar-nav a:hover {
            background: #f5f5f5;
            color: #1a1a2e;
        }
        .sidebar-nav a.active {
            background: rgba(227,6,19,0.08);
            color: #e30613;
            border-left-color: #e30613;
            font-weight: 600;
        }
        .sidebar-nav a i {
            width: 20px;
            text-align: center;
            font-size: 15px;
        }
        .sidebar-footer {
            padding: 16px 24px;
            border-top: 1px solid #f0f0f0;
            margin-top: auto;
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 10px;
        }
        .avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #e30613;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
            color: #ffffff;
            flex-shrink: 0;
        }
        .name {
            font-size: 14px;
            font-weight: 600;
            color: #1a1a2e;
        }
        .role {
            font-size: 12px;
            color: #888;
        }
        .sidebar-footer a {
            color: #888;
            text-decoration: none;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: color 0.2s;
        }
        .sidebar-footer a:hover { color: #e30613; }
        .main-content {
            margin-left: 250px;
            flex: 1;
            padding: 24px 32px;
            min-height: 100vh;
        }
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
            color: #1a1a2e;
        }
        .page-header h1 span { color: #e30613; }
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
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-primary { background: #e30613; color: #fff; }
        .btn-primary:hover { background: #c70510; }
        .btn-secondary { background: #e8e8e8; color: #333; }
        .btn-secondary:hover { background: #ddd; }
        .btn-success { background: #22c55e; color: #fff; }
        .btn-success:hover { background: #16a34a; }
        .btn-sm { padding: 6px 14px; font-size: 12px; border-radius: 8px; }
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
        .form-group label .required { color: #e30613; }
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
        .full-width {
            grid-column: 1 / -1;
        }
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
        .dynamic-fields .field-row input:focus { border-color: #e30613; }
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
        .dynamic-fields .btn-remove:hover { background: #fecaca; }
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
        .upload-area input[type="file"] { display: none; }
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
        .image-preview-item .remove-img:hover { background: #e30613; }
        .color-upload-item {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 10px;
            flex-wrap: wrap;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 10px;
            border: 1px solid #e8e8e8;
        }
        .color-upload-item .upload-area-mini {
            flex: 2;
            padding: 10px 16px;
            border: 2px dashed #e0e0e0;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            background: #fff;
            transition: all 0.3s;
            min-width: 150px;
        }
        .color-upload-item .upload-area-mini:hover {
            border-color: #e30613;
            background: #fef0f0;
        }
        .color-upload-item .upload-area-mini i {
            font-size: 18px;
            color: #ccc;
        }
        .color-upload-item .upload-area-mini span {
            font-size: 13px;
            color: #888;
        }
        .color-upload-item .upload-area-mini .file-name {
            display: block;
            font-size: 12px;
            color: #e30613;
            margin-top: 4px;
            font-weight: 600;
        }
        .color-upload-item input[type="file"] { display: none; }
        .color-upload-item .image-preview-mini {
            margin-top: 6px;
            position: relative;
            display: inline-block;
        }
        .color-upload-item .image-preview-mini img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #e8e8e8;
        }
        .color-upload-item .btn-remove-color {
            background: #fee2e2;
            color: #dc2626;
            border: none;
            border-radius: 8px;
            padding: 6px 12px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            align-self: flex-start;
        }
        .color-upload-item .btn-remove-color:hover { background: #fecaca; }
        .color-upload-item input[type="text"] {
            flex: 1;
            padding: 8px 12px;
            border: 2px solid #e8e8e8;
            border-radius: 8px;
            font-size: 14px;
            outline: none;
            min-width: 100px;
            background: #fff;
        }
        .color-upload-item input[type="text"]:focus { border-color: #e30613; }
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
        .upload-hint {
            font-size: 13px;
            color: #888;
            margin-bottom: 10px;
            padding: 10px 14px;
            background: #f0f7ff;
            border-radius: 8px;
            border-left: 4px solid #3b82f6;
        }
        .menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            padding: 4px;
        }
        @media (max-width: 1024px) {
            .form-grid { grid-template-columns: 1fr; }
            .full-width { grid-column: 1; }
        }
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 16px; }
            .image-preview-grid { grid-template-columns: repeat(2, 1fr); }
            .menu-toggle { display: block; }
            .color-upload-item { flex-direction: column; align-items: stretch; }
            .color-upload-item .upload-area-mini { flex: 1; }
        }
    </style>
</head>
<body>
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <a href="home.php" class="brand-link">
                <img src="../images/logo.avif" alt="CottonUSA" class="brand-logo">
            </a>
        </div>
        <nav class="sidebar-nav">
            <a href="home.php"><i class="fas fa-store"></i> Trang chính</a>
            <div class="nav-label">Tổng quan</div>
            <a href="dashboard.php"><i class="fas fa-chart-pie"></i> Thống kê</a>
            <a href="products.php" class="active"><i class="fas fa-tshirt"></i> Sản phẩm</a>
            <a href="orders.php"><i class="fas fa-shopping-cart"></i> Đơn hàng</a>
            <div class="nav-label">Nội dung</div>
            <a href="statistics.php"><i class="fas fa-chart-line"></i> Thống kê doanh thu</a>
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
    <main class="main-content">
        <div class="page-header">
            <div style="display:flex;align-items:center;gap:12px;">
                <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
                <h1>➕ Thêm <span>Sản phẩm</span></h1>
            </div>
            <div>
                <a href="products.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Quay lại</a>
            </div>
        </div>
        <div class="form-card">
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                    <a href="products.php" style="color:#16a34a;font-weight:600;margin-left:12px;">Xem danh sách sản phẩm</a>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Mã sản phẩm <span style="font-size:12px;color:#888;font-weight:400;"></span></label>
                        <input type="text" name="product_id" placeholder="Nhập mã...">
                    </div>
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
                    <div class="form-group full-width">
                        <label>Tên sản phẩm <span class="required">*</span></label>
                        <input type="text" name="product_name" placeholder="Nhập tên sản phẩm" required>
                    </div>
                    <div class="form-group">
                        <label>Giá (VNĐ) <span class="required">*</span></label>
                        <input type="number" name="price" placeholder="" required>
                    </div>
                    <div class="form-group">
                        <label>Tồn kho</label>
                        <input type="number" name="stock" value="" min="0">
                        <small style="color:#888;font-size:12px;display:block;margin-top:4px;"></small>
                    </div>
                    <div class="form-group full-width">
                        <label>Size</label>
                        <div class="dynamic-fields" id="sizeContainer">
                            <div class="field-row"><input type="text" placeholder="Nhập size (VD: S, M, L...)" name="sizes[]"><button type="button" class="btn-remove" onclick="removeField(this)">✕</button></div>
                            <div class="field-row"><input type="text" placeholder="Nhập size (VD: S, M, L...)" name="sizes[]"><button type="button" class="btn-remove" onclick="removeField(this)">✕</button></div>
                            <div class="field-row"><input type="text" placeholder="Nhập size (VD: S, M, L...)" name="sizes[]"><button type="button" class="btn-remove" onclick="removeField(this)">✕</button></div>
                        </div>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="addField('sizeContainer', 'Nhập size (VD: S, M, L...)', 'sizes[]')">
                            <i class="fas fa-plus"></i> Thêm size
                        </button>
                    </div>
                    <div class="form-group full-width">
                        <label>Màu sắc</label>
                        <div class="dynamic-fields" id="colorContainer">
                            <div class="field-row"><input type="text" placeholder="Nhập màu (VD: Đen, Trắng...)" name="colors[]"><button type="button" class="btn-remove" onclick="removeField(this)">✕</button></div>
                            <div class="field-row"><input type="text" placeholder="Nhập màu (VD: Đen, Trắng...)" name="colors[]"><button type="button" class="btn-remove" onclick="removeField(this)">✕</button></div>
                            <div class="field-row"><input type="text" placeholder="Nhập màu (VD: Đen, Trắng...)" name="colors[]"><button type="button" class="btn-remove" onclick="removeField(this)">✕</button></div>
                        </div>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="addField('colorContainer', 'Nhập màu (VD: Đen, Trắng...)', 'colors[]')">
                            <i class="fas fa-plus"></i> Thêm màu
                        </button>
                    </div>
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
                    <div class="form-group full-width">
                        <label>Ảnh cho từng màu <span style="color:#e30613;font-weight:400;"></label>
                     
                        <div id="colorUploadContainer">
                            <div class="color-upload-item">
                                <input type="text" placeholder="Tên màu" name="color_names[]">
                                <div class="upload-area-mini" onclick="this.querySelector('input[type=file]').click()">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <span>Chọn ảnh</span>
                                    <input type="file" name="color_images[]" accept="image/*" onchange="previewColorImage(this)">
                                    <span class="file-name"></span>
                                </div>
                                <button type="button" class="btn-remove-color" onclick="removeColorUpload(this)">✕</button>
                            </div>
                            <div class="color-upload-item">
                                <input type="text" placeholder="Tên màu" name="color_names[]">
                                <div class="upload-area-mini" onclick="this.querySelector('input[type=file]').click()">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <span>Chọn ảnh</span>
                                    <input type="file" name="color_images[]" accept="image/*" onchange="previewColorImage(this)">
                                    <span class="file-name"></span>
                                </div>
                                <button type="button" class="btn-remove-color" onclick="removeColorUpload(this)">✕</button>
                            </div>
                        </div>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="addColorUpload()">
                            <i class="fas fa-plus"></i> Thêm ảnh màu
                        </button>
                    </div>
                </div>
                <div style="display:flex;gap:12px;margin-top:24px;">
                    <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Lưu sản phẩm</button>
                    <button type="reset" class="btn btn-secondary"><i class="fas fa-undo"></i> Làm mới</button>
                </div>
            </form>
        </div>
    </main>
    <script>
        document.getElementById('menuToggle')?.addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('open');
        });

        function addField(containerId, placeholder, name) {
            var container = document.getElementById(containerId);
            var row = document.createElement('div');
            row.className = 'field-row';
            row.innerHTML = '<input type="text" placeholder="' + placeholder + '" name="' + name + '"><button type="button" class="btn-remove" onclick="removeField(this)">✕</button>';
            container.appendChild(row);
        }

        function removeField(btn) {
            var row = btn.parentElement;
            var container = row.parentElement;
            if (container.children.length > 1) {
                row.remove();
            } else {
                alert('Cần ít nhất 1 trường!');
            }
        }

        function addColorUpload() {
            var container = document.getElementById('colorUploadContainer');
            var item = document.createElement('div');
            item.className = 'color-upload-item';
            item.innerHTML = `
                <input type="text" placeholder="Tên màu (VD: Đen, Trắng...)" name="color_names[]">
                <div class="upload-area-mini" onclick="this.querySelector('input[type=file]').click()">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <span>Chọn ảnh</span>
                    <input type="file" name="color_images[]" accept="image/*" onchange="previewColorImage(this)">
                    <span class="file-name"></span>
                </div>
                <button type="button" class="btn-remove-color" onclick="removeColorUpload(this)">✕</button>
            `;
            container.appendChild(item);
        }

        function removeColorUpload(btn) {
            var item = btn.parentElement;
            var container = item.parentElement;
            if (container.children.length > 1) {
                item.remove();
            } else {
                alert('Cần ít nhất 1 ảnh màu!');
            }
        }

        function previewColorImage(input) {
            var file = input.files[0];
            var parent = input.parentElement;
            var fileNameSpan = parent.querySelector('.file-name');
            var oldPreview = parent.querySelector('.image-preview-mini');
            if (oldPreview) oldPreview.remove();
            if (file) {
                fileNameSpan.textContent = file.name;
                var reader = new FileReader();
                reader.onload = function(e) {
                    var previewDiv = document.createElement('div');
                    previewDiv.className = 'image-preview-mini';
                    previewDiv.innerHTML = '<img src="' + e.target.result + '" alt="Color preview">';
                    parent.appendChild(previewDiv);
                };
                reader.readAsDataURL(file);
            } else {
                fileNameSpan.textContent = '';
            }
        }

        document.getElementById('mainImageInput')?.addEventListener('change', function(e) {
            var file = this.files[0];
            if (file) {
                document.getElementById('mainImageName').textContent = file.name;
                var reader = new FileReader();
                reader.onload = function(event) {
                    var preview = document.getElementById('mainImagePreview');
                    preview.innerHTML = '<div class="image-preview-item"><img src="' + event.target.result + '" alt="Main image"></div>';
                };
                reader.readAsDataURL(file);
            }
        });

        document.querySelectorAll('.upload-area, .upload-area-mini').forEach(function(area) {
            area.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.style.borderColor = '#e30613';
                this.style.background = '#fef0f0';
            });
            area.addEventListener('dragleave', function(e) {
                e.preventDefault();
                this.style.borderColor = '#e0e0e0';
                this.style.background = '#fff';
            });
            area.addEventListener('drop', function(e) {
                e.preventDefault();
                this.style.borderColor = '#e0e0e0';
                this.style.background = '#fff';
                var input = this.querySelector('input[type="file"]');
                if (input) {
                    input.files = e.dataTransfer.files;
                    input.dispatchEvent(new Event('change'));
                }
            });
        });
    </script>
</body>
</html>