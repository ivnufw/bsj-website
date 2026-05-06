<?php
session_start();
// ป้องกันการเข้าถึงโดยตรงถ้ายังไม่ได้ล็อคอิน
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

// **สำคัญ:** เรียกใช้ไฟล์เชื่อมต่อฐานข้อมูล
include 'db_connect.php'; 

$message = '';
$error = '';
$upload_dir = 'uploads/'; 

// ----------------------
// 1. จัดการการลบข้อมูล (หากคุณต้องการฟังก์ชันนี้)
// ----------------------
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    $delete_id = $conn->real_escape_string($_GET['delete_id']);
    
    // ดึงชื่อไฟล์รูปภาพก่อนลบเพื่อลบไฟล์ออกจาก Server
    $sql_file = "SELECT image_file FROM boards WHERE id = '$delete_id'";
    $result_file = $conn->query($sql_file);
    if ($result_file && $result_file->num_rows > 0) {
        $row_file = $result_file->fetch_assoc();
        $file_to_delete = $upload_dir . $row_file['image_file'];
        if (file_exists($file_to_delete)) {
            unlink($file_to_delete); 
        }
    }
    
    // ลบข้อมูลออกจากตาราง
    $sql_delete = "DELETE FROM boards WHERE id = '$delete_id'";
    if ($conn->query($sql_delete) === TRUE) {
        $message = "ลบรายการประกาศเรียบร้อยแล้ว";
    } else {
        $error = "Error deleting record: " . $conn->error;
    }
}

// ----------------------
// 2. จัดการการเพิ่ม/แก้ไขข้อมูลจาก Modal (ใช้ DB)
// ----------------------
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = $conn->real_escape_string($_POST['title_th']);
    $subtitle = $conn->real_escape_string(str_replace(array("\r\n", "\r", "\n"), '\n', $_POST['subtitle_th'])); // เก็บ \n
    $link = $conn->real_escape_string($_POST['link_url']);
    $footer = $conn->real_escape_string($_POST['footer_text']);
    $sort_order = (isset($_POST['sort_order']) && is_numeric($_POST['sort_order'])) ? (int)$_POST['sort_order'] : 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $board_id = (isset($_POST['board_id']) && is_numeric($_POST['board_id'])) ? (int)$_POST['board_id'] : 0; // ID สำหรับแก้ไข
    
    $image_file_name = NULL;

    // A. จัดการการอัปโหลดไฟล์
    if (isset($_FILES["image_file"]) && $_FILES["image_file"]["error"] == 0) {
        $original_filename = basename($_FILES["image_file"]["name"]);
        $file_extension = pathinfo($original_filename, PATHINFO_EXTENSION);
        $new_filename = 'board_' . time() . '_' . rand(100, 999) . '.' . $file_extension;
        $target_file = $upload_dir . $new_filename;
        
        if (getimagesize($_FILES["image_file"]["tmp_name"]) !== false) {
            // ลบไฟล์เก่าออกถ้าเป็นการแก้ไข
            if ($board_id > 0) {
                $sql_old_file = "SELECT image_file FROM boards WHERE id = '$board_id'";
                $result_old = $conn->query($sql_old_file);
                if ($result_old && $result_old->num_rows > 0) {
                    $old_file = $result_old->fetch_assoc()['image_file'];
                    if (file_exists($upload_dir . $old_file)) {
                        unlink($upload_dir . $old_file);
                    }
                }
            }
            // ... โค้ดเดิม
$target_file = $upload_dir . $new_filename;

// **เพิ่มโค้ดนี้:** ตรวจสอบว่าพาธของโฟลเดอร์ uploads ถูกต้อง
if (!is_dir($upload_dir)) { 
    mkdir($upload_dir, 0777, true); 
}
// ... โค้ดที่เหลือ
            
            if (move_uploaded_file($_FILES["image_file"]["tmp_name"], $target_file)) {
                $image_file_name = $new_filename;
            } else {
                $error = "มีข้อผิดพลาดในการย้ายไฟล์รูปภาพ.";
            }
        } else {
            $error = "ไฟล์ที่อัปโหลดไม่ใช่รูปภาพ.";
        }
    }
    
    // B. บันทึก/อัปเดตข้อมูลในฐานข้อมูล
    if (!isset($error)) {
        if ($board_id > 0) {
            // โหมดแก้ไข
            $update_image = $image_file_name ? ", image_file = '$image_file_name'" : "";
            $sql_update = "UPDATE boards SET 
                           title_th = '$title', subtitle_th = '$subtitle', link_url = '$link', 
                           footer_text = '$footer', sort_order = '$sort_order', is_active = '$is_active'
                           $update_image WHERE id = '$board_id'";
            
            if ($conn->query($sql_update) === TRUE) {
                $message = "แก้ไขรายการประกาศ #$board_id เรียบร้อยแล้ว";
            } else {
                $error = "Error updating record: " . $conn->error;
            }

        } else {
            // โหมดเพิ่มใหม่ (ต้องมีรูปภาพ)
            if ($image_file_name) {
                $sql_insert = "INSERT INTO boards (title_th, subtitle_th, image_file, link_url, footer_text, sort_order, is_active)
                               VALUES ('$title', '$subtitle', '$image_file_name', '$link', '$footer', '$sort_order', '$is_active')";
                               
                if ($conn->query($sql_insert) === TRUE) {
                    $message = "เพิ่มรายการประกาศใหม่เรียบร้อยแล้ว";
                } else {
                    $error = "Error: " . $conn->error;
                }
            } else {
                $error = "กรุณาเลือกไฟล์รูปภาพสำหรับการเพิ่มรายการใหม่.";
            }
        }
    }
}

