<?php
session_start();
require_once "dbh.inc.php";

header('Content-Type: application/json'); // 告訴瀏覽器我們要回傳 JSON

if (isset($_GET['post_id']) && isset($_SESSION['user_id'])) {
    $post_id = $_GET['post_id'];
    $user_id = $_SESSION['user_id'];

    // 1. 檢查並切換按讚狀態 (Toggle)
    $check_sql = "SELECT * FROM likes WHERE user_id = ? AND post_id = ?";
    $stmt = $pdo->prepare($check_sql);
    $stmt->execute([$user_id, $post_id]);
    
    $is_liked = false;
    if ($stmt->rowCount() == 0) {
        $sql = "INSERT INTO likes (user_id, post_id) VALUES (?, ?)";
        $pdo->prepare($sql)->execute([$user_id, $post_id]);
        $is_liked = true;
    } else {
        $sql = "DELETE FROM likes WHERE user_id = ? AND post_id = ?";
        $pdo->prepare($sql)->execute([$user_id, $post_id]);
        $is_liked = false;
    }

    // 2. 抓取最新的總按讚數
    $count_sql = "SELECT COUNT(*) FROM likes WHERE post_id = ?";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute([$post_id]);
    $new_count = $count_stmt->fetchColumn();

    // 3. 回傳結果給 JavaScript
    echo json_encode([
        'status' => 'success',
        'is_liked' => $is_liked,
        'new_count' => $new_count
    ]);
    exit();
}