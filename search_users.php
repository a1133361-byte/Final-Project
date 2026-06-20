<?php
session_start();
require_once "includes/dbh.inc.php";

$u_search = isset($_GET['u_search']) ? trim($_GET['u_search']) : '';
$current_user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] == 1;

// 未登入使用者不能使用搜尋功能
$loginRequired = false;
if ($u_search !== '' && !$current_user_id) {
    $loginRequired = true;
    $u_search = '';
}

// --- 0. 處理好友請求操作 (從頂部下拉選單觸發) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $current_user_id) {
    // ===== 接受好友請求 =====
    if (isset($_POST['accept_friend']) && isset($_POST['friend_row_id'])) {
        try {
            $rowId = (int)$_POST['friend_row_id'];
            $acc_stmt = $pdo->prepare("UPDATE friends SET status = 'accepted' WHERE id = ? AND friend_id = ?");
            $acc_stmt->execute([$rowId, $current_user_id]);
            
            $get_req = $pdo->prepare("SELECT user_id FROM friends WHERE id = ?");
            $get_req->execute([$rowId]);
            $req_row = $get_req->fetch();
            
            if ($req_row) {
                $check = $pdo->prepare("SELECT id FROM friends WHERE user_id = ? AND friend_id = ?");
                $check->execute([$current_user_id, $req_row['user_id']]);
                if (!$check->fetch()) {
                    $ins = $pdo->prepare("INSERT INTO friends (user_id, friend_id, status) VALUES (?, ?, 'accepted')");
                    $ins->execute([$current_user_id, $req_row['user_id']]);
                } else {
                    $upd = $pdo->prepare("UPDATE friends SET status = 'accepted' WHERE user_id = ? AND friend_id = ?");
                    $upd->execute([$current_user_id, $req_row['user_id']]);
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
            $rej_stmt->execute([$rowId, $current_user_id]);
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit();
        } catch (PDOException $e) { }
    }
}

// --- 1. 獲取通知與計數器 (供導覽列使用) ---
$pendingReportsCount = 0;
$unreadAnnouncementsCount = 0;
$pendingFriendRequestsCount = 0;
$pendingFriendRequests = [];
$unreadChatsCount = 0;

if ($current_user_id) {
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
        $unread_stmt->execute([$current_user_id]);
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
        $friend_req_stmt->bindValue(':friend_id', (int)$current_user_id, PDO::PARAM_INT);
        $friend_req_stmt->execute();
        $pendingFriendRequests = $friend_req_stmt->fetchAll();
        $pendingFriendRequestsCount = count($pendingFriendRequests);
        
        // 讀取總未讀私訊數
        try {
            $chat_sql = "SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0";
            $chat_stmt = $pdo->prepare($chat_sql);
            $chat_stmt->execute([$current_user_id]);
            $unreadChatsCount = (int)$chat_stmt->fetchColumn();
        } catch (PDOException $e) {
            try {
                $chat_sql = "SELECT COUNT(*) FROM chat_messages WHERE receiver_id = ? AND is_read = 0";
                $chat_stmt = $pdo->prepare($chat_sql);
                $chat_stmt->execute([$current_user_id]);
                $unreadChatsCount = (int)$chat_stmt->fetchColumn();
            } catch (PDOException $ex) {
                $unreadChatsCount = 0; 
            }
        }
    } catch (PDOException $e) { }
}

// --- 2. 搜尋用戶，並取得與當前用戶的好友狀態 ---
$users_results = [];

