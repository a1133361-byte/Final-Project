<?php
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["submit_post"]) && isset($_SESSION["user_id"])) {
    
    $title = $_POST["title"];
    $content = $_POST["content"];
    $category_id = $_POST["category_id"];
    $user_id = $_SESSION["user_id"]; 

    try {
        require_once "dbh.inc.php";

        // 1. 先新增文章內容到 posts 表
        $query = "INSERT INTO posts (title, content, user_id, category_id) VALUES (?, ?, ?, ?);";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$title, $content, $user_id, $category_id]);

        // 取得剛剛新增的文章 ID (為了關聯圖片)
        $last_post_id = $pdo->lastInsertId();

        // 2. 處理多圖上傳
        if (!empty($_FILES['post_imgs']['name'][0])) {
            $files = $_FILES['post_imgs'];
            $uploaded_images = [];
            $upload_dir = '../uploads/post_imgs/';

            // 確保資料夾存在
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            foreach ($files['name'] as $key => $name) {
                if ($files['error'][$key] === 0) {
                    $file_tmp = $files['tmp_name'][$key];
                    $file_ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    $allowed = ['jpg', 'jpeg', 'png', 'gif'];

                    if (in_array($file_ext, $allowed)) {
                        // 重新命名檔案，避免重複 (例如: 65d2f1e2a3b4c_1.jpg)
                        $new_file_name = uniqid('', true) . "_" . ($key + 1) . "." . $file_ext;
                        $destination = $upload_dir . $new_file_name;

                        if (move_uploaded_file($file_tmp, $destination)) {
                            // 3. 將圖片路徑存入資料庫
                            $img_query = "INSERT INTO post_images (post_id, image_path) VALUES (?, ?);";
                            $img_stmt = $pdo->prepare($img_query);
                            $img_stmt->execute([$last_post_id, $new_file_name]);
                        }
                    }
                }
            }
        }

        header("Location: ../index.php?post=success");
        exit();
    } catch (PDOException $e) {
        die("發文失敗: " . $e->getMessage());
    }
} else {
    header("Location: ../index.php");
    exit();
}