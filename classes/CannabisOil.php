<?php
require_once __DIR__ . '/Product.php';

class CannabisOil extends Product {
    private string $extractionMethod;
    private int $stockBottles;

    public function __construct(int $id, string $name, float $basePrice, string $extractionMethod, int $stockBottles) {
        parent::__construct($id, $name, $basePrice);
        $this->extractionMethod = $extractionMethod;
        $this->stockBottles = $stockBottles;
    }

    public function getExtractionMethod(): string { return $this->extractionMethod; }
    public function getStockBottles(): int { return $this->stockBottles; }

    public function checkStock(float $quantity): bool {
        return $this->stockBottles >= (int)$quantity;
    }

    public function reduceStock(float $quantity): bool {
        $qty = (int)$quantity;
        if ($this->checkStock($qty)) {
            $this->stockBottles -= $qty;
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("UPDATE products SET stock_bottles = :stock WHERE id = :id");
            return $stmt->execute([':stock' => $this->stockBottles, ':id' => $this->id]);
        }
        return false;
    }

    public function getStockDisplay(): string {
        return number_format($this->stockBottles) . ' ขวด';
    }

    public function getStockUnit(): string {
        return 'ขวด';
    }

    public function getStockValue(): float {
        return (float)$this->stockBottles;
    }

    public function getType(): string {
        return 'oil';
    }
}
