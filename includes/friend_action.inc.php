<?php
session_start();
require_once "dbh.inc.php";

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => '請先登入']);
    exit();
}

$user_id = $_SESSION['user_id'];

// 同時支援 GET（刪除好友用）與 POST（加好友/接受好友用）
$friend_id = 0;
if (isset($_GET['friend_id'])) {
    $friend_id = (int)$_GET['friend_id'];
} elseif (isset($_POST['friend_id'])) {
    $friend_id = (int)$_POST['friend_id'];
}

if ($friend_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => '無效的用戶 ID']);
    exit();
}

$action = $_GET['action'] ?? ($_POST['action'] ?? 'toggle');

try {
    if ($action === 'accept' || isset($_POST['accept_friend'])) {
        // 接受好友請求：將狀態改為 accepted
        $sql = "UPDATE friends SET status = 'accepted' WHERE user_id = ? AND friend_id = ?";
        $pdo->prepare($sql)->execute([$friend_id, $user_id]); // 注意：請求者是 friend_id
        
        // 為了讓雙方列表都有彼此，插入一條反向的已接受資料
        $sql_inverse = "INSERT IGNORE INTO friends (user_id, friend_id, status) VALUES (?, ?, 'accepted')";
        $pdo->prepare($sql_inverse)->execute([$user_id, $friend_id]);
        
        header("Location: ../profile.php?id=" . $friend_id . "&success=accepted");
        exit();
    } 
    else {
        // 原本的切換邏輯 (申請/取消申請/刪除好友)
        $check_sql = "SELECT * FROM friends WHERE user_id = ? AND friend_id = ?";
        $stmt = $pdo->prepare($check_sql);
        $stmt->execute([$user_id, $friend_id]);
        $relation = $stmt->fetch();

        if ($relation) {
            // 如果存在關係（無論是 pending 還是 accepted），刪除雙方的關係
            $delete_sql = "DELETE FROM friends WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)";
            $pdo->prepare($delete_sql)->execute([$user_id, $friend_id, $friend_id, $user_id]);

            // ✅ 刪除好友後：重導向回對方個人頁面並顯示成功訊息
            header("Location: ../profile.php?id=" . $friend_id . "&success=removed");
            exit();
        } else {
            // 新增好友申請
            $insert_sql = "INSERT INTO friends (user_id, friend_id, status) VALUES (?, ?, 'pending')";
            $pdo->prepare($insert_sql)->execute([$user_id, $friend_id]);

            header("Location: ../profile.php?id=" . $friend_id . "&success=sent");
            exit();
        }
    }
} catch (PDOException $e) {
    // 若有錯誤，重導向回個人頁並帶錯誤提示
    header("Location: ../profile.php?id=" . $friend_id . "&error=1");
    exit();
}