<?php
require_once '../../includes/config.php';
requireAdmin();

$page_title = 'แดชบอร์ด';

// ดึงสถิติข้อมูล
try {
    // จำนวนห้องทั้งหมด
    $stmt = $pdo->query("SELECT COUNT(*) as total_rooms FROM rooms");
    $total_rooms = $stmt->fetchColumn();

    // จำนวนห้องที่มีผู้เช่า
    $stmt = $pdo->query("SELECT COUNT(*) as occupied_rooms FROM rooms WHERE status = 'occupied'");
    $occupied_rooms = $stmt->fetchColumn();

    // จำนวนห้องว่าง
    $available_rooms = $total_rooms - $occupied_rooms;

    // จำนวน User ทั้งหมด
    $stmt = $pdo->query("SELECT COUNT(*) as total_users FROM users WHERE role = 'user' AND status = 'active'");
    $total_users = $stmt->fetchColumn();

    // จำนวน User ที่มีห้องปัจจุบัน
    $stmt = $pdo->query("SELECT COUNT(*) as users_with_room FROM users WHERE role = 'user' AND has_room = 1 AND status = 'active'");
    $users_with_room = $stmt->fetchColumn();

    // จำนวนสัญญาที่ยังใช้งานอยู่
    $stmt = $pdo->query("SELECT COUNT(*) as active_contracts FROM contracts WHERE status = 'active'");
    $active_contracts = $stmt->fetchColumn();

    // สถิติบิลเดือนปัจจุบัน
    $current_month = date('Y-m-01');
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) as total_bills,
            SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_bills,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_bills,
            SUM(CASE WHEN status = 'paid' THEN total_amount ELSE 0 END) as revenue_this_month,
            SUM(CASE WHEN status = 'pending' THEN total_amount ELSE 0 END) as pending_amount
        FROM utility_bills
        WHERE billing_month = ?
    ");
    $stmt->execute([$current_month]);
    $bills_stats = $stmt->fetch();

    // ดึงข้อมูลห้องแต่ละโซน
    $stmt = $pdo->query("
        SELECT z.zone_name, r.room_id, r.room_number, r.status,
               CONCAT(u.first_name, ' ', u.last_name) as tenant_name
        FROM zones z
        LEFT JOIN rooms r ON z.zone_id = r.zone_id
        LEFT JOIN contracts c ON r.room_id = c.room_id AND c.status = 'active'
        LEFT JOIN users u ON c.user_id = u.user_id
        ORDER BY z.zone_name, r.room_number
    ");
    $rooms_by_zone = $stmt->fetchAll();

    // จัดกลุ่มห้องตามโซน
    $zones = [];
    foreach ($rooms_by_zone as $room) {
        $zones[$room['zone_name']][] = $room;
    }

} catch (PDOException $e) {
    $error_message = 'เกิดข้อผิดพลาดในการโหลดข้อมูล: ' . $e->getMessage();
}

require_once '../../includes/header.php';
?>

<h1 class="page-title">แดชบอร์ดผู้ดูแลระบบ</h1>

<?php if (isset($error_message)): ?>
    <div class="alert alert-error"><?php echo h($error_message); ?></div>
<?php endif; ?>

<!-- สถิติทั่วไป -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-number"><?php echo $total_rooms; ?></div>
        <div class="stat-label">ห้องทั้งหมด</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?php echo $occupied_rooms; ?></div>
        <div class="stat-label">ห้องที่มีผู้เช่า</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?php echo $available_rooms; ?></div>
        <div class="stat-label">ห้องว่าง</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?php echo $total_users; ?></div>
        <div class="stat-label">ผู้ใช้ทั้งหมด</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?php echo $users_with_room; ?></div>
        <div class="stat-label">ผู้ใช้ที่มีห้อง</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?php echo $active_contracts; ?></div>
        <div class="stat-label">สัญญาที่ใช้งานอยู่</div>
    </div>
