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

// Kết nối database
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'cottonusa';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

// Lấy danh sách sản phẩm chi tiết với số lượng theo size + màu
$products = [];
$result = $conn->query("
    SELECT 
        p.id, 
        p.name, 
        p.category, 
        p.price, 
        p.main_image,
        ps.size_name,
        ps.color_name,
        ps.stock
    FROM products p
    LEFT JOIN product_stock ps ON p.id = ps.product_id
    ORDER BY p.id, ps.size_name, ps.color_name
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}

// Xử lý cập nhật số lượng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_stock'])) {
    $product_id = $_POST['product_id'] ?? '';
    $size_name = $_POST['size_name'] ?? '';
    $color_name = $_POST['color_name'] ?? '';
    $new_stock = (int)($_POST['new_stock'] ?? 0);
    
    // Kiểm tra xem bản ghi đã tồn tại chưa
    $check = $conn->query("SELECT id FROM product_stock WHERE product_id = '$product_id' AND size_name = '$size_name' AND color_name = '$color_name'");
    
    if ($check->num_rows > 0) {
        // Cập nhật
        $stmt = $conn->prepare("UPDATE product_stock SET stock = ? WHERE product_id = ? AND size_name = ? AND color_name = ?");
        $stmt->bind_param("isss", $new_stock, $product_id, $size_name, $color_name);
    } else {
        // Thêm mới
        $stmt = $conn->prepare("INSERT INTO product_stock (product_id, size_name, color_name, stock) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $product_id, $size_name, $color_name, $new_stock);
    }
    
    if ($stmt->execute()) {
        $success = "✅ Cập nhật số lượng thành công!";
        header('Location: products.php?success=1');
        exit;
    } else {
        $error = "❌ Lỗi: " . $conn->error;
    }
    $stmt->close();
}

$conn->close();

