<?php
/**
 * Database Connection (PDO Singleton)
 * จัดการการเชื่อมต่อฐานข้อมูลรูปแบบ Singleton Pattern
 */
class Database {
    private static ?Database $instance = null;
    private ?PDO $conn = null;

    private string $host = 'localhost';
    private string $db_name = 'dispensary_db';
    private string $username = 'root';
    private string $password = '';

    private function __construct() {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            // หากยังไม่ได้สร้าง database ให้ลองเชื่อมต่อแบบไม่ระบุ db เพื่อให้ระบบแจ้งเตือนชัดเจน
            die("Database Connection Error: " . $e->getMessage() . "<br><small>กรุณาตรวจสอบว่าเปิด XAMPP MySQL และนำเข้าไฟล์ database.sql แล้ว</small>");
        }
    }

    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection(): PDO {
        return $this->conn;
    }

    // ป้องกันการ clone และ unserialize สำหรับ Singleton
    private function __clone() {}
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}
