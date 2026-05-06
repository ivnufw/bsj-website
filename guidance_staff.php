<?php
// =======================================================
// ** PHP Configuration Block **
// =======================================================
$staff_group = 'แนะแนว'; // ** เปลี่ยนตรงนี้: ชื่อกลุ่มใน DB **
$staff_group_title = 'กลุ่มสาระการเรียนรู้แนะแนว';
$staff_color_primary = '#FFD700'; // Gold
$staff_color_secondary = '#FFFFF0'; // Ivory (พื้นหลัง)
$staff_color_accent = '#DAA520'; // GoldenRod Accent

session_start();
$is_admin = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
error_reporting(E_ALL); 

// -----------------------------------------------------------------
// ** 1. CONNECTION & INITIAL DATA FETCH **
// -----------------------------------------------------------------
include 'db_connect.php'; 

$message = isset($_GET['msg']) ? urldecode($_GET['msg']) : '';
$error = isset($_GET['err']) ? urldecode($_GET['err']) : '';
$upload_dir = 'uploads/'; 

// --- Function: Format Name ---
function formatFullName($title, $name) {
    return htmlspecialchars($title) . htmlspecialchars($name);
}

// --- Data Fetch: Staff Directory (สำคัญ: เรียงตาม sort_order ASC) ---
$staff_list = [];
$sql_staff = "SELECT id, group_name, title_th, full_name_th, position_th, position_secondary, image_file, contact_email, sort_order FROM staff_directory WHERE group_name = '$staff_group' ORDER BY sort_order ASC, id ASC";
$result_staff = $conn->query($sql_staff);
if ($result_staff && $result_staff->num_rows > 0) {
    while($row = $result_staff->fetch_assoc()) {
        $staff_list[] = $row;
    }
} 

// --- Academic Groups Data (สำหรับ Dropdown ใน Navbar) ---
$academic_groups_data = [
    ["name" => "ภาษาไทย", "link" => "thai_staff.php"],
    ["name" => "คณิตศาสตร์", "link" => "math_staff.php"], 
    ["name" => "วิทยาศาสตร์และเทคโนโลยี", "link" => "science_staff.php"],
    ["name" => "สังคมศึกษา ศาสนาและวัฒนธรรม", "link" => "social_staff.php"], 
    ["name" => "สุขศึกษาและพลศึกษา", "link" => "pe_staff.php"], 
    ["name" => "การงานอาชีพ", "link" => "career_staff.php"], 
    ["name" => "ภาษาต่างประเทศ", "link" => "foreign_staff.php"], 
    ["name" => "ศิลปะ ดนตรี นาฏศิลป์", "link" => "art_staff.php"], 
    ["name" => "งานแนะแนว", "link" => "guidance_staff.php"],
];

// -----------------------------------------------------------------
// ** 2. POST HANDLERS (CRUD Operations) **
// -----------------------------------------------------------------

