<?php
session_start();
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SESSION["user_id"])) {
    $post_id = $_POST["post_id"];
    $content = $_POST["content"];
    $user_id = $_SESSION["user_id"];

    try {
        require_once "dbh.inc.php";
        $sql = "INSERT INTO comments (post_id, user_id, content) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$post_id, $user_id, $content]);

        // 成功後跳回原本那篇文章
        header("Location: ../view_post.php?id=" . $post_id);
        exit();
    } catch (PDOException $e) {
        die("留言失敗: " . $e->getMessage());
    }
} else {
    header("Location: ../index.php");
    exit();
}