<?php
require_once '../../includes/config.php';
requireLogin();

// ตรวจสอบว่าเป็น user เท่านั้น
if (isAdmin()) {
    header('Location: ../admin/dashboard.php');
    exit();
}

$page_title = 'บิลค่าใช้จ่ายของฉัน';
$user_id = $_SESSION['user_id'];

// เดือนที่เลือก (ถ้าไม่ได้เลือกจะแสดงทั้งหมด)
$selected_year = isset($_GET['year']) ? intval($_GET['year']) : 0;
$selected_month_num = isset($_GET['month']) ? intval($_GET['month']) : 0;
$selected_month = ($selected_year && $selected_month_num) ? sprintf('%04d-%02d-01', $selected_year, $selected_month_num) : '';

try {
    // ดึงข้อมูลสัญญาปัจจุบัน
    $stmt = $pdo->prepare("
        SELECT c.contract_id, c.rental_price,
               CONCAT(z.zone_name, '-', r.room_number) as room_name,
               r.water_rate, r.electricity_rate
        FROM contracts c
        JOIN rooms r ON c.room_id = r.room_id
        JOIN zones z ON r.zone_id = z.zone_id
        WHERE c.user_id = ? AND c.status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $current_contract = $stmt->fetch();

    if ($current_contract) {
        // ดึงบิลทั้งหมดของ user
        if ($selected_month) {
            // ดึงเฉพาะเดือนที่เลือก
            $stmt = $pdo->prepare("
                SELECT *
                FROM utility_bills
                WHERE contract_id = ? AND billing_month = ?
                ORDER BY billing_month DESC
            ");
            $stmt->execute([$current_contract['contract_id'], $selected_month]);
        } else {
            // ดึงทั้งหมด
            $stmt = $pdo->prepare("
                SELECT *
                FROM utility_bills
                WHERE contract_id = ?
                ORDER BY billing_month DESC
                LIMIT 12
            ");
            $stmt->execute([$current_contract['contract_id']]);
        }
        $bills = $stmt->fetchAll();

        // คำนวณสถิติ
        $total_bills = count($bills);
        $paid_bills = count(array_filter($bills, fn($b) => $b['status'] === 'paid'));
        $pending_bills = count(array_filter($bills, fn($b) => $b['status'] === 'pending'));
        $total_paid_amount = array_sum(array_map(fn($b) => $b['total_amount'], array_filter($bills, fn($b) => $b['status'] === 'paid')));
        $total_pending_amount = array_sum(array_map(fn($b) => $b['total_amount'], array_filter($bills, fn($b) => $b['status'] === 'pending')));
    }

} catch (PDOException $e) {
    $error_message = 'เกิดข้อผิดพลาดในการโหลดข้อมูล: ' . $e->getMessage();
}

require_once '../../includes/header.php';
?>

<h1 class="page-title">บิลค่าใช้จ่ายของฉัน</h1>

<?php if (isset($error_message)): ?>
    <div class="alert alert-error"><?php echo h($error_message); ?></div>
<?php endif; ?>

<?php if (!$current_contract): ?>
    <!-- ไม่มีห้องเช่า -->
    <div class="card">
        <div style="text-align: center; padding: 3rem; color: #666;">
            <div style="font-size: 4rem; margin-bottom: 1rem;">📋</div>
            <h2>คุณยังไม่มีห้องเช่าในขณะนี้</h2>
            <p>กรุณาติดต่อผู้ดูแลระบบเพื่อขอเช่าห้อง</p>
        </div>
    </div>
<?php else: ?>

    <!-- ข้อมูลห้อง -->
    <div class="card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; margin-bottom: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <div style="font-size: 0.9rem; opacity: 0.9; margin-bottom: 0.5rem;">ห้องของฉัน</div>
                <h2 style="margin: 0; font-size: 2rem;"><?php echo h($current_contract['room_name']); ?></h2>
                <div style="margin-top: 0.5rem; opacity: 0.9;">ค่าเช่า: ฿<?php echo formatMoney($current_contract['rental_price']); ?>/เดือน</div>
            </div>
            <div style="text-align: right;">
                <div style="font-size: 0.9rem; opacity: 0.9;">อัตราค่าน้ำ</div>
                <div style="font-size: 1.2rem; font-weight: 600;">฿<?php echo formatMoney($current_contract['water_rate']); ?>/หน่วย</div>
                <div style="font-size: 0.9rem; opacity: 0.9; margin-top: 0.5rem;">อัตราค่าไฟ</div>
                <div style="font-size: 1.2rem; font-weight: 600;">฿<?php echo formatMoney($current_contract['electricity_rate']); ?>/หน่วย</div>
            </div>
        </div>
    </div>

    <!-- สถิติ -->
    <div class="stats-grid" style="margin-bottom: 1.5rem;">
        <div class="stat-card">
            <div class="stat-number"><?php echo $total_bills; ?></div>
            <div class="stat-label">บิลทั้งหมด</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" style="color: #28a745;"><?php echo $paid_bills; ?></div>
            <div class="stat-label">ชำระแล้ว</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" style="color: #dc3545;"><?php echo $pending_bills; ?></div>
            <div class="stat-label">รอชำระ</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" style="color: #ffc107;">฿<?php echo formatMoney($total_pending_amount); ?></div>
            <div class="stat-label">ยอดค้างชำระ</div>
        </div>
    </div>

    <!-- เลือกเดือน -->
    <div class="card" style="margin-bottom: 1.5rem;">
        <form method="GET" onsubmit="return convertDateToMonthYear()" style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
            <input type="hidden" name="month" id="month_value">
            <input type="hidden" name="year" id="year_value">

            <label style="font-weight: 600;">เลือกเดือน/ปี:</label>
            <input type="date" id="date_picker" class="form-control" style="width: 180px;"
                   value="<?php echo $selected_month ?: date('Y-m-01'); ?>" required>

            <button type="submit" class="btn btn-primary">กรอง</button>
            <?php if ($selected_month): ?>
                <a href="my_bills.php" class="btn btn-outline">แสดงทั้งหมด</a>
            <?php endif; ?>
        </form>
    </div>

    <script>
    function convertDateToMonthYear() {
        const dateValue = document.getElementById('date_picker').value;
        if (!dateValue) {
            alert('กรุณาเลือกวันที่');
            return false;
        }
        const [year, month, day] = dateValue.split('-');
        document.getElementById('month_value').value = parseInt(month);
        document.getElementById('year_value').value = parseInt(year);
        return true;
    }
    </script>

    <!-- รายการบิล -->
    <?php if (empty($bills)): ?>
        <div class="card">
            <div style="text-align: center; padding: 3rem; color: #666;">
                <div style="font-size: 3rem; margin-bottom: 1rem;">📄</div>
                <h3>ยังไม่มีบิลในเดือนนี้</h3>
                <p>รอผู้ดูแลระบบแจ้งบิลค่าน้ำค่าไฟ</p>
            </div>
        </div>
    <?php else: ?>
        <!-- แสดงบิลแบบ Card -->
        <?php foreach ($bills as $bill): ?>
        <div class="card" style="margin-bottom: 1.5rem; border-left: 5px solid <?php echo $bill['status'] === 'paid' ? '#28a745' : ($bill['status'] === 'overdue' ? '#dc3545' : '#ffc107'); ?>;">
            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                <div>
                    <h3 style="margin: 0; color: #667eea;"><?php echo formatBillingMonth($bill['billing_month']); ?></h3>
                    <small style="color: #999;">วันที่แจ้ง: <?php echo formatDate($bill['created_at']); ?></small>
                </div>
                <span class="status-badge status-<?php echo $bill['status']; ?>" style="font-size: 1rem; padding: 0.5rem 1rem;">
                    <?php
                    $status_text = ['pending' => 'รอชำระ', 'paid' => 'ชำระแล้ว', 'overdue' => 'เกินกำหนด'];
                    echo $status_text[$bill['status']] ?? $bill['status'];
                    ?>
                </span>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
                <!-- ค่าเช่า -->
                <div style="background: #f8f9fa; padding: 1rem; border-radius: 8px;">
                    <div style="color: #666; font-size: 0.9rem; margin-bottom: 0.5rem;">💰 ค่าเช่า</div>
                    <div style="font-size: 1.5rem; font-weight: 600; color: #667eea;">
                        ฿<?php echo formatMoney($bill['rental_price']); ?>
                    </div>
                </div>

                <!-- ค่าน้ำ -->
                <div style="background: #f8f9fa; padding: 1rem; border-radius: 8px;">
                    <div style="color: #666; font-size: 0.9rem; margin-bottom: 0.5rem;">💧 ค่าน้ำ</div>
                    <div style="font-size: 1.2rem; font-weight: 600;">
                        <?php echo $bill['water_unit']; ?> หน่วย × ฿<?php echo formatMoney($bill['water_rate']); ?>
                    </div>
                    <div style="font-size: 1rem; color: #28a745; margin-top: 0.3rem;">
                        = ฿<?php echo formatMoney($bill['water_total']); ?>
                    </div>
                    <small style="color: #999;">มิเตอร์: <?php echo $bill['water_previous']; ?> → <?php echo $bill['water_current']; ?></small>
                </div>

                <!-- ค่าไฟ -->
                <div style="background: #f8f9fa; padding: 1rem; border-radius: 8px;">
                    <div style="color: #666; font-size: 0.9rem; margin-bottom: 0.5rem;">⚡ ค่าไฟ</div>
                    <div style="font-size: 1.2rem; font-weight: 600;">
                        <?php echo $bill['electricity_unit']; ?> หน่วย × ฿<?php echo formatMoney($bill['electricity_rate']); ?>
                    </div>
                    <div style="font-size: 1rem; color: #28a745; margin-top: 0.3rem;">
                        = ฿<?php echo formatMoney($bill['electricity_total']); ?>
                    </div>
                    <small style="color: #999;">มิเตอร์: <?php echo $bill['electricity_previous']; ?> → <?php echo $bill['electricity_current']; ?></small>
                </div>

                <?php if ($bill['other_fees'] > 0): ?>
                <!-- ค่าอื่นๆ -->
                <div style="background: #f8f9fa; padding: 1rem; border-radius: 8px;">
                    <div style="color: #666; font-size: 0.9rem; margin-bottom: 0.5rem;">📎 ค่าใช้จ่ายอื่นๆ</div>
                    <div style="font-size: 1.5rem; font-weight: 600;">
                        ฿<?php echo formatMoney($bill['other_fees']); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- ยอดรวม -->
            <div style="border-top: 2px solid #e5e7eb; padding-top: 1rem; display: flex; justify-content: space-between; align-items: center;">
                <div style="font-size: 1.2rem; font-weight: 600;">ยอดรวมทั้งหมด</div>
                <div style="font-size: 2rem; font-weight: bold; color: #667eea;">
                    ฿<?php echo formatMoney($bill['total_amount']); ?>
                </div>
            </div>

            <?php if ($bill['status'] === 'paid'): ?>
                <div style="background: #d4edda; color: #155724; padding: 1rem; border-radius: 5px; margin-top: 1rem;">
                    ✅ ชำระเงินเรียบร้อยแล้ว<br>
                    <small>วันที่: <?php echo formatDate($bill['paid_date']); ?> | วิธีการ: <?php
                    $payment_methods = ['cash' => 'เงินสด', 'transfer' => 'โอนเงิน', 'qr' => 'QR Code', 'other' => 'อื่นๆ'];
                    echo $payment_methods[$bill['payment_method']] ?? $bill['payment_method'];
                    ?></small>
                </div>
            <?php elseif ($bill['status'] === 'overdue'): ?>
                <div style="background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 5px; margin-top: 1rem;">
                    ⚠️ เกินกำหนดชำระ กรุณาติดต่อผู้ดูแลระบบ
                </div>
            <?php else: ?>
                <div style="background: #fff3cd; color: #856404; padding: 1rem; border-radius: 5px; margin-top: 1rem;">
                    ⏳ รอการชำระเงิน กรุณาชำระภายในกำหนด
                </div>
            <?php endif; ?>

            <?php if (!empty($bill['note'])): ?>
                <div style="margin-top: 1rem; padding: 1rem; background: #f8f9fa; border-radius: 5px;">
                    <strong>หมายเหตุ:</strong><br>
                    <?php echo nl2br(h($bill['note'])); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <!-- กราฟสถิติการใช้ย้อนหลัง 6 เดือน -->
        <?php if (!$selected_month && count($bills) >= 2): ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">สรุปการใช้ย้อนหลัง</h2>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>เดือน</th>
                            <th>ค่าเช่า</th>
                            <th>น้ำ (หน่วย)</th>
                            <th>ไฟ (หน่วย)</th>
                            <th>ยอดรวม</th>
                            <th>สถานะ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($bills, 0, 6) as $bill): ?>
                        <tr>
                            <td><?php echo formatBillingMonth($bill['billing_month']); ?></td>
                            <td>฿<?php echo formatMoney($bill['rental_price']); ?></td>
                            <td><?php echo $bill['water_unit']; ?> (฿<?php echo formatMoney($bill['water_total']); ?>)</td>
                            <td><?php echo $bill['electricity_unit']; ?> (฿<?php echo formatMoney($bill['electricity_total']); ?>)</td>
                            <td><strong>฿<?php echo formatMoney($bill['total_amount']); ?></strong></td>
                            <td>
                                <span class="status-badge status-<?php echo $bill['status']; ?>">
                                    <?php echo $status_text[$bill['status']] ?? $bill['status']; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

    <?php endif; ?>

<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>