if ($is_admin && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['staff_action'])) {
    $action = $_POST['staff_action'];
    $staff_id = isset($_POST['staff_id']) ? (int)$_POST['staff_id'] : 0;
    $title = $conn->real_escape_string($_POST['title_th']);
    $full_name = $conn->real_escape_string($_POST['full_name_th']);
    $position = $conn->real_escape_string($_POST['position_th']);
    
    // จัดการตำแหน่งรอง: ถ้าเลือก "อื่นๆ" ให้ดึงค่าจากช่อง input text แทน
    $position_secondary = $conn->real_escape_string($_POST['position_secondary'] ?? ''); 
    if ($position_secondary === 'อื่นๆ') {
        $position_secondary = $conn->real_escape_string($_POST['position_secondary_other'] ?? '');
    }
    
    $email = $conn->real_escape_string($_POST['contact_email'] ?? ''); 
    $sort = (int)$_POST['sort_order'];
    $current_image = isset($_POST['current_image_file']) ? $conn->real_escape_string($_POST['current_image_file']) : '';
    $image_name = $current_image;
    $sql = "";
    
    if ($action == 'add' || $action == 'edit') {
        // --- Image Upload Logic ---
        if (isset($_FILES["image_file"]) && $_FILES["image_file"]["error"] == 0) {
            $file_info = $_FILES["image_file"];
            $file_extension = pathinfo(basename($file_info["name"]), PATHINFO_EXTENSION);
            $new_filename = strtolower(str_replace(' ', '_', $staff_group)) . '_' . time() . '.' . $file_extension;
            $target_file = $upload_dir . $new_filename;
            
            if (getimagesize($file_info["tmp_name"]) !== false) {
                if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }
                
                if ($action == 'edit' && !empty($current_image) && file_exists($upload_dir . $current_image)) {
                    unlink($upload_dir . $current_image);
                }
                
                if (move_uploaded_file($file_info["tmp_name"], $target_file)) { 
                    $image_name = $new_filename; 
                } else { $error = "มีข้อผิดพลาดในการย้ายไฟล์รูปภาพ."; }
            } else { $error = "ไฟล์ที่อัปโหลดไม่ใช่รูปภาพ."; }
        }
        
        // --- Save/Update DB Logic ---
        if (empty($error)) {
            if ($staff_id > 0) {
                $sql = "UPDATE staff_directory SET 
                        title_th = '$title', full_name_th = '$full_name', position_th = '$position', 
                        position_secondary = '$position_secondary',
                        image_file = '$image_name', contact_email = '$email', sort_order = '$sort' 
                        WHERE id = $staff_id";
                $message = "แก้ไขข้อมูลบุคลากรเรียบร้อยแล้ว";
            } else {
                 if (empty($image_name) && !isset($_FILES["image_file"])) { $error = "กรุณาเลือกไฟล์รูปภาพสำหรับการเพิ่มรายการใหม่."; }
                 else {
                    $sql = "INSERT INTO staff_directory (group_name, title_th, full_name_th, position_th, position_secondary, image_file, contact_email, sort_order) 
                            VALUES ('$staff_group', '$title', '$full_name', '$position', '$position_secondary', '$image_name', '$email', '$sort')";
                    $message = "เพิ่มข้อมูลบุคลากรใหม่เรียบร้อยแล้ว";
                 }
            }
            if (!empty($sql) && empty($error) && !$conn->query($sql)) { 
                $error = "Error saving data: " . $conn->error; $message = ''; 
            }
        }
    } elseif ($action == 'delete' && $staff_id > 0) {
        // --- Delete Logic ---
        $sql_file = "SELECT image_file FROM staff_directory WHERE id = $staff_id";
        $result_file = $conn->query($sql_file);
        if ($result_file && $result_file->num_rows > 0) {
            $file_to_delete = $result_file->fetch_assoc()['image_file'];
            if (!empty($file_to_delete) && file_exists($upload_dir . $file_to_delete)) { 
                unlink($upload_dir . $file_to_delete); 
            }
        }
        $sql = "DELETE FROM staff_directory WHERE id = $staff_id";
        if ($conn->query($sql)) { $message = "ลบข้อมูลบุคลากรเรียบร้อยแล้ว"; } 
        else { $error = "Error deleting staff: " . $conn->error; }
    }

    $conn->close();
    header("Location: " . basename($_SERVER['PHP_SELF']) . "?msg=" . urlencode($message) . "&err=" . urlencode($error));
    exit;
}

