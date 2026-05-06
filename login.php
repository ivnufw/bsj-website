<?php
session_start();
$error = '';

// ตรวจสอบข้อมูลผู้ใช้ (Hardcoded)
$valid_username = 'admin';
$valid_password = '12345'; // รหัสผ่านสำหรับผู้ดูแลระบบ

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    if ($username === $valid_username && $password === $valid_password) {
        $_SESSION['loggedin'] = true;
        $_SESSION['username'] = $username;
        // ล็อคอินสำเร็จ ส่งกลับไปหน้าหลัก
        header("Location: index.php"); 
        exit;
    } else {
        $error = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - ผู้ดูแล</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { font-family: 'Kanit', sans-serif; background-color: #fce4ec; display: flex; align-items: center; justify-content: center; height: 100vh; }
        .login-container { max-width: 400px; padding: 30px; background: white; border-radius: 10px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); }
        .btn-pink { background-color: #FF69B4; border-color: #FF69B4; color: white; }
        .btn-pink:hover { background-color: #E0509C; border-color: #E0509C; }
    </style>
</head>
<body>
    <div class="login-container">
        <h2 class="text-center mb-4"><i class="fas fa-lock me-2"></i> ผู้ดูแลเว็บไซต์</h2>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="mb-3">
                <label for="username" class="form-label">ชื่อผู้ใช้</label>
                <input type="text" class="form-control" id="username" name="username" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">รหัสผ่าน</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <div class="d-grid">
                <button type="submit" class="btn btn-pink">เข้าสู่ระบบ</button>
            </div>
        </form>
        <p class="text-center mt-3"><a href="index.php" class="text-muted small">กลับหน้าหลัก</a></p>
    </div>
</body>
</html>