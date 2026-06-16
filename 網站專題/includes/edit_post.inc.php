<?php
session_start();
require_once "dbh.inc.php";

if (isset($_POST["submit_edit"])) {
    $post_id = $_POST["post_id"];
    $title = $_POST["title"];
    $category_id = $_POST["category_id"];
    $content = $_POST["content"]; // 富文本 HTML 結構
    $user_id = $_SESSION["user_id"];

    try {
        // 確保目前登入的使用者確實是這篇文章的作者
        $check_sql = "SELECT id FROM posts WHERE id = ? AND user_id = ?";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([$post_id, $user_id]);
        if (!$check_stmt->fetch()) {
            header("Location: ../index.php?error=unauthorized");
            exit();
        }

        // ==========================================
        // 步驟 1: 處理「刪除舊圖片」
        // ==========================================
        if (isset($_POST['delete_imgs']) && !empty($_POST['delete_imgs'])) {
            foreach ($_POST['delete_imgs'] as $del_img_id) {
                // 先撈出檔案名稱，以便刪除硬碟中的實體檔案
                $img_info_sql = "SELECT image_path FROM post_images WHERE id = ? AND post_id = ?";
                $img_info_stmt = $pdo->prepare($img_info_sql);
                $img_info_stmt->execute([$del_img_id, $post_id]);
                $img_data = $img_info_stmt->fetch();

                if ($img_data) {
                    $target_file = "../uploads/post_imgs/" . $img_data['image_path'];
                    if (file_exists($target_file)) {
                        unlink($target_file); // 刪除實體檔案
                    }
                    // 刪除資料庫紀錄
                    $del_sql = "DELETE FROM post_images WHERE id = ?";
                    $del_stmt = $pdo->prepare($del_sql);
                    $del_stmt->execute([$del_img_id]);
                }
            }
        }

        // ==========================================
        // 步驟 2: 處理「新圖片上傳與富文本路徑替換」
        // ==========================================
        // 利用 PHP 的 DOMDocument 來精準解析並修改富文本中的 <img> 標籤
        if (!empty($_FILES['new_post_imgs']['name'][0])) {
            $upload_dir = "../uploads/post_imgs/";
            
            // 如果資料夾不存在就建立它
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            // 為了不讓中文或特殊字元亂碼，轉換 HTML 編碼
            $dom = new DOMDocument();
            // 加上 UTF-8 標頭防止亂碼
            @$dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            $imgs = $dom->getElementsByTagName('img');

            $uploaded_count = 0;
            
            // 跑迴圈處理多檔案上傳
            foreach ($_FILES['new_post_imgs']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['new_post_imgs']['error'][$key] === UPLOAD_ERR_OK) {
                    
                    $file_ext = pathinfo($_FILES['new_post_imgs']['name'][$key], PATHINFO_EXTENSION);
                    // 產生不重複的全新檔名，防範檔名衝突與權限漏洞
                    $new_file_name = "post_" . $post_id . "_" . uniqid() . "." . $file_ext;
                    $destination = $upload_dir . $new_file_name;

                    if (move_uploaded_file($tmp_name, $destination)) {
                        // 1. 寫入 post_images 資料表
                        $ins_img_sql = "INSERT INTO post_images (post_id, image_path) VALUES (?, ?)";
                        $ins_img_stmt = $pdo->prepare($ins_img_sql);
                        $ins_img_stmt->execute([$post_id, $new_file_name]);

                        // 2. 找到富文本中對應的第 N 個含有 data-type="new_img" 的標籤
                        // 將它的 src 替換成真正的伺服器儲存路徑
                        $match_index = 0;
                        foreach ($imgs as $img) {
                            if ($img->getAttribute('data-type') === 'new_img') {
                                if ($match_index == $uploaded_count) {
                                    // 替換路徑 (注意：在 index 觀看時，相對路徑通常是從根目錄算起，因此改為 uploads/post_imgs/...)
                                    $img->setAttribute('src', "uploads/post_imgs/" . $new_file_name);
                                    // 移除臨時屬性
                                    $img->removeAttribute('data-type');
                                    $img->removeAttribute('data-index');
                                    break;
                                }
                                $match_index++;
                            }
                        }
                        $uploaded_count++;
                    }
                }
            }
            // 將替換完圖片路徑後的 HTML 結構重新存回 $content 變數
            $content = $dom->saveHTML();
            $content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');
        }

        // ==========================================
        // 步驟 3: 處理「新影片上傳與富文本路徑替換」（選填）
        // ==========================================
        // 這裡的邏輯與圖片完全相同，如果您有需要啟用影片功能，可以解開此處：
        /*
        if (!empty($_FILES['new_post_vids']['name'][0])) {
            $dom = new DOMDocument();
            @$dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            $videos = $dom->getElementsByTagName('video');
            $uploaded_vid_count = 0;

            foreach ($_FILES['new_post_vids']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['new_post_vids']['error'][$key] === UPLOAD_ERR_OK) {
                    $file_ext = pathinfo($_FILES['new_post_vids']['name'][$key], PATHINFO_EXTENSION);
                    $new_vid_name = "vid_" . $post_id . "_" . uniqid() . "." . $file_ext;
                    
                    if (move_uploaded_file($tmp_name, "../uploads/post_imgs/" . $new_vid_name)) {
                        foreach ($videos as $vid) {
                            if ($vid->getAttribute('data-type') === 'new_vid') {
                                if ($uploaded_vid_count == $key) {
                                    $vid->setAttribute('src', "uploads/post_imgs/" . $new_vid_name);
                                    $vid->removeAttribute('data-type');
                                    $vid->removeAttribute('data-index');
                                    break;
                                }
                            }
                        }
                    }
                }
            }
            $content = $dom->saveHTML();
            $content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');
        }
        */

        // ==========================================
        // 步驟 4: 更新文章標題與內容到資料庫
        // ==========================================
        $sql = "UPDATE posts SET title = ?, content = ?, category_id = ? 
                WHERE id = ? AND user_id = ?";
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([$title, $content, $category_id, $post_id, $user_id]);

        if ($result) {
            header("Location: ../view_post.php?id=$post_id&edit=success");
            exit();
        } else {
            header("Location: ../index.php?error=updatefailed");
            exit();
        }

    } catch (PDOException $e) {
        die("更新失敗: " . $e->getMessage());
    }
} else {
    header("Location: ../index.php");
    exit();
}