// Close DB Connection (สำหรับกรณีที่ไม่มีการ POST)
if (isset($conn)) $conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $staff_group_title; ?> | ทำเนียบครู</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;600;700&display=swap">
    <link rel="stylesheet" href="style.css"> 
    <style>
        /* CSS เฉพาะสำหรับกลุ่มสาระ */
        
        body {
            background-color: white !important;
        }
        
        .thai-staff-section { 
            background-color: <?php echo $staff_color_secondary; ?>;
            padding: 40px 20px; 
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.05);
        }
        
        .thai-staff-header {
            color: <?php echo $staff_color_primary; ?>;
            font-weight: 700;
            border-bottom: 5px double <?php echo $staff_color_accent; ?>; 
            border-top: 1px solid <?php echo $staff_color_primary; ?>; 
            padding-bottom: 10px;
            margin-bottom: 40px;
            text-align: center;
        }
        .staff-card {
            border: 2px solid <?php echo $staff_color_accent; ?>; 
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            overflow: hidden;
            background-color: white;
            text-align: center;
            height: 100%; 
        }
        .staff-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        }

        /* --- ASPECT RATIO 4:5 (แนวตั้ง) --- */
        .staff-photo-wrapper {
            position: relative;
            width: 100%;
            padding-top: 125%; 
            overflow: hidden; 
            border-bottom: 3px solid <?php echo $staff_color_accent; ?>;
        }

        .staff-photo {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover; 
            border-radius: 10px 10px 0 0;
        }

        .staff-name {
            font-size: 1.2rem;
            font-weight: 600;
            color: <?php echo $staff_color_primary; ?>;
            margin-bottom: 5px;
        }
        .staff-position-primary {
            font-size: 0.9rem;
            color: #555;
            margin-bottom: 2px;
        }
        .staff-position-secondary {
            font-size: 0.8rem;
            color: #888;
            margin-bottom: 10px;
        }
        .btn-add-staff {
            background-color: <?php echo $staff_color_primary; ?>;
            border-color: <?php echo $staff_color_primary; ?>;
            color: white;
            transition: background-color 0.3s;
        }
        .btn-add-staff:hover {
            background-color: #B8860B; 
            border-color: #B8860B;
            color: white;
        }
        
        /* Layout หัวหน้ากลุ่มสาระ */
        .head-staff-card {
            width: 100%;
            max-width: 300px; 
            margin: 0 auto;
        }
        
        .staff-card.head-staff-card {
            height: auto !important; 
            margin-bottom: 40px !important; 
        }
        
        /* FIX: MODAL CSS */
        #staffEditModal .modal-body { 
            max-height: calc(100vh - 180px) !important;
            overflow-y: auto !important; 
            overflow-x: hidden; 
        }
        .img-preview {
            max-width: 100%;
            height: auto;
            max-height: 150px;
            object-fit: contain;
            border: 1px solid #ddd;
            padding: 5px;
            border-radius: 5px;
        }
        
        /* Custom Delete Modal Style */
        #deleteConfirmModal .modal-content {
            border: 3px solid <?php echo $staff_color_primary; ?>;
        }
    </style>
</head>
<body>

<div class="marquee-bar">
    <div class="container-fluid">
        <div class="marquee-content">
            <i class="fas fa-bullhorn me-2"></i> :: ยินดีต้อนรับสู่เว็บไซต์โรงเรียนบ้านสวน (จั่นอนุสรณ์) :: Welcome to Bansuan Jananusorn School :: <a href="http://www.banjan.ac.th" class="text-white text-decoration-underline">http://www.banjan.ac.th</a> :: ขอให้ทุกท่านมีความสุขกับการเยี่ยมชมเว็บไซต์ของเราค่ะ ::
        </div>
    </div>
</div>

<nav class="navbar navbar-expand-lg navbar-custom sticky-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="index.php">
            <img src="janLogo.png" alt="School Logo" class="me-2" style="height: 40px !important;"> 
            <span class="fw-bold fs-4">BSJ</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="index.php">หน้าแรก</a></li>
                
                <li class="nav-item">
                    <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'executive_staff.php') ? 'active' : ''; ?>" href="executive_staff.php">คณะผู้บริหาร</a>
                </li>
                
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownAcademic" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        กลุ่มสาระอื่น ๆ
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="navbarDropdownAcademic">
                        <?php 
                        // ตรวจสอบว่าตัวแปร $academic_groups_data ถูกกำหนดไว้ในไฟล์หรือไม่
                        if (isset($academic_groups_data)):
                            foreach ($academic_groups_data as $group): 
                        ?>
                        <li><a class="dropdown-item" href="<?php echo $group['link']; ?>"><?php echo $group['name']; ?></a></li>
                        <?php 
                            endforeach; 
                        endif;
                        ?>
                    </ul>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'support_staff.php') ? 'active' : ''; ?>" href="support_staff.php">ภารโรง/สนับสนุน</a>
                </li>
                
                <li class="nav-item"><a class="nav-link btn btn-pink text-white ms-lg-3" href="admission.php">สมัครเรียน</a></li>
            </ul>
        </div>
    </div>
