<?php
require_once '../../includes/config.php';
requireAdmin();

$page_title = 'จัดการห้อง';
$success_message = '';
$error_message = '';

// การดำเนินการต่างๆ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_room') {
        // สร้างห้องใหม่
        $zone_id = intval($_POST['zone_id']);
        $room_number = trim($_POST['room_number']);

        if (empty($zone_id) || empty($room_number)) {
            $error_message = 'กรุณาเลือกโซนและกรอกหมายเลขห้อง';
        } else {
            try {
                // ตรวจสอบว่าห้องนี้มีอยู่แล้วหรือไม่
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM rooms WHERE zone_id = ? AND room_number = ?");
                $stmt->execute([$zone_id, $room_number]);

                if ($stmt->fetchColumn() > 0) {
                    $error_message = 'ห้องนี้มีอยู่ในระบบแล้ว';
                } else {
                    // สร้างห้องใหม่
                    $stmt = $pdo->prepare("INSERT INTO rooms (zone_id, room_number, status) VALUES (?, ?, 'available')");
                    $stmt->execute([$zone_id, $room_number]);
                    $success_message = 'สร้างห้องใหม่เรียบร้อยแล้ว';
                }
            } catch (PDOException $e) {
                $error_message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete_room') {
        // ลบห้อง
        $room_id = intval($_POST['room_id']);

        try {
            // ตรวจสอบว่าห้องมีผู้เช่าหรือมีสัญญาที่ active อยู่หรือไม่
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM contracts
                WHERE room_id = ? AND status = 'active'
            ");
            $stmt->execute([$room_id]);

            if ($stmt->fetchColumn() > 0) {
                $error_message = 'ไม่สามารถลบห้องได้ เนื่องจากมีผู้เช่าอยู่';
            } else {
                // ตรวจสอบว่ามีสัญญาเก่าหรือไม่ (expired/terminated)
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM contracts WHERE room_id = ?");
                $stmt->execute([$room_id]);

                if ($stmt->fetchColumn() > 0) {
                    $error_message = 'ไม่สามารถลบห้องได้ เนื่องจากมีประวัติสัญญาในระบบ';
                } else {
                    // ลบห้อง
                    $stmt = $pdo->prepare("DELETE FROM rooms WHERE room_id = ?");
                    $stmt->execute([$room_id]);
                    $success_message = 'ลบห้องเรียบร้อยแล้ว';
                }
            }
        } catch (PDOException $e) {
            $error_message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
        }
    } elseif ($action === 'update_room') {
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

    // ดึงรายชื่อโซนทั้งหมด
    $stmt = $pdo->query("SELECT zone_id, zone_name FROM zones ORDER BY zone_name");
    $all_zones = $stmt->fetchAll();

} catch (PDOException $e) {
    $error_message = 'เกิดข้อผิดพลาดในการโหลดข้อมูล: ' . $e->getMessage();
}

require_once '../../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
    <h1 class="page-title" style="margin: 0;">จัดการห้อง</h1>
    <button class="btn btn-primary" onclick="openCreateRoomModal()">+ สร้างห้องใหม่</button>
</div>

<?php if ($success_message): ?>
    <div class="alert alert-success"><?php echo h($success_message); ?></div>
<?php endif; ?>

<?php if ($error_message): ?>
    <div class="alert alert-error"><?php echo h($error_message); ?></div>
<?php endif; ?>

<!-- แสดงห้องแต่ละโซนแบบ Tab -->
<div class="card">
    <!-- Tab Headers -->
    <div class="tab-container">
        <?php $first = true; foreach ($zones as $zone_name => $zone_rooms): ?>
            <button class="tab-btn <?php echo $first ? 'active' : ''; ?>" onclick="showZone('<?php echo $zone_name; ?>')">
                โซน <?php echo h($zone_name); ?>
            </button>
        <?php $first = false; endforeach; ?>
    </div>

    <!-- Tab Contents -->
    <?php $first = true; foreach ($zones as $zone_name => $zone_rooms): ?>
    <div id="zone-<?php echo $zone_name; ?>" class="tab-content" style="<?php echo $first ? '' : 'display: none;'; ?>">
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>สถานะ</th>
                        <th>ผู้เช่าปัจจุบัน</th>
                        <th>ค่าเช่า</th>
                        <th>ค่าน้ำ</th>
                        <th>ค่าไฟ</th>
                        <th>วันที่เริ่ม</th>
                        <th>วันสิ้นสุด</th>
                        <th>การจัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($zone_rooms as $room): ?>
                    <tr>
                        <td title="<?php echo h($room['room_number']); ?>"><strong><?php echo h($room['room_number']); ?></strong></td>
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
                        <td title="<?php echo $room['tenant_name'] ? h($room['tenant_name']) : '-'; ?>">
                            <?php echo $room['tenant_name'] ? h($room['tenant_name']) : '-'; ?>
                        </td>
                        <td title="<?php echo $room['rental_price'] ? formatMoney($room['rental_price']) : '-'; ?>">
                            <?php echo $room['rental_price'] ? formatMoney($room['rental_price']) : '-'; ?>
                        </td>
                        <td title="<?php echo formatMoney($room['water_rate']); ?>">
                            <?php echo formatMoney($room['water_rate']); ?>
                        </td>
                        <td title="<?php echo formatMoney($room['electricity_rate']); ?>">
                            <?php echo formatMoney($room['electricity_rate']); ?>
                        </td>
                        <td title="<?php echo $room['start_date'] ? formatDate($room['start_date']) : '-'; ?>">
                            <?php echo $room['start_date'] ? formatDate($room['start_date']) : '-'; ?>
                        </td>
                        <td title="<?php echo $room['end_date'] ? formatDate($room['end_date']) : '-'; ?>">
                            <?php echo $room['end_date'] ? formatDate($room['end_date']) : '-'; ?>
                        </td>
                        <td>
                            <button class="btn btn-warning btn-sm" onclick="editRoom(<?php echo h(json_encode($room)); ?>)">แก้ไข</button>
                            <?php if ($room['status'] === 'available'): ?>
                                <button class="btn btn-success btn-sm" onclick="rentRoom(<?php echo $room['room_id']; ?>, '<?php echo h($room['zone_name'] . '-' . $room['room_number']); ?>')">ปล่อยเช่า</button>
                            <?php endif; ?>
                            <button class="btn btn-danger btn-sm" onclick="deleteRoom(<?php echo $room['room_id']; ?>, '<?php echo h($room['zone_name'] . '-' . $room['room_number']); ?>', '<?php echo $room['status']; ?>')">ลบ</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php $first = false; endforeach; ?>
</div>

<!-- Modal สร้างห้องใหม่ -->
<div id="createRoomModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem; border-radius: 10px; width: 90%; max-width: 500px;">
        <h3>สร้างห้องใหม่</h3>
        <form id="createRoomForm" method="POST" action="">
            <input type="hidden" name="action" value="create_room">

            <div class="form-group">
                <label for="create_zone_id">โซน *</label>
                <select id="create_zone_id" name="zone_id" class="form-control" required onchange="updateRoomPrefix()">
                    <option value="">เลือกโซน</option>
                    <?php foreach ($all_zones as $zone): ?>
                        <option value="<?php echo $zone['zone_id']; ?>" data-zone-name="<?php echo h($zone['zone_name']); ?>"><?php echo h($zone['zone_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="create_room_number">หมายเลขห้อง *</label>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <span id="room_prefix" style="font-weight: 600; font-size: 1.1rem; color: #3b82f6; min-width: 50px;">-</span>
                    <input type="text" id="create_room_number_input" class="form-control" placeholder="เช่น 01, 02, 10" required oninput="updateRoomNumberPreview()">
                </div>
                <input type="hidden" id="create_room_number" name="room_number">
                <small style="color: #6b7280; font-size: 0.875rem;">กรอกเฉพาะหมายเลข (เช่น 01, 02, 10)</small>
            </div>

            <div style="margin-top: 1.5rem; display: flex; gap: 1rem;">
                <button type="submit" class="btn btn-success">สร้างห้อง</button>
                <button type="button" class="btn btn-danger" onclick="closeCreateRoomModal()">ยกเลิก</button>
            </div>
        </form>
    </div>
</div>

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
                        <label for="edit_water_rate">ค่าน้ำ </label>
                        <input type="number" id="edit_water_rate" name="water_rate" class="form-control" step="0.01" min="0">
                    </div>
                </div>
                <div class="form-col">
                    <div class="form-group">
                        <label for="edit_electricity_rate">ค่าไฟ </label>
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
                        <label for="rent_rental_price">ค่าเช่า</label>
                        <input type="number" id="rent_rental_price" name="rental_price" class="form-control" step="0.01" min="0" required>
                    </div>
                </div>
                <div class="form-col">
                    <div class="form-group">
                        <label for="rent_start_date">วันที่เริ่มสัญญา</label>
                        <input type="date" id="rent_start_date" name="start_date" class="form-control" required>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="rent_end_date">วันสิ้นสุดสัญญา</label>
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
// Tab Switching
function showZone(zoneName) {
    // ซ่อน tab ทั้งหมด
    const allTabs = document.querySelectorAll('.tab-content');
    allTabs.forEach(tab => tab.style.display = 'none');

    // ลบ active class จากปุ่มทั้งหมด
    const allBtns = document.querySelectorAll('.tab-btn');
    allBtns.forEach(btn => btn.classList.remove('active'));

    // แสดง tab ที่เลือก
    document.getElementById('zone-' + zoneName).style.display = 'block';

    // เพิ่ม active class ให้ปุ่มที่คลิก
    if (event && event.target) {
        event.target.classList.add('active');
    } else {
        // ถ้าไม่มี event (เรียกจาก URL parameter) ให้หาปุ่มที่ตรงกัน
        allBtns.forEach(btn => {
            if (btn.textContent.includes(zoneName)) {
                btn.classList.add('active');
            }
        });
    }
}

// ตรวจสอบ URL parameter เมื่อโหลดหน้า
window.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const zone = urlParams.get('zone');

    if (zone) {
        // เปิด tab ที่ระบุใน URL
        showZone(zone);
    }
});

// Create Room Modal
function openCreateRoomModal() {
    document.getElementById('createRoomModal').style.display = 'block';
}

function closeCreateRoomModal() {
    document.getElementById('createRoomModal').style.display = 'none';
    document.getElementById('createRoomForm').reset();
    document.getElementById('room_prefix').textContent = '-';
    document.getElementById('create_room_number').value = '';
}

function updateRoomPrefix() {
    const zoneSelect = document.getElementById('create_zone_id');
    const selectedOption = zoneSelect.options[zoneSelect.selectedIndex];
    const zoneName = selectedOption.getAttribute('data-zone-name');

    if (zoneName) {
        document.getElementById('room_prefix').textContent = zoneName + '-';
    } else {
        document.getElementById('room_prefix').textContent = '-';
    }

    // อัปเดตหมายเลขห้องเต็ม
    updateRoomNumberPreview();
}

function updateRoomNumberPreview() {
    const zoneSelect = document.getElementById('create_zone_id');
    const selectedOption = zoneSelect.options[zoneSelect.selectedIndex];
    const zoneName = selectedOption.getAttribute('data-zone-name');
    const roomNum = document.getElementById('create_room_number_input').value.trim();

    if (zoneName && roomNum) {
        document.getElementById('create_room_number').value = zoneName + '-' + roomNum;
    } else {
        document.getElementById('create_room_number').value = '';
    }
}

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

// Delete Room
function deleteRoom(roomId, roomDisplay, status) {
    if (status !== 'available') {
        alert('ไม่สามารถลบห้องได้ เนื่องจากมีผู้เช่าอยู่หรือห้องอยู่ในสถานะซ่อมแซม');
        return;
    }

    if (confirm('คุณต้องการลบห้อง ' + roomDisplay + ' ใช่หรือไม่?\n\nการลบห้องจะไม่สามารถกู้คืนได้')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '';

        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete_room';

        const roomIdInput = document.createElement('input');
        roomIdInput.type = 'hidden';
        roomIdInput.name = 'room_id';
        roomIdInput.value = roomId;

        form.appendChild(actionInput);
        form.appendChild(roomIdInput);
        document.body.appendChild(form);
        form.submit();
    }
}

// ปิด Modal เมื่อคลิกนอกพื้นที่
document.getElementById('createRoomModal').onclick = function(e) {
    if (e.target === this) {
        closeCreateRoomModal();
    }
}

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

<style>
.tab-container {
    display: flex;
    border-bottom: 2px solid #e5e7eb;
    background: #f9fafb;
    padding: 0.5rem 1rem 0;
    gap: 0.5rem;
}

.tab-btn {
    padding: 0.75rem 1.5rem;
    border: none;
    background: transparent;
    color: #6b7280;
    font-size: 1rem;
    font-weight: 500;
    cursor: pointer;
    border-radius: 8px 8px 0 0;
    transition: all 0.2s;
    position: relative;
}

.tab-btn:hover {
    background: #e5e7eb;
    color: #374151;
}

.tab-btn.active {
    background: white;
    color: #3b82f6;
    font-weight: 600;
}

.tab-btn.active::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 0;
    right: 0;
    height: 2px;
    background: #3b82f6;
}

