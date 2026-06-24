<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// ===== KẾT NỐI DATABASE =====
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'cottonusa';  

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

$error = '';

// ===== XỬ LÝ ĐĂNG NHẬP =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if (empty($username) || empty($password)) {
        $error = 'Vui lòng nhập tên đăng nhập và mật khẩu';
    } else {
        $stmt = $conn->prepare("SELECT id, username, password, name, email, role FROM users WHERE username = ?");
        
        if ($stmt === false) {
            $error = 'Lỗi SQL: ' . $conn->error;
        } else {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                
                // So sánh mật khẩu
                $passwordCorrect = false;
                if ($password === $user['password']) {
                    $passwordCorrect = true;
                } elseif (password_verify($password, $user['password'])) {
                    $passwordCorrect = true;
                }
                
                if ($passwordCorrect) {
                    $_SESSION['admin_id'] = $user['id'];
                    $_SESSION['admin_username'] = $user['username'];
                    $_SESSION['admin_fullname'] = $user['fullname'];
                    $_SESSION['admin_role'] = $user['role'];
                    
                    header('Location: home.php');
                    exit;
                } else {
                    $error = 'Mật khẩu không đúng!';
                }
            } else {
                $error = 'Tên đăng nhập không tồn tại!';
            }
            $stmt->close();
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
    <title>Đăng nhập Admin - CottonUSA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Arial, sans-serif; 
            background: linear-gradient(135deg, #f5f6fa 0%, #e8e9f0 100%);
            display: flex; 
            justify-content: center; 
            align-items: center; 
            min-height: 100vh;
        }
        .login-box {
            background: #fff;
            padding: 40px 35px;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.12);
            width: 100%;
            max-width: 400px;
            animation: fadeIn 0.4s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .login-box .logo {
            text-align: center;
            margin-bottom: 20px;
        }
        .login-box .logo img {
            max-height: 55px;
        }
        .login-box h1 {
            text-align: center;
            font-size: 22px;
            color: #1a1a2e;
            margin-bottom: 4px;
        }
        .login-box .sub {
            text-align: center;
            color: #999;
            font-size: 13px;
            margin-bottom: 25px;
        }
        .form-group {
            margin-bottom: 18px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 13px;
            color: #333;
            margin-bottom: 5px;
        }
        .form-group .input-wrapper {
            position: relative;
        }
        .form-group .input-wrapper input {
            width: 100%;
            padding: 12px 44px 12px 14px;
            border: 2px solid #e8e8e8;
            border-radius: 10px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.3s, box-shadow 0.3s;
            background: #fafafa;
        }
        .form-group .input-wrapper input:focus {
            border-color: #e30613;
            box-shadow: 0 0 0 4px rgba(227, 6, 19, 0.08);
            background: #fff;
        }
        .form-group .input-wrapper .toggle-password {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #aaa;
            font-size: 18px;
            padding: 4px;
            transition: color 0.3s;
        }
        .form-group .input-wrapper .toggle-password:hover {
            color: #333;
        }
        .btn-login {
            width: 100%;
            padding: 14px;
            background: #e30613;
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.3s, transform 0.2s;
        }
        .btn-login:hover {
            background: #c70510;
            transform: translateY(-1px);
        }
        .btn-login:active {
            transform: translateY(0);
        }
        .error {
            background: #fff5f5;
            color: #e30613;
            padding: 10px 14px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 13px;
            border: 1px solid #fcc;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .error i { font-size: 16px; }
    </style>
</head>
<body>
    <div class="login-box">
        <div class="logo">
            <img src="../images/logo.avif" alt="CottonUSA">
        </div>
        <h1>Đăng nhập</h1>
        <p class="sub">Quản trị hệ thống CottonUSA</p>
        
        <?php if ($error): ?>
            <div class="error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" autocomplete="off">
            <div class="form-group">
                <label><i class="fas fa-user" style="margin-right:6px;color:#888;"></i> Tên đăng nhập</label>
                <div class="input-wrapper">
                    <input type="text" name="username" placeholder="Nhập tên đăng nhập" value="admin" required>
                </div>
            </div>
            <div class="form-group">
                <label><i class="fas fa-lock" style="margin-right:6px;color:#888;"></i> Mật khẩu</label>
                <div class="input-wrapper">
                    <input type="password" name="password" id="password" placeholder="Nhập mật khẩu" value="admin123" required>
                    <button type="button" class="toggle-password" id="togglePassword" title="Hiển thị mật khẩu">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt" style="margin-right:8px;"></i> Đăng nhập
            </button>
        </form>
    </div>

    <script>
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    </script>
</body>
</html>