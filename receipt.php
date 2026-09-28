<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin('login.php');

require_once __DIR__ . '/config/Database.php';

$db = Database::getInstance()->getConnection();

$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

// หากไม่ได้ระบุ order_id ให้ดึงออเดอร์ล่าสุดมาแสดง (อำนวยความสะดวกในการทดสอบ)
if ($orderId <= 0) {
    $latestStmt = $db->query("SELECT id FROM orders ORDER BY id DESC LIMIT 1");
    $latest = $latestStmt->fetch();
    if ($latest) {
        $orderId = (int)$latest['id'];
    }
}

$order = null;
$orderItems = [];

if ($orderId > 0) {
    $orderStmt = $db->prepare("SELECT o.*, u.full_name as cashier_name, u.username as cashier_user
        FROM orders o 
        LEFT JOIN users u ON o.user_id = u.id 
        WHERE o.id = :id LIMIT 1");
    $orderStmt->execute([':id' => $orderId]);
    $order = $orderStmt->fetch();

    if ($order) {
        $itemsStmt = $db->prepare("SELECT * FROM order_items WHERE order_id = :order_id ORDER BY id ASC");
        $itemsStmt->execute([':order_id' => $orderId]);
        $orderItems = $itemsStmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ใบเสร็จรับเงิน #<?= htmlspecialchars($order['order_code'] ?? 'N/A') ?> - Dispensary POS</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* สไตล์เสริมสำหรับใบเสร็จความร้อน */
        .tax-invoice-badge {
            display: inline-block;
            border: 1px solid #111;
            padding: 2px 8px;
            font-size: 0.75rem;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: 1px;
        }
        .verified-age-box {
            background: #f0fdf4;
            border: 1px dashed #22c55e;
            color: #15803d;
            font-size: 0.8rem;
            font-weight: 600;
            padding: 6px;
            text-align: center;
            border-radius: 6px;
            margin: 10px 0;
        }
    </style>
</head>
<body style="background: #eef2ef;">
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <main class="receipt-wrapper">
        <?php if (!$order): ?>
            <div class="card" style="text-align: center; padding: 40px 20px;">
                <div style="font-size: 2.5rem; margin-bottom: 12px;">🧾</div>
                <h2 style="color: var(--color-primary-dark); font-size: 1.3rem;">ไม่พบข้อมูลใบเสร็จ</h2>
                <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 6px;">ยังไม่มีคำสั่งซื้อในระบบ หรือหมายเลขออเดอร์ไม่ถูกต้อง</p>
                <div style="margin-top: 20px;">
                    <a href="pos.php" class="btn btn-primary">ไปที่หน้าร้าน POS เพื่อสร้างออเดอร์</a>
                </div>
            </div>
        <?php else: ?>
            <!-- สลิปใบเสร็จ POS ขนาดมาตรฐาน 80mm -->
            <div class="receipt-slip" id="thermalSlip">
                <div class="receipt-header">
                    <div class="receipt-logo">🌿</div>
                    <div class="receipt-store-name">BOTANICAL DISPENSARY</div>
                    <div class="receipt-meta">
                        123 ถนนสมุนไพร เขตวัฒนา กรุงเทพฯ 10110<br>
                        โทร: 02-123-4567 | ใบอนุญาต: DISP-2026-088<br>
                        เลขประจำตัวผู้เสียภาษี: 0-1055-67012-34-5
                    </div>
                    <div style="margin-top: 10px;">
                        <span class="tax-invoice-badge">ใบเสร็จรับเงิน / ใบกำกับภาษีอย่างย่อ</span>
                    </div>
                </div>

                <div class="receipt-meta" style="margin-bottom: 10px;">
                    <div style="display: flex; justify-content: space-between;">
                        <span><strong>เลขที่บิล:</strong> <?= htmlspecialchars($order['order_code']) ?></span>
                        <span><strong>วันที่:</strong> <?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-top: 4px;">
                        <span><strong>พนักงาน:</strong> <?= htmlspecialchars($order['cashier_name'] ?? 'พนักงานขาย') ?></span>
                        <span><strong>เครื่อง:</strong> POS-01</span>
                    </div>
                </div>

                <!-- ป้ายรับรองการตรวจสอบอายุ 20+ -->
                <div class="verified-age-box">
                    ✓ ผ่านการตรวจบัตรประชาชน: อายุ <?= (int)$order['customer_age'] ?> ปีบริบูรณ์<br>
                    <span style="font-size: 0.72rem; color: #166534; font-weight: 400;">
                        (วันเกิด: <?= date('d/m/Y', strtotime($order['customer_birthdate'])) ?> ตาม พ.ร.บ. กฎหมายกำหนด)
                    </span>
                </div>

                <div class="receipt-divider"></div>

                <!-- ตารางรายการสินค้า -->
                <table class="receipt-table">
                    <thead>
                        <tr>
                            <th style="width: 50%;">รายการ</th>
                            <th style="width: 25%; text-align: right;">จำนวน</th>
                            <th style="width: 25%; text-align: right;">จำนวนเงิน</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orderItems as $item): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 600; color: #111;">
                                        <?= htmlspecialchars($item['product_name']) ?>
                                    </div>
                                    <div style="font-size: 0.74rem; color: #6b7280;">
                                        @฿<?= number_format($item['unit_price'], 2) ?>/<?= htmlspecialchars($item['unit_label']) ?>
                                    </div>
                                    <?php if ($item['discount'] > 0): ?>
                                        <div style="font-size: 0.74rem; color: #15803d; font-style: italic;">
                                            ส่วนลดโปรโมชั่น: -฿<?= number_format($item['discount'], 2) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right; font-weight: 500;">
                                    <?= rtrim(rtrim(number_format($item['quantity'], 2), '0'), '.') ?> <?= htmlspecialchars($item['unit_label']) ?>
                                </td>
                                <td style="text-align: right; font-weight: 600;">
                                    ฿<?= number_format($item['final_price'], 2) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="receipt-divider"></div>

                <!-- สรุปยอดเงิน -->
                <div class="receipt-totals">
                    <div class="receipt-row">
                        <span>ยอดรวมสินค้า (Subtotal):</span>
                        <span>฿<?= number_format($order['total_amount'], 2) ?></span>
                    </div>

                    <?php if ($order['discount_amount'] > 0): ?>
                        <div class="receipt-row" style="color: #15803d; font-weight: 600;">
                            <span>ส่วนลดโปรโมชั่น (Bulk Discount):</span>
                            <span>-฿<?= number_format($order['discount_amount'], 2) ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="receipt-row grand-total">
                        <span>ยอดรวมสุทธิ (NET TOTAL):</span>
                        <span>฿<?= number_format($order['net_amount'], 2) ?></span>
                    </div>

                    <div class="receipt-row">
                        <span>รับเงินสด (Cash Tendered):</span>
                        <span>฿<?= number_format($order['cash_received'], 2) ?></span>
                    </div>

                    <div class="receipt-row" style="font-weight: 700; color: #1e3a8a;">
                        <span>เงินทอน (Change Due):</span>
                        <span>฿<?= number_format($order['change_returned'], 2) ?></span>
                    </div>
                </div>

                <!-- จำลองบาร์โค้ดสลิปและคำขอบคุณ -->
                <div class="receipt-footer">
                    <div class="barcode-simulator"></div>
                    <div style="font-weight: 600; color: #374151;">THANK YOU / ขอบคุณที่ใช้บริการ</div>
                    <div style="font-size: 0.72rem; margin-top: 4px;">
                        สินค้าสมุนไพรควบคุมเพื่อสุขภาพตามข้อกำหนด<br>
                        กรุณาเก็บใบเสร็จไว้เป็นหลักฐานการซื้อ
                    </div>
                </div>
            </div>

            <!-- ปุ่มดำเนินการ (ซ่อนเมื่อกดพิมพ์ด้วย @media print) -->
            <div class="receipt-actions no-print">
                <button type="button" class="btn btn-primary" onclick="window.print()" style="flex: 1;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                    พิมพ์ใบเสร็จ (Print)
                </button>
                <a href="pos.php" class="btn btn-secondary" style="flex: 1;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                    กลับหน้าร้าน POS
                </a>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
