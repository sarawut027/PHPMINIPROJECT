<?php
require_once __DIR__ . '/auth.php';
$currentUser = getCurrentUser();
$currentScript = basename($_SERVER['PHP_SELF']);

// ตรวจสอบระดับโฟลเดอร์สำหรับทำ root path
$isInAdmin = strpos($_SERVER['PHP_SELF'], '/admin/') !== false;
$rootPath = $isInAdmin ? '../' : './';
?>
<header class="top-navbar no-print">
    <div class="nav-inner">
        <a href="<?= $rootPath ?>admin/product-list.php" class="nav-brand">
            <div class="nav-brand-icon">🌿</div>
            <span>BOTANICAL POS</span>
        </a>

        <ul class="nav-links">
            <li class="nav-item <?= ($currentScript === 'product-list.php') ? 'active' : '' ?>">
                <a href="<?= $rootPath ?>admin/product-list.php">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                    รายการสินค้า
                </a>
            </li>
            <li class="nav-item <?= ($currentScript === 'product-add.php') ? 'active' : '' ?>">
                <a href="<?= $rootPath ?>admin/product-add.php">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                    เพิ่มสินค้าใหม่
                </a>
            </li>
            <li class="nav-item <?= ($currentScript === 'pos.php') ? 'active' : '' ?>">
                <a href="<?= $rootPath ?>pos.php">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                    หน้าร้าน POS
                </a>
            </li>
        </ul>

        <div class="nav-user-actions">
            <?php if ($currentUser): ?>
                <div class="user-badge">
                    <span>👤 <?= htmlspecialchars($currentUser['full_name']) ?></span>
                    <span class="role-tag <?= htmlspecialchars($currentUser['role']) ?>">
                        <?= strtoupper(htmlspecialchars($currentUser['role'])) ?>
                    </span>
                </div>
                <a href="<?= $rootPath ?>logout.php" class="btn btn-secondary btn-sm" title="ออกจากระบบ">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    ออก
                </a>
            <?php else: ?>
                <a href="<?= $rootPath ?>login.php" class="btn btn-primary btn-sm">เข้าสู่ระบบ</a>
            <?php endif; ?>
        </div>
    </div>
</header>
