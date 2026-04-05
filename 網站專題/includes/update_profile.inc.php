<?php
session_start();
require_once "dbh.inc.php";

if (isset($_POST["submit_profile"])) {
    $uid = $_SESSION["user_id"];
    $bio = $_POST["bio"];
    $file = $_FILES["profile_image"];

    try {
        // 1. 先抓取舊資料（為了獲取舊頭像檔名，方便之後刪除空間）
        $sql = "SELECT profile_img FROM users WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$uid]);
        $user = $stmt->fetch();
        $old_img = $user['profile_img'];

        $new_img_name = $old_img; // 預設為舊檔名，如果沒換圖就不動

        // 2. 處理圖片上傳邏輯 (如果有選擇檔案且沒錯誤)
        if ($file['error'] === 0) {
            $fileName = $file['name'];
            $fileTmpName = $file['tmp_name'];
            $fileSize = $file['size'];
            
            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];

            if (in_array($fileExt, $allowed)) {
                if ($fileSize < 2000000) { // 限制 2MB
                    // 生成唯一檔名
                    $new_img_name = "avatar_" . $uid . "_" . uniqid('', true) . "." . $fileExt;
                    $fileDestination = "../uploads/users_profile_img/" . $new_img_name;

                    if (move_uploaded_file($fileTmpName, $fileDestination)) {
                        // 修正點：加上判斷，確保不刪除預設預設圖片
                        // 假設你的預設圖案檔名就叫 'default_avatar.png'
                        if (!empty($old_img) && $old_id != "default_avatar.png") {
                            $old_file_path = "../uploads/users_profile_img/" . $old_img;
                            
                            // 檢查檔案是否存在，且檔名不是預設圖，才刪除
                            if (file_exists($old_file_path) && $old_img !== "default_avatar.png") {
                                unlink($old_file_path);
                            }
                        }
                    }
                } else {
                    header("Location: ../edit_profile.php?error=filetoolarge");
                    exit();
                }
            } else {
                header("Location: ../edit_profile.php?error=invalidtype");
                exit();
            }
        }

        // 3. 更新資料庫 (包含圖片名稱與簡介)
        $update_sql = "UPDATE users SET profile_img = ?, bio = ? WHERE id = ?";
        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->execute([$new_img_name, $bio, $uid]);

        // 4. 重要：更新 Session 資訊
        $_SESSION["profile_img"] = $new_img_name;
        // 如果你有存 bio 在 session 的話也要更新（通常建議只存關鍵資訊）

        header("Location: ../profile.php?id=$uid&update=success");
        exit();

    } catch (PDOException $e) {
        die("更新失敗: " . $e->getMessage());
    }
} else {
    header("Location: ../index.php");
    exit();
}