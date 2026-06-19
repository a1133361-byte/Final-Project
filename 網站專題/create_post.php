<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php?error=please_login");
    exit();
}

require_once "includes/dbh.inc.php";

$current_uid = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] == 1;

// 初始化未讀通知與檢舉計數
$pendingReportsCount = 0;
$unreadAnnouncementsCount = 0;
$unreadChatsCount = 0;
$pendingFriendRequestsCount = 0;
$pendingFriendRequests = [];

try {
    // 取得看板分類
    $sql = "SELECT * FROM categories ORDER BY id ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $categories = $stmt->fetchAll();

    // 取得未讀公告與待審核檢舉數 (加上安全 Try-Catch 防護，防止資料表不存在導致頁面崩潰)
    if ($current_uid) {
        if ($isAdmin) {
            try {
                $report_stmt = $pdo->query("SELECT COUNT(*) FROM reports WHERE status = 0");
                $pendingReportsCount = (int)$report_stmt->fetchColumn();
            } catch (PDOException $e) {
                $pendingReportsCount = 0;
            }
        }
        
        try {
            $unread_sql = "SELECT COUNT(*) FROM announcements WHERE created_at > (SELECT IFNULL(last_announcement_view, '1970-01-01 00:00:00') FROM users WHERE id = ?)";
            $unread_stmt = $pdo->prepare($unread_sql);
            $unread_stmt->execute([$current_uid]);
            $unreadAnnouncementsCount = (int)$unread_stmt->fetchColumn();
        } catch (PDOException $e) {
            $unreadAnnouncementsCount = 0;
        }

        // 讀取總未讀好友請求
        try {
            $friend_req_sql = "
                SELECT f.id AS friend_row_id, f.user_id AS requester_id, IFNULL(u.username, '未知用戶') AS username, u.profile_img
                FROM friends f
                LEFT JOIN users u ON f.user_id = u.id
                WHERE f.friend_id = :friend_id AND f.status = 'pending'
                ORDER BY f.created_at DESC
                LIMIT 10
            ";
            $friend_req_stmt = $pdo->prepare($friend_req_sql);
            $friend_req_stmt->bindValue(':friend_id', (int)$current_uid, PDO::PARAM_INT);
            $friend_req_stmt->execute();
            $pendingFriendRequests = $friend_req_stmt->fetchAll();
            $pendingFriendRequestsCount = count($pendingFriendRequests);
        } catch (PDOException $e) { }

        // 讀取總未讀私訊數
        try {
            $chat_sql = "SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0";
            $chat_stmt = $pdo->prepare($chat_sql);
            $chat_stmt->execute([$current_uid]);
            $unreadChatsCount = (int)$chat_stmt->fetchColumn();
        } catch (PDOException $e) {
            try {
                $chat_sql = "SELECT COUNT(*) FROM chat_messages WHERE receiver_id = ? AND is_read = 0";
                $chat_stmt = $pdo->prepare($chat_sql);
                $chat_stmt->execute([$current_uid]);
                $unreadChatsCount = (int)$chat_stmt->fetchColumn();
            } catch (PDOException $ex) {
                $unreadChatsCount = 0; 
            }
        }
    }
} catch (PDOException $e) {
    die("資料庫錯誤: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>發表新文章 - Talk Forum</title>

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
    --admin-color: #f59e0b;
    --admin-soft: rgba(245, 158, 11, 0.1);
    --danger-color: #ef4444;
    --danger-soft: rgba(239, 68, 68, 0.1);
    --success-color: #22c55e;
    --input-bg: #f8fafc;
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
    --danger-soft: rgba(239, 68, 68, 0.15);
    --input-bg: #0f172a;
}

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: 'Inter', system-ui, sans-serif;
    background: var(--bg-color);
    color: var(--text-color);
    transition: background-color 0.3s, color 0.3s;
}

