<?php
// save_report.php
include 'db_connect.php'; // เรียกใช้การเชื่อมต่อฐานข้อมูล

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // รับค่าและป้องกัน SQL Injection
    $name = $conn->real_escape_string($_POST['reporter_name']);
    $detail = $conn->real_escape_string($_POST['issue_detail']);

    // ตรวจสอบว่าข้อมูลไม่ว่างเปล่า
    if (!empty($name) && !empty($detail)) {
        // คำสั่ง SQL สำหรับเพิ่มข้อมูลลงตาราง
        $sql = "INSERT INTO contact_reports (reporter_name, issue_detail) VALUES ('$name', '$detail')";

        if ($conn->query($sql) === TRUE) {
            // บันทึกสำเร็จ ส่งกลับไปหน้า contact.php พร้อมข้อความสำเร็จ
            $msg = "ส่งข้อมูลแจ้งปัญหาเรียบร้อยแล้ว ขอบคุณสำหรับข้อเสนอแนะค่ะ";
            header("Location: contact.php?msg=" . urlencode($msg));
            exit();
        } else {
            // บันทึกไม่สำเร็จ ส่งกลับไปพร้อมข้อความ Error
            $err = "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $conn->error;
            header("Location: contact.php?err=" . urlencode($err));
            exit();
        }
    } else {
        $err = "กรุณากรอกข้อมูลให้ครบถ้วน";
        header("Location: contact.php?err=" . urlencode($err));
        exit();
    }
} else {
    // หากเข้าหน้านี้โดยไม่ได้ส่ง POST มา ให้เด้งกลับไปหน้าติดต่อ
    header("Location: contact.php");
    exit();
}

$conn->close();
?>