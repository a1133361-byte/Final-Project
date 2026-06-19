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

// --- 0. 處理好友請求操作 (從頂部下拉選單觸發) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_GET['ajax'])) {
    // ===== 接受好友請求 =====
    if (isset($_POST['accept_friend']) && isset($_POST['friend_row_id'])) {
        try {
            $rowId = (int)$_POST['friend_row_id'];
            $acc_stmt = $pdo->prepare("UPDATE friends SET status = 'accepted' WHERE id = ? AND friend_id = ?");
            $acc_stmt->execute([$rowId, $my_id]);
            
            $get_req = $pdo->prepare("SELECT user_id FROM friends WHERE id = ?");
            $get_req->execute([$rowId]);
            $req_row = $get_req->fetch();
            
            if ($req_row) {
                $check = $pdo->prepare("SELECT id FROM friends WHERE user_id = ? AND friend_id = ?");
                $check->execute([$my_id, $req_row['user_id']]);
                if (!$check->fetch()) {
                    $ins = $pdo->prepare("INSERT INTO friends (user_id, friend_id, status) VALUES (?, ?, 'accepted')");
                    $ins->execute([$my_id, $req_row['user_id']]);
                } else {
                    $upd = $pdo->prepare("UPDATE friends SET status = 'accepted' WHERE user_id = ? AND friend_id = ?");
                    $upd->execute([$my_id, $req_row['user_id']]);
                }
            }
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit();
        } catch (PDOException $e) { }
    }

    // ===== 拒絕/取消好友請求 =====
    if (isset($_POST['reject_friend']) && isset($_POST['friend_row_id'])) {
        try {
            $rowId = (int)$_POST['friend_row_id'];
            $rej_stmt = $pdo->prepare("DELETE FROM friends WHERE id = ? AND friend_id = ?");
            $rej_stmt->execute([$rowId, $my_id]);
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit();
        } catch (PDOException $e) { }
    }
}

// --- 1. 將來自目前開啟好友的未讀訊息標記為已讀 (雙重相容設計) ---
if ($other_id > 0) {
    try {
        $read_stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
        $read_stmt->execute([$other_id, $my_id]);
    } catch (PDOException $e) {
        try {
            $read_stmt = $pdo->prepare("UPDATE chat_messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
            $read_stmt->execute([$other_id, $my_id]);
        } catch (PDOException $ex) { }
    }
}

// --- 2. 獲取通知與計數器 (供導覽列使用) ---
$pendingReportsCount = 0;
$unreadAnnouncementsCount = 0;
$pendingFriendRequestsCount = 0;
$pendingFriendRequests = [];
$unreadChatsCount = 0;

try {
    if ($isAdmin) {
        $report_sql = "SELECT COUNT(*) FROM reports WHERE status = 0";
        $report_stmt = $pdo->query($report_sql);
        $pendingReportsCount = (int)$report_stmt->fetchColumn();
    }

    $unread_sql = "SELECT COUNT(*) FROM announcements 
                   WHERE created_at > (
                       SELECT IFNULL(last_announcement_view, '1970-01-01 00:00:00') 
                       FROM users WHERE id = ?
                   )";
    $unread_stmt = $pdo->prepare($unread_sql);
    $unread_stmt->execute([$my_id]);
    $unreadAnnouncementsCount = (int)$unread_stmt->fetchColumn();

    $friend_req_sql = "
        SELECT f.id AS friend_row_id, f.user_id AS requester_id, IFNULL(u.username, '未知用戶') AS username, u.profile_img
        FROM friends f
        LEFT JOIN users u ON f.user_id = u.id
        WHERE f.friend_id = :friend_id AND f.status = 'pending'
        ORDER BY f.created_at DESC
        LIMIT 10
    ";
    $friend_req_stmt = $pdo->prepare($friend_req_sql);
    $friend_req_stmt->bindValue(':friend_id', (int)$my_id, PDO::PARAM_INT);
    $friend_req_stmt->execute();
    $pendingFriendRequests = $friend_req_stmt->fetchAll();
    $pendingFriendRequestsCount = count($pendingFriendRequests);
    
    // 讀取總未讀私訊數
    try {
        $chat_sql = "SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0";
        $chat_stmt = $pdo->prepare($chat_sql);
        $chat_stmt->execute([$my_id]);
        $unreadChatsCount = (int)$chat_stmt->fetchColumn();
    } catch (PDOException $e) {
        try {
            $chat_sql = "SELECT COUNT(*) FROM chat_messages WHERE receiver_id = ? AND is_read = 0";
            $chat_stmt = $pdo->prepare($chat_sql);
            $chat_stmt->execute([$my_id]);
            $unreadChatsCount = (int)$chat_stmt->fetchColumn();
        } catch (PDOException $ex) {
            $unreadChatsCount = 0; 
        }
    }
} catch (PDOException $e) { }

// --- 3. 獲取好友列表 (連同未讀統計) ---
$friends_list = [];
try {
    $friends_sql = "
        SELECT DISTINCT users.id, users.username, users.profile_img,
               (SELECT COUNT(*) FROM messages WHERE sender_id = users.id AND receiver_id = ? AND is_read = 0) AS unread_count
        FROM friends 
        JOIN users ON (
            CASE 
                WHEN friends.user_id = ? THEN friends.friend_id = users.id 
                WHEN friends.friend_id = ? THEN friends.user_id = users.id 
            END
        ) 
        WHERE (friends.user_id = ? OR friends.friend_id = ?) 
          AND friends.status = 'accepted' 
        ORDER BY users.username ASC
    ";
    $friends_stmt = $pdo->prepare($friends_sql);
    $friends_stmt->execute([$my_id, $my_id, $my_id, $my_id, $my_id]);
    $friends_list = $friends_stmt->fetchAll();
} catch (PDOException $e) {
    try {
        $friends_sql = "
            SELECT DISTINCT users.id, users.username, users.profile_img,
                   (SELECT COUNT(*) FROM chat_messages WHERE sender_id = users.id AND receiver_id = ? AND is_read = 0) AS unread_count
            FROM friends 
            JOIN users ON (
                CASE 
                    WHEN friends.user_id = ? THEN friends.friend_id = users.id 
                    WHEN friends.friend_id = ? THEN friends.user_id = users.id 
                END
            ) 
            WHERE (friends.user_id = ? OR friends.friend_id = ?) 
              AND friends.status = 'accepted' 
            ORDER BY users.username ASC
        ";
        $friends_stmt = $pdo->prepare($friends_sql);
        $friends_stmt->execute([$my_id, $my_id, $my_id, $my_id, $my_id]);
        $friends_list = $friends_stmt->fetchAll();
    } catch (PDOException $ex) {
        try {
            $friends_sql = "SELECT DISTINCT users.id, users.username, users.profile_img, 0 AS unread_count FROM friends JOIN users ON (CASE WHEN friends.user_id = ? THEN friends.friend_id = users.id WHEN friends.friend_id = ? THEN friends.user_id = users.id END) WHERE (friends.user_id = ? OR friends.friend_id = ?) AND friends.status = 'accepted' ORDER BY users.username ASC";
            $friends_stmt = $pdo->prepare($friends_sql);
            $friends_stmt->execute([$my_id, $my_id, $my_id, $my_id]);
            $friends_list = $friends_stmt->fetchAll();
        } catch (PDOException $ex2) {}
    }
}

// 自動選取第一個好友
if ($other_id === 0 && !empty($friends_list) && !isset($_GET['ajax'])) {
    header("Location: chat.php?user_id=" . $friends_list[0]['id']);
    exit();
}

// --- 4. 處理發送訊息 (支援圖片及影片上傳，且排除好友邀請的 POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $other_id > 0 && !isset($_POST['accept_friend']) && !isset($_POST['reject_friend'])) {
    $msg = trim($_POST['message'] ?? '');
    $msg_type = 'text';
    $final_content = $msg;
    $upload_ok = true;

    if (isset($_FILES['chat_image']) && $_FILES['chat_image']['error'] === 0) {
        $allowed_imgs = ['jpg', 'jpeg', 'png', 'gif'];
        $allowed_videos = ['mp4', 'webm', 'ogg', 'mov'];
        $filename = $_FILES['chat_image']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (in_array($ext, $allowed_imgs)) {
            $folder = "uploads/chat_imgs/";
            if (!is_dir($folder)) mkdir($folder, 0777, true);
            $newName = "IMG_" . date("Ymd_His") . "_" . uniqid() . "." . $ext;
            if (move_uploaded_file($_FILES['chat_image']['tmp_name'], $folder . $newName)) {
                $final_content = $newName;
                $msg_type = 'image';
            } else {
                $upload_ok = false;
            }
        } elseif (in_array($ext, $allowed_videos)) {
            $folder = "uploads/chat_videos/";
            if (!is_dir($folder)) mkdir($folder, 0777, true);
            $newName = "VID_" . date("Ymd_His") . "_" . uniqid() . "." . $ext;
            if (move_uploaded_file($_FILES['chat_image']['tmp_name'], $folder . $newName)) {
                $final_content = $newName;
                $msg_type = 'video';
            } else {
                $upload_ok = false;
            }
        } else {
            $upload_ok = false;
        }
    }

    if ($upload_ok && ($msg_type === 'image' || $msg_type === 'video' || $msg !== '')) {
        $insert = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message, msg_type, is_read) VALUES (?, ?, ?, ?, 0)");
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

// --- 5. 獲取當前選定的聊天資訊 ---
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
    <title>私訊對話 - Talk Forum</title>
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
            --danger-color: #ef4444;
            --success-color: #22c55e;
            --admin-color: #f59e0b;
            --admin-soft: rgba(245, 158, 11, 0.1);
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
            --admin-soft: rgba(245, 158, 11, 0.15);
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

        /* --- 自定義捲軸設計 --- */
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
            border: 2px solid var(--bg-color) !important;
        }
        .chat-container::-webkit-scrollbar-thumb:hover,
        .friends-list::-webkit-scrollbar-thumb:hover {
            background-color: var(--accent-color) !important;
        }

        .chat-container, .friends-list {
            scrollbar-width: thin;
            scrollbar-color: var(--scrollbar-thumb) var(--scrollbar-track);
        }

        /* --- 導覽列與頭像、下拉選單樣式 --- */
        header { 
            background: var(--nav-bg); 
            backdrop-filter: blur(10px); 
            border-bottom: 1px solid var(--border-color); 
            padding: 12px 0; 
            z-index: 1000;
            position: sticky;
            top: 0;
            transition: background-color 0.3s, border-color 0.3s;
        }
        .nav-container { max-width: 1400px; margin: 0 auto; padding: 0 25px; display: flex; justify-content: space-between; align-items: center; }
        .logo h1 { 
            margin: 0; 
            font-size: 1.4rem; 
            font-weight: 800; 
            background: var(--header-gradient); 
            -webkit-background-clip: text; 
            -webkit-text-fill-color: transparent;
        }

        .user-trigger { 
            display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 5px 12px; border-radius: 50px; transition: 0.2s; position: relative;
        }
        .user-trigger:hover { background: var(--sidebar-item-hover); }
        .user-trigger span { font-weight: 700; font-size: 0.95rem; color: var(--text-color); }

        .notification-badge {
            position: absolute;
            top: -2px;
            right: -2px;
            background: var(--danger-color);
            color: white;
            font-size: 0.65rem;
            min-width: 18px;
            height: 18px;
            padding: 0 4px;
            border-radius: 10px;
            display: flex;
            justify-content: center;
            align-items: center;
            border: 2px solid var(--card-bg);
            font-weight: 800;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .dropdown-menu { 
            position: absolute; 
            right: 0; 
            top: 125%; 
            width: 280px; 
            background: var(--card-bg); 
            border: 1px solid var(--border-color); 
            border-radius: 16px; 
            box-shadow: 0 10px 25px rgba(0,0,0,0.15); 
            display: none; 
            flex-direction: column; 
            overflow: hidden; 
            z-index: 1100;
        }
        .dropdown-menu.active { display: flex; }
        .dropdown-menu a { 
            padding: 12px 20px; 
            text-decoration: none; 
            color: var(--text-color); 
            font-weight: 600; 
            font-size: 0.9rem; 
            transition: 0.2s; 
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .dropdown-menu a:last-child { border-bottom: none; }
        .dropdown-menu a:hover { background: var(--sidebar-item-hover); color: var(--accent-color); }
        
        .admin-link { color: var(--admin-color) !important; background: var(--admin-soft); }
        .admin-link:hover { background: var(--admin-color) !important; color: white !important; }

        /* 好友邀請下拉區塊樣式 */
        .friend-requests-section {
            border-bottom: 1px solid var(--border-color);
        }
        .friend-requests-header {
            padding: 10px 20px 6px;
            font-size: 0.7rem;
            color: var(--success-color);
            font-weight: 800;
            text-transform: uppercase;
            background: rgba(34, 197, 94, 0.08);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .friend-request-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 16px;
            border-bottom: 1px solid var(--border-color);
            background: transparent;
            transition: background 0.15s;
        }
        .friend-request-item:last-child { border-bottom: none; }
        .friend-request-item:hover { background: var(--sidebar-item-hover); }
        .friend-request-avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--success-color);
            flex-shrink: 0;
        }
        .friend-request-name {
            font-weight: 700;
            font-size: 0.88rem;
            color: var(--text-color);
            flex: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .friend-request-actions {
            display: flex;
            gap: 5px;
            flex-shrink: 0;
        }
        .friend-btn {
            border: none;
            border-radius: 8px;
            padding: 5px 10px;
            font-size: 0.78rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.15s;
            line-height: 1;
        }
        .friend-btn-accept { background: var(--success-color); color: white; }
        .friend-btn-accept:hover { background: #16a34a; transform: scale(1.05); }
        .friend-btn-reject { background: var(--border-color); color: var(--text-muted); }
        .friend-btn-reject:hover { background: var(--danger-color); color: white; transform: scale(1.05); }

        .badge-inline { background: var(--danger-color); color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.7rem; margin-left: auto; font-weight: 800; }

        /* --- 聊天室配置與結構 --- */
        .main-wrapper { 
            max-width: 1400px; 
            margin: 0 auto; 
            width: 100%;
            flex: 1; 
            display: grid; 
            grid-template-columns: 300px 1fr; 
            overflow: hidden;
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

        .friend-list-badge {
            background: var(--danger-color);
            color: white;
            font-size: 0.7rem;
            font-weight: 800;
            padding: 2px 7px;
            border-radius: 10px;
            margin-left: auto;
        }

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

        .chat-container { 
            flex: 1; 
            overflow-y: auto !important; 
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
        
        /* --- 媒體（圖片與影片）專用無背景方框與內距樣式 --- */
        .msg.msg-media {
            padding: 0;
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
        }
        
        .msg-time { 
            font-size: 0.65rem; 
            opacity: 0.7; 
            margin-top: 4px; 
            display: block; 
            text-align: right; 
        }

        /* 確保在無背景媒體下的時間戳依然清晰可見 */
        .msg.msg-media .msg-time {
            color: var(--text-muted);
            opacity: 0.8;
        }

        /* --- 改良：限制圖片與影片的最大顯示尺寸，並保持自適應縮放 --- */
        .msg.msg-media img,
        .msg.msg-media video { 
            max-width: 320px; 
            max-height: 240px;
            width: 100%;
            height: auto;
            object-fit: contain;
            border-radius: 12px; 
            margin-top: 5px; 
            display: block;
        }

        /* 針對小螢幕進行最佳化 */
        @media (max-width: 480px) {
            .msg.msg-media img,
            .msg.msg-media video {
                max-width: 100%;
                max-height: 200px;
            }
        }

        /* 確保影片元件圓角美觀與貼齊，並帶有原生控制項 */
        .msg video {
            outline: none;
            background: #000; /* 影片載入前防黑底溢出 */
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
            .friend-list-badge { display: none; }
        }
    </style>
</head>
<body data-theme="light">

<header>
    <div class="nav-container">
        <a href="index.php" class="logo" style="text-decoration:none"><h1>✌️ Talk Forum</h1></a>
        <div style="display:flex; align-items:center; gap:15px;">
            <button id="themeBtn" title="切換主題" style="background:none; border:none; cursor:pointer; font-size:1.3rem; padding:5px; border-radius:50%; transition: 0.2s;">🌓</button>
            <?php if (isset($_SESSION["user_id"])): ?>
                <div style="position:relative;">
                    <div class="user-trigger" id="userTrigger">
                        <img src="<?= !empty($_SESSION['profile_img']) ? "uploads/users_profile_img/".$_SESSION['profile_img'] : "uploads/default_avatar.png" ?>" style="width:32px; height:32px; border-radius:50%; object-fit:cover; border: 2px solid <?= $isAdmin ? 'var(--admin-color)' : 'var(--accent-color)' ?>;">
                        <span style="<?= $isAdmin ? 'color: var(--admin-color);' : '' ?>"><?= htmlspecialchars($_SESSION["username"]) ?></span>
                        <?php 
                        // 同時整合未讀通知紅點
                        $totalNotif = $unreadAnnouncementsCount + ($isAdmin ? $pendingReportsCount : 0) + $pendingFriendRequestsCount + $unreadChatsCount;
                        if ($totalNotif > 0): 
                        ?>
                            <div class="notification-badge"><?= $totalNotif ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="dropdown-menu" id="dropdownMenu">

                        <?php if ($pendingFriendRequestsCount > 0): ?>
                        <div class="friend-requests-section">
                            <div class="friend-requests-header">
                                🤝 好友邀請
                                <span style="background:var(--success-color); color:white; padding:1px 7px; border-radius:8px; font-size:0.68rem;"><?= $pendingFriendRequestsCount ?></span>
                            </div>
                            <?php foreach ($pendingFriendRequests as $req): ?>
                                <div class="friend-request-item">
                                    <a href="profile.php?id=<?= $req['requester_id'] ?>" onclick="event.stopPropagation();" style="display:flex; align-items:center; flex:1; min-width:0; gap:8px; text-decoration:none;">
                                        <img src="<?= !empty($req['profile_img']) ? "uploads/users_profile_img/".$req['profile_img'] : "uploads/default_avatar.png" ?>" class="friend-request-avatar">
                                        <span class="friend-request-name"><?= htmlspecialchars($req['username']) ?></span>
                                    </a>
                                    <div class="friend-request-actions">
                                        <form method="POST" action="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>" style="display:inline;" onclick="event.stopPropagation();">
                                            <input type="hidden" name="friend_row_id" value="<?= $req['friend_row_id'] ?>">
                                            <button type="submit" name="accept_friend" class="friend-btn friend-btn-accept" title="接受好友邀請">✓ 接受</button>
                                        </form>
                                        <form method="POST" action="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>" style="display:inline;" onclick="event.stopPropagation();">
                                            <input type="hidden" name="friend_row_id" value="<?= $req['friend_row_id'] ?>">
                                            <button type="submit" name="reject_friend" class="friend-btn friend-btn-reject" title="拒絕好友邀請">✕</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <!-- 如果有其他人的未讀私訊，在選單顯示醒目通知 -->
                        <?php if ($unreadChatsCount > 0): ?>
                            <div style="padding: 10px 20px 6px; font-size: 0.7rem; color: var(--danger-color); font-weight: 800; text-transform: uppercase; background: rgba(239, 68, 68, 0.08); display: flex; align-items: center; gap: 6px;">
                                💬 未讀訊息
                                <span style="background:var(--danger-color); color:white; padding:1px 7px; border-radius:8px; font-size:0.68rem;"><?= $unreadChatsCount ?></span>
                            </div>
                            <a href="chat.php" style="background: rgba(239, 68, 68, 0.03); font-weight: 700;">
                                <span>📬 去看看新訊息</span>
                                <span class="badge-inline" style="background: var(--danger-color); margin-left: auto;"><?= $unreadChatsCount ?> 條未讀</span>
                            </a>
                        <?php endif; ?>

                        <div style="padding: 10px 20px; font-size: 0.7rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase;">使用者功能</div>
                        <a href="profile.php?id=<?= $_SESSION['user_id'] ?>">👤 我的個人資料</a>
                        <a href="index.php?view=history">🕒 歷史瀏覽紀錄</a>
                        <a href="create_post.php">✍️ 撰寫新文章</a>
                        
                        <?php if ($isAdmin): ?>
                            <div style="padding: 10px 20px; font-size: 0.7rem; color: var(--admin-color); font-weight: 800; text-transform: uppercase; background: var(--admin-soft);">管理員功能</div>
                            <a href="admin_dashboard.php" class="admin-link">📊 後台數據首頁</a>
                            <a href="admin_reports.php" class="admin-link">
                                🚩 檢舉審理 
                                <?php if($pendingReportsCount > 0): ?>
                                    <span class="badge-inline"><?= $pendingReportsCount ?></span>
                                <?php endif; ?>
                            </a>
                            <a href="admin_categories.php" class="admin-link">🛠️ 看板管理</a>
                        <?php endif; ?>
                        
                        <a href="logout.php" style="color:#ef4444; font-weight:700;">🚪 登出系統</a>
                    </div>
                </div>
            <?php else: ?>
                <a href="login.php" style="text-decoration:none; background:var(--accent-color); color:white; padding:8px 20px; border-radius:50px; font-weight:700;">登入</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<div class="main-wrapper">
    <aside class="sidebar">
        <div class="sidebar-title">好友列表</div>
        <div class="friends-list">
            <?php foreach ($friends_list as $f): ?>
                <a href="chat.php?user_id=<?= $f['id'] ?>" class="friend-item <?= $f['id'] == $other_id ? 'active' : '' ?>">
                    <div style="position: relative; display: inline-block;">
                        <img src="<?= !empty($f['profile_img']) ? 'uploads/users_profile_img/'.$f['profile_img'] : 'uploads/default_avatar.png' ?>" class="friend-avatar">
                        <?php if (isset($f['unread_count']) && $f['unread_count'] > 0 && $f['id'] != $other_id): ?>
                            <span style="position: absolute; top: -2px; right: -2px; width: 10px; height: 10px; background: var(--danger-color); border: 2px solid var(--card-bg); border-radius: 50%;"></span>
                        <?php endif; ?>
                    </div>
                    <span class="friend-name"><?= htmlspecialchars($f['username']) ?></span>
                    <?php if (isset($f['unread_count']) && $f['unread_count'] > 0 && $f['id'] != $other_id): ?>
                        <span class="friend-list-badge"><?= $f['unread_count'] ?></span>
                    <?php endif; ?>
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
                    <?php 
                        $msg_file = $m['message'];
                        // 雙重保險判斷：同時檢查資料庫的 msg_type 欄位以及訊息內容的命名規則與副檔名
                        $is_image = ($m['msg_type'] === 'image') || (strpos($msg_file, 'IMG_') === 0 && preg_match('/\.(jpg|jpeg|png|gif)$/i', $msg_file));
                        $is_video = ($m['msg_type'] === 'video') || (strpos($msg_file, 'VID_') === 0 && preg_match('/\.(mp4|webm|ogg|mov|3gp)$/i', $msg_file));
                        $media_class = ($is_image || $is_video) ? 'msg-media' : '';
                    ?>
                    <div class="msg <?= $m['sender_id'] == $my_id ? 'sent' : 'received' ?> <?= $media_class ?>">
                        <?php if ($is_image): ?>
                            <img src="uploads/chat_imgs/<?= htmlspecialchars($m['message']) ?>" onclick="window.open(this.src)">
                        <?php elseif ($is_video): ?>
                            <video src="uploads/chat_videos/<?= htmlspecialchars($m['message']) ?>" controls></video>
                        <?php else: ?>
                            <?= htmlspecialchars($m['message']) ?>
                        <?php endif; ?>
                        <span class="msg-time"><?= date('H:i', strtotime($m['created_at'])) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <form class="input-area" id="chatForm" method="POST" enctype="multipart/form-data">
                <label for="img_input" class="upload-btn" title="上傳圖片或影片">📎</label>
                <input type="file" name="chat_image" id="img_input" accept="image/*,video/*" style="display:none;" onchange="handleImgSelect(this)">
                
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

    // 主題切換邏輯
    themeBtn.onclick = () => {
        const currentTheme = document.body.getAttribute('data-theme');
        const newTheme = currentTheme === 'light' ? 'dark' : 'light';
        document.body.setAttribute('data-theme', newTheme);
        localStorage.setItem('theme', newTheme);
    };

    if(localStorage.getItem('theme')) {
        document.body.setAttribute('data-theme', localStorage.getItem('theme'));
    }

    // 導覽列頭像選單點擊展開與點擊外面關閉邏輯
    const userTrigger = document.getElementById('userTrigger');
    const dropdownMenu = document.getElementById('dropdownMenu');
    
    if(userTrigger && dropdownMenu) {
        userTrigger.onclick = (e) => { 
            e.stopPropagation(); 
            dropdownMenu.classList.toggle('active'); 
        };
        
        document.addEventListener('click', (e) => {
            if (userTrigger && !userTrigger.contains(e.target)) {
                dropdownMenu.classList.remove('remove');
                dropdownMenu.classList.remove('active');
            }
        });
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
                    // 當為圖片或影片時，套用 msg-media 樣式
                    const isImage = (data.msg_type === 'image' || (typeof data.content === 'string' && data.content.startsWith('IMG_')));
                    const isVideo = (data.msg_type === 'video' || (typeof data.content === 'string' && data.content.startsWith('VID_')));
                    const isMedia = isImage || isVideo;
                    
                    div.className = 'msg sent' + (isMedia ? ' msg-media' : '');
                    
                    let content = '';
                    if (isImage) {
                        content = `<img src="uploads/chat_imgs/${data.content}" onclick="window.open(this.src)">`;
                    } else if (isVideo) {
                        content = `<video src="uploads/chat_videos/${data.content}" controls></video>`;
                    } else {
                        content = escapeHtml(data.content);
                    }
                    
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