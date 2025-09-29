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
               CONCAT(z.zone_name, '-', r.room_number) as room_name,
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

    // ดึงประวัติสัญญาเก่า
    $stmt = $pdo->prepare("
        SELECT c.*,
               CONCAT(z.zone_name, '-', r.room_number) as room_name
        FROM contracts c
        JOIN rooms r ON c.room_id = r.room_id
        JOIN zones z ON r.zone_id = z.zone_id
        WHERE c.user_id = ? AND c.status IN ('expired', 'terminated')
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $contract_history = $stmt->fetchAll();

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

<!-- ประวัติสัญญาเก่า -->
<?php if (!empty($contract_history)): ?>
<div class="card">
    <div class="card-header">
        <h2 class="card-title">ประวัติสัญญาเก่า (<?php echo count($contract_history); ?> รายการ)</h2>
    </div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>สัญญาเลขที่</th>
                    <th>ห้อง</th>
                    <th>ค่าเช่า</th>
                    <th>วันเริ่มสัญญา</th>
                    <th>วันสิ้นสุด</th>
                    <th>สถานะ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($contract_history as $contract): ?>
                <tr>
                    <td>C<?php echo str_pad($contract['contract_id'], 4, '0', STR_PAD_LEFT); ?></td>
                    <td><?php echo h($contract['room_name']); ?></td>
                    <td><?php echo formatMoney($contract['rental_price']); ?></td>
                    <td><?php echo formatDate($contract['start_date']); ?></td>
                    <td><?php echo formatDate($contract['end_date']); ?></td>
                    <td>
                        <span class="status-badge status-<?php echo $contract['status']; ?>">
                            <?php
                            $status_text = [
                                'expired' => 'หมดอายุ',
                                'terminated' => 'ยุติแล้ว'
                            ];
                            echo $status_text[$contract['status']] ?? $contract['status'];
                            ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- สถิติส่วนตัว -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">สถิติ</h2>
    </div>
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?php echo $current_contract ? '1' : '0'; ?></div>
            <div class="stat-label">ห้องปัจจุบัน</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo count($contract_history); ?></div>
            <div class="stat-label">ประวัติสัญญา</div>
        </div>
        <div class="stat-card">
            <div class="stat-number">
                <?php
                $total_contracts = count($contract_history) + ($current_contract ? 1 : 0);
                echo $total_contracts;
                ?>
            </div>
            <div class="stat-label">สัญญาทั้งหมด</div>
        </div>
        <div class="stat-card">
            <div class="stat-number">
                <?php
                echo formatDate($user_info['created_at']);
                ?>
            </div>
            <div class="stat-label">วันที่สมัครสมาชิก</div>
        </div>
    </div>
</div>

<style>
.stats-grid .stat-card:last-child .stat-number {
    font-size: 1.2rem;
    line-height: 1.2;
}
</style>

<?php require_once '../../includes/footer.php'; ?>