</nav>

<header class="hero-section text-center">
    <div class="container">
        <h1 class="display-2 mb-3">บุคลากรโรงเรียนบ้านสวน (จั่นอนุสรณ์)</h1> 
    </div>
</header>

<main class="container my-5">
    <div class="thai-staff-section">
    
        <?php if (!empty($message)): ?>
            <div class="alert alert-success text-center"><?php echo $message; ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger text-center"><?php echo $error; ?></div>
        <?php endif; ?>

        <h2 class="thai-staff-header display-5 mb-5"><i class="fas fa-compass me-3"></i> <?php echo $staff_group_title; ?></h2>
        
        <?php if ($is_admin): ?>
        <button type="button" class="btn btn-add-staff mb-4 w-100" data-bs-toggle="modal" data-bs-target="#staffEditModal" data-staff-id="0" id="btn-add-staff">
            <i class="fas fa-pen-fancy me-1"></i> เพิ่มบุคลากรใหม่ในกลุ่มสาระ<?php echo $staff_group; ?>
        </button>
        
        <div class="alert alert-info small text-center">
            **คำแนะนำสำหรับ Admin:** กรุณากำหนด **ลำดับการแสดง (Sort Order)** ให้ **หัวหน้ากลุ่มสาระมีค่าต่ำที่สุด (เช่น 1)** เพื่อให้แสดงผลเป็นคนแรกเสมอ
        </div>
        
        <?php endif; ?>

        <?php if (!empty($staff_list)): ?>
            
            <?php 
            // 1. หัวหน้ากลุ่มสาระ
            $head_staff = $staff_list[0]; 
            ?>
            <div class="row justify-content-center">
                <div class="col-12 text-center">
                    <div class="card staff-card head-staff-card">
                        <div class="staff-photo-wrapper"> 
                            <img src="<?php echo $upload_dir . htmlspecialchars($head_staff['image_file'] ?? 'placeholder.jpg'); ?>" 
                                 alt="<?php echo formatFullName($head_staff['title_th'], $head_staff['full_name_th']); ?>" 
                                 class="staff-photo">
                        </div>
                        <div class="staff-info d-flex flex-column p-3">
                            <p class="staff-name mb-1"><?php echo formatFullName($head_staff['title_th'], $head_staff['full_name_th']); ?></p>
                            <p class="staff-position-primary"><?php echo htmlspecialchars($head_staff['position_th']); ?></p>
                            <?php if (!empty($head_staff['position_secondary'])): ?>
                                <p class="staff-position-secondary"><?php echo htmlspecialchars($head_staff['position_secondary']); ?></p>
                            <?php endif; ?>

                            <?php if ($is_admin): ?>
                                <div class="mt-2">
                                    <button type="button" class="btn btn-sm btn-warning me-1 btn-edit-staff" 
                                            data-bs-toggle="modal" data-bs-target="#staffEditModal" data-id="<?php echo $head_staff['id']; ?>"
                                            data-title="<?php echo htmlspecialchars($head_staff['title_th']); ?>" data-name="<?php echo htmlspecialchars($head_staff['full_name_th']); ?>"
                                            data-position="<?php echo htmlspecialchars($head_staff['position_th']); ?>"
                                            data-position-secondary="<?php echo htmlspecialchars($head_staff['position_secondary']); ?>"
                                            data-image="<?php echo htmlspecialchars($head_staff['image_file']); ?>" data-order="<?php echo $head_staff['sort_order']; ?>">
                                        <i class="fas fa-pencil-alt"></i> แก้ไข
                                    </button>
                                    <button type="button" class="btn btn-sm btn-danger btn-delete-staff" 
                                            data-bs-toggle="modal" data-bs-target="#deleteConfirmModal"
                                            data-id="<?php echo $head_staff['id']; ?>"
                                            data-name="<?php echo formatFullName($head_staff['title_th'], $head_staff['full_name_th']); ?>">
                                        <i class="fas fa-trash"></i> ลบ
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php 
            // 2. บุคลากรคนอื่น ๆ
            $other_staff = array_slice($staff_list, 1); 
            ?>
            <?php if (!empty($other_staff)): ?>
            <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4 staff-grid-row"> 
                <?php foreach ($other_staff as $staff): ?>
                <div class="col">
                    <div class="card staff-card h-100">
                        <div class="staff-photo-wrapper">
                            <img src="<?php echo $upload_dir . htmlspecialchars($staff['image_file'] ?? 'placeholder.jpg'); ?>" 
                                 alt="<?php echo formatFullName($staff['title_th'], $staff['full_name_th']); ?>" 
                                 class="staff-photo">
                        </div>
                        <div class="staff-info d-flex flex-column h-100 p-3">
                            <p class="staff-name mb-1"><?php echo formatFullName($staff['title_th'], $staff['full_name_th']); ?></p>
                            <p class="staff-position-primary"><?php echo htmlspecialchars($staff['position_th']); ?></p>
                            <?php if (!empty($staff['position_secondary'])): ?>
                                <p class="staff-position-secondary"><?php echo htmlspecialchars($staff['position_secondary']); ?></p>
                            <?php endif; ?>

                            <?php if ($is_admin): ?>
                            <div class="mt-auto pt-2">
                                <button type="button" class="btn btn-sm btn-warning me-1 btn-edit-staff" 
                                        data-bs-toggle="modal" data-bs-target="#staffEditModal"
                                        data-id="<?php echo $staff['id']; ?>"
                                        data-title="<?php echo htmlspecialchars($staff['title_th']); ?>"
                                        data-name="<?php echo htmlspecialchars($staff['full_name_th']); ?>"
                                        data-position="<?php echo htmlspecialchars($staff['position_th']); ?>"
                                        data-position-secondary="<?php echo htmlspecialchars($staff['position_secondary']); ?>"
                                        data-image="<?php echo htmlspecialchars($staff['image_file']); ?>"
                                        data-order="<?php echo $staff['sort_order']; ?>">
                                    <i class="fas fa-pencil-alt"></i> แก้ไข
                                </button>
                                <button type="button" class="btn btn-sm btn-danger btn-delete-staff" 
                                        data-bs-toggle="modal" data-bs-target="#deleteConfirmModal"
                                        data-id="<?php echo $staff['id']; ?>"
                                        data-name="<?php echo formatFullName($staff['title_th'], $staff['full_name_th']); ?>">
                                    <i class="fas fa-trash"></i> ลบ
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="col-12">
                <p class="text-center text-muted p-5 border rounded">ยังไม่มีรายการบุคลากร</p>
            </div>
        <?php endif; ?>
    </div> 
