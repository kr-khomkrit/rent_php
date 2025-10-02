<?php
require_once '../../includes/config.php';
requireAdmin();

$page_title = 'จัดการสัญญา';
$success_message = '';
$error_message = '';

// การดำเนินการต่างๆ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'terminate_contract') {
        // ยุติสัญญา
        $contract_id = intval($_POST['contract_id']);

        try {
            $pdo->beginTransaction();

            // ดึงข้อมูลสัญญา
            $stmt = $pdo->prepare("
                SELECT c.*, r.room_id, c.user_id
                FROM contracts c
                JOIN rooms r ON c.room_id = r.room_id
                WHERE c.contract_id = ? AND c.status = 'active'
            ");
            $stmt->execute([$contract_id]);
            $contract = $stmt->fetch();

            if (!$contract) {
                throw new Exception('ไม่พบสัญญาที่ต้องการยุติ');
            }

            // อัปเดตสถานะสัญญา
            $stmt = $pdo->prepare("UPDATE contracts SET status = 'terminated' WHERE contract_id = ?");
            $stmt->execute([$contract_id]);

            // อัปเดตสถานะห้อง
            $stmt = $pdo->prepare("UPDATE rooms SET status = 'available' WHERE room_id = ?");
            $stmt->execute([$contract['room_id']]);

            // อัปเดตสถานะผู้ใช้
            $stmt = $pdo->prepare("UPDATE users SET has_room = 0 WHERE user_id = ?");
            $stmt->execute([$contract['user_id']]);

            $pdo->commit();
            $success_message = 'ยุติสัญญาเรียบร้อยแล้ว';

        } catch (Exception $e) {
            $pdo->rollBack();
            $error_message = $e->getMessage();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error_message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
        }

    } elseif ($action === 'update_contract') {
        // อัปเดตสัญญา
        $contract_id = intval($_POST['contract_id']);
        $user_id = intval($_POST['user_id']);
        $room_id = intval($_POST['room_id']);
        $rental_price = floatval($_POST['rental_price']);
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $contract_terms = trim($_POST['contract_terms']);

        // ข้อมูลผู้เช่าที่อาจมีการแก้ไข
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $emergency_contact = trim($_POST['emergency_contact'] ?? '');

        // ข้อมูลห้อง
        $water_rate = floatval($_POST['water_rate']);
        $electricity_rate = floatval($_POST['electricity_rate']);

        try {
            $pdo->beginTransaction();

            // อัปเดตข้อมูลผู้ใช้
            $stmt = $pdo->prepare("
                UPDATE users
                SET first_name = ?, last_name = ?, phone = ?, emergency_contact = ?
                WHERE user_id = ?
            ");
            $stmt->execute([$first_name, $last_name, $phone, $emergency_contact, $user_id]);

            // อัปเดตข้อมูลห้อง (ค่าน้ำ ค่าไฟ)
            $stmt = $pdo->prepare("UPDATE rooms SET water_rate = ?, electricity_rate = ? WHERE room_id = ?");
            $stmt->execute([$water_rate, $electricity_rate, $room_id]);

            // อัปเดตสัญญา
            $stmt = $pdo->prepare("
                UPDATE contracts
                SET rental_price = ?, start_date = ?, end_date = ?, contract_terms = ?
                WHERE contract_id = ? AND status = 'active'
            ");
            $stmt->execute([$rental_price, $start_date, $end_date, $contract_terms, $contract_id]);

            $pdo->commit();
            $success_message = 'อัปเดตสัญญาเรียบร้อยแล้ว';

        } catch (PDOException $e) {
            $pdo->rollBack();
            $error_message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
        }
    }
}

// ดึงข้อมูลสัญญาทั้งหมด
$filter = $_GET['filter'] ?? 'active';
$valid_filters = ['active', 'expired', 'terminated', 'all'];
if (!in_array($filter, $valid_filters)) {
    $filter = 'active';
}

try {
    $where_clause = "";
    if ($filter !== 'all') {
        $where_clause = "WHERE c.status = '$filter'";
    }

    $stmt = $pdo->query("
        SELECT c.*,
               u.first_name, u.last_name,
               CONCAT(u.first_name, ' ', u.last_name) as tenant_name,
               u.phone, u.emergency_contact,
               r.room_number as room_name,
               r.water_rate, r.electricity_rate
        FROM contracts c
        JOIN users u ON c.user_id = u.user_id
        JOIN rooms r ON c.room_id = r.room_id
        JOIN zones z ON r.zone_id = z.zone_id
        $where_clause
        ORDER BY c.created_at DESC
    ");
    $contracts = $stmt->fetchAll();

} catch (PDOException $e) {
    $error_message = 'เกิดข้อผิดพลาดในการโหลดข้อมูล: ' . $e->getMessage();
}

require_once '../../includes/header.php';
?>

<h1 class="page-title">จัดการสัญญา</h1>

<?php if ($success_message): ?>
    <div class="alert alert-success"><?php echo h($success_message); ?></div>
<?php endif; ?>

<?php if ($error_message): ?>
    <div class="alert alert-error"><?php echo h($error_message); ?></div>
<?php endif; ?>


<!-- ตัวกรองสัญญา -->
<div class="card">
    <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
        <span style="font-weight: 600;">แสดงสัญญา:</span>
        <a href="?filter=active" class="btn <?php echo $filter === 'active' ? 'btn-primary' : 'btn-outline-primary'; ?>">
            ที่ใช้งานอยู่
        </a>
        <a href="?filter=expired" class="btn <?php echo $filter === 'expired' ? 'btn-primary' : 'btn-outline-primary'; ?>">
            หมดอายุ
        </a>
        <a href="?filter=terminated" class="btn <?php echo $filter === 'terminated' ? 'btn-primary' : 'btn-outline-primary'; ?>">
            ถูกยุติ
        </a>
        <a href="?filter=all" class="btn <?php echo $filter === 'all' ? 'btn-primary' : 'btn-outline-primary'; ?>">
            ทั้งหมด
        </a>
    </div>
</div>

<!-- รายการสัญญา -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            รายการสัญญา
            <?php
            $filter_text = [
                'active' => 'ที่ใช้งานอยู่',
                'expired' => 'หมดอายุ',
                'terminated' => 'ถูกยุติ',
                'all' => 'ทั้งหมด'
            ];
            echo $filter_text[$filter];
            ?>
            (<?php echo count($contracts); ?> รายการ)
        </h2>
    </div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>สัญญาเลขที่</th>
                    <th>ผู้เช่า</th>
                    <th>เบอร์โทร</th>
                    <th>ห้อง</th>
                    <th>ค่าเช่า</th>
                    <th>วันเริ่มสัญญา</th>
                    <th>วันสิ้นสุด</th>
                    <th>สถานะ</th>
                    <th>การจัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($contracts)): ?>
                <tr>
                    <td colspan="9" style="text-align: center; color: #666;">ไม่มีข้อมูลสัญญา</td>
                </tr>
                <?php else: ?>
                    <?php foreach ($contracts as $contract): ?>
                    <?php
                    // ตรวจสอบว่าสัญญาหมดอายุหรือไม่
                    $is_expired = $contract['status'] === 'active' && strtotime($contract['end_date']) < strtotime('today');
                    $days_left = '';
                    if ($contract['status'] === 'active') {
                        $days_diff = (strtotime($contract['end_date']) - strtotime('today')) / (60 * 60 * 24);
                        if ($days_diff > 0) {
                            $days_left = ' (อีก ' . ceil($days_diff) . ' วัน)';
                        }
                    }
                    ?>
                    <tr>
                        <td><strong>C<?php echo str_pad($contract['contract_id'], 4, '0', STR_PAD_LEFT); ?></strong></td>
                        <td><?php echo h($contract['tenant_name']); ?></td>
                        <td><?php echo h($contract['phone']); ?></td>
                        <td><?php echo h($contract['room_name']); ?></td>
                        <td><?php echo formatMoney($contract['rental_price']); ?></td>
                        <td><?php echo formatDate($contract['start_date']); ?></td>
                        <td>
                            <?php echo formatDate($contract['end_date']); ?>
                            <?php if ($is_expired): ?>
                                <span style="color: red; font-weight: bold;">(หมดอายุ)</span>
                            <?php elseif ($days_left): ?>
                                <span style="color: #666; font-size: 0.9rem;"><?php echo $days_left; ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo $is_expired ? 'expired' : $contract['status']; ?>">
                                <?php
                                if ($is_expired) {
                                    echo 'หมดอายุ';
                                } else {
                                    $status_text = [
                                        'active' => 'ใช้งาน',
                                        'expired' => 'หมดอายุ',
                                        'terminated' => 'ยุติแล้ว'
                                    ];
                                    echo $status_text[$contract['status']] ?? $contract['status'];
                                }
                                ?>
                            </span>
                        </td>
                        <td>
                            <a href="../../contract_view.php?contract_id=<?php echo $contract['contract_id']; ?>"
                               class="btn btn-success btn-sm"
                               target="_blank"
                               title="ดูและพิมพ์สัญญา">
                                 ดูสัญญา
                            </a>
                            <?php if ($contract['status'] === 'active'): ?>
                                <button class="btn btn-warning btn-sm" onclick="editContract(<?php echo h(json_encode($contract)); ?>)">แก้ไข</button>
                                <button class="btn btn-danger btn-sm" onclick="terminateContract(<?php echo $contract['contract_id']; ?>, '<?php echo h($contract['tenant_name']); ?>', '<?php echo h($contract['room_name']); ?>')">ยุติสัญญา</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal ดูสัญญา -->
<div id="viewContractModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem; border-radius: 10px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto;">
        <h3>รายละเอียดสัญญา</h3>
        <div id="contractDetails"></div>
        <div style="margin-top: 1.5rem;">
            <button type="button" class="btn btn-primary" onclick="closeViewContractModal()">ปิด</button>
        </div>
    </div>
</div>

<!-- Modal แก้ไขสัญญา -->
<div id="editContractModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem; border-radius: 10px; width: 90%; max-width: 700px; max-height: 90vh; overflow-y: auto;">
        <h3>แก้ไขสัญญา</h3>
        <form id="editContractForm" method="POST" action="">
            <input type="hidden" name="action" value="update_contract">
            <input type="hidden" name="contract_id" id="edit_contract_id">
            <input type="hidden" name="user_id" id="edit_user_id">
            <input type="hidden" name="room_id" id="edit_room_id">

            <div class="form-group">
                <label>สัญญาเลขที่</label>
                <input type="text" id="edit_contract_number" class="form-control" readonly>
            </div>

            <div class="form-group">
                <label>ห้อง</label>
                <input type="text" id="edit_room_name" class="form-control" readonly>
            </div>

            <!-- ข้อมูลผู้เช่า -->
            <div class="card" style="background: #f8f9fa; padding: 1rem; margin-bottom: 1rem; border: 1px solid #dee2e6; border-radius: 8px;">
                <h4 style="margin: 0 0 1rem 0; color: #495057; font-size: 1rem;">ข้อมูลผู้เช่า</h4>

                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="edit_first_name">ชื่อ *</label>
                            <input type="text" id="edit_first_name" name="first_name" class="form-control" placeholder="ชื่อ" required>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label for="edit_last_name">นามสกุล *</label>
                            <input type="text" id="edit_last_name" name="last_name" class="form-control" placeholder="นามสกุล" required>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="edit_phone">เบอร์โทรศัพท์ *</label>
                            <input type="text" id="edit_phone" name="phone" class="form-control" placeholder="เบอร์โทรศัพท์" required>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label for="edit_emergency_contact">เบอร์ติดต่อฉุกเฉิน</label>
                            <input type="text" id="edit_emergency_contact" name="emergency_contact" class="form-control" placeholder="เบอร์ติดต่อฉุกเฉิน">
                        </div>
                    </div>
                </div>

                <small style="color: #6c757d; font-size: 0.875rem;">
                    💡 ข้อมูลจะถูกอัปเดตในระบบหากมีการแก้ไข
                </small>
            </div>

            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="edit_rental_price">ค่าเช่า/เดือน (บาท) *</label>
                        <input type="number" id="edit_rental_price" name="rental_price" class="form-control" step="0.01" min="0" required>
                    </div>
                </div>
                <div class="form-col">
                    <div class="form-group">
                        <label for="edit_water_rate">ค่าน้ำ/หน่วย (บาท) *</label>
                        <input type="number" id="edit_water_rate" name="water_rate" class="form-control" step="0.01" min="0" required>
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="edit_electricity_rate">ค่าไฟ/หน่วย (บาท) *</label>
                        <input type="number" id="edit_electricity_rate" name="electricity_rate" class="form-control" step="0.01" min="0" required>
                    </div>
                </div>
                <div class="form-col">
                    <div class="form-group">
                        <label for="edit_start_date">วันที่เริ่มสัญญา *</label>
                        <input type="date" id="edit_start_date" name="start_date" class="form-control" required>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="edit_end_date">วันสิ้นสุดสัญญา *</label>
                <input type="date" id="edit_end_date" name="end_date" class="form-control" required>
            </div>

            <div class="form-group">
                <label for="edit_contract_terms">ข้อกำหนดสัญญาเพิ่มเติม</label>
                <textarea id="edit_contract_terms" name="contract_terms" class="form-control" rows="3" placeholder="ระบุข้อกำหนดพิเศษเพิ่มเติม (ถ้ามี)"></textarea>
            </div>

            <div style="margin-top: 1.5rem; display: flex; gap: 1rem;">
                <button type="submit" class="btn btn-success">✓ บันทึกการแก้ไข</button>
                <button type="button" class="btn btn-danger" onclick="closeEditContractModal()">ยกเลิก</button>
            </div>
        </form>
    </div>
</div>

<script>
function viewContract(contract) {
    let html = `
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
            <div><strong>สัญญาเลขที่:</strong> C${String(contract.contract_id).padStart(4, '0')}</div>
            <div><strong>ผู้เช่า:</strong> ${contract.tenant_name}</div>
            <div><strong>ห้อง:</strong> ${contract.room_name}</div>
            <div><strong>ค่าเช่า:</strong> ${parseFloat(contract.rental_price).toLocaleString()} บาท/เดือน</div>
            <div><strong>วันเริ่มสัญญา:</strong> ${formatDate(contract.start_date)}</div>
            <div><strong>วันสิ้นสุด:</strong> ${formatDate(contract.end_date)}</div>
        </div>
    `;

    if (contract.contract_terms) {
        html += `
            <div style="margin-top: 1rem;">
                <strong>ข้อกำหนดสัญญา:</strong>
                <div style="background: #f8f9fa; padding: 1rem; border-radius: 5px; margin-top: 0.5rem; white-space: pre-wrap;">${contract.contract_terms}</div>
            </div>
        `;
    }

    document.getElementById('contractDetails').innerHTML = html;
    document.getElementById('viewContractModal').style.display = 'block';
}

function closeViewContractModal() {
    document.getElementById('viewContractModal').style.display = 'none';
}

function editContract(contract) {
    document.getElementById('edit_contract_id').value = contract.contract_id;
    document.getElementById('edit_user_id').value = contract.user_id;
    document.getElementById('edit_room_id').value = contract.room_id;
    document.getElementById('edit_contract_number').value = contract.contract_number || ('C' + String(contract.contract_id).padStart(4, '0'));
    document.getElementById('edit_room_name').value = contract.room_name;

    // ข้อมูลผู้เช่า
    document.getElementById('edit_first_name').value = contract.first_name || '';
    document.getElementById('edit_last_name').value = contract.last_name || '';
    document.getElementById('edit_phone').value = contract.phone || '';
    document.getElementById('edit_emergency_contact').value = contract.emergency_contact || '';

    // ข้อมูลสัญญาและห้อง
    document.getElementById('edit_rental_price').value = contract.rental_price;
    document.getElementById('edit_water_rate').value = contract.water_rate || 0;
    document.getElementById('edit_electricity_rate').value = contract.electricity_rate || 0;
    document.getElementById('edit_start_date').value = contract.start_date;
    document.getElementById('edit_end_date').value = contract.end_date;
    document.getElementById('edit_contract_terms').value = contract.contract_terms || '';

    document.getElementById('editContractModal').style.display = 'block';
}

function closeEditContractModal() {
    document.getElementById('editContractModal').style.display = 'none';
}

function terminateContract(contractId, tenantName, roomName) {
    if (confirm(`ต้องการยุติสัญญาของ ${tenantName} ห้อง ${roomName} หรือไม่?\n\nการยุติสัญญาจะทำให้ห้องว่างและผู้เช่าไม่มีห้องอีกต่อไป`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '';

        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'terminate_contract';

        const contractIdInput = document.createElement('input');
        contractIdInput.type = 'hidden';
        contractIdInput.name = 'contract_id';
        contractIdInput.value = contractId;

        form.appendChild(actionInput);
        form.appendChild(contractIdInput);
        document.body.appendChild(form);
        form.submit();
    }
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('th-TH');
}

// ปิด Modal เมื่อคลิกนอกพื้นที่
document.getElementById('viewContractModal').onclick = function(e) {
    if (e.target === this) {
        closeViewContractModal();
    }
}

document.getElementById('editContractModal').onclick = function(e) {
    if (e.target === this) {
        closeEditContractModal();
    }
}

</script>

<style>
.btn-outline-primary {
    background-color: transparent;
    color: #007bff;
    border: 1px solid #007bff;
}

.btn-outline-primary:hover {
    background-color: #007bff;
    color: white;
}

.status-expired {
    background-color: #dc3545;
    color: white;
}
</style>

<?php require_once '../../includes/footer.php'; ?>