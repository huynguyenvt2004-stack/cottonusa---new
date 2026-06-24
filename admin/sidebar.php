<?php
$current_page = basename($_SERVER['PHP_SELF']);
$fullname = $_SESSION['admin_fullname'] ?? 'Admin';
?>
<aside class="sidebar" id="sidebar">
    <!-- ===== LOGO ===== -->
    <div class="sidebar-brand">
        <a href="home.php" class="brand-link">
            <img src="../images/logo.avif" alt="CottonUSA" class="brand-logo">
            
        </a>
    </div>
    
    <nav class="sidebar-nav">
        <!-- Trang chính -->
        <a href="home.php" class="<?php echo $current_page === 'home.php' ? 'active' : ''; ?>">
            <i class="fas fa-store"></i> Trang chính
        </a>
        
        <div class="nav-label">TỔNG QUAN</div>
        <a href="dashboard.php" class="<?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-chart-pie"></i> Thống kê
        </a>
        <a href="products.php" class="<?php echo $current_page === 'products.php' ? 'active' : ''; ?>">
            <i class="fas fa-tshirt"></i> Sản phẩm
        </a>
        <a href="orders.php" class="<?php echo $current_page === 'orders.php' ? 'active' : ''; ?>">
            <i class="fas fa-shopping-cart"></i> Đơn hàng
        </a>
        
        <div class="nav-label">NỘI DUNG</div>
        <a href="statistics.php" class="<?php echo $current_page === 'statistics.php' ? 'active' : ''; ?>">
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