<?php
session_start();
require_once "includes/dbh.inc.php";

// 取得目標用戶 ID
$target_user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$current_uid = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] == 1;

if ($target_user_id <= 0) {
    header("Location: index.php");
    exit();
}

// --- 處理編輯個人資料提交 ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. 處理簡介更新
    if (isset($_POST['update_bio'])) {
        if ($current_uid && $current_uid == $target_user_id) {
            $new_bio = $_POST['bio'];
            try {
                $update_stmt = $pdo->prepare("UPDATE users SET bio = ? WHERE id = ?");
                $update_stmt->execute([$new_bio, $current_uid]);
                header("Location: profile.php?id=" . $target_user_id . "&success=bio");
                exit();
            } catch (PDOException $e) {
                $error_msg = "更新簡介失敗：" . $e->getMessage();
            }
        }
    }

    // 2. 處理大頭照更新
    if (isset($_FILES['profile_img']) && $current_uid == $target_user_id) {
        $file = $_FILES['profile_img'];
        $fileName = $file['name'];
        $fileTmpName = $file['tmp_name'];
        $fileSize = $file['size'];
        $fileError = $file['error'];
        
        $fileExt = explode('.', $fileName);
        $fileActualExt = strtolower(end($fileExt));
        $allowed = array('jpg', 'jpeg', 'png', 'gif');

        if (in_array($fileActualExt, $allowed)) {
            if ($fileError === 0) {
                if ($fileSize < 5000000) { // 限制 5MB
                    $fileNameNew = "profile_" . $current_uid . "_" . time() . "." . $fileActualExt;
                    $fileDestination = 'uploads/users_profile_img/' . $fileNameNew;
                    
                    if (move_uploaded_file($fileTmpName, $fileDestination)) {
                        try {
                            $update_stmt = $pdo->prepare("UPDATE users SET profile_img = ? WHERE id = ?");
                            $update_stmt->execute([$fileNameNew, $current_uid]);
                            // 同時更新 Session 讓導覽列同步
                            $_SESSION['profile_img'] = $fileNameNew;
                            header("Location: profile.php?id=" . $target_user_id . "&success=avatar");
                            exit();
                        } catch (PDOException $e) {
                            $error_msg = "資料庫更新失敗";
                        }
                    }
                } else { $error_msg = "檔案太大"; }
            } else { $error_msg = "上傳錯誤"; }
        } else { $error_msg = "不支援此檔案類型"; }
    }
}

$pendingReportsCount = 0;
$unreadAnnouncementsCount = 0;
if ($current_uid) {
    try {
        if ($isAdmin) {
            $report_stmt = $pdo->query("SELECT COUNT(*) FROM reports WHERE status = 0");
            $pendingReportsCount = (int)$report_stmt->fetchColumn();
        }
        $unread_sql = "SELECT COUNT(*) FROM announcements WHERE created_at > (SELECT IFNULL(last_announcement_view, '1970-01-01 00:00:00') FROM users WHERE id = ?)";
        $unread_stmt = $pdo->prepare($unread_sql);
        $unread_stmt->execute([$current_uid]);
        $unreadAnnouncementsCount = (int)$unread_stmt->fetchColumn();
    } catch (PDOException $e) {}
}