try {
    if ($u_search !== '') {
        // 先獲取符合搜尋條件的用戶名單（包含自己，方便使用者搜尋到自己的資料）
        $sql = "SELECT id, username, profile_img, bio FROM users WHERE username LIKE :search LIMIT 50";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':search', '%' . $u_search . '%');
        $stmt->execute();
        $raw_results = $stmt->fetchAll();

        // 針對每位搜尋出來的用戶，查詢是否已經為好友關係
        foreach ($raw_results as $user) {
            $is_friend = false;
            if ($current_user_id) {
                $f_check = $pdo->prepare("SELECT status FROM friends WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)");
                $f_check->execute([$current_user_id, $user['id'], $user['id'], $current_user_id]);
                $relation = $f_check->fetch();
                if ($relation && $relation['status'] === 'accepted') {
                    $is_friend = true;
                }
            }
            $user['is_friend'] = $is_friend;
            $users_results[] = $user;
        }
    }
} catch (PDOException $e) {
    die("搜尋失敗: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>搜尋用戶: <?= htmlspecialchars($u_search) ?> - Talk Forum</title>
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

        .main-content { max-width: 900px; margin: 40px auto; padding: 0 20px; }

        .search-header { margin-bottom: 30px; }
        .search-header h2 { font-size: 1.8rem; font-weight: 800; margin-bottom: 10px; }

        /* 用戶卡片設計 */
        .user-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; }
        .user-card { 
            background: var(--card-bg); 
            border-radius: 24px; 
            padding: 25px; 
            border: 1px solid var(--border-color); 
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            text-align: center;
            transition: 0.3s;
        }
        .user-card:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(0,0,0,0.08); border-color: var(--accent-color); }

        .user-avatar { 
            width: 90px; height: 90px; border-radius: 50%; object-fit: cover; 
            margin-bottom: 15px; border: 4px solid var(--bg-color); box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        .user-info h3 { margin: 0; font-size: 1.2rem; font-weight: 800; }
        .user-info p { color: var(--text-muted); font-size: 0.9rem; margin: 10px 0 20px 0; line-height: 1.5; height: 3em; overflow: hidden; }

        .action-btns { display: flex; gap: 10px; width: 100%; }
        .btn { 
            flex: 1; padding: 10px; border-radius: 12px; font-weight: 700; font-size: 0.85rem; 
            text-decoration: none; text-align: center; cursor: pointer; transition: 0.2s; border: none;
        }
        .btn-primary { background: var(--accent-color); color: white; }
        .btn-primary:hover { opacity: 0.9; transform: scale(1.02); }
        .btn-secondary { background: var(--sidebar-item-hover); color: var(--text-color); }
        .btn-secondary:hover { background: var(--border-color); }
        
        /* 刪除好友專用紅色按鈕樣式 */
        .btn-danger { background: var(--danger-color); color: white; }
        .btn-danger:hover { opacity: 0.9; transform: scale(1.02); }

        .empty-state { text-align: center; padding: 60px 20px; background: var(--card-bg); border-radius: 30px; border: 1px dashed var(--border-color); }
        .empty-state h3 { font-size: 1.5rem; color: var(--text-muted); }

        .back-link { display: inline-flex; align-items: center; gap: 8px; color: var(--accent-color); text-decoration: none; font-weight: 700; margin-bottom: 20px; }
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

<main class="main-content">
    <a href="index.php" class="back-link">⬅ 返回探索牆</a>

    <div class="search-header">
        <h2>🔍 搜尋用戶結果</h2>
        <?php if ($loginRequired): ?>
            <p style="color: var(--text-muted);">目前未顯示搜尋結果。</p>
        <?php else: ?>
            <p style="color: var(--text-muted);">正在搜尋關於 「<strong><?= htmlspecialchars($u_search) ?></strong>」 的結果...</p>
        <?php endif; ?>
    </div>

    <?php if ($loginRequired): ?>
        <div class="empty-state">
            <h3>🔒 請先登入才能使用搜尋功能</h3>
            <p style="color: var(--text-muted);">登入後即可搜尋論壇上的所有用戶，包括你自己的資料。</p>
            <a href="login.php" style="display:inline-block; margin-top:20px; background:var(--accent-color); color:white; text-decoration:none; padding:10px 25px; border-radius:12px; font-weight:700;">前往登入</a>
        </div>
    <?php elseif (count($users_results) > 0): ?>
        <div class="user-grid">
            <?php foreach ($users_results as $user): ?>
                <div class="user-card">
                    <img src="<?= !empty($user['profile_img']) ? "uploads/users_profile_img/".$user['profile_img'] : "uploads/default_avatar.png" ?>" class="user-avatar">
                    <div class="user-info">
                        <h3><?= htmlspecialchars($user['username']) ?></h3>
                        <p><?= !empty($user['bio']) ? htmlspecialchars(mb_substr($user['bio'], 0, 45)) . '...' : '這傢伙很懶，什麼都沒寫。' ?></p>
                    </div>
                    <div class="action-btns">
                        <a href="profile.php?id=<?= $user['id'] ?>" class="btn btn-secondary">查看資料</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <h3>🛸 哎呀！找不到該用戶</h3>
            <p style="color: var(--text-muted);">試試搜尋其他名字，或者檢查一下拼字是否正確。</p>
            <form action="search_users.php" method="GET" style="margin-top:20px; display:flex; justify-content:center; gap:10px;">
                <input type="text" name="u_search" placeholder="再次搜尋..." style="padding:12px 20px; border-radius:12px; border:1px solid var(--border-color); width:250px; outline:none;" required>
                <button type="submit" style="background:var(--accent-color); color:white; border:none; padding:10px 25px; border-radius:12px; font-weight:700; cursor:pointer;">搜尋</button>
            </form>
        </div>
    <?php endif; ?>
</main>

<script>
    // 主題切換邏輯
    const themeBtn = document.getElementById('themeBtn');
    const currentTheme = localStorage.getItem('theme') || 'light';
    document.body.setAttribute('data-theme', currentTheme);

    themeBtn.onclick = () => {
        const targetTheme = document.body.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        document.body.setAttribute('data-theme', targetTheme);
        localStorage.setItem('theme', targetTheme);
    };

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
                dropdownMenu.classList.remove('active');
            }
        });
    }
</script>
</body>
</html>