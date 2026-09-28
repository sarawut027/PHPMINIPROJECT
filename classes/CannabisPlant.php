<?php
require_once __DIR__ . '/Product.php';

class CannabisPlant extends Product {
    private int $ageWeeks;
    private int $stockPieces;

    public function __construct(int $id, string $name, float $basePrice, int $ageWeeks, int $stockPieces) {
        parent::__construct($id, $name, $basePrice);
        $this->ageWeeks = $ageWeeks;
        $this->stockPieces = $stockPieces;
    }

    public function getAgeWeeks(): int { return $this->ageWeeks; }
    public function getStockPieces(): int { return $this->stockPieces; }

    public function checkStock(float $quantity): bool {
        return $this->stockPieces >= (int)$quantity;
    }

    public function reduceStock(float $quantity): bool {
        $qty = (int)$quantity;
        if ($this->checkStock($qty)) {
            $this->stockPieces -= $qty;
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("UPDATE products SET stock_pieces = :stock WHERE id = :id");
            return $stmt->execute([':stock' => $this->stockPieces, ':id' => $this->id]);
        }
        return false;
    }

    public function getStockDisplay(): string {
        return number_format($this->stockPieces) . ' ต้น';
    }

    public function getStockUnit(): string {
        return 'ต้น';
    }

    public function getStockValue(): float {
        return (float)$this->stockPieces;
    }

    public function getType(): string {
        return 'plant';
    }
}
