<?php
session_start();
$is_admin = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
error_reporting(E_ALL);


// -----------------------------------------------------------------
// ** 1. CONNECTION & INITIAL DATA FETCH **
// -----------------------------------------------------------------
// ตรวจสอบว่าไฟล์ db_connect.php อยู่ใน Path เดียวกัน
include 'db_connect.php'; 

// ตรวจสอบการเชื่อมต่อฐานข้อมูล (เพื่อ Debug)
if (!isset($conn) || $conn->connect_error) {
    die("Database Connection Error: " . $conn->connect_error . ". Please check db_connect.php credentials.");
}

$message = isset($_GET['msg']) ? urldecode($_GET['msg']) : '';
$error = isset($_GET['err']) ? urldecode($_GET['err']) : '';
$upload_dir = 'uploads/'; 

// --- Function: Format Date Thai ---
function formatDateThai($dateString) {
    if (!$dateString || $dateString === '0000-00-00 00:00:00') return '';
    try {
        $date = new DateTime($dateString);
        $year = $date->format('Y') + 543; // แปลง ค.ศ. เป็น พ.ศ.
        $monthNames = [
            'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.',
            'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'
        ];
        $month = $monthNames[$date->format('n') - 1];
        $day = $date->format('d');
        
        return "$day $month $year";
    } catch (Exception $e) {
        return date('d M Y', strtotime($dateString)); // Fallback
    }
}
// --- Config IDs ---
$manual_id = 1; 
$journal_id = 1; 

// --- D. พันธกิจ และรายการเกี่ยวกับโรงเรียน (DB: about_us_config) ---
// *** โค้ดแก้ไข: ใช้โครงสร้างข้อมูลแบบใหม่ตามตาราง about_us_config ***
// ** เนื่องจากเราใช้ชื่อเรื่อง (title_th) ใน DB เป็นตัวเก็บ URL แล้ว จึงต้องทำการดึงค่า title_th มาใช้เป็น link_url_pdf **
$about_us_data = [
    'mission' => ['title' => 'พันธกิจ', 'image_file' => 'default_mission.png', 'id' => 1, 'link_url_pdf' => ''],
    'vision' => ['title' => 'วิสัยทัศน์', 'image_file' => 'default_vision.png', 'id' => 2, 'link_url_pdf' => ''],
    'intro' => ['title' => 'แนะนำโรงเรียน', 'image_file' => 'default_intro.png', 'id' => 3, 'link_url_pdf' => ''],
    'charac' => ['title' => 'คุณลักษณะอันพึงประสงค์', 'image_file' => 'default_charac.png', 'id' => 4, 'link_url_pdf' => ''],
    'map' => ['title' => 'แผนที่โรงเรียน', 'image_file' => 'default_map.png', 'id' => 5, 'link_url_pdf' => ''],
    'school_plan' => ['title' => 'แผนผังโรงเรียน', 'image_file' => 'default_plan.png', 'id' => 6, 'link_url_pdf' => ''],
];
$id_map = ['mission' => 1, 'vision' => 2, 'intro' => 3, 'charac' => 4, 'map' => 5];

// ดึงข้อมูลจากตาราง about_us_config
$sql_select_about = "SELECT item_key, title_th, image_file FROM about_us_config";
$result_about = $conn->query($sql_select_about);

if ($result_about && $result_about->num_rows > 0) {
    while ($row = $result_about->fetch_assoc()) {
        $key = $row['item_key'];
        if (isset($about_us_data[$key])) {
            // 💡 เราใช้ title_th เป็นตัวเก็บ URL PDF แทน (Bypass)
            $about_us_data[$key]['link_url_pdf'] = $row['title_th']; 
            // 💡 กำหนด title_th เป็นชื่อเดิมเพื่อให้แสดงผลถูกต้อง (Hardcode ชื่อเดิม)
            // (ต้องกำหนดชื่อจริงกลับไปเพื่อให้ปุ่มแสดงชื่อถูก)
            $original_title = $about_us_data[$key]['title']; 
            $about_us_data[$key]['title'] = $original_title; 
            // 💡 image_file ยังคงเก็บชื่อไฟล์รูปภาพเดิมไว้
            $about_us_data[$key]['image_file'] = $row['image_file']; 
        }
    }
} else {
    // ถ้าไม่มีข้อมูล ให้ INSERT DEFAULT DATA 
    $insert_values = [];
    foreach ($about_us_data as $key => $data) {
        $id = $id_map[$key];
        // 💡 ใส่ URL เริ่มต้นใน title_th ในกรณีไม่มีข้อมูล
        $default_link = $data['link_url_pdf'];
        $insert_values[] = "($id, '$key', '$default_link', '{$data['image_file']}')";
    }
    // ใช้ INSERT IGNORE เพื่อป้องกันความผิดพลาดหากตารางถูกสร้างแล้ว
    $sql_insert_default_about = "INSERT IGNORE INTO about_us_config (id, item_key, title_th, image_file) VALUES " . implode(', ', $insert_values);
    if ($conn->query($sql_insert_default_about) === FALSE) {
        error_log("Error inserting default about_us_config data: " . $conn->error);
    }
}

// 💡 Hardcode URL PDF เริ่มต้น (ใช้เฉพาะครั้งแรกถ้า DB ว่าง)
// ** โค้ดนี้จะใช้เมื่อไม่มีค่าใน DB เท่านั้น**
$about_us_data['mission']['link_url_pdf'] = $about_us_data['mission']['link_url_pdf'] ?: 'https://drive.google.com/file/d/ID_MISSION/preview';
$about_us_data['vision']['link_url_pdf'] = $about_us_data['vision']['link_url_pdf'] ?: 'https://drive.google.com/file/d/ID_VISION/preview';
$about_us_data['intro']['link_url_pdf'] = $about_us_data['intro']['link_url_pdf'] ?: 'https://drive.google.com/file/d/ID_INTRO/preview';
$about_us_data['charac']['link_url_pdf'] = $about_us_data['charac']['link_url_pdf'] ?: 'https://drive.google.com/file/d/ID_CHARAC/preview';
$about_us_data['map']['link_url_pdf'] = $about_us_data['map']['link_url_pdf'] ?: 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d4817.03397414338!2d101.00035677508308!3d13.354438786997248!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x311d359f59dba15b%3A0xd7156c6ffe83f20d!2z4LmC4Lij4LiH4LmA4Lij4Li14Lii4LiZ4Lia4LmJ4Liy4LiZ4Liq4Lin4LiZICjguIjguLHguYjguJnguK3guJnguLjguKrguKPguJPguYwp!5e1!3m2!1sth!2sth!4v1766631911996!5m2!1sth!2sth'; // Example embed map


$mission_data = $about_us_data['mission']; 
$mission_data['title'] = $mission_data['title']; 
// *** สิ้นสุดโค้ดแก้ไข ***


// --- A. คู่มือ (JSON) ---
$manual_data = [
    'image_file' => 'manual_default.jpg', 
    'link_url' => 'http://www.banjan.ac.th/manual_2568.pdf',
    'title' => 'คู่มือนักเรียน ครู และผู้ปกครอง',
];
$config_file = 'manual_config.json';
if (file_exists($config_file)) {
    $manual_data = json_decode(file_get_contents($config_file), true);
} else {
    if (!file_put_contents($config_file, json_encode($manual_data))) {
        // $error = "ไม่สามารถสร้างไฟล์ manual_config.json ได้";
    }
}

// --- B. วารสาร (DB) ---
$sql_select_journal = "SELECT image_file, link_url FROM journal_config WHERE id = $journal_id";
$result_journal = $conn->query($sql_select_journal);
$journal_data = ['image_file' => 'journal_default.jpg', 'link_url' => '#', 'title' => 'วารสารโรงเรียน'];
if ($result_journal && $result_journal->num_rows > 0) {
    $row = $result_journal->fetch_assoc();
    $journal_data['image_file'] = $row['image_file'];
    $journal_data['link_url'] = $row['link_url'];
} else {
    $sql_insert_default = "INSERT IGNORE INTO journal_config (id, image_file, link_url) VALUES (1, 'journal_default.jpg', 'http://www.banjan.ac.th/journal_2568.pdf')";
    $conn->query($sql_insert_default);
}

// --- C. ผู้อำนวยการ (DB) ---
$director_data = [
    'image_file' => 'jansa.png', 
    'name_th' => 'นางจันทร์ษา ชัยวัฒนธีรากร',
    'title_th' => 'ผู้อำนวยการโรงเรียนบ้านสวน (จั่นอนุสรณ์)',
];
$sql_select_director = "SELECT name_th, title_th, image_file FROM director_config WHERE id = 1";
$result_director = $conn->query($sql_select_director);
if ($result_director && $result_director->num_rows > 0) {
    $director_data = $result_director->fetch_assoc();
} 

// -----------------------------------------------------------------
// ** 2. POST HANDLERS (CRUD Operations) **
// -----------------------------------------------------------------

// --- 2.1 จัดการข้อมูลผู้อำนวยการ (Director) ---
if ($is_admin && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['director_action'])) {
    $prefix = 'director_';
    $default_img_name = 'jansa.png'; 
    $current_image_file = $director_data['image_file']; 
    $image_name = $current_image_file; 
    $new_name = $conn->real_escape_string($_POST['director_name']);
    $new_title = $conn->real_escape_string($_POST['director_title']);
    
    if (isset($_FILES[$prefix . "image_file"]) && $_FILES[$prefix . "image_file"]["error"] == 0) {
        $file_info = $_FILES[$prefix . "image_file"];
        $file_extension = pathinfo(basename($file_info["name"]), PATHINFO_EXTENSION);
        $new_filename = $prefix . time() . '.' . $file_extension;
        $target_file = $upload_dir . $new_filename;
        if (getimagesize($file_info["tmp_name"]) !== false) {
            if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }
            if (move_uploaded_file($file_info["tmp_name"], $target_file)) {
                $image_name = $new_filename;
                if ($current_image_file !== $default_img_name && file_exists($upload_dir . $current_image_file)) {
                     unlink($upload_dir . $current_image_file);
                }
            } else { $error = "มีข้อผิดพลาดในการย้ายไฟล์รูปภาพผู้อำนวยการ."; }
        } else { $error = "ไฟล์ที่อัปโหลดไม่ใช่รูปภาพ."; }
    }
    
    if (empty($error)) {
        $sql_upsert_director = "
            INSERT INTO director_config (id, name_th, title_th, image_file) 
            VALUES (1, '$new_name', '$new_title', '$image_name')
            ON DUPLICATE KEY UPDATE 
            name_th = VALUES(name_th), title_th = VALUES(title_th), image_file = VALUES(image_file)
        ";
        if ($conn->query($sql_upsert_director)) {
            $message = "แก้ไขข้อมูลผู้อำนวยการเรียบร้อยแล้ว";
        } else {
            $error = "ไม่สามารถบันทึกข้อมูลผู้อำนวยการในฐานข้อมูลได้: " . $conn->error;
        }
    }
    header("Location: index.php?msg=" . urlencode($message) . "&err=" . urlencode($error));
    exit;
}

// --- 2.2 จัดการคู่มือและวารสาร (Manual/Journal) ---
if ($is_admin && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['config_action'])) {
    $action_type = $_POST['config_action'];
    $link = $conn->real_escape_string($_POST['link_url']);
    $item_id = 0;
    $current_image_data = [];
    $db_table = '';
    $prefix = '';
    $default_img_name = '';

    if ($action_type == 'update_manual') {
        $current_image_data = $manual_data;
        $prefix = 'manual_';
        $default_img_name = 'manual_default.jpg';
        $config_file = 'manual_config.json';
    } elseif ($action_type == 'update_journal') {
        $item_id = $journal_id;
        $current_image_data = $journal_data;
        $db_table = 'journal_config';
        $prefix = 'journal_';
        $default_img_name = 'journal_default.jpg';
    } else {
        $error = "การกระทำไม่ถูกต้อง";
        header("Location: index.php?msg=" . urlencode($message) . "&err=" . urlencode($error));
        exit;
    }

    $image_name = $current_image_data['image_file']; 

    if (isset($_FILES[$prefix . "image_file"]) && $_FILES[$prefix . "image_file"]["error"] == 0) {
        $file_info = $_FILES[$prefix . "image_file"];
        $original_filename = basename($file_info["name"]);
        $file_extension = pathinfo($original_filename, PATHINFO_EXTENSION);
        $new_filename = $prefix . time() . '.' . $file_extension;
        $target_file = $upload_dir . $new_filename;
        
        if (getimagesize($file_info["tmp_name"]) !== false) {
            if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }
            if (move_uploaded_file($file_info["tmp_name"], $target_file)) {
                $image_name = $new_filename;
                $old_file = $current_image_data['image_file'];
                if ($old_file !== $default_img_name && file_exists($upload_dir . $old_file)) {
                     unlink($upload_dir . $old_file);
                }
            } else { $error = "มีข้อผิดพลาดในการย้ายไฟล์รูปภาพ."; }
        } else { $error = "ไฟล์ที่อัปโหลดไม่ใช่รูปภาพ."; }
    }

    if (empty($error)) {
        if ($action_type == 'update_manual') {
             $new_config = $current_image_data;
             $new_config['link_url'] = $link;
             $new_config['image_file'] = $image_name;
            if (file_put_contents($config_file, json_encode($new_config, JSON_PRETTY_PRINT))) {
                $message = "แก้ไขข้อมูลคู่มือเรียบร้อยแล้ว";
            } else { $error = "ไม่สามารถบันทึกไฟล์ manual_config.json ได้"; }
        } elseif ($action_type == 'update_journal') {
             $sql_update = "UPDATE $db_table SET image_file = '$image_name', link_url = '$link' WHERE id = $item_id";
            if ($conn->query($sql_update)) {
                $message = "แก้ไขข้อมูลวารสารเรียบร้อยแล้ว";
            } else {
                $sql_insert = "INSERT IGNORE INTO $db_table (id, image_file, link_url) VALUES (1, 'journal_default.jpg', 'http://www.banjan.ac.th/journal_2568.pdf')";
                if ($conn->query($sql_insert)) { $message = "เพิ่มข้อมูลเริ่มต้นเรียบร้อยแล้ว"; } 
                else { $error = "Error updating config data: " . $conn->error; }
            }
        }
    }
    
    header("Location: index.php?msg=" . urlencode($message) . "&err=" . urlencode($error));
    exit;
}