try {
    // 1. 取得目標用戶資訊
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$target_user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        die("找不到該用戶。");
    }

    // 2. 取得該用戶發布的文章 (安全按讚統計關聯)
    $user_posts = [];
    try {
        // 優先嘗試：從 `likes` 關聯表統計按讚數
        $post_stmt = $pdo->prepare("SELECT posts.*, categories.name AS cat_name, 
            (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.id) AS like_count 
            FROM posts 
            JOIN categories ON posts.category_id = categories.id 
            WHERE posts.user_id = ? 
            ORDER BY created_at DESC");
        $post_stmt->execute([$target_user_id]);
        $user_posts = $post_stmt->fetchAll();
    } catch (PDOException $e) {
        try {
            // 次要嘗試：從 `post_likes` 關聯表統計
            $post_stmt = $pdo->prepare("SELECT posts.*, categories.name AS cat_name, 
                (SELECT COUNT(*) FROM post_likes WHERE post_likes.post_id = posts.id) AS like_count 
                FROM posts 
                JOIN categories ON posts.category_id = categories.id 
                WHERE posts.user_id = ? 
                ORDER BY created_at DESC");
            $post_stmt->execute([$target_user_id]);
            $user_posts = $post_stmt->fetchAll();
        } catch (PDOException $e2) {
            // 終極備用方案：若無額外按讚關聯表，直接抓取並檢查 posts 本身的按讚數欄位
            $post_stmt = $pdo->prepare("SELECT posts.*, categories.name AS cat_name FROM posts JOIN categories ON posts.category_id = categories.id WHERE posts.user_id = ? ORDER BY created_at DESC");
            $post_stmt->execute([$target_user_id]);
            $user_posts = $post_stmt->fetchAll();
            
            // 手動過濾賦值
            foreach ($user_posts as &$post) {
                if (isset($post['likes_count'])) {
                    $post['like_count'] = $post['likes_count'];
                } elseif (isset($post['likes'])) {
                    $post['like_count'] = $post['likes'];
                } else {
                    $post['like_count'] = 0;
                }
            }
        }
    }

    // 3. 好友關係判定
    $friend_status = 'none'; 
    if ($current_uid && $current_uid != $target_user_id) {
        $f_stmt = $pdo->prepare("SELECT status, user_id FROM friends WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)");
        $f_stmt->execute([$current_uid, $target_user_id, $target_user_id, $current_uid]);
        $relation = $f_stmt->fetch();
        
        if ($relation) {
            if ($relation['status'] === 'accepted') {
                $friend_status = 'accepted';
            } else {
                $friend_status = ($relation['user_id'] == $current_uid) ? 'pending_sent' : 'pending_received';
            }
        }
    }

    // 4. 取得該目標用戶的好友人數 (使用 DISTINCT 防止雙向資料重複計數)
    $friend_count = 0;
    try {
        $count_sql = "SELECT COUNT(DISTINCT CASE WHEN user_id = ? THEN friend_id ELSE user_id END) 
                      FROM friends 
                      WHERE (user_id = ? OR friend_id = ?) AND status = 'accepted'";
        $count_stmt = $pdo->prepare($count_sql);
        $count_stmt->execute([$target_user_id, $target_user_id, $target_user_id]);
        $friend_count = (int)$count_stmt->fetchColumn();
    } catch (PDOException $e) {
        $friend_count = 0; // 防呆處理
    }

} catch (PDOException $e) {
    die("資料讀取失敗: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($user['username']) ?> 的個人資料 - PHP Forum</title>
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
            --admin-soft: rgba(245, 158, 11, 0.15);
            --danger-color: #ef4444;
            --success-color: #22c55e;
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
        }

        body { 
            font-family: 'Inter', system-ui, sans-serif; 
            background-color: var(--bg-color); 
            margin: 0; 
            color: var(--text-color); 
            transition: background-color 0.3s, color 0.3s; 
        }

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
        .notification-badge { position: absolute; top: -2px; right: -2px; background: var(--danger-color); color: white; font-size: 0.65rem; min-width: 18px; height: 18px; padding: 0 4px; border-radius: 10px; display: flex; justify-content: center; align-items: center; border: 2px solid var(--card-bg); font-weight: 800; }
        .dropdown-menu { position: absolute; right: 0; top: 125%; width: 240px; background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.15); display: none; flex-direction: column; overflow: hidden; z-index: 1100; }
        .dropdown-menu.active { display: flex; }
        .dropdown-menu a { padding: 12px 20px; text-decoration: none; color: var(--text-color); font-weight: 600; font-size: 0.9rem; transition: 0.2s; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
        .dropdown-menu a:hover { background: var(--sidebar-item-hover); color: var(--accent-color); }

        .main-wrapper { max-width: 1400px; margin: 20px auto; padding: 0 25px; display: grid; grid-template-columns: 280px 1fr; gap: 30px; }

        .profile-header { background: var(--card-bg); border-radius: 24px; border: 1px solid var(--border-color); overflow: hidden; margin-bottom: 25px; }
        .profile-cover { height: 160px; background: var(--header-gradient); opacity: 0.8; }
        .profile-info-section { padding: 0 40px 30px 40px; position: relative; display: flex; justify-content: space-between; align-items: flex-end; margin-top: -60px; }
        
        .avatar-container { position: relative; width: 120px; height: 120px; }
        .profile-avatar { width: 120px; height: 120px; border-radius: 30px; border: 6px solid var(--card-bg); object-fit: cover; background: var(--card-bg); box-shadow: 0 10px 20px rgba(0,0,0,0.1); transition: 0.3s; }
        
        .editable-avatar { cursor: pointer; }
        .editable-avatar:hover .profile-avatar { opacity: 0.8; filter: brightness(0.7); }
        .avatar-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; justify-content: center; align-items: center; color: white; opacity: 0; transition: 0.3s; pointer-events: none; font-size: 1.5rem; }
        .editable-avatar:hover .avatar-overlay { opacity: 1; }

        .profile-actions { display: flex; gap: 10px; margin-bottom: 10px; }

        .action-btn { padding: 10px 20px; border-radius: 12px; font-weight: 700; border: none; cursor: pointer; text-decoration: none; font-size: 0.9rem; transition: 0.2s; }
        .btn-primary { background: var(--accent-color); color: white; }
        .btn-outline { background: transparent; border: 1.5px solid var(--border-color); color: var(--text-color); }
        .btn-outline:hover { background: var(--sidebar-item-hover); }

        .profile-bio { padding: 0 40px 40px 40px; }
        .bio-text { color: var(--text-muted); line-height: 1.6; max-width: 600px; }
        
        .bio-edit-form { display: none; flex-direction: column; gap: 15px; max-width: 600px; }
        .bio-textarea { width: 100%; min-height: 120px; border-radius: 12px; border: 1px solid var(--border-color); background: var(--bg-color); color: var(--text-color); padding: 15px; font-family: inherit; font-size: 1rem; resize: vertical; }
        .edit-actions { display: flex; gap: 10px; }

        .post-card { background: var(--card-bg); border-radius: 20px; padding: 25px; margin-bottom: 20px; border: 1px solid var(--border-color); transition: 0.3s; }
        .post-card:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.05); }

        .menu-link { display: flex; align-items: center; gap: 10px; padding: 12px 15px; margin-bottom: 5px; border-radius: 12px; text-decoration: none; color: var(--text-color); font-weight: 600; transition: 0.2s; cursor: pointer; }
        .menu-link:hover { background: var(--sidebar-item-hover); color: var(--accent-color); }
        .menu-label { font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin: 20px 0 10px 10px; }
        .badge-inline { background: var(--danger-color); color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.7rem; margin-left: auto; font-weight: 800; }
        .badge-count { background: var(--border-color); color: var(--text-color); font-size: 0.75rem; padding: 2px 10px; border-radius: 10px; margin-left: auto; font-weight: 700; transition: background 0.3s, color 0.3s; }

        @media (max-width: 900px) {
            .main-wrapper { grid-template-columns: 1fr; }
            .left-sidebar { display: block; margin-bottom: 15px; }
            .profile-info-section { flex-direction: column; align-items: center; text-align: center; margin-top: -60px; }
            .profile-actions { margin-top: 20px; }
        }
    </style>
