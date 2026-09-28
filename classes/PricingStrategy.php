<?php
/**
 * PricingStrategy Interface & BulkPricing Implementation
 * จัดการคำนวณราคาสินค้าตามกลยุทธ์ เช่น ราคาส่ง/โปรโมชั่นตามน้ำหนักหรือจำนวน
 */
interface PricingStrategy {
    /**
     * คำนวณราคาสุทธิและส่วนลด
     * @return array ['subtotal' => float, 'discount' => float, 'final_price' => float, 'discount_desc' => string]
     */
    public function calculatePrice(float $quantity, float $basePrice): array;
}

class StandardPricing implements PricingStrategy {
    public function calculatePrice(float $quantity, float $basePrice): array {
        $subtotal = $quantity * $basePrice;
        return [
            'subtotal' => $subtotal,
            'discount' => 0.0,
            'final_price' => $subtotal,
            'discount_desc' => 'ราคาปกติ'
        ];
    }
}

class BulkPricing implements PricingStrategy {
    public function calculatePrice(float $quantity, float $basePrice): array {
        $subtotal = $quantity * $basePrice;
        $discountRate = 0.0;
        $desc = 'ราคาปกติ';

        // ส่วนลดขั้นบันไดตามน้ำหนัก/ปริมาณ
        if ($quantity >= 28.0) {
            $discountRate = 0.20; // ลด 20% เมื่อซื้อ 1 ออนซ์ (28g) ขึ้นไป
            $desc = 'ส่วนลดซื้อส่ง 1 ออนซ์ขึ้นไป (-20%)';
        } elseif ($quantity >= 10.0) {
            $discountRate = 0.15; // ลด 15% เมื่อซื้อ 10g/ชิ้น ขึ้นไป
            $desc = 'ส่วนลดแพ็คเกจ 10 กรัม/ชิ้น (-15%)';
        } elseif ($quantity >= 5.0) {
            $discountRate = 0.10; // ลด 10% เมื่อซื้อ 5g/ชิ้น ขึ้นไป
            $desc = 'ส่วนลดปริมาณ 5 กรัม/ชิ้น (-10%)';
        } elseif ($quantity >= 3.5) {
            $discountRate = 0.05; // ลด 5% เมื่อซื้อกระปุกมาตรฐาน 3.5g ขึ้นไป
            $desc = 'ส่วนลดพิเศษขนาด 3.5 กรัม (-5%)';
        }

        $discount = $subtotal * $discountRate;
        $finalPrice = $subtotal - $discount;

        return [
            'subtotal' => round($subtotal, 2),
            'discount' => round($discount, 2),
            'final_price' => round($finalPrice, 2),
            'discount_desc' => $desc
        ];
    }
}