// --- 2.3 จัดการลิงก์ Navbar Dynamic ---
if ($is_admin && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['navbar_dynamic_action'])) {
    $action = $_POST['navbar_dynamic_action'];
    $id = isset($_POST['link_id']) ? (int)$_POST['link_id'] : 0;
    $parent = isset($_POST['parent_menu']) ? $conn->real_escape_string($_POST['parent_menu']) : '';
    $title = isset($_POST['title_th']) ? $conn->real_escape_string($_POST['title_th']) : '';
    $link = isset($_POST['link_url']) ? $conn->real_escape_string($_POST['link_url']) : '';
    $sort = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;

    if ($action == 'add' || $action == 'edit') {
        if ($id > 0) {
            $sql = "UPDATE navbar_links_dynamic SET parent_menu = '$parent', title_th = '$title', link_url = '$link', sort_order = '$sort' WHERE id = $id";
            $message = "แก้ไขรายการเมนูเรียบร้อยแล้ว";
        } else {
            $sql = "INSERT INTO navbar_links_dynamic (parent_menu, title_th, link_url, sort_order) VALUES ('$parent', '$title', '$link', '$sort')";
            $message = "เพิ่มรายการเมนูใหม่เรียบร้อยแล้ว";
        }
        if (!$conn->query($sql)) { $error = "Error saving link: " . $conn->error; $message = ''; }
    } elseif ($action == 'delete' && $id > 0) {
        $sql = "DELETE FROM navbar_links_dynamic WHERE id = $id";
        if ($conn->query($sql)) { $message = "ลบรายการเมนูเรียบร้อยแล้ว"; } 
        else { $error = "Error deleting link: " . $conn->error; }
    }
    header("Location: index.php?msg=" . urlencode($message) . "&err=" . urlencode($error));
    exit;
}

// --- 2.4 จัดการ ข่าวสารและประกาศ (News) ---
if ($is_admin && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['news_action'])) {
    $action = $_POST['news_action'];
    $news_id = isset($_POST['news_id']) ? (int)$_POST['news_id'] : 0;
    $title = $conn->real_escape_string($_POST['title_th']);
    $description = $conn->real_escape_string($_POST['description_th']);
    $full_content = $conn->real_escape_string($_POST['full_content']);
    $link = empty($_POST['link_url']) ? '' : $conn->real_escape_string($_POST['link_url']);
    $sort = (int)$_POST['sort_order'];
    $news_date = $conn->real_escape_string($_POST['news_date']); // ดึงค่าวันที่
    $new_news_id = 0;

    if ($action == 'add' || $action == 'edit') {
        $files_to_upload = (isset($_FILES['news_images']) && count($_FILES['news_images']['name']) > 0 && !empty($_FILES['news_images']['name'][0]));
        
        if ($news_id > 0) {
            // UPDATED: เพิ่ม created_at = '$news_date'
            $sql = "UPDATE news_items SET 
                    title_th = '$title', description_th = '$description', full_content = '$full_content', 
                    link_url = '$link', sort_order = '$sort', created_at = '$news_date' 
                    WHERE id = $news_id";
            $conn->query($sql);
            $new_news_id = $news_id;
            $message = "แก้ไขรายการข่าวสารเรียบร้อยแล้ว";
        } else {
            // UPDATED: เพิ่ม created_at ใน INSERT
            $sql = "INSERT INTO news_items (title_th, description_th, full_content, sort_order, created_at) 
                    VALUES ('$title', '$description', '$full_content', '$sort', '$news_date')";
            $conn->query($sql);
            $new_news_id = $conn->insert_id;
            
            // ถ้าไม่มี link_url ที่ใส่มาเอง ให้สร้าง link_url เป็น news_detail.php?id=... 
            $news_page_link = empty($link) ? "news_detail.php?id=$new_news_id" : $link; 
            $sql_update_link = "UPDATE news_items SET link_url = '$news_page_link' WHERE id = $new_news_id";
            $conn->query($sql_update_link);

            $message = "เพิ่มรายการข่าวสารใหม่เรียบร้อยแล้ว และสร้างหน้าเว็บใหม่ให้แล้ว";
        }
        
        if ($new_news_id > 0 && $files_to_upload) {
            if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }
            foreach ($_FILES['news_images']['name'] as $key => $name) {
                if ($_FILES['news_images']['error'][$key] == 0 && !empty($name)) {
                    $file_tmp = $_FILES['news_images']['tmp_name'][$key];
                    $file_ext = pathinfo($name, PATHINFO_EXTENSION);
                    $new_filename = 'news_' . $new_news_id . '_' . time() . '_' . rand(100, 999) . '.' . $file_ext;
                    $target_file = $upload_dir . $new_filename;
                    if (getimagesize($file_tmp) !== false) {
                        if (move_uploaded_file($file_tmp, $target_file)) {
                            $sql_img = "INSERT INTO news_gallery (news_id, image_file) VALUES ($new_news_id, '$new_filename')";
                            $conn->query($sql_img);
                        } else { $error .= " | ข้อผิดพลาดในการย้ายรูปภาพ: " . $name; }
                    }
                }
            }
        }
    } elseif ($action == 'delete' && $news_id > 0) {
        // *** แก้ไขการลบ: ตรวจสอบและลบไฟล์จาก Gallery และลบรายการหลัก ***
        
        // 1. ลบไฟล์รูปภาพทั้งหมดใน Gallery
        $sql_files = "SELECT image_file FROM news_gallery WHERE news_id = $news_id";
        $result_files = $conn->query($sql_files);
        if ($result_files) {
            while ($row = $result_files->fetch_assoc()) {
                if (!empty($row['image_file']) && file_exists($upload_dir . $row['image_file'])) { 
                    unlink($upload_dir . $row['image_file']); 
                }
            }
        }
        // 2. ลบรายการ Gallery ใน DB
        $sql_del_gallery = "DELETE FROM news_gallery WHERE news_id = $news_id";
        $conn->query($sql_del_gallery);
        
        // 3. ลบรายการข่าวสารหลัก
        $sql = "DELETE FROM news_items WHERE id = $news_id";
        if ($conn->query($sql)) { 
            $message = "ลบรายการข่าวสารเรียบร้อยแล้ว"; 
        } else { 
            $error = "Error deleting news item: " . $conn->error; 
        }
    } elseif ($action == 'delete_image' && isset($_POST['image_id'])) {
        $image_id = (int)$_POST['image_id'];
        $news_id_for_redirect = (int)$_POST['news_id'];
        $sql_file = "SELECT image_file FROM news_gallery WHERE id = $image_id";
        $result_file = $conn->query($sql_file);
        if ($result_file && $result_file->num_rows > 0) {
            $file_to_delete = $result_file->fetch_assoc()['image_file'];
            if (file_exists($upload_dir . $file_to_delete)) { unlink($upload_dir . $file_to_delete); }
            $sql_del = "DELETE FROM news_gallery WHERE id = $image_id";
            $conn->query($sql_del);
            $message = "ลบรูปภาพเรียบร้อยแล้ว";
        }
        header("Location: index.php?msg=" . urlencode($message) . "&err=" . urlencode($error) . "&edit_news_id=" . $news_id_for_redirect);
        exit;
    }
    header("Location: index.php?msg=" . urlencode($message) . "&err=" . urlencode($error));
    exit;
}

// --- 2.5 จัดการบอร์ดประกาศหลัก (Boards) ---
if ($is_admin && (isset($_GET['delete_id']) || isset($_POST['board_action']))) {
    // Delete Logic
    if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
        $delete_id = $conn->real_escape_string($_GET['delete_id']);
        $sql_file = "SELECT image_file FROM boards WHERE id = '$delete_id'";
        $result_file = $conn->query($sql_file);
        if ($result_file && $result_file->num_rows > 0) {
            $file_to_delete = $upload_dir . $result_file->fetch_assoc()['image_file'];
            if (file_exists($file_to_delete)) { unlink($file_to_delete); }
        }
        $sql_delete = "DELETE FROM boards WHERE id = '$delete_id'";
        if ($conn->query($sql_delete) === TRUE) { $message = "ลบรายการประกาศเรียบร้อยแล้ว"; } 
        else { $error = "Error deleting record: " . $conn->error; }
        
        // ** FIX: เพิ่ม exit; เพื่อป้องกันการประมวลผลต่อในหน้าเดียวกัน **
        header("Location: index.php?msg=" . urlencode($message) . "&err=" . urlencode($error));
        exit;
        
    // Save/Update Logic
    } elseif ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['board_action'])) {
        $link = $conn->real_escape_string($_POST['link_url']);
        $footer = $conn->real_escape_string($_POST['footer_text']);
        $sort_order = (isset($_POST['sort_order']) && is_numeric($_POST['sort_order'])) ? (int)$_POST['sort_order'] : 0;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $board_id = (isset($_POST['board_id']) && is_numeric($_POST['board_id'])) ? (int)$_POST['board_id'] : 0; 
        $image_file_name = NULL;
        
        // Upload Image Logic
        if (isset($_FILES["image_file"]) && $_FILES["image_file"]["error"] == 0) {
            $file_info = $_FILES["image_file"];
            $file_extension = pathinfo(basename($file_info["name"]), PATHINFO_EXTENSION);
            $new_filename = 'board_' . time() . '_' . rand(100, 999) . '.' . $file_extension;
            $target_file = $upload_dir . $new_filename;
            if (getimagesize($file_info["tmp_name"]) !== false) {
                if ($board_id > 0) {
                    $sql_old_file = "SELECT image_file FROM boards WHERE id = '$board_id'";
                    $result_old = $conn->query($sql_old_file);
                    if ($result_old && $result_old->num_rows > 0) {
                        $old_file = $result_old->fetch_assoc()['image_file'];
                        if (file_exists($upload_dir . $old_file)) { unlink($upload_dir . $old_file); }
                    }
                }
                if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }
                if (move_uploaded_file($file_info["tmp_name"], $target_file)) { $image_file_name = $new_filename; } 
                else { $error = "มีข้อผิดพลาดในการย้ายไฟล์รูปภาพ."; }
            } else { $error = "ไฟล์ที่อัปโหลดไม่ใช่รูปภาพ."; }
        }
        
        // Save/Update DB
        if (empty($error)) {
            if ($board_id > 0) {
                $update_image = $image_file_name ? ", image_file = '$image_file_name'" : "";
                $sql_update = "UPDATE boards SET title_th = '', subtitle_th = '', link_url = '$link', footer_text = '$footer', sort_order = '$sort_order', is_active = '$is_active' $update_image WHERE id = '$board_id'";
                if ($conn->query($sql_update) === TRUE) { $message = "แก้ไขรายการประกาศ #$board_id เรียบร้อยแล้ว"; } 
                else { $error = "Error updating record: " . $conn->error; }
            } else {
                if ($image_file_name) {
                    $sql_insert = "INSERT INTO boards (title_th, subtitle_th, image_file, link_url, footer_text, sort_order, is_active) VALUES ('', '', '$image_file_name', '$link', '$footer', '$sort_order', '$is_active')";
                    if ($conn->query($sql_insert) === TRUE) { $message = "เพิ่มรายการประกาศใหม่เรียบร้อยแล้ว"; } 
                    else { $error = "Error: " . $conn->error; }
                } else { $error = "กรุณาเลือกไฟล์รูปภาพสำหรับการเพิ่มรายการใหม่."; }
            }
        }
    }
    header("Location: index.php?msg=" . urlencode($message) . "&err=" . urlencode($error));
    exit;
}

// --- 2.6 จัดการลิงก์ด่วน (Quick Links) ---
if ($is_admin && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['quick_link_action'])) {
    $action = $_POST['quick_link_action'];
    $id = isset($_POST['quick_link_id']) ? (int)$_POST['quick_link_id'] : 0;
    $title = isset($_POST['title']) ? $conn->real_escape_string($_POST['title']) : '';
    $link = isset($_POST['link_url']) ? $conn->real_escape_string($_POST['link_url']) : '';
    $icon = isset($_POST['icon']) ? $conn->real_escape_string($_POST['icon']) : '';
    $sort = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;

    if ($action == 'add' && $title && $link && $icon) {
        $sql = "INSERT INTO quick_links (title, link_url, icon, sort_order) VALUES ('$title', '$link', '$icon', $sort)";
        if ($conn->query($sql)) { $message = "เพิ่มลิงก์ด่วนใหม่สำเร็จ"; } 
        else { $error = "Error adding quick link: " . $conn->error; }
    } elseif ($action == 'edit' && $id > 0 && $title && $link && $icon) {
        $sql = "UPDATE quick_links SET title = '$title', link_url = '$link', icon = '$icon', sort_order = $sort WHERE id = $id";
        if ($conn->query($sql)) { $message = "แก้ไขลิงก์ด่วนสำเร็จ"; } 
        else { $error = "Error editing quick link: " . $conn->error; }
    } elseif ($action == 'delete' && $id > 0) {
        $sql = "DELETE FROM quick_links WHERE id = $id";
        if ($conn->query($sql)) { $message = "ลบลิงก์ด่วนสำเร็จ"; } 
        else { $error = "Error deleting quick link: " . $conn->error; }
    }
    header("Location: index.php?msg=" . urlencode($message) . "&err=" . urlencode($error));
    exit;
}

