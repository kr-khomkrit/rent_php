<?php
if (!isLoggedIn()) {
    header('Location: ' . SITE_URL . 'login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? h($page_title) . ' - ' : ''; ?><?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/style.css">
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <h1><?php echo SITE_NAME; ?></h1>
                </div>
                <div class="user-info">
                    <span>สวัสดี, <?php echo h($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></span>
                    <span>(<?php echo $_SESSION['role'] === 'admin' ? 'ผู้ดูแลระบบ' : 'ผู้ใช้'; ?>)</span>
                    <a href="<?php echo SITE_URL; ?>logout.php" class="btn btn-outline">ออกจากระบบ</a>
                </div>
            </div>
        </div>
    </header>

    <?php if (isAdmin()): ?>
    <nav>
        <div class="container">
            <ul class="nav-links">
                <li><a href="<?php echo SITE_URL; ?>pages/admin/dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">หน้าหลัก</a></li>
                <li><a href="<?php echo SITE_URL; ?>pages/admin/users.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>">จัดการผู้ใช้</a></li>
                <li><a href="<?php echo SITE_URL; ?>pages/admin/rooms.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'rooms.php' ? 'active' : ''; ?>">จัดการห้อง</a></li>
                <li><a href="<?php echo SITE_URL; ?>pages/admin/contracts.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'contracts.php' ? 'active' : ''; ?>">จัดการสัญญา</a></li>
                <li><a href="<?php echo SITE_URL; ?>pages/admin/utility_bills.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'utility_bills.php' ? 'active' : ''; ?>">แจ้งค่าน้ำไฟ</a></li>
                <li><a href="<?php echo SITE_URL; ?>pages/admin/reports.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">รายงาน</a></li>
            </ul>
        </div>
    </nav>
    <?php else: ?>
    <nav>
        <div class="container">
            <ul class="nav-links">
                <li><a href="<?php echo SITE_URL; ?>pages/user/dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">หน้าหลัก</a></li>
                <li><a href="<?php echo SITE_URL; ?>pages/user/my_bills.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'my_bills.php' ? 'active' : ''; ?>">บิลของฉัน</a></li>
            </ul>
        </div>
    </nav>
    <?php endif; ?>

    <main>
        <div class="container">