</main>

<footer>
    <div class="container">
        <div class="row">
            <div class="col-md-4 mb-4">
                <h5><i class="fas fa-graduation-cap me-2"></i> โรงเรียนบ้านสวน (จั่นอนุสรณ์)</h5>
                <p class="small">ก้าวไปข้างหน้าอย่างมั่นคง มุ่งสู่ความเป็นเลิศทางการศึกษา</p>
                <div class="social-icons mt-3">
                    <a href="https://www.facebook.com/profile.php?id=100057645151857" class="me-3" target="_blank"><i class="fab fa-facebook-square"></i></a>
                    <a href="#" class="me-3"><i class="fab fa-youtube"></i></a>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <h5><i class="fas fa-map-marker-alt me-2"></i> ที่อยู่</h5>
                <p class="small">111 หมู่ที่ 6 ถนน เศรษฐกิจ ตำบล บ้านสวน อำเภอเมืองชลบุรี ชลบุรี 20000</p>
                <p class="small"><i class="fas fa-phone me-2"></i> โทรศัพท์: 038 273 174</p>
                <p class="small"><i class="fas fa-fax me-2"></i> โทรสาร: 038 285 505</p>
                <p class="small"><i class="fas fa-envelope me-2"></i> อีเมล: director@banjan.ac.th</p>
            </div>
            <div class="col-md-4 mb-4">
                <h5><i class="fas fa-sitemap me-2"></i> แผนที่เว็บไซต์</h5>
                <ul class="list-unstyled">
                    <li><a href="index.php">หน้าแรก</a></li>
                    <li><a href="#">เกี่ยวกับโรงเรียน</a></li>
                    <li><a href="#">กลุ่มสาระการเรียนรู้</a></li>
                    <li><a href="contact.php">ติดต่อเรา</a></li>
                </ul>
            </div>
                        
        </div>
        <hr class="my-4 border-secondary">
        <p class="text-center mb-0 small">&copy; <?php echo date("Y"); ?> โรงเรียนบ้านสวน (จั่นอนุสรณ์). สงวนลิขสิทธิ์.</p>
    </div>