// --- 2.7 จัดการรูปภาพเกี่ยวกับโรงเรียน (Mission, Vision, etc.) ---
// *** โค้ดแก้ไข: ใช้ POST Handler เดียวกันสำหรับรายการทั้งหมดใน about_us_config *** (ห้ามแก้ไข Logic ภายใน)
if ($is_admin && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['about_us_action'])) {
    $item_key = $conn->real_escape_string($_POST['item_key']);
    // ตรวจสอบว่ามี item_key อยู่ในชุดข้อมูลเริ่มต้นหรือไม่
    if (!isset($about_us_data[$item_key])) {
         $error = "รายการ About Us ไม่ถูกต้อง.";
         header("Location: index.php?msg=" . urlencode($message) . "&err=" . urlencode($error));
         exit;
    }
    
    $current_data = $about_us_data[$item_key];
    $current_image_file = $current_data['image_file'];
    // 💡 title_th ถูกใช้เก็บ URL PDF
    $title_th = $conn->real_escape_string($_POST['title_th']); 
    $image_name = $current_image_file;
    $prefix = $item_key . '_'; 
    $item_id = $current_data['id'];

    // ❌ ช่องอัพโหลดรูปภาพถูกลบออกไปแล้ว
    
    if (empty($error)) {
        // Upsert (Insert/Update)
        // 💡 เราใช้ title_th เป็นตัวเก็บ URL PDF แทน
        $sql_upsert_about = "
            INSERT INTO about_us_config (id, item_key, title_th, image_file) 
            VALUES ($item_id, '$item_key', '$title_th', '$image_name')
            ON DUPLICATE KEY UPDATE 
            title_th = VALUES(title_th)
        ";
        if ($conn->query($sql_upsert_about)) {
            // 💡 เราใช้ชื่อจริงที่ถูก Hardcode ไว้แสดงในข้อความแจ้งเตือนแทน
            $message = "แก้ไข URL {$current_data['title']} เรียบร้อยแล้ว"; 
        } else {
            $error = "ไม่สามารถบันทึกข้อมูลในฐานข้อมูลได้: " . $conn->error;
        }
    }
    header("Location: index.php?msg=" . urlencode($message) . "&err=" . urlencode($error));
    exit;
}
// *** สิ้นสุดโค้ดแก้ไข ***


// -----------------------------------------------------------------
// ** 3. DATA FOR DISPLAY ** (ห้ามแก้ไข Logic ภายใน)
// -----------------------------------------------------------------

// --- A. Navbar Links (Dynamic) ---
$navbar_links_dynamic = [];
$sql_navbar = "SELECT * FROM navbar_links_dynamic ORDER BY parent_menu ASC, sort_order ASC, id ASC";
$result_navbar = $conn->query($sql_navbar);
if ($result_navbar && $result_navbar->num_rows > 0) {
    while($row = $result_navbar->fetch_assoc()) {
        $navbar_links_dynamic[$row['parent_menu']][] = $row;
    }
} 

// --- B. News Items (with Gallery) ---
$news_items_list = [];
// ** FIX: แก้ไขการเรียงลำดับให้ created_at DESC (ล่าสุดขึ้นก่อน) เป็นอันดับแรก **
$sql_news = "SELECT * FROM news_items ORDER BY created_at DESC, sort_order ASC LIMIT 6";
$result_news = $conn->query($sql_news);
if ($result_news && $result_news->num_rows > 0) {
    while($row = $result_news->fetch_assoc()) {
        $sql_gallery = "SELECT id, image_file FROM news_gallery WHERE news_id = {$row['id']} ORDER BY id ASC";
        $result_gallery = $conn->query($sql_gallery);
        $row['gallery'] = [];
        if ($result_gallery) {
            while ($img = $result_gallery->fetch_assoc()) {
                $row['gallery'][] = $img;
            }
        }
        $news_items_list[] = $row;
    }
}


// --- C. All Boards (for Admin Modal) ---
$all_boards_list = [];
if ($is_admin) {
    $sql_all = "SELECT * FROM boards ORDER BY sort_order ASC, id DESC";
    $result_all = $conn->query($sql_all);
    if ($result_all && $result_all->num_rows > 0) {
        while($row = $result_all->fetch_assoc()) {
            $all_boards_list[] = $row;
        }
    }
}

// --- D. Main Boards (Active for Carousel) ---
$main_boards = [];
$sql = "SELECT id, title_th, subtitle_th, image_file, link_url, footer_text FROM boards WHERE is_active = 1 ORDER BY sort_order ASC, id DESC LIMIT 5";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $main_boards[] = $row;
    }
} else {
    // Default Fallback
    $main_boards = [
        ['title_th' => '', 'subtitle_th' => '', 'image_file' => 'default_reg.jpg', 'link_url' => '#', 'footer_text' => 'คลิกเพื่อดูรายละเอียด'], 
        ['title_th' => '', 'subtitle_th' => '', 'image_file' => 'default_bid.jpg', 'link_url' => '#', 'footer_text' => 'รายละเอียดเพิ่มเติม'] 
    ];
}

// --- E. Quick Links (DB or Mock Data) ---
$quick_links_db = [];
$sql_quick_links = "SELECT id, title, link_url, icon, sort_order FROM quick_links ORDER BY sort_order ASC, id ASC";
$result_quick_links = $conn->query($sql_quick_links);

if ($result_quick_links && $result_quick_links->num_rows > 0) {
    while($row = $result_quick_links->fetch_assoc()) {
        $quick_links_db[] = $row;
    }
} 
// ใช้ Mock Data เดิมเป็น Fallback หากตารางว่างหรือยังไม่มีข้อมูล
$quick_links_right_fallback = [
    ["title" => "เว็บไซต์ สพม.18", "link_url" => "https://www.spmcr.go.th/", "icon" => "fa-globe"],
    ["title" => "TO BE NUMBER ONE", "link_url" => "https://www.facebook.com/TOBEBSJ", "icon" => "fa-hand-holding-heart"],
    ["title" => "ITA ONLINE", "link_url" => "https://sites.google.com/pracharath.ac.th/1020080312-bansuanjananusorn/%E0%B8%AB%E0%B8%99%E0%B8%B2%E0%B9%81%E0%B8%A3%E0%B8%81?authuser=3", "icon" => "fa-chart-line"],
];
$quick_links_final = !empty($quick_links_db) ? $quick_links_db : $quick_links_right_fallback;

// Close DB Connection
$conn->close();
// -----------------------------------------------------------------

// Other static data
$school_name_th = "โรงเรียนบ้านสวน (จั่นอนุสรณ์)";
$school_name_en = "Bansuan Jananusorn School";
$website_url = "http://www.banjan.ac.th";
$motto = "Bansuan Jananusorn School"; 
$academic_groups = [
    ["name" => "ภาษาไทย", "link" => "https://course.byethost17.com/thai_staff.php"],
    ["name" => "คณิตศาสตร์", "link" => "https://course.byethost17.com/math_staff.php"],
    ["name" => "วิทยาศาสตร์และเทคโนโลยี", "link" => "https://course.byethost17.com/science_staff.php"],
    ["name" => "สังคมศึกษา ศาสนาและวัฒนธรรม", "https://course.byethost17.com/social_staff.php"],
    ["name" => "สุขศึกษาและพลศึกษา", "link" => "https://course.byethost17.com/pe_staff.php"],
    ["name" => "การงานอาชีพ", "link" => "https://course.byethost17.com/career_staff.php"],
    ["name" => "ภาษาต่างประเทศ", "link" => "https://course.byethost17.com/foreign_staff.php"],
    ["name" => "ศิลปะ ดนตรี นาฏศิลป์", "link" => "https://course.byethost17.com/art_staff.php"],
    

];
// *** แก้ไข Static Mock Data ให้ครบถ้วนตามความต้องการของคุณ ***
$executive_members = [
    ["name" => "คณะผู้อำนวยการ", "link" => "https://course.byethost17.com/executive_staff.php"],
    ["name" => "กลุ่มบริหารงานวิชาการ", "link" => "https://bsjacademic.wolf89.com/"],
    ["name" => "กลุ่มบริหารงานงบประมาณ", "link" => "#"], 
    ["name" => "กลุ่มบริหารงานบุคคล", "link" => "https://sites.google.com/banjan.ac.th/bsj-personal"],
    ["name" => "กลุ่มบริหารงานทั่วไป", "link" => "#"],
];
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $school_name_th; ?> | <?php echo $school_name_en; ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;600;700&display=swap">
    
    <link rel="stylesheet" href="style.css"> 
    <style>
        /* CSS จากคำตอบก่อนหน้า */
        :root {
            --pink-primary: #FF69B4; 
            --dark-secondary: #2c333a; 
            --light-bg: #F8F9FA;
            --text-light: #F0F0F0;
        }
        
        .admin-modal-section {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #e9ecef;
        }
        .admin-modal-section h6 {
            font-weight: 600;
            color: #495057;
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
        .btn-edit-dynamic, .btn-edit-news {
            white-space: nowrap;
        }
        .director-photo {
            width: 180px; 
            height: 225px; 
            object-fit: cover;
            border-radius: 8px; 
            border: none; 
            margin-bottom: 15px;
        }
        .news-thumbnail {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 10px;
            border: 1px solid #ddd;
        }
        
        #newsEditModal .modal-body,
        #dynamicLinkModal .modal-body,
        #newsViewModal .modal-body,
        #quickLinkModal .modal-body { 
            max-height: calc(100vh - 180px) !important;
            overflow-y: auto !important; 
            overflow-x: hidden; 
        }

        @media (max-width: 576px) {
            #newsEditModal .modal-body,
            #dynamicLinkModal .modal-body,
            #newsViewModal .modal-body,
            #quickLinkModal .modal-body { 
                max-height: 75vh !important; 
            }
        }
        
        .bg-pink-primary {
            background-color: var(--pink-primary) !important; 
        }
        .text-pink-primary {
            color: var(--pink-primary) !important;
        }
        #newsViewModal .news-full-content-area p {
            line-height: 1.8;
            font-size: 1.1rem;
            color: var(--dark-secondary);
            text-align: justify; 
        }
        #newsViewModal .news-gallery-image {
            width: 100%;
            height: 250px;
            object-fit: cover; 
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        #newsViewModal .news-gallery-image:hover {
            transform: scale(1.02);
        }
        
        .news-item-card .btn-danger,
        .news-item-card .btn-warning {
            min-width: 70px; 
            width: auto !important;
            white-space: nowrap !important; 
            padding: .25rem .5rem !important; 
        }
        .news-item-card .float-end {
            justify-content: flex-end; 
        }
        
        .support-groups-panel h4 {
            font-size: 1.3rem;
            font-weight: 600;
            color: #FFA500; 
            border-bottom: 2px solid #FFD700;
            padding-bottom: 8px;
            margin-bottom: 15px;
        }
        .support-groups-panel .list-group {
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
        }

        .btn-support {
            display: block;
            width: 100%;
            padding: 10px 15px;
            margin-bottom: 4px;
            color: var(--dark-secondary);
            background-color: #FFFACD; 
            border: none;
            border-radius: 6px;
            transition: background-color 0.3s, color 0.3s;
            font-weight: 500;
        }

        .btn-support:hover {
            background-color: #FFA500; 
            color: white;
            text-decoration: none;
        }
        .btn-support .fas {
            color: #FF8C00; 
            min-width: 20px;
        }
        .btn-support:hover .fas {
            color: white;
        }
        
        .about-school-panel h4 {
            font-size: 1.3rem;
            font-weight: 600;
            color: #008080; 
            border-bottom: 2px solid #48D1CC;
            padding-bottom: 8px;
            margin-bottom: 15px;
        }

        .btn-about {
            display: block;
            width: 100%;
            padding: 10px 15px;
            margin-bottom: 4px;
            color: var(--dark-secondary);
            background-color: #E0FFFF; 
            border: none;
            border-radius: 6px;
            transition: background-color 0.3s, color 0.3s;
            font-weight: 500;
        }

        .btn-about:hover {
            background-color: #008080; 
            color: white;
            text-decoration: none;
        }
        .btn-about .fas {
            color: #00CED1; 
            min-width: 20px;
        }
        .btn-about:hover .fas {
            color: white;
        }

        /* Generic About Us Modal Styles */
        #AboutUsModal .modal-content {
            border-radius: 12px; 
            border: 3px solid var(--pink-primary); 
            box-shadow: 0 4px 15px rgba(255, 105, 180, 0.4); 
        }
        
        .modal-about-header {
            background-color: var(--pink-primary); 
            color: white;
            position: relative;
            padding-bottom: 40px !important; 
            border-top-left-radius: 9px; 
            border-top-right-radius: 9px;
            border-bottom: none;
        }
        
        .modal-about-logo {
            position: absolute;
            top: 10px; 
            left: 50%;
            transform: translateX(-50%);
            height: 90px; 
            width: auto;
            z-index: 1050;
        }
        
        .modal-about-content {
            background-color: white;
            color: var(--dark-secondary);
            padding: 30px;
            margin-top: 25px; 
        }
        .about-image-preview {
            max-width: 100%;
            height: auto;
            max-height: 250px;
            object-fit: contain;
            border: 1px solid #ddd;
            padding: 5px;
            border-radius: 5px;
        }
        
        /* 💡 NEW CSS: สไตล์สำหรับ iframe แสดง PDF */
        #AboutUsModal .modal-pdf-iframe {
            width: 100%;
            height: 600px; /* กำหนดความสูงที่ต้องการให้แสดงผล PDF */
            border: none;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        
    </style>
</head>
<body>