// Nhóm sản phẩm theo ID
$grouped_products = [];
foreach ($products as $p) {
    $id = $p['id'];
    if (!isset($grouped_products[$id])) {
        $grouped_products[$id] = [
            'id' => $p['id'],
            'name' => $p['name'],
            'category' => $p['category'],
            'price' => $p['price'],
            'main_image' => $p['main_image'],
            'variants' => []
        ];
    }
    if (!empty($p['size_name']) && !empty($p['color_name'])) {
        $grouped_products[$id]['variants'][] = [
            'size' => $p['size_name'],
            'color' => $p['color_name'],
            'stock' => $p['stock'] ?? 0
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý sản phẩm - CottonUSA</title>
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

        /* ===== SIDEBAR MÀU TRẮNG ===== */
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

        /* ===== MAIN CONTENT ===== */
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
        .page-header .date { font-size: 13px; color: #888; }

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
        .btn-danger { background: #ef4444; color: #fff; }
        .btn-danger:hover { background: #dc2626; }
        .btn-sm { padding: 6px 14px; font-size: 12px; border-radius: 8px; }
        .btn-xs { padding: 4px 10px; font-size: 11px; border-radius: 6px; }

        .toolbar {
            background: #fff;
            border-radius: 14px;
            padding: 16px 20px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .search-box {
            display: flex;
            align-items: center;
            background: #f5f6fa;
            border-radius: 10px;
            padding: 0 14px;
            border: 2px solid transparent;
            flex: 1;
            max-width: 400px;
        }

        .search-box:focus-within { border-color: #e30613; background: #fff; }
        .search-box input {
            border: none;
            background: transparent;
            padding: 10px 12px;
            font-size: 14px;
            outline: none;
            width: 100%;
            font-family: inherit;
        }
        .search-box i { color: #aaa; font-size: 14px; }

        .product-card {
            background: #fff;
            border-radius: 14px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 16px;
        }

        .product-card .product-header {
            display: flex;
            align-items: center;
            gap: 16px;
            cursor: pointer;
            padding: 4px 0;
        }

        .product-card .product-header img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #e8e8e8;
            background: #fafafa;
        }

        .product-card .product-header .info {
            flex: 1;
        }

        .product-card .product-header .info .name {
            font-size: 16px;
            font-weight: 600;
            color: #1a1a2e;
        }

        .product-card .product-header .info .meta {
            font-size: 13px;
            color: #888;
            margin-top: 2px;
        }

        .product-card .product-header .info .meta .price {
            color: #e30613;
            font-weight: 600;
        }

        .product-card .product-header .toggle-icon {
            font-size: 18px;
            color: #aaa;
            transition: transform 0.3s;
        }

        .product-card .product-header .toggle-icon.open { transform: rotate(180deg); }

        .variant-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            margin-top: 12px;
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

        .color-dot {
            display: inline-block;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border: 1px solid rgba(0,0,0,0.1);
            vertical-align: middle;
            margin-right: 6px;
        }

        .size-badge {
            display: inline-block;
            padding: 2px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            background: #e8f0fe;
            color: #1a73e8;
        }

        .stock-badge {
            display: inline-block;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .stock-in-stock { background: #dcfce7; color: #16a34a; }
        .stock-low { background: #fef3c7; color: #d97706; }
        .stock-out { background: #fee2e2; color: #dc2626; }

        .stock-form {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .stock-form input {
            width: 80px;
            padding: 6px 10px;
            border: 2px solid #e8e8e8;
            border-radius: 6px;
            font-size: 14px;
            outline: none;
            text-align: center;
        }

        .stock-form input:focus { border-color: #e30613; }

        .stock-form .btn-update {
            padding: 6px 14px;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            background: #e30613;
            color: #fff;
            transition: background 0.2s;
        }

        .stock-form .btn-update:hover { background: #c70510; }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        .empty-state i {
            font-size: 48px;
            display: block;
            margin-bottom: 16px;
            color: #ddd;
        }

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

        .product-grid {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        /* ===== RESPONSIVE ===== */
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
            .product-header { flex-wrap: wrap; }
            .variant-table { font-size: 12px; }
            .variant-table thead th, .variant-table tbody td { padding: 6px 8px; }
            .stock-form input { width: 60px; }
            .menu-toggle { display: block; }
        }
    </style>
</head>
<body>

    <!-- ===== SIDEBAR ===== -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <a href="home.php" class="brand-link">
                <img src="../images/logo.avif" alt="CottonUSA" class="brand-logo">
            </a>
        </div>
        <nav class="sidebar-nav">
            <a href="home.php">
                <i class="fas fa-store"></i> Trang chính
            </a>
            <div class="nav-label">Tổng quan</div>
            <a href="dashboard.php">
                <i class="fas fa-chart-pie"></i> Thống kê
            </a>
            <a href="products.php" class="active">
                <i class="fas fa-tshirt"></i> Sản phẩm
            </a>
            <a href="orders.php">
                <i class="fas fa-shopping-cart"></i> Đơn hàng
            </a>
            <div class="nav-label">Nội dung</div>
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
        <div class="page-header">
            <div style="display:flex;align-items:center;gap:12px;">
                <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
                <h1>👕 Quản lý <span>Sản phẩm</span></h1>
            </div>
            <div>
                <span class="date"><i class="far fa-calendar-alt"></i> <?php echo date('d/m/Y'); ?></span>
                <a href="add-product.php" class="btn btn-primary" style="margin-left:12px;">
                    <i class="fas fa-plus"></i> Thêm sản phẩm
                </a>
            </div>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">✅ Cập nhật số lượng thành công!</div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Toolbar -->
        <div class="toolbar">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Tìm kiếm theo mã hoặc tên sản phẩm...">
            </div>
            <div>
                <button class="btn btn-secondary btn-sm" onclick="location.reload()">
                    <i class="fas fa-sync-alt"></i> Làm mới
                </button>
                <span style="font-size:13px;color:#888;margin-left:12px;" id="resultCount">
                    <?php echo count($grouped_products); ?> sản phẩm
                </span>
            </div>
        </div>

        <!-- Product List -->
        <div class="product-grid" id="productGrid">
            <?php if (empty($grouped_products)): ?>
                <div class="empty-state">
                    <i class="fas fa-box-open"></i>
                    <p>Chưa có sản phẩm nào</p>
                    <span style="font-size:13px;color:#bbb;">Nhấn "Thêm sản phẩm" để tạo sản phẩm mới</span>
                </div>
            <?php else: ?>
                <?php foreach ($grouped_products as $product): ?>
                    <?php 
                    $total_stock = 0;
                    foreach ($product['variants'] as $v) {
                        $total_stock += (int)$v['stock'];
                    }
                    
                    // Xử lý đường dẫn ảnh
                    $image_path = '';
                    if (!empty($product['main_image'])) {
                        if (file_exists('../uploads/' . $product['main_image'])) {
                            $image_path = '../uploads/' . $product['main_image'];
                        } elseif (file_exists('../' . $product['main_image'])) {
                            $image_path = '../' . $product['main_image'];
                        }
                    }
                    ?>
                    <div class="product-card" data-id="<?php echo $product['id']; ?>" data-name="<?php echo strtolower($product['name']); ?>">
                        <div class="product-header" onclick="toggleProduct(this)">
                            <?php if ($image_path): ?>
                                <img src="<?php echo $image_path; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" onerror="this.src='../images/no-image.png'">
                            <?php else: ?>
                                <img src="../images/no-image.png" alt="No image">
                            <?php endif; ?>
                            <div class="info">
                                <div class="name"><?php echo htmlspecialchars($product['name']); ?></div>
                                <div class="meta">
                                    <span>Mã: <strong><?php echo htmlspecialchars($product['id']); ?></strong></span>
                                    <span style="margin-left:12px;">Danh mục: <?php echo htmlspecialchars($product['category']); ?></span>
                                    <span style="margin-left:12px;" class="price"><?php echo number_format($product['price']); ?>đ</span>
                                    <span style="margin-left:12px;">Tồn kho: <strong><?php echo $total_stock; ?></strong></span>
                                </div>
                            </div>
                            <div class="toggle-icon">▼</div>
                        </div>
                        <div class="variant-content" style="display:none; padding-top:12px;">
                            <table class="variant-table">
                                <thead>
                                    <tr>
                                        <th style="width:80px;">Size</th>
                                        <th>Màu</th>
                                        <th style="width:100px;">Số lượng</th>
                                        <th style="width:120px;">Trạng thái</th>
                                        <th style="width:180px;">Cập nhật</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($product['variants'])): ?>
                                        <tr>
                                            <td colspan="5" style="text-align:center;color:#999;padding:20px;">
                                                Chưa có biến thể (size/màu)
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($product['variants'] as $variant): 
                                            $stock = (int)$variant['stock'];
                                            $colorHex = [
                                                'Đen' => '#1a1a1a',
                                                'Trắng' => '#ffffff',
                                                'Kem' => '#f5f5dc',
                                                'Hồng' => '#f8bbd0',
                                                'Navy' => '#1a237e',
                                                'Xám' => '#9e9e9e',
                                                'Xanh' => '#2196f3',
                                                'Đỏ' => '#dc2626',
                                                'Vàng' => '#f59e0b',
                                                'Tím' => '#7c3aed'
                                            ];
                                            $hex = $colorHex[$variant['color']] ?? '#cccccc';
                                            
                                            if ($stock <= 0) {
                                                $status_class = 'stock-out';
                                                $status_text = 'Hết hàng';
                                            } elseif ($stock <= 5) {
                                                $status_class = 'stock-low';
                                                $status_text = 'Sắp hết';
                                            } else {
                                                $status_class = 'stock-in-stock';
                                                $status_text = 'Còn hàng';
                                            }
                                        ?>
                                        <tr>
                                            <td><span class="size-badge"><?php echo htmlspecialchars($variant['size']); ?></span></td>
                                            <td>
                                                <span class="color-dot" style="background:<?php echo $hex; ?>;<?php echo $variant['color'] == 'Trắng' ? 'border-color:#ddd;' : ''; ?>"></span>
                                                <?php echo htmlspecialchars($variant['color']); ?>
                                            </td>
                                            <td><strong><?php echo $stock; ?></strong></td>
                                            <td><span class="stock-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                                            <td>
                                                <form method="POST" class="stock-form">
                                                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                    <input type="hidden" name="size_name" value="<?php echo htmlspecialchars($variant['size']); ?>">
                                                    <input type="hidden" name="color_name" value="<?php echo htmlspecialchars($variant['color']); ?>">
                                                    <input type="number" name="new_stock" value="<?php echo $stock; ?>" min="0" required>
                                                    <button type="submit" name="update_stock" class="btn-update">
                                                        <i class="fas fa-save"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // Toggle sidebar mobile
        document.getElementById('menuToggle')?.addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('open');
        });

        // Toggle product variants
        function toggleProduct(header) {
            const content = header.nextElementSibling;
            const icon = header.querySelector('.toggle-icon');
            if (content.style.display === 'none') {
                content.style.display = 'block';
                icon.classList.add('open');
            } else {
                content.style.display = 'none';
                icon.classList.remove('open');
            }
        }

        // Search filter
        document.getElementById('searchInput')?.addEventListener('input', function() {
            const keyword = this.value.toLowerCase().trim();
            const cards = document.querySelectorAll('.product-card');
            let count = 0;
            
            cards.forEach(card => {
                const id = card.dataset.id.toLowerCase();
                const name = card.dataset.name.toLowerCase();
                const match = !keyword || id.includes(keyword) || name.includes(keyword);
                card.style.display = match ? 'block' : 'none';
                if (match) count++;
            });
            
            document.getElementById('resultCount').textContent = count + ' sản phẩm';
        });

        // Tự động mở product đầu tiên
        document.addEventListener('DOMContentLoaded', function() {
            const firstHeader = document.querySelector('.product-header');
            if (firstHeader) {
                toggleProduct(firstHeader);
            }
        });
    </script>
</body>
</html>