</head>
<body data-theme="light">

<header>
    <div class="nav-container">
        <a href="index.php" class="logo" style="text-decoration:none"><h1>🚀 PHP Forum</h1></a>
        <div style="display:flex; align-items:center; gap:15px;">
            <button id="themeBtn" title="切換主題" style="background:none; border:none; cursor:pointer; font-size:1.3rem; padding:5px; border-radius:50%;">🌓</button>
            <?php if (isset($_SESSION["user_id"])): ?>
                <div style="position:relative;">
                    <div class="user-trigger" id="userTrigger">
                        <img src="<?= !empty($_SESSION['profile_img']) ? "uploads/users_profile_img/".$_SESSION['profile_img'] : "uploads/default_avatar.png" ?>" style="width:32px; height:32px; border-radius:50%; object-fit:cover; border: 2px solid <?= $isAdmin ? 'var(--admin-color)' : 'var(--accent-color)' ?>;">
                        <span style="<?= $isAdmin ? 'color: var(--admin-color);' : '' ?>"><?= htmlspecialchars($_SESSION["username"]) ?></span>
                        <?php 
                        $totalNotif = $unreadAnnouncementsCount + ($isAdmin ? $pendingReportsCount : 0);
                        if ($totalNotif > 0): ?>
                            <div class="notification-badge"><?= $totalNotif ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="dropdown-menu" id="dropdownMenu">
                        <div style="padding: 10px 20px; font-size: 0.7rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase;">使用者功能</div>
                        <a href="profile.php?id=<?= $_SESSION['user_id'] ?>">👤 我的個人資料</a>
                        <a href="view_announcements.php">📢 系統公告通知
                            <?php if($unreadAnnouncementsCount > 0): ?><span class="badge-inline"><?= $unreadAnnouncementsCount ?></span><?php endif; ?>
                        </a>
                        <a href="create_post.php">✍️ 撰寫新文章</a>
                        <?php if ($isAdmin): ?>
                            <div style="padding: 10px 20px; font-size: 0.7rem; color: var(--admin-color); font-weight: 800; text-transform: uppercase; background: var(--admin-soft);">管理員功能</div>
                            <a href="admin_announcement.php" style="color:var(--admin-color)">📢 發布系統公告</a>
                            <a href="admin_reports.php" style="color:var(--admin-color)">🚩 檢舉審核 
                                <?php if($pendingReportsCount > 0): ?><span class="badge-inline"><?= $pendingReportsCount ?></span><?php endif; ?>
                            </a>
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
    <aside class="left-sidebar">
        <div class="menu-label">個人檔案選單</div>
        <a id="tabBio" class="menu-link" style="background: var(--accent-soft); color: var(--accent-color);">👤 用戶簡介</a>
        <a id="tabPosts" class="menu-link">✍️ 歷史發文紀錄 <span class="badge-count" id="postCountBadge"><?= count($user_posts) ?></span></a>
        <a href="index.php" class="menu-link" style="margin-top: 20px;">🏠 返回探索牆</a>
    </aside>

    <main>
        <?php if(isset($error_msg)): ?>
            <div style="background:var(--danger-color); color:white; padding:15px; border-radius:12px; margin-bottom:20px; font-weight:600;">⚠️ <?= $error_msg ?></div>
        <?php endif; ?>

        <section class="profile-header">
            <div class="profile-cover"></div>
            <div class="profile-info-section">
                <!-- 頭像部分：如果是本人，點擊可更換 -->
                <div class="avatar-container <?= ($current_uid == $target_user_id) ? 'editable-avatar' : '' ?>" id="avatarBox">
                    <img src="<?= !empty($user['profile_img']) ? "uploads/users_profile_img/".$user['profile_img'] : "uploads/default_avatar.png" ?>" class="profile-avatar">
                    <?php if ($current_uid == $target_user_id): ?>
                        <div class="avatar-overlay">📷</div>
                        <form id="avatarForm" method="POST" enctype="multipart/form-data" style="display:none;">
                            <input type="file" name="profile_img" id="avatarInput" accept="image/*">
                        </form>
                    <?php endif; ?>
                </div>
                
                <div class="profile-actions">
                    <?php if ($current_uid): ?>
                        <?php if ($current_uid == $target_user_id): ?>
                            <button id="toggleEditBtn" class="action-btn btn-outline">🛠️ 快速編輯簡介</button>
                        <?php else: ?>
                            <?php if ($friend_status === 'accepted'): ?>
                                <span class="action-btn" style="background:var(--success-color); color:white; cursor:default;">✔️ 已是好友</span>
                                <a href="chat.php?user_id=<?= $target_user_id ?>" class="action-btn btn-primary">💬 發送私訊</a>
                            <?php elseif ($friend_status === 'pending_sent'): ?>
                                <button class="action-btn btn-outline" disabled>⏳ 已送出請求</button>
                            <?php elseif ($friend_status === 'pending_received'): ?>
                                <form action="includes/friend_action.inc.php" method="POST" style="display:inline;">
                                    <input type="hidden" name="friend_id" value="<?= $target_user_id ?>">
                                    <button type="submit" name="accept_friend" class="action-btn btn-primary">🤝 接受好友請求</button>
                                </form>
                            <?php else: ?>
                                <form action="includes/friend_action.inc.php" method="POST" style="display:inline;">
                                    <input type="hidden" name="friend_id" value="<?= $target_user_id ?>">
                                    <button type="submit" name="add_friend" class="action-btn btn-primary">➕ 加為好友</button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- 用戶簡介分頁主體區 -->
            <div class="profile-bio" id="bioTabContent">
                <h1 style="margin: 0 0 5px 0; font-size: 1.8rem; font-weight: 800;"><?= htmlspecialchars($user['username']) ?></h1>
                
                <!-- 新增：好友人數顯示徽章 -->
                <div style="display: flex; gap: 10px; margin-bottom: 20px; align-items: center;">
                    <span style="font-size: 0.85rem; background: var(--accent-soft); color: var(--accent-color); padding: 5px 12px; border-radius: 50px; font-weight: 700; display: inline-flex; align-items: center; gap: 5px;">
                        👥 <?= $friend_count ?> 位好友
                    </span>
                </div>
                
                <!-- 顯示模式 -->
                <div id="bioDisplay" class="bio-text">
                    <?= !empty($user['bio']) ? nl2br(htmlspecialchars($user['bio'])) : "這名用戶很神秘，還沒有填寫自我介紹。" ?>
                </div>

                <!-- 編輯模式 (僅本人可見) -->
                <?php if($current_uid == $target_user_id): ?>
                    <form id="bioEditForm" class="bio-edit-form" method="POST" action="">
                        <textarea name="bio" class="bio-textarea" placeholder="介紹一下你自己..."><?= htmlspecialchars($user['bio']) ?></textarea>
                        <div class="edit-actions">
                            <button type="submit" name="update_bio" class="action-btn btn-primary">💾 儲存修改</button>
                            <button type="button" id="cancelEditBtn" class="action-btn btn-outline">取消</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </section>

        <section id="postsTabContent" style="display: none;">
            <h3 style="margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                ✍️ 歷史發布內容 
                <span style="font-size: 0.9rem; background: var(--border-color); padding: 2px 10px; border-radius: 10px;"><?= count($user_posts) ?></span>
            </h3>
            
            <?php if (count($user_posts) > 0): ?>
                <?php foreach ($user_posts as $post): ?>
                    <article class="post-card">
                        <span style="background:var(--accent-soft); color:var(--accent-color); font-size:0.75rem; font-weight:800; padding:4px 12px; border-radius:50px;"># <?= htmlspecialchars($post['cat_name']) ?></span>
                        <h2 style="margin:12px 0;"><a href="view_post.php?id=<?= $post['id'] ?>" style="text-decoration:none; color:var(--text-color); font-weight:800;"><?= htmlspecialchars($post['title']) ?></a></h2>
                        <div style="color:var(--text-muted); font-size:0.9rem; margin-bottom:10px; display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                            <span>📅 發布於 <?= date('Y/m/d H:i', strtotime($post['created_at'])) ?></span>
                            <!-- 新增讚數顯示 -->
                            <span style="display: flex; align-items: center; gap: 4px; color: var(--danger-color); font-weight: 700;">
                                ❤️ <?= (int)($post['like_count']) ?> 個讚
                            </span>
                        </div>
                        <p style="color:var(--text-muted); line-height:1.6; margin:0;"><?= htmlspecialchars(mb_substr(strip_tags($post['content']), 0, 120)) ?>...</p>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="post-card" style="text-align: center; padding: 60px; color: var(--text-muted);">
                    此用戶尚未發表任何文章。
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>

