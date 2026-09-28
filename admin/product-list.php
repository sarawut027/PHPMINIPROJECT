<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin('../login.php');

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../classes/ProductFactory.php';

$db = Database::getInstance()->getConnection();

// จัดการการลบสินค้า
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $deleteId = (int)$_GET['id'];
    try {
        $stmt = $db->prepare("DELETE FROM products WHERE id = :id");
        $stmt->execute([':id' => $deleteId]);
        $_SESSION['flash_success'] = "ลบสินค้ารหัส #{$deleteId} เรียบร้อยแล้ว";
    } catch (Exception $e) {
        $_SESSION['flash_error'] = "ไม่สามารถลบสินค้าได้: " . $e->getMessage();
    }
    header("Location: product-list.php");
    exit();
}

// ข้อความแจ้งเตือน Flash
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// ดึงข้อมูลสินค้าทั้งหมด
$filterType = $_GET['type'] ?? 'all';
$searchQuery = trim($_GET['search'] ?? '');

$sql = "SELECT * FROM products WHERE 1=1";
$params = [];

if ($filterType !== 'all' && in_array($filterType, ['flower', 'oil', 'plant'])) {
    $sql .= " AND type = :type";
    $params[':type'] = $filterType;
}

if (!empty($searchQuery)) {
    $sql .= " AND (name LIKE :search OR strain_type LIKE :search OR extraction_method LIKE :search)";
    $params[':search'] = "%{$searchQuery}%";
}

$sql .= " ORDER BY id DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$rawProducts = $stmt->fetchAll();