/* Header & Navigation */
header { 
    background: var(--nav-bg); 
    backdrop-filter: blur(10px); 
    border-bottom: 1px solid var(--border-color); 
    position: sticky; top: 0; z-index: 1000; padding: 12px 0; 
}
.nav-container { max-width: 1400px; margin: 0 auto; padding: 0 25px; display: flex; justify-content: space-between; align-items: center; }
.logo h1 { margin: 0; font-size: 1.4rem; font-weight: 800; background: var(--header-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }

.user-trigger { display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 5px 12px; border-radius: 50px; transition: 0.2s; position: relative; }
.user-trigger:hover { background: var(--sidebar-item-hover); }
.user-trigger span { font-weight: 700; font-size: 0.95rem; }

.notification-badge { position: absolute; top: -2px; right: -2px; background: var(--danger-color); color: white; font-size: 0.65rem; min-width: 18px; height: 18px; padding: 0 4px; border-radius: 10px; display: flex; justify-content: center; align-items: center; border: 2px solid var(--card-bg); font-weight: 800; }

/* 下拉選單樣式 */
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

/* 管理員連結樣式 */
.admin-link { color: var(--admin-color) !important; background: var(--admin-soft); }
.admin-link:hover { background: var(--admin-color) !important; color: white !important; }
.badge-inline { background: var(--danger-color); color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.7rem; margin-left: auto; font-weight: 800; }

/* Layout */
.main-wrapper {
    max-width: 1000px; 
    margin: 25px auto;
    padding: 0 25px;
}

/* Main Card */
.form-card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 28px;
    overflow: hidden;
}

.form-content {
    padding: 35px;
}

.page-title {
    font-size: 2rem;
    font-weight: 900;
    margin: 0 0 8px 0;
}

.page-desc {
    color: var(--text-muted);
    margin-bottom: 35px;
    line-height: 1.6;
}

/* Form */
.form-group {
    margin-bottom: 24px;
}

label {
    display: block;
    margin-bottom: 10px;
    font-size: .92rem;
    font-weight: 800;
    color: var(--text-color);
}

select,
input[type="text"] {
    width: 100%;
    border: 1px solid var(--border-color);
    background: var(--input-bg);
    color: var(--text-color);
    border-radius: 16px;
    padding: 14px 16px;
    font-size: .96rem;
    font-family: inherit;
    transition: .2s;
}

select:focus,
input[type="text"]:focus,
.rich-editor:focus {
    outline: none;
    border-color: var(--accent-color);
    box-shadow: 0 0 0 4px var(--accent-soft);
}

/* Rich Editor */
.rich-editor {
    width: 100%;
    min-height: 300px;
    border: 1px solid var(--border-color);
    background: var(--input-bg);
    color: var(--text-color);
    border-radius: 16px;
    padding: 14px 16px;
    font-size: .96rem;
    font-family: inherit;
    line-height: 1.7;
    overflow-y: auto;
    transition: .2s;
}

/* --- 改良：限制文章內圖片和影片最大預覽寬高 --- */
.rich-editor img,
.rich-editor video {
    max-width: 320px;
    max-height: 240px;
    width: 100%;
    height: auto;
    object-fit: contain;
    display: block;
    margin: 10px 0;
    border-radius: 12px;
    border: 1px solid var(--border-color);
}

