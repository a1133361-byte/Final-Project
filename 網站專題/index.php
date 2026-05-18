<?php
session_start();
require_once "includes/dbh.inc.php";

$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$catID = isset($_GET['category']) ? $_GET['category'] : '';
$viewFriendsActivity = isset($_GET['view']) && $_GET['view'] === 'friends_activity';
$viewHistory = isset($_GET['view']) && $_GET['view'] === 'history';

// 判斷是否為管理員
$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] == 1;

$currentCatName = "所有文章";
$currentCatDesc = "探索社群中的最新動態與深度討論。";

// --- 初始化計數器 ---
$pendingReportsCount = 0;
$unreadAnnouncementsCount = 0;

if (isset($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];
    try {
        // 1. 管理員檢舉通知邏輯
        if ($isAdmin) {
            $report_sql = "SELECT COUNT(*) FROM reports WHERE status = 0";
            $report_stmt = $pdo->query($report_sql);
            $pendingReportsCount = (int)$report_stmt->fetchColumn();
        }

        // 2. 未讀公告通知邏輯 (確保欄位 NULL 時也能運作)
        $unread_sql = "SELECT COUNT(*) FROM announcements 
                       WHERE created_at > (
                           SELECT IFNULL(last_announcement_view, '1970-01-01 00:00:00') 
                           FROM users WHERE id = ?
                       )";
        $unread_stmt = $pdo->prepare($unread_sql);
        $unread_stmt->execute([$uid]);
        $unreadAnnouncementsCount = (int)$unread_stmt->fetchColumn();
        
    } catch (PDOException $e) {
        // 靜默錯誤
    }
}