<div class="marquee-bar">
    <div class="container-fluid">
        <div class="marquee-content">
            <i class="fas fa-bullhorn me-2"></i> :: ยินดีต้อนรับสู่เว็บไซต์<?php echo $school_name_th; ?> :: Welcome to <?php echo $school_name_en; ?> :: <a href="<?php echo $website_url; ?>" class="text-white text-decoration-underline"><?php echo $website_url; ?></a> :: ขอให้ทุกท่านมีความสุขกับการเยี่ยมชมเว็บไซต์ของเราค่ะ ::
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
                <li class="nav-item"><a class="nav-link active" href="index.php">หน้าแรก</a></li>
                
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownStudent" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        สำหรับนักเรียน
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="navbarDropdownStudent">
                        <?php 
                        if (isset($navbar_links_dynamic['student'])):
                            foreach ($navbar_links_dynamic['student'] as $item): 
                        ?>
                        <li><a class="dropdown-item" href="<?php echo htmlspecialchars($item['link_url']); ?>"><?php echo htmlspecialchars($item['title_th']); ?></a></li>
                        <?php 
                            endforeach; 
                        endif;
                        ?>
                    </ul>
                </li>
                
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownAbout" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        ข้อมูลโรงเรียน
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="navbarDropdownAbout">
                        <?php 
                        if (isset($navbar_links_dynamic['about'])):
                            foreach ($navbar_links_dynamic['about'] as $item): 
                        ?>
                        <li><a class="dropdown-item" href="<?php echo htmlspecialchars($item['link_url']); ?>"><?php echo htmlspecialchars($item['title_th']); ?></a></li>
                        <?php 
                            endforeach; 
                        endif;
                        ?>
                    </ul>
                </li>

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownAcademic" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        กลุ่มสาระ
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="navbarDropdownAcademic">
                        <?php 
                        foreach ($academic_groups as $group): 
                        ?>
                        <li><a class="dropdown-item" href="<?php echo $group['link']; ?>"><?php echo $group['name']; ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </li>
                
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownExecutive" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        กลุ่มบริหาร
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="navbarDropdownExecutive">
                        <?php 
                        $menu_items = isset($navbar_links_dynamic['executive']) ? $navbar_links_dynamic['executive'] : $executive_members;
                        foreach ($menu_items as $item): 
                        ?>
                        <li><a class="dropdown-item" href="<?php echo htmlspecialchars($item['link_url'] ?? $item['link']); ?>"><?php echo htmlspecialchars($item['title_th'] ?? $item['name']); ?></a></li>
                        <?php 
                        endforeach; 
                        ?>
                    </ul>
                </li>
                
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownGallery" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        แกลเลอรี่กิจกรรม
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="navbarDropdownGallery">
                        <?php 
                        if (isset($navbar_links_dynamic['gallery'])):
                            foreach ($navbar_links_dynamic['gallery'] as $item): 
                        ?>
                        <li><a class="dropdown-item" href="<?php echo htmlspecialchars($item['link_url']); ?>"><?php echo htmlspecialchars($item['title_th']); ?></a></li>
                        <?php 
                            endforeach; 
                        endif;
                        ?>
                    </ul>
                </li>

                <li class="nav-item"><a class="nav-link" href="contact.php">ติดต่อเรา</a></li>
                <li class="nav-item"><a class="nav-link btn btn-pink text-white ms-lg-3" href="admission.php">สมัครเรียน</a></li>
            </ul>
            
            <?php if ($is_admin): ?>
            <button type="button" class="btn btn-sm btn-info ms-3" data-bs-toggle="modal" data-bs-target="#dynamicLinkModal" style="margin-right: 10px;">
                <i class="fas fa-link me-1"></i> จัดการเมนูย่อย
            </button>
            
            <a href="logout.php" class="btn btn-sm btn-danger ms-2">
                <i class="fas fa-sign-out-alt me-1"></i> ออกจากระบบ
            </a>
            
            <?php else: ?>
            <a href="login.php" class="btn btn-sm btn-outline-light ms-3">
                <i class="fas fa-sign-in-alt me-1"></i> เข้าสู่ระบบ
            </a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<header class="hero-section text-center">
    <div class="container">
        <h1 class="display-2 mb-3">โรงเรียนบ้านสวน (จั่นอนุสรณ์)</h1>
        <p class="lead mb-4">
            "<?php echo $motto; ?>"
        </p>
       
    </div>
</header>

<main class="container my-5">
    
    <?php 
    // ** แสดงข้อความสถานะสำหรับ Admin เท่านั้น **
    if ($is_admin) : ?>
        <?php if (!empty($message)): ?>
            <div class="alert alert-success text-center"><?php echo $message; ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger text-center"><?php echo $error; ?></div>
        <?php endif; ?>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-lg-3 mb-4">
            <div class="director-section text-center mb-4 rounded-3 p-4">
                <h3 class="mb-3">ผู้อำนวยการ</h3>
                
                <?php if ($is_admin): ?>
                <button type="button" class="btn btn-sm btn-info btn-manual-edit mb-3" 
                        data-bs-toggle="modal" 
                        data-bs-target="#directorEditModal"
                        data-name="<?php echo htmlspecialchars($director_data['name_th']); ?>"
                        data-title="<?php echo htmlspecialchars($director_data['title_th']); ?>"
                        data-image="<?php echo htmlspecialchars($director_data['image_file']); ?>">
                    <i class="fas fa-user-edit me-1"></i> แก้ไขข้อมูล
                </button>
                <?php endif; ?>
                
                <img src="<?php echo $upload_dir . $director_data['image_file']; ?>" alt="รูปผู้อำนวยการ" class="director-photo">
                
                <p class="fw-bold mb-1"><?php echo htmlspecialchars($director_data['name_th']); ?></p> 
                <p class="small fw-bold"><?php echo htmlspecialchars($director_data['title_th']); ?></p> 
                
            </div>

            <div class="executive-groups-panel mb-4">
                <h4><i class="fas fa-users-cog me-2"></i> คณะผู้บริหาร</h4>
                <div class="list-group">
                    <?php 
                    // ตรวจสอบ: ถ้าไม่มี Dynamic Link ให้ใช้ Mock Data ทั้งหมด 5 รายการ
                    $group_items = $executive_members;
                    
                    // กำหนดไอคอนสำหรับ Mock Data
                    $icon_map = [
                        'คณะผู้อำนวยการ' => 'fa-user-tie',
                        'กลุ่มบริหารงานวิชาการ' => 'fa-book-reader',
                        'กลุ่มบริหารงานงบประมาณ' => 'fa-calculator',
                        'กลุ่มบริหารงานบุคคล' => 'fa-users',
                        'กลุ่มบริหารงานทั่วไป' => 'fa-building',
                        // ชื่ออื่น ๆ (เพื่อความยืดหยุ่น)
                        'ฝ่ายวิชาการ' => 'fa-book-reader',
                        'ฝ่ายบุคคล' => 'fa-users',
                        'ฝ่ายบริหารทั่วไป' => 'fa-building',
                        'ฝ่ายแผนงาน' => 'fa-chart-pie',
                    ];

                    foreach ($group_items as $member): 
                        $name = $member['title_th'] ?? $member['name'];
                        $link = $member['link_url'] ?? $member['link'];
                        
                        // เลือกไอคอนที่เหมาะสม
                        $icon_class = $member['icon'] ?? ($icon_map[$name] ?? 'fa-user-tie');
                    ?>
                        <a href="<?php echo $link; ?>" class="btn btn-executive text-start">
                            <i class="fas <?php echo $icon_class; ?> me-2"></i> <?php echo $name; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="academic-groups-panel mb-4">
                <h4><i class="fas fa-book-open me-2"></i> กลุ่มสาระการเรียนรู้</h4>
                <div class="list-group">
                    <?php foreach ($academic_groups as $group): ?>
                        <a href="<?php echo $group['link']; ?>" class="btn btn-academic text-start">
                            <i class="fas fa-graduation-cap me-2"></i> <?php echo $group['name']; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="support-groups-panel mb-4">
                <h4><i class="fas fa-handshake me-2"></i> สนับสนุนการศึกษา</h4>
                <div class="list-group">
                    <?php 
                    $support_groups = [
                        ["name" => "งานแนะแนว", "link" => "http://127.0.0.1/guidance_staff.php"],                        
                        ["name" => "สนับสนุน/ภารโรง", "link" => "#"], 
                        
                    ];
                    
                    foreach ($support_groups as $group): 
                        $icon_class = 'fa-cogs'; // Default icon
                        if ($group['name'] == 'งานแนะแนว') $icon_class = 'fa-compass';
                        if ($group['name'] == 'สนับสนุน/ภารโรง') $icon_class = 'fa-tools';
                    ?>
                        <a href="<?php echo $group['link']; ?>" class="btn btn-support text-start">
                            <i class="fas <?php echo $icon_class; ?> me-2"></i> <?php echo $group['name']; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            </div>

        <div class="col-lg-6 mb-4">
            
            <div class="announcement-board-lg mb-4 carousel slide" id="mainBoardCarousel" data-bs-ride="carousel" data-bs-interval="5000"> 
                <div class="board-header text-center">
                    <img src="janLogo.png" alt="Logo" class="board-logo">
                    <h2 class="board-title">บอกเล่าชาว จ.อ.</h2>
                    
                    <?php if ($is_admin): ?>
                    <button type="button" class="btn btn-sm btn-warning btn-board-admin" 
                            data-bs-toggle="modal" 
                            data-bs-target="#editModal">
                        <i class="fas fa-edit me-1"></i> แก้ไข/เพิ่มบอร์ด
                    </button>
                    <?php endif; ?>
                    
                </div>
                
                <div class="carousel-indicators">
                    <?php 
                    if (!empty($main_boards)):
                        foreach ($main_boards as $index => $board): 
                    ?>
                        <button type="button" data-bs-target="#mainBoardCarousel" data-bs-slide-to="<?php echo $index; ?>" 
                                class="<?php echo ($index == 0) ? 'active' : ''; ?>" 
                                aria-current="<?php echo ($index == 0) ? 'true' : 'false'; ?>" 
                                aria-label="Slide <?php echo $index + 1; ?>"></button>
                    <?php 
                        endforeach; 
                    endif;
                    ?>
                </div>

                <div class="carousel-inner">
                    
                    <?php 
                    $is_first = true;
                    if (!empty($main_boards)):
                        foreach ($main_boards as $index => $board): 
                    ?>
                    <div class="carousel-item <?php echo ($is_first) ? 'active' : ''; ?>">
                        <div class="card board-item border-0 shadow-lg mt-0">
                            <div class="card-body p-0">
                                <a href="<?php echo $board['link_url']; ?>" target="_blank">
                                    <div class="board-image-area" style="background-image: url('uploads/<?php echo $board['image_file']; ?>');">
                                        </div>
                                </a>
                            </div>
                            <div class="board-footer-link text-center p-2">
                                <a href="<?php echo htmlspecialchars($board['link_url']); ?>" target="_blank" class="fw-bold">
                                    <?php echo htmlspecialchars($board['footer_text']); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php 
                        $is_first = false;
                        endforeach; 
                    else: 
                    ?>
                            <div class="carousel-item active">
                                <div class="text-center p-5 text-muted">ไม่มีรายการประกาศที่เปิดใช้งานในฐานข้อมูล</div>
                            </div>
                    <?php endif; ?>
                </div>
                
                <button class="carousel-control-prev" type="button" data-bs-target="#mainBoardCarousel" data-bs-slide="prev" style="z-index: 10; width: 10%;">
                    <span class="carousel-control-prev-icon" aria-hidden="true" style="background-color: rgba(0,0,0,0.3); border-radius: 50%; padding: 20px;"></span>
                    <span class="visually-hidden">Previous</span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#mainBoardCarousel" data-bs-slide="next" style="z-index: 10; width: 10%;">
                    <span class="carousel-control-next-icon" aria-hidden="true" style="background-color: rgba(0,0,0,0.3); border-radius: 50%; padding: 20px;"></span>
                    <span class="visually-hidden">Next</span>
                </button>

            </div>
            <h3 class="mb-3"><i class="fas fa-newspaper me-2 text-pink-primary"></i> ข่าวสารและประกาศ</h3>
            
            <?php if ($is_admin): ?>
            <button type="button" class="btn btn-success mb-3 w-100" data-bs-toggle="modal" data-bs-target="#newsEditModal" data-news-id="0" id="btn-add-news">
                <i class="fas fa-plus-circle me-1"></i> เพิ่มรายการข่าวสารใหม่
            </button>
            <?php endif; ?>

           <div class="news-list-container">
    <?php if (!empty($news_items_list)): ?>
        <?php foreach ($news_items_list as $news): 
            // ตรวจสอบว่าลิงก์ที่ใส่มาเป็นลิงก์ภายนอกหรือไม่ (ไม่ใช่ news_detail.php)
            $has_external_link = !empty($news['link_url']) && strpos($news['link_url'], 'news_detail.php') === false;
        ?>
        <div class="card news-item-card mb-3 shadow-sm">
            <div class="card-body p-3">
                <div class="row g-3">
                    
                    <div class="col-auto">
                        <button type="button" class="btn p-0 border-0 btn-read-more" data-news-id="<?php echo htmlspecialchars($news['id']); ?>">
                            <img src="<?php echo $upload_dir . htmlspecialchars($news['gallery'][0]['image_file'] ?? 'placeholder.jpg'); ?>" 
                                alt="<?php echo htmlspecialchars($news['title_th']); ?>" 
                                class="news-thumbnail">
                        </button>
                    </div>
                    
                    <div class="col">
                        <h5 class="news-title-link mb-1">
                            <button type="button" class="btn p-0 border-0 text-start text-dark fw-bold btn-read-more" data-news-id="<?php echo htmlspecialchars($news['id']); ?>" style="font-size: 1.1rem;">
                                <?php echo htmlspecialchars($news['title_th']); ?>
                            </button>
                        </h5>
                        <p class="text-muted small mb-1"><?php echo htmlspecialchars($news['description_th']); ?></p>
                        <span class="text-secondary small me-3"><i class="fas fa-calendar-alt me-1"></i> <?php echo formatDateThai($news['created_at']); ?></span>
                        
                        <?php if ($is_admin): ?>
                        <div class="d-flex float-end">
                            <button type="button" class="btn btn-sm btn-warning me-2 btn-edit-news d-flex align-items-center" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#newsEditModal"
                                    data-news-id="<?php echo $news['id']; ?>"
                                    data-title="<?php echo htmlspecialchars($news['title_th']); ?>"
                                    data-description="<?php echo htmlspecialchars($news['description_th']); ?>"
                                    data-link="<?php echo htmlspecialchars($news['link_url']); ?>"
                                    data-sort="<?php echo $news['sort_order']; ?>">
                                <i class="fas fa-pencil-alt me-1"></i> แก้ไข
                            </button>
                            <button type="button" class="btn btn-sm btn-danger btn-delete-confirm d-flex align-items-center" 
                                    data-bs-toggle="modal"
                                    data-bs-target="#deleteNewsConfirmModal"
                                    data-id="<?php echo $news['id']; ?>"
                                    data-title="<?php echo htmlspecialchars($news['title_th']); ?>">
                                <i class="fas fa-trash me-1"></i> ลบ
                            </button>
                        </div>
                        <?php endif; ?>
                        
                        <button type="button" class="btn btn-sm btn-outline-primary mt-2 btn-read-more" data-news-id="<?php echo htmlspecialchars($news['id']); ?>">
                            <i class="fas fa-arrow-right me-1"></i> อ่านต่อ
                            <?php if($has_external_link): ?>
                                
                            <?php endif; ?>
                        </button>

                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p class="text-center text-muted p-4">ยังไม่มีรายการข่าวสาร</p>
    <?php endif; ?>
