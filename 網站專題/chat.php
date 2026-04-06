<?php
session_start();
require_once "includes/dbh.inc.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$my_id = $_SESSION['user_id'];
$other_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

// 檢查是否為好友 (安全性檢查)
$check_sql = "SELECT * FROM friends WHERE user_id = ? AND friend_id = ? AND status = 'accepted'";
$check_stmt = $pdo->prepare($check_sql);
$check_stmt->execute([$my_id, $other_id]);
$is_friend = $check_stmt->fetch();

if (!$is_friend) {
    die("你們還不是好友，無法私訊。");
}

// 取得對方資訊
$u_stmt = $pdo->prepare("SELECT username, profile_img FROM users WHERE id = ?");
$u_stmt->execute([$other_id]);
$other_user = $u_stmt->fetch();

// 處理發送訊息
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $msg = trim($_POST['message']);
    if ($msg !== '') {
        $insert = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
        $insert->execute([$my_id, $other_id, $msg]);
        header("Location: chat.php?user_id=" . $other_id); // 刷新
        exit();
    }
}

// 取得對話紀錄
$msg_sql = "SELECT * FROM messages 
            WHERE (sender_id = ? AND receiver_id = ?) 
            OR (sender_id = ? AND receiver_id = ?) 
            ORDER BY created_at ASC";
$msg_stmt = $pdo->prepare($msg_sql);
$msg_stmt->execute([$my_id, $other_id, $other_id, $my_id]);
$messages = $msg_stmt->fetchAll();

// 將對方的訊息設為已讀
$update_read = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?");
$update_read->execute([$other_id, $my_id]);
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>與 <?= htmlspecialchars($other_user['username']) ?> 的對話</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: sans-serif; background: #f0f2f5; margin: 0; display: flex; flex-direction: column; height: 100vh; }
        .chat-header { background: #764ba2; color: white; padding: 15px; display: flex; align-items: center; gap: 10px; }
        .chat-header a { color: white; text-decoration: none; font-size: 20px; }
        
        .chat-container { flex: 1; overflow-y: auto; padding: 20px; display: flex; flex-direction: column; gap: 10px; }
        
        .msg { max-width: 70%; padding: 10px 15px; border-radius: 18px; font-size: 14px; line-height: 1.4; position: relative; }
        .msg.sent { align-self: flex-end; background: #667eea; color: white; border-bottom-right-radius: 2px; }
        .msg.received { align-self: flex-start; background: white; color: #333; border-bottom-left-radius: 2px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .msg-time { font-size: 10px; opacity: 0.7; margin-top: 5px; display: block; text-align: right; }

        .input-area { background: white; padding: 15px; display: flex; gap: 10px; border-top: 1px solid #ddd; }
        .input-area input { flex: 1; border: 1px solid #ddd; padding: 10px; border-radius: 20px; outline: none; }
        .input-area button { background: #764ba2; color: white; border: none; padding: 0 20px; border-radius: 20px; cursor: pointer; font-weight: bold; }
    </style>
</head>
<body>

<div class="chat-header">
    <a href="index.php">←</a>
    <img src="<?= !empty($other_user['profile_img']) ? 'uploads/users_profile_img/'.$other_user['profile_img'] : 'uploads/default_avatar.png' ?>" style="width:30px; height:30px; border-radius:50%;">
    <span><?= htmlspecialchars($other_user['username']) ?></span>
</div>

<div class="chat-container" id="chatBox">
    <?php foreach ($messages as $m): ?>
        <div class="msg <?= $m['sender_id'] == $my_id ? 'sent' : 'received' ?>">
            <?= htmlspecialchars($m['message']) ?>
            <span class="msg-time"><?= date('H:i', strtotime($m['created_at'])) ?></span>
        </div>
    <?php endforeach; ?>
</div>

<form class="input-area" method="POST">
    <input type="text" name="message" placeholder="輸入訊息..." autocomplete="off" required autofocus>
    <button type="submit">發送</button>
</form>

<script>
    // 自動捲動到底部
    const chatBox = document.getElementById('chatBox');
    chatBox.scrollTop = chatBox.scrollHeight;
</script>

</body>
</html>