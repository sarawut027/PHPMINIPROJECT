<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin('../login.php');

require_once __DIR__ . '/../config/Database.php';

$errorMessage = '';
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $type = trim($_POST['type'] ?? '');
    $basePrice = floatval($_POST['base_price'] ?? 0);
    $description = trim($_POST['description'] ?? '');

    // ฟิลด์เฉพาะตามประเภท
    $strainType = ($type === 'flower') ? trim($_POST['strain_type'] ?? '') : null;
    $stockGrams = ($type === 'flower') ? floatval($_POST['stock_grams'] ?? 0) : null;

    $extractionMethod = ($type === 'oil') ? trim($_POST['extraction_method'] ?? '') : null;
    $stockBottles = ($type === 'oil') ? intval($_POST['stock_bottles'] ?? 0) : null;

    $ageWeeks = ($type === 'plant') ? intval($_POST['age_weeks'] ?? 0) : null;
    $stockPieces = ($type === 'plant') ? intval($_POST['stock_pieces'] ?? 0) : null;

    // การตรวจสอบข้อมูล
    if (empty($name) || empty($type) || $basePrice <= 0) {
        $errorMessage = 'กรุณากรอกชื่อสินค้า เลือกประเภทสินค้า และกำหนดราคาเริ่มต้นให้ถูกต้อง';
    } elseif ($type === 'flower' && empty($strainType)) {
        $errorMessage = 'กรุณาระบุสายพันธุ์ (Strain Type) สำหรับช่อดอก';
    } elseif ($type === 'oil' && empty($extractionMethod)) {
        $errorMessage = 'กรุณาระบุวิธีการสกัด (Extraction Method) สำหรับน้ำมันสกัด';
    } else {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("INSERT INTO products 
                (name, type, base_price, strain_type, stock_grams, extraction_method, stock_bottles, age_weeks, stock_pieces, description)
                VALUES (:name, :type, :base_price, :strain_type, :stock_grams, :extraction_method, :stock_bottles, :age_weeks, :stock_pieces, :description)");

            $stmt->execute([
                ':name' => $name,
                ':type' => $type,
                ':base_price' => $basePrice,
                ':strain_type' => $strainType,
                ':stock_grams' => $stockGrams,
                ':extraction_method' => $extractionMethod,
                ':stock_bottles' => $stockBottles,
                ':age_weeks' => $ageWeeks,
                ':stock_pieces' => $stockPieces,
                ':description' => $description
            ]);

            $_SESSION['flash_success'] = "เพิ่มสินค้า '{$name}' เข้าสู่ระบบเรียบร้อยแล้ว";
            header("Location: product-list.php");
            exit();

        } catch (Exception $e) {
            $errorMessage = 'เกิดข้อผิดพลาดในการบันทึก: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เพิ่มสินค้าใหม่ - ระบบจัดการสินค้า</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <main class="app-container">
        <div class="page-header">
            <div>
                <h1 class="page-title">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
                    เพิ่มสินค้าใหม่
                </h1>
                <p class="page-subtitle">กรอกรายละเอียดสินค้าและกำหนดคุณสมบัติเฉพาะตามคลาสสินค้าในระบบ</p>
            </div>
            <a href="product-list.php" class="btn btn-secondary">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                กลับหน้ารายการสินค้า
            </a>
        </div>

        <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-danger">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <span><?= htmlspecialchars($errorMessage) ?></span>
            </div>
        <?php endif; ?>

        <div class="card" style="max-width: 860px; margin: 0 auto;">
            <form action="product-add.php" method="POST" id="productForm">
                <!-- 1. ข้อมูลพื้นฐานสินค้า (Common Attributes from Abstract Product) -->
                <div class="card-header">
                    <h2 class="card-title">
                        <span>📦</span> 1. ข้อมูลพื้นฐานสินค้า (Base Product)
                    </h2>
                </div>

                <div class="form-row">
                    <div class="form-group" style="flex: 2;">
                        <label for="name" class="form-label">ชื่อสินค้า <span class="required">*</span></label>
                        <input type="text" id="name" name="name" class="form-control" required placeholder="เช่น OG Kush Premium Flower" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                    </div>

                    <div class="form-group" style="flex: 1;">
                        <label for="type" class="form-label">ประเภทสินค้า (Class Type) <span class="required">*</span></label>
                        <select id="type" name="type" class="form-control" required onchange="handleTypeChange()">
                            <option value="flower" <?= (($_POST['type'] ?? 'flower') === 'flower') ? 'selected' : '' ?>>🌸 ช่อดอก (CannabisFlower)</option>
                            <option value="oil" <?= (($_POST['type'] ?? '') === 'oil') ? 'selected' : '' ?>>💧 น้ำมันสกัด (CannabisOil)</option>
                            <option value="plant" <?= (($_POST['type'] ?? '') === 'plant') ? 'selected' : '' ?>>🌱 ต้นพันธุ์ (CannabisPlant)</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="base_price" class="form-label">ราคาต่อหน่วย (บาท) <span class="required">*</span></label>
                        <input type="number" step="0.01" min="0" id="base_price" name="base_price" class="form-control" required placeholder="เช่น 450.00" value="<?= htmlspecialchars($_POST['base_price'] ?? '') ?>">
                        <div class="form-hint" id="priceHint">ราคาต่อกรัม (สำหรับช่อดอก)</div>
                    </div>

                    <div class="form-group">
                        <label for="description" class="form-label">รายละเอียดสินค้า / หมายเหตุ</label>
                        <input type="text" id="description" name="description" class="form-control" placeholder="เช่น กลิ่นหอมเทอร์ปีนธรรมชาติ หรือข้อแนะนำ" value="<?= htmlspecialchars($_POST['description'] ?? '') ?>">
                    </div>
                </div>

                <!-- 2. ข้อมูลเฉพาะคลาส (แสดงทั้งหมดในหน้าเดียวตามโจทย์ พร้อม Disable ช่องที่ไม่เกี่ยวข้อง) -->
                <div class="card-header" style="margin-top: 10px;">
                    <h2 class="card-title">
                        <span>⚙️</span> 2. คุณสมบัติเฉพาะของแต่ละคลาส (Specific Attributes)
                    </h2>
                </div>

                <!-- ส่วนที่ 1: ช่อดอก (CannabisFlower) -->
                <div class="field-section" id="section-flower">
                    <div class="field-section-title">
                        <span>🌸 CannabisFlower (ช่อดอก)</span>
                        <span class="section-badge badge-flower" id="badge-flower">เปิดใช้งาน</span>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="strain_type" class="form-label">สายพันธุ์ (Strain Type) <span class="required">*</span></label>
                            <select id="strain_type" name="strain_type" class="form-control">
                                <option value="Hybrid">Hybrid (ไฮบริด สมดุล)</option>
                                <option value="Sativa">Sativa (ซาติว่า สดชื่นกระปรี้กระเปร่า)</option>
                                <option value="Indica">Indica (อินดิก้า ผ่อนคลายสบาย)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="stock_grams" class="form-label">สต็อกเริ่มต้น (กรัม - ทศนิยมได้) <span class="required">*</span></label>
                            <input type="number" step="0.01" min="0" id="stock_grams" name="stock_grams" class="form-control" placeholder="เช่น 100.50" value="<?= htmlspecialchars($_POST['stock_grams'] ?? '50.00') ?>">
                        </div>
                    </div>
                </div>

                <!-- ส่วนที่ 2: น้ำมันสกัด (CannabisOil) -->
                <div class="field-section" id="section-oil">
                    <div class="field-section-title">
                        <span>💧 CannabisOil (น้ำมันสกัด)</span>
                        <span class="section-badge badge-oil" id="badge-oil">ปิดใช้งาน</span>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="extraction_method" class="form-label">วิธีการสกัด (Extraction Method) <span class="required">*</span></label>
                            <input type="text" id="extraction_method" name="extraction_method" class="form-control" placeholder="เช่น Supercritical CO2 Extraction" value="<?= htmlspecialchars($_POST['extraction_method'] ?? 'Supercritical CO2') ?>">
                        </div>
                        <div class="form-group">
                            <label for="stock_bottles" class="form-label">สต็อกเริ่มต้น (ขวด - จำนวนเต็ม) <span class="required">*</span></label>
                            <input type="number" step="1" min="0" id="stock_bottles" name="stock_bottles" class="form-control" placeholder="เช่น 20" value="<?= htmlspecialchars($_POST['stock_bottles'] ?? '10') ?>">
                        </div>
                    </div>
                </div>

                <!-- ส่วนที่ 3: ต้นพันธุ์ (CannabisPlant) -->
                <div class="field-section" id="section-plant">
                    <div class="field-section-title">
                        <span>🌱 CannabisPlant (ต้นพันธุ์)</span>
                        <span class="section-badge badge-plant" id="badge-plant">ปิดใช้งาน</span>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="age_weeks" class="form-label">อายุต้นพันธุ์ (สัปดาห์) <span class="required">*</span></label>
                            <input type="number" step="1" min="1" id="age_weeks" name="age_weeks" class="form-control" placeholder="เช่น 4" value="<?= htmlspecialchars($_POST['age_weeks'] ?? '4') ?>">
                        </div>
                        <div class="form-group">
                            <label for="stock_pieces" class="form-label">สต็อกเริ่มต้น (ต้น - จำนวนเต็ม) <span class="required">*</span></label>
                            <input type="number" step="1" min="0" id="stock_pieces" name="stock_pieces" class="form-control" placeholder="เช่น 15" value="<?= htmlspecialchars($_POST['stock_pieces'] ?? '5') ?>">
                        </div>
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px; padding-top: 16px; border-top: 1px solid var(--border-subtle);">
                    <a href="product-list.php" class="btn btn-secondary">ยกเลิก</a>
                    <button type="submit" class="btn btn-primary btn-lg">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                        บันทึกสินค้าใหม่
                    </button>
                </div>
            </form>
        </div>
    </main>

    <script>
        function handleTypeChange() {
            const type = document.getElementById('type').value;
            const priceHint = document.getElementById('priceHint');

            // Elements for Flower
            const secFlower = document.getElementById('section-flower');
            const badgeFlower = document.getElementById('badge-flower');
            const strainType = document.getElementById('strain_type');
            const stockGrams = document.getElementById('stock_grams');

            // Elements for Oil
            const secOil = document.getElementById('section-oil');
            const badgeOil = document.getElementById('badge-oil');
            const extractMethod = document.getElementById('extraction_method');
            const stockBottles = document.getElementById('stock_bottles');

            // Elements for Plant
            const secPlant = document.getElementById('section-plant');
            const badgePlant = document.getElementById('badge-plant');
            const ageWeeks = document.getElementById('age_weeks');
            const stockPieces = document.getElementById('stock_pieces');

            // 1. Reset all to disabled
            [strainType, stockGrams, extractMethod, stockBottles, ageWeeks, stockPieces].forEach(el => {
                el.disabled = true;
            });
            [secFlower, secOil, secPlant].forEach(sec => sec.classList.add('disabled-section'));
            badgeFlower.innerText = 'ปิดใช้งาน (Disabled)';
            badgeOil.innerText = 'ปิดใช้งาน (Disabled)';
            badgePlant.innerText = 'ปิดใช้งาน (Disabled)';

            // 2. Enable specific section based on selected type
            if (type === 'flower') {
                secFlower.classList.remove('disabled-section');
                strainType.disabled = false;
                stockGrams.disabled = false;
                badgeFlower.innerText = '✓ กำลังใช้งาน (Active)';
                priceHint.innerText = 'ราคาต่อ 1 กรัม (THB/gram)';
            } else if (type === 'oil') {
                secOil.classList.remove('disabled-section');
                extractMethod.disabled = false;
                stockBottles.disabled = false;
                badgeOil.innerText = '✓ กำลังใช้งาน (Active)';
                priceHint.innerText = 'ราคาต่อ 1 ขวด (THB/bottle)';
            } else if (type === 'plant') {
                secPlant.classList.remove('disabled-section');
                ageWeeks.disabled = false;
                stockPieces.disabled = false;
                badgePlant.innerText = '✓ กำลังใช้งาน (Active)';
                priceHint.innerText = 'ราคาต่อ 1 ต้น (THB/piece)';
            }
        }

        // Run once on load
        document.addEventListener('DOMContentLoaded', handleTypeChange);
    </script>
</body>
</html>