try {
    $cat_query = $pdo->query("SELECT * FROM categories ORDER BY id ASC");
    $all_categories = $cat_query->fetchAll();

    // 找出系統公告的 ID (假設名稱為系統公告)
    $announcementCatID = null;
    foreach ($all_categories as $cat) {
        if ($cat['name'] == '系統公告') {
            $announcementCatID = $cat['id'];
            break;
        }
    }

    if ($catID !== '') {
        foreach ($all_categories as $cat) {
            if ($cat['id'] == $catID) {
                $currentCatName = $cat['name'];
                $currentCatDesc = "歡迎來到 " . $cat['name'] . " 看板，這裡充滿了精彩的內容。";
                break;
            }
        }
    }

    $my_friends = [];
    $friend_ids = [];
    if (isset($_SESSION['user_id'])) {
        $f_sql = "SELECT users.id, users.username, users.profile_img FROM friends JOIN users ON friends.friend_id = users.id WHERE friends.user_id = ? AND friends.status = 'accepted' LIMIT 10";
        $f_stmt = $pdo->prepare($f_sql);
        $f_stmt->execute([$_SESSION['user_id']]);
        $my_friends = $f_stmt->fetchAll();
        $friend_ids = array_column($my_friends, 'id');
    }

    if ($viewFriendsActivity && !empty($friend_ids)) {
        $currentCatName = "好友動態";
        $currentCatDesc = "看看你的好友們最近在忙些什麼。";
        $placeholders = implode(',', array_fill(0, count($friend_ids), '?'));
        
        $activity_sql = "
            (SELECT '發布了文章' as type_cn, 'post' as type, p.id as target_id, p.title as title, p.content as content, p.created_at, u.username, u.profile_img 
             FROM posts p 
             JOIN users u ON p.user_id = u.id 
             WHERE p.user_id IN ($placeholders))
            UNION ALL
            (SELECT '發表了評論' as type_cn, 'comment' as type, p.id as target_id, p.title as title, com.content as content, com.created_at, u.username, u.profile_img 
             FROM comments com
             JOIN posts p ON com.post_id = p.id
             JOIN users u ON com.user_id = u.id
             WHERE com.user_id IN ($placeholders))
            UNION ALL
            (SELECT '點了個讚' as type_cn, 'like' as type, p.id as target_id, p.title as title, '對這篇文章點了個讚' as content, l.created_at, u.username, u.profile_img 
             FROM likes l
             JOIN posts p ON l.post_id = p.id
             JOIN users u ON l.user_id = u.id
             WHERE l.user_id IN ($placeholders))
            ORDER BY created_at DESC LIMIT 50
        ";
        
        $stmt = $pdo->prepare($activity_sql);
        $stmt->execute(array_merge($friend_ids, $friend_ids, $friend_ids));
        $activities = $stmt->fetchAll();
        $posts = [];
    } elseif ($viewHistory && isset($_SESSION['user_id'])) {
        $currentCatName = "瀏覽紀錄";
        $currentCatDesc = "回顧你最近閱讀過的文章紀錄。";
        
        // 查詢當前使用者的瀏覽紀錄（關聯 posts, users 與 categories）
        $history_sql = "
            SELECT posts.*, users.username, users.profile_img, categories.name AS cat_name, bh.viewed_at 
            FROM browsing_history bh
            JOIN posts ON bh.post_id = posts.id
            JOIN users ON posts.user_id = users.id
            JOIN categories ON posts.category_id = categories.id
            WHERE bh.user_id = ?
            ORDER BY bh.viewed_at DESC 
            LIMIT 50
        ";
        $stmt = $pdo->prepare($history_sql);
        $stmt->execute([$_SESSION['user_id']]);
        $posts = $stmt->fetchAll();
        $activities = [];
    } else {
        $sql = "SELECT posts.*, users.username, users.profile_img, categories.name AS cat_name FROM posts JOIN users ON posts.user_id = users.id JOIN categories ON posts.category_id = categories.id WHERE 1=1";
        if ($searchTerm !== '') $sql .= " AND (posts.title LIKE :search OR posts.content LIKE :search)";
        if ($catID !== '') $sql .= " AND posts.category_id = :catID";
        $sql .= " ORDER BY posts.created_at DESC";
        $stmt = $pdo->prepare($sql);
        if ($searchTerm !== '') $stmt->bindValue(':search', '%' . $searchTerm . '%');
        if ($catID !== '') $stmt->bindValue(':catID', $catID);
        $stmt->execute();
        $posts = $stmt->fetchAll();
        $activities = [];
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
    <title><?= htmlspecialchars($currentCatName) ?> - PHP Forum</title>
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
            --admin-soft: rgba(245, 158, 11, 0.15);
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
            position: sticky; 
            top: 0; 
            z-index: 1000; 
            padding: 12px 0; 
            transition: background-color 0.3s, border-color 0.3s;
        }
        .nav-container { max-width: 1400px; margin: 0 auto; padding: 0 25px; display: flex; justify-content: space-between; align-items: center; }
        .logo h1 { margin: 0; font-size: 1.4rem; font-weight: 800; background: var(--header-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }

        .user-trigger { 
            display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 5px 12px; border-radius: 50px; transition: 0.2s; position: relative;
        }
        .user-trigger:hover { background: var(--sidebar-item-hover); }
        .user-trigger span { font-weight: 700; font-size: 0.95rem; }

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
            width: 240px; 
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

        .main-wrapper { max-width: 1400px; margin: 20px auto; padding: 0 25px; display: grid; grid-template-columns: 260px 1fr 300px; gap: 30px; }

        .left-sidebar { position: sticky; top: 90px; height: fit-content; }
        .menu-label { font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin: 20px 0 10px 10px; }
        
        .menu-btn, .menu-link { 
            display: flex; align-items: center; gap: 10px; width: 100%; box-sizing: border-box;
            padding: 12px 15px; margin-bottom: 5px; border: 1px solid transparent; border-radius: 12px; 
            background: transparent; color: var(--text-color); font-weight: 600; text-align: left; 
            cursor: pointer; transition: 0.2s; text-decoration: none; font-size: 1rem;
        }
        .menu-btn:hover, .menu-link:hover { background: var(--sidebar-item-hover); color: var(--accent-color); }
        .menu-btn.active, .menu-link.active { background: var(--accent-soft); color: var(--accent-color); border-color: rgba(99, 102, 241, 0.3); }

        .admin-sidebar-item { border-left: 3px solid var(--admin-color) !important; }
        .badge-inline { background: var(--danger-color); color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.7rem; margin-left: auto; font-weight: 800; }

        .category-header { background: var(--card-bg); padding: 30px; border-radius: 24px; border: 1px solid var(--border-color); margin-bottom: 25px; position: relative; overflow: hidden; transition: 0.3s; }
        .search-box { background: var(--card-bg); border: 2px solid var(--border-color); border-radius: 18px; padding: 5px 5px 5px 20px; display: flex; gap: 10px; transition: 0.3s; margin-bottom: 30px; }
        .search-box:focus-within { border-color: var(--accent-color); }
        .search-box input { flex: 1; border: none; background: transparent; color: var(--text-color); outline: none; }
        .search-box button { background: var(--accent-color); color: white; border: none; padding: 10px 20px; border-radius: 14px; cursor: pointer; font-weight: 700; }

        .post-card, .activity-card { background: var(--card-bg); border-radius: 20px; padding: 25px; margin-bottom: 20px; border: 1px solid var(--border-color); transition: 0.3s; }
        .post-card:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.05); }

        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: none; justify-content: center; align-items: center; z-index: 2000; }
        .modal-content { background: var(--card-bg); width: 90%; max-width: 500px; padding: 30px; border-radius: 25px; border: 1px solid var(--border-color); }

        @media (max-width: 1100px) { .main-wrapper { grid-template-columns: 1fr 300px; } .left-sidebar { display: none; } }
    </style>
