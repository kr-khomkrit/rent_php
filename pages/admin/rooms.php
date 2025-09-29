<?php
require_once '../../includes/config.php';
requireAdmin();

$page_title = 'จัดการห้อง';
$success_message = '';
$error_message = '';

// การดำเนินการต่างๆ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_room') {
        // อัปเดตข้อมูลห้อง
        $room_id = intval($_POST['room_id']);
        $status = $_POST['status'];
        $water_rate = floatval($_POST['water_rate']);
        $electricity_rate = floatval($_POST['electricity_rate']);

        try {
            $stmt = $pdo->prepare("
                UPDATE rooms
                SET status = ?, water_rate = ?, electricity_rate = ?
                WHERE room_id = ?
            ");
            $stmt->execute([$status, $water_rate, $electricity_rate, $room_id]);
            $success_message = 'อัปเดตข้อมูลห้องเรียบร้อยแล้ว';
        } catch (PDOException $e) {
            $error_message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
        }
    } elseif ($action === 'rent_room') {
        // ปล่อยเช่าห้อง
        $room_id = intval($_POST['room_id']);
        $user_id = intval($_POST['user_id']);
        $rental_price = floatval($_POST['rental_price']);
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $contract_terms = trim($_POST['contract_terms']);

        if (empty($user_id) || empty($rental_price) || empty($start_date) || empty($end_date)) {
            $error_message = 'กรุณากรอกข้อมูลให้ครบถ้วน';
        } else {
            try {
                $pdo->beginTransaction();

                // ตรวจสอบว่า User นี้มีสัญญาอื่นที่ยังใช้งานอยู่หรือไม่
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM contracts WHERE user_id = ? AND status = 'active'");
                $stmt->execute([$user_id]);

                if ($stmt->fetchColumn() > 0) {
                    throw new Exception('ผู้ใช้คนนี้มีสัญญาที่ยังใช้งานอยู่แล้ว');
                }

                // สร้างสัญญาใหม่
                $stmt = $pdo->prepare("
                    INSERT INTO contracts (user_id, room_id, rental_price, start_date, end_date, contract_terms, status)
                    VALUES (?, ?, ?, ?, ?, ?, 'active')
                ");
                $stmt->execute([$user_id, $room_id, $rental_price, $start_date, $end_date, $contract_terms]);

                // อัปเดตสถานะห้อง
                $stmt = $pdo->prepare("UPDATE rooms SET status = 'occupied' WHERE room_id = ?");
                $stmt->execute([$room_id]);

                // อัปเดตสถานะผู้ใช้
                $stmt = $pdo->prepare("UPDATE users SET has_room = 1 WHERE user_id = ?");
                $stmt->execute([$user_id]);

                $pdo->commit();
                $success_message = 'ปล่อยเช่าห้องเรียบร้อยแล้ว';
            } catch (Exception $e) {
                $pdo->rollBack();
                $error_message = $e->getMessage();
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error_message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
            }
        }
    }
}