/* Attachment Box */
.attach-box {
    margin-top: 18px;
    background: var(--bg-color);
    border: 1px solid var(--border-color);
    border-radius: 20px;
    padding: 18px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.attachment-controls {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.ai-controls {
    display: flex;
    align-items: center;
    gap: 8px;
}

.btn-control {
    border: none;
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    color: var(--text-color);
    padding: 10px 16px;
    border-radius: 12px;
    font-size: .9rem;
    font-weight: 700;
    cursor: pointer;
    transition: .2s;
}

.btn-control:hover {
    background: var(--accent-soft);
    border-color: var(--accent-color);
    color: var(--accent-color);
}

.btn-ai-polish {
    background: var(--header-gradient);
    color: white;
    border: none;
    padding: 10px 18px;
    border-radius: 12px;
    font-size: .9rem;
    font-weight: 800;
    cursor: pointer;
    transition: .2s;
    box-shadow: 0 4px 12px rgba(99,102,241,0.2);
}

.btn-ai-polish:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(99,102,241,0.35);
}

.style-select {
    padding: 10px 14px;
    border-radius: 12px;
    border: 1px solid var(--border-color);
    background: var(--card-bg);
    color: var(--text-color);
    font-size: .85rem;
    font-weight: 700;
    outline: none;
    cursor: pointer;
}

/* Buttons */
.button-group {
    display: flex;
    gap: 15px;
    margin-top: 35px;
}

.btn-submit,
.btn-cancel {
    border: none;
    text-decoration: none;
    padding: 15px 20px;
    border-radius: 16px;
    font-weight: 800;
    font-size: .95rem;
    transition: .2s;
}

.btn-submit {
    flex: 2;
    background: var(--header-gradient);
    color: white;
    cursor: pointer;
}

.btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(99,102,241,0.25);
}

.btn-cancel {
    flex: 1;
    background: transparent;
    border: 1px solid var(--border-color);
    color: var(--text-color);
    display: flex;
    justify-content: center;
    align-items: center;
}

.btn-cancel:hover {
    background: var(--sidebar-item-hover);
}

