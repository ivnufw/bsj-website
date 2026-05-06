<?php
session_start();
// เช็คสิทธิ์แอดมิน (อิงจากระบบ Login เดิม)
$is_admin = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;

include 'db_connect.php'; // เชื่อมต่อฐานข้อมูล

$school_name_th = "โรงเรียนบ้านสวน (จั่นอนุสรณ์)";

// ---------------------------------------------------------
// ระบบจัดการสำหรับ Admin (อนุมัติ / ลบ)
// ---------------------------------------------------------
if ($is_admin && isset($_GET['action']) && isset($_GET['id'])) {
    $action_id = (int)$_GET['id'];
    if ($_GET['action'] == 'approve') {
        $sql_update = "UPDATE contact_reports SET is_approved = 1 WHERE id = $action_id";
        if ($conn->query($sql_update)) {
            $message = "อนุมัติข้อความเรียบร้อยแล้ว ข้อความจะแสดงผลหน้าเว็บ";
        } else {
            $error = "เกิดข้อผิดพลาดในการอนุมัติ: " . $conn->error;
        }
    } elseif ($_GET['action'] == 'delete') {
        $sql_delete = "DELETE FROM contact_reports WHERE id = $action_id";
        if ($conn->query($sql_delete)) {
            $message = "ลบข้อความเรียบร้อยแล้ว";
        } else {
            $error = "เกิดข้อผิดพลาดในการลบ: " . $conn->error;
        }
    }
    // Redirect ป้องกันการ Refresh แล้ว Action ซ้ำ
    header("Location: contact.php" . (isset($message) ? "?msg=".urlencode($message) : "?err=".urlencode($error)));
    exit();
}

// รับข้อความแจ้งเตือนจากการทำงานอื่นๆ
if (!isset($message)) $message = isset($_GET['msg']) ? urldecode($_GET['msg']) : '';
if (!isset($error)) $error = isset($_GET['err']) ? urldecode($_GET['err']) : '';