</div>

<!-- สถิติรายได้เดือนนี้ -->
<div class="card" style="margin-bottom: 1.5rem; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
    <div class="card-header" style="background: transparent; border-bottom: 1px solid rgba(255,255,255,0.2);">
        <h2 class="card-title" style="color: white; margin: 0;">💰 รายได้เดือนนี้ - <?php echo formatBillingMonth($current_month); ?></h2>
    </div>
    <div class="stats-grid" style="margin: 0;">
        <div class="stat-card" style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2);">
            <div class="stat-number" style="color: white;"><?php echo $bills_stats['total_bills'] ?: 0; ?></div>
            <div class="stat-label" style="color: rgba(255,255,255,0.9);">บิลทั้งหมด</div>
        </div>
        <div class="stat-card" style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2);">
            <div class="stat-number" style="color: #90ee90;"><?php echo $bills_stats['paid_bills'] ?: 0; ?></div>
            <div class="stat-label" style="color: rgba(255,255,255,0.9);">ชำระแล้ว</div>
        </div>
        <div class="stat-card" style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2);">
            <div class="stat-number" style="color: #ffd700;"><?php echo $bills_stats['pending_bills'] ?: 0; ?></div>
            <div class="stat-label" style="color: rgba(255,255,255,0.9);">รอชำระ</div>
        </div>
        <div class="stat-card" style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2);">
            <div class="stat-number" style="color: white; font-size: 2rem;">฿<?php echo formatMoney($bills_stats['revenue_this_month'] ?: 0); ?></div>
            <div class="stat-label" style="color: rgba(255,255,255,0.9);">รายได้ที่ได้รับ</div>
        </div>
        <div class="stat-card" style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2);">
            <div class="stat-number" style="color: #ffa500; font-size: 2rem;">฿<?php echo formatMoney($bills_stats['pending_amount'] ?: 0); ?></div>
            <div class="stat-label" style="color: rgba(255,255,255,0.9);">ยอดค้างรับ</div>
        </div>
        <div class="stat-card" style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2);">
            <div style="display: flex; align-items: center; justify-content: center; height: 100%;">
                <a href="<?php echo SITE_URL; ?>pages/admin/utility_bills.php" class="btn btn-outline" style="color: white; border-color: white;">
                    📋 จัดการบิล
                </a>
            </div>
        </div>
    </div>
</div>

<!-- แสดงห้องแต่ละโซน -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">สถานะห้องแต่ละโซน</h2>
    </div>
    <div class="zone-grid">
        <?php foreach ($zones as $zone_name => $zone_rooms): ?>
        <div class="zone-card">
            <div class="zone-header">
                <h3 class="zone-name">โซน <?php echo h($zone_name); ?></h3>
                <span style="font-size: 0.9rem; color: #666;">
                    <?php
                    $zone_occupied = 0;
                    $zone_total = count($zone_rooms);
                    foreach ($zone_rooms as $room) {
                        if ($room['status'] === 'occupied') $zone_occupied++;
                    }
                    echo $zone_occupied . '/' . $zone_total;
                    ?>
                </span>
            </div>
            <div class="zone-rooms">
                <?php foreach ($zone_rooms as $room): ?>
                <div class="room-item room-<?php echo $room['status']; ?>"
                     title="<?php echo h($room['room_number']); ?> - <?php echo $room['status'] === 'occupied' && $room['tenant_name'] ? h($room['tenant_name']) : ucfirst($room['status']); ?>"
                     onclick="goToRoomZone('<?php echo h($zone_name); ?>')"
                     style="cursor: pointer;">
                    <?php echo h($room['room_number']); ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
function goToRoomZone(zoneName) {
    // ไปที่หน้าจัดการห้องพร้อมพารามิเตอร์โซน
    window.location.href = 'rooms.php?zone=' + encodeURIComponent(zoneName);
}
</script>

<?php require_once '../../includes/footer.php'; ?>