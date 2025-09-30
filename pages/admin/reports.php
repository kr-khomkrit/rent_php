<?php
require_once '../../includes/config.php';
requireAdmin();

$page_title = 'รายงาน';

try {
    // รายงานสถิติทั่วไป
    $stmt = $pdo->query("SELECT COUNT(*) as total_rooms FROM rooms");
    $total_rooms = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) as occupied_rooms FROM rooms WHERE status = 'occupied'");
    $occupied_rooms = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) as total_users FROM users WHERE role = 'user' AND status = 'active'");
    $total_users = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) as users_with_room FROM users WHERE role = 'user' AND has_room = 1 AND status = 'active'");
    $users_with_room = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) as active_contracts FROM contracts WHERE status = 'active'");
    $active_contracts = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT SUM(rental_price) as total_rental_income FROM contracts WHERE status = 'active'");
    $total_rental_income = $stmt->fetchColumn() ?: 0;

    // รายงานตามโซน
    $stmt = $pdo->query("
        SELECT z.zone_name,
               COUNT(r.room_id) as total_rooms,
               SUM(CASE WHEN r.status = 'occupied' THEN 1 ELSE 0 END) as occupied_rooms,
               SUM(CASE WHEN r.status = 'available' THEN 1 ELSE 0 END) as available_rooms,
               SUM(CASE WHEN r.status = 'maintenance' THEN 1 ELSE 0 END) as maintenance_rooms,
               COALESCE(SUM(c.rental_price), 0) as zone_income
        FROM zones z
        LEFT JOIN rooms r ON z.zone_id = r.zone_id
        LEFT JOIN contracts c ON r.room_id = c.room_id AND c.status = 'active'
        GROUP BY z.zone_id, z.zone_name
        ORDER BY z.zone_name
    ");
    $zone_stats = $stmt->fetchAll();

    // รายชื่อผู้เช่าปัจจุบัน
    $stmt = $pdo->query("
        SELECT CONCAT(u.first_name, ' ', u.last_name) as tenant_name,
               u.phone,
               CONCAT(z.zone_name, '-', r.room_number) as room_name,
               c.rental_price,
               c.start_date,
               c.end_date,
               DATEDIFF(c.end_date, CURDATE()) as days_left
        FROM contracts c
        JOIN users u ON c.user_id = u.user_id
        JOIN rooms r ON c.room_id = r.room_id
        JOIN zones z ON r.zone_id = z.zone_id
        WHERE c.status = 'active'
        ORDER BY z.zone_name, r.room_number
    ");
    $current_tenants = $stmt->fetchAll();

    // ผู้ใช้ที่ไม่มีห้อง
    $stmt = $pdo->query("
        SELECT CONCAT(first_name, ' ', last_name) as user_name,
               phone,
               created_at
        FROM users
        WHERE role = 'user' AND has_room = 0 AND status = 'active'
        ORDER BY created_at DESC
    ");
    $users_without_room = $stmt->fetchAll();

    // สัญญาที่ใกล้หมดอายุ (30 วัน)
    $stmt = $pdo->query("
        SELECT CONCAT(u.first_name, ' ', u.last_name) as tenant_name,
               u.phone,
               CONCAT(z.zone_name, '-', r.room_number) as room_name,
               c.end_date,
               DATEDIFF(c.end_date, CURDATE()) as days_left
        FROM contracts c
        JOIN users u ON c.user_id = u.user_id
        JOIN rooms r ON c.room_id = r.room_id
        JOIN zones z ON r.zone_id = z.zone_id
        WHERE c.status = 'active' AND c.end_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
        ORDER BY c.end_date ASC
    ");
    $expiring_contracts = $stmt->fetchAll();

} catch (PDOException $e) {
    $error_message = 'เกิดข้อผิดพลาดในการโหลดข้อมูล: ' . $e->getMessage();
}

require_once '../../includes/header.php';
?>

<h1 class="page-title">รายงาน</h1>

<?php if (isset($error_message)): ?>
    <div class="alert alert-error"><?php echo h($error_message); ?></div>
<?php endif; ?>

<!-- สถิติทั่วไป -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">สถิติทั่วไป</h2>
    </div>
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
            <div class="stat-number"><?php echo ($total_rooms - $occupied_rooms); ?></div>
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
            <div class="stat-number"><?php echo formatMoney($total_rental_income); ?></div>
            <div class="stat-label">รายได้ค่าเช่า/เดือน</div>
        </div>
    </div>
</div>

<!-- รายงานตามโซน -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">รายงานตามโซน</h2>
    </div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>โซน</th>
                    <th>ห้องทั้งหมด</th>
                    <th>มีผู้เช่า</th>
                    <th>ห้องว่าง</th>
                    <th>ซ่อมแซม</th>
                    <th>อัตราการเช่า (%)</th>
                    <th>รายได้รวม/เดือน</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($zone_stats as $zone): ?>
                <tr>
                    <td><strong>โซน <?php echo h($zone['zone_name']); ?></strong></td>
                    <td><?php echo $zone['total_rooms']; ?></td>
                    <td><?php echo $zone['occupied_rooms']; ?></td>
                    <td><?php echo $zone['available_rooms']; ?></td>
                    <td><?php echo $zone['maintenance_rooms']; ?></td>
                    <td>
                        <?php
                        $occupancy_rate = $zone['total_rooms'] > 0 ? ($zone['occupied_rooms'] / $zone['total_rooms']) * 100 : 0;
                        echo number_format($occupancy_rate, 1) . '%';
                        ?>
                    </td>
                    <td><?php echo formatMoney($zone['zone_income']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- สัญญาที่ใกล้หมดอายุ -->
<?php if (!empty($expiring_contracts)): ?>
<div class="card">
    <div class="card-header">
        <h2 class="card-title">สัญญาที่ใกล้หมดอายุ (30 วันข้างหน้า) - <?php echo count($expiring_contracts); ?> รายการ</h2>
    </div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>ผู้เช่า</th>
                    <th>เบอร์โทร</th>
                    <th>ห้อง</th>
                    <th>วันสิ้นสุดสัญญา</th>
                    <th>จำนวนวันที่เหลือ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($expiring_contracts as $contract): ?>
                <tr>
                    <td><?php echo h($contract['tenant_name']); ?></td>
                    <td><?php echo h($contract['phone']); ?></td>
                    <td><?php echo h($contract['room_name']); ?></td>
                    <td><?php echo formatDate($contract['end_date']); ?></td>
                    <td>
                        <?php if ($contract['days_left'] < 0): ?>
                            <span style="color: red; font-weight: bold;">หมดอายุแล้ว</span>
                        <?php elseif ($contract['days_left'] <= 7): ?>
                            <span style="color: red; font-weight: bold;"><?php echo $contract['days_left']; ?> วัน</span>
                        <?php elseif ($contract['days_left'] <= 14): ?>
                            <span style="color: orange; font-weight: bold;"><?php echo $contract['days_left']; ?> วัน</span>
                        <?php else: ?>
                            <span style="color: #666;"><?php echo $contract['days_left']; ?> วัน</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- รายชื่อผู้เช่าปัจจุบัน -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">รายชื่อผู้เช่าปัจจุบัน (<?php echo count($current_tenants); ?> คน)</h2>
    </div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>ผู้เช่า</th>
                    <th>เบอร์โทร</th>
                    <th>ห้อง</th>
                    <th>ค่าเช่า</th>
                    <th>วันเริ่มสัญญา</th>
                    <th>วันสิ้นสุดสัญญา</th>
                    <th>สถานะ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($current_tenants)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; color: #666;">ไม่มีผู้เช่าปัจจุบัน</td>
                </tr>
                <?php else: ?>
                    <?php foreach ($current_tenants as $tenant): ?>
                    <tr>
                        <td><?php echo h($tenant['tenant_name']); ?></td>
                        <td><?php echo h($tenant['phone']); ?></td>
                        <td><?php echo h($tenant['room_name']); ?></td>
                        <td><?php echo formatMoney($tenant['rental_price']); ?></td>
                        <td><?php echo formatDate($tenant['start_date']); ?></td>
                        <td><?php echo formatDate($tenant['end_date']); ?></td>
                        <td>
                            <?php if ($tenant['days_left'] < 0): ?>
                                <span class="status-badge" style="background-color: #dc3545; color: white;">หมดอายุ</span>
                            <?php elseif ($tenant['days_left'] <= 30): ?>
                                <span class="status-badge status-warning">ใกล้หมดอายุ</span>
                            <?php else: ?>
                                <span class="status-badge status-active">ปกติ</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ผู้ใช้ที่ไม่มีห้อง -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">ผู้ใช้ที่ไม่มีห้อง (<?php echo count($users_without_room); ?> คน)</h2>
    </div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>ชื่อ-นามสกุล</th>
                    <th>เบอร์โทร</th>
                    <th>วันที่สมัครสมาชิก</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users_without_room)): ?>
                <tr>
                    <td colspan="3" style="text-align: center; color: #666;">ไม่มีผู้ใช้ที่ไม่มีห้อง</td>
                </tr>
                <?php else: ?>
                    <?php foreach ($users_without_room as $user): ?>
                    <tr>
                        <td><?php echo h($user['user_name']); ?></td>
                        <td><?php echo h($user['phone']); ?></td>
                        <td><?php echo formatDate($user['created_at']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