</footer>

<?php if ($is_admin): ?>
<div class="modal fade" id="staffEditModal" tabindex="-1" aria-labelledby="staffEditModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header text-white" style="background-color: <?php echo $staff_color_primary; ?>;">
                <h5 class="modal-title" id="staffEditModalLabel"><i class="fas fa-user-edit me-1"></i> เพิ่ม/แก้ไข บุคลากรกลุ่มสาระแนะแนว</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="staff_action" id="staff-action" value="add">
                <input type="hidden" name="staff_id" id="staff-id" value="0">
                <input type="hidden" name="current_image_file" id="current-image-file">
                <input type="hidden" name="contact_email" id="contact-email">
                
                <div class="modal-body">
                    <div class="mb-3 text-center">
                        <label class="form-label">รูปภาพปัจจุบัน/ตัวอย่าง (4:5 แนวตั้ง):</label><br>
                        <img id="modal-staff-current-image" src="" alt="Current Staff Image" class="img-preview mb-2">
                    </div>

                    <div class="mb-3">
                        <label for="image-file" class="form-label">อัปโหลดรูปภาพใหม่ (จะใช้แทนรูปเดิม)</label>
                        <input class="form-control" type="file" id="image-file" name="image_file" accept="image/*">
                        <div class="form-text">ปล่อยว่างถ้าไม่ต้องการเปลี่ยนรูปภาพ (ภาพแนะนำคือสี่เหลี่ยมแนวตั้ง 4:5)</div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-4">
                            <label for="title-th" class="form-label">คำนำหน้า <span class="text-danger">*</span></label>
                            <select class="form-select" id="title-th" name="title_th" required>
                                <option value="นาย">นาย</option>
                                <option value="นาง">นาง</option>
                                <option value="นางสาว">นางสาว</option>
                            </select>
                        </div>
                        <div class="col-8">
                            <label for="full-name-th" class="form-label">ชื่อ-นามสกุล (ไทย) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="full-name-th" name="full_name_th" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="position-th" class="form-label">ตำแหน่ง/หน้าที่หลัก <span class="text-danger">*</span></label>
                        <select class="form-select" id="position-th" name="position_th" required>
                            <option value="" disabled selected>--- เลือกตำแหน่ง ---</option>
                            <option value="หัวหน้ากลุ่มสาระการเรียนรู้แนะแนว">หัวหน้ากลุ่มสาระการเรียนรู้แนะแนว</option>
                            <option value="ครูเชี่ยวชาญพิเศษ">ครูเชี่ยวชาญพิเศษ</option>
                            <option value="ครูเชี่ยวชาญ">ครูเชี่ยวชาญ</option>
                            <option value="ครูชำนาญการพิเศษ">ครูชำนาญการพิเศษ</option>
                            <option value="ครูชำนาญการ">ครูชำนาญการ</option>
                            <option value="ครู">ครู</option>
                            <option value="ครูผู้ช่วย">ครูผู้ช่วย</option>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-9 mb-3">
                            <label for="position-secondary" class="form-label">ตำแหน่ง/หน้าที่รอง</label>
                            <select class="form-select" id="position-secondary" name="position_secondary">
                                <option value="">--- ไม่ระบุ ---</option>
                                <option value="ครูเชี่ยวชาญพิเศษ">ครูเชี่ยวชาญพิเศษ</option>
                                <option value="ครูเชี่ยวชาญ">ครูเชี่ยวชาญ</option>
                                <option value="ครูชำนาญการพิเศษ">ครูชำนาญการพิเศษ</option>
                                <option value="ครูชำนาญการ">ครูชำนาญการ</option>
                                <option value="ครู">ครู</option>
                                <option value="ครูผู้ช่วย">ครูผู้ช่วย</option>
                                <option value="อื่นๆ">อื่นๆ (ระบุเอง)</option>
                            </select>
                            
                            <input type="text" class="form-control mt-2" id="position-secondary-other" name="position_secondary_other" placeholder="พิมพ์ตำแหน่งรองอื่นๆ ที่นี่..." style="display: none;">
                            
                            <div class="form-text">จะแสดงใต้ตำแหน่งหลัก (ไม่บังคับกรอก)</div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="sort-order" class="form-label">ลำดับ <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="sort-order" name="sort_order" value="0" required>
                            <div class="form-text">**หัวหน้ามีค่าน้อยสุด**</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary" style="background-color: <?php echo $staff_color_primary; ?>; border-color: <?php echo $staff_color_primary; ?>;"><i class="fas fa-save me-1"></i> บันทึกข้อมูล</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content border-danger">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteConfirmModalLabel">ยืนยันการลบข้อมูล</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="staff_action" value="delete">
                <input type="hidden" name="staff_id" id="delete-staff-id">
                <div class="modal-body text-center">
                    <p class="mb-0">คุณแน่ใจหรือไม่ที่จะลบข้อมูลของ</p>
                    <p class="fw-bold text-danger fs-5" id="staff-name-to-delete"></p>
                    <p class="small text-muted">รายการนี้จะถูกลบอย่างถาวร</p>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-trash-alt me-1"></i> ยืนยันการลบ</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const uploadDir = '<?php echo $upload_dir; ?>';
        const staffEditModal = document.getElementById('staffEditModal');
        const deleteConfirmModal = document.getElementById('deleteConfirmModal');
        
        // --- จัดการ Dropdown "อื่นๆ" สำหรับตำแหน่งรอง ---
        const posSecSelect = document.getElementById('position-secondary');
        const posSecOtherInput = document.getElementById('position-secondary-other');
        
        if (posSecSelect && posSecOtherInput) {
            posSecSelect.addEventListener('change', function() {
                if (this.value === 'อื่นๆ') {
                    posSecOtherInput.style.display = 'block';
                    posSecOtherInput.required = true;
                } else {
                    posSecOtherInput.style.display = 'none';
                    posSecOtherInput.required = false;
                    posSecOtherInput.value = ''; // ล้างค่าทิ้งเมื่อไม่ได้ใช้
                }
            });
        }
        
        // --- Admin Edit Modal Handler ---
        if (staffEditModal) {
            staffEditModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const staffId = button ? button.getAttribute('data-id') : 0; 

                if (staffId > 0) {
                    // Edit Mode
                    staffEditModal.querySelector('.modal-title').textContent = 'แก้ไขบุคลากร #' + staffId;
                    document.getElementById('staff-action').value = 'edit';
                    document.getElementById('staff-id').value = staffId;
                    
                    document.getElementById('title-th').value = button.getAttribute('data-title');
                    document.getElementById('full-name-th').value = button.getAttribute('data-name');
                    document.getElementById('position-th').value = button.getAttribute('data-position');
                    
                    // Logic สำหรับตั้งค่า Dropdown ตำแหน่งรอง
                    const currentSecPos = button.getAttribute('data-position-secondary') || '';
                    const predefinedOptions = ['', 'ครูเชี่ยวชาญพิเศษ', 'ครูเชี่ยวชาญ', 'ครูชำนาญการพิเศษ', 'ครูชำนาญการ', 'ครู', 'ครูผู้ช่วย'];
                    
                    if (predefinedOptions.includes(currentSecPos)) {
                        posSecSelect.value = currentSecPos;
                        posSecOtherInput.style.display = 'none';
                        posSecOtherInput.value = '';
                        posSecOtherInput.required = false;
                    } else {
                        // ถ้าเป็นค่า Custom ให้เลือก 'อื่นๆ' และโชว์ input
                        posSecSelect.value = 'อื่นๆ';
                        posSecOtherInput.style.display = 'block';
                        posSecOtherInput.value = currentSecPos;
                        posSecOtherInput.required = true;
                    }
                    
                    document.getElementById('sort-order').value = button.getAttribute('data-order');

                    const currentImage = button.getAttribute('data-image');
                    document.getElementById('current-image-file').value = currentImage;
                    document.getElementById('modal-staff-current-image').src = uploadDir + currentImage;
                } else {
                    // Add Mode
                    staffEditModal.querySelector('.modal-title').textContent = 'เพิ่มบุคลากรกลุ่มสาระแนะแนว';
                    document.getElementById('staff-action').value = 'add';
                    document.getElementById('staff-id').value = 0;
                    
                    document.getElementById('title-th').value = 'นาย';
                    document.getElementById('full-name-th').value = '';
                    document.getElementById('position-th').value = '';
                    
                    // Reset ตำแหน่งรอง
                    if (posSecSelect && posSecOtherInput) {
                        posSecSelect.value = ''; 
                        posSecOtherInput.style.display = 'none';
                        posSecOtherInput.value = '';
                        posSecOtherInput.required = false;
                    }
                    
                    // หาค่า Sort Order ถัดไป 
                    const staffList = <?php echo json_encode($staff_list); ?>;
                    let maxSortOrder = 0;
                    if (staffList.length > 0) {
                        staffList.forEach(item => {
                            if (parseInt(item.sort_order) > maxSortOrder) {
                                maxSortOrder = parseInt(item.sort_order);
                            }
                        });
                    }
                    const nextSortOrder = maxSortOrder > 0 ? (maxSortOrder + 1) : 10;
                    document.getElementById('sort-order').value = nextSortOrder;
                    
                    document.getElementById('current-image-file').value = '';
                    document.getElementById('modal-staff-current-image').src = 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs='; 
                }
                 // Reset file input
                document.getElementById('image-file').value = '';
            });
        }
        
        // --- Delete Confirmation Modal Handler ---
        if (deleteConfirmModal) {
            deleteConfirmModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const staffId = button.getAttribute('data-id');
                const staffName = button.getAttribute('data-name');
                
                document.getElementById('delete-staff-id').value = staffId;
                document.getElementById('staff-name-to-delete').textContent = staffName;
            });
        }
        
        // --- Global Modal Cleanup ---
        document.querySelectorAll('.modal').forEach(modalEl => {
            modalEl.addEventListener('hidden.bs.modal', function () {
                if (document.querySelectorAll('.modal.show').length === 0) {
                    const backdrops = document.querySelectorAll('.modal-backdrop');
                    backdrops.forEach(backdrop => backdrop.remove());
                    document.body.classList.remove('modal-open');
                    document.body.style.overflow = '';
                    document.body.style.paddingRight = '';
                }
            });
        });

    });
</script>
</body>
</html>