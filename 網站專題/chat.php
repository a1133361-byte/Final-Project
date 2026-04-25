<?php
session_start();
require_once "includes/dbh.inc.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$my_id = $_SESSION['user_id'];
$other_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

// --- 1. 獲取好友列表 ---
$friends_sql = "SELECT DISTINCT users.id, users.username, users.profile_img FROM friends JOIN users ON (CASE WHEN friends.user_id = ? THEN friends.friend_id = users.id WHEN friends.friend_id = ? THEN friends.user_id = users.id END) WHERE (friends.user_id = ? OR friends.friend_id = ?) AND friends.status = 'accepted' ORDER BY users.username ASC";
$friends_stmt = $pdo->prepare($friends_sql);
$friends_stmt->execute([$my_id, $my_id, $my_id, $my_id]);
$friends_list = $friends_stmt->fetchAll();

// 自動選取第一個好友 (僅限一般載入時)
if ($other_id === 0 && !empty($friends_list) && !isset($_GET['ajax'])) {
    header("Location: chat.php?user_id=" . $friends_list[0]['id']);
    exit();
}

// --- 2. 處理發送訊息 (支援 AJAX) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $other_id > 0) {
    $msg = trim($_POST['message'] ?? '');
    $msg_type = 'text';
    $final_content = $msg;
    $upload_ok = true;
    $new_img_html = '';

    if (isset($_FILES['chat_image']) && $_FILES['chat_image']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['chat_image']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (in_array($ext, $allowed)) {
            $folder = "uploads/chat_imgs/";
            if (!is_dir($folder)) mkdir($folder, 0777, true);
            $newName = "IMG_" . date("Ymd_His") . "_" . uniqid() . "." . $ext;
            if (move_uploaded_file($_FILES['chat_image']['tmp_name'], $folder . $newName)) {
                $final_content = $newName;
                $msg_type = 'image';
            }
        }
    }

    if ($upload_ok && ($msg_type === 'image' || $msg !== '')) {
        $insert = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message, msg_type) VALUES (?, ?, ?, ?)");
        $insert->execute([$my_id, $other_id, $final_content, $msg_type]);
        
        // 如果是 AJAX 請求，直接回傳成功並結束，不進行跳轉
        if (isset($_GET['ajax'])) {
            echo json_encode([
                'status' => 'success', 
                'msg_type' => $msg_type, 
                'content' => $final_content, 
                'time' => date('H:i')
            ]);
            exit();
        }

        header("Location: chat.php?user_id=" . $other_id);
        exit();
    }
}

