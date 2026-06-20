<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <title>Cotton USA</title>
</head>
<body>
    <header>
        <div class="logo">
            <img src="images/logo.avif" alt="Cotton USA">
        </div>
        <ul class="MENU">
            <li><a href="index.php">TRANG CHỦ</a></li>
            <li class="has-submenu">
                <a href="quan.php">QUẦN <span class="arrow">▼</span></a>
                <ul class="sub-menu">
                    <li><a href="quan.php?type=ong-suong">Quần Ống Suông</a></li>
                    <li><a href="quan.php?type=jogger">Quần Jogger</a></li>
                </ul>
            </li>
            <li class="has-submenu">
                <a href="sweater.php">SWEATERS <span class="arrow">▼</span></a>
                <ul class="sub-menu">
                    <li><a href="sweater.php?id=US001">University Sweater</a></li>
                    <li><a href="sweater.php?id=CS001">Car Sweater</a></li>
                    <li><a href="sweater.php?id=FS001">Floral Sweater</a></li>
                </ul>
            </li>
            <li class="has-submenu">
                <a href="hoodies.php">HOODIES <span class="arrow">▼</span></a>
                <ul class="sub-menu">
                    <li><a href="hoodies.php?id=BH001">Basic Hoodie</a></li>
                    <li><a href="hoodies.php?id=PH001">Porsche Hoodie</a></li>
                    <li><a href="hoodies.php?id=USAH001">USA Hoodie</a></li>
                </ul>
            </li>
            <li class="has-submenu">
                <a href="aothun.php">ÁO THUN <span class="arrow">▼</span></a>
                <ul class="sub-menu">
                    <li><a href="aothun.php">Áo Thun ngắn tay <span class="arrow">▼</span></a>
                        <ul class="sub-menu">
                            <li><a href="aothun.php?id=W001">Áo Thun Watch</a></li>
                            <li><a href="aothun.php?id=POR001">Áo Thun Poscher</a></li>
                            <li><a href="aothun.php?id=USA001">Áo Thun USA</a></li>
                        </ul>
                    </li>
                    <li><a href="aothundaitay.php">Áo Thun dài tay <span class="arrow">▼</span></a>
                        <ul class="sub-menu">
                            <li><a href="aothundaitay.php?id=RP001">Áo Thun Dài Tay Rapper</a></li>
                            <li><a href="aothundaitay.php?id=POR001">Áo Thun Dài Tay Poscher</a></li>
                            <li><a href="aothundaitay.php?id=USA001">Áo Thun Dài Tay USA</a></li>
                        </ul>
                    </li>
                </ul>
            </li>
        </ul>
        <div class="other">
            <div class="search-icon">
                <input type="text" id="searchInput" placeholder="Tìm kiếm sản phẩm...">
                <i class="fas fa-search"></i>
            </div>
            <div class="cart-wrapper">
                <button class="cart-icon-btn" id="cartBtn"><i class="fas fa-shopping-bag"></i><span class="cart-badge" id="cartBadge">0</span></button>
                <div class="cart-dropdown" id="cartDropdown">
                    <div class="cart-dropdown-header"><span>Giỏ hàng</span><span id="cartItemCount">0 sản phẩm</span></div>
                    <div class="cart-items-list" id="cartItemsList"><div class="cart-empty-msg"><i class="fas fa-shopping-bag"></i>Giỏ hàng trống</div></div>
                    <div class="cart-dropdown-footer" id="cartFooter" style="display:none">
                        <div class="cart-total-row"><span>Tổng cộng:</span><span class="cart-total-amount" id="cartTotal">0₫</span></div>
                        <button class="cart-checkout-btn" onclick="window.location.href='giaohang.html'">Thanh toán ngay →</button>
                    </div>
                </div>
            </div>
            <div class="auth-wrapper">
                <button class="auth-icon-btn" id="authBtn"><i class="fas fa-user"></i></button>
                <div class="auth-dropdown" id="authDropdown">
                    <div id="authLoggedOut">
                        <div class="auth-tabs"><button class="auth-tab active" onclick="switchTab('login')">Đăng nhập</button><button class="auth-tab" onclick="switchTab('register')">Đăng ký</button></div>
                        <div class="auth-form-panel" id="panelLogin">
                            <div class="auth-error" id="loginError"></div>
                            <div class="auth-field"><label>Email</label><input type="email" id="loginEmail" placeholder="example@email.com"></div>
                            <div class="auth-field"><label>Mật khẩu</label><input type="password" id="loginPassword" placeholder="••••••••"></div>
                            <button class="auth-submit-btn" onclick="doLogin()">Đăng nhập</button>
                        </div>
                        <div class="auth-form-panel" id="panelRegister" style="display:none">
                            <div class="auth-error" id="registerError"></div>
                            <div class="auth-field"><label>Họ tên</label><input type="text" id="regName" placeholder="Nguyễn Văn A"></div>
                            <div class="auth-field"><label>Email</label><input type="email" id="regEmail" placeholder="example@email.com"></div>
                            <div class="auth-field"><label>Mật khẩu</label><input type="password" id="regPassword" placeholder="Tối thiểu 6 ký tự"></div>
                            <button class="auth-submit-btn" onclick="doRegister()">Tạo tài khoản</button>
                        </div>
                    </div>
                    <div id="authLoggedIn" style="display:none">
                        <div class="auth-avatar" id="authAvatar">A</div>
                        <div class="auth-username" id="authUsername"></div>
                        <div class="auth-useremail" id="authUserEmail"></div>
                        <button class="auth-logout-btn" onclick="doLogout()">Đăng xuất</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="dropdown-overlay" id="dropdownOverlay"></div>
    </header>