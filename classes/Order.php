<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/Product.php';
require_once __DIR__ . '/CustomerGuard.php';
require_once __DIR__ . '/PricingStrategy.php';

class Order {
    private ?int $orderId = null;
    private string $orderCode;
    private array $items = []; // [['product' => Product, 'quantity' => float, 'calc' => array]]
    private float $totalAmount = 0.0;
    private float $discountAmount = 0.0;
    private float $netAmount = 0.0;

    public function __construct() {
        $this->orderCode = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
    }

    public function addItem(Product $product, float $quantity, ?PricingStrategy $strategy = null): bool {
        if (!$product->checkStock($quantity)) {
            return false;
        }

        $strategy = $strategy ?? new BulkPricing();
        $calc = $strategy->calculatePrice($quantity, $product->getBasePrice());

        $this->items[] = [
            'product' => $product,
            'quantity' => $quantity,
            'calc' => $calc
        ];

        $this->totalAmount += $calc['subtotal'];
        $this->discountAmount += $calc['discount'];
        $this->netAmount += $calc['final_price'];

        return true;
    }

    public function checkout(string $customerBirthdate, float $cashReceived, int $userId): array {
        // 1. ตรวจสอบอายุลูกค้า (>= 20 ปี)
        if (!CustomerGuard::verifyAge($customerBirthdate)) {
            return [
                'success' => false,
                'message' => 'ไม่อนุญาตให้จำหน่าย: ลูกค้าอายุต่ำกว่า 20 ปีบริบูรณ์ตามที่กฎหมายกำหนด'
            ];
        }

        if (empty($this->items)) {
            return [
                'success' => false,
                'message' => 'ไม่มีสินค้าในรายการคำสั่งซื้อ'
            ];
        }

        if ($cashReceived < $this->netAmount) {
            return [
                'success' => false,
                'message' => 'จำนวนเงินที่รับมาไม่เพียงพอต่อยอดสุทธิ'
            ];
        }

        $db = Database::getInstance()->getConnection();

        try {
            $db->beginTransaction();

            // 2. เช็คสต็อกและตัดสต็อกสินค้าทุกรายการ
            foreach ($this->items as $item) {
                /** @var Product $p */
                $p = $item['product'];
                $qty = $item['quantity'];

                if (!$p->reduceStock($qty)) {
                    $db->rollBack();
                    return [
                        'success' => false,
                        'message' => "สต็อกสินค้า '{$p->getName()}' ไม่เพียงพอ"
                    ];
                }
            }

            // 3. บันทึก Order ลงใน Database
            $changeReturned = $cashReceived - $this->netAmount;
            $customerAge = CustomerGuard::calculateAge($customerBirthdate);

            $stmt = $db->prepare("INSERT INTO orders 
                (order_code, user_id, customer_birthdate, customer_age, total_amount, discount_amount, net_amount, cash_received, change_returned)
                VALUES (:order_code, :user_id, :birthdate, :age, :total, :discount, :net, :cash, :change)");
            
            $stmt->execute([
                ':order_code' => $this->orderCode,
                ':user_id' => $userId,
                ':birthdate' => $customerBirthdate,
                ':age' => $customerAge,
                ':total' => $this->totalAmount,
                ':discount' => $this->discountAmount,
                ':net' => $this->netAmount,
                ':cash' => $cashReceived,
                ':change' => $changeReturned
            ]);

            $this->orderId = (int)$db->lastInsertId();

            // 4. บันทึก Order Items
            $itemStmt = $db->prepare("INSERT INTO order_items 
                (order_id, product_id, product_name, product_type, quantity, unit_label, unit_price, subtotal, discount, final_price)
                VALUES (:order_id, :product_id, :product_name, :product_type, :quantity, :unit_label, :unit_price, :subtotal, :discount, :final_price)");

            foreach ($this->items as $item) {
                /** @var Product $p */
                $p = $item['product'];
                $itemStmt->execute([
                    ':order_id' => $this->orderId,
                    ':product_id' => $p->getId(),
                    ':product_name' => $p->getName(),
                    ':product_type' => $p->getType(),
                    ':quantity' => $item['quantity'],
                    ':unit_label' => $p->getStockUnit(),
                    ':unit_price' => $p->getBasePrice(),
                    ':subtotal' => $item['calc']['subtotal'],
                    ':discount' => $item['calc']['discount'],
                    ':final_price' => $item['calc']['final_price']
                ]);
            }

            $db->commit();

            return [
                'success' => true,
                'order_id' => $this->orderId,
                'order_code' => $this->orderCode,
                'net_amount' => $this->netAmount,
                'change_returned' => $changeReturned
            ];

        } catch (Exception $e) {
            $db->rollBack();
            return [
                'success' => false,
                'message' => 'เกิดข้อผิดพลาดในการบันทึกคำสั่งซื้อ: ' . $e->getMessage()
            ];
        }
    }

    public function getItems(): array { return $this->items; }
    public function getTotalAmount(): float { return $this->totalAmount; }
    public function getDiscountAmount(): float { return $this->discountAmount; }
    public function getNetAmount(): float { return $this->netAmount; }
    public function getOrderId(): ?int { return $this->orderId; }
    public function getOrderCode(): string { return $this->orderCode; }
}
