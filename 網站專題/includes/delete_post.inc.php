<?php
session_start();
require_once "dbh.inc.php";

// 1. 檢查是否登入，且是否有 ID
if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header("Location: ../index.php");
    exit();
}

$post_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

try {
    // 2. 關鍵：刪除條件必須同時符合文章 ID 和 作者 ID
    $sql = "DELETE FROM posts WHERE id = ? AND user_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$post_id, $user_id]);

    if ($stmt->rowCount() > 0) {
        // 刪除成功
        header("Location: ../index.php?delete=success");
    } else {
        // 刪除失敗（可能是 ID 不對，或是這個人根本不是作者）
        header("Location: ../view_post.php?id=$post_id&error=unauthorized");
    }
    exit();

} catch (PDOException $e) {
    die("刪除失敗: " . $e->getMessage());
}