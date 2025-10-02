<?php
require_once 'includes/config.php';
requireLogin();

// รับ contract_id
$contract_id = isset($_GET['contract_id']) ? intval($_GET['contract_id']) : 0;

if (!$contract_id) {
    die('ไม่พบเลขที่สัญญา');
}

// ดึงข้อมูลสัญญา
$contract = getContractData($pdo, $contract_id);

if (!$contract) {
    die('ไม่พบข้อมูลสัญญา');
}

// เช็คสิทธิ์ - admin ดูได้ทุกสัญญา, user ดูได้เฉพาะสัญญาตัวเอง
if (!isAdmin() && $contract['user_id'] != $_SESSION['user_id']) {
    die('คุณไม่มีสิทธิ์เข้าถึงสัญญานี้');
}

// อ่าน template
$template_path = __DIR__ . '/templates/contract_template.html';
if (!file_exists($template_path)) {
    die('ไม่พบไฟล์ template สัญญา');
}

$template = file_get_contents($template_path);

// แทนที่ placeholders
$html = replaceContractPlaceholders($template, $contract);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>&nbsp;</title>
    <style>
        body {
            font-family: "Sarabun", "THSarabunNew", sans-serif;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;

        }
        .contract-container {
            background: white;
            padding: 40px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .actions {
            text-align: center;
            padding: 20px;
            background: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            margin: 0 10px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            text-decoration: none;
            color: white;
        }
        .btn-primary {
            background: #007bff;
        }
        .btn-success {
            background: #28a745;
        }
        .btn-secondary {
            background: #6c757d;
        }
        .btn:hover {
            opacity: 0.9;
        }
        @media print {
            @page {
                margin: 0;
                size: A4;
            }
            body {
                background: white;
                padding: 0;
                margin: 0;
            }
            .actions {
                display: none;
            }
            .contract-container {
                box-shadow: none;
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="actions">
        <button onclick="window.print()" class="btn btn-primary">🖨️ พิมพ์ / บันทึกเป็น PDF</button>
        <a href="javascript:history.back()" class="btn btn-secondary"> ย้อนกลับ</a>
    </div>

    <div class="contract-container">
        <?php echo $html; ?>
    </div>

    <script>
        // ตั้งค่า title ว่างเพื่อไม่ให้แสดง header/footer
        document.title = '';

        // เมื่อกดพิมพ์ ให้ปิด headers and footers อัตโนมัติ
        window.onbeforeprint = function() {
            // ตั้งค่าว่างเพื่อไม่ให้แสดงใน header/footer
            document.title = ' ';
        };

        window.onafterprint = function() {
            document.title = '';
        };
    </script>
</body>
</html>