// --- 3. 獲取聊天資訊 ---
$other_user = null;
$messages = [];
if ($other_id > 0) {
    $u_stmt = $pdo->prepare("SELECT username, profile_img FROM users WHERE id = ?");
    $u_stmt->execute([$other_id]);
    $other_user = $u_stmt->fetch();
    $msg_stmt = $pdo->prepare("SELECT * FROM messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) ORDER BY created_at ASC");
    $msg_stmt->execute([$my_id, $other_id, $other_id, $my_id]);
    $messages = $msg_stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>聊天室</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* 你原本的樣式全部保留 */
        body { font-family: sans-serif; background: #f0f2f5; margin: 0; display: flex; flex-direction: column; height: 100vh; overflow: hidden; }
        .chat-header { background: #764ba2; color: white; padding: 15px; display: flex; align-items: center; gap: 10px; flex-shrink: 0; z-index: 10; }
        .chat-header a { color: white; text-decoration: none; font-size: 20px; }
        .main-layout { display: flex; flex: 1; overflow: hidden; }
        .sidebar { width: 280px; background: white; border-right: 1px solid #ddd; display: flex; flex-direction: column; }
        .sidebar-title { padding: 15px; font-weight: bold; border-bottom: 1px solid #eee; color: #764ba2; }
        .friends-list { flex: 1; overflow-y: auto; }
        .friend-item { display: flex; align-items: center; gap: 12px; padding: 12px 15px; text-decoration: none; color: #333; transition: 0.2s; border-bottom: 1px solid #fafafa; }
        .friend-item:hover { background: #f7f7f7; }
        .friend-item.active { background: #eef2ff; border-left: 4px solid #764ba2; }
        .friend-avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; background: #eee; }
        .chat-main { flex: 1; display: flex; flex-direction: column; background: #f0f2f5; }
        .chat-container { flex: 1; overflow-y: auto; padding: 20px; display: flex; flex-direction: column; gap: 10px; }
        .msg { max-width: 70%; padding: 10px 15px; border-radius: 18px; font-size: 14px; line-height: 1.4; position: relative; }
        .msg.sent { align-self: flex-end; background: #667eea; color: white; border-bottom-right-radius: 2px; }
        .msg.received { align-self: flex-start; background: white; color: #333; border-bottom-left-radius: 2px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .msg-time { font-size: 10px; opacity: 0.7; margin-top: 5px; display: block; text-align: right; }
        
        /* 修改：圖片固定大小 */
        .msg img { 
            width: 200px; 
            height: 200px; 
            object-fit: cover; /* 確保圖片不變形，多餘部分裁掉 */
            border-radius: 10px; 
            display: block; 
            margin-top: 5px; 
            cursor: zoom-in; 
        }

        .input-area { background: white; padding: 15px; display: flex; gap: 10px; border-top: 1px solid #ddd; align-items: center; }
        .input-area input[type="text"] { flex: 1; border: 1px solid #ddd; padding: 12px; border-radius: 25px; outline: none; }
        .input-area button { background: #764ba2; color: white; border: none; padding: 10px 20px; border-radius: 25px; cursor: pointer; font-weight: bold; }
        .upload-btn { cursor: pointer; color: #764ba2; font-size: 24px; display: flex; align-items: center; }
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
                        <?php if ($m['msg_type'] === 'image'): ?>
                            <img src="uploads/chat_imgs/<?= htmlspecialchars($m['message']) ?>" onclick="window.open(this.src)">
                        <?php else: ?>
                            <?= htmlspecialchars($m['message']) ?>
                        <?php endif; ?>
                        <span class="msg-time"><?= date('H:i', strtotime($m['created_at'])) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- 修改：加入 id 方便 JS 抓取 -->
            <form class="input-area" id="chatForm" method="POST" enctype="multipart/form-data">
                <label for="img_input" class="upload-btn" title="傳送圖片">🖼️</label>
                <input type="file" name="chat_image" id="img_input" accept="image/*" style="display:none;" onchange="handleImgSelect(this)">
                
                <input type="text" name="message" id="msg_field" placeholder="輸入訊息..." autocomplete="off" autofocus>
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
    const chatForm = document.getElementById('chatForm');

    if (chatBox) {
        chatBox.scrollTop = chatBox.scrollHeight;
    }

    function handleImgSelect(input) {
        if (input.files && input.files[0]) {
            const fileName = input.files[0].name;
            document.getElementById('msg_field').placeholder = "已選取圖片: " + fileName;
            document.getElementById('msg_field').style.background = "#fff9c4";
        }
    }

    // --- 關鍵修改：AJAX 發送 ---
    if (chatForm) {
        chatForm.onsubmit = function(e) {
            e.preventDefault(); // 阻止頁面刷新跳轉

            let formData = new FormData(this);
            // 發送至當前頁面，並帶上 ajax 參數
            fetch(`chat.php?user_id=<?= $other_id ?>&ajax=1`, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    // 動態產生新的訊息氣泡
                    let newMsg = document.createElement('div');
                    newMsg.className = 'msg sent';
                    
                    let content = data.msg_type === 'image' 
                        ? `<img src="uploads/chat_imgs/${data.content}" onclick="window.open(this.src)">` 
                        : escapeHtml(data.content);
                    
                    newMsg.innerHTML = `${content}<span class="msg-time">${data.time}</span>`;
                    chatBox.appendChild(newMsg);
                    
                    // 清空表單與還原樣式
                    chatForm.reset();
                    document.getElementById('msg_field').placeholder = "輸入訊息...";
                    document.getElementById('msg_field').style.background = "";
                    chatBox.scrollTop = chatBox.scrollHeight;
                }
            })
            .catch(error => console.error('Error:', error));
        };
    }

    // 簡單的 HTML 轉義函式防止 XSS
    function escapeHtml(text) {
        let div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
</script>

</body>
</html>