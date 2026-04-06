<?php
session_start();
require_once "dbh.inc.php";

if (!isset($_SESSION['user_id']) || !isset($_GET['friend_id'])) {
    echo json_encode(['status' => 'error', 'message' => '無效請求']);
    exit();
}

$user_id = $_SESSION['user_id'];
$friend_id = (int)$_GET['friend_id'];
$action = $_GET['action'] ?? 'toggle'; // 新增 action 參數來區分操作

try {
    if ($action === 'accept') {
        // 接受好友請求：將狀態改為 accepted
        $sql = "UPDATE friends SET status = 'accepted' WHERE user_id = ? AND friend_id = ?";
        $pdo->prepare($sql)->execute([$friend_id, $user_id]); // 注意：請求者是 friend_id
        
        // 為了讓雙方列表都有彼此，我們插入一條反向的已接受資料
        $sql_inverse = "INSERT INTO friends (user_id, friend_id, status) VALUES (?, ?, 'accepted')";
        $pdo->prepare($sql_inverse)->execute([$user_id, $friend_id]);
        
        echo json_encode(['status' => 'success', 'message' => '已成為好友']);
    } 
    else {
        // 原本的切換邏輯 (申請/取消申請/刪除好友)
        $check_sql = "SELECT * FROM friends WHERE user_id = ? AND friend_id = ?";
        $stmt = $pdo->prepare($check_sql);
        $stmt->execute([$user_id, $friend_id]);
        $relation = $stmt->fetch();

        if ($relation) {
            // 如果存在關係（無論是 pending 還是 accepted），就刪除雙方的關係
            $delete_sql = "DELETE FROM friends WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)";
            $pdo->prepare($delete_sql)->execute([$user_id, $friend_id, $friend_id, $user_id]);
            echo json_encode(['status' => 'success', 'action' => 'removed']);
        } else {
            // 新增好友申請
            $insert_sql = "INSERT INTO friends (user_id, friend_id, status) VALUES (?, ?, 'pending')";
            $pdo->prepare($insert_sql)->execute([$user_id, $friend_id]);
            echo json_encode(['status' => 'success', 'action' => 'pending']);
        }
    }
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}