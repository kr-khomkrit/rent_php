<?php
require_once '../../includes/config.php';
requireLogin();

// ตรวจสอบว่าเป็น user เท่านั้น
if (isAdmin()) {
    header('Location: ../admin/dashboard.php');
    exit();
}

$page_title = 'สัญญาเช่าของฉัน';
$user_id = $_SESSION['user_id'];

// ดึงข้อมูลสัญญาของ user
try {
    $stmt = $pdo->prepare("
        SELECT c.*,
               CONCAT(z.zone_name, '-', r.room_number) as room_name,
               r.water_rate, r.electricity_rate
        FROM contracts c
        JOIN rooms r ON c.room_id = r.room_id
        JOIN zones z ON r.zone_id = z.zone_id
        WHERE c.user_id = ?
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $contracts = $stmt->fetchAll();

} catch (PDOException $e) {
    $error_message = 'เกิดข้อผิดพลาดในการโหลดข้อมูล: ' . $e->getMessage();
}

require_once '../../includes/header.php';
?>

<h1 class="page-title">สัญญาเช่าของฉัน</h1>

<?php if (isset($error_message)): ?>
    <div class="alert alert-error"><?php echo h($error_message); ?></div>
<?php endif; ?>

<?php if (empty($contracts)): ?>
    <!-- ไม่มีสัญญา -->
    <div class="card">
        <div style="text-align: center; padding: 3rem; color: #666;">
            <div style="font-size: 4rem; margin-bottom: 1rem;">📄</div>
            <h2>คุณยังไม่มีสัญญาเช่า</h2>
            <p>กรุณาติดต่อผู้ดูแลระบบเพื่อทำสัญญาเช่าห้อง</p>
        </div>
    </div>
<?php else: ?>
    <!-- แสดงสัญญาแบบ Card -->
    <?php foreach ($contracts as $contract): ?>
        <?php
        // ตรวจสอบสถานะสัญญา
        $is_active = ($contract['status'] === 'active');
        $is_expired = ($contract['status'] === 'active' && strtotime($contract['end_date']) < strtotime('today'));
        $days_left = '';

        if ($is_active && !$is_expired) {
            $days_diff = (strtotime($contract['end_date']) - strtotime('today')) / (60 * 60 * 24);
            if ($days_diff > 0) {
                $days_left = ceil($days_diff);
            }
        }

        // สีของ card ตามสถานะ
        $border_color = '#28a745'; // เขียว
        if ($is_expired) {
            $border_color = '#dc3545'; // แดง
        } elseif ($contract['status'] === 'terminated') {
            $border_color = '#6c757d'; // เทา
        } elseif ($days_left > 0 && $days_left <= 30) {
            $border_color = '#ffc107'; // เหลือง
        }
        ?>

        <div class="card" style="margin-bottom: 1.5rem; border-left: 5px solid <?php echo $border_color; ?>;">
            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h3 style="margin: 0; color: #667eea;">สัญญาเลขที่: <?php echo h($contract['contract_number'] ?: 'C' . str_pad($contract['contract_id'], 4, '0', STR_PAD_LEFT)); ?></h3>
                    <small style="color: #999;">สร้างเมื่อ: <?php echo formatDate($contract['created_at']); ?></small>
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <a href="../../contract_view.php?contract_id=<?php echo $contract['contract_id']; ?>"
                       class="btn btn-success"
                       target="_blank"
                       title="ดูและพิมพ์สัญญา">
                        📄 ดู/พิมพ์สัญญา
                    </a>
                    <span class="status-badge status-<?php echo $is_expired ? 'overdue' : $contract['status']; ?>" style="padding: 0.5rem 1rem;">
                        <?php
                        if ($is_expired) {
                            echo 'หมดอายุ';
                        } else {
                            $status_text = [
                                'active' => 'ใช้งานอยู่',
                                'expired' => 'หมดอายุ',
                                'terminated' => 'ยกเลิกแล้ว'
                            ];
                            echo $status_text[$contract['status']] ?? $contract['status'];
                        }
                        ?>
                    </span>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
                <!-- ห้อง -->
                <div style="background: #f8f9fa; padding: 1rem; border-radius: 8px;">
                    <div style="color: #666; font-size: 0.9rem; margin-bottom: 0.5rem;">🏠 ห้อง</div>
                    <div style="font-size: 1.5rem; font-weight: 600; color: #667eea;">
                        <?php echo h($contract['room_name']); ?>
                    </div>
                </div>

                <!-- ค่าเช่า -->
                <div style="background: #f8f9fa; padding: 1rem; border-radius: 8px;">
                    <div style="color: #666; font-size: 0.9rem; margin-bottom: 0.5rem;">💰 ค่าเช่า</div>
                    <div style="font-size: 1.5rem; font-weight: 600;">
                        ฿<?php echo formatMoney($contract['rental_price']); ?>
                    </div>
                    <small style="color: #999;">/ เดือน</small>
                </div>

                <!-- ค่าน้ำ -->
                <div style="background: #f8f9fa; padding: 1rem; border-radius: 8px;">
                    <div style="color: #666; font-size: 0.9rem; margin-bottom: 0.5rem;">💧 อัตราค่าน้ำ</div>
                    <div style="font-size: 1.2rem; font-weight: 600;">
                        ฿<?php echo formatMoney($contract['water_rate']); ?>
                    </div>
                    <small style="color: #999;">/ หน่วย</small>
                </div>

                <!-- ค่าไฟ -->
                <div style="background: #f8f9fa; padding: 1rem; border-radius: 8px;">
                    <div style="color: #666; font-size: 0.9rem; margin-bottom: 0.5rem;">⚡ อัตราค่าไฟ</div>
                    <div style="font-size: 1.2rem; font-weight: 600;">
                        ฿<?php echo formatMoney($contract['electricity_rate']); ?>
                    </div>
                    <small style="color: #999;">/ หน่วย</small>
                </div>
            </div>

            <!-- ระยะเวลาสัญญา -->
            <div style="border-top: 2px solid #e5e7eb; padding-top: 1rem;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <strong style="color: #666;">วันที่เริ่มสัญญา:</strong><br>
                        <?php echo formatThaiDate($contract['start_date']); ?>
                    </div>
                    <div>
                        <strong style="color: #666;">วันที่สิ้นสุดสัญญา:</strong><br>
                        <?php echo formatThaiDate($contract['end_date']); ?>
                        <?php if ($days_left > 0): ?>
                            <br><span style="color: <?php echo $days_left <= 30 ? '#dc3545' : '#28a745'; ?>; font-weight: bold;">
                                (อีก <?php echo $days_left; ?> วัน)
                            </span>
                        <?php elseif ($is_expired): ?>
                            <br><span style="color: #dc3545; font-weight: bold;">(หมดอายุแล้ว)</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- เงื่อนไขพิเศษ -->
            <?php if (!empty($contract['contract_terms'])): ?>
                <div style="margin-top: 1rem; padding: 1rem; background: #fff3cd; border-radius: 5px; border-left: 4px solid #ffc107;">
                    <strong>📋 เงื่อนไขพิเศษ:</strong><br>
                    <div style="margin-top: 0.5rem; white-space: pre-wrap;"><?php echo nl2br(h($contract['contract_terms'])); ?></div>
                </div>
            <?php endif; ?>

            <!-- การแจ้งเตือน -->
            <?php if ($is_expired): ?>
                <div style="background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 5px; margin-top: 1rem;">
                    ⚠️ <strong>สัญญาหมดอายุ!</strong> กรุณาติดต่อผู้ดูแลระบบเพื่อต่อสัญญา
                </div>
            <?php elseif ($days_left > 0 && $days_left <= 30): ?>
                <div style="background: #fff3cd; color: #856404; padding: 1rem; border-radius: 5px; margin-top: 1rem;">
                    ⏰ <strong>สัญญาใกล้หมดอายุ!</strong> เหลืออีก <?php echo $days_left; ?> วัน กรุณาเตรียมต่อสัญญา
                </div>
            <?php elseif ($contract['status'] === 'terminated'): ?>
                <div style="background: #f8f9fa; color: #6c757d; padding: 1rem; border-radius: 5px; margin-top: 1rem;">
                    ℹ️ สัญญานี้ถูกยกเลิกแล้ว
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <!-- คำแนะนำ -->
    <div class="card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
        <h3 style="margin-top: 0;">💡 คำแนะนำ</h3>
        <ul style="padding-left: 1.5rem; margin: 0;">
            <li>กดปุ่ม "ดาวน์โหลด PDF" เพื่อบันทึกสัญญาไว้ในเครื่องของคุณ</li>
            <li>ตรวจสอบวันที่สิ้นสุดสัญญาเป็นประจำ</li>
            <li>หากต้องการต่อสัญญา กรุณาติดต่อผู้ดูแลระบบล่วงหน้าอย่างน้อย 30 วัน</li>
            <li>สัญญาเช่าเป็นเอกสารสำคัญ ควรเก็บรักษาไว้เป็นหลักฐาน</li>
        </ul>
    </div>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>
