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

// Lấy ID sản phẩm từ URL
$product_id = isset($_GET['id']) ? trim($_GET['id']) : '';

if (empty($product_id)) {
    header('Location: dashboard.php');
    exit;
}

// Lấy thông tin sản phẩm
$product = null;
$stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt->bind_param("s", $product_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $product = $result->fetch_assoc();
} else {
    header('Location: dashboard.php');
    exit;
}
$stmt->close();

// Lấy size và số lượng (không lấy màu)
$sizes = [];
$result = $conn->query("SELECT size_name, SUM(stock) as total_stock FROM product_stock WHERE product_id = '$product_id' GROUP BY size_name ORDER BY size_name");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $sizes[] = $row;
    }
}

// Xử lý cập nhật sản phẩm
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_name = trim($_POST['product_name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $price = trim($_POST['price'] ?? '');
    $new_sizes = $_POST['sizes'] ?? [];
    $new_stocks = $_POST['stocks'] ?? [];
    $new_colors = $_POST['colors'] ?? [];
    
    // Xử lý upload ảnh chính
    $main_image = $product['main_image'];
    if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] === 0) {
        $upload_dir = '../uploads/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $file_ext = pathinfo($_FILES['main_image']['name'], PATHINFO_EXTENSION);
        $main_image = $product_id . '_main.' . $file_ext;
        move_uploaded_file($_FILES['main_image']['tmp_name'], $upload_dir . $main_image);
    }
    
    // Cập nhật sản phẩm
    $stmt = $conn->prepare("UPDATE products SET name = ?, category = ?, price = ?, main_image = ? WHERE id = ?");
    $stmt->bind_param("ssdss", $product_name, $category, $price, $main_image, $product_id);
    
    if ($stmt->execute()) {
        // ===== CẬP NHẬT SỐ LƯỢNG CHO TỪNG SIZE =====
        if (!empty($new_sizes) && !empty($new_stocks)) {
            for ($i = 0; $i < count($new_sizes); $i++) {
                $size_name = trim($new_sizes[$i]);
                $stock_qty = (int)($new_stocks[$i] ?? 0);
                
                if (!empty($size_name)) {
                    // Cập nhật tất cả các màu của size này
                    $updateStock = $conn->prepare("UPDATE product_stock SET stock = ? WHERE product_id = ? AND size_name = ?");
                    $updateStock->bind_param("iss", $stock_qty, $product_id, $size_name);
                    $updateStock->execute();
                    $updateStock->close();
                }
            }
        }
        
        // ===== XỬ LÝ THÊM MÀU MỚI (nếu có) =====
        $hasNewColors = false;
        $validColors = [];
        foreach ($new_colors as $c) {
            $c = trim($c);
            if ($c != '') {
                $hasNewColors = true;
                $validColors[] = $c;
            }
        }
        
        if ($hasNewColors && count($validColors) > 0) {
            // Lấy danh sách size hiện có
            $sizesResult = $conn->query("SELECT DISTINCT size_name FROM product_stock WHERE product_id = '$product_id'");
            $existingSizes = [];
            while ($row = $sizesResult->fetch_assoc()) {
                $existingSizes[] = $row['size_name'];
            }
            
            // Nếu không có size nào, thêm size mặc định
            if (empty($existingSizes)) {
                $existingSizes = ['Mặc định'];
            }
            
            foreach ($validColors as $color) {
                // Thêm vào bảng product_colors
                $image_url = '';
                $insertColor = $conn->prepare("INSERT INTO product_colors (product_id, color_name, image_url) VALUES (?, ?, ?)");
                $insertColor->bind_param("sss", $product_id, $color, $image_url);
                $insertColor->execute();
                $insertColor->close();
                
                foreach ($existingSizes as $size) {
                    $checkExists = $conn->query("SELECT id FROM product_stock WHERE product_id = '$product_id' AND size_name = '$size' AND color_name = '$color'");
                    if ($checkExists->num_rows == 0) {
                        $insertStock = $conn->prepare("INSERT INTO product_stock (product_id, size_name, color_name, stock) VALUES (?, ?, ?, ?)");
                        $stockDefault = 0;
                        $insertStock->bind_param("sssi", $product_id, $size, $color, $stockDefault);
                        $insertStock->execute();
                        $insertStock->close();
                    }
                }
            }
        }
        
        header('Location: edit-product.php?id=' . $product_id . '&success=1');
        exit;
    } else {
        $error = '❌ Lỗi: ' . $conn->error;
    }
    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sửa sản phẩm - CottonUSA</title>
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
            background: #1a1a2e;
            color: #fff;
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            overflow-y: auto;
            z-index: 100;
        }
        .sidebar-brand {
            text-align: center;
            padding: 20px 0;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }
        .sidebar-brand a { display: block; text-decoration: none; }
        .sidebar-brand img { height: 50px; width: auto; display: block; margin: 0 auto; }
        .sidebar-nav { flex: 1; padding: 16px 0; }
        .sidebar-nav .nav-label {
            font-size: 11px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.25);
            padding: 8px 24px;
        }
        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 24px;
            color: rgba(255,255,255,0.6);
            text-decoration: none;
            font-size: 14px;
            transition: all 0.2s;
            border-left: 3px solid transparent;
        }
        .sidebar-nav a:hover { background: rgba(255,255,255,0.05); color: #fff; }
        .sidebar-nav a.active {
            background: rgba(227,6,19,0.15);
            color: #fff;
            border-left-color: #e30613;
        }
        .sidebar-nav a i { width: 20px; text-align: center; }
        .sidebar-footer {
            padding: 16px 24px;
            border-top: 1px solid rgba(255,255,255,0.08);
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
        }
        .sidebar-footer .user-info .name { font-size: 14px; font-weight: 600; }
        .sidebar-footer .user-info .role { font-size: 12px; color: rgba(255,255,255,0.4); }
        .sidebar-footer a {
            color: rgba(255,255,255,0.5);
            text-decoration: none;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
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
        .page-header h1 { font-size: 24px; color: #1a1a2e; }
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
        .form-group input, .form-group select {
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
        .form-group input:focus, .form-group select:focus {
            border-color: #e30613;
            background: #fff;
        }
        .full-width { grid-column: 1 / -1; }
        
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
        
        .variant-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            margin: 16px 0 20px 0;
        }
        .variant-table thead th {
            text-align: left;
            padding: 10px 12px;
            border-bottom: 2px solid #f0f0f0;
            color: #888;
            font-size: 12px;
            text-transform: uppercase;
            font-weight: 600;
        }
        .variant-table tbody td {
            padding: 10px 12px;
            border-bottom: 1px solid #f5f5f5;
            vertical-align: middle;
        }
        .variant-table tbody tr:hover { background: #fafafa; }
        .variant-table tbody tr:last-child td { border-bottom: none; }
        .variant-table input[type="number"] {
            width: 100px;
            padding: 6px 10px;
            border: 2px solid #e8e8e8;
            border-radius: 6px;
            font-size: 14px;
            outline: none;
            text-align: center;
        }
        .variant-table input[type="number"]:focus { border-color: #e30613; }
        .size-badge {
            display: inline-block;
            padding: 4px 16px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            background: #e8f0fe;
            color: #1a73e8;
        }
        .dynamic-fields {
            border: 2px dashed #e0e0e0;
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 12px;
            background: #fafafa;
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
            background: #fff;
        }
        .dynamic-fields .field-row input:focus { border-color: #e30613; }
        .btn-remove {
            background: #fee2e2;
            color: #dc2626;
            border: none;
            border-radius: 8px;
            padding: 6px 12px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
        }
        .btn-remove:hover { background: #fecaca; }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        /* Info box thông báo */
        .info-box {
            background: #f0f7ff;
            border: 1px solid #b8d4f0;
            border-radius: 8px;
            padding: 12px 16px;
            margin: 16px 0;
            font-size: 13px;
            color: #1a56db;
        }
        .info-box i { margin-right: 8px; }
        .info-box strong { color: #0a3d7a; }
        
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 16px; }
            .form-grid { grid-template-columns: 1fr; }
            .variant-table { font-size: 12px; }
            .variant-table input[type="number"] { width: 70px; }
        }
        .menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            padding: 4px;
        }
        @media (max-width: 768px) {
            .menu-toggle { display: block; }
        }
        .upload-area {
            border: 2px dashed #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: #fafafa;
        }
        .upload-area:hover { border-color: #e30613; background: #fef0f0; }
        .upload-area i { font-size: 30px; color: #ccc; display: block; margin-bottom: 8px; }
        .upload-area p { color: #888; font-size: 13px; }
        .upload-area input[type="file"] { display: none; }
        .image-preview {
            margin-top: 10px;
            max-width: 150px;
            border-radius: 8px;
            overflow: hidden;
            border: 2px solid #e8e8e8;
        }
        .image-preview img { width: 100%; height: auto; display: block; }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <!-- Main -->
    <main class="main-content">
        <div class="page-header">
            <div style="display:flex;align-items:center;gap:12px;">
                <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
                <h1>✏️ Sửa <span>Sản phẩm</span></h1>
            </div>
            <div>
                <a href="products.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Quay lại</a>
            </div>
        </div>

        <div class="form-card">
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">✅ Cập nhật sản phẩm thành công!</div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Info box -->
            <div class="info-box">
                <i class="fas fa-info-circle"></i>
                <strong>Số lượng nhập vào sẽ được áp dụng cho tất cả các màu của size đó.</strong>
               
            </div>

            <form method="POST" enctype="multipart/form-data">
                <!-- Thông tin cơ bản -->
                <div class="form-grid">
                    <div class="form-group">
                        <label>Mã sản phẩm</label>
                        <input type="text" value="<?php echo htmlspecialchars($product['id']); ?>" disabled style="background:#f0f0f0;">
                        <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product['id']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Danh mục <span class="required">*</span></label>
                        <select name="category" required>
                            <option value="Quần" <?php echo $product['category'] == 'Quần' ? 'selected' : ''; ?>>Quần</option>
                            <option value="Sweater" <?php echo $product['category'] == 'Sweater' ? 'selected' : ''; ?>>Sweater</option>
                            <option value="Hoodies" <?php echo $product['category'] == 'Hoodies' ? 'selected' : ''; ?>>Hoodies</option>
                            <option value="Áo Thun" <?php echo $product['category'] == 'Áo Thun' ? 'selected' : ''; ?>>Áo Thun</option>
                            <option value="Áo Thun Dài Tay" <?php echo $product['category'] == 'Áo Thun Dài Tay' ? 'selected' : ''; ?>>Áo Thun Dài Tay</option>
                        </select>
                    </div>
                    <div class="form-group full-width">
                        <label>Tên sản phẩm <span class="required">*</span></label>
                        <input type="text" name="product_name" value="<?php echo htmlspecialchars($product['name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Giá (VNĐ) <span class="required">*</span></label>
                        <input type="number" name="price" value="<?php echo $product['price']; ?>" required>
                    </div>
                </div>

                <!-- Bảng size và số lượng -->
                <table class="variant-table">
                    <thead>
                        <tr>
                            <th style="width:120px;">Size</th>
                            <th style="width:180px;">Số lượng</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($sizes)): ?>
                            <tr>
                                <td colspan="2" style="text-align:center;color:#999;padding:20px;">
                                    Chưa có size nào. Thêm mới bên dưới.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($sizes as $size): ?>
                                <tr>
                                    <td><span class="size-badge"><?php echo htmlspecialchars($size['size_name']); ?></span></td>
                                    <td>
                                        <input type="hidden" name="sizes[]" value="<?php echo htmlspecialchars($size['size_name']); ?>">
                                        <input type="number" name="stocks[]" value="<?php echo $size['total_stock']; ?>" min="0">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <!-- Thêm Size mới -->
                <div class="form-group full-width">
                    <label>Thêm size mới</label>
                    <div class="dynamic-fields" id="sizeContainer">
                        <div class="field-row">
                            <input type="text" placeholder="Nhập size (VD: S, M, L...)" name="new_sizes[]">
                            <button type="button" class="btn-remove" onclick="removeField(this)">✕</button>
                        </div>
                        <div class="field-row">
                            <input type="text" placeholder="Nhập size (VD: S, M, L...)" name="new_sizes[]">
                            <button type="button" class="btn-remove" onclick="removeField(this)">✕</button>
                        </div>
                        <div class="field-row">
                            <input type="text" placeholder="Nhập size (VD: S, M, L...)" name="new_sizes[]">
                            <button type="button" class="btn-remove" onclick="removeField(this)">✕</button>
                        </div>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="addField('sizeContainer', 'Nhập size (VD: S, M, L...)', 'new_sizes[]')">
                        <i class="fas fa-plus"></i> Thêm size
                    </button>
                    <p style="font-size:12px;color:#888;margin-top:8px;">
                        <i class="fas fa-info-circle"></i> Size mới sẽ có số lượng mặc định là 0.
                    </p>
                </div>

                <!-- Thêm Màu mới -->
                <div class="form-group full-width">
                    <label>Thêm màu mới</label>
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

                <!-- Ảnh -->
                <div class="form-group full-width">
                    <label>Ảnh chính</label>
                    <div class="upload-area" onclick="document.getElementById('mainImageInput').click()">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Nhấp để thay đổi ảnh</p>
                        <input type="file" name="main_image" id="mainImageInput" accept="image/*">
                    </div>
                    <?php if ($product['main_image']): ?>
                        <div class="image-preview">
                            <img src="../<?php echo $product['main_image']; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                        </div>
                    <?php endif; ?>
                </div>

                <div style="display:flex;gap:12px;margin-top:24px;">
                    <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Cập nhật sản phẩm</button>
                    <a href="products.php" class="btn btn-secondary"><i class="fas fa-times"></i> Hủy</a>
                </div>
            </form>
        </div>
    </main>

    <script>
        // Toggle sidebar
        document.getElementById('menuToggle')?.addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('open');
        });

        // Add field
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

        // Remove field
        function removeField(btn) {
            const row = btn.parentElement;
            const container = row.parentElement;
            if (container.children.length > 1) {
                row.remove();
            } else {
                alert('Cần ít nhất 1 trường!');
            }
        }
    </script>
</body>
</html>