.tab-content {
    padding: 1.5rem;
}

/* กำหนดขนาดคอลัมน์ตาราง */
.tab-content table {
    table-layout: fixed;
    width: 100%;
}

.tab-content th,
.tab-content td {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    padding: 0.75rem 0.5rem;
}

/* กำหนดความกว้างแต่ละคอลัมน์ */
.tab-content th:nth-child(1), .tab-content td:nth-child(1) { width: 8%; } /* หมายเลขห้อง */
.tab-content th:nth-child(2), .tab-content td:nth-child(2) { width: 8%; } /* สถานะ */
.tab-content th:nth-child(3), .tab-content td:nth-child(3) { width: 18%; } /* ผู้เช่า */
.tab-content th:nth-child(4), .tab-content td:nth-child(4) { width: 12%; } /* ค่าเช่า */
.tab-content th:nth-child(5), .tab-content td:nth-child(5) { width: 8%; } /* ค่าน้ำ */
.tab-content th:nth-child(6), .tab-content td:nth-child(6) { width: 8%; } /* ค่าไฟ */
.tab-content th:nth-child(7), .tab-content td:nth-child(7) { width: 10%; } /* วันเริ่ม */
.tab-content th:nth-child(8), .tab-content td:nth-child(8) { width: 10%; } /* วันสิ้นสุด */
.tab-content th:nth-child(9), .tab-content td:nth-child(9) { width: 18%; } /* การจัดการ */

/* เพิ่ม tooltip สำหรับข้อความที่ถูกตัด */
.tab-content td[title] {
    cursor: help;
}
</style>

<?php require_once '../../includes/footer.php'; ?>