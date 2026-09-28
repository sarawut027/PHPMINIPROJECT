<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/classes/User.php';

// หากล็อกอินอยู่แล้ว ให้ redirect ตามสิทธิ์ทันที
if (isLoggedIn()) {
    if (isAdmin()) {
        header('Location: admin/product-list.php');
    } else {
        header('Location: pos.php');
    }
    exit();
}

$errorMessage = '';
if (isset($_SESSION['flash_error'])) {
    $errorMessage = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $errorMessage = 'กรุณากรอกชื่อผู้ใช้งานและรหัสผ่านให้ครบถ้วน';
    } else {
        try {
            $user = User::authenticate($username, $password);
            if ($user) {
                $_SESSION['user_id'] = $user->getId();
                $_SESSION['username'] = $user->getUsername();
                $_SESSION['full_name'] = $user->getFullName();
                $_SESSION['role'] = $user->getRole();

                if ($user->getRole() === 'admin') {
                    header('Location: admin/product-list.php');
                } else {
                    header('Location: pos.php');
                }
                exit();
            } else {
                $errorMessage = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';
            }
        } catch (Exception $e) {
            $errorMessage = 'เกิดข้อผิดพลาดของระบบ: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - Dispensary POS</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-header">
                <div class="login-logo">🌿</div>
                <h1 class="login-title">Dispensary POS</h1>
                <p class="login-subtitle">ระบบบริหารจัดการและจุดจำหน่ายเวชภัณฑ์สมุนไพร</p>
            </div>

            <?php if (!empty($errorMessage)): ?>
                <div class="alert alert-danger">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <span><?= htmlspecialchars($errorMessage) ?></span>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST" id="loginForm">
                <div class="form-group">
                    <label for="username" class="form-label">ชื่อผู้ใช้งาน (Username) <span class="required">*</span></label>
                    <input type="text" id="username" name="username" class="form-control" required placeholder="เช่น admin หรือ staff" autofocus value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">รหัสผ่าน (Password) <span class="required">*</span></label>
                    <input type="password" id="password" name="password" class="form-control" required placeholder="กรอกรหัสผ่าน">
                </div>

                <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; margin-top: 8px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                    เข้าสู่ระบบ
                </button>
            </form>

            <div class="demo-account-hint">
                <div style="font-weight: 600; color: var(--color-primary-dark); margin-bottom: 6px;">🔑 บัญชีทดสอบระบบ:</div>
                <div style="display: flex; justify-content: space-between; gap: 8px; margin-top: 6px;">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="quickFill('admin', 'admin123')">
                        ผู้จัดการ (Admin)
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="quickFill('staff', 'staff123')">
                        พนักงานขาย (Staff)
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function quickFill(user, pass) {
            document.getElementById('username').value = user;
            document.getElementById('password').value = pass;
        }
    </script>
</body>
</html>