// ---------------------------------------------------------
// ดึงข้อมูลข้อเสนอแนะ (Admin เห็นทั้งหมด / ผู้ใช้เห็นแค่อนุมัติแล้ว)
// ---------------------------------------------------------
$reports = [];
if ($is_admin) {
    // Admin: เห็นทุกข้อความ เรียงจากใหม่ไปเก่า
    $sql_reports = "SELECT * FROM contact_reports ORDER BY created_at DESC";
} else {
    // Public: เห็นเฉพาะที่ is_approved = 1
    $sql_reports = "SELECT * FROM contact_reports WHERE is_approved = 1 ORDER BY created_at DESC";
}
// เพิ่มการตรวจสอบว่า $conn มีอยู่จริงเพื่อป้องกัน Error ตอนยังไม่ต่อ Database
if (isset($conn)) {
    $result_reports = $conn->query($sql_reports);
    if ($result_reports && $result_reports->num_rows > 0) {
        while($row = $result_reports->fetch_assoc()) {
            $reports[] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ติดต่อเรา | <?php echo $school_name_th; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;600&display=swap">
    <style>
        body { font-family: 'Kanit', sans-serif; background-color: #fdf2f8; }
        :root { --pink-bsj: #FF69B4; --dark-bsj: #2c333a; }
        
        /* ----- Navbar Custom Styles ----- */
        .custom-navbar {
            background-color: var(--dark-bsj);
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
        }
        .custom-navbar .navbar-brand {
            color: white;
            font-weight: 600;
        }
        .custom-navbar .nav-link {
            color: rgba(255,255,255,0.8);
            font-weight: 400;
            transition: color 0.3s ease;
        }
        .custom-navbar .nav-link:hover, .custom-navbar .nav-link.active {
            color: var(--pink-bsj);
        }
        .custom-navbar .navbar-toggler {
            border-color: rgba(255,255,255,0.3);
        }
        .custom-navbar .navbar-toggler-icon {
            background-image: url("data:image/svg+xml;charset=utf8,%3Csvg viewBox='0 0 30 30' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath stroke='rgba%28255, 255, 255, 0.8%29' stroke-width='2' stroke-linecap='round' stroke-miterlimit='10' d='M4 7h22M4 15h22M4 23h22'/%3E%3C/svg%3E");
        }

        .contact-header { 
            background-color: var(--dark-bsj); color: white; padding: 60px 0; border-bottom: 5px solid var(--pink-bsj);
        }
        .contact-card { 
            border-radius: 15px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.05); background: white;
        }
        .icon-box { 
            width: 50px; height: 50px; background: var(--pink-bsj); color: white; 
            border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; 
        }
        .map-frame { 
            border-radius: 15px; overflow: hidden; border: 4px solid white; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.1); height: 100%; min-height: 400px;
        }
        .btn-facebook { background-color: #1877F2; color: white; border: none; }
        .btn-facebook:hover { background-color: #145dbf; color: white; }
        .text-pink { color: var(--pink-bsj) !important; }

        /* กล่องข้อความแชท */
        .report-panel {
            background: #fff; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            display: flex; flex-direction: column; height: 100%; max-height: 700px;
            border: 2px solid <?php echo $is_admin ? '#dc3545' : '#FF69B4'; ?>;
        }
        .report-header {
            background-color: <?php echo $is_admin ? '#dc3545' : 'var(--pink-bsj)'; ?>;
            color: white; padding: 15px; border-radius: 12px 12px 0 0; font-weight: 600; text-align: center;
        }
        .report-body {
            flex-grow: 1; overflow-y: auto; background-color: #f0f2f5; padding: 20px; border-radius: 0 0 15px 15px;
        }
        .chat-message { margin-bottom: 20px; }
        .chat-bubble {
            background-color: #ffffff; padding: 12px 16px; border-radius: 0 15px 15px 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05); display: inline-block; width: 100%; position: relative;
            border-left: 4px solid #ddd;
        }
        .chat-bubble.approved { border-left-color: #28a745; }
        .chat-bubble.pending { border-left-color: #ffc107; } 
        
        .chat-bubble::before {
            content: ""; position: absolute; top: 0; left: -10px; border-width: 0 10px 10px 0;
            border-style: solid; border-color: transparent #ffffff transparent transparent;
        }
        .chat-sender { font-weight: 600; color: var(--pink-bsj); font-size: 1rem; border-bottom: 1px dashed #eee; padding-bottom: 5px;}
        .chat-time { font-size: 0.8rem; color: #888; text-align: right; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg custom-navbar sticky-top">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse justify-content-end" id="mainNavbar">
            <ul class="navbar-nav align-items-center">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">หน้าหลัก</a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link active" aria-current="page" href="contact.php">ติดต่อเรา</a>
                </li>
                
                <?php if ($is_admin): ?>
                <li class="nav-item ms-lg-3 mt-3 mt-lg-0">
                    <span class="badge bg-danger px-3 py-2"><i class="fas fa-user-shield me-1"></i> Admin</span>
                </li>
                <li class="nav-item ms-lg-2 mt-2 mt-lg-0">
                    <a class="btn btn-outline-light btn-sm rounded-pill px-3" href="logout.php">ออกจากระบบ</a>
                </li>
                <?php else: ?>
                <li class="nav-item ms-lg-3 mt-3 mt-lg-0">
                    <a class="btn btn-outline-light btn-sm rounded-pill px-4" href="login.php">เข้าสู่ระบบ</a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
<header class="contact-header text-center">
    <div class="container">
        <h1 class="display-5 fw-bold"><i class="fas fa-headset me-2 text-pink"></i> ติดต่อสอบถาม</h1>
        <p class="lead mb-0"><?php echo $school_name_th; ?></p>
    </div>
</header>

<main class="container py-5">
    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm text-center" role="alert">
            <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm text-center" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i> <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="text-center mb-5">
        <button class="btn btn-warning btn-lg px-4 me-2 shadow-sm rounded-pill" data-bs-toggle="modal" data-bs-target="#reportIssueModal">
            <i class="fas fa-exclamation-circle me-2"></i> แจ้งปัญหา / ข้อเสนอแนะ
        </button>
        <a href="https://www.facebook.com/profile.php?id=100057645151857" target="_blank" class="btn btn-facebook btn-lg px-4 shadow-sm rounded-pill">
            <i class="fab fa-facebook me-2"></i> Facebook โรงเรียน
        </a>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="row g-4 h-100">
                <div class="col-md-5">
                    <div class="contact-card p-4 h-100">
                        <h4 class="fw-bold mb-4 border-bottom pb-3"><i class="fas fa-address-card me-2 text-pink"></i> ข้อมูลติดต่อ</h4>
                        <div class="d-flex align-items-center mb-4">
                            <div class="icon-box me-3"><i class="fas fa-map-marker-alt"></i></div>
                            <div>
                                <h6 class="mb-0 fw-bold">ที่อยู่</h6>
                                <p class="text-muted mb-0 small">111 หมู่ 6 ต.บ้านสวน อ.เมือง จ.ชลบุรี 20000</p>
                            </div>
                        </div>
                        <div class="d-flex align-items-center mb-4">
                            <div class="icon-box me-3"><i class="fas fa-phone-alt"></i></div>
                            <div>
                                <h6 class="mb-0 fw-bold">โทรศัพท์</h6>
                                <p class="text-muted mb-0 small">0-38273-174</p>
                            </div>
                        </div>
                        <div class="d-flex align-items-center mb-4">
                            <div class="icon-box me-3"><i class="fas fa-fax"></i></div>
                            <div>
                                <h6 class="mb-0 fw-bold">โทรสาร</h6>
                                <p class="text-muted mb-0 small">0-38285-505</p>
                            </div>
                        </div>
                        <div class="d-flex align-items-center">
                            <div class="icon-box me-3"><i class="fas fa-envelope"></i></div>
                            <div>
                                <h6 class="mb-0 fw-bold">อีเมล</h6>
                                <p class="text-muted mb-0 small">director@banjan.ac.th</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-7">
                    <div class="map-frame">
                        <iframe 
                            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3882.261274944888!2d100.99824631482597!3d13.334057890618774!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x311d36034870f4d3%3A0xc3911c03e65cd283!2z4LmC4Lij4LiH4LmA4Lij4Li14Lii4LiZ4Lia4LmJ4Liy4LiZ4Liq4Lin4LiZICjจั่นอนุสรณ์)!5e0!3m2!1sth!2sth!4v1710000000000!5m2!1sth!2sth" 
                            width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade">
                        </iframe>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="report-panel">
                <div class="report-header">
                    <?php if ($is_admin): ?>
                        <i class="fas fa-user-shield me-1"></i> จัดการข้อเสนอแนะ (Admin)
                    <?php else: ?>
                        <i class="fas fa-comments me-1"></i> เสียงจากผู้ใช้งาน
                    <?php endif; ?>
                    <span class="badge bg-light text-dark ms-2"><?php echo count($reports); ?></span>
                </div>
                
                <div class="report-body">
                    <?php if (!empty($reports)): ?>
                        <?php foreach ($reports as $report): ?>
                        <div class="chat-message">
                            <div class="chat-bubble <?php echo ($report['is_approved'] == 1) ? 'approved' : 'pending'; ?>">
                                
                                <div class="chat-sender d-flex justify-content-between align-items-center mb-2">
                                    <span><i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($report['reporter_name']); ?></span>
                                    
                                    <?php if ($is_admin): ?>
                                        <?php if ($report['is_approved'] == 1): ?>
                                            <span class="badge bg-success" style="font-size:0.65rem;">แสดงสาธารณะ</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark" style="font-size:0.65rem;">รอตรวจสอบ</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="chat-text text-dark mt-1" style="font-size: 0.95rem;">
                                    <?php echo nl2br(htmlspecialchars($report['issue_detail'])); ?>
                                </div>
                                
                                <div class="chat-time d-flex justify-content-between align-items-end mt-2 pt-2 border-top">
                                    <div>
                                        <?php if ($is_admin): ?>
                                            <?php if ($report['is_approved'] == 0): ?>
                                                <a href="contact.php?action=approve&id=<?php echo $report['id']; ?>" 
                                                   class="btn btn-sm btn-outline-success py-0 px-2" style="font-size: 0.75rem;" 
                                                   onclick="return confirm('ต้องการอนุมัติข้อความนี้ให้คนทั่วไปเห็นใช่หรือไม่?');">
                                                   <i class="fas fa-check"></i> อนุมัติ
                                                </a>
                                            <?php endif; ?>
                                            <a href="contact.php?action=delete&id=<?php echo $report['id']; ?>" 
                                               class="btn btn-sm btn-outline-danger py-0 px-2 ms-1" style="font-size: 0.75rem;" 
                                               onclick="return confirm('ยืนยันการลบข้อความนี้อย่างถาวร?');">
                                               <i class="fas fa-trash"></i> ลบ
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                    <span class="text-muted"><i class="far fa-clock me-1"></i> <?php echo date('d/m/Y H:i', strtotime($report['created_at'])); ?></span>
                                </div>
                                
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center text-muted mt-5">
                            <i class="fas fa-box-open fs-1 mb-3"></i>
                            <p><?php echo $is_admin ? "ยังไม่มีข้อความแจ้งปัญหาในระบบ" : "ยังไม่มีข้อเสนอแนะในขณะนี้<br>"; ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<div class="modal fade" id="reportIssueModal" tabindex="-1" aria-labelledby="reportIssueModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-warning text-dark border-0">
                <h5 class="modal-title fw-bold" id="reportIssueModalLabel">
                    <i class="fas fa-comment-dots me-2"></i> ติดต่อแจ้งปัญหาหรือข้อเสนอแนะ
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="save_report.php" method="POST">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="reporter_name" class="form-label fw-bold">ชื่อ-นามสกุล ผู้แจ้ง <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="reporter_name" name="reporter_name" placeholder="กรุณากรอกชื่อของคุณ" required>
                    </div>
                    <div class="mb-3">
                        <label for="issue_detail" class="form-label fw-bold">รายละเอียดปัญหาหรือข้อเสนอแนะ <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="issue_detail" name="issue_detail" rows="5" placeholder="อธิบายรายละเอียดที่ต้องการแจ้งให้โรงเรียนทราบ..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-warning px-4 fw-bold"><i class="fas fa-paper-plane me-1"></i> ส่งข้อความ</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>