</div>

            
        </div>

        <div class="col-lg-3 mb-4">
            
            <div class="quick-links-panel">
                <h4><i class="fas fa-link me-2"></i> ลิงก์หน่วยงาน</h4>
                <?php if ($is_admin): ?>
                <button type="button" class="btn btn-sm btn-outline-secondary w-100 mb-3" data-bs-toggle="modal" data-bs-target="#quickLinkModal">
                    <i class="fas fa-tools me-1"></i> จัดการลิงก์ด่วน
                </button>
                <?php endif; ?>
                <div class="list-group">
                    <?php foreach ($quick_links_final as $link): ?>
                        <a href="<?php echo $link['link_url']; ?>" target="_blank" class="list-group-item list-group-item-action d-flex align-items-center">
                            <i class="fas <?php echo $link['icon']; ?>"></i> <?php echo $link['title']; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="about-school-panel mt-4">
                <h4><i class="fas fa-info-circle me-2"></i> เกี่ยวกับโรงเรียน</h4>
                <div class="list-group">
                    <?php 
                    // ใช้ $about_us_data ที่ดึงมาจาก DB แล้ว
                    $about_groups = [
                        ['key' => 'mission', 'icon' => 'fa-bullseye'],
                        ['key' => 'vision', 'icon' => 'fa-eye'],
                        ['key' => 'intro', 'icon' => 'fa-school'],
                        ['key' => 'charac', 'icon' => 'fa-heart'],
                        ['key' => 'map', 'icon' => 'fa-map-marked-alt'],
                        ['key' => 'school_plan', 'icon' => 'fa-sitemap'],
                    ];
                    
                    foreach ($about_groups as $group): 
                        $key = $group['key'];
                        $item_data = $about_us_data[$key];
                    ?>
                        <a href="#" 
                           class="btn btn-about text-start btn-about-us-view"
                           data-bs-toggle="modal" 
                           data-bs-target="#AboutUsModal"
                           data-item-key="<?php echo $key; ?>"
                           data-item-title="<?php echo htmlspecialchars($item_data['title']); ?>"
                           
                           data-item-image="<?php echo $upload_dir . htmlspecialchars($item_data['image_file']); ?>" 
                           data-item-pdf-url="<?php echo htmlspecialchars($item_data['link_url_pdf']); ?>"
                           
                           data-item-edit-id="#AboutUsEditModal">
                            <i class="fas <?php echo $group['icon']; ?> me-2"></i> <?php echo htmlspecialchars($item_data['title']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="manual-panel mt-4">
                <h4 class="text-center mb-3">คู่มือนักเรียน</h4>
                <a href="<?php echo $manual_data['link_url']; ?>" target="_blank" class="manual-link">
                    <img id="manual-display-image" src="<?php echo $upload_dir . htmlspecialchars($manual_data['image_file']); ?>" 
                            alt="<?php echo htmlspecialchars($manual_data['title']); ?>" 
                            class="manual-image">
                </a>
                <?php if ($is_admin): ?>
                <button type="button" class="btn btn-sm btn-info btn-manual-edit mt-2 w-100" 
                        data-bs-toggle="modal" 
                        data-bs-target="#manualEditModal"
                        data-link="<?php echo htmlspecialchars($manual_data['link_url']); ?>"
                        data-image="<?php echo htmlspecialchars($manual_data['image_file']); ?>">
                    <i class="fas fa-tools me-1"></i> แก้ไขคู่มือ
                </button>
                <?php endif; ?>
            </div>
            <div class="journal-panel mt-4">
                <h4 class="text-center mb-3">วารสารโรงเรียน</h4>
                <a href="<?php echo $journal_data['link_url']; ?>" target="_blank" class="journal-link">
                    <img id="journal-display-image" src="<?php echo $upload_dir . htmlspecialchars($journal_data['image_file']); ?>" 
                            alt="<?php echo htmlspecialchars($journal_data['title']); ?>" 
                            class="journal-image">
                </a>
                <?php if ($is_admin): ?>
                <button type="button" class="btn btn-sm btn-success btn-journal-edit mt-2 w-100" 
                        data-bs-toggle="modal" 
                        data-bs-target="#journalEditModal"
                        data-link="<?php echo htmlspecialchars($journal_data['link_url']); ?>"
                        data-image="<?php echo htmlspecialchars($journal_data['image_file']); ?>">
                    <i class="fas fa-tools me-1"></i> แก้ไขวารสาร
                </button>
                <?php endif; ?>
            </div>
            </div>
    </div>
</main>

<footer>
    <div class="container">
        <div class="row">
            <div class="col-md-4 mb-4">
                <h5><i class="fas fa-graduation-cap me-2"></i> <?php echo $school_name_th; ?></h5>
                <p class="small">ก้าวไปข้างหน้าอย่างมั่นคง มุ่งสู่ความเป็นเลิศทางการศึกษา</p>
                <div class="social-icons mt-3">
                    <a href="https://www.facebook.com/profile.php?id=100057645151857" class="me-3"><i class="fab fa-facebook-square"></i></a>
                    <a href="#" class="me-3"><i class="fab fa-youtube"></i></a>
                    
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <h5><i class="fas fa-map-marker-alt me-2"></i> ที่อยู่</h5>
                <p class="small">111 หมู่ที่ 6 ถนน เศรษฐกิจ ตำบล บ้านสวน อำเภอเมืองชลบุรี ชลบุรี 20000</p>
                <p class="small"><i class="fas fa-phone me-2"></i> โทรศัพท์: 038 273 174</p>
                <p class="small"><i class="fas fa-file me-2"></i> โทรสาร: 038 285 505</p>
                <p class="small"><i class="fas fa-envelope me-2"></i> อีเมล: director@banjan.ac.th</p>
            </div>
                       
        </div>
        <hr class="my-4 border-secondary">
        <p class="text-center mb-0 small">© <?php echo date("Y"); ?> <?php echo $school_name_th; ?>. สงวนลิขสิทธิ์.</p>
    </div>
</footer>

<div class="modal fade" id="AboutUsModal" tabindex="-1" aria-labelledby="AboutUsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header modal-about-header">
                <img src="janLogo.png" alt="School Logo" class="modal-about-logo">
                <h5 class="modal-title" id="AboutUsModalTitle" style="margin-top: 30px;"> </h5> 
                
            </div>
            <div class="modal-body p-0">
                <div class="modal-about-content text-center">
                    
                    <iframe id="AboutUsModalIframe" class="modal-pdf-iframe" src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d4817.03397414338!2d101.00035677508308!3d13.354438786997248!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x311d359f59dba15b%3A0xd7156c6ffe83f20d!2z4LmC4Lij4LiH4LmA4Lij4Li14Lii4LiZ4Lia4LmJ4Liy4LiZ4Liq4Lin4LiZICjguIjguLHguYjguJnguK3guJnguLjguKrguKPguJPguYwp!5e1!3m2!1sth!2sth!4v1766631911996!5m2!1sth!2sth" frameborder="0" allowfullscreen>
                         <p class="text-muted">เบราว์เซอร์ของคุณไม่รองรับการแสดงผล iframe/PDF</p>
                    </iframe>
                    
                    <div class="d-flex justify-content-center align-items-center mt-3">
                        <?php if ($is_admin): ?>
                            <button type="button" class="btn btn-sm btn-warning me-3 btn-edit-about-us" data-bs-target="#AboutUsEditModal">
                                <i class="fas fa-link me-1"></i> แก้ไข URL PDF/แผนที่
                            </button>
                            <a id="AboutUsModalDownloadLink" href="#" target="_blank" class="btn btn-sm btn-outline-info">
                                <i class="fas fa-external-link-alt me-1"></i> เปิดในแท็บใหม่
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($is_admin): ?>
<div class="modal fade" id="AboutUsEditModal" tabindex="-1" aria-labelledby="AboutUsEditModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title" id="AboutUsEditModalLabel"><i class="fas fa-edit me-1"></i> แก้ไข URL <span id="AboutUsEditTitle"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="aboutUsEditForm" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="about_us_action" value="update_image">
                <input type="hidden" name="item_key" id="edit-about-us-key">
                <input type="hidden" name="title_th" id="hidden-title-th-url"> 
                
                <div class="modal-body">
                    <div class="alert alert-info small">
                        **คำแนะนำ:** คุณกำลังแก้ไข URL ที่ใช้ฝังไฟล์ PDF/แผนที่ การเปลี่ยนแปลงจะบันทึกค่าลงในช่อง **'ชื่อหัวข้อ'** ในฐานข้อมูลแทน
                    </div>

                    <div class="mb-3">
                        <label for="about-us-pdf-link-input" class="form-label">ลิงก์ PDF/Map Embed URL <span class="text-danger">*</span></label>
                        <input class="form-control" type="url" id="about-us-pdf-link-input" required placeholder="เช่น https://drive.google.com/file/d/ID_FILE/preview">
                        <div class="form-text">
                            <p class="mb-0"><strong>รูปแบบลิงก์ที่ถูกต้องสำหรับการฝัง:</strong></p>
                            <ul>
                                <li><strong>สำหรับ Google Drive PDF:</strong> ต้องเป็นลิงก์สำหรับฝัง (Embed Link) โดยเฉพาะ ซึ่งควรลงท้ายด้วย <code>/preview</code><br>
                                    (ตัวอย่าง: <code>https://drive.google.com/file/d/ID_ไฟล์/preview</code>)</li>
                                <li><strong>สำหรับ Google Maps:</strong> ใช้ URL ที่ได้จากฟังก์ชัน "Embed a map" ของ Google Maps</li>
                                <li>**สำคัญ:** ไฟล์ต้องถูกตั้งค่าการแชร์เป็น **"ทุกคนที่มีลิงก์ (Viewer)"** แล้วเท่านั้น</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-warning" id="about-us-save-btn"><i class="fas fa-save me-1"></i> บันทึกการแก้ไข URL</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>


<div class="modal fade" id="manualEditModal" tabindex="-1" aria-labelledby="manualEditModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="manualEditModalLabel"><i class="fas fa-book me-1"></i> แก้ไขคู่มือนักเรียน</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="config_action" value="update_manual">
                <div class="modal-body">
                    <div class="mb-3 text-center">
                        <label class="form-label">รูปภาพ Thumbnail ปัจจุบัน:</label><br>
                        <img id="modal-manual-current-image" src="<?php echo $upload_dir . htmlspecialchars($manual_data['image_file']); ?>" alt="Current Manual Image" class="img-preview mb-2">
                    </div>

                    <div class="mb-3">
                        <label for="manual-image-file" class="form-label">อัปโหลดรูปภาพใหม่ (จะใช้แทนรูปปก)</label>
                        <input class="form-control" type="file" id="manual-image-file" name="manual_image_file" accept="image/*">
                        <div class="form-text">ปล่อยว่างถ้าไม่ต้องการเปลี่ยนรูปภาพ</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="manual-link-url" class="form-label">ลิงก์ไฟล์คู่มือ (PDF/เว็บ) <span class="text-danger">*</span></label>
                        <input type="url" class="form-control" id="manual-link-url" name="link_url" value="<?php echo htmlspecialchars($manual_data['link_url']); ?>" required>
                        <div class="form-text">ใส่ลิงก์ตรงไปยังไฟล์ PDF หรือหน้าเว็บที่ต้องการ</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-info"><i class="fas fa-save me-1"></i> บันทึกการแก้ไข</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="journalEditModal" tabindex="-1" aria-labelledby="journalEditModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="journalEditModalLabel"><i class="fas fa-newspaper me-1"></i> แก้ไขวารสารโรงเรียน</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="config_action" value="update_journal">
                <div class="modal-body">
                    <div class="mb-3 text-center">
                        <label class="form-label">รูปภาพ Thumbnail ปัจจุบัน:</label><br>
                        <img id="modal-journal-current-image" src="<?php echo $upload_dir . htmlspecialchars($journal_data['image_file']); ?>" alt="Current Journal Image" class="img-preview mb-2">
                    </div>

                    <div class="mb-3">
                        <label for="journal-image-file" class="form-label">อัปโหลดรูปภาพใหม่ (จะใช้แทนรูปปก)</label>
                        <input class="form-control" type="file" id="journal-image-file" name="journal_image_file" accept="image/*">
                        <div class="form-text">ปล่อยว่างถ้าไม่ต้องการเปลี่ยนรูปภาพ</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="journal-link-url" class="form-label">ลิงก์ไฟล์วารสาร (PDF/เว็บ) <span class="text-danger">*</span></label>
                        <input type="url" class="form-control" id="journal-link-url" name="link_url" value="<?php echo htmlspecialchars($journal_data['link_url']); ?>" required>
                         <div class="form-text">ใส่ลิงก์ตรงไปยังไฟล์ PDF หรือหน้าเว็บที่ต้องการ</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-save me-1"></i> บันทึกการแก้ไข</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="directorEditModal" tabindex="-1" aria-labelledby="directorEditModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="directorEditModalLabel"><i class="fas fa-user-edit me-1"></i> แก้ไขข้อมูลผู้อำนวยการ</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="director_action" value="update_director">
                <div class="modal-body">
                    <div class="mb-3 text-center">
                        <label class="form-label">รูปภาพปัจจุบัน:</label><br>
                        <img id="modal-director-current-image" 
                             src="<?php echo $upload_dir . $director_data['image_file']; ?>" 
                             alt="Current Director Image" 
                             class="director-photo mb-2">
                    </div>

                    <div class="mb-3">
                        <label for="director-image-file" class="form-label">อัปโหลดรูปภาพใหม่ (จะใช้แทนรูปเดิม)</label>
                        <input type="file" class="form-control" id="director-image-file" name="director_image_file" accept="image/*">
                        <div class="form-text">ปล่อยว่างถ้าไม่ต้องการเปลี่ยนรูปภาพ (ภาพแนะนำคือสี่เหลี่ยมแนวตั้ง)</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="director-name" class="form-label">ชื่อผู้อำนวยการ (ภาษาไทย) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="director-name" name="director_name" value="<?php echo htmlspecialchars($director_data['name_th']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="director-title" class="form-label">ตำแหน่ง/บรรทัดล่างสุด <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="director-title" name="director_title" value="<?php echo htmlspecialchars($director_data['title_th']); ?>" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> บันทึกการแก้ไข</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="dynamicLinkModal" tabindex="-1" aria-labelledby="dynamicLinkModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="dynamicLinkModalLabel"><i class="fas fa-link me-1"></i> จัดการเมนูย่อย Dynamic (Navbar)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                
                <div class="admin-modal-section">
                    <h6 class="border-bottom pb-2 mb-3"><i class="fas fa-plus-circle me-1"></i> เพิ่มรายการเมนูใหม่</h6>
                    <form method="POST">
                        <input type="hidden" name="navbar_dynamic_action" value="add">
                        <input type="hidden" name="link_id" value="0">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="new-parent-menu" class="form-label">เมนูหลัก</label>
                                <select class="form-select" id="new-parent-menu" name="parent_menu" required>
                                    <option value="executive">กลุ่มบริหาร</option>
                                    <option value="student">สำหรับนักเรียน</option>
                                    <option value="about">ข้อมูลโรงเรียน</option>
                                    <option value="gallery">แกลเลอรี่กิจกรรม</option>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label for="new-title-th" class="form-label">ชื่อเมนูย่อย (ไทย)</label>
                                <input type="text" class="form-control" id="new-title-th" name="title_th" required>
                            </div>
                            <div class="col-md-3">
                                <label for="new-sort-order" class="form-label">ลำดับ</label>
                                <input type="number" class="form-control" id="new-sort-order" name="sort_order" value="0">
                            </div>
                            <div class="col-12">
                                <label for="new-link-url" class="form-label">ลิงก์ปลายทาง (URL)</label>
                                <input type="url" class="form-control" id="new-link-url" name="link_url" required>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end pt-3 border-top mt-3">
                            <button type="submit" class="btn btn-info text-white"><i class="fas fa-plus me-1"></i> เพิ่มรายการ</button>
                        </div>
                    </form>
                </div>

                <div class="admin-modal-section">
                    <h6 class="border-bottom pb-2 mb-3 mt-4"><i class="fas fa-list-ul me-1"></i> รายการเมนูย่อยทั้งหมด</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>เมนูหลัก</th>
                                    <th>ชื่อเมนู (ไทย)</th>
                                    <th>ลำดับ</th>
                                    <th>จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($navbar_links_dynamic as $parent_menu => $items): ?>
                                    <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td><?php echo $item['id']; ?></td>
                                        <td><?php 
                                            // Mapping menu key to readable title
                                            $menu_title_map = [
                                                'executive' => 'กลุ่มบริหาร',
                                                'student' => 'สำหรับนักเรียน',
                                                'about' => 'ข้อมูลโรงเรียน',
                                                'gallery' => 'แกลเลอรี่กิจกรรม',
                                            ];
                                            echo $menu_title_map[$parent_menu] ?? ucfirst($parent_menu);
                                        ?></td>
                                        <td><small class="d-block"><?php echo htmlspecialchars($item['title_th']); ?></small><small class="text-muted"><a href="<?php echo htmlspecialchars($item['link_url']); ?>" target="_blank">(ลิงก์)</a></small></td>
                                        <td><?php echo $item['sort_order']; ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-dark me-2 btn-edit-dynamic"
                                                data-id="<?php echo $item['id']; ?>"
                                                data-parent="<?php echo $item['parent_menu']; ?>"
                                                data-title="<?php echo htmlspecialchars($item['title_th']); ?>"
                                                data-link="<?php echo htmlspecialchars($item['link_url']); ?>"
                                                data-order="<?php echo $item['sort_order']; ?>"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editDynamicModal">
                                                <i class="fas fa-pencil-alt"></i> แก้ไข
                                            </button>
                                            <button type="button" class="btn btn-sm btn-danger" 
                                                data-bs-toggle="modal"
                                                data-bs-target="#deleteConfirmModal"
                                                data-id="<?php echo $item['id']; ?>"
                                                data-title="<?php echo htmlspecialchars($item['title_th']); ?>">
                                                <i class="fas fa-trash-alt"></i> ลบ
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิดหน้าต่าง</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editDynamicModal" tabindex="-1" aria-labelledby="editDynamicModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title" id="editDynamicModalLabel"><i class="fas fa-pencil-alt me-1"></i> แก้ไขรายการเมนูย่อย</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="navbar_dynamic_action" value="edit">
                <input type="hidden" name="link_id" id="dynamic-link-id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="dynamic-parent-menu" class="form-label">เมนูหลัก</label>
                        <select class="form-select" id="dynamic-parent-menu" name="parent_menu" required>
                            <option value="executive">กลุ่มบริหาร</option>
                            <option value="student">สำหรับนักเรียน</option>
                            <option value="about">ข้อมูลโรงเรียน</option>
                            <option value="gallery">แกลเลอรี่กิจกรรม</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="dynamic-title-th" class="form-label">ชื่อเมนูย่อย (ไทย) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="dynamic-title-th" name="title_th" required>
                    </div>
                    <div class="mb-3">
                        <label for="dynamic-link-url" class="form-label">ลิงก์ปลายทาง (URL) <span class="text-danger">*</span></label>
                        <input type="url" class="form-control" id="dynamic-link-url" name="link_url" required>
                    </div>
                    <div class="mb-3">
                        <label for="dynamic-sort-order" class="form-label">ลำดับการแสดง (น้อยไปมาก)</label>
                        <input type="number" class="form-control" id="dynamic-sort-order" name="sort_order" value="0">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-dark"><i class="fas fa-save me-1"></i> บันทึกการแก้ไข</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteConfirmModalLabel">ยืนยันการลบ</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="navbar_dynamic_action" value="delete">
                <input type="hidden" name="link_id" id="delete-link-id">
                <div class="modal-body">
                    <p>คุณแน่ใจหรือไม่ที่จะลบรายการเมนู</p>
                    <p class="fw-bold text-danger" id="item-title-to-delete"></p>
                    <p>รายการนี้จะถูกลบอย่างถาวร</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-trash-alt me-1"></i> ยืนยันการลบ</button>
                </div>
            </form>
        </div>
    </div>
</div>


<div class="modal fade" id="newsEditModal" tabindex="-1" aria-labelledby="newsEditModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable"> 
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="newsEditModalLabel"><i class="fas fa-pencil-alt me-1"></i> เพิ่ม/แก้ไข ข่าวสาร</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="news_action" id="news-action" value="add">
                <input type="hidden" name="news_id" id="news-id" value="0">
                <div class="modal-body">
                    
                    <div class="admin-modal-section">
                        <h6 class="border-bottom pb-2 mb-3">รายละเอียดเนื้อหา</h6>
                        <div class="mb-3">
                            <label for="news-title-th" class="form-label">หัวข้อข่าวสาร/ประกาศ <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="news-title-th" name="title_th" required>
                        </div>
                        <div class="mb-3">
                            <label for="news-description-th" class="form-label">คำอธิบายสั้นๆ (แสดงหน้าแรก) <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="news-description-th" name="description_th" rows="2" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="news-full-content" class="form-label">เนื้อหาเต็ม (แสดงในหน้า news_detail.php)</label>
                            <textarea class="form-control" id="news-full-content" name="full_content" rows="5" placeholder="ใส่เนื้อหาข่าวสารแบบเต็ม"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="news-link-url" class="form-label">ลิงก์ปลายทางภายนอก (ไม่จำเป็น)</label>
                            <input type="url" class="form-control" id="news-link-url" name="link_url" placeholder="เช่น ลิงก์ Google Drive, Facebook, หรือเว็บไซต์ภายนอก">
                            <div class="form-text">ปล่อยว่าง หากต้องการให้ลิงก์เป็นหน้าอ่านเนื้อหาภายในเว็บไซต์นี้</div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="news-date" class="form-label"><i class="fas fa-calendar-alt me-1"></i> วันที่ประกาศ <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="news-date" name="news_date" required> 
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="news-sort-order" class="form-label">ลำดับ</label>
                                <input type="number" class="form-control" id="news-sort-order" name="sort_order" value="0">
                            </div>
                          
                        </div>
                    </div>
                    
                    <div class="admin-modal-section mt-4">
                        <h6 class="border-bottom pb-2 mb-3">จัดการรูปภาพ</h6>
                        <div class="mb-3">
                            <label for="news-images" class="form-label">อัปโหลดรูปภาพใหม่ (เลือกได้หลายไฟล์) <span class="text-danger">**สำคัญ: เลือกไฟล์อย่างน้อย 1 ไฟล์สำหรับรายการใหม่**</span></label>
                            <input type="file" class="form-control" id="news-images" name="news_images[]" accept="image/*" multiple>
                            <div class="form-text">รูปภาพแรกจะเป็นรูป Thumbnail ที่แสดงหน้าแรก (ถ้ามี)</div>
                        </div>
                        
                        <div class="mt-3 p-3 border rounded bg-white" id="current-images-container">
                            </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-success" id="news-save-btn"><i class="fas fa-save me-1"></i> บันทึกรายการ</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="quickLinkModal" tabindex="-1" aria-labelledby="quickLinkModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title" id="quickLinkModalLabel"><i class="fas fa-bolt me-1"></i> จัดการรายการลิงก์ด่วน (ลิงก์หน่วยงาน)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                
                <div class="admin-modal-section">
                    <h6 class="border-bottom pb-2 mb-3"><i class="fas fa-plus-circle me-1"></i> เพิ่มลิงก์ด่วนใหม่</h6>
                    <form method="POST">
                        <input type="hidden" name="quick_link_action" value="add">
                        <input type="hidden" name="quick_link_id" value="0">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="new-ql-title" class="form-label">ชื่อลิงก์ <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="new-ql-title" name="title" required>
                            </div>
                            <div class="col-md-3">
                                <label for="new-ql-icon" class="form-label">FA Icon Class</label>
                                <input type="text" class="form-control" id="new-ql-icon" name="icon" required>
                                <div class="form-text">ใช้ Font Awesome Class</div>
                            </div>
                            <div class="col-md-3">
                                <label for="new-ql-sort" class="form-label">ลำดับ</label>
                                <input type="number" class="form-control" id="new-ql-sort" name="sort_order" value="0">
                            </div>
                            <div class="col-12">
                                <label for="new-ql-link" class="form-label">ลิงก์ปลายทาง (URL)</label>
                                <input type="url" class="form-control" id="new-ql-link" name="link_url" required>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end pt-3 border-top mt-3">
                            <button type="submit" class="btn btn-success"><i class="fas fa-plus me-1"></i> เพิ่มรายการ</button>
                        </div>
                    </form>
                </div>

                <div class="admin-modal-section">
                    <h6 class="border-bottom pb-2 mb-3 mt-4"><i class="fas fa-list-ul me-1"></i> รายการลิงก์ด่วนทั้งหมด</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr>
                                    <th>Icon</th>
                                    <th>ชื่อลิงก์</th>
                                    <th>URL</th>
                                    <th>ลำดับ</th>
                                    <th>จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($quick_links_db)): ?>
                                    <?php foreach ($quick_links_db as $link): ?>
                                    <tr>
                                        <td class="text-center"><i class="fas <?php echo htmlspecialchars($link['icon']); ?> text-pink-primary"></i></td>
                                        <td><?php echo htmlspecialchars($link['title']); ?></td>
                                        <td><a href="<?php echo htmlspecialchars($link['link_url']); ?>" target="_blank" class="small"><?php echo htmlspecialchars($link['link_url']); ?></a></td>
                                        <td><?php echo $link['sort_order']; ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-warning me-1 btn-edit-quick-link"
                                                data-bs-toggle="modal" data-bs-target="#editQuickLinkModal"
                                                data-id="<?php echo $link['id']; ?>"
                                                data-title="<?php echo htmlspecialchars($link['title']); ?>"
                                                data-link="<?php echo htmlspecialchars($link['link_url']); ?>"
                                                data-icon="<?php echo htmlspecialchars($link['icon']); ?>"
                                                data-sort="<?php echo $link['sort_order']; ?>">
                                                <i class="fas fa-pencil-alt"></i> แก้ไข
                                            </button>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('ยืนยันการลบลิงก์ด่วนนี้?');">
                                                <input type="hidden" name="quick_link_action" value="delete">
                                                <input type="hidden" name="quick_link_id" value="<?php echo $link['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i> ลบ</button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">ยังไม่มีรายการลิงก์ด่วนในฐานข้อมูล (กำลังใช้ Mock Data)</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิดหน้าต่าง</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editQuickLinkModal" tabindex="-1" aria-labelledby="editQuickLinkModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="editQuickLinkModalLabel"><i class="fas fa-pencil-alt me-1"></i> แก้ไขลิงก์ด่วน</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="quick_link_action" value="edit">
                <input type="hidden" name="quick_link_id" id="edit-ql-id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit-ql-title" class="form-label">ชื่อลิงก์ <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit-ql-title" name="title" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6 mb-3">
                            <label for="edit-ql-icon" class="form-label">FA Icon Class</label>
                            <input type="text" class="form-control" id="edit-ql-icon" name="icon" required>
                            <div class="form-text">เช่น fa-globe</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit-ql-sort" class="form-label">ลำดับ</label>
                            <input type="number" class="form-control" id="edit-ql-sort" name="sort_order">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="edit-ql-link" class="form-label">ลิงก์ปลายทาง (URL) <span class="text-danger">*</span></label>
                        <input type="url" class="form-control" id="edit-ql-link" name="link_url" required>
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


<div class="modal fade" id="deleteNewsConfirmModal" tabindex="-1" aria-labelledby="deleteNewsConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteNewsConfirmModalLabel"><i class="fas fa-exclamation-triangle me-1"></i> ยืนยันการลบ</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="newsDeleteForm" method="POST" action="index.php">
                <input type="hidden" name="news_action" value="delete">
                <input type="hidden" name="news_id" id="delete-news-id">
                <div class="modal-body text-center">
                    <p class="mb-3">คุณแน่ใจหรือไม่ที่จะลบข่าวสารนี้?</p>
                    <p class="fw-bold text-danger" id="news-title-to-delete"></p>
                    <p class="small text-muted">รายการนี้จะถูกลบอย่างถาวร</p>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-trash me-1"></i> ยืนยันการลบ</button>
                </div>
            </form>
        </div>
    </div>
</div>


<div class="modal fade" id="newsViewModal" tabindex="-1" aria-labelledby="newsViewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable"> 
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title" id="newsViewModalTitle"></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="badge bg-pink-primary" id="newsViewModalDate"><i class="far fa-calendar-alt me-1"></i></span>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i> ปิด</button>
                </div>
                
                <p class="lead text-muted" id="newsViewModalDescription"></p>
                
                <div id="newsViewModalLinkButton">
                    </div>
                <hr>

                <div class="news-full-content-area mb-4">
                    <p id="newsViewModalContent"></p>
                </div>

                <h4 class="text-pink-primary border-bottom pb-2 mb-3"><i class="fas fa-camera me-2"></i> รูปภาพประกอบ</h4>
                <div class="row g-3" id="newsViewModalGallery">
                    <div class="col-12 text-center text-muted">ไม่มีรูปภาพประกอบเพิ่มเติม</div>
                </div>
                
            </div>
            <div class="modal-footer d-flex justify-content-between align-items-center">
                <div id="newsModalExternalLinkBtn" class="flex-grow-1 me-2">
                    </div>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิดหน้าต่าง</button>
            </div>
            </div>
    </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title" id="editModalLabel"><i class="fas fa-edit me-1"></i> จัดการรายการประกาศ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-4">
                <div class="admin-modal-section">
                    <h6 class="border-bottom pb-2 mb-3"><i class="fas fa-plus-circle me-1"></i> เพิ่ม / แก้ไข รายการปัจจุบัน</h6>
                    <form method="POST" enctype="multipart/form-data" class="mb-0">
                        <input type="hidden" name="board_action" value="save">
                        <input type="hidden" name="board_id" id="modal-id" value="0">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3 text-center">
                                <label class="form-label">รูปภาพปัจจุบัน (สำหรับแก้ไข)</label><br>
                                <img id="modal-current-image" src="" alt="Current Board Image" class="img-preview mb-2">
                                <label for="modal-image-file" class="form-label d-block mt-2">อัปโหลดรูปภาพใหม่ (ขนาดแนะนำ 16:9)</label>
                                <input class="form-control" type="file" id="modal-image-file" name="image_file" accept="image/*">
                                <div class="form-text">ปล่อยว่างถ้าไม่ต้องการเปลี่ยนรูปภาพ</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <input type="hidden" name="title_th" id="modal-title-th" value="">
                                <input type="hidden" name="subtitle_th" id="modal-subtitle-th" value="">

                                <label for="modal-link-url" class="form-label">ลิงก์ปลายทาง <span class="text-danger">*</span></label>
                                <input type="url" class="form-control" id="modal-link-url" name="link_url" required>

                                <label for="modal-footer-text" class="form-label mt-3">ข้อความในแถบชมพู (Footer) <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="modal-footer-text" name="footer_text" required>
                            
                                <div class="row mt-3">
                                    <div class="col-6">
                                        <label for="modal-sort-order" class="form-label">ลำดับการแสดง (น้อยไปมาก)</label>
                                        <input type="number" class="form-control" id="modal-sort-order" name="sort_order" value="0">
                                    </div>
                                    <div class="col-6 d-flex align-items-end">
                                        <div class="form-check form-switch pt-2">
                                            <input class="form-check-input" type="checkbox" id="modal-is-active" name="is_active" checked>
                                            <label class="form-check-label" for="modal-is-active">เปิดใช้งาน</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-end pt-3 border-top mt-3">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> บันทึกรายการ</button>
                        </div>
                    </form>
                </div>

                <div class="admin-modal-section">
                    <h6 class="border-bottom pb-2 mb-3 mt-4"><i class="fas fa-list-ul me-1"></i> รายการทั้งหมด</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr>
                                    <th>รูปภาพ</th>
                                    <th>ID / ลำดับ</th>
                                    <th>ลิงก์ / Footer</th>
                                    <th>Active</th>
                                    <th>จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_boards_list as $board): ?>
                                <tr>
                                    <td style="width:100px;">
                                        <img src="<?php echo $upload_dir . htmlspecialchars($board['image_file']); ?>" class="img-fluid" style="height: 50px; object-fit: cover;">
                                    </td>
                                    <td><?php echo $board['id']; ?> / <?php echo $board['sort_order']; ?></td>
                                    <td>
                                        <small><a href="<?php echo htmlspecialchars($board['link_url']); ?>" target="_blank" class="d-block"><?php echo htmlspecialchars($board['link_url']); ?></a></small>
                                        <small class="text-muted d-block">(<?php echo htmlspecialchars($board['footer_text']); ?>)</small>
                                    </td>
                                    <td>
                                        <?php echo ($board['is_active'] == 1) ? '<span class="badge bg-success">ON</span>' : '<span class="badge bg-danger">OFF</span>'; ?>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-dark me-2 btn-edit" 
                                             data-bs-toggle="modal" 
                                             data-bs-target="#editModal"
                                             data-id="<?php echo $board['id']; ?>"
                                             data-title="<?php echo htmlspecialchars($board['title_th']); ?>"
                                             data-subtitle="<?php echo htmlspecialchars($board['subtitle_th']); ?>"
                                             data-link="<?php echo htmlspecialchars($board['link_url']); ?>"
                                             data-footer="<?php echo htmlspecialchars($board['footer_text']); ?>"
                                             data-image="<?php echo $upload_dir . htmlspecialchars($board['image_file']); ?>"
                                             data-order="<?php echo $board['sort_order']; ?>"
                                             data-active="<?php echo $board['is_active']; ?>"
                                        >
                                            <i class="fas fa-pencil-alt"></i> แก้ไข
                                        </button>
                                        <a href="index.php?delete_id=<?php echo $board['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('คุณต้องการลบรายการนี้ใช่หรือไม่?')">
                                            <i class="fas fa-trash-alt"></i> ลบ
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                 <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิดหน้าต่าง</button>
            </div>
        </div>
    </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // ** ข้อมูลที่ดึงจาก PHP **
    const aboutUsData = <?php echo json_encode($about_us_data); ?>;
    const uploadDir = '<?php echo $upload_dir; ?>';
    const newsItemsList = <?php echo json_encode($news_items_list); ?>;
    
    // --- ฟังก์ชันช่วยเหลือ ---
    function ucfirst(str) {
        if (!str) return str;
        return str.charAt(0).toUpperCase() + str.slice(1);
    }
    
    document.addEventListener('DOMContentLoaded', function () {

        // =======================================================
        //  บอร์ดประกาศ: บังคับเริ่มสไลด์อัตโนมัติทุกๆ 5 วินาที
        // =======================================================
        const boardCarousel = document.getElementById('mainBoardCarousel');
        if (boardCarousel) {
            new bootstrap.Carousel(boardCarousel, {
                interval: 5000,
                ride: 'carousel',
                wrap: true
            });
        }
        
        // =======================================================
        //  GLOBAL FIX: Modal Cleanup 
        // =======================================================
        document.querySelectorAll('.modal').forEach(modalEl => {
            modalEl.addEventListener('hidden.bs.modal', function () {
                if (document.activeElement && document.activeElement.tagName !== 'BODY') {
                    document.activeElement.blur(); 
                }
                if (document.querySelectorAll('.modal.show').length === 0) {
                    const backdrops = document.querySelectorAll('.modal-backdrop');
                    backdrops.forEach(backdrop => backdrop.remove());
                    document.body.classList.remove('modal-open');
                    document.body.style.overflow = '';
                    document.body.style.paddingRight = '';
                }
            });
        });
        
        // =======================================================
        //  1. About Us Modal Handlers (Generic) (MODIFIED)
        // =======================================================
        const aboutUsModalElement = document.getElementById('AboutUsModal');
        const aboutUsEditModalElement = document.getElementById('AboutUsEditModal');

        // --- View Modal Handler (Open Modal) ---
        document.body.addEventListener('click', function(event) {
            const viewButton = event.target.closest('.btn-about-us-view');
            if (viewButton) {
                const itemKey = viewButton.getAttribute('data-item-key');
                const itemTitle = viewButton.getAttribute('data-item-title');
                const imageUrl = viewButton.getAttribute('data-item-image');
                // 💡 NEW: Get PDF URL
                const pdfUrl = viewButton.getAttribute('data-item-pdf-url');
                
                // Set data for View Modal
                if (aboutUsModalElement) {
                    
                    // ❌ FIX: ลบชื่อหัวข้อออก (เหลือเพียงโลโก้)
                    aboutUsModalElement.querySelector('#AboutUsModalTitle').textContent = '\u00a0'; 
                    const iframe = aboutUsModalElement.querySelector('#AboutUsModalIframe');
                    const downloadLink = aboutUsModalElement.querySelector('#AboutUsModalDownloadLink');

                    if (pdfUrl) {
                        iframe.src = pdfUrl;
                        downloadLink.href = pdfUrl;
                        downloadLink.textContent = pdfUrl.includes('map') ? 'ดูแผนที่ขนาดใหญ่' : 'เปิดไฟล์ PDF ในแท็บใหม่';
                        iframe.style.display = 'block';
                    } else {
                        iframe.src = 'about:blank';
                        iframe.style.display = 'none';
                        downloadLink.textContent = 'ไม่พบไฟล์ที่ฝัง';
                        downloadLink.href = '#';
                    }
                    
                    // Pass data to Edit Button in View Modal (for easy chaining)
                    const editBtnInView = aboutUsModalElement.querySelector('.btn-edit-about-us');
                    if (editBtnInView) {
                        editBtnInView.setAttribute('data-item-key', itemKey);
                        editBtnInView.setAttribute('data-item-title', itemTitle);
                        editBtnInView.setAttribute('data-item-image', imageUrl); // ยังคงส่ง Image URL เดิมไป
                        editBtnInView.setAttribute('data-item-pdf-url', pdfUrl); // 💡 NEW: Send PDF URL
                        editBtnInView.setAttribute('data-bs-toggle', 'modal');
                        editBtnInView.setAttribute('data-bs-target', '#AboutUsEditModal');
                    }
                    
                    const modalInstance = new bootstrap.Modal(aboutUsModalElement);
                    modalInstance.show();
                }
            }
        });

        // --- Edit Modal Handler (Prepare data and hide View Modal) (MODIFIED) ---
        document.body.addEventListener('click', function(event) {
            const editButton = event.target.closest('.btn-edit-about-us');
            if (editButton) {
                // 1. Hide View Modal
                const viewModalInstance = bootstrap.Modal.getInstance(document.getElementById('AboutUsModal'));
                if (viewModalInstance) viewModalInstance.hide();
                
                const itemKey = editButton.getAttribute('data-item-key');
                const itemTitle = editButton.getAttribute('data-item-title');
                const imageUrl = editButton.getAttribute('data-item-image');
                const pdfUrl = editButton.getAttribute('data-item-pdf-url'); // 💡 NEW: Get PDF URL
                
                if (aboutUsEditModalElement) {
                    // 2. Populate Edit Modal
                    aboutUsEditModalElement.querySelector('#AboutUsEditTitle').textContent = itemTitle;
                    aboutUsEditModalElement.querySelector('#edit-about-us-key').value = itemKey;
                    
                    // 💡 NEW: Populate URL Link Field
                    // ** ข้อควรระวัง: เราดึง URL PDF จาก data-item-pdf-url (ซึ่งมาจาก title_th)
                    aboutUsEditModalElement.querySelector('#about-us-pdf-link-input').value = pdfUrl;
                    
                    // 3. Show Edit Modal
                    const editModalInstance = new bootstrap.Modal(aboutUsEditModalElement);
                    editModalInstance.show();
                }
            }
        });
        
        // --- Intercept Form Submit (MODIFIED) ---
        const aboutUsEditForm = document.getElementById('aboutUsEditForm');
        if (aboutUsEditForm) {
            aboutUsEditForm.addEventListener('submit', function(e) {
                // 1. ดึง URL ใหม่จากช่อง Input
                const newUrl = document.getElementById('about-us-pdf-link-input').value;
                
                // 2. ยัด URL นี้เข้าไปในช่อง Hidden Input ที่ชื่อ 'title_th'
                //    เพื่อให้ PHP Post Handler (2.7) บันทึก URL นี้กลับไปที่ DB แทนชื่อเรื่องเดิม
                document.getElementById('hidden-title-th-url').value = newUrl;
                
                // 3. ฟอร์มจะถูกส่งไปตาม Logic เดิมของ PHP (2.7)
            });
            
            // --- Edit Modal Close Handler (Re-open View Modal) ---
            aboutUsEditModalElement.addEventListener('hidden.bs.modal', function () {
                 // Do nothing, just close.
            });
        }
        
        // =======================================================
        //  2. News View Modal Function (MODIFIED) (ห้ามแก้ไข)
        // =======================================================
        const newsViewModalElement = document.getElementById('newsViewModal');
        const footerLinkContainer = document.getElementById('newsModalExternalLinkBtn');

        function loadNewsViewModal(newsId) {
            let newsItem = newsItemsList.find(item => parseInt(item.id) === parseInt(newsId));
            
            if (!newsItem) {
                console.error('News item not found in newsItemsList. Using mock fallback.', newsId);
                newsItem = {
                    title_th: "ไม่พบข่าวสาร (กรุณาเพิ่มในฐานข้อมูล)",
                    description_th: "ไม่พบรายการข่าวสารในระบบ กรุณาเข้าสู่ระบบ Admin เพื่อเพิ่มรายการใหม่",
                    full_content: "<h2>ไม่พบรายการ</h2><p>รายการนี้อาจถูกลบไปแล้ว หรือยังไม่ถูกเพิ่มในฐานข้อมูล</p>",
                    created_at: new Date().toISOString(),
                    link_url: '', 
                    gallery: []
                };
            }
            
             if (newsItem && newsViewModalElement) { 
                const dateObj = new Date(newsItem.created_at);
                const formattedDate = dateObj.toLocaleDateString('th-TH', { 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric' 
                });

                document.getElementById('newsViewModalTitle').textContent = newsItem.title_th;
                document.getElementById('newsViewModalDate').innerHTML = `<i class="far fa-calendar-alt me-1"></i> วันที่ประกาศ: ${formattedDate}`;
                document.getElementById('newsViewModalDescription').textContent = newsItem.description_th;
                document.getElementById('newsViewModalContent').innerHTML = newsItem.full_content || 'ไม่มีเนื้อหาข่าวสารฉบับเต็ม'; 

                const linkUrl = newsItem.link_url;

                if (footerLinkContainer) {
                    if (linkUrl && linkUrl.indexOf('news_detail.php') === -1 && linkUrl.trim() !== '') {
                        footerLinkContainer.innerHTML = `<a href="${linkUrl}" target="_blank" class="btn bg-pink-primary text-white w-100"><i class="fas fa-external-link-alt me-1"></i> คลิกเพื่อดูเอกสาร/ลิงก์ต้นฉบับ</a>`;
                    } else {
                        footerLinkContainer.innerHTML = '';
                    }
                }
                
                const galleryContainer = document.getElementById('newsViewModalGallery');
                galleryContainer.innerHTML = '';
                
                if (newsItem.gallery && newsItem.gallery.length > 0) {
                    newsItem.gallery.forEach(img => {
                        const imageUrl = uploadDir + img.image_file;
                        const col = document.createElement('div');
                        col.classList.add('col-md-4', 'col-sm-6'); 
                        col.innerHTML = `
                            <a href="${imageUrl}" target="_blank">
                                <img src="${imageUrl}" alt="${newsItem.title_th}" class="news-gallery-image">
                            </a>
                        `;
                        galleryContainer.appendChild(col);
                    });
                } else {
                    galleryContainer.innerHTML = '<div class="col-12 text-center text-muted">ไม่มีรูปภาพประกอบเพิ่มเติม</div>';
                }

                const newsViewModalInstance = new bootstrap.Modal(newsViewModalElement);
                newsViewModalInstance.show();

            } else {
                console.error('News View Modal element not found or data error.');
            }
        }
        
        // --- Event Listener for "Read More" Buttons (Open View Modal) ---
        document.body.addEventListener('click', function(event) {
            const readMoreButton = event.target.closest('.btn-read-more');
            if (readMoreButton) {
                const newsId = readMoreButton.getAttribute('data-news-id');
                if (newsId) {
                    loadNewsViewModal(newsId);
                }
            }
        });

        // =======================================================
        //  3. Other Admin Modals Handlers (ห้ามแก้ไข)
        // =======================================================
        
        // ** News Delete Confirmation Modal **
        var deleteNewsConfirmModal = document.getElementById('deleteNewsConfirmModal');
        if (deleteNewsConfirmModal) {
            deleteNewsConfirmModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                var newsId = button.getAttribute('data-id');
                var newsTitle = button.getAttribute('data-title');

                var modal = this;
                modal.querySelector('#delete-news-id').value = newsId;
                modal.querySelector('#news-title-to-delete').textContent = newsTitle;
            });
        }
        
        // ** Board Edit Modal Handlers **
        var editModal = document.getElementById('editModal');
        if (editModal) { 
            editModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget; 
                
                if (button && button.classList.contains('btn-edit')) {
                    // Edit state 
                    document.getElementById('editModalLabel').textContent = 'แก้ไขรายการประกาศ #' + button.getAttribute('data-id');
                    var id = button.getAttribute('data-id');
                    var link = button.getAttribute('data-link');
                    var footer = button.getAttribute('data-footer');
                    var image_url = button.getAttribute('data-image');
                    var sort_order = button.getAttribute('data-order');
                    var is_active = button.getAttribute('data-active');

                    document.getElementById('modal-current-image').src = image_url;
                    document.getElementById('modal-id').value = id;
                    document.getElementById('modal-link-url').value = link;
                    document.getElementById('modal-footer-text').value = footer;
                    document.getElementById('modal-sort-order').value = sort_order;
                    document.getElementById('modal-is-active').checked = (is_active == 1);
                } else {
                    // Reset to "Add New" state 
                    document.getElementById('editModalLabel').textContent = 'จัดการรายการประกาศ';
                    document.getElementById('modal-id').value = 0;
                    document.getElementById('modal-current-image').src = 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs='; 
                    document.getElementById('modal-link-url').value = '';
                    document.getElementById('modal-footer-text').value = 'คลิกเพื่อดูรายละเอียด';
                    document.getElementById('modal-sort-order').value = 0;
                    document.getElementById('modal-is-active').checked = true;
                }
            });
        }

        // ** Manual/Journal Modal Handlers **
        var manualEditModal = document.getElementById('manualEditModal');
        if (manualEditModal) { 
            manualEditModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget; 
                var link = button.getAttribute('data-link');
                var image_name = button.getAttribute('data-image');
                var modalImage = manualEditModal.querySelector('#modal-manual-current-image');
                var modalLink = manualEditModal.querySelector('#manual-link-url');
                modalImage.src = uploadDir + image_name;
                modalLink.value = link;
            });
        }
        
        var journalEditModal = document.getElementById('journalEditModal');
        if (journalEditModal) { 
            journalEditModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget; 
                var link = button.getAttribute('data-link');
                var image_name = button.getAttribute('data-image');
                var modalImage = journalEditModal.querySelector('#modal-journal-current-image');
                var modalLink = journalEditModal.querySelector('#journal-link-url');
                modalImage.src = uploadDir + image_name;
                modalLink.value = link;
            });
        }

        // ** Director Modal Handler **
        var directorEditModal = document.getElementById('directorEditModal');
        if (directorEditModal) { 
            directorEditModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget; 
                var name = button.getAttribute('data-name');
                var title = button.getAttribute('data-title');
                var image_file_name = button.getAttribute('data-image');
                var modalImage = directorEditModal.querySelector('#modal-director-current-image');
                var modalName = directorEditModal.querySelector('#director-name');
                var modalTitle = directorEditModal.querySelector('#director-title');
                
                modalImage.src = uploadDir + image_file_name; 
                modalName.value = name;
                modalTitle.value = title;
            });
        }

        // ** News Edit/Add Modal Handler **
        const newsEditModal = document.getElementById('newsEditModal');
        function loadCurrentNewsImages(newsId) {
            const container = document.getElementById('current-images-container');
            container.innerHTML = '';
            
            if (newsId === 0 || newsId === '0') {
                container.innerHTML = '<p class="text-muted small mb-0">รายการใหม่ยังไม่มีรูปภาพ</p>';
                return;
            }

            const newsItem = newsItemsList.find(item => parseInt(item.id) === parseInt(newsId));
            
            if (newsItem && newsItem.gallery.length > 0) {
                container.innerHTML = '<h6 class="text-muted small">รูปภาพปัจจุบัน (ลบได้)</h6><div class="row row-cols-3 g-2">';
                newsItem.gallery.forEach(img => {
                    const imgPath = uploadDir + img.image_file;
                    container.innerHTML += `
                        <div class="col text-center position-relative">
                            <img src="${imgPath}" class="img-fluid rounded" style="max-height: 100px; object-fit: cover;">
                            <form method="POST" class="d-inline-block position-absolute top-0 end-0" style="margin-top:-10px;">
                                <input type="hidden" name="news_action" value="delete_image">
                                <input type="hidden" name="news_id" value="${newsId}">
                                <input type="hidden" name="image_id" value="${img.id}">
                                <button type="submit" class="btn btn-danger btn-sm rounded-circle p-0" style="width:24px; height:24px; line-height: 1;" onclick="return confirm('ยืนยันลบรูปภาพ?')">
                                    <i class="fas fa-times" style="font-size:10px;"></i>
                                </button>
                            </form>
                        </div>
                    `;
                });
                container.innerHTML += '</div>';
            } else {
                 container.innerHTML = '<p class="text-muted small mb-0">ไม่มีรูปภาพในรายการนี้</p>';
            }
        }
        
        if (newsEditModal) { 
            const newsModalElement = document.getElementById('newsEditModal');
            
            newsEditModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const newsId = button ? button.getAttribute('data-news-id') : null; 
                
                if (newsId > 0) {
                    const newsItem = newsItemsList.find(item => parseInt(item.id) === parseInt(newsId));

                    newsEditModal.querySelector('.modal-title').textContent = 'แก้ไขข่าวสาร #' + newsId;
                    document.getElementById('news-action').value = 'edit';
                    document.getElementById('news-id').value = newsId;
                    document.getElementById('news-title-th').value = button.getAttribute('data-title');
                    document.getElementById('news-description-th').value = button.getAttribute('data-description');
                    document.getElementById('news-full-content').value = newsItem ? newsItem.full_content : '';
                    document.getElementById('news-link-url').value = button.getAttribute('data-link') || '';
                    document.getElementById('news-sort-order').value = button.getAttribute('data-sort');
                    document.getElementById('news-save-btn').innerHTML = '<i class="fas fa-save me-1"></i> บันทึกการแก้ไข';
                    
                    if (newsItem && newsItem.created_at) {
                        const dateOnly = newsItem.created_at.split(' ')[0]; 
                        document.getElementById('news-date').value = dateOnly;
                    }
                    
                    loadCurrentNewsImages(newsId);
                } else {
                    // --- Add New Mode (newsId is 0 or null) ---
                    newsEditModal.querySelector('.modal-title').textContent = 'เพิ่มรายการข่าวสารใหม่';
                    document.getElementById('news-action').value = 'add';
                    document.getElementById('news-id').value = 0;
                    document.getElementById('news-title-th').value = '';
                    document.getElementById('news-description-th').value = '';
                    document.getElementById('news-full-content').value = ''; 
                    document.getElementById('news-link-url').value = ''; 
                    document.getElementById('news-sort-order').value = 0;
                    document.getElementById('news-save-btn').innerHTML = '<i class="fas fa-save me-1"></i> เพิ่มรายการ';
                    
                    document.getElementById('news-date').valueAsDate = new Date(); 
                    
                    loadCurrentNewsImages(0);
                }
            });
        }
        
        // ** Dynamic Links Handler **
        var dynamicLinkModal = document.getElementById('dynamicLinkModal');
        var editDynamicModal = document.getElementById('editDynamicModal');
        var deleteConfirmModal = document.getElementById('deleteConfirmModal');
        
        if (dynamicLinkModal && editDynamicModal && deleteConfirmModal) { 
            dynamicLinkModal.addEventListener('click', function(event) {
                 const editButton = event.target.closest('.btn-edit-dynamic');
                 const deleteButton = event.target.closest('.btn-danger[data-bs-target="#deleteConfirmModal"]');

                 if (editButton) {
                    const mainModal = bootstrap.Modal.getInstance(document.getElementById('dynamicLinkModal'));
                    if (mainModal) mainModal.hide();

                    const id = editButton.getAttribute('data-id');
                    const parent = editButton.getAttribute('data-parent');
                    const title = editButton.getAttribute('data-title');
                    const link = editButton.getAttribute('data-link');
                    const order = editButton.getAttribute('data-order');

                    const modalElement = document.getElementById('editDynamicModal');
                    
                    modalElement.querySelector('#dynamic-link-id').value = id;
                    modalElement.querySelector('#dynamic-parent-menu').value = parent;
                    modalElement.querySelector('#dynamic-title-th').value = title;
                    modalElement.querySelector('#dynamic-link-url').value = link;
                    modalElement.querySelector('#dynamic-sort-order').value = order;
                    
                    const editModalInstance = new bootstrap.Modal(modalElement);
                    editModalInstance.show();
                 } else if (deleteButton) {
                     const mainModal = bootstrap.Modal.getInstance(document.getElementById('dynamicLinkModal'));
                     if (mainModal) mainModal.hide();

                     var itemId = deleteButton.getAttribute('data-id');
                     var itemTitle = deleteButton.getAttribute('data-title');
                     
                     document.getElementById('delete-link-id').value = itemId;
                     document.getElementById('item-title-to-delete').textContent = itemTitle;

                     const deleteModalInstance = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
                     deleteModalInstance.show();
                 }
            });
            
            editDynamicModal.addEventListener('hidden.bs.modal', function () {
                 const mainModalInstance = new bootstrap.Modal(document.getElementById('dynamicLinkModal'));
                 mainModalInstance.show();
            });
            deleteConfirmModal.addEventListener('hidden.bs.modal', function () {
                 const mainModalInstance = new bootstrap.Modal(document.getElementById('dynamicLinkModal'));
                 mainModalInstance.show();
            });
            
            var editQuickLinkModal = document.getElementById('editQuickLinkModal');
            if (editQuickLinkModal) { 
                editQuickLinkModal.addEventListener('hidden.bs.modal', function () {
                     const mainModalInstance = new bootstrap.Modal(document.getElementById('quickLinkModal'));
                     mainModalInstance.show();
                });
            }
        }
        
        // ** Quick Links Handler **
        var editQuickLinkModal = document.getElementById('editQuickLinkModal');
        if (editQuickLinkModal) { 
            editQuickLinkModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget; 
                
                var id = button.getAttribute('data-id');
                var title = button.getAttribute('data-title');
                var link = button.getAttribute('data-link');
                var icon = button.getAttribute('data-icon');
                var sort = button.getAttribute('data-sort');

                document.getElementById('edit-ql-id').value = id;
                document.getElementById('edit-ql-title').value = title;
                document.getElementById('edit-ql-link').value = link;
                document.getElementById('edit-ql-icon').value = icon;
                document.getElementById('edit-ql-sort').value = sort;
            });
        }

    });
</script>
</body>
</html>