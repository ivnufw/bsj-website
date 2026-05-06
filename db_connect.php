<?php
// ข้อมูลการเชื่อมต่อ
$servername = "sql202.byethost17.com";
$username = "b17_40341827"; 
$password = "Oam280546"; // รหัสผ่านตามที่คุณระบุ
$dbname = "b17_40341827_bsj"; // ชื่อฐานข้อมูลที่ถูกต้อง

// สร้างการเชื่อมต่อ
$conn = new mysqli($servername, $username, $password, $dbname);

// ตรวจสอบการเชื่อมต่อ
if ($conn->connect_error) {
    // แสดงข้อความผิดพลาดหากเชื่อมต่อไม่ได้
    die("Connection failed: " . $conn->connect_error);
}

// กำหนด Charset ให้รองรับภาษาไทย
$conn->set_charset("utf8mb4"); 
?>