// คำนวณสถิติภาพรวม
$statsStmt = $db->query("SELECT 
    COUNT(*) as total_count,
    SUM(CASE WHEN type = 'flower' THEN 1 ELSE 0 END) as flower_count,
    SUM(CASE WHEN type = 'oil' THEN 1 ELSE 0 END) as oil_count,
    SUM(CASE WHEN type = 'plant' THEN 1 ELSE 0 END) as plant_count,
    SUM(CASE WHEN (type = 'flower' AND stock_grams < 10) OR (type = 'oil' AND stock_bottles < 5) OR (type = 'plant' AND stock_pieces < 5) THEN 1 ELSE 0 END) as low_stock_count
    FROM products");
$stats = $statsStmt->fetch();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>คลังสินค้าและสต็อก - ระบบจัดการ Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <main class="app-container">
        <div class="page-header">
            <div>
                <h1 class="page-title">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                    รายการสินค้าและสต็อกคงคลัง
                </h1>
                <p class="page-subtitle">จัดการแคตตาล็อกสินค้า อัปเดตสต็อก และตรวจสอบสถานะสินค้าแยกตามคลาส</p>
            </div>
            <a href="product-add.php" class="btn btn-primary">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                เพิ่มสินค้าใหม่
            </a>
        </div>

        <?php if ($flashSuccess): ?>
            <div class="alert alert-success">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                <span><?= htmlspecialchars($flashSuccess) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($flashError): ?>
            <div class="alert alert-danger">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <span><?= htmlspecialchars($flashError) ?></span>
            </div>
        <?php endif; ?>

        <!-- สรุปตัวเลขสต็อก (Summary Stats) -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📦</div>
                <div class="stat-info">
                    <div class="stat-value"><?= number_format($stats['total_count'] ?? 0) ?></div>
                    <div class="stat-label">รายการสินค้าทั้งหมด</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">🌸</div>
                <div class="stat-info">
                    <div class="stat-value"><?= number_format($stats['flower_count'] ?? 0) ?></div>
                    <div class="stat-label">ช่อดอก (Flower)</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">💧</div>
                <div class="stat-info">
                    <div class="stat-value"><?= number_format($stats['oil_count'] ?? 0) ?></div>
                    <div class="stat-label">น้ำมันสกัด (Oil)</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">🌱</div>
                <div class="stat-info">
                    <div class="stat-value"><?= number_format($stats['plant_count'] ?? 0) ?></div>
                    <div class="stat-label">ต้นพันธุ์ (Plant)</div>
                </div>
            </div>

            <div class="stat-card <?= (($stats['low_stock_count'] ?? 0) > 0) ? 'warning' : '' ?>">
                <div class="stat-icon">⚠️</div>
                <div class="stat-info">
                    <div class="stat-value"><?= number_format($stats['low_stock_count'] ?? 0) ?></div>
                    <div class="stat-label">สินค้าใกล้หมด (Low Stock)</div>
                </div>
            </div>
        </div>

        <!-- กล่องค้นหาและตัวกรองประเภท (Filter & Search) -->
        <div class="card" style="margin-bottom: 24px; padding: 16px 20px;">
            <form action="product-list.php" method="GET" class="filter-bar" style="margin-bottom: 0;">
                <div class="search-box">
                    <svg class="search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" name="search" class="form-control" placeholder="ค้นหาชื่อสินค้า, สายพันธุ์ หรือวิธีการสกัด..." value="<?= htmlspecialchars($searchQuery) ?>">
                </div>

                <div class="filter-pills">
                    <a href="product-list.php?type=all<?= !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : '' ?>" class="filter-pill <?= ($filterType === 'all') ? 'active' : '' ?>">
                        ทั้งหมด (All)
                    </a>
                    <a href="product-list.php?type=flower<?= !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : '' ?>" class="filter-pill <?= ($filterType === 'flower') ? 'active' : '' ?>">
                        🌸 ช่อดอก
                    </a>
                    <a href="product-list.php?type=oil<?= !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : '' ?>" class="filter-pill <?= ($filterType === 'oil') ? 'active' : '' ?>">
                        💧 น้ำมันสกัด
                    </a>
                    <a href="product-list.php?type=plant<?= !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : '' ?>" class="filter-pill <?= ($filterType === 'plant') ? 'active' : '' ?>">
                        🌱 ต้นพันธุ์
                    </a>
                </div>

                <?php if (!empty($searchQuery) || $filterType !== 'all'): ?>
                    <a href="product-list.php" class="btn btn-secondary btn-sm" title="ล้างตัวกรอง">ล้างตัวกรอง</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- ตารางแสดงรายการสินค้าตาม OOP Class Pattern -->
        <div class="card" style="padding: 0; overflow: hidden;">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 70px;">รหัส</th>
                            <th>ชื่อสินค้า (Product Name)</th>
                            <th>ประเภทคลาส (Class)</th>
                            <th>คุณลักษณะเฉพาะ (Attributes)</th>
                            <th>ราคาต่อหน่วย</th>
                            <th>สต็อกคงเหลือ</th>
                            <th>สถานะสต็อก</th>
                            <th style="text-align: right; width: 100px;">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rawProducts)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 48px 20px; color: var(--text-muted);">
                                    <div style="font-size: 2.2rem; margin-bottom: 8px;">🍃</div>
                                    <div style="font-size: 1.05rem; font-weight: 500;">ไม่พบรายการสินค้าตามเงื่อนไขที่เลือก</div>
                                    <p style="font-size: 0.88rem; margin-top: 4px;">ลองเปลี่ยนคำค้นหาหรือเพิ่มสินค้าใหม่เข้าสู่ระบบ</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($rawProducts as $row): 
                                // แปลง Database Row เป็น Object Instance ตาม Class Hierarchy (Polymorphism)
                                $product = ProductFactory::createFromRow($row);
                                if (!$product) continue;

                                $stockVal = $product->getStockValue();
                                $type = $product->getType();

                                // กำหนดสถานะและสีเตือนสต็อก
                                $isLowStock = false;
                                $isEmptyStock = ($stockVal <= 0);
                                if ($type === 'flower' && $stockVal < 10) $isLowStock = true;
                                if ($type === 'oil' && $stockVal < 5) $isLowStock = true;
                                if ($type === 'plant' && $stockVal < 5) $isLowStock = true;
                            ?>
                                <tr>
                                    <td style="font-weight: 600; color: var(--text-muted);">
                                        #<?= $product->getId() ?>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600; color: var(--color-primary-dark); font-size: 0.96rem;">
                                            <?= htmlspecialchars($product->getName()) ?>
                                        </div>
                                        <?php if (!empty($row['description'])): ?>
                                            <div style="font-size: 0.8rem; color: var(--text-muted);">
                                                <?= htmlspecialchars($row['description']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($product instanceof CannabisFlower): ?>
                                            <span class="badge badge-flower">🌸 CannabisFlower</span>
                                        <?php elseif ($product instanceof CannabisOil): ?>
                                            <span class="badge badge-oil">💧 CannabisOil</span>
                                        <?php elseif ($product instanceof CannabisPlant): ?>
                                            <span class="badge badge-plant">🌱 CannabisPlant</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($product instanceof CannabisFlower): ?>
                                            <span style="font-size: 0.88rem;">
                                                สายพันธุ์: <strong><?= htmlspecialchars($product->getStrainType()) ?></strong>
                                            </span>
                                        <?php elseif ($product instanceof CannabisOil): ?>
                                            <span style="font-size: 0.88rem;">
                                                สกัดด้วย: <strong><?= htmlspecialchars($product->getExtractionMethod()) ?></strong>
                                            </span>
                                        <?php elseif ($product instanceof CannabisPlant): ?>
                                            <span style="font-size: 0.88rem;">
                                                อายุ: <strong><?= $product->getAgeWeeks() ?> สัปดาห์</strong>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-weight: 600; color: var(--text-primary);">
                                        ฿<?= number_format($product->getBasePrice(), 2) ?>
                                        <span style="font-size: 0.78rem; font-weight: 400; color: var(--text-muted);">/<?= $product->getStockUnit() ?></span>
                                    </td>
                                    <td style="font-weight: 700; font-size: 0.96rem;">
                                        <?= $product->getStockDisplay() ?>
                                    </td>
                                    <td>
                                        <?php if ($isEmptyStock): ?>
                                            <span class="badge badge-stock-empty">❌ สินค้าหมด</span>
                                        <?php elseif ($isLowStock): ?>
                                            <span class="badge badge-stock-low">⚠️ ใกล้หมด</span>
                                        <?php else: ?>
                                            <span class="badge badge-stock-normal">✓ สต็อกปกติ</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: right;">
                                        <a href="product-list.php?action=delete&id=<?= $product->getId() ?>" 
                                           class="btn btn-danger btn-sm" 
                                           onclick="return confirm('ยืนยันที่จะลบสินค้า \'<?= addslashes(htmlspecialchars($product->getName())) ?>\' หรือไม่?')"
                                           title="ลบสินค้า">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                            ลบ
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>