// ดึงข้อมูลห้องทั้งหมด
try {
    $stmt = $pdo->query("
        SELECT r.*, z.zone_name,
               CONCAT(u.first_name, ' ', u.last_name) as tenant_name,
               c.rental_price, c.start_date, c.end_date
        FROM rooms r
        JOIN zones z ON r.zone_id = z.zone_id
        LEFT JOIN contracts c ON r.room_id = c.room_id AND c.status = 'active'
        LEFT JOIN users u ON c.user_id = u.user_id
        ORDER BY z.zone_name, r.room_number
    ");
    $rooms = $stmt->fetchAll();

    // จัดกลุ่มห้องตามโซน
    $zones = [];
    foreach ($rooms as $room) {
        $zones[$room['zone_name']][] = $room;
    }

    // ดึงรายชื่อผู้ใช้ที่ไม่มีห้อง
    $stmt = $pdo->query("
        SELECT user_id, CONCAT(first_name, ' ', last_name) as full_name
        FROM users
        WHERE role = 'user' AND has_room = 0 AND status = 'active'
        ORDER BY first_name, last_name
    ");
    $available_users = $stmt->fetchAll();

} catch (PDOException $e) {
    $error_message = 'เกิดข้อผิดพลาดในการโหลดข้อมูล: ' . $e->getMessage();
}

require_once '../../includes/header.php';
?>

<h1 class="page-title">จัดการห้อง</h1>

<?php if ($success_message): ?>
    <div class="alert alert-success"><?php echo h($success_message); ?></div>
<?php endif; ?>

<?php if ($error_message): ?>
    <div class="alert alert-error"><?php echo h($error_message); ?></div>
<?php endif; ?>

<!-- แสดงห้องแต่ละโซน -->
<?php foreach ($zones as $zone_name => $zone_rooms): ?>
<div class="card">
    <div class="card-header">
        <h2 class="card-title">โซน <?php echo h($zone_name); ?></h2>
    </div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>หมายเลขห้อง</th>
                    <th>สถานะ</th>
                    <th>ผู้เช่าปัจจุบัน</th>
                    <th>ค่าเช่า</th>
                    <th>ค่าน้ำ</th>
                    <th>ค่าไฟ</th>
                    <th>วันที่เริ่มสัญญา</th>
                    <th>วันสิ้นสุดสัญญา</th>
                    <th>การจัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($zone_rooms as $room): ?>
                <tr>
                    <td><strong><?php echo h($room['room_number']); ?></strong></td>
                    <td>
                        <span class="status-badge status-<?php echo $room['status']; ?>">
                            <?php
                            $status_text = [
                                'available' => 'ว่าง',
                                'occupied' => 'มีผู้เช่า',
                                'maintenance' => 'ซ่อมแซม'
                            ];
                            echo $status_text[$room['status']] ?? $room['status'];
                            ?>
                        </span>
                    </td>
                    <td><?php echo $room['tenant_name'] ? h($room['tenant_name']) : '-'; ?></td>
                    <td><?php echo $room['rental_price'] ? formatMoney($room['rental_price']) : '-'; ?></td>
                    <td><?php echo formatMoney($room['water_rate']); ?></td>
                    <td><?php echo formatMoney($room['electricity_rate']); ?></td>
                    <td><?php echo $room['start_date'] ? formatDate($room['start_date']) : '-'; ?></td>
                    <td><?php echo $room['end_date'] ? formatDate($room['end_date']) : '-'; ?></td>
                    <td>
                        <button class="btn btn-warning btn-sm" onclick="editRoom(<?php echo h(json_encode($room)); ?>)">แก้ไข</button>
                        <?php if ($room['status'] === 'available'): ?>
                            <button class="btn btn-success btn-sm" onclick="rentRoom(<?php echo $room['room_id']; ?>, '<?php echo h($room['zone_name'] . '-' . $room['room_number']); ?>')">ปล่อยเช่า</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; ?>

<!-- Modal แก้ไขห้อง -->
<div id="editRoomModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem; border-radius: 10px; width: 90%; max-width: 500px;">
        <h3>แก้ไขข้อมูลห้อง</h3>
        <form id="editRoomForm" method="POST" action="">
            <input type="hidden" name="action" value="update_room">
            <input type="hidden" name="room_id" id="edit_room_id">

            <div class="form-group">
                <label>ห้อง</label>
                <input type="text" id="edit_room_display" class="form-control" readonly>
            </div>

            <div class="form-group">
                <label for="edit_status">สถานะ</label>
                <select id="edit_status" name="status" class="form-control">
                    <option value="available">ว่าง</option>
                    <option value="occupied">มีผู้เช่า</option>
                    <option value="maintenance">ซ่อมแซม</option>
                </select>
            </div>

            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="edit_water_rate">ค่าน้ำ (บาท/หน่วย)</label>
                        <input type="number" id="edit_water_rate" name="water_rate" class="form-control" step="0.01" min="0">
                    </div>
                </div>
                <div class="form-col">
                    <div class="form-group">
                        <label for="edit_electricity_rate">ค่าไฟ (บาท/หน่วย)</label>
                        <input type="number" id="edit_electricity_rate" name="electricity_rate" class="form-control" step="0.01" min="0">
                    </div>
                </div>
            </div>

            <div style="margin-top: 1.5rem; display: flex; gap: 1rem;">
                <button type="submit" class="btn btn-success">บันทึก</button>
                <button type="button" class="btn btn-danger" onclick="closeEditRoomModal()">ยกเลิก</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal ปล่อยเช่าห้อง -->
<div id="rentRoomModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem; border-radius: 10px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto;">
        <h3>ปล่อยเช่าห้อง</h3>
        <form id="rentRoomForm" method="POST" action="">
            <input type="hidden" name="action" value="rent_room">
            <input type="hidden" name="room_id" id="rent_room_id">

            <div class="form-group">
                <label>ห้อง</label>
                <input type="text" id="rent_room_display" class="form-control" readonly>
            </div>

            <div class="form-group">
                <label for="rent_user_id">ผู้เช่า *</label>
                <select id="rent_user_id" name="user_id" class="form-control" required>
                    <option value="">เลือกผู้เช่า</option>
                    <?php foreach ($available_users as $user): ?>
                        <option value="<?php echo $user['user_id']; ?>"><?php echo h($user['full_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="rent_rental_price">ค่าเช่า (บาท/เดือน) *</label>
                        <input type="number" id="rent_rental_price" name="rental_price" class="form-control" step="0.01" min="0" required>
                    </div>
                </div>
                <div class="form-col">
                    <div class="form-group">
                        <label for="rent_start_date">วันที่เริ่มสัญญา *</label>
                        <input type="date" id="rent_start_date" name="start_date" class="form-control" required>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="rent_end_date">วันสิ้นสุดสัญญา *</label>
                <input type="date" id="rent_end_date" name="end_date" class="form-control" required>
            </div>

            <div class="form-group">
                <label for="rent_contract_terms">ข้อกำหนดสัญญา</label>
                <textarea id="rent_contract_terms" name="contract_terms" class="form-control" rows="4" placeholder="ระบุข้อกำหนดเพิ่มเติม (ถ้ามี)"></textarea>
            </div>

            <div style="margin-top: 1.5rem; display: flex; gap: 1rem;">
                <button type="submit" class="btn btn-success">ปล่อยเช่า</button>
                <button type="button" class="btn btn-danger" onclick="closeRentRoomModal()">ยกเลิก</button>
            </div>
        </form>
    </div>
</div>

<script>
function editRoom(room) {
    document.getElementById('edit_room_id').value = room.room_id;
    document.getElementById('edit_room_display').value = room.zone_name + '-' + room.room_number;
    document.getElementById('edit_status').value = room.status;
    document.getElementById('edit_water_rate').value = room.water_rate;
    document.getElementById('edit_electricity_rate').value = room.electricity_rate;

    document.getElementById('editRoomModal').style.display = 'block';
}

function closeEditRoomModal() {
    document.getElementById('editRoomModal').style.display = 'none';
}

function rentRoom(roomId, roomDisplay) {
    document.getElementById('rent_room_id').value = roomId;
    document.getElementById('rent_room_display').value = roomDisplay;

    // ตั้งวันเริ่มสัญญาเป็นวันนี้
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('rent_start_date').value = today;

    // ตั้งวันสิ้นสุดสัญญาเป็น 1 ปีจากวันนี้
    const nextYear = new Date();
    nextYear.setFullYear(nextYear.getFullYear() + 1);
    document.getElementById('rent_end_date').value = nextYear.toISOString().split('T')[0];

    document.getElementById('rentRoomModal').style.display = 'block';
}

function closeRentRoomModal() {
    document.getElementById('rentRoomModal').style.display = 'none';
}

// ปิด Modal เมื่อคลิกนอกพื้นที่
document.getElementById('editRoomModal').onclick = function(e) {
    if (e.target === this) {
        closeEditRoomModal();
    }
}

document.getElementById('rentRoomModal').onclick = function(e) {
    if (e.target === this) {
        closeRentRoomModal();
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>