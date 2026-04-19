<?php
session_start();
require_once "includes/dbh.inc.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$my_id = $_SESSION['user_id'];
$other_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

// --- 【修正後的 SQL：確保好友不重複出現】 ---
$friends_sql = "SELECT DISTINCT 
                    users.id, 
                    users.username, 
                    users.profile_img 
                FROM friends 
                JOIN users ON (
                    CASE 
                        WHEN friends.user_id = ? THEN friends.friend_id = users.id 
                        WHEN friends.friend_id = ? THEN friends.user_id = users.id 
                    END
                )
                WHERE (friends.user_id = ? OR friends.friend_id = ?) 
                AND friends.status = 'accepted'
                ORDER BY users.username ASC";

$friends_stmt = $pdo->prepare($friends_sql);
// 依序傳入：我的ID (用於JOIN判斷), 我的ID (用於JOIN判斷), 我的ID (用於WHERE過濾), 我的ID (用於WHERE過濾)
$friends_stmt->execute([$my_id, $my_id, $my_id, $my_id]);
$friends_list = $friends_stmt->fetchAll();

// 1. 自動選取第一個好友（若未指定）
if ($other_id === 0 && !empty($friends_list)) {
    header("Location: chat.php?user_id=" . $friends_list[0]['id']);
    exit();
}

// 2. 獲取聊天對象資訊與訊息 (保持原有邏輯)
$other_user = null;
$messages = [];
if ($other_id > 0) {
    // 安全檢查：確認雙方真的是好友
    $check_sql = "SELECT * FROM friends 
                  WHERE ((user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)) 
                  AND status = 'accepted'";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([$my_id, $other_id, $other_id, $my_id]);
    
    if ($check_stmt->fetch()) {
        // 取得對方資料
        $u_stmt = $pdo->prepare("SELECT username, profile_img FROM users WHERE id = ?");
        $u_stmt->execute([$other_id]);
        $other_user = $u_stmt->fetch();

        // 處理發送訊息
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
            $msg = trim($_POST['message']);
            if ($msg !== '') {
                $insert = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
                $insert->execute([$my_id, $other_id, $msg]);
                header("Location: chat.php?user_id=" . $other_id);
                exit();
            }
        }

        // 取得訊息紀錄
        $msg_sql = "SELECT * FROM messages 
                    WHERE (sender_id = ? AND receiver_id = ?) 
                    OR (sender_id = ? AND receiver_id = ?) 
                    ORDER BY created_at ASC";
        $msg_stmt = $pdo->prepare($msg_sql);
        $msg_stmt->execute([$my_id, $other_id, $other_id, $my_id]);
        $messages = $msg_stmt->fetchAll();

        // 標記已讀
        $update_read = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?");
        $update_read->execute([$other_id, $my_id]);
    } else {
        die("權限不足或好友關係不存在。");
    }
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>聊天室</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* 樣式保持與上一次一致，確保排版正確 */
        body { font-family: sans-serif; background: #f0f2f5; margin: 0; display: flex; flex-direction: column; height: 100vh; overflow: hidden; }
        .chat-header { background: #764ba2; color: white; padding: 15px; display: flex; align-items: center; gap: 10px; flex-shrink: 0; z-index: 10; }
        .chat-header a { color: white; text-decoration: none; font-size: 20px; }
        
        .main-layout { display: flex; flex: 1; overflow: hidden; }

        /* 左側好友列表 */
        .sidebar { width: 280px; background: white; border-right: 1px solid #ddd; display: flex; flex-direction: column; }
        .sidebar-title { padding: 15px; font-weight: bold; border-bottom: 1px solid #eee; color: #764ba2; background: #fff; }
        .friends-list { flex: 1; overflow-y: auto; }
        .friend-item { display: flex; align-items: center; gap: 12px; padding: 12px 15px; text-decoration: none; color: #333; transition: 0.2s; border-bottom: 1px solid #fafafa; }
        .friend-item:hover { background: #f7f7f7; }
        .friend-item.active { background: #eef2ff; border-left: 4px solid #764ba2; }
        .friend-avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; background: #eee; }
        .friend-name { font-size: 14px; font-weight: 500; }

        /* 右側聊天內容 */
        .chat-main { flex: 1; display: flex; flex-direction: column; background: #f0f2f5; position: relative; }
        .chat-container { flex: 1; overflow-y: auto; padding: 20px; display: flex; flex-direction: column; gap: 10px; }
        
        .msg { max-width: 70%; padding: 10px 15px; border-radius: 18px; font-size: 14px; line-height: 1.4; word-wrap: break-word; }
        .msg.sent { align-self: flex-end; background: #667eea; color: white; border-bottom-right-radius: 2px; }
        .msg.received { align-self: flex-start; background: white; color: #333; border-bottom-left-radius: 2px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .msg-time { font-size: 10px; opacity: 0.7; margin-top: 5px; display: block; text-align: right; }

        .input-area { background: white; padding: 15px; display: flex; gap: 10px; border-top: 1px solid #ddd; }
        .input-area input { flex: 1; border: 1px solid #ddd; padding: 12px; border-radius: 25px; outline: none; }
        .input-area button { background: #764ba2; color: white; border: none; padding: 0 25px; border-radius: 25px; cursor: pointer; font-weight: bold; }

        @media (max-width: 600px) {
            .sidebar { width: 70px; }
            .friend-name, .sidebar-title { display: none; }
            .friend-item { justify-content: center; padding: 15px 5px; }
        }
    </style>
</head>
<body>

<div class="chat-header">
    <a href="index.php">←</a>
    <?php if ($other_user): ?>
        <img src="<?= !empty($other_user['profile_img']) ? 'uploads/users_profile_img/'.$other_user['profile_img'] : 'uploads/default_avatar.png' ?>" style="width:30px; height:30px; border-radius:50%; object-fit:cover;">
        <span><?= htmlspecialchars($other_user['username']) ?></span>
    <?php else: ?>
        <span>私訊對話</span>
    <?php endif; ?>
</div>

<div class="main-layout">
    <div class="sidebar">
        <div class="sidebar-title">好友列表</div>
        <div class="friends-list">
            <?php foreach ($friends_list as $f): ?>
                <a href="chat.php?user_id=<?= $f['id'] ?>" class="friend-item <?= $f['id'] == $other_id ? 'active' : '' ?>">
                    <img src="<?= !empty($f['profile_img']) ? 'uploads/users_profile_img/'.$f['profile_img'] : 'uploads/default_avatar.png' ?>" class="friend-avatar">
                    <span class="friend-name"><?= htmlspecialchars($f['username']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="chat-main">
        <?php if ($other_id > 0): ?>
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
        <?php else: ?>
            <div style="flex:1; display:flex; align-items:center; justify-content:center; color:#999;">
                請選擇好友開始對話
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    const chatBox = document.getElementById('chatBox');
    if (chatBox) {
        chatBox.scrollTop = chatBox.scrollHeight;
    }
</script>

</body>
</html>