/* AI Polish Result Modal */
.modal-overlay {
    position: fixed;
    top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(4px);
    z-index: 2000;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.3s ease;
}
.modal-overlay.open {
    opacity: 1;
    pointer-events: auto;
}
.modal {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 24px;
    max-width: 800px;
    width: 90%;
    max-height: 85vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    transform: scale(0.95);
    transition: transform 0.3s ease;
    overflow: hidden;
}
.modal-overlay.open .modal {
    transform: scale(1);
}
.modal-header {
    padding: 20px 24px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.modal-title {
    font-size: 1.2rem;
    font-weight: 900;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}
.modal-body {
    padding: 24px;
    overflow-y: auto;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}
@media (max-width: 768px) {
    .modal-body {
        grid-template-columns: 1fr;
    }
}
.comparison-box {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.comparison-label {
    font-size: 0.8rem;
    font-weight: 800;
    text-transform: uppercase;
    color: var(--text-muted);
}
.comparison-content {
    border: 1px solid var(--border-color);
    background: var(--bg-color);
    border-radius: 16px;
    padding: 16px;
    font-size: 0.92rem;
    line-height: 1.6;
    min-height: 250px;
    max-height: 400px;
    overflow-y: auto;
}
.modal-footer {
    padding: 20px 24px;
    border-top: 1px solid var(--border-color);
    display: flex;
    justify-content: flex-end;
    gap: 12px;
}

/* Spinner CSS */
.spinner {
    border: 3px solid rgba(255,255,255,0.3);
    border-radius: 50%;
    border-top: 3px solid #fff;
    width: 18px;
    height: 18px;
    animation: spin 1s linear infinite;
    display: inline-block;
    vertical-align: middle;
    margin-right: 6px;
}
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

@media (max-width:600px){
    .form-content {
        padding:22px;
    }

    .button-group {
        flex-direction:column;
    }

    .page-title {
        font-size:1.6rem;
    }
    
    .attach-box {
        flex-direction: column;
        align-items: stretch;
    }
    .ai-controls {
        justify-content: space-between;
    }
}
</style>
</head>

<body data-theme="light">

<header>
    <div class="nav-container">
        <a href="index.php" class="logo" style="text-decoration:none">
            <h1>✌️ Talk Forum</h1>
        </a>
        <div style="display:flex; align-items:center; gap:15px;">
            <button id="themeBtn" title="切換主題" style="background:none; border:none; cursor:pointer; font-size:1.3rem; padding:5px; border-radius:50%;">🌓</button>
            <?php if (isset($_SESSION["user_id"])): ?>
                <div style="position:relative;">
                    <div class="user-trigger" id="userTrigger">
                        <img src="<?= !empty($_SESSION['profile_img']) ? "uploads/users_profile_img/".$_SESSION['profile_img'] : "uploads/default_avatar.png" ?>" style="width:32px; height:32px; border-radius:50%; object-fit:cover; border: 2px solid <?= $isAdmin ? 'var(--admin-color)' : 'var(--accent-color)' ?>;">
                        <span style="<?= $isAdmin ? 'color: var(--admin-color);' : '' ?>"><?= htmlspecialchars($_SESSION["username"]) ?></span>
                        <?php 
                        // 同時整合未讀通知紅點
                        $totalNotif = $unreadAnnouncementsCount + ($isAdmin ? $pendingReportsCount : 0) + $pendingFriendRequestsCount + $unreadChatsCount;
                        if ($totalNotif > 0): ?>
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
                            <a href="admin_reports.php" class="admin-link">🚩 檢舉審核 
                                <?php if($pendingReportsCount > 0): ?><span class="badge-inline"><?= $pendingReportsCount ?></span><?php endif; ?>
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

    <!-- Main -->
    <main>

        <section class="form-card">

            <div class="form-content">

                <h1 class="page-title">
                    ✍️ 分享你的想法
                </h1>

                <div class="page-desc">
                    你的文字會像流星一樣飛進論壇宇宙 ✨
                </div>

                <form id="postForm"
                      action="includes/post.inc.php"
                      method="POST"
                      enctype="multipart/form-data">

                    <!-- Category -->
                    <div class="form-group">
                        <label>📚 選擇看板</label>

                        <select name="category_id" required>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>">
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Title -->
                    <div class="form-group">
                        <label>📝 文章標題</label>

                        <input type="text"
                               name="title"
                               maxlength="120"
                               placeholder="輸入一個吸引人的標題..."
                               required>
                    </div>

                    <!-- Content -->
                    <div class="form-group">
                        <label>📖 文章內容</label>

                        <!-- 可編輯區塊 -->
                        <div id="postContentEditor" 
                             class="rich-editor" 
                             contenteditable="true" 
                             placeholder="輸入你的內容..."></div>
                        
                        <!-- 隱藏打包傳送給後端 -->
                        <textarea name="content" id="hiddenContent" style="display:none;"></textarea>

                        <div class="attach-box">

                            <div class="attachment-controls">

                                <button type="button"
                                        class="btn-control"
                                        onclick="document.getElementById('imgInput').click()">
                                    📷 圖片
                                </button>

                                <button type="button"
                                        class="btn-control"
                                        onclick="document.getElementById('vidInput').click()">
                                    🎬 影片
                                </button>

                            </div>
                            
                            <!-- ===== AI 文章潤色工具列 ===== -->
                            <div class="ai-controls">
                                <select id="aiStyleSelect" class="style-select" title="選擇修飾風格">
                                    <option value="professional">🛡️ 專業職場</option>
                                    <option value="poetic">✨ 文學優美</option>
                                    <option value="humorous">🤪 幽默風趣</option>
                                    <option value="simple">💡 通俗易懂</option>
                                </select>
                                <button type="button" class="btn-ai-polish" id="aiPolishBtn" onclick="polishArticle()">
                                    🔮 AI 潤色文章
                                </button>
                            </div>

                            <input type="file"
                                   id="imgInput"
                                   accept="image/*"
                                   multiple
                                   style="display:none;">

                            <input type="file"
                                   id="vidInput"
                                   accept="video/mp4,video/webm,video/ogg"
                                   multiple
                                   style="display:none;">

                        </div>
                    </div>

                    <!-- Hidden -->
                    <input type="file"
                           name="post_imgs[]"
                           id="hiddenFiles"
                           multiple
                           style="display:none;">

                    <input type="file"
                           name="post_vids[]"
                           id="hiddenVids"
                           multiple
                           style="display:none;">

                    <!-- Buttons -->
                    <div class="button-group">

                        <a href="index.php" class="btn-cancel">
                            取消
                        </a>

                        <button type="submit"
                                name="submit_post"
                                class="btn-submit">
                            🚀 發布文章
                        </button>

                    </div>

                </form>

            </div>

        </section>

    </main>

</div>

<!-- ===== AI 潤色對比確認彈窗 ===== -->
<div class="modal-overlay" id="polishModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">🔮 AI 潤色結果對比</h3>
            <button class="btn-control" onclick="closePolishModal()" style="padding: 5px 10px;">✕</button>
        </div>
        <div class="modal-body">
            <div class="comparison-box">
                <span class="comparison-label">原先的內容</span>
                <div class="comparison-content" id="originalTextContent" style="opacity: 0.7;"></div>
            </div>
            <div class="comparison-box">
                <span class="comparison-label" style="color: var(--accent-color);">✨ AI 修飾後的內容</span>
                <div class="comparison-content" id="polishedTextContent" style="border-color: var(--accent-color);"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-cancel" onclick="closePolishModal()" style="margin:0;">取消使用</button>
            <button class="btn-submit" onclick="applyPolishResult()" style="margin:0; flex:none; padding: 12px 28px;">✔️ 替換成此內容</button>
        </div>
    </div>
</div>

<script>
/* =========================
    Theme (與 profile.php 相同的本機儲存與渲染邏輯)
========================= */
const themeBtn = document.getElementById('themeBtn');
const currentTheme = localStorage.getItem('theme') || 'light';

document.body.setAttribute('data-theme', currentTheme);

themeBtn.onclick = () => {
    const targetTheme =
        document.body.getAttribute('data-theme') === 'dark'
        ? 'light'
        : 'dark';

    document.body.setAttribute('data-theme', targetTheme);
    localStorage.setItem('theme', targetTheme);
};

/* =========================
    User Dropdown Menu
========================= */
const userTrigger = document.getElementById('userTrigger');
const dropdownMenu = document.getElementById('dropdownMenu');
if (userTrigger && dropdownMenu) {
    userTrigger.onclick = (e) => { 
        e.stopPropagation(); 
        dropdownMenu.classList.toggle('active'); 
    };
    document.addEventListener('click', (e) => {
        if (!userTrigger.contains(e.target)) dropdownMenu.classList.remove('active');
    });
}

/* =========================
    Editor & Files
========================= */
let imgList = [];
let vidList = [];

const editor = document.getElementById('postContentEditor');

// 初始化 Placeholder 效果
if (editor.innerText.trim() === '') {
    editor.innerHTML = '<span style="color: var(--text-muted);" id="placeholderSpan">輸入你的內容...</span>';
}
editor.addEventListener('focus', function() {
    const placeholder = document.getElementById('placeholderSpan');
    if (placeholder) {
        editor.innerHTML = '';
    }
});
editor.addEventListener('blur', function() {
    if (editor.innerText.trim() === '') {
        editor.innerHTML = '<span style="color: var(--text-muted);" id="placeholderSpan">輸入你的內容...</span>';
    }
});

// 在游標處插入 HTML 節點的函式
function insertElementAtCursor(el) {
    editor.focus();
    const sel = window.getSelection();
    if (sel.getRangeAt && sel.rangeCount) {
        let range = sel.getRangeAt(0);
        
        // 如果目前還在 placeholder 狀態則清空
        const placeholder = document.getElementById('placeholderSpan');
        if (placeholder) {
            editor.innerHTML = '';
            range = sel.getRangeAt(0);
        }
        
        range.deleteContents();
        range.insertNode(el);
        
        range = range.cloneRange();
        range.setStartAfter(el);
        range.collapse(true);
        sel.removeAllRanges();
        sel.addRange(range);
    } else {
        editor.appendChild(el);
    }
}

/* --- 安全優化：單檔大小限制常數 (10MB / 50MB) --- */
const MAX_IMAGE_SIZE = 10 * 1024 * 1024; // 10MB
const MAX_VIDEO_SIZE = 50 * 1024 * 1024; // 50MB

/* --- 核心優化：將原本的 Base64 直上，改成前端 Canvas 智慧壓縮壓縮 --- */
document.getElementById('imgInput').addEventListener('change', function(){
    Array.from(this.files).forEach(file => {
        if (file.size > MAX_IMAGE_SIZE) {
            alert(`圖片「${file.name}」太大了！單張圖片上限為 10MB，請壓縮後再上傳。`);
            return;
        }

        imgList.push(file);
        const fileIndex = imgList.length - 1;

        const reader = new FileReader();
        reader.onload = function(e) {
            const img = new Image();
            img.onload = function() {
                const canvas = document.createElement('canvas');
                let width = img.width;
                let height = img.height;
                
                // 限制最大預覽寬高為 1000px，在兼顧清晰度前提下極大限度縮小檔案體積
                const MAX_WIDTH = 1000;
                const MAX_HEIGHT = 1000;
                
                if (width > height) {
                    if (width > MAX_WIDTH) {
                        height *= MAX_WIDTH / width;
                        width = MAX_WIDTH;
                    }
                } else {
                    if (height > MAX_HEIGHT) {
                        width *= MAX_HEIGHT / height;
                        height = MAX_HEIGHT;
                    }
                }
                
                canvas.width = width;
                canvas.height = height;
                
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, width, height);
                
                // 將圖片轉換成 JPEG 格式，並以 0.7 的中高壓縮率進行壓縮
                // 這會將 5MB 的大照片直接優化到 50KB ~ 80KB 左右，徹底擺脫 MySQL 2006 crash
                const compressedBase64 = canvas.toDataURL('image/jpeg', 0.7);
                
                const imgEl = document.createElement('img');
                imgEl.src = compressedBase64;
                imgEl.dataset.type = 'img';
                imgEl.dataset.index = fileIndex;
                insertElementAtCursor(imgEl);
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    });
    this.value = '';
});

/* --- 核心優化：影片捨棄 Base64，全面改用本地臨時指標網址 Blob URL 預覽 --- */
document.getElementById('vidInput').addEventListener('change', function(){
    Array.from(this.files).forEach(file => {
        if (file.size > MAX_VIDEO_SIZE) {
            alert(`影片「${file.name}」太大了！單個影片上限為 50MB，請剪輯或降低解析度後再上傳。`);
            return;
        }

        vidList.push(file);
        const fileIndex = vidList.length - 1;

        // 全面改用 URL.createObjectURL，建立只屬於當前瀏覽器執行的本地指標
        // 編輯器內預覽時將會是 0 位元組（0 bytes）文字負荷，在按下發布文章時只傳送短短的 blob: 指標
        // 完美的將影片交給後端 post_vids[] 去進行真正的實體檔案上傳，完全不佔用 SQL 資料庫封包
        const objectUrl = URL.createObjectURL(file);

        const video = document.createElement('video');
        video.src = objectUrl;
        video.controls = true;
        video.dataset.type = 'vid';
        video.dataset.index = fileIndex;
        insertElementAtCursor(video);
    });
    this.value = '';
});

/* =========================
    AI 文章潤色互動邏輯
========================= */
const polishModal = document.getElementById('polishModal');
const originalTextContent = document.getElementById('originalTextContent');
const polishedTextContent = document.getElementById('polishedTextContent');
const aiPolishBtn = document.getElementById('aiPolishBtn');
let polishedResultHTML = ""; // 儲存 AI 潤色完成後的完整 HTML

async function polishArticle() {
    // 檢查是否有 Placeholder 內容或空白
    const placeholder = document.getElementById('placeholderSpan');
    if (placeholder || editor.innerText.trim() === '') {
        alert("請先輸入一些文章內容再進行 AI 潤色喔！✍️");
        return;
    }

    const currentContent = editor.innerHTML;
    const selectedStyle = document.getElementById('aiStyleSelect').value;

    // 按鈕進入 Loading 狀態
    aiPolishBtn.disabled = true;
    const originalBtnText = aiPolishBtn.innerHTML;
    aiPolishBtn.innerHTML = `<span class="spinner"></span> 魔法修飾中...`;

    try {
        const response = await fetch('api_ai_polish.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                content: currentContent,
                style: selectedStyle
            })
        });

        const data = await response.json();
        
        if (data && data.polished_content) {
            // 保存潤色成果
            polishedResultHTML = data.polished_content;

            // 將 HTML 渲染到對比 Modal 中
            originalTextContent.innerHTML = currentContent;
            polishedTextContent.innerHTML = polishedResultHTML;

            // 開啟對比 Modal
            polishModal.classList.add('open');
        } else {
            alert(data.error || "潤色失敗，請稍後再試一次！💨");
        }
    } catch (error) {
        alert("網路連線失敗，請檢查您的伺服器與 API 金鑰狀態。");
    } finally {
        // 恢復按鈕狀態
        aiPolishBtn.disabled = false;
        aiPolishBtn.innerHTML = originalBtnText;
    }
}