// ----------------------
// 3. ดึงข้อมูลรายการประกาศทั้งหมด
// ----------------------
$boards_list = [];
$sql_list = "SELECT * FROM boards ORDER BY sort_order ASC, id DESC";
$result_list = $conn->query($sql_list);
if ($result_list && $result_list->num_rows > 0) {
    while($row = $result_list->fetch_assoc()) {
        $boards_list[] = $row;
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BSJ Admin Control - Board Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;600;700&display=swap">
    <style>
        body { font-family: 'Kanit', sans-serif; background-color: #f4f7f6; }
        .container { max-width: 1200px; }
        .card-header-main { background-color: #2c333a; color: white; font-weight: 700; }
        .card-item-header { background-color: #FF69B4; color: white; }
        .img-preview { max-width: 100%; max-height: 150px; object-fit: contain; border-radius: 5px; border: 1px solid #ddd; padding: 5px; }
    </style>
</head>
<body>

<div class="container my-5">
    <h1 class="text-center mb-4 text-dark"><i class="fas fa-tools text-danger me-2"></i> ระบบจัดการบอร์ดประกาศ</h1>
    <p class="text-center mb-5"><a href="index.php" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-home me-1"></i> กลับหน้าหลัก
    </a></p>

    <?php if (isset($message)): ?>
        <div class="alert alert-success text-center"><?php echo $message; ?></div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="alert alert-danger text-center"><?php echo $error; ?></div>
    <?php endif; ?>

    <button type="button" class="btn btn-success mb-3" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="fas fa-plus-circle me-1"></i> เพิ่มรายการใหม่
    </button>
    
    <div class="card shadow-sm">
        <div class="card-header-main card-header"><i class="fas fa-list me-2"></i> รายการบอร์ด "บอกเล่าชาว จ.อ." ปัจจุบัน</div>
        <div class="card-body">
            <div class="row">
                <?php if (!empty($boards_list)): ?>
                    <?php foreach ($boards_list as $board): ?>
                    <div class="col-md-6 mb-4">
                        <div class="card h-100 shadow-sm">
                            <div class="card-item-header card-header">ID: <?php echo $board['id']; ?> | ลำดับ: <?php echo $board['sort_order']; ?></div>
                            <div class="card-body text-center">
                                
                                <img src="<?php echo $upload_dir . htmlspecialchars($board['image_file']); ?>" 
                                     alt="รูปภาพ" class="img-preview mb-3">
                                <h5 class="fw-bold mb-0"><?php echo htmlspecialchars($board['title_th']); ?></h5>
                                <p class="text-muted small mb-1"><?php echo htmlspecialchars($board['link_url']); ?></p>
                                <?php if ($board['is_active']): ?>
                                    <span class="badge bg-success">เปิดใช้งาน</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">ปิดใช้งาน</span>
                                <?php endif; ?>

                                <button type="button" class="btn btn-sm btn-dark mt-3 me-2" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#editModal"
                                    data-id="<?php echo $board['id']; ?>"
                                    data-title="<?php echo htmlspecialchars($board['title_th']); ?>"
                                    data-subtitle="<?php echo htmlspecialchars($board['subtitle_th']); ?>"
                                    data-link="<?php echo htmlspecialchars($board['link_url']); ?>"
                                    data-footer="<?php echo htmlspecialchars($board['footer_text']); ?>"
                                    data-order="<?php echo $board['sort_order']; ?>"
                                    data-active="<?php echo $board['is_active']; ?>"
                                    data-image="<?php echo $upload_dir . htmlspecialchars($board['image_file']); ?>">
                                    <i class="fas fa-edit"></i> แก้ไข
                                </button>
                                <a href="?delete_id=<?php echo $board['id']; ?>" 
                                   onclick="return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบรายการนี้? รูปภาพจะถูกลบออกด้วย')"
                                   class="btn btn-sm btn-danger mt-3">
                                   <i class="fas fa-trash-alt"></i> ลบ
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12"><p class="text-center text-muted p-4">ยังไม่มีรายการประกาศในฐานข้อมูล</p></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addModal" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="addModalLabel"><i class="fas fa-plus-circle me-1"></i> เพิ่มรายการประกาศใหม่</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="board_id" value="0"> 
                    
                    <div class="mb-3">
                        <label for="add-image-file" class="form-label">รูปภาพ (Image File) <span class="text-danger">*</span></label>
                        <input class="form-control" type="file" id="add-image-file" name="image_file" accept="image/*" required>
                    </div>

                    <div class="mb-3">
                        <label for="add-link-url" class="form-label">ลิงก์ปลายทาง (URL) <span class="text-danger">*</span></label>
                        <input type="url" class="form-control" id="add-link-url" name="link_url" placeholder="https://" required>
                    </div>

                    <div class="mb-3">
                        <label for="add-title-th" class="form-label">หัวข้อหลัก (Title) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="add-title-th" name="title_th" required>
                    </div>

                    <div class="mb-3">
                        <label for="add-subtitle-th" class="form-label">รายละเอียดรอง (Subtitle)</label>
                        <textarea class="form-control" id="add-subtitle-th" name="subtitle_th" rows="2"></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="add-footer-text" class="form-label">ข้อความปุ่มด้านล่าง</label>
                            <input type="text" class="form-control" id="add-footer-text" name="footer_text" value="คลิกเพื่อดูรายละเอียด" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="add-sort-order" class="form-label">ลำดับการแสดง</label>
                            <input type="number" class="form-control" id="add-sort-order" name="sort_order" value="0">
                        </div>
                        <div class="col-md-3 d-flex align-items-center pt-md-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="add-is-active" name="is_active" checked>
                                <label class="form-check-label" for="add-is-active">เปิดใช้งาน</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-save me-1"></i> บันทึกรายการใหม่</button>
                </div>
            </form>
        </div>
    </div>
</div>


<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="editModalLabel"><i class="fas fa-edit me-1"></i> แก้ไขบอร์ดประกาศ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" enctype="multipart/form-data" id="editForm">
                <div class="modal-body">
                    <input type="hidden" name="board_id" id="modal-id">

                    <div class="mb-3 text-center">
                        <label class="form-label">รูปภาพปัจจุบัน:</label><br>
                        <img id="modal-current-image" src="" alt="Current Image" class="img-preview">
                    </div>

                    <div class="mb-3">
                        <label for="modal-image-file" class="form-label">อัปโหลดรูปภาพใหม่ (จะใช้แทนรูปเดิม)</label>
                        <input class="form-control" type="file" id="modal-image-file" name="image_file" accept="image/*">
                        <div class="form-text">ปล่อยว่างถ้าไม่ต้องการเปลี่ยนรูปภาพ</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="modal-link-url" class="form-label">ลิงก์ปลายทาง (URL) <span class="text-danger">*</span></label>
                        <input type="url" class="form-control" id="modal-link-url" name="link_url" required>
                    </div>

                    <div class="mb-3">
                        <label for="modal-title-th" class="form-label">หัวข้อหลัก (Title) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="modal-title-th" name="title_th" required>
                    </div>

                    <div class="mb-3">
                        <label for="modal-subtitle-th" class="form-label">รายละเอียดรอง (Subtitle)</label>
                        <textarea class="form-control" id="modal-subtitle-th" name="subtitle_th" rows="2"></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="modal-footer-text" class="form-label">ข้อความปุ่มด้านล่าง</label>
                            <input type="text" class="form-control" id="modal-footer-text" name="footer_text" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="modal-sort-order" class="form-label">ลำดับการแสดง</label>
                            <input type="number" class="form-control" id="modal-sort-order" name="sort_order" value="0">
                        </div>
                        <div class="col-md-3 d-flex align-items-center pt-md-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="modal-is-active" name="is_active">
                                <label class="form-check-label" for="modal-is-active">เปิดใช้งาน</label>
                            </div>
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-warning"><i class="fas fa-save me-1"></i> บันทึกการแก้ไข</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var editModal = document.getElementById('editModal');
        editModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget; 
            
            // ดึงค่าข้อมูลจากปุ่ม
            var id = button.getAttribute('data-id');
            var title = button.getAttribute('data-title');
            var subtitle = button.getAttribute('data-subtitle');
            var link = button.getAttribute('data-link');
            var footer = button.getAttribute('data-footer');
            var image_url = button.getAttribute('data-image');
            var sort_order = button.getAttribute('data-order');
            var is_active = button.getAttribute('data-active');

            // อัปเดตฟอร์มใน Modal
            var modalImage = editModal.querySelector('#modal-current-image');
            var modalId = editModal.querySelector('#modal-id');
            var modalLink = editModal.querySelector('#modal-link-url');
            var modalTitleTh = editModal.querySelector('#modal-title-th');
            var modalSubtitleTh = editModal.querySelector('#modal-subtitle-th');
            var modalFooterText = editModal.querySelector('#modal-footer-text');
            var modalSortOrder = editModal.querySelector('#modal-sort-order');
            var modalIsActive = editModal.querySelector('#modal-is-active');

            modalId.value = id;
            modalImage.src = image_url;
            modalLink.value = link;
            modalTitleTh.value = title;
            // แก้ไข: นำ \n ที่เก็บใน DB ออกก่อนแสดงใน textarea
            modalSubtitleTh.value = subtitle.replace(/\\n/g, '\n'); 
            modalFooterText.value = footer;
            modalSortOrder.value = sort_order;
            modalIsActive.checked = (is_active == 1);
        });
    });
</script>
</body>
</html>