</head>
<body data-theme="light">

<header>
    <div class="nav-container">
        <a href="index.php" class="logo" style="text-decoration:none"><h1>🚀 PHP Forum</h1></a>
        <div style="display:flex; align-items:center; gap:15px;">
            <button id="themeBtn" title="切換主題" style="background:none; border:none; cursor:pointer; font-size:1.3rem; padding:5px; border-radius:50%; transition: 0.2s;">🌓</button>
            <?php if (isset($_SESSION["user_id"])): ?>
                <div style="position:relative;">
                    <div class="user-trigger" id="userTrigger">
                        <img src="<?= !empty($_SESSION['profile_img']) ? "uploads/users_profile_img/".$_SESSION['profile_img'] : "uploads/default_avatar.png" ?>" style="width:32px; height:32px; border-radius:50%; object-fit:cover; border: 2px solid <?= $isAdmin ? 'var(--admin-color)' : 'var(--accent-color)' ?>;">
                        <span style="<?= $isAdmin ? 'color: var(--admin-color);' : '' ?>"><?= htmlspecialchars($_SESSION["username"]) ?></span>
                        <?php 
                        $totalNotif = $unreadAnnouncementsCount + ($isAdmin ? $pendingReportsCount : 0);
                        if ($totalNotif > 0): 
                        ?>
                            <div class="notification-badge"><?= $totalNotif ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="dropdown-menu" id="dropdownMenu">
                        <div style="padding: 10px 20px; font-size: 0.7rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase;">使用者功能</div>
                        <a href="profile.php?id=<?= $_SESSION['user_id'] ?>">👤 我的個人資料</a>
                        <a href="index.php?view=history">🕒 歷史瀏覽紀錄</a>
                        <a href="view_announcements.php">
                            📢 系統公告通知
                            <?php if($unreadAnnouncementsCount > 0): ?>
                                <span class="badge-inline"><?= $unreadAnnouncementsCount ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="create_post.php">✍️ 撰寫新文章</a>
                        
                        <?php if ($isAdmin): ?>
                            <div style="padding: 10px 20px; font-size: 0.7rem; color: var(--admin-color); font-weight: 800; text-transform: uppercase; background: var(--admin-soft);">管理員功能</div>
                            <a href="admin_announcement.php" class="admin-link">📢 發布系統公告</a>
                            <a href="admin_reports.php" class="admin-link">
                                🚩 檢舉審核 
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
    <aside class="left-sidebar">
        <div class="menu-label">主選單</div>
        <a href="index.php" class="menu-link <?= ($catID == '' && !$viewFriendsActivity && !$viewHistory) ? 'active' : '' ?>">🏠 探索牆</a>
        
        <?php if(isset($_SESSION['user_id'])): ?>
            <a href="index.php?view=friends_activity" class="menu-link <?= $viewFriendsActivity ? 'active' : '' ?>">✨ 好友動態</a>
        <?php endif; ?>
        
        <button class="menu-btn" id="openCategories">📂 所有看板</button>

        <!-- 將系統公告獨立出來，放在主選單下面 -->
        <?php if($announcementCatID): ?>
            <a href="index.php?category=<?= $announcementCatID ?>" class="menu-link <?= ($catID == $announcementCatID) ? 'active' : '' ?>">
                📢 系統公告
                <?php if($unreadAnnouncementsCount > 0): ?>
                    <span class="badge-inline"><?= $unreadAnnouncementsCount ?></span>
                <?php endif; ?>
            </a>
        <?php endif; ?>

        <?php if ($isAdmin): ?>
            <div class="menu-label" style="color: var(--admin-color);">管理員專區</div>
            <a href="admin_reports.php" class="menu-link admin-sidebar-item">
                🚩 檢舉審理
                <?php if($pendingReportsCount > 0): ?>
                    <span class="badge-inline"><?= $pendingReportsCount ?></span>
                <?php endif; ?>
            </a>
        <?php endif; ?>
        
        <div class="menu-label">常用分類看板</div>
        <?php 
        $displayCount = 0;
        foreach ($all_categories as $cat): 
            // 如果是系統公告看板，則在常用分類清單中跳過（因為上面已經獨立顯示了）
            if($cat['name'] == '系統公告') continue;
            if($displayCount >= 8) break;
            $displayCount++;
        ?>
            <a href="index.php?category=<?= $cat['id'] ?>" class="menu-link <?= ($catID == $cat['id']) ? 'active' : '' ?>">
                # <?= htmlspecialchars($cat['name']) ?>
            </a>
        <?php endforeach; ?>
    </aside>

    <main>
        <div class="category-header">
            <h2 style="margin:0;"><?= ($viewFriendsActivity) ? '✨ ' : (($viewHistory) ? '🕒 ' : (($catID == '') ? '🌏 ' : (($currentCatName == '系統公告') ? '📢 ' : '📂 '))) ?><?= htmlspecialchars($currentCatName) ?></h2>
            <p style="margin:10px 0 0 0; color:var(--text-muted);"><?= htmlspecialchars($currentCatDesc) ?></p>
        </div>

        <?php if(!$viewFriendsActivity && !$viewHistory): ?>
        <form action="index.php" method="GET" class="search-box">
            <input type="text" name="search" placeholder="在 <?= htmlspecialchars($currentCatName) ?> 中搜尋..." value="<?= htmlspecialchars($searchTerm) ?>">
            <?php if($catID): ?> <input type="hidden" name="category" value="<?= $catID ?>"> <?php endif; ?>
            <button type="submit">搜尋</button>
        </form>
        <?php endif; ?>

        <?php if ($viewFriendsActivity): ?>
            <?php if (count($activities) > 0): ?>
                <?php foreach ($activities as $act): ?>
                    <div class="activity-card">
                        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:15px;">
                            <div style="display:flex; align-items:center; gap:12px;">
                                <img src="<?= !empty($act['profile_img']) ? "uploads/users_profile_img/".$act['profile_img'] : "uploads/default_avatar.png" ?>" style="width:40px; height:40px; border-radius:50%; object-fit:cover;">
                                <div>
                                    <span style="font-weight:800;"><?= htmlspecialchars($act['username']) ?></span>
                                    <span class="activity-tag tag-<?= $act['type'] ?>"><?= $act['type_cn'] ?></span>
                                </div>
                            </div>
                            <span style="color:var(--text-muted); font-size:0.85rem;"><?= date('Y/m/d H:i', strtotime($act['created_at'])) ?></span>
                        </div>
                        <p style="margin-bottom: 5px; font-weight: 700;">
                            <a href="view_post.php?id=<?= $act['target_id'] ?>" style="text-decoration:none; color:inherit;">
                                <?= htmlspecialchars($act['title']) ?>
                            </a>
                        </p>
                        <p style="color:var(--text-muted); line-height:1.5; margin: 0;"><?= htmlspecialchars(mb_substr(strip_tags($act['content']), 0, 80)) ?>...</p>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="activity-card" style="text-align: center; padding: 50px;">
                    <p style="color:var(--text-muted);">目前還沒有好友的動態資訊。</p>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <?php if (count($posts) > 0): ?>
                <?php foreach ($posts as $post): ?>
                    <article class="post-card">
                        <span style="background:var(--accent-soft); color:var(--accent-color); font-size:0.75rem; font-weight:800; padding:4px 12px; border-radius:50px;"># <?= htmlspecialchars($post['cat_name']) ?></span>
                        <h2 style="margin:12px 0;"><a href="view_post.php?id=<?= $post['id'] ?>" style="text-decoration:none; color:var(--text-color); font-weight:800;"><?= htmlspecialchars($post['title']) ?></a></h2>
                        <div style="display:flex; align-items:center; gap:10px; margin-bottom:15px; font-size:0.9rem;">
                            <img src="<?= !empty($post['profile_img']) ? "uploads/users_profile_img/".$post['profile_img'] : "uploads/default_avatar.png" ?>" style="width:30px; height:30px; border-radius:50%; object-fit:cover;">
                            <span style="font-weight:600;"><?= htmlspecialchars($post['username']) ?></span>
                            <span style="color:var(--text-muted);">
                                • <?= date('Y/m/d', strtotime($post['created_at'])) ?>
                                <?php if (isset($post['viewed_at'])): ?>
                                    <span style="color:var(--accent-color); font-weight: 700;"> (於 <?= date('m/d H:i', strtotime($post['viewed_at'])) ?> 閱讀)</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <p style="color:var(--text-muted); line-height:1.6;"><?= htmlspecialchars(mb_substr(strip_tags($post['content']), 0, 110)) ?>...</p>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="post-card" style="text-align: center; padding: 50px;">
                    <p style="color:var(--text-muted);"><?= $viewHistory ? "目前沒有任何瀏覽紀錄。" : "目前沒有文章。" ?></p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <aside class="right-sidebar">
        <div style="background:var(--card-bg); padding:25px; border-radius:24px; border:1px solid var(--border-color); margin-bottom:20px;">
            <h3 style="margin-top:0; font-size:1.1rem; margin-bottom:15px;">🔍 尋找用戶</h3>
            <form action="search_users.php" method="GET" class="small-search-box" style="display:flex; background:var(--bg-color); border:1px solid var(--border-color); border-radius:12px; padding:4px 4px 4px 12px; margin-bottom:15px;">
                <input type="text" name="u_search" placeholder="輸入用戶名..." required style="border:none; background:transparent; color:var(--text-color); font-size:0.85rem; outline:none; flex:1;">
                <button type="submit" style="background:var(--accent-color); color:white; border:none; padding:6px 12px; border-radius:8px; font-size:0.8rem; cursor:pointer;">搜尋</button>
            </form>
        </div>

        <div style="background:var(--card-bg); padding:25px; border-radius:24px; border:1px solid var(--border-color);">
            <h3 style="margin-top:0; font-size:1.1rem;">🤝 在線好友</h3>
            <div style="display:flex; flex-direction:column; gap:15px;">
                <?php if (isset($_SESSION['user_id']) && count($my_friends) > 0): ?>
                    <?php foreach ($my_friends as $f): ?>
                        <div style="display:flex; align-items:center; justify-content:space-between;">
                            <a href="profile.php?id=<?= $f['id'] ?>" style="display:flex; align-items:center; gap:10px; text-decoration:none; color:inherit;">
                                <img src="<?= !empty($f['profile_img']) ? "uploads/users_profile_img/".$f['profile_img'] : "uploads/default_avatar.png" ?>" style="width:35px; height:35px; border-radius:50%; object-fit:cover;">
                                <span style="font-weight:700; font-size:0.9rem;"><?= htmlspecialchars($f['username']) ?></span>
                            </a>
                            <a href="chat.php?user_id=<?= $f['id'] ?>" style="text-decoration:none;">💬</a>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="font-size:0.85rem; color:var(--text-muted);">目前沒有好友在線</p>
                <?php endif; ?>
            </div>
        </div>
    </aside>
