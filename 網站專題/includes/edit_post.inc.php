<?php
session_start();
require_once "dbh.inc.php";

if (isset($_POST["submit_edit"])) {
    $post_id = $_POST["post_id"];
    $title = $_POST["title"];
    $category_id = $_POST["category_id"];
    $content = $_POST["content"];
    $user_id = $_SESSION["user_id"];

    // 再次檢查使用者是否真的有權限（安全第一）
    try {
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