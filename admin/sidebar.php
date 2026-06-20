<?php
$current_page = basename($_SERVER['PHP_SELF']);
$fullname = $_SESSION['admin_fullname'] ?? 'Admin';
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand" style="text-align:center; padding:20px 0; border-bottom:1px solid rgba(255,255,255,0.06);">
        <a href="home.php" style="display:block; text-decoration:none;">
            <img src="../images/logo.avif" alt="CottonUSA" style="height:50px; width:auto; display:block; margin:0 auto; cursor:pointer;">
        </a>
    </div>
    <nav class="sidebar-nav">
        <!-- Trang chính -->
        <a href="home.php" class="<?php echo $current_page === 'home.php' ? 'active' : ''; ?>" style="display:flex; align-items:center; gap:14px; padding:12px 24px; color:rgba(255,255,255,0.7); text-decoration:none; font-size:14px; transition:all 0.2s; border-left:3px solid transparent; background:rgba(255,255,255,0.05); margin-bottom:4px;">
            <i class="fas fa-store" style="width:20px; text-align:center;"></i> Trang chính
        </a>
        <div class="nav-label" style="font-size:11px; text-transform:uppercase; color:rgba(255,255,255,0.25); padding:8px 24px;">Tổng quan</div>
        <a href="dashboard.php" class="<?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-chart-pie"></i> Thống kê
        </a>
        <a href="products.php" class="<?php echo $current_page === 'products.php' ? 'active' : ''; ?>">
            <i class="fas fa-tshirt"></i> Sản phẩm
        </a>
        <a href="orders.php" class="<?php echo $current_page === 'orders.php' ? 'active' : ''; ?>">
            <i class="fas fa-shopping-cart"></i> Đơn hàng
        </a>
        
        <div class="nav-label" style="font-size:11px; text-transform:uppercase; color:rgba(255,255,255,0.25); padding:12px 24px 8px 24px;">Nội dung</div>
        <a href="statistics.php" class="<?php echo $current_page === 'statistics.php' ? 'active' : ''; ?>">
            <i class="fas fa-chart-line"></i> Thống kê doanh thu
        </a>
    </nav>
    <div class="sidebar-footer" style="padding:16px 24px; border-top:1px solid rgba(255,255,255,0.06);">
        <div class="user-info" style="display:flex; align-items:center; gap:12px; margin-bottom:10px;">
            <div class="avatar" style="width:36px; height:36px; border-radius:50%; background:#e30613; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:14px;"><?php echo strtoupper(substr($fullname, 0, 1)); ?></div>
            <div>
                <div class="name" style="font-size:14px; font-weight:600;"><?php echo htmlspecialchars($fullname); ?></div>
                <div class="role" style="font-size:12px; color:rgba(255,255,255,0.4);">Administrator</div>
            </div>
        </div>
        <a href="logout.php" style="color:rgba(255,255,255,0.5); text-decoration:none; font-size:13px; display:flex; align-items:center; gap:8px;">
            <i class="fas fa-sign-out-alt"></i> Đăng xuất
        </a>
    </div>
</aside>