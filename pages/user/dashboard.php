<?php
require_once '../../includes/config.php';
requireLogin();

// ตรวจสอบว่าเป็น user เท่านั้น
if (isAdmin()) {
    header('Location: ../admin/dashboard.php');
    exit();
}

$page_title = 'แดชบอร์ดผู้ใช้';
$user_id = $_SESSION['user_id'];

try {
    // ดึงข้อมูลผู้ใช้
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user_info = $stmt->fetch();

    // ดึงข้อมูลสัญญาปัจจุบัน
    $stmt = $pdo->prepare("
        SELECT c.*,
               r.room_number as room_name,
               r.water_rate, r.electricity_rate
        FROM contracts c
        JOIN rooms r ON c.room_id = r.room_id
        JOIN zones z ON r.zone_id = z.zone_id
        WHERE c.user_id = ? AND c.status = 'active'
        ORDER BY c.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $current_contract = $stmt->fetch();

    // ดึงบิลเดือนปัจจุบัน (ถ้ามีสัญญา active)
    $current_month_bill = null;
    if ($current_contract) {
        $current_month = date('Y-m-01');
        $stmt = $pdo->prepare("
            SELECT *
            FROM utility_bills
            WHERE contract_id = ? AND billing_month = ?
        ");
        $stmt->execute([$current_contract['contract_id'], $current_month]);
        $current_month_bill = $stmt->fetch();
    }

} catch (PDOException $e) {
    $error_message = 'เกิดข้อผิดพลาดในการโหลดข้อมูล: ' . $e->getMessage();
}

require_once '../../includes/header.php';
?>

<h1 class="page-title">แดชบอร์ดผู้ใช้</h1>

<?php if (isset($error_message)): ?>
    <div class="alert alert-error"><?php echo h($error_message); ?></div>
<?php endif; ?>

<!-- ข้อมูลส่วนตัว -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">ข้อมูลส่วนตัว</h2>
    </div>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
        <div>
            <strong>ชื่อ-นามสกุล:</strong><br>
            <span style="font-size: 1.1rem;"><?php echo h($user_info['first_name'] . ' ' . $user_info['last_name']); ?></span>
        </div>
        <div>
            <strong>Username:</strong><br>
            <span style="font-size: 1.1rem;"><?php echo h($user_info['username']); ?></span>
        </div>
        <div>
            <strong>เบอร์โทรศัพท์:</strong><br>
            <span style="font-size: 1.1rem;"><?php echo h($user_info['phone']) ?: '-'; ?></span>
        </div>
        <div>
            <strong>เบอร์ติดต่อฉุกเฉิน:</strong><br>
            <span style="font-size: 1.1rem;"><?php echo h($user_info['emergency_contact']) ?: '-'; ?></span>
        </div>
    </div>
</div>

<!-- บิลเดือนปัจจุบัน -->
<?php if ($current_contract && $current_month_bill): ?>
<div class="card" style="border-left: 5px solid <?php echo $current_month_bill['status'] === 'paid' ? '#28a745' : '#ffc107'; ?>; margin-bottom: 1.5rem;">
    <div class="card-header" style="background: <?php echo $current_month_bill['status'] === 'paid' ? '#d4edda' : '#fff3cd'; ?>; border-radius: 0;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h2 class="card-title" style="margin: 0;">💰 บิลเดือนนี้ - <?php echo formatBillingMonth($current_month_bill['billing_month']); ?></h2>
            <span class="status-badge status-<?php echo $current_month_bill['status']; ?>" style="font-size: 1.1rem;">
                <?php
                $status_text = ['pending' => 'รอชำระ', 'paid' => 'ชำระแล้ว', 'overdue' => 'เกินกำหนด'];
                echo $status_text[$current_month_bill['status']] ?? $current_month_bill['status'];
                ?>
            </span>
        </div>
    </div>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1.5rem; margin-bottom: 1rem;">
        <div>
            <strong>ค่าเช่า:</strong><br>
            <span style="font-size: 1.3rem; color: #667eea;">฿<?php echo formatMoney($current_month_bill['rental_price']); ?></span>
        </div>
        <div>
            <strong>ค่าน้ำ:</strong><br>
            <span style="font-size: 1.1rem;"><?php echo $current_month_bill['water_unit']; ?> หน่วย</span><br>
            <span style="font-size: 1.3rem; color: #667eea;">฿<?php echo formatMoney($current_month_bill['water_total']); ?></span>
        </div>
        <div>
            <strong>ค่าไฟ:</strong><br>
            <span style="font-size: 1.1rem;"><?php echo $current_month_bill['electricity_unit']; ?> หน่วย</span><br>
            <span style="font-size: 1.3rem; color: #667eea;">฿<?php echo formatMoney($current_month_bill['electricity_total']); ?></span>
        </div>
        <div>
            <strong>ยอดรวมทั้งหมด:</strong><br>
            <span style="font-size: 1.8rem; font-weight: bold; color: #28a745;">฿<?php echo formatMoney($current_month_bill['total_amount']); ?></span>
        </div>
    </div>
    <div style="text-align: center; margin-top: 1rem;">
        <a href="<?php echo SITE_URL; ?>pages/user/my_bills.php" class="btn btn-primary">ดูรายละเอียดบิล</a>
    </div>
</div>
<?php elseif ($current_contract && !$current_month_bill): ?>
<div class="card" style="background: #f8f9fa; margin-bottom: 1.5rem;">
    <div style="text-align: center; padding: 2rem;">
        <div style="font-size: 3rem; margin-bottom: 1rem;">📋</div>
        <h3>ยังไม่มีบิลสำหรับเดือนนี้</h3>
        <p style="color: #666;">รอผู้ดูแลระบบแจ้งบิลค่าน้ำค่าไฟ</p>
    </div>
</div>
<?php endif; ?>

<!-- ข้อมูลห้องปัจจุบัน -->
<?php if ($current_contract): ?>
<div class="card">
    <div class="card-header">
        <h2 class="card-title">ข้อมูลห้องปัจจุบัน</h2>
    </div>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
        <div>
            <strong>ห้อง:</strong><br>
            <span style="font-size: 1.2rem; font-weight: 600; color: #667eea;"><?php echo h($current_contract['room_name']); ?></span>
        </div>
        <div>
            <strong>ค่าเช่า:</strong><br>
            <span style="font-size: 1.2rem; font-weight: 600; color: #28a745;"><?php echo formatMoney($current_contract['rental_price']); ?></span>
        </div>
        <div>
            <strong>ค่าน้ำ:</strong><br>
            <span style="font-size: 1.1rem;"><?php echo formatMoney($current_contract['water_rate']); ?> ต่อหน่วย</span>
        </div>
        <div>
            <strong>ค่าไฟ:</strong><br>
            <span style="font-size: 1.1rem;"><?php echo formatMoney($current_contract['electricity_rate']); ?> ต่อหน่วย</span>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem;">
        <div>
            <strong>วันเริ่มสัญญา:</strong><br>
            <span style="font-size: 1.1rem;"><?php echo formatDate($current_contract['start_date']); ?></span>
        </div>
        <div>
            <strong>วันสิ้นสุดสัญญา:</strong><br>
            <span style="font-size: 1.1rem;">
                <?php
                echo formatDate($current_contract['end_date']);

                // คำนวณวันที่เหลือ
                $days_left = (strtotime($current_contract['end_date']) - strtotime('today')) / (60 * 60 * 24);
                if ($days_left > 0) {
                    echo '<br><small style="color: #666;">(อีก ' . ceil($days_left) . ' วัน)</small>';
                } else {
                    echo '<br><small style="color: red; font-weight: bold;">(หมดอายุแล้ว)</small>';
                }
                ?>
            </span>
        </div>
        <div>
            <strong>สัญญาเลขที่:</strong><br>
            <span style="font-size: 1.1rem;">C<?php echo str_pad($current_contract['contract_id'], 4, '0', STR_PAD_LEFT); ?></span>
        </div>
        <div>
            <strong>วันที่ทำสัญญา:</strong><br>
            <span style="font-size: 1.1rem;"><?php echo formatDate($current_contract['created_at']); ?></span>
        </div>
    </div>

    <?php if (!empty($current_contract['contract_terms'])): ?>
    <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #eee;">
        <strong>ข้อกำหนดสัญญา:</strong>
        <div style="background: #f8f9fa; padding: 1rem; border-radius: 5px; margin-top: 0.5rem; white-space: pre-wrap;">
            <?php echo h($current_contract['contract_terms']); ?>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="card">
    <div class="card-header">
        <h2 class="card-title">ข้อมูลห้อง</h2>
    </div>
    <div style="text-align: center; padding: 2rem; color: #666;">
        <div style="font-size: 3rem; margin-bottom: 1rem;">🏠</div>
        <h3>คุณยังไม่มีห้องเช่าในขณะนี้</h3>
        <p>กรุณาติดต่อผู้ดูแลระบบเพื่อขอเช่าห้อง</p>
    </div>
</div>
<?php endif; ?>

<style>
.stats-grid .stat-card:last-child .stat-number {
    font-size: 1.2rem;
    line-height: 1.2;
}
</style>

<?php require_once '../../includes/footer.php'; ?>