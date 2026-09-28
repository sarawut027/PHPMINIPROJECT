<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin('login.php');

require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/classes/ProductFactory.php';
require_once __DIR__ . '/classes/CustomerGuard.php';
require_once __DIR__ . '/classes/PricingStrategy.php';
require_once __DIR__ . '/classes/Order.php';

$db = Database::getInstance()->getConnection();
$currentUser = getCurrentUser();

// ดึงสินค้าที่มีสต็อกพร้อมขาย
$stmt = $db->query("SELECT * FROM products WHERE (type='flower' AND stock_grams > 0) OR (type='oil' AND stock_bottles > 0) OR (type='plant' AND stock_pieces > 0) ORDER BY type, name");
$products = $stmt->fetchAll();

$posError = '';
$posSuccess = '';

// จัดการการส่งฟอร์มชำระเงิน (Checkout)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'checkout') {
    $birthdate = trim($_POST['customer_birthdate'] ?? '');
    $cashReceived = floatval($_POST['cash_received'] ?? 0);
    $cartItemsJson = $_POST['cart_items'] ?? '[]';
    $cartItems = json_decode($cartItemsJson, true);

    if (empty($birthdate)) {
        $posError = 'กรุณาระบุวันเดือนปีเกิดของลูกค้าเพื่อตรวจสอบอายุตามกฎหมาย';
    } elseif (!CustomerGuard::verifyAge($birthdate)) {
        $calculatedAge = CustomerGuard::calculateAge($birthdate);
        $posError = "ระบบไม่อนุญาตให้ทำรายการขาย! ลูกค้าอายุ {$calculatedAge} ปี (ต้องมีอายุ 20 ปีบริบูรณ์ขึ้นไปเท่านั้น)";
    } elseif (empty($cartItems)) {
        $posError = 'กรุณาเลือกสินค้าลงตะกร้าก่อนดำเนินการชำระเงิน';
    } else {
        // สร้าง Object Order และประมวลผล
        $order = new Order();
        $pricingStrategy = new BulkPricing();
        $hasError = false;

        foreach ($cartItems as $item) {
            $productId = (int)$item['id'];
            $qty = floatval($item['qty']);

            // ดึงข้อมูลสินค้าจาก DB
            $pStmt = $db->prepare("SELECT * FROM products WHERE id = :id LIMIT 1");
            $pStmt->execute([':id' => $productId]);
            $pRow = $pStmt->fetch();

            if ($pRow) {
                $productObj = ProductFactory::createFromRow($pRow);
                if ($productObj) {
                    if (!$order->addItem($productObj, $qty, $pricingStrategy)) {
                        $posError = "สินค้า '{$productObj->getName()}' มีสต็อกไม่เพียงพอต่อการสั่งซื้อ";
                        $hasError = true;
                        break;
                    }
                }
            }
        }

        if (!$hasError) {
            $result = $order->checkout($birthdate, $cashReceived, $currentUser['id']);
            if ($result['success']) {
                header("Location: receipt.php?order_id=" . $result['order_id']);
                exit();
            } else {
                $posError = $result['message'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>หน้าร้านขาย POS - จุดชำระเงิน</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .pos-grid {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 20px;
            align-items: start;
        }
        @media (max-width: 900px) {
            .pos-grid { grid-template-columns: 1fr; }
        }
        .catalog-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 14px;
        }
        .pos-item-card {
            background: #ffffff;
            border: 1.5px solid var(--border-subtle);
            border-radius: var(--radius-md);
            padding: 14px;
            cursor: pointer;
            transition: all var(--transition-fast);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .pos-item-card:hover {
            border-color: var(--color-primary);
            box-shadow: var(--shadow-sm);
            transform: translateY(-2px);
        }
        .cart-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.88rem;
        }
        .cart-table th, .cart-table td {
            padding: 8px 6px;
            border-bottom: 1px solid var(--border-subtle);
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <main class="app-container">
        <div class="page-header">
            <div>
                <h1 class="page-title">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                    จุดขายหน้าร้าน (Dispensary POS)
                </h1>
                <p class="page-subtitle">ทำรายการขาย ตรวจสอบอายุลูกค้าตามกฎหมาย และคำนวณส่วนลดตามน้ำหนัก</p>
            </div>
            <div style="font-size: 0.9rem; color: var(--text-secondary);">
                แคชเชียร์: <strong><?= htmlspecialchars($currentUser['full_name']) ?></strong>
            </div>
        </div>

        <?php if (!empty($posError)): ?>
            <div class="alert alert-danger">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <span><?= htmlspecialchars($posError) ?></span>
            </div>
        <?php endif; ?>

        <div class="pos-grid">
            <!-- คอลัมน์ซ้าย: แคตตาล็อกสินค้า -->
            <div>
                <div class="card" style="margin-bottom: 16px; padding: 14px 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap;">
                        <div style="font-weight: 600; color: var(--color-primary-dark);">
                            เลือกสินค้าใส่ตะกร้า
                        </div>
                        <div class="filter-pills" style="margin-bottom: 0;">
                            <button type="button" class="filter-pill active" onclick="filterCatalog('all', this)">ทั้งหมด</button>
                            <button type="button" class="filter-pill" onclick="filterCatalog('flower', this)">🌸 ช่อดอก</button>
                            <button type="button" class="filter-pill" onclick="filterCatalog('oil', this)">💧 น้ำมัน</button>
                            <button type="button" class="filter-pill" onclick="filterCatalog('plant', this)">🌱 ต้นพันธุ์</button>
                        </div>
                    </div>
                </div>

                <div class="catalog-grid" id="catalogContainer">
                    <?php foreach ($products as $p): 
                        $pObj = ProductFactory::createFromRow($p);
                        if (!$pObj) continue;
                    ?>
                        <div class="pos-item-card catalog-card" data-type="<?= $p['type'] ?>" 
                             onclick="addToCart(<?= $p['id'] ?>, '<?= addslashes(htmlspecialchars($p['name'])) ?>', <?= $p['base_price'] ?>, '<?= $p['type'] ?>', '<?= $pObj->getStockUnit() ?>', <?= $pObj->getStockValue() ?>)">
                            <div>
                                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 6px;">
                                    <span class="badge badge-<?= $p['type'] ?>" style="font-size: 0.72rem;">
                                        <?= strtoupper($p['type']) ?>
                                    </span>
                                    <span style="font-size: 0.78rem; color: var(--text-muted);">
                                        สต็อก: <?= $pObj->getStockDisplay() ?>
                                    </span>
                                </div>
                                <div style="font-weight: 600; color: var(--color-primary-dark); font-size: 0.95rem; margin-bottom: 4px;">
                                    <?= htmlspecialchars($p['name']) ?>
                                </div>
                                <div style="font-size: 0.78rem; color: var(--text-secondary); margin-bottom: 8px;">
                                    <?= ($p['type'] === 'flower') ? 'สายพันธุ์: ' . htmlspecialchars($p['strain_type'] ?? '') : (($p['type'] === 'oil') ? 'วิธีสกัด: ' . htmlspecialchars($p['extraction_method'] ?? '') : 'อายุ: ' . ($p['age_weeks'] ?? 0) . ' สัปดาห์') ?>
                                </div>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border-subtle); padding-top: 8px; margin-top: 6px;">
                                <span style="font-weight: 700; color: var(--color-primary); font-size: 1.05rem;">
                                    ฿<?= number_format($p['base_price'], 2) ?>
                                </span>
                                <span class="btn btn-primary btn-sm" style="padding: 4px 10px; font-size: 0.8rem;">+ เพิ่ม</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- คอลัมน์ขวา: ตะกร้าสินค้าและการคิดเงิน -->
            <div class="card" style="position: sticky; top: 80px;">
                <div class="card-header" style="margin-bottom: 14px; padding-bottom: 10px;">
                    <h2 class="card-title" style="font-size: 1.1rem;">
                        🛒 รายการคำสั่งซื้อ (<span id="cartCount">0</span>)
                    </h2>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="clearCart()">ล้างตะกร้า</button>
                </div>

                <!-- รายการในตะกร้า -->
                <div style="max-height: 220px; overflow-y: auto; margin-bottom: 14px;">
                    <table class="cart-table">
                        <thead>
                            <tr>
                                <th>สินค้า</th>
                                <th style="width: 75px; text-align: center;">จำนวน</th>
                                <th style="width: 75px; text-align: right;">รวม (฿)</th>
                                <th style="width: 25px;"></th>
                            </tr>
                        </thead>
                        <tbody id="cartTableBody">
                            <tr>
                                <td colspan="4" style="text-align: center; color: var(--text-muted); padding: 24px 0;">
                                    ยังไม่มีสินค้าในตะกร้า
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- สรุปตัวเลขราคาและส่วนลดกลยุทธ์ (PricingStrategy) -->
                <div style="background: var(--bg-surface-subtle); border-radius: var(--radius-sm); padding: 12px; margin-bottom: 16px; font-size: 0.9rem;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                        <span>ยอดรวมสินค้า:</span>
                        <span id="subtotalDisplay">฿0.00</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 4px; color: var(--color-primary); font-weight: 600;">
                        <span>ส่วนลดโปรโมชั่นน้ำหนัก:</span>
                        <span id="discountDisplay">-฿0.00</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 1.15rem; font-weight: 700; color: var(--color-primary-dark); border-top: 1px solid var(--border-subtle); padding-top: 8px; margin-top: 6px;">
                        <span>ยอดสุทธิ:</span>
                        <span id="netDisplay">฿0.00</span>
                    </div>
                </div>

                <!-- ฟอร์มชำระเงิน พร้อมตรวจสอบอายุ 20+ ตามผังงาน Flowchart -->
                <form action="pos.php" method="POST" id="checkoutForm" onsubmit="return validateCheckout()">
                    <input type="hidden" name="action" value="checkout">
                    <input type="hidden" name="cart_items" id="cartItemsInput" value="[]">

                    <div class="form-group" style="margin-bottom: 12px;">
                        <label for="customer_birthdate" class="form-label" style="font-size: 0.85rem;">
                            🎂 วันเกิดลูกค้า (ตรวจอายุ 20+) <span class="required">*</span>
                        </label>
                        <input type="date" id="customer_birthdate" name="customer_birthdate" class="form-control" required value="1998-05-15">
                        <div class="form-hint" id="agePreview">อายุคำนวณ: ผ่านเกณฑ์ 20+</div>
                    </div>

                    <div class="form-group" style="margin-bottom: 14px;">
                        <label for="cash_received" class="form-label" style="font-size: 0.85rem;">
                            💵 รับเงินสด (บาท) <span class="required">*</span>
                        </label>
                        <input type="number" step="0.01" min="0" id="cash_received" name="cash_received" class="form-control" required placeholder="0.00">
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        ชำระเงินและออกใบเสร็จ
                    </button>
                </form>
            </div>
        </div>
    </main>

    <script>
        let cart = [];

        function addToCart(id, name, price, type, unit, maxStock) {
            const defaultStep = (type === 'flower') ? 1.0 : 1;
            const existing = cart.find(item => item.id === id);
            if (existing) {
                if (existing.qty + defaultStep <= maxStock) {
                    existing.qty += defaultStep;
                } else {
                    alert('สต็อกคงเหลือไม่เพียงพอ (มีจำหน่าย ' + maxStock + ' ' + unit + ')');
                }
            } else {
                cart.push({
                    id: id,
                    name: name,
                    price: price,
                    type: type,
                    unit: unit,
                    maxStock: maxStock,
                    qty: (type === 'flower' ? 3.5 : 1) // default 3.5g for flower jar
                });
            }
            renderCart();
        }

        function updateQty(id, newQty) {
            const item = cart.find(i => i.id === id);
            if (item) {
                const val = parseFloat(newQty);
                if (isNaN(val) || val <= 0) {
                    removeFromCart(id);
                } else if (val > item.maxStock) {
                    alert('สต็อกคงเหลือไม่เพียงพอ (คงเหลือ ' + item.maxStock + ' ' + item.unit + ')');
                    item.qty = item.maxStock;
                    renderCart();
                } else {
                    item.qty = val;
                    renderCart();
                }
            }
        }

        function removeFromCart(id) {
            cart = cart.filter(item => item.id !== id);
            renderCart();
        }

        function clearCart() {
            cart = [];
            renderCart();
        }

        function renderCart() {
            const tbody = document.getElementById('cartTableBody');
            const cartCount = document.getElementById('cartCount');
            const cartInput = document.getElementById('cartItemsInput');
            
            cartCount.innerText = cart.length;
            cartInput.value = JSON.stringify(cart);

            if (cart.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" style="text-align: center; color: var(--text-muted); padding: 24px 0;">ยังไม่มีสินค้าในตะกร้า</td></tr>';
                document.getElementById('subtotalDisplay').innerText = '฿0.00';
                document.getElementById('discountDisplay').innerText = '-฿0.00';
                document.getElementById('netDisplay').innerText = '฿0.00';
                return;
            }

            let subtotal = 0;
            let totalDiscount = 0;
            tbody.innerHTML = '';

            cart.forEach(item => {
                const itemSubtotal = item.qty * item.price;
                // คำนวณส่วนลด BulkPricing จำลองในฝั่ง client
                let discountRate = 0;
                if (item.qty >= 28) discountRate = 0.20;
                else if (item.qty >= 10) discountRate = 0.15;
                else if (item.qty >= 5) discountRate = 0.10;
                else if (item.qty >= 3.5) discountRate = 0.05;

                const itemDiscount = itemSubtotal * discountRate;
                const finalItemPrice = itemSubtotal - itemDiscount;

                subtotal += itemSubtotal;
                totalDiscount += itemDiscount;

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>
                        <div style="font-weight: 600;">${item.name}</div>
                        <div style="font-size: 0.75rem; color: var(--text-muted);">฿${item.price.toFixed(2)}/${item.unit}</div>
                    </td>
                    <td style="text-align: center;">
                        <input type="number" step="${item.type === 'flower' ? '0.1' : '1'}" min="0.1" 
                               value="${item.qty}" style="width: 60px; padding: 4px; text-align: center;" 
                               onchange="updateQty(${item.id}, this.value)">
                    </td>
                    <td style="text-align: right; font-weight: 600;">
                        ฿${finalItemPrice.toFixed(2)}
                    </td>
                    <td style="text-align: right;">
                        <button type="button" onclick="removeFromCart(${item.id})" style="border:none; background:none; color:red; cursor:pointer; font-weight:bold;">&times;</button>
                    </td>
                `;
                tbody.appendChild(tr);
            });

            const netTotal = subtotal - totalDiscount;
            document.getElementById('subtotalDisplay').innerText = '฿' + subtotal.toFixed(2);
            document.getElementById('discountDisplay').innerText = '-฿' + totalDiscount.toFixed(2);
            document.getElementById('netDisplay').innerText = '฿' + netTotal.toFixed(2);
            document.getElementById('cash_received').value = netTotal.toFixed(2);
        }

        function filterCatalog(type, btn) {
            document.querySelectorAll('.filter-pill').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            const cards = document.querySelectorAll('.catalog-card');
            cards.forEach(card => {
                if (type === 'all' || card.getAttribute('data-type') === type) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        function validateCheckout() {
            if (cart.length === 0) {
                alert('กรุณาเลือกสินค้าใส่ตะกร้าก่อนดำเนินการ');
                return false;
            }

            const birthdate = document.getElementById('customer_birthdate').value;
            if (!birthdate) {
                alert('กรุณาระบุวันเดือนปีเกิดของลูกค้า');
                return false;
            }

            const dob = new Date(birthdate);
            const today = new Date();
            let age = today.getFullYear() - dob.getFullYear();
            const m = today.getMonth() - dob.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) {
                age--;
            }

            if (age < 20) {
                alert('ไม่อนุญาตให้ทำรายการขาย! ลูกค้าอายุ ' + age + ' ปี (ต้องอายุ 20 ปีขึ้นไป)');
                return false;
            }

            return true;
        }

        // Live check age on birthdate change
        document.getElementById('customer_birthdate').addEventListener('change', function() {
            const dob = new Date(this.value);
            const today = new Date();
            let age = today.getFullYear() - dob.getFullYear();
            const m = today.getMonth() - dob.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) {
                age--;
            }
            const preview = document.getElementById('agePreview');
            if (age >= 20) {
                preview.innerText = `อายุคำนวณ: ${age} ปี (ผ่านเกณฑ์ 20+ ✓)`;
                preview.style.color = 'var(--status-success)';
            } else {
                preview.innerText = `อายุคำนวณ: ${age} ปี (ไม่ผ่านเกณฑ์ ❌ ต้อง 20 ปีขึ้นไป)`;
                preview.style.color = 'var(--status-danger)';
            }
        });
    </script>
</body>
</html>