</div>

<div class="modal-overlay" id="categoryModal">
    <div class="modal-content">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h2 style="margin:0;">探索所有看板</h2>
            <span id="closeModal" style="cursor:pointer; font-size:1.5rem;">&times;</span>
        </div>
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
            <?php foreach ($all_categories as $cat): ?>
                <a href="index.php?category=<?= $cat['id'] ?>" style="padding:12px; background:var(--bg-color); border-radius:10px; text-decoration:none; color:var(--text-color); text-align:center; font-weight:600; border:1px solid var(--border-color); position:relative;">
                    <?= ($cat['name'] == '系統公告') ? '📢' : '# ' ?> <?= htmlspecialchars($cat['name']) ?>
                    <?php if($cat['name'] == '系統公告' && $unreadAnnouncementsCount > 0): ?>
                        <span class="badge-inline" style="position:absolute; top:-5px; right:-5px;"><?= $unreadAnnouncementsCount ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
    const themeBtn = document.getElementById('themeBtn');
    const currentTheme = localStorage.getItem('theme') || 'light';
    document.body.setAttribute('data-theme', currentTheme);

    themeBtn.onclick = () => {
        const targetTheme = document.body.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        document.body.setAttribute('data-theme', targetTheme);
        localStorage.setItem('theme', targetTheme);
    };

    const userTrigger = document.getElementById('userTrigger');
    const dropdownMenu = document.getElementById('dropdownMenu');
    
    if(userTrigger && dropdownMenu) {
        userTrigger.onclick = (e) => { 
            e.stopPropagation(); 
            dropdownMenu.classList.toggle('active'); 
        };
        
        document.addEventListener('click', (e) => {
            if (userTrigger && !userTrigger.contains(e.target)) {
                dropdownMenu.classList.remove('active');
            }
        });
    }

    const catModal = document.getElementById('categoryModal');
    const openBtn = document.getElementById('openCategories');
    const closeBtn = document.getElementById('closeModal');
    if(openBtn) openBtn.onclick = () => catModal.style.display = 'flex';
    if(closeBtn) closeBtn.onclick = () => catModal.style.display = 'none';
    window.onclick = (e) => { if (e.target == catModal) catModal.style.display = 'none'; };
</script>
</body>
</html>