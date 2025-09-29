<?php
require_once 'includes/config.php';

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error_message = 'กรุณากรอก Username และ Password';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND status = 'active'");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];

                if ($user['role'] === 'admin') {
                    header('Location: pages/admin/dashboard.php');
                } else {
                    header('Location: pages/user/dashboard.php');
                }
                exit();
            } else {
                $error_message = 'Username หรือ Password ไม่ถูกต้อง';
            }
        } catch (PDOException $e) {
            $error_message = 'เกิดข้อผิดพลาดในการเข้าสู่ระบบ';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - เข้าสู่ระบบ</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <h1 class="login-title"><?php echo SITE_NAME; ?></h1>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-error">
                    <?php echo h($error_message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" class="form-control"
                           value="<?php echo h($_POST['username'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
                    เข้าสู่ระบบ
                </button>
            </form>

            <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid #eee; font-size: 0.9rem; color: #666;">
                <strong>บัญชีทดสอบ:</strong><br>
                Admin: admin / admin123<br>
                User: john_doe / user123
            </div>
        </div>
    </div>
</body>
</html>