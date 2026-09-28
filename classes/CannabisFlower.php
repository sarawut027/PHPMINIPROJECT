<?php
require_once __DIR__ . '/Product.php';

class CannabisFlower extends Product {
    private string $strainType;
    private float $stockGrams;

    public function __construct(int $id, string $name, float $basePrice, string $strainType, float $stockGrams) {
        parent::__construct($id, $name, $basePrice);
        $this->strainType = $strainType;
        $this->stockGrams = $stockGrams;
    }

    public function getStrainType(): string { return $this->strainType; }
    public function getStockGrams(): float { return $this->stockGrams; }

    public function checkStock(float $quantity): bool {
        return $this->stockGrams >= $quantity;
    }

    public function reduceStock(float $quantity): bool {
        if ($this->checkStock($quantity)) {
            $this->stockGrams -= $quantity;
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("UPDATE products SET stock_grams = :stock WHERE id = :id");
            return $stmt->execute([':stock' => $this->stockGrams, ':id' => $this->id]);
        }
        return false;
    }

    public function getStockDisplay(): string {
        return number_format($this->stockGrams, 2) . ' กรัม';
    }

    public function getStockUnit(): string {
        return 'กรัม';
    }

    public function getStockValue(): float {
        return $this->stockGrams;
    }

    public function getType(): string {
        return 'flower';
    }
}
