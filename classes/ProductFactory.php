<?php
require_once __DIR__ . '/Product.php';
require_once __DIR__ . '/CannabisFlower.php';
require_once __DIR__ . '/CannabisOil.php';
require_once __DIR__ . '/CannabisPlant.php';

class ProductFactory {
    public static function createFromRow(array $row): ?Product {
        switch ($row['type']) {
            case 'flower':
                return new CannabisFlower(
                    (int)$row['id'],
                    $row['name'],
                    (float)$row['base_price'],
                    $row['strain_type'] ?? 'Hybrid',
                    (float)($row['stock_grams'] ?? 0.0)
                );
            case 'oil':
                return new CannabisOil(
                    (int)$row['id'],
                    $row['name'],
                    (float)$row['base_price'],
                    $row['extraction_method'] ?? 'Standard',
                    (int)($row['stock_bottles'] ?? 0)
                );
            case 'plant':
                return new CannabisPlant(
                    (int)$row['id'],
                    $row['name'],
                    (float)$row['base_price'],
                    (int)($row['age_weeks'] ?? 0),
                    (int)($row['stock_pieces'] ?? 0)
                );
            default:
                return null;
        }
    }
}