<script>
    // 主題切換
    const themeBtn = document.getElementById('themeBtn');
    const currentTheme = localStorage.getItem('theme') || 'light';
    document.body.setAttribute('data-theme', currentTheme);

    themeBtn.onclick = () => {
        const targetTheme = document.body.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        document.body.setAttribute('data-theme', targetTheme);
        localStorage.setItem('theme', targetTheme);
    };

    // 下拉選單
    const userTrigger = document.getElementById('userTrigger');
    const dropdownMenu = document.getElementById('dropdownMenu');
    if(userTrigger && dropdownMenu) {
        userTrigger.onclick = (e) => { 
            e.stopPropagation(); 
            dropdownMenu.classList.toggle('active'); 
        };
        document.addEventListener('click', (e) => {
            if (!userTrigger.contains(e.target)) dropdownMenu.classList.remove('active');
        });
    }

    // 編輯大頭照觸發
    const avatarBox = document.getElementById('avatarBox');
    const avatarInput = document.getElementById('avatarInput');
    const avatarForm = document.getElementById('avatarForm');

    if (avatarBox && avatarInput) {
        avatarBox.onclick = () => avatarInput.click();
        avatarInput.onchange = () => {
            if (avatarInput.value) {
                avatarForm.submit();
            }
        };
    }

    // 編輯個人簡介互動
    const toggleEditBtn = document.getElementById('toggleEditBtn');
    const cancelEditBtn = document.getElementById('cancelEditBtn');
    const bioDisplay = document.getElementById('bioDisplay');
    const bioEditForm = document.getElementById('bioEditForm');

    if (toggleEditBtn && bioEditForm) {
        toggleEditBtn.onclick = () => {
            bioDisplay.style.display = 'none';
            bioEditForm.style.display = 'flex';
            toggleEditBtn.style.display = 'none';
        };

        cancelEditBtn.onclick = () => {
            bioDisplay.style.display = 'block';
            bioEditForm.style.display = 'none';
            toggleEditBtn.style.display = 'inline-block';
        };
    }

    // --- 新增：動態分頁切換互動邏輯 ---
    const tabBio = document.getElementById('tabBio');
    const tabPosts = document.getElementById('tabPosts');
    const bioTabContent = document.getElementById('bioTabContent');
    const postsTabContent = document.getElementById('postsTabContent');
    const postCountBadge = document.getElementById('postCountBadge');

    if (tabBio && tabPosts && bioTabContent && postsTabContent) {
        // 用戶簡介分頁
        tabBio.onclick = (e) => {
            e.preventDefault();
            // 切換左側選單高亮狀態
            tabBio.style.background = 'var(--accent-soft)';
            tabBio.style.color = 'var(--accent-color)';
            tabPosts.style.background = 'transparent';
            tabPosts.style.color = 'var(--text-color)';
            if (postCountBadge) {
                postCountBadge.style.background = 'var(--border-color)';
                postCountBadge.style.color = 'var(--text-color)';
            }
            
            // 顯示/隱藏對應內容區
            bioTabContent.style.display = 'block';
            postsTabContent.style.display = 'none';
            if (toggleEditBtn) {
                toggleEditBtn.style.display = 'inline-block';
            }
            // 重置個人檔案編輯為非編輯模式
            if (bioDisplay) bioDisplay.style.display = 'block';
            if (bioEditForm) bioEditForm.style.display = 'none';
        };

        // 歷史發文紀錄分頁
        tabPosts.onclick = (e) => {
            e.preventDefault();
            // 切換左側選單高亮狀態
            tabPosts.style.background = 'var(--accent-soft)';
            tabPosts.style.color = 'var(--accent-color)';
            tabBio.style.background = 'transparent';
            tabBio.style.color = 'var(--text-color)';
            if (postCountBadge) {
                postCountBadge.style.background = 'var(--accent-color)';
                postCountBadge.style.color = 'white';
            }
            
            // 顯示/隱藏對應內容區
            bioTabContent.style.display = 'none';
            postsTabContent.style.display = 'block';
            if (toggleEditBtn) {
                toggleEditBtn.style.display = 'none';
            }
        };
    }
</script>
</body>
</html>