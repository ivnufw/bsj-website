<?php
// news_detail.php
session_start();
// เชื่อมต่อ DB และกำหนดตัวแปรพื้นฐาน
include 'db_connect.php'; 

$news_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$upload_dir = 'uploads/'; 

// 1. ดึงข้อมูลข่าวสารฉบับเต็ม
$news = null;
if ($news_id > 0) {
    $sql_news = "SELECT * FROM news_items WHERE id = $news_id";
    $result_news = $conn->query($sql_news);
    
    if ($result_news && $result_news->num_rows > 0) {
        $news = $result_news->fetch_assoc();
        
        // 2. ดึงรูปภาพแกลเลอรี
        $sql_gallery = "SELECT image_file FROM news_gallery WHERE news_id = $news_id ORDER BY id ASC";
        $result_gallery = $conn->query($sql_gallery);
        $news['gallery'] = [];
        if ($result_gallery) {
            while ($img = $result_gallery->fetch_assoc()) {
                $news['gallery'][] = $img['image_file'];
            }
        }
    }
}

// 3. ดึงข้อมูล Banner และ Navbar (สำหรับ Header Template)
$page_config = ['banner_image' => 'akarn.png', 'header_title' => 'ข่าวสารและกิจกรรม'];
$sql_page_config = "SELECT banner_image, header_title FROM page_config WHERE id = 1";
$result_page_config = $conn->query($sql_page_config);
if ($result_page_config && $result_page_config->num_rows > 0) {
    $page_config = $result_page_config->fetch_assoc();
}

$school_name_th = "โรงเรียนบ้านสวน (จั่นอนุสรณ์)";
$school_name_en = "Bansuan Jananusorn School";

$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $news ? htmlspecialchars($news['title_th']) : $page_config['header_title']; ?> | <?php echo $school_name_th; ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;600;700&display=swap">
    <link rel="stylesheet" href="style.css"> 
    
    <style>
        .hero-section-news {
            background: url('<?php echo $page_config['banner_image']; ?>') no-repeat center center / cover; 
            color: white;
            padding: 80px 0 60px;
            text-shadow: 2px 2px 5px rgba(0,0,0,0.8); 
            position: relative;
            overflow: hidden;
        }
        /* จำลอง NavBar Custom จาก index.php */
        .navbar-custom {
            background-color: var(--dark-secondary) !important;
        }
        .article-content img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            margin: 15px 0;
        }
        .gallery-item {
            cursor: pointer;
            transition: transform 0.2s;
        }
        .gallery-item:hover {
            transform: scale(1.05);
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-custom">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="index.php">
            <img src="janLogo.png" alt="School Logo" class="me-2" style="height: 40px !important;"> 
            <span class="fw-bold fs-4" style="color: var(--pink-primary) !important;">BSJ</span>
        </a>
        <span class="text-white ms-auto">หน้าหลัก > <?php echo $page_config['header_title']; ?></span>
    </div>
</nav>

<header class="hero-section-news text-center">
    <div class="container">
        <h1 class="display-3 mb-3"><?php echo $page_config['header_title']; ?></h1>
        <?php if ($news): ?>
            <p class="lead mb-0"><?php echo htmlspecialchars($news['title_th']); ?></p>
        <?php endif; ?>
    </div>
</header>
<main class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <?php if ($news): ?>
                <div class="card shadow-lg p-4 mb-4">
                    <h2 class="card-title text-center text-primary mb-3"><?php echo htmlspecialchars($news['title_th']); ?></h2>
                    <p class="text-muted text-center small mb-4">
                        <i class="fas fa-calendar-alt me-1"></i> เผยแพร่เมื่อ: <?php echo date('d M Y', strtotime($news['created_at'])); ?> 
                        | <i class="fas fa-clock me-1"></i> เวลา: <?php echo date('H:i', strtotime($news['created_at'])); ?> น.
                    </p>
                    
                    <div class="article-content border-top pt-4">
                        <?php echo $news['full_content']; ?>
                    </div>
                    
                    <?php if (!empty($news['gallery'])): ?>
                        <h4 class="mt-5 mb-3 border-bottom pb-2"><i class="fas fa-images me-2 text-pink-primary"></i> แกลเลอรี่กิจกรรม</h4>
                        <div class="row row-cols-1 row-cols-md-3 g-4">
                            <?php foreach ($news['gallery'] as $image_file): ?>
                                <div class="col">
                                    <img src="<?php echo $upload_dir . htmlspecialchars($image_file); ?>" 
                                         class="img-fluid rounded shadow gallery-item" 
                                         alt="รูปภาพกิจกรรม"
                                         data-bs-toggle="modal" 
                                         data-bs-target="#imageModal" 
                                         onclick="showImageModal('<?php echo $upload_dir . htmlspecialchars($image_file); ?>', '<?php echo htmlspecialchars($news['title_th']); ?>')">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="text-center mt-5">
                         <a href="index.php" class="btn btn-lg btn-secondary rounded-pill px-5">
                            <i class="fas fa-chevron-circle-left me-2"></i> กลับหน้าแรก
                         </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-warning text-center">ไม่พบรายการข่าวสารนี้</div>
            <?php endif; ?>
        </div>
    </div>
</main>

<div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="imageModalLabel"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <img id="modalImageContent" src="" class="img-fluid" alt="รูปภาพกิจกรรมขนาดใหญ่">
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function showImageModal(imageSrc, title) {
        document.getElementById('modalImageContent').src = imageSrc;
        document.getElementById('imageModalLabel').textContent = title;
    }
</script>
</body>
</html>