function closePolishModal() {
    polishModal.classList.remove('open');
}

function applyPolishResult() {
    if (polishedResultHTML) {
        editor.innerHTML = polishedResultHTML;
    }
    closePolishModal();
}

// 點擊彈窗遮罩關閉彈窗
polishModal.onclick = (e) => {
    if (e.target === polishModal) {
        closePolishModal();
    }
};

/* Submit 表單送出處理 */
document.getElementById('postForm').addEventListener('submit', function(e){
    e.preventDefault();

    // 檢查是否有 Placeholder 內容
    const placeholder = document.getElementById('placeholderSpan');
    if (placeholder) {
        editor.innerHTML = '';
    }

    // 將可編輯區塊內的純文字或 HTML 結構同步到隱藏的 textarea 送出
    document.getElementById('hiddenContent').value = editor.innerHTML;

    // 重新過濾被使用者留在編輯器裡面的圖片檔案
    const remainingImgs = editor.querySelectorAll('img[data-type="img"]');
    const dataTransferImg = new DataTransfer();
    remainingImgs.forEach(img => {
        const idx = parseInt(img.dataset.index);
        if(imgList[idx]) {
            dataTransferImg.items.add(imgList[idx]);
        }
    });
    document.getElementById('hiddenFiles').files = dataTransferImg.files;

    // 重新過濾被使用者留在編輯器裡面的影片檔案
    // 改用 FormData 手動 append，避免動態設定 .files 在部分瀏覽器/伺服器環境失效
    const remainingVids = editor.querySelectorAll('video[data-type="vid"]');
    const formData = new FormData(this);

    // fetch 送出時 submit button 不會被帶入，手動補上讓後端驗證通過
    formData.append('submit_post', '1');

    // 移除原本空的 hiddenVids（避免送出空的 post_vids[]）
    formData.delete('post_vids[]');

    remainingVids.forEach(vid => {
        const idx = parseInt(vid.dataset.index);
        if(vidList[idx]) {
            formData.append('post_vids[]', vidList[idx], vidList[idx].name);
        }
    });

    // 用 fetch 送出 FormData，完整保留所有欄位與檔案
    fetch(this.action, {
        method: 'POST',
        body: formData
    }).then(response => {
        // 後端若有 redirect header，fetch 會自動跟隨；若後端回傳 HTML 頁面則導向
        if (response.redirected) {
            window.location.href = response.url;
        } else {
            return response.text().then(html => {
                document.open();
                document.write(html);
                document.close();
            });
        }
    }).catch(err => {
        alert('發布失敗，請稍後再試！');
        console.error(err);
    });
});
</script>

</body>
</html>