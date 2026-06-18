<?php
session_start();
require_once "includes/dbh.inc.php";

$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$catID = isset($_GET['category']) ? $_GET['category'] : '';
$viewFriendsActivity = isset($_GET['view']) && $_GET['view'] === 'friends_activity';
$viewHistory = isset($_GET['view']) && $_GET['view'] === 'history';
$viewHot = isset($_GET['view']) && $_GET['view'] === 'hot';
$viewCategories = isset($_GET['view']) && $_GET['view'] === 'categories';

$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] == 1;

$currentCatName = "最新文章";
$currentCatDesc = "探索社群中的最新動態與深度討論。";

// --- 處理瀏覽紀錄與好友請求操作 ---
$historyMessage = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SESSION['user_id'])) {
    // 清除所有瀏覽紀錄
    if (isset($_POST['clear_history'])) {
        try {
            $delete_stmt = $pdo->prepare("DELETE FROM browsing_history WHERE user_id = ?");
            $delete_stmt->execute([$_SESSION['user_id']]);
            $historyMessage = "已清除所有瀏覽紀錄";
            header("Location: index.php?view=history&msg=cleared");
            exit();
        } catch (PDOException $e) {
            $historyMessage = "清除失敗：" . $e->getMessage();
        }
    }
    
    // 切換瀏覽紀錄追蹤功能
    if (isset($_POST['toggle_tracking'])) {
        try {
            $current_status = $pdo->prepare("SELECT track_browsing_history FROM users WHERE id = ?");
            $current_status->execute([$_SESSION['user_id']]);
            $status_result = $current_status->fetch();
            $currentTrackingStatus = isset($status_result['track_browsing_history']) ? $status_result['track_browsing_history'] : 1;
            $newStatus = $currentTrackingStatus ? 0 : 1;
            
            $update_stmt = $pdo->prepare("UPDATE users SET track_browsing_history = ? WHERE id = ?");
            $update_stmt->execute([$newStatus, $_SESSION['user_id']]);
            $historyMessage = $newStatus ? "已啟用瀏覽紀錄追蹤" : "已禁用瀏覽紀錄追蹤";
            header("Location: index.php?view=history&msg=" . ($newStatus ? "enabled" : "disabled"));
            exit();
        } catch (PDOException $e) {
            $historyMessage = "";
        }
    }

    // ===== 接受好友請求 =====
    if (isset($_POST['accept_friend']) && isset($_POST['friend_row_id'])) {
        try {
            $rowId = (int)$_POST['friend_row_id'];
            $acc_stmt = $pdo->prepare("UPDATE friends SET status = 'accepted' WHERE id = ? AND friend_id = ?");
            $acc_stmt->execute([$rowId, $_SESSION['user_id']]);
            
            $get_req = $pdo->prepare("SELECT user_id FROM friends WHERE id = ?");
            $get_req->execute([$rowId]);
            $req_row = $get_req->fetch();
            
            if ($req_row) {
                $check = $pdo->prepare("SELECT id FROM friends WHERE user_id = ? AND friend_id = ?");
                $check->execute([$_SESSION['user_id'], $req_row['user_id']]);
                if (!$check->fetch()) {
                    $ins = $pdo->prepare("INSERT INTO friends (user_id, friend_id, status) VALUES (?, ?, 'accepted')");
                    $ins->execute([$_SESSION['user_id'], $req_row['user_id']]);
                } else {
                    $upd = $pdo->prepare("UPDATE friends SET status = 'accepted' WHERE user_id = ? AND friend_id = ?");
                    $upd->execute([$_SESSION['user_id'], $req_row['user_id']]);
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
            $rej_stmt->execute([$rowId, $_SESSION['user_id']]);
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit();
        } catch (PDOException $e) { }
    }
}

// --- 初始化計數器 ---
$pendingReportsCount = 0;
$unreadAnnouncementsCount = 0;
$pendingFriendRequestsCount = 0;
$pendingFriendRequests = [];

if (isset($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];
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
        $unread_stmt->execute([$uid]);
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
        $friend_req_stmt->bindValue(':friend_id', (int)$uid, PDO::PARAM_INT);
        $friend_req_stmt->execute();
        $pendingFriendRequests = $friend_req_stmt->fetchAll();
        $pendingFriendRequestsCount = count($pendingFriendRequests);
        
    } catch (PDOException $e) { }
}

try {
    $cat_query = $pdo->query("SELECT * FROM categories ORDER BY id ASC");
    $all_categories = $cat_query->fetchAll();

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

    $recent_categories = [];
    if (isset($_SESSION['user_id'])) {
        try {
            $recent_cat_sql = "
                SELECT DISTINCT c.id, c.name 
                FROM browsing_history bh
                JOIN posts p ON bh.post_id = p.id
                JOIN categories c ON p.category_id = c.id
                WHERE bh.user_id = ?
                ORDER BY bh.viewed_at DESC
                LIMIT 5
            ";
            $recent_cat_stmt = $pdo->prepare($recent_cat_sql);
            $recent_cat_stmt->execute([$_SESSION['user_id']]);
            $recent_categories = $recent_cat_stmt->fetchAll();
        } catch (PDOException $e) { }
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

    if ($viewCategories) {
        $currentCatName = "所有看板";
        $currentCatDesc = "探索社群中的所有看板分類與主題。";
        $posts = [];
        $activities = [];
    } elseif ($viewFriendsActivity && !empty($friend_ids)) {
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
        if ($viewHot) {
            $currentCatName = "熱門文章";
            $currentCatDesc = "大家都在看！社群中按讚討論度最高的熱門文章。";
            
            $sql = "SELECT posts.*, users.username, users.profile_img, categories.name AS cat_name, 
                    (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.id) AS like_count
                    FROM posts 
                    JOIN users ON posts.user_id = users.id 
                    JOIN categories ON posts.category_id = categories.id 
                    WHERE 1=1";
            if ($searchTerm !== '') $sql .= " AND (posts.title LIKE :search OR posts.content LIKE :search)";
            if ($catID !== '') $sql .= " AND posts.category_id = :catID";
            $sql .= " ORDER BY like_count DESC, posts.created_at DESC";
        } else {
            $sql = "SELECT posts.*, users.username, users.profile_img, categories.name AS cat_name,
                    (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.id) AS like_count
                    FROM posts 
                    JOIN users ON posts.user_id = users.id 
                    JOIN categories ON posts.category_id = categories.id 
                    WHERE 1=1";
            if ($searchTerm !== '') $sql .= " AND (posts.title LIKE :search OR posts.content LIKE :search)";
            if ($catID !== '') $sql .= " AND posts.category_id = :catID";
            $sql .= " ORDER BY posts.created_at DESC";
        }
        
        $stmt = $pdo->prepare($sql);
        if ($searchTerm !== '') $stmt->bindValue(':search', '%' . $searchTerm . '%');
        if ($catID !== '') $stmt->bindValue(':catID', $catID);
        $stmt->execute();
        $posts = $stmt->fetchAll();
        $activities = [];
    }

    if ($viewFriendsActivity) {
        $currentCatIcon = "✨ ";
    } elseif ($viewHistory) {
        $currentCatIcon = "🕒 ";
    } elseif ($viewHot) {
        $currentCatIcon = "🔥 ";
    } elseif ($viewCategories) {
        $currentCatIcon = "📂 ";
    } elseif ($catID === '') {
        $currentCatIcon = "🌏 ";
    } else {
        $currentCatIcon = ($currentCatName === '系統公告') ? "📢 " : "📂 ";
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
    <title><?= htmlspecialchars($currentCatName) ?> - Talk Forum</title>
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
            
            /* AI Chat Window Variables */
            --ai-shadow: 0 12px 40px rgba(0, 0, 0, 0.12);
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
            --ai-shadow: 0 12px 40px rgba(0, 0, 0, 0.4);
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

        /* ===== 好友邀請通知區塊樣式 ===== */
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
        .friend-request-item:last-child {
            border-bottom: none;
        }
        .friend-request-item:hover {
            background: var(--sidebar-item-hover);
        }
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
        .friend-btn-accept {
            background: var(--success-color);
            color: white;
        }
        .friend-btn-accept:hover {
            background: #16a34a;
            transform: scale(1.05);
        }
        .friend-btn-reject {
            background: var(--border-color);
            color: var(--text-muted);
        }
        .friend-btn-reject:hover {
            background: var(--danger-color);
            color: white;
            transform: scale(1.05);
        }

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

        .category-list-container {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .category-list-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 18px 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            text-decoration: none;
            color: var(--text-color);
            transition: all 0.2s ease;
        }
        .category-list-card:hover {
            transform: translateY(-2px);
            border-color: var(--accent-color);
            background: var(--accent-soft);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.08);
        }
        .category-card-title {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1.1rem;
            font-weight: 700;
        }
        .category-card-prefix {
            color: var(--accent-color);
            font-weight: 800;
        }
        .category-card-arrow {
            font-size: 1.2rem;
            color: var(--text-muted);
            transition: transform 0.2s;
        }
        .category-list-card:hover .category-card-arrow {
            transform: translateX(4px);
            color: var(--accent-color);
        }

        /* ===== AI 懸浮聊天室樣式 (AI Floating Chat CSS) ===== */
        .ai-chat-trigger {
            position: fixed;
            bottom: 24px;
            right: 24px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--header-gradient);
            color: white;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            box-shadow: 0 4px 18px rgba(99, 102, 241, 0.4);
            z-index: 1500;
            transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275), box-shadow 0.3s;
        }
        .ai-chat-trigger:hover {
            transform: scale(1.1) rotate(5deg);
            box-shadow: 0 6px 24px rgba(99, 102, 241, 0.5);
        }
        .ai-chat-trigger.active {
            transform: scale(0.9) rotate(-45deg);
        }

        .ai-chat-container {
            position: fixed;
            bottom: 96px;
            right: 24px;
            width: 380px;
            height: 520px;
            max-width: calc(100vw - 48px);
            max-height: calc(100vh - 140px);
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            box-shadow: var(--ai-shadow);
            z-index: 1500;
            display: none;
            flex-direction: column;
            overflow: hidden;
            transition: transform 0.3s ease, opacity 0.3s ease;
            transform: translateY(20px) scale(0.95);
            opacity: 0;
        }
        .ai-chat-container.open {
            display: flex;
            transform: translateY(0) scale(1);
            opacity: 1;
        }

        .ai-chat-header {
            padding: 16px 20px;
            background: var(--header-gradient);
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .ai-chat-header-title {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .ai-chat-header-title h4 {
            margin: 0;
            font-size: 1.05rem;
            font-weight: 800;
        }
        .ai-chat-header-status {
            font-size: 0.75rem;
            background: rgba(255, 255, 255, 0.2);
            padding: 3px 8px;
            border-radius: 20px;
            font-weight: 600;
        }

        .ai-chat-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 14px;
            background: var(--bg-color);
        }
        
        .ai-message {
            max-width: 80%;
            padding: 12px 16px;
            border-radius: 16px;
            font-size: 0.9rem;
            line-height: 1.45;
            word-wrap: break-word;
        }
        .ai-message.user {
            background: var(--accent-color);
            color: white;
            align-self: flex-end;
            border-bottom-right-radius: 4px;
        }
        .ai-message.bot {
            background: var(--card-bg);
            color: var(--text-color);
            align-self: flex-start;
            border-bottom-left-radius: 4px;
            border: 1px solid var(--border-color);
        }
        
        /* 推薦問題按鈕樣式 */
        .ai-suggestions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 4px;
        }
        .ai-suggest-btn {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            color: var(--accent-color);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .ai-suggest-btn:hover {
            background: var(--accent-soft);
            border-color: var(--accent-color);
        }

        .ai-chat-input-area {
            padding: 14px 20px;
            background: var(--card-bg);
            border-top: 1px solid var(--border-color);
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .ai-chat-input {
            flex: 1;
            border: 1px solid var(--border-color);
            background: var(--bg-color);
            color: var(--text-color);
            padding: 10px 16px;
            border-radius: 14px;
            font-size: 0.9rem;
            outline: none;
            transition: border-color 0.2s;
        }
        .ai-chat-input:focus {
            border-color: var(--accent-color);
            background: var(--card-bg);
        }
        .ai-chat-send {
            background: var(--accent-color);
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            transition: background-color 0.2s, transform 0.1s;
        }
        .ai-chat-send:hover {
            background: #4f46e5;
        }
        .ai-chat-send:active {
            transform: scale(0.95);
        }

        /* 打字中動畫 (Typing Indicator) */
        .ai-typing-indicator {
            display: flex;
            gap: 4px;
            padding: 4px 8px;
            align-items: center;
        }
        .ai-typing-dot {
            width: 6px;
            height: 6px;
            background: var(--text-muted);
            border-radius: 50%;
            animation: ai-bounce 1.4s infinite ease-in-out both;
        }
        .ai-typing-dot:nth-child(1) { animation-delay: -0.32s; }
        .ai-typing-dot:nth-child(2) { animation-delay: -0.16s; }
        
        @keyframes ai-bounce {
            0%, 80%, 100% { transform: scale(0); }
            40% { transform: scale(1); }
        }

        @media (max-width: 1100px) { 
            .main-wrapper { grid-template-columns: 1fr 300px; } 
            .left-sidebar { display: none; } 
        }
        @media (max-width: 480px) {
            .ai-chat-container {
                right: 12px;
                left: 12px;
                width: auto;
                bottom: 84px;
            }
        }
    </style>
</head>
<body data-theme="light">

<div id="toastContainer"></div>

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
                        $totalNotif = $unreadAnnouncementsCount + ($isAdmin ? $pendingReportsCount : 0) + $pendingFriendRequestsCount;
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
    <aside class="left-sidebar">
        <div class="menu-label">主選單</div>
        <a href="index.php" class="menu-link <?= ($catID == '' && !$viewFriendsActivity && !$viewHistory && !$viewHot && !$viewCategories) ? 'active' : '' ?>">🏠 最新文章</a>
        <a href="index.php?view=hot" class="menu-link <?= $viewHot ? 'active' : '' ?>">🔥 熱門文章</a>
        
        <?php if(isset($_SESSION['user_id'])): ?>
            <a href="index.php?view=friends_activity" class="menu-link <?= $viewFriendsActivity ? 'active' : '' ?>">✨ 好友動態</a>
        <?php endif; ?>
        
        <a href="index.php?view=categories" class="menu-link <?= $viewCategories ? 'active' : '' ?>">📂 所有看板</a>

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
        
        <div class="menu-label">最近瀏覽看板</div>
        <?php if (isset($_SESSION['user_id'])): ?>
            <?php if (count($recent_categories) > 0): ?>
                <?php foreach ($recent_categories as $rcat): ?>
                    <a href="index.php?category=<?= $rcat['id'] ?>" class="menu-link <?= ($catID == $rcat['id']) ? 'active' : '' ?>">
                        # <?= htmlspecialchars($rcat['name']) ?>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="padding: 10px 15px; font-size: 0.85rem; color: var(--text-muted); line-height: 1.4;">尚無瀏覽紀錄。</div>
            <?php endif; ?>
        <?php else: ?>
            <div style="padding: 10px 15px; font-size: 0.85rem; color: var(--text-muted); line-height: 1.4;">登入後顯示您最近瀏覽的看板。</div>
        <?php endif; ?>
    </aside>

    <main>
        <div class="category-header">
            <h2 style="margin:0;"><?= $currentCatIcon ?><?= htmlspecialchars($currentCatName) ?></h2>
            <p style="margin:10px 0 0 0; color:var(--text-muted);"><?= htmlspecialchars($currentCatDesc) ?></p>
        </div>

        <?php if($viewHistory && isset($_SESSION['user_id'])): ?>
            <?php if(isset($_GET['msg'])): ?>
                <div style="background:var(--accent-color); color:white; padding:12px 20px; border-radius:12px; margin-bottom:20px; font-weight:600; display:flex; justify-content:space-between; align-items:center;">
                    <span>
                        <?= $_GET['msg'] === 'cleared' ? '✓ 已清除所有瀏覽紀錄' : ($_GET['msg'] === 'enabled' ? '✓ 已啟用瀏覽紀錄追蹤' : '✓ 已禁用瀏覽紀錄追蹤') ?>
                    </span>
                </div>
            <?php endif; ?>
            <div style="display:flex; gap:10px; margin-bottom:20px; flex-wrap:wrap;">
                <form method="POST" action="" style="display:inline;">
                    <button type="submit" name="clear_history" class="action-btn" style="background:var(--danger-color); color:white; padding:10px 20px; border-radius:12px; font-weight:700; border:none; cursor:pointer; font-size:0.9rem; transition:0.2s;" onclick="return confirm('確定要刪除所有瀏覽紀錄嗎？');">🗑️ 清除所有瀏覽紀錄</button>
                </form>
            </div>
        <?php endif; ?>

        <?php if(!$viewFriendsActivity && !$viewHistory && !$viewCategories): ?>
        <form action="index.php" method="GET" class="search-box">
            <input type="text" name="search" placeholder="在 <?= htmlspecialchars($currentCatName) ?> 中搜尋..." value="<?= htmlspecialchars($searchTerm) ?>">
            <?php if($catID): ?> <input type="hidden" name="category" value="<?= $catID ?>"> <?php endif; ?>
            <?php if($viewHot): ?> <input type="hidden" name="view" value="hot"> <?php endif; ?>
            <button type="submit">搜尋</button>
        </form>
        <?php endif; ?>

        <?php if ($viewCategories): ?>
            <div class="category-list-container">
                <?php foreach ($all_categories as $cat): ?>
                    <a href="index.php?category=<?= $cat['id'] ?>" class="category-list-card">
                        <div class="category-card-title">
                            <span class="category-card-prefix"><?= ($cat['name'] == '系統公告') ? '📢' : '#' ?></span>
                            <span><?= htmlspecialchars($cat['name']) ?></span>
                            <?php if($cat['name'] == '系統公告' && $unreadAnnouncementsCount > 0): ?>
                                <span class="badge-inline" style="margin-left:10px;"><?= $unreadAnnouncementsCount ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="category-card-arrow">➔</div>
                    </a>
                <?php endforeach; ?>
            </div>

        <?php elseif ($viewFriendsActivity): ?>
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
                                <?php if (isset($post['like_count']) && $post['like_count'] > 0): ?>
                                    <span style="color:var(--danger-color); font-weight: 700; margin-left: 5px;">❤️ <?= $post['like_count'] ?> 個讚</span>
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

<!-- ===== AI 智能聊天懸浮按鈕 & 視窗 ===== -->
<button class="ai-chat-trigger" id="aiChatTrigger" title="問問論壇 AI 助手">
    🤖
</button>

<div class="ai-chat-container" id="aiChatContainer">
    <div class="ai-chat-header">
        <div class="ai-chat-header-title">
            <span>🤖</span>
            <div>
                <h4>論壇 AI 智能助手</h4>
            </div>
        </div>
        <span class="ai-chat-header-status">線上常駐</span>
    </div>
    
    <div class="ai-chat-messages" id="aiChatMessages">
        <div class="ai-message bot">
            你好！我是本論壇的 AI 智能助手。你可以問我關於這個社群的功能介紹、如何發貼、加好友或是任何使用上的疑惑喔！✨
            <div class="ai-suggestions" style="margin-top: 10px;">
                <button class="ai-suggest-btn" onclick="sendSuggestedQuestion('如何發表新文章？')">✍️ 如何發表文章</button>
                <button class="ai-suggest-btn" onclick="sendSuggestedQuestion('怎麼跟別的用戶加好友？')">🤝 如何加好友</button>
                <button class="ai-suggest-btn" onclick="sendSuggestedQuestion('系統公告有什麼作用？')">📢 系統公告作用</button>
                <button class="ai-suggest-btn" onclick="sendSuggestedQuestion('如何切換網站主題？')">🌓 怎麼切換黑夜模式</button>
            </div>
        </div>
    </div>
    
    <div class="ai-chat-input-area">
        <input type="text" class="ai-chat-input" id="aiChatInput" placeholder="輸入你想詢問的問題..." onkeypress="handleAiKeyPress(event)">
        <button class="ai-chat-send" id="aiChatSendBtn" onclick="sendAiMessage()">➔</button>
    </div>
</div>

<script>
    // Theme switching control
    const themeBtn = document.getElementById('themeBtn');
    const currentTheme = localStorage.getItem('theme') || 'light';
    document.body.setAttribute('data-theme', currentTheme);

    themeBtn.onclick = () => {
        const targetTheme = document.body.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        document.body.setAttribute('data-theme', targetTheme);
        localStorage.setItem('theme', targetTheme);
    };

    // User trigger menu activation
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

    // ===== AI 聊天室互動 JS 控制 (AI Chatbox Frontend Logic) =====
    const aiChatTrigger = document.getElementById('aiChatTrigger');
    const aiChatContainer = document.getElementById('aiChatContainer');
    const aiChatMessages = document.getElementById('aiChatMessages');
    const aiChatInput = document.getElementById('aiChatInput');

    // 開關聊天室
    aiChatTrigger.addEventListener('click', () => {
        aiChatTrigger.classList.toggle('active');
        aiChatContainer.classList.toggle('open');
        if (aiChatContainer.classList.contains('open')) {
            aiChatMessages.scrollTop = aiChatMessages.scrollHeight;
            aiChatInput.focus();
        }
    });

    // 處理輸入框 Enter 鍵
    function handleAiKeyPress(event) {
        if (event.key === 'Enter') {
            sendAiMessage();
        }
    }

    // 點擊建議問題
    function sendSuggestedQuestion(question) {
        aiChatInput.value = question;
        sendAiMessage();
    }

    // 發送訊息給 AI
    async function sendAiMessage() {
        const text = aiChatInput.value.trim();
        if (!text) return;

        // 1. 顯示使用者的訊息
        appendMessage(text, 'user');
        aiChatInput.value = '';

        // 2. 顯示 AI 正在打字的動畫
        const typingId = appendTypingIndicator();
        aiChatMessages.scrollTop = aiChatMessages.scrollHeight;

        try {
            // 3. 發送請求至後端 PHP
            const response = await fetch('api_ai_chat.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ message: text })
            });

            const data = await response.json();
            
            // 移除打字指示器
            removeTypingIndicator(typingId);

            if (data && data.reply) {
                // 4. 逐字打字效果 (Typing Effect)
                typeOutMessage(data.reply);
            } else {
                appendMessage("抱歉，我現在有點頭痛，請稍後再試一次！💨", 'bot');
            }
        } catch (error) {
            removeTypingIndicator(typingId);
            appendMessage("連線失敗，請檢查網路狀態或稍後再試。", 'bot');
        }
    }

    // 新增訊息至對話視窗
    function appendMessage(text, sender) {
        const msgDiv = document.createElement('div');
        msgDiv.className = `ai-message ${sender}`;
        msgDiv.innerText = text;
        aiChatMessages.appendChild(msgDiv);
        aiChatMessages.scrollTop = aiChatMessages.scrollHeight;
        return msgDiv;
    }

    // 新增打字動畫
    function appendTypingIndicator() {
        const indicatorId = 'typing_' + Date.now();
        const msgDiv = document.createElement('div');
        msgDiv.className = 'ai-message bot';
        msgDiv.id = indicatorId;
        msgDiv.innerHTML = `
            <div class="ai-typing-indicator">
                <div class="ai-typing-dot"></div>
                <div class="ai-typing-dot"></div>
                <div class="ai-typing-dot"></div>
            </div>
        `;
        aiChatMessages.appendChild(msgDiv);
        return indicatorId;
    }

    // 移除打字動畫
    function removeTypingIndicator(id) {
        const indicator = document.getElementById(id);
        if (indicator) {
            indicator.remove();
        }
    }

    // 模擬 AI 打字效果 (Character by Character Typing)
    function typeOutMessage(fullText) {
        const msgDiv = document.createElement('div');
        msgDiv.className = 'ai-message bot';
        aiChatMessages.appendChild(msgDiv);

        let index = 0;
        const interval = setInterval(() => {
            if (index < fullText.length) {
                msgDiv.innerHTML += fullText.charAt(index);
                index++;
                aiChatMessages.scrollTop = aiChatMessages.scrollHeight;
            } else {
                clearInterval(interval);
            }
        }, 15); // 打字速度：每 15 毫秒一個字
    }
</script>
</body>
</html>