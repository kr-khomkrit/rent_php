<?php
require_once '../../includes/config.php';
requireAdmin();

$page_title = 'จัดการผู้ใช้';
$success_message = '';
$error_message = '';

// การดำเนินการต่างๆ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        // เพิ่มผู้ใช้ใหม่
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $phone = trim($_POST['phone']);
        $emergency_contact = trim($_POST['emergency_contact']);

        if (empty($username) || empty($password) || empty($first_name) || empty($last_name)) {
            $error_message = 'กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน';
        } else {
            try {
                // ตรวจสอบว่า username ซ้ำหรือไม่
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
                $stmt->execute([$username]);

                if ($stmt->fetchColumn() > 0) {
                    $error_message = 'Username นี้มีอยู่ในระบบแล้ว';
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("
                        INSERT INTO users (username, password, first_name, last_name, phone, emergency_contact)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$username, $hashed_password, $first_name, $last_name, $phone, $emergency_contact]);
                    $success_message = 'เพิ่มผู้ใช้เรียบร้อยแล้ว';
                }
            } catch (PDOException $e) {
                $error_message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'edit') {
        // แก้ไขผู้ใช้
        $user_id = intval($_POST['user_id']);
        $username = trim($_POST['username']);
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $phone = trim($_POST['phone']);
        $emergency_contact = trim($_POST['emergency_contact']);
        $status = $_POST['status'];

        if (empty($username) || empty($first_name) || empty($last_name)) {
            $error_message = 'กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน';
        } else {
            try {
                // ตรวจสอบว่า username ซ้ำหรือไม่ (ยกเว้นตัวเอง)
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND user_id != ?");
                $stmt->execute([$username, $user_id]);

                if ($stmt->fetchColumn() > 0) {
                    $error_message = 'Username นี้มีอยู่ในระบบแล้ว';
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE users
                        SET username = ?, first_name = ?, last_name = ?, phone = ?, emergency_contact = ?, status = ?
                        WHERE user_id = ? AND role = 'user'
                    ");
                    $stmt->execute([$username, $first_name, $last_name, $phone, $emergency_contact, $status, $user_id]);
                    $success_message = 'แก้ไขข้อมูลผู้ใช้เรียบร้อยแล้ว';
                }
            } catch (PDOException $e) {
                $error_message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        // ลบผู้ใช้
        $user_id = intval($_POST['user_id']);
        try {
            // ตรวจสอบว่ามีสัญญาอยู่หรือไม่
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM contracts WHERE user_id = ? AND status = 'active'");
            $stmt->execute([$user_id]);

            if ($stmt->fetchColumn() > 0) {
                $error_message = 'ไม่สามารถลบผู้ใช้ที่มีสัญญาที่ยังใช้งานอยู่ได้';
            } else {
                $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ? AND role = 'user'");
                $stmt->execute([$user_id]);
                $success_message = 'ลบผู้ใช้เรียบร้อยแล้ว';
            }
        } catch (PDOException $e) {
            $error_message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
        }
    }
}

// ดึงข้อมูลผู้ใช้ทั้งหมด
try {
    $stmt = $pdo->query("
        SELECT u.*,
               CONCAT(z.zone_name, '-', r.room_number) as current_room,
               c.rental_price
        FROM users u
        LEFT JOIN contracts c ON u.user_id = c.user_id AND c.status = 'active'
        LEFT JOIN rooms r ON c.room_id = r.room_id
        LEFT JOIN zones z ON r.zone_id = z.zone_id
        WHERE u.role = 'user'
        ORDER BY u.created_at DESC
    ");
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = 'เกิดข้อผิดพลาดในการโหลดข้อมูล: ' . $e->getMessage();
}

require_once '../../includes/header.php';
?>

<h1 class="page-title">จัดการผู้ใช้</h1>

<?php if ($success_message): ?>
    <div class="alert alert-success"><?php echo h($success_message); ?></div>
<?php endif; ?>

<?php if ($error_message): ?>
    <div class="alert alert-error"><?php echo h($error_message); ?></div>
<?php endif; ?>

<!-- ฟอร์มเพิ่มผู้ใช้ใหม่ -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">เพิ่มผู้ใช้ใหม่</h2>
    </div>
    <form method="POST" action="">
        <input type="hidden" name="action" value="add">

        <div class="form-row">
            <div class="form-col">
                <div class="form-group">
                    <label for="username">Username *</label>
                    <input type="text" id="username" name="username" class="form-control" required>
                </div>
            </div>
            <div class="form-col">
                <div class="form-group">
                    <label for="password">Password *</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>
            </div>
        </div>

        <div class="form-row">
            <div class="form-col">
                <div class="form-group">
                    <label for="first_name">ชื่อ *</label>
                    <input type="text" id="first_name" name="first_name" class="form-control" required>
                </div>
            </div>
            <div class="form-col">
                <div class="form-group">
                    <label for="last_name">นามสกุล *</label>
                    <input type="text" id="last_name" name="last_name" class="form-control" required>
                </div>
            </div>
        </div>

        <div class="form-row">
            <div class="form-col">
                <div class="form-group">
                    <label for="phone">เบอร์โทรศัพท์</label>
                    <input type="text" id="phone" name="phone" class="form-control">
                </div>
            </div>
            <div class="form-col">
                <div class="form-group">
                    <label for="emergency_contact">เบอร์ติดต่อฉุกเฉิน</label>
                    <input type="text" id="emergency_contact" name="emergency_contact" class="form-control">
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-success">เพิ่มผู้ใช้</button>
    </form>
</div>

<!-- รายชื่อผู้ใช้ -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">รายชื่อผู้ใช้ (<?php echo count($users); ?> คน)</h2>
    </div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Username</th>
                    <th>ชื่อ-นามสกุล</th>
                    <th>เบอร์โทร</th>
                    <th>ห้องปัจจุบัน</th>
                    <th>ค่าเช่า</th>
                    <th>สถานะ</th>
                    <th>วันที่สมัคร</th>
                    <th>การจัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; color: #666;">ไม่มีข้อมูลผู้ใช้</td>
                </tr>
                <?php else: ?>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo h($user['username']); ?></td>
                        <td><?php echo h($user['first_name'] . ' ' . $user['last_name']); ?></td>
                        <td><?php echo h($user['phone']); ?></td>
                        <td><?php echo $user['current_room'] ? h($user['current_room']) : '<span style="color: #999;">ไม่มี</span>'; ?></td>
                        <td><?php echo $user['rental_price'] ? formatMoney($user['rental_price']) : '-'; ?></td>
                        <td>
                            <span class="status-badge status-<?php echo $user['status']; ?>">
                                <?php echo $user['status'] === 'active' ? 'ใช้งาน' : 'ปิดใช้งาน'; ?>
                            </span>
                        </td>
                        <td><?php echo formatDate($user['created_at']); ?></td>
                        <td>
                            <button class="btn btn-warning btn-sm" onclick="editUser(<?php echo h(json_encode($user)); ?>)">แก้ไข</button>
                            <?php if (!$user['current_room']): ?>
                            <button class="btn btn-danger btn-sm" onclick="deleteUser(<?php echo $user['user_id']; ?>, '<?php echo h($user['username']); ?>')">ลบ</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal สำหรับแก้ไขผู้ใช้ -->
<div id="editUserModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem; border-radius: 10px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto;">
        <h3>แก้ไขข้อมูลผู้ใช้</h3>
        <form id="editUserForm" method="POST" action="">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="user_id" id="edit_user_id">

            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="edit_username">Username *</label>
                        <input type="text" id="edit_username" name="username" class="form-control" required>
                    </div>
                </div>
                <div class="form-col">
                    <div class="form-group">
                        <label for="edit_status">สถานะ</label>
                        <select id="edit_status" name="status" class="form-control">
                            <option value="active">ใช้งาน</option>
                            <option value="inactive">ปิดใช้งาน</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="edit_first_name">ชื่อ *</label>
                        <input type="text" id="edit_first_name" name="first_name" class="form-control" required>
                    </div>
                </div>
                <div class="form-col">
                    <div class="form-group">
                        <label for="edit_last_name">นามสกุล *</label>
                        <input type="text" id="edit_last_name" name="last_name" class="form-control" required>
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="edit_phone">เบอร์โทรศัพท์</label>
                        <input type="text" id="edit_phone" name="phone" class="form-control">
                    </div>
                </div>
                <div class="form-col">
                    <div class="form-group">
                        <label for="edit_emergency_contact">เบอร์ติดต่อฉุกเฉิน</label>
                        <input type="text" id="edit_emergency_contact" name="emergency_contact" class="form-control">
                    </div>
                </div>
            </div>

            <div style="margin-top: 1.5rem; display: flex; gap: 1rem;">
                <button type="submit" class="btn btn-success">บันทึก</button>
                <button type="button" class="btn btn-danger" onclick="closeEditModal()">ยกเลิก</button>
            </div>
        </form>
    </div>
</div>

<script>
function editUser(user) {
    document.getElementById('edit_user_id').value = user.user_id;
    document.getElementById('edit_username').value = user.username;
    document.getElementById('edit_first_name').value = user.first_name;
    document.getElementById('edit_last_name').value = user.last_name;
    document.getElementById('edit_phone').value = user.phone || '';
    document.getElementById('edit_emergency_contact').value = user.emergency_contact || '';
    document.getElementById('edit_status').value = user.status;

    document.getElementById('editUserModal').style.display = 'block';
}

function closeEditModal() {
    document.getElementById('editUserModal').style.display = 'none';
}

function deleteUser(userId, username) {
    if (confirm('ต้องการลบผู้ใช้ ' + username + ' หรือไม่?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '';

        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete';

        const userIdInput = document.createElement('input');
        userIdInput.type = 'hidden';
        userIdInput.name = 'user_id';
        userIdInput.value = userId;

        form.appendChild(actionInput);
        form.appendChild(userIdInput);
        document.body.appendChild(form);
        form.submit();
    }
}

// ปิด Modal เมื่อคลิกนอกพื้นที่
document.getElementById('editUserModal').onclick = function(e) {
    if (e.target === this) {
        closeEditModal();
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>