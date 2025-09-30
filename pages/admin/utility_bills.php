<?php
require_once '../../includes/config.php';
requireAdmin();

$page_title = 'แจ้งค่าน้ำค่าไฟ';
$success_message = '';
$error_message = '';

// เดือนที่เลือก (default = เดือนปัจจุบัน)
// ใช้ dropdown แยกเดือนและปี
$selected_year = isset($_GET['year']) ? intval($_GET['year']) : (int)date('Y');
$selected_month_num = isset($_GET['month']) ? intval($_GET['month']) : (int)date('m');
$selected_month = sprintf('%04d-%02d-01', $selected_year, $selected_month_num);

// การดำเนินการต่างๆ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_bill') {
        // สร้างบิลใหม่
        $contract_id = intval($_POST['contract_id']);
        $billing_month = $_POST['billing_month'];
        $water_current = intval($_POST['water_current']);
        $electricity_current = intval($_POST['electricity_current']);
        $water_rate = floatval($_POST['water_rate']);
        $electricity_rate = floatval($_POST['electricity_rate']);
        $rental_price = floatval($_POST['rental_price']);
        $other_fees = floatval($_POST['other_fees'] ?? 0);
        $note = trim($_POST['note'] ?? '');

        // ดึงเลขมิเตอร์เดือนก่อน
        $previous = getPreviousMeterReading($pdo, $contract_id);

        try {
            // ตรวจสอบว่ามีบิลเดือนนี้อยู่แล้วหรือไม่
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM utility_bills WHERE contract_id = ? AND billing_month = ?");
            $stmt->execute([$contract_id, $billing_month]);

            if ($stmt->fetchColumn() > 0) {
                $error_message = 'มีบิลสำหรับเดือนนี้อยู่แล้ว กรุณาใช้การแก้ไขแทน';
            } else {
                // สร้างบิลใหม่
                $stmt = $pdo->prepare("
                    INSERT INTO utility_bills
                    (contract_id, billing_month, water_previous, water_current, water_rate,
                     electricity_previous, electricity_current, electricity_rate,
                     rental_price, other_fees, note, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $contract_id, $billing_month,
                    $previous['water_previous'], $water_current, $water_rate,
                    $previous['electricity_previous'], $electricity_current, $electricity_rate,
                    $rental_price, $other_fees, $note, $_SESSION['user_id']
                ]);
                $success_message = 'สร้างบิลเรียบร้อยแล้ว';
            }
        } catch (PDOException $e) {
            $error_message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
        }
    } elseif ($action === 'update_bill') {
        // แก้ไขบิล
        $bill_id = intval($_POST['bill_id']);
        $water_current = intval($_POST['water_current']);
        $electricity_current = intval($_POST['electricity_current']);
        $other_fees = floatval($_POST['other_fees'] ?? 0);
        $note = trim($_POST['note'] ?? '');

        try {
            $stmt = $pdo->prepare("
                UPDATE utility_bills
                SET water_current = ?, electricity_current = ?, other_fees = ?, note = ?
                WHERE bill_id = ?
            ");
            $stmt->execute([$water_current, $electricity_current, $other_fees, $note, $bill_id]);
            $success_message = 'อัปเดตบิลเรียบร้อยแล้ว';
        } catch (PDOException $e) {
            $error_message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
        }
    } elseif ($action === 'mark_paid') {
        // อัปเดตสถานะเป็นชำระแล้ว
        $bill_id = intval($_POST['bill_id']);
        $paid_date = $_POST['paid_date'] ?? date('Y-m-d');
        $payment_method = $_POST['payment_method'] ?? 'cash';
        $payment_note = trim($_POST['payment_note'] ?? '');

        try {
            $stmt = $pdo->prepare("
                UPDATE utility_bills
                SET status = 'paid', paid_date = ?, payment_method = ?, payment_note = ?
                WHERE bill_id = ?
            ");
            $stmt->execute([$paid_date, $payment_method, $payment_note, $bill_id]);
            $success_message = 'บันทึกการชำระเงินเรียบร้อยแล้ว';
        } catch (PDOException $e) {
            $error_message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
        }
    } elseif ($action === 'delete_bill') {
        // ลบบิล
        $bill_id = intval($_POST['bill_id']);

        try {
            $stmt = $pdo->prepare("DELETE FROM utility_bills WHERE bill_id = ?");
            $stmt->execute([$bill_id]);
            $success_message = 'ลบบิลเรียบร้อยแล้ว';
        } catch (PDOException $e) {
            $error_message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
        }
    }
}

// ดึงข้อมูลสัญญาที่ active และอยู่ในช่วงเวลาของเดือนที่เลือก
try {
    $stmt = $pdo->prepare("
        SELECT c.contract_id, c.user_id, c.room_id, c.rental_price, c.start_date, c.end_date,
               CONCAT(u.first_name, ' ', u.last_name) as tenant_name,
               CONCAT(z.zone_name, '-', r.room_number) as room_name,
               r.water_rate, r.electricity_rate
        FROM contracts c
        JOIN users u ON c.user_id = u.user_id
        JOIN rooms r ON c.room_id = r.room_id
        JOIN zones z ON r.zone_id = z.zone_id
        WHERE c.status = 'active'
              AND c.start_date <= LAST_DAY(?)
              AND (c.end_date >= ? OR c.end_date IS NULL)
        ORDER BY room_name
    ");
    $stmt->execute([$selected_month, $selected_month]);
    $active_contracts = $stmt->fetchAll();

    // ดึงบิลที่มีอยู่แล้วในเดือนที่เลือก
    $stmt = $pdo->prepare("
        SELECT ub.*,
               CONCAT(u.first_name, ' ', u.last_name) as tenant_name,
               CONCAT(z.zone_name, '-', r.room_number) as room_name
        FROM utility_bills ub
        JOIN contracts c ON ub.contract_id = c.contract_id
        JOIN users u ON c.user_id = u.user_id
        JOIN rooms r ON c.room_id = r.room_id
        JOIN zones z ON r.zone_id = z.zone_id
        WHERE ub.billing_month = ?
        ORDER BY room_name
    ");
    $stmt->execute([$selected_month]);
    $existing_bills = $stmt->fetchAll();

    // สร้าง map สำหรับเช็คว่ามีบิลแล้วหรือยัง
    $bills_map = [];
    foreach ($existing_bills as $bill) {
        $bills_map[$bill['contract_id']] = $bill;
    }

} catch (PDOException $e) {
    $error_message = 'เกิดข้อผิดพลาดในการโหลดข้อมูล: ' . $e->getMessage();
}

require_once '../../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
    <h1 class="page-title" style="margin: 0;">แจ้งค่าน้ำค่าไฟ</h1>

    <!-- เลือกเดือน/ปี -->
    <form method="GET" onsubmit="return convertDateToMonthYear()" style="display: flex; gap: 0.5rem; align-items: center;">
        <input type="hidden" name="month" id="month_value">
        <input type="hidden" name="year" id="year_value">

        <label style="font-weight: 600;">เลือกเดือน/ปี:</label>
        <input type="date" id="date_picker" class="form-control" style="width: 180px;"
               value="<?php echo $selected_month; ?>" required>

        <button type="submit" class="btn btn-primary">กรอง</button>
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

<?php if ($success_message): ?>
    <div class="alert alert-success"><?php echo h($success_message); ?></div>
<?php endif; ?>

<?php if ($error_message): ?>
    <div class="alert alert-error"><?php echo h($error_message); ?></div>
<?php endif; ?>

<!-- สรุปสถิติ -->
<div class="stats-grid" style="margin-bottom: 1.5rem;">
    <div class="stat-card">
        <div class="stat-number"><?php echo count($active_contracts); ?></div>
        <div class="stat-label">ห้องที่มีผู้เช่า</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?php echo count($existing_bills); ?></div>
        <div class="stat-label">บิลที่แจ้งแล้ว</div>
    </div>
    <div class="stat-card">
        <div class="stat-number">
            <?php
            $paid_count = array_filter($existing_bills, fn($b) => $b['status'] === 'paid');
            echo count($paid_count);
            ?>
        </div>
        <div class="stat-label">ชำระแล้ว</div>
    </div>
    <div class="stat-card">
        <div class="stat-number">
            <?php
            $total_revenue = array_sum(array_map(fn($b) => $b['total_amount'], array_filter($existing_bills, fn($b) => $b['status'] === 'paid')));
            echo formatMoney($total_revenue);
            ?>
        </div>
        <div class="stat-label">รายได้เดือนนี้</div>
    </div>
</div>

<!-- ตารางแจ้งค่าน้ำค่าไฟ -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">รายการแจ้งค่าน้ำค่าไฟ - <?php echo formatBillingMonth($selected_month); ?></h2>
    </div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th style="width: 10%;">ห้อง</th>
                    <th style="width: 12%;">ผู้เช่า</th>
                    <th style="width: 10%;">ค่าเช่า</th>
                    <th style="width: 15%;">มิเตอร์น้ำ</th>
                    <th style="width: 15%;">มิเตอร์ไฟ</th>
                    <th style="width: 10%;">ยอดรวม</th>
                    <th style="width: 8%;">สถานะ</th>
                    <th style="width: 20%;">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($active_contracts as $contract): ?>
                <?php
                $has_bill = isset($bills_map[$contract['contract_id']]);
                $bill = $has_bill ? $bills_map[$contract['contract_id']] : null;
                $previous = getPreviousMeterReading($pdo, $contract['contract_id']);
                ?>
                <tr>
                    <td><strong><?php echo h($contract['room_name']); ?></strong></td>
                    <td><?php echo h($contract['tenant_name']); ?></td>
                    <td><?php echo formatMoney($contract['rental_price']); ?></td>

                    <?php if ($has_bill): ?>
                        <!-- แสดงบิลที่มีอยู่แล้ว -->
                        <td title="<?php echo $bill['water_previous']; ?> → <?php echo $bill['water_current']; ?>">
                            <?php echo $bill['water_unit']; ?> หน่วย<br>
                            <small style="color: #666;">฿<?php echo formatMoney($bill['water_total']); ?></small>
                        </td>
                        <td title="<?php echo $bill['electricity_previous']; ?> → <?php echo $bill['electricity_current']; ?>">
                            <?php echo $bill['electricity_unit']; ?> หน่วย<br>
                            <small style="color: #666;">฿<?php echo formatMoney($bill['electricity_total']); ?></small>
                        </td>
                        <td><strong style="color: #28a745;">฿<?php echo formatMoney($bill['total_amount']); ?></strong></td>
                        <td>
                            <span class="status-badge status-<?php echo $bill['status']; ?>">
                                <?php
                                $status_text = ['pending' => 'รอชำระ', 'paid' => 'ชำระแล้ว', 'overdue' => 'เกินกำหนด'];
                                echo $status_text[$bill['status']] ?? $bill['status'];
                                ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-warning btn-sm" onclick="editBill(<?php echo h(json_encode($bill)); ?>)">แก้ไข</button>
                            <?php if ($bill['status'] !== 'paid'): ?>
                                <button class="btn btn-success btn-sm" onclick="markPaid(<?php echo $bill['bill_id']; ?>, '<?php echo h($contract['room_name']); ?>')">ชำระ</button>
                            <?php endif; ?>
                            <button class="btn btn-danger btn-sm" onclick="deleteBill(<?php echo $bill['bill_id']; ?>, '<?php echo h($contract['room_name']); ?>')">ลบ</button>
                        </td>
                    <?php else: ?>
                        <!-- ยังไม่มีบิล ให้กรอกใหม่ -->
                        <td colspan="4" style="text-align: center; color: #999;">
                            <em>ยังไม่ได้แจ้งบิล</em>
                        </td>
                        <td>
                            <button class="btn btn-primary btn-sm" onclick="createBill(<?php echo h(json_encode($contract)); ?>, '<?php echo $selected_month; ?>', <?php echo $previous['water_previous']; ?>, <?php echo $previous['electricity_previous']; ?>)">
                                + แจ้งบิล
                            </button>
                        </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>

                <?php if (empty($active_contracts)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; padding: 2rem; color: #999;">
                        ไม่มีสัญญาที่ active ในขณะนี้
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal สร้างบิลใหม่ -->
<div id="createBillModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem; border-radius: 10px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto;">
        <h3>แจ้งบิลค่าน้ำค่าไฟ</h3>
        <form id="createBillForm" method="POST">
            <input type="hidden" name="action" value="create_bill">
            <input type="hidden" name="contract_id" id="create_contract_id">
            <input type="hidden" name="billing_month" id="create_billing_month">

            <div class="form-group">
                <label>ห้อง / ผู้เช่า</label>
                <input type="text" id="create_display_info" class="form-control" readonly>
            </div>

            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="create_rental_price">ค่าเช่า (บาท) *</label>
                        <input type="number" id="create_rental_price" name="rental_price" class="form-control" step="0.01" required>
                    </div>
                </div>
                <div class="form-col">
                    <div class="form-group">
                        <label for="create_other_fees">ค่าใช้จ่ายอื่นๆ</label>
                        <input type="number" id="create_other_fees" name="other_fees" class="form-control" step="0.01" value="0">
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="create_water_current">มิเตอร์น้ำปัจจุบัน *</label>
                        <input type="number" id="create_water_current" name="water_current" class="form-control" required>
                        <small id="water_info" style="color: #666;"></small>
                    </div>
                </div>
                <div class="form-col">
                    <div class="form-group">
                        <label for="create_water_rate">อัตราค่าน้ำ (บาท/หน่วย) *</label>
                        <input type="number" id="create_water_rate" name="water_rate" class="form-control" step="0.01" required>
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="create_electricity_current">มิเตอร์ไฟปัจจุบัน *</label>
                        <input type="number" id="create_electricity_current" name="electricity_current" class="form-control" required>
                        <small id="electricity_info" style="color: #666;"></small>
                    </div>
                </div>
                <div class="form-col">
                    <div class="form-group">
                        <label for="create_electricity_rate">อัตราค่าไฟ (บาท/หน่วย) *</label>
                        <input type="number" id="create_electricity_rate" name="electricity_rate" class="form-control" step="0.01" required>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="create_note">หมายเหตุ</label>
                <textarea id="create_note" name="note" class="form-control" rows="2"></textarea>
            </div>

            <div style="margin-top: 1.5rem; display: flex; gap: 1rem;">
                <button type="submit" class="btn btn-success">บันทึกบิล</button>
                <button type="button" class="btn btn-danger" onclick="closeCreateBillModal()">ยกเลิก</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal แก้ไขบิล -->
<div id="editBillModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem; border-radius: 10px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto;">
        <h3>แก้ไขบิล</h3>
        <form method="POST">
            <input type="hidden" name="action" value="update_bill">
            <input type="hidden" name="bill_id" id="edit_bill_id">

            <div class="form-group">
                <label>ห้อง / ผู้เช่า</label>
                <input type="text" id="edit_display_info" class="form-control" readonly>
            </div>

            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="edit_water_current">มิเตอร์น้ำปัจจุบัน *</label>
                        <input type="number" id="edit_water_current" name="water_current" class="form-control" required>
                    </div>
                </div>
                <div class="form-col">
                    <div class="form-group">
                        <label for="edit_electricity_current">มิเตอร์ไฟปัจจุบัน *</label>
                        <input type="number" id="edit_electricity_current" name="electricity_current" class="form-control" required>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="edit_other_fees">ค่าใช้จ่ายอื่นๆ</label>
                <input type="number" id="edit_other_fees" name="other_fees" class="form-control" step="0.01">
            </div>

            <div class="form-group">
                <label for="edit_note">หมายเหตุ</label>
                <textarea id="edit_note" name="note" class="form-control" rows="2"></textarea>
            </div>

            <div style="margin-top: 1.5rem; display: flex; gap: 1rem;">
                <button type="submit" class="btn btn-success">บันทึก</button>
                <button type="button" class="btn btn-danger" onclick="closeEditBillModal()">ยกเลิก</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal ชำระเงิน -->
<div id="markPaidModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem; border-radius: 10px; width: 90%; max-width: 500px;">
        <h3>บันทึกการชำระเงิน</h3>
        <form method="POST">
            <input type="hidden" name="action" value="mark_paid">
            <input type="hidden" name="bill_id" id="paid_bill_id">

            <div class="form-group">
                <label>ห้อง</label>
                <input type="text" id="paid_room_name" class="form-control" readonly>
            </div>

            <div class="form-group">
                <label for="paid_date">วันที่ชำระ *</label>
                <input type="date" id="paid_date" name="paid_date" class="form-control" required>
            </div>

            <div class="form-group">
                <label for="payment_method">วิธีการชำระ *</label>
                <select id="payment_method" name="payment_method" class="form-control" required>
                    <option value="cash">เงินสด</option>
                    <option value="transfer">โอนเงิน</option>
                    <option value="qr">QR Code</option>
                    <option value="other">อื่นๆ</option>
                </select>
            </div>

            <div class="form-group">
                <label for="payment_note">หมายเหตุ</label>
                <textarea id="payment_note" name="payment_note" class="form-control" rows="2"></textarea>
            </div>

            <div style="margin-top: 1.5rem; display: flex; gap: 1rem;">
                <button type="submit" class="btn btn-success">บันทึก</button>
                <button type="button" class="btn btn-danger" onclick="closeMarkPaidModal()">ยกเลิก</button>
            </div>
        </form>
    </div>
</div>

<script>
// Modal จัดการบิล
function createBill(contract, billingMonth, waterPrevious, electricityPrevious) {
    document.getElementById('create_contract_id').value = contract.contract_id;
    document.getElementById('create_billing_month').value = billingMonth;
    document.getElementById('create_display_info').value = contract.room_name + ' - ' + contract.tenant_name;
    document.getElementById('create_rental_price').value = contract.rental_price;
    document.getElementById('create_water_rate').value = contract.water_rate;
    document.getElementById('create_electricity_rate').value = contract.electricity_rate;
    document.getElementById('water_info').textContent = 'เลขมิเตอร์เดือนก่อน: ' + waterPrevious;
    document.getElementById('electricity_info').textContent = 'เลขมิเตอร์เดือนก่อน: ' + electricityPrevious;

    document.getElementById('createBillModal').style.display = 'block';
}

function closeCreateBillModal() {
    document.getElementById('createBillModal').style.display = 'none';
    document.getElementById('createBillForm').reset();
}

function editBill(bill) {
    document.getElementById('edit_bill_id').value = bill.bill_id;
    document.getElementById('edit_display_info').value = bill.room_name + ' - ' + bill.tenant_name;
    document.getElementById('edit_water_current').value = bill.water_current;
    document.getElementById('edit_electricity_current').value = bill.electricity_current;
    document.getElementById('edit_other_fees').value = bill.other_fees;
    document.getElementById('edit_note').value = bill.note || '';

    document.getElementById('editBillModal').style.display = 'block';
}

function closeEditBillModal() {
    document.getElementById('editBillModal').style.display = 'none';
}

function markPaid(billId, roomName) {
    document.getElementById('paid_bill_id').value = billId;
    document.getElementById('paid_room_name').value = roomName;
    document.getElementById('paid_date').value = new Date().toISOString().split('T')[0];

    document.getElementById('markPaidModal').style.display = 'block';
}

function closeMarkPaidModal() {
    document.getElementById('markPaidModal').style.display = 'none';
}

function deleteBill(billId, roomName) {
    if (confirm('คุณต้องการลบบิลของห้อง ' + roomName + ' ใช่หรือไม่?\n\nการลบจะไม่สามารถกู้คืนได้')) {
        const form = document.createElement('form');
        form.method = 'POST';

        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete_bill';

        const billIdInput = document.createElement('input');
        billIdInput.type = 'hidden';
        billIdInput.name = 'bill_id';
        billIdInput.value = billId;

        form.appendChild(actionInput);
        form.appendChild(billIdInput);
        document.body.appendChild(form);
        form.submit();
    }
}

// ปิด Modal เมื่อคลิกนอกพื้นที่
document.getElementById('createBillModal').onclick = function(e) {
    if (e.target === this) closeCreateBillModal();
}
document.getElementById('editBillModal').onclick = function(e) {
    if (e.target === this) closeEditBillModal();
}
document.getElementById('markPaidModal').onclick = function(e) {
    if (e.target === this) closeMarkPaidModal();
}
</script>

<?php require_once '../../includes/footer.php'; ?>