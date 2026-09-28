<?php
require_once __DIR__ . '/../config/Database.php';

abstract class Product {
    protected int $id;
    protected string $name;
    protected float $basePrice;

    public function __construct(int $id, string $name, float $basePrice) {
        $this->id = $id;
        $this->name = $name;
        $this->basePrice = $basePrice;
    }

    public function getId(): int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getBasePrice(): float { return $this->basePrice; }

    abstract public function checkStock(float $quantity): bool;
    abstract public function reduceStock(float $quantity): bool;
    abstract public function getStockDisplay(): string;
    abstract public function getStockUnit(): string;
    abstract public function getStockValue(): float;
    abstract public function getType(): string;
}
