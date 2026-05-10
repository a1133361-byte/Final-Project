<?php
session_start();
require_once "includes/dbh.inc.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$my_id = $_SESSION['user_id'];
$other_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] == 1;

// --- 1. 獲取好友列表 ---
$friends_sql = "SELECT DISTINCT users.id, users.username, users.profile_img FROM friends JOIN users ON (CASE WHEN friends.user_id = ? THEN friends.friend_id = users.id WHEN friends.friend_id = ? THEN friends.user_id = users.id END) WHERE (friends.user_id = ? OR friends.friend_id = ?) AND friends.status = 'accepted' ORDER BY users.username ASC";
$friends_stmt = $pdo->prepare($friends_sql);
$friends_stmt->execute([$my_id, $my_id, $my_id, $my_id]);
$friends_list = $friends_stmt->fetchAll();

// 自動選取第一個好友
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
    <title>私訊對話 - PHP Forum</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root {
            --bg-color: #f8fafc;
            --card-bg: #ffffff;
            --text-color: #0f172a;
            --text-muted: #64748b;
            --nav-bg: rgba(255, 255, 255, 0.85);
            --accent-color: #6366f1;
            --accent-soft: rgba(99, 102, 241, 0.1);
            --header-gradient: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            --border-color: #e2e8f0;
            --sidebar-item-hover: #f1f5f9;
            --scrollbar-thumb: #cbd5e1;
            --scrollbar-track: transparent;
        }

        [data-theme="dark"] {
            --bg-color: #0f172a;
            --card-bg: #1e293b;
            --text-color: #f1f5f9;
            --text-muted: #94a3b8;
            --nav-bg: rgba(15, 23, 42, 0.9);
            --border-color: #334155;
            --sidebar-item-hover: #334155;
            --accent-soft: rgba(99, 102, 241, 0.2);
            --scrollbar-thumb: #475569;
        }

        body { 
            font-family: 'Inter', system-ui, sans-serif; 
            background-color: var(--bg-color); 
            margin: 0; 
            color: var(--text-color); 
            display: flex; 
            flex-direction: column; 
            height: 100vh; 
            overflow: hidden;
            transition: background-color 0.3s, color 0.3s;
        }

        /* --- 改良 1: 強制生效的自定義捲軸設計 --- */
        /* Webkit (Chrome, Safari, Edge) */
        .chat-container::-webkit-scrollbar,
        .friends-list::-webkit-scrollbar {
            width: 8px !important;
            display: block !important;
        }
        .chat-container::-webkit-scrollbar-track,
        .friends-list::-webkit-scrollbar-track {
            background: var(--scrollbar-track) !important;
        }
        .chat-container::-webkit-scrollbar-thumb,
        .friends-list::-webkit-scrollbar-thumb {
            background-color: var(--scrollbar-thumb) !important;
            border-radius: 20px !important;
            border: 2px solid var(--bg-color) !important; /* 增加間隙感 */
        }
        .chat-container::-webkit-scrollbar-thumb:hover,
        .friends-list::-webkit-scrollbar-thumb:hover {
            background-color: var(--accent-color) !important;
        }

        /* Firefox */
        .chat-container, .friends-list {
            scrollbar-width: thin;
            scrollbar-color: var(--scrollbar-thumb) var(--scrollbar-track);
        }

        header { 
            background: var(--nav-bg); 
            backdrop-filter: blur(10px); 
            border-bottom: 1px solid var(--border-color); 
            padding: 12px 0; 
            z-index: 1000;
        }
        .nav-container { max-width: 1400px; margin: 0 auto; padding: 0 25px; display: flex; justify-content: space-between; align-items: center; }
        .logo h1 { margin: 0; font-size: 1.4rem; font-weight: 800; background: var(--header-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }

        .main-wrapper { 
            max-width: 1400px; 
            margin: 0 auto; 
            width: 100%;
            flex: 1; 
            display: grid; 
            grid-template-columns: 300px 1fr; 
            overflow: hidden; /* 確保主視窗不捲動 */
        }

        .sidebar { 
            background: var(--card-bg); 
            border-right: 1px solid var(--border-color); 
            display: flex; 
            flex-direction: column; 
            padding: 20px 10px;
            overflow: hidden;
        }
        .sidebar-title { 
            font-size: 0.75rem; 
            font-weight: 800; 
            color: var(--text-muted); 
            text-transform: uppercase; 
            letter-spacing: 1px; 
            margin-bottom: 15px;
            padding-left: 15px;
        }
        .friends-list { 
            flex: 1; 
            overflow-y: auto; 
            padding-right: 5px;
        }
        .friend-item { 
            display: flex; 
            align-items: center; 
            gap: 12px; 
            padding: 12px 15px; 
            text-decoration: none; 
            color: var(--text-color); 
            border-radius: 12px;
            margin-bottom: 5px;
            font-weight: 600;
            transition: all 0.2s ease; 
        }
        .friend-item:hover { background: var(--sidebar-item-hover); }
        .friend-item.active { background: var(--accent-soft); color: var(--accent-color); }
        .friend-avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; background: #eee; border: 2px solid transparent; }
        .friend-item.active .friend-avatar { border-color: var(--accent-color); }

        .chat-main { 
            display: flex; 
            flex-direction: column; 
            background: var(--bg-color); 
            overflow: hidden;
        }
        .chat-top-info {
            padding: 15px 25px;
            background: var(--card-bg);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 10;
        }
        .chat-top-info h3 { margin: 0; font-size: 1.1rem; font-weight: 800; }

        /* --- 改良 2: 聊天視窗容器 --- */
        .chat-container { 
            flex: 1; 
            overflow-y: auto !important; /* 強制溢出顯示捲軸 */
            padding: 25px; 
            display: flex; 
            flex-direction: column; 
            gap: 12px; 
        }

        .msg { 
            max-width: 70%; 
            padding: 10px 16px; 
            border-radius: 18px; 
            font-size: 0.95rem; 
            line-height: 1.5; 
            position: relative; 
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            animation: popIn 0.25s ease-out;
        }
        @keyframes popIn {
            from { opacity: 0; transform: translateY(5px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .msg.sent { 
            align-self: flex-end; 
            background: var(--accent-color); 
            color: white; 
            border-bottom-right-radius: 4px; 
        }
        .msg.received { 
            align-self: flex-start; 
            background: var(--card-bg); 
            color: var(--text-color); 
            border-bottom-left-radius: 4px; 
            border: 1px solid var(--border-color);
        }
        .msg-time { 
            font-size: 0.65rem; 
            opacity: 0.7; 
            margin-top: 4px; 
            display: block; 
            text-align: right; 
        }

        .msg img { 
            max-width: 100%; 
            border-radius: 12px; 
            margin-top: 5px; 
            cursor: zoom-in; 
            transition: 0.2s;
            display: block;
        }

        .input-area { 
            background: var(--card-bg); 
            padding: 15px 25px; 
            display: flex; 
            gap: 12px; 
            border-top: 1px solid var(--border-color); 
            align-items: center; 
        }
        .input-area input[type="text"] { 
            flex: 1; 
            border: 2px solid var(--border-color); 
            padding: 12px 20px; 
            border-radius: 25px; 
            outline: none; 
            background: var(--bg-color);
            color: var(--text-color);
            transition: all 0.3s;
            font-size: 0.95rem;
        }
        .input-area input[type="text"]:focus { 
            border-color: var(--accent-color); 
            background: var(--card-bg);
        }
        
        .input-area button { 
            background: var(--accent-color); 
            color: white; 
            border: none; 
            padding: 10px 22px; 
            border-radius: 20px; 
            cursor: pointer; 
            font-weight: 700; 
        }
        .input-area button:disabled { opacity: 0.5; }

        .upload-btn { 
            cursor: pointer; 
            color: var(--accent-color); 
            font-size: 1.4rem; 
            padding: 8px;
            border-radius: 50%;
        }

        @media (max-width: 800px) {
            .main-wrapper { grid-template-columns: 80px 1fr; }
            .friend-name { display: none; }
        }
    </style>
</head>
<body data-theme="light">

<header>
    <div class="nav-container">
        <a href="index.php" class="logo" style="text-decoration:none"><h1>🚀 PHP Forum</h1></a>
        <div style="display:flex; align-items:center; gap:15px;">
            <button id="themeBtn" style="background:none; border:none; cursor:pointer; font-size:1.3rem;">🌓</button>
            <a href="index.php" style="text-decoration:none; color:var(--text-muted); font-weight:700;">返回</a>
        </div>
    </div>
</header>

<div class="main-wrapper">
    <aside class="sidebar">
        <div class="sidebar-title">好友列表</div>
        <div class="friends-list">
            <?php foreach ($friends_list as $f): ?>
                <a href="chat.php?user_id=<?= $f['id'] ?>" class="friend-item <?= $f['id'] == $other_id ? 'active' : '' ?>">
                    <img src="<?= !empty($f['profile_img']) ? 'uploads/users_profile_img/'.$f['profile_img'] : 'uploads/default_avatar.png' ?>" class="friend-avatar">
                    <span class="friend-name"><?= htmlspecialchars($f['username']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </aside>

    <main class="chat-main">
        <?php if ($other_id > 0): ?>
            <div class="chat-top-info">
                <img src="<?= !empty($other_user['profile_img']) ? 'uploads/users_profile_img/'.$other_user['profile_img'] : 'uploads/default_avatar.png' ?>" style="width:32px; height:32px; border-radius:50%; object-fit:cover;">
                <h3><?= htmlspecialchars($other_user['username']) ?></h3>
            </div>

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

            <form class="input-area" id="chatForm" method="POST" enctype="multipart/form-data">
                <label for="img_input" class="upload-btn">🖼️</label>
                <input type="file" name="chat_image" id="img_input" accept="image/*" style="display:none;" onchange="handleImgSelect(this)">
                
                <input type="text" name="message" id="msg_field" placeholder="輸入訊息..." autocomplete="off" autofocus>
                <button type="submit" id="sendBtn">發送</button>
            </form>
        <?php else: ?>
            <div style="flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; opacity:0.5;">
                <span style="font-size: 3rem;">💬</span>
                <p>選擇一個好友開始聊天</p>
            </div>
        <?php endif; ?>
    </main>
</div>

<script>
    const chatBox = document.getElementById('chatBox');
    const chatForm = document.getElementById('chatForm');
    const themeBtn = document.getElementById('themeBtn');

    // 主題切換
    themeBtn.onclick = () => {
        const currentTheme = document.body.getAttribute('data-theme');
        const newTheme = currentTheme === 'light' ? 'dark' : 'light';
        document.body.setAttribute('data-theme', newTheme);
        localStorage.setItem('theme', newTheme);
    };

    if(localStorage.getItem('theme')) {
        document.body.setAttribute('data-theme', localStorage.getItem('theme'));
    }

    // 捲動底部
    function scrollToBottom(smooth = false) {
        if (!chatBox) return;
        chatBox.scrollTo({
            top: chatBox.scrollHeight,
            behavior: smooth ? 'smooth' : 'auto'
        });
    }

    window.onload = () => {
        scrollToBottom();
        // 額外延遲執行一次，確保圖片加載後能準確定位到底部
        setTimeout(scrollToBottom, 300);
    };

    function handleImgSelect(input) {
        if (input.files && input.files[0]) {
            const field = document.getElementById('msg_field');
            field.placeholder = "已選取: " + input.files[0].name;
        }
    }

    // AJAX 處理
    if (chatForm) {
        chatForm.onsubmit = function(e) {
            e.preventDefault();
            const btn = document.getElementById('sendBtn');
            const field = document.getElementById('msg_field');
            
            let formData = new FormData(this);
            btn.disabled = true;

            fetch(`chat.php?user_id=<?= $other_id ?>&ajax=1`, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                if (data.status === 'success') {
                    let div = document.createElement('div');
                    div.className = 'msg sent';
                    let content = data.msg_type === 'image' 
                        ? `<img src="uploads/chat_imgs/${data.content}" onclick="window.open(this.src)">` 
                        : escapeHtml(data.content);
                    div.innerHTML = `${content}<span class="msg-time">${data.time}</span>`;
                    chatBox.appendChild(div);
                    chatForm.reset();
                    field.placeholder = "輸入訊息...";
                    scrollToBottom(true);
                }
            })
            .catch(err => {
                btn.disabled = false;
                console.error(err);
            });
        };
    }

    function escapeHtml(text) {
        let p = document.createElement('p');
        p.style.margin = '0';
        p.textContent = text;
        return p.innerHTML;
    }
</script>

</body>
</html>