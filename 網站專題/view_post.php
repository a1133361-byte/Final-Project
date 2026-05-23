<?php
session_start();
require_once "includes/dbh.inc.php";

if (!isset($_GET["id"]) || empty($_GET["id"])) {
    header("Location: index.php");
    exit();
}

$post_id = $_GET["id"];
$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] == 1;

try {
    // 1. 取得文章主體與作者資訊
    $sql = "SELECT posts.*, users.username, users.profile_img, categories.name AS cat_name
            FROM posts
            JOIN users ON posts.user_id = users.id
            JOIN categories ON posts.category_id = categories.id
            WHERE posts.id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$post_id]);
    $post = $stmt->fetch();

    if (!$post) {
        die("這篇文章不存在！");
    }

    // --- 核心功能：紀錄瀏覽行為 ---
    if (isset($_SESSION['user_id'])) {
        try {
            $history_sql = "INSERT INTO browsing_history (user_id, post_id) VALUES (?, ?) 
                            ON DUPLICATE KEY UPDATE viewed_at = CURRENT_TIMESTAMP";
            $history_stmt = $pdo->prepare($history_sql);
            $history_stmt->execute([$_SESSION['user_id'], $post_id]);
        } catch (PDOException $e) {
            // 靜默錯誤：若資料表尚未建立或發生意外，不影響使用者正常看文章
        }
    }

    // --- 【取得該文章的所有圖片】 ---
    $img_sql = "SELECT image_path FROM post_images WHERE post_id = ? ORDER BY id ASC";
    $img_stmt = $pdo->prepare($img_sql);
    $img_stmt->execute([$post_id]);
    $post_images = $img_stmt->fetchAll(PDO::FETCH_COLUMN);

    // 2. 按讚統計
    $like_sql = "SELECT COUNT(*) FROM likes WHERE post_id = ?";
    $like_stmt = $pdo->prepare($like_sql);
    $like_stmt->execute([$post_id]);
    $like_count = $like_stmt->fetchColumn();

    $user_liked = false;
    if (isset($_SESSION['user_id'])) {
        $check_like = "SELECT * FROM likes WHERE user_id = ? AND post_id = ?";
        $check_stmt = $pdo->prepare($check_like);
        $check_stmt->execute([$_SESSION['user_id'], $post_id]);
        if ($check_stmt->rowCount() > 0) {
            $user_liked = true;
        }
    }
} catch (PDOException $e) {
    die("讀取失敗: " . $e->getMessage());
}

// --- 【核心功能：處理標籤替換邏輯】 ---
function renderPostContent($content, $images) {
    $content = trim($content);
    $safe_content = htmlspecialchars($content);
    
    $rendered = preg_replace_callback('/\[img(\d+)\]/i', function($matches) use ($images) {
        $index = intval($matches[1]) - 1;
        if (isset($images[$index])) {
            $url = "uploads/post_imgs/" . $images[$index];
            return '</div><div class="content-image-wrapper"><img src="'.$url.'" class="post-inline-img"></div><div class="content-text">';
        }
        return ''; 
    }, $safe_content);

    return '<div class="content-text">' . nl2br($rendered) . '</div>';
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($post['title']) ?> - PHP Forum</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* 直接採用 index.php 的變數設定 */
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
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background-color: var(--bg-color);
            margin: 0;
            color: var(--text-color);
            transition: background-color 0.3s, color 0.3s;
            line-height: 1.6;
        }

        /* --- Header: 與 index.php 完美同步 --- */
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
        .logo { text-decoration: none; }

        .user-trigger { 
            display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 5px 12px; border-radius: 50px; transition: 0.2s; 
        }
        .user-trigger:hover { background: var(--sidebar-item-hover); }
        .user-trigger span { font-weight: 700; font-size: 0.95rem; }

        /* Dropdown Menu 與 index.php 一致 */
        .dropdown-menu { 
            position: absolute; right: 0; top: 125%; width: 240px; 
            background: var(--card-bg); border: 1px solid var(--border-color); 
            border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.15); 
            display: none; flex-direction: column; overflow: hidden; z-index: 1100;
        }
        .dropdown-menu.active { display: flex; }
        .dropdown-menu a { 
            padding: 12px 20px; text-decoration: none; color: var(--text-color); 
            font-weight: 600; font-size: 0.9rem; transition: 0.2s; 
            border-bottom: 1px solid var(--border-color);
        }
        .dropdown-menu a:hover { background: var(--sidebar-item-hover); color: var(--accent-color); }
        .admin-link { color: var(--admin-color) !important; background: var(--admin-soft); }

        /* --- Main Content Layout --- */
        .main-wrapper { max-width: 800px; margin: 30px auto; padding: 0 20px; }

        .post-article {
            background: var(--card-bg);
            padding: 40px;
            border-radius: 24px;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 6px rgba(0,0,0,0.02);
            transition: 0.3s;
        }

        .category-tag { 
            background: var(--accent-soft); 
            color: var(--accent-color); 
            font-size: 0.75rem; 
            font-weight: 800; 
            padding: 5px 15px; 
            border-radius: 50px; 
            display: inline-block;
            margin-bottom: 15px;
        }

        h1.post-title { font-size: 2.2rem; font-weight: 800; margin: 0 0 25px 0; color: var(--text-color); line-height: 1.3; }

        .post-author-box {
            display: flex; align-items: center; gap: 12px; margin-bottom: 30px;
            padding-bottom: 20px; border-bottom: 1px solid var(--border-color);
        }
        .author-avatar { width: 45px; height: 45px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border-color); }
        .meta-info { display: flex; flex-direction: column; }
        .author-name { font-weight: 700; color: var(--text-color); text-decoration: none; font-size: 1rem; }
        .post-date { font-size: 0.85rem; color: var(--text-muted); font-weight: 500; }

        /* 內容區塊 */
        .content-text { font-size: 1.15rem; color: var(--text-color); line-height: 1.8; word-break: break-word; }
        .content-image-wrapper { margin: 30px 0; text-align: center; }
        .post-inline-img { max-width: 100%; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }

        /* 底部按鈕區 */
        .post-footer-actions {
            margin-top: 40px; padding-top: 25px; border-top: 1px solid var(--border-color);
            display: flex; justify-content: space-between; align-items: center;
        }
        .like-btn { 
            cursor: pointer; display: inline-flex; align-items: center; gap: 10px; 
            background: var(--sidebar-item-hover); padding: 8px 18px; border-radius: 50px;
            transition: 0.2s; font-weight: 700;
        }
        .like-btn:hover { background: var(--accent-soft); }
        .like-count { color: var(--accent-color); }
        .report-link { color: var(--text-muted); text-decoration: none; font-size: 0.9rem; font-weight: 600; cursor: pointer; }
        .report-link:hover { color: #ef4444; }

        /* --- STREAMING_CHUNK:Styling layout floor comments and threaded nesting... --- */
        /* 留言區塊與樓層回覆樣式 */
        .comment-section { margin-top: 30px; background: var(--card-bg); padding: 30px; border-radius: 24px; border: 1px solid var(--border-color); }
        .comment-item { display: flex; gap: 15px; border-bottom: 1px solid var(--border-color); padding: 20px 0; position: relative; flex-direction: row; }
        .comment-item:last-child { border-bottom: none; }
        .comment-avatar { width: 38px; height: 38px; border-radius: 50%; object-fit: cover; }
        .comment-user { font-weight: 700; color: var(--text-color); text-decoration: none; font-size: 0.95rem; }
        .comment-date { font-size: 0.8rem; color: var(--text-muted); font-weight: 500; }
        .comment-content { margin-top: 6px; font-size: 1rem; color: var(--text-color); opacity: 0.9; word-break: break-all; }
        
        /* 樓層專屬徽章 */
        .comment-floor-badge {
            background: var(--accent-soft);
            color: var(--accent-color);
            font-size: 0.75rem;
            font-weight: 800;
            padding: 2px 8px;
            border-radius: 6px;
            margin-right: 6px;
            display: inline-block;
        }
        
        /* 回覆按鈕樣式 */
        .comment-reply-trigger {
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 0.8rem;
            font-weight: 700;
            cursor: pointer;
            padding: 4px 10px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.2s;
        }
        .comment-reply-trigger:hover {
            color: var(--accent-color);
            background: var(--accent-soft);
        }

        .comment-form textarea { 
            width: 100%; padding: 15px; border: 2px solid var(--border-color); border-radius: 16px; 
            background: var(--bg-color); color: var(--text-color); margin-top: 15px; 
            box-sizing: border-box; resize: vertical; outline: none; transition: 0.2s; font-family: inherit;
        }
        .comment-form textarea:focus { border-color: var(--accent-color); }
        .btn-submit { background: var(--accent-color); color: white; border: none; padding: 10px 25px; border-radius: 12px; cursor: pointer; margin-top: 10px; font-weight: 700; font-size: 0.95rem; transition: 0.2s; }
        .btn-submit:hover { opacity: 0.9; transform: translateY(-1px); }

        /* 巢狀回覆（縮排與提示線條）樣式 */
        .comment-item.nested-reply {
            margin-left: 36px;
            padding-left: 20px;
            border-left: 2px solid var(--border-color);
            border-bottom: none;
            padding-top: 15px;
            padding-bottom: 5px;
            background: transparent;
        }

        /* 針對窄螢幕/手機版自動縮小縮排，防止版面擠壓破圖 */
        @media (max-width: 640px) {
            .comment-item.nested-reply {
                margin-left: 16px;
                padding-left: 12px;
            }
        }

        /* 管理員樣式小盒 */
        .post-management {
            margin-top: 20px; padding: 15px 20px; background: var(--admin-soft);
            border-radius: 14px; border: 1px solid var(--admin-color); font-size: 0.9rem;
            display: flex; align-items: center; gap: 15px;
        }

        /* Modal: 已移除模糊效果，確保輸入時極度順暢 */
        #reportModal {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            display: none; justify-content: center; align-items: center; z-index: 2000;
        }
        .modal-overlay {
            position: absolute; top:0; left:0; width:100%; height:100%;
            background: rgba(0,0,0,0.6); /* 使用純半透明黑色背景 */
            z-index: -1; transition: opacity 0.3s;
        }
        .modal-content {
            background: var(--card-bg); padding: 30px; border-radius: 25px;
            width: 95%; max-width: 450px; border: 1px solid var(--border-color);
            box-shadow: 0 20px 50px rgba(0,0,0,0.3);
            transform: scale(0.95); transition: transform 0.2s ease-out;
        }
        #reportModal.active { display: flex; }
        #reportModal.active .modal-content { transform: scale(1); }

        /* Toast: 優美的提示通知 */
        #toastContainer {
            position: fixed; top: 20px; left: 50%; transform: translateX(-50%);
            z-index: 9999; display: flex; flex-direction: column; gap: 10px; pointer-events: none;
        }
        .toast {
            background: #10b981; color: white; padding: 12px 25px; border-radius: 50px;
            font-weight: 700; box-shadow: 0 10px 20px rgba(16, 185, 129, 0.3);
            display: flex; align-items: center; gap: 10px;
            transform: translateY(-50px); opacity: 0; transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .toast.show { transform: translateY(0); opacity: 1; }
        .toast.error { background: #ef4444; box-shadow: 0 10px 20px rgba(239, 68, 68, 0.3); }
    </style>
</head>
<body>

<!-- 用於顯示優雅通知的容器 -->
<div id="toastContainer"></div>

<header>
    <div class="nav-container">
        <a href="index.php" class="logo"><h1>🚀 PHP Forum</h1></a>
        <div style="display:flex; align-items:center; gap:15px;">
            <button id="themeBtn" title="切換主題" style="background:none; border:none; cursor:pointer; font-size:1.3rem; padding:5px; border-radius:50%;">🌓</button>
            
            <?php if (isset($_SESSION["user_id"])): ?>
                <div style="position:relative;">
                    <div class="user-trigger" id="userTrigger">
                        <img src="<?= !empty($_SESSION['profile_img']) ? "uploads/users_profile_img/".$_SESSION['profile_img'] : "uploads/default_avatar.png" ?>" class="author-avatar" style="width:32px; height:32px;">
                        <span style="<?= $isAdmin ? 'color: var(--admin-color);' : '' ?>"><?= htmlspecialchars($_SESSION["username"]) ?></span>
                    </div>
                    <!-- 新增「歷史瀏覽紀錄」至 Dropdown-menu 以配合 index.php -->
                    <div class="dropdown-menu" id="dropdownMenu">
                        <div style="padding: 10px 20px; font-size: 0.7rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase;">使用者功能</div>
                        <a href="profile.php?id=<?= $_SESSION['user_id'] ?>">👤 我的個人資料</a>
                        <a href="index.php?view=history">🕒 歷史瀏覽紀錄</a>
                        <a href="create_post.php">✍️ 撰寫新文章</a>
                        
                        <?php if ($isAdmin): ?>
                            <div style="padding: 10px 20px; font-size: 0.7rem; color: var(--admin-color); font-weight: 800; text-transform: uppercase; background: var(--admin-soft);">管理員功能</div>
                            <a href="admin_categories.php" class="admin-link">🛠️ 看板管理</a>
                            <a href="admin_dashboard.php" class="admin-link">📊 後台數據</a>
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
    <article class="post-article">
        <span class="category-tag"># <?= htmlspecialchars($post['cat_name']) ?></span>
        <h1 class="post-title"><?= htmlspecialchars($post['title']) ?></h1>

        <div class="post-author-box">
            <?php $author_img = !empty($post['profile_img']) ? "uploads/users_profile_img/".$post['profile_img'] : "uploads/default_avatar.png"; ?>
            <img src="<?= $author_img ?>" class="author-avatar">
            <div class="meta-info">
                <a href="profile.php?id=<?= $post['user_id'] ?>" class="author-name"><?= htmlspecialchars($post['username']) ?></a>
                <span class="post-date"><?= date('Y/m/d H:i', strtotime($post['created_at'])) ?></span>
            </div>
        </div>

        <div class="post-content-body">
            <?= renderPostContent($post['content'], $post_images) ?>
        </div>

        <div class="post-footer-actions">
            <div class="like-section">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <div id="like-btn" class="like-btn">
                        <span id="like-icon"><?= $user_liked ? '❤️' : '🤍' ?></span>
                        <span id="like-count" class="like-count"><?= $like_count ?></span>
                    </div>
                <?php else: ?>
                    <div style="color: var(--text-muted); font-size: 1rem; font-weight: 700;">
                        🤍 <?= $like_count ?> <small style="font-weight: 500;">(登入後按讚)</small>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (isset($_SESSION['user_id'])): ?>
                <span class="report-link" onclick="openReport()">🚩 檢舉文章</span>
            <?php endif; ?>
        </div>

        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $post['user_id']): ?>
            <div class="post-management">
                <span style="color: var(--admin-color); font-weight: 800;">🛠️ 管理：</span>
                <a href="edit_post.php?id=<?= $post['id'] ?>" style="color: var(--accent-color); text-decoration: none; font-weight: 700;">編輯文章</a>
                <a href="includes/delete_post.inc.php?id=<?= $post['id'] ?>" onclick="return confirm('確定要刪除嗎？')" style="color: #ef4444; text-decoration: none; font-weight: 700;">刪除文章</a>
            </div>
        <?php endif; ?>
    </article>

    <section class="comment-section">
        <h3 style="margin-top: 0; font-weight: 800;">💬 留言討論</h3>
        
        <?php if (isset($_SESSION["user_id"])): ?>
            <!-- 原有底部的通用留言輸入框：維持原樣不做任何功能與 ID 破壞 -->
            <div class="comment-form">
                <form action="includes/comment.inc.php" method="POST">
                    <input type="hidden" name="post_id" value="<?= $post_id ?>">
                    <textarea id="commentTextarea" name="content" rows="3" placeholder="分享您的看法..." required></textarea>
                    <div style="text-align: right;">
                        <button type="submit" name="submit_comment" class="btn-submit">發表留言</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <!-- --- STREAMING_CHUNK:Rendering list comments with replies placeholders... --- -->
        <div style="margin-top: 20px;" id="commentsContainer">
            <?php
            $c_sql = "SELECT comments.*, users.username, users.id AS comment_user_id, users.profile_img AS comment_avatar
                      FROM comments
                      JOIN users ON comments.user_id = users.id
                      WHERE post_id = ?
                      ORDER BY created_at ASC";
            $c_stmt = $pdo->prepare($c_sql);
            $c_stmt->execute([$post_id]);
            $comments = $c_stmt->fetchAll();

            foreach ($comments as $index => $c): 
                $floor = $index + 1; 
            ?>
                <!-- 留言最外層：加入 data-floor 屬性利於動態 DOM 分發 -->
                <div class="comment-item" id="comment-floor-<?= $floor ?>" data-floor="<?= $floor ?>">
                    <?php $c_img = !empty($c['comment_avatar']) ? "uploads/users_profile_img/".$c['comment_avatar'] : "uploads/default_avatar.png"; ?>
                    <img src="<?= $c_img ?>" class="comment-avatar">
                    <div style="flex: 1; display: flex; flex-direction: column;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <span class="comment-floor-badge">B<?= $floor ?></span>
                                <a href="profile.php?id=<?= $c['comment_user_id'] ?>" class="comment-user"><?= htmlspecialchars($c['username']) ?></a>
                            </div>
                            <span class="comment-date"><?= date('m/d H:i', strtotime($c['created_at'])) ?></span>
                        </div>
                        <div class="comment-content"><?= nl2br(htmlspecialchars($c['content'])) ?></div>
                        
                        <?php if (isset($_SESSION["user_id"])): ?>
                            <div style="text-align: right; margin-top: 5px;">
                                <button type="button" class="comment-reply-trigger" onclick="replyToFloor(<?= $floor ?>, '<?= htmlspecialchars(addslashes($c['username'])) ?>')">
                                    💬 回覆
                                </button>
                            </div>
                        <?php endif; ?>

                        <!-- 專為本樓設計的行內回覆輸入框容器（預設隱藏） -->
                        <div class="inline-reply-form-container" style="display: none; margin-top: 15px; width: 100%;"></div>

                        <!-- 專為本樓設計的子留言（縮排回覆）存放區 -->
                        <div class="replies-container" style="margin-top: 10px; width: 100%; display: flex; flex-direction: column; gap: 12px;"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
</div>

<!-- 檢舉 Modal -->
<div id="reportModal">
    <div class="modal-overlay" onclick="closeReport()"></div>
    <div class="modal-content">
        <h3 style="margin-top:0; font-weight:800;">🚩 檢舉此文章</h3>
        <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom:10px;">請敘述檢舉理由：</p>
        <textarea id="reportReason" style="width:100%; height:120px; border-radius:12px; border:2px solid var(--border-color); background:var(--bg-color); color:var(--text-color); padding:15px; box-sizing:border-box; outline:none; font-family:inherit;" placeholder="例如：內容包含不當言言..."></textarea>
        <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:20px;">
            <button style="background:var(--sidebar-item-hover); color:var(--text-color); border:none; padding:10px 20px; border-radius:10px; cursor:pointer; font-weight:700;" onclick="closeReport()">取消</button>
            <button id="submitReportBtn" style="background:#ef4444; color:white; border:none; padding:10px 20px; border-radius:10px; cursor:pointer; font-weight:700;" onclick="submitReport()">送出檢舉</button>
        </div>
    </div>
</div>

<!-- --- STREAMING_CHUNK:Configuring JavaScript nested routing and dynamic forms... --- -->
<script>
    /**
     * 點擊樓層回覆觸發的互動邏輯 - 改為行內彈出輸入框，不再強行拖拽頁面到最底部
     * @param {number} floor - 樓層編號
     * @param {string} username - 該樓層的作者名字
     */
    function replyToFloor(floor, username) {
        // 先關閉其他可能正開啟的行內回覆框，保持乾淨
        document.querySelectorAll('.inline-reply-form-container').forEach(container => {
            container.style.display = 'none';
            container.innerHTML = '';
        });

        const targetComment = document.getElementById(`comment-floor-${floor}`);
        if (!targetComment) return;

        // 讀取動態計算後的視覺樓層編號 (例如 B1、B1-1、B2 等)，確保與使用者視覺上完全一致
        const visualFloor = targetComment.getAttribute('data-visual-floor') || `B${floor}`;

        // 【新增體驗優化】：如果目標樓層含有收合按鈕且目前為隱藏狀態，回覆時自動將其展開，方便閱讀前後文
        const toggler = targetComment.querySelector('.replies-toggle-btn');
        const repliesContainer = targetComment.querySelector('.replies-container');
        if (toggler && repliesContainer && repliesContainer.style.display === 'none') {
            toggler.click();
        }

        const inlineContainer = targetComment.querySelector('.inline-reply-form-container');
        if (inlineContainer) {
            // 動態置入完美相容原本 backend includes/comment.inc.php 的提交表單
            inlineContainer.innerHTML = `
                <form action="includes/comment.inc.php" method="POST" style="margin-top: 10px; width: 100%;">
                    <input type="hidden" name="post_id" value="<?= $post_id ?>">
                    <!-- 加上隱藏 prefix。當送出時，自動將 @B{floor} 前綴附加於內文 -->
                    <input type="hidden" name="reply_prefix" value="@B${floor} ">
                    <textarea name="content" rows="2" placeholder="回覆 ${visualFloor} @${username}..." required 
                        style="width: 100%; padding: 12px; border: 2px solid var(--border-color); border-radius: 12px; background: var(--bg-color); color: var(--text-color); box-sizing: border-box; resize: vertical; outline: none; font-family: inherit; font-size: 0.95rem;"></textarea>
                    <div style="text-align: right; margin-top: 8px; display: flex; justify-content: flex-end; gap: 8px;">
                        <button type="button" onclick="closeInlineReply(${floor})" style="background: var(--sidebar-item-hover); color: var(--text-color); border: none; padding: 6px 15px; border-radius: 8px; cursor: pointer; font-weight: 700; font-size: 0.85rem;">取消</button>
                        <button type="submit" name="submit_comment" class="btn-submit" style="margin-top: 0; padding: 6px 15px; border-radius: 8px; font-size: 0.85rem;">送出回覆</button>
                    </div>
                </form>
            `;
            
            // 監聽行內表單送出事件，在送出前自動組裝好 "@B{floor}" 的前綴字，以符合原有的階梯式回覆資料設計
            const form = inlineContainer.querySelector('form');
            form.addEventListener('submit', function(e) {
                const textarea = form.querySelector('textarea[name="content"]');
                const prefix = form.querySelector('input[name="reply_prefix"]').value;
                if (textarea && !textarea.value.startsWith(prefix)) {
                    textarea.value = prefix + textarea.value;
                }
            });

            inlineContainer.style.display = 'block';

            // 平滑滾動聚焦到剛開啟的輸入框
            const textarea = inlineContainer.querySelector('textarea');
            setTimeout(() => {
                textarea.focus();
                textarea.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }, 150);
        }
    }

    /**
     * 關閉指定的行內回覆框
     */
    function closeInlineReply(floor) {
        const targetComment = document.getElementById(`comment-floor-${floor}`);
        if (targetComment) {
            const inlineContainer = targetComment.querySelector('.inline-reply-form-container');
            if (inlineContainer) {
                inlineContainer.style.display = 'none';
                inlineContainer.innerHTML = '';
            }
        }
    }

    /**
     * 核心邏輯：在 DOM 載入後，處理留言的歸類與層級控制
     * 1. 限制最多一層：將所有帶有「@B{樓層}」的前綴留言歸類至對應的「根留言」下，避免產生多層嵌套。
     * 2. 收展回覆功能：預設隱藏子留言，並動態新增「查看回覆」按鈕進行切換。
     */
    document.addEventListener("DOMContentLoaded", function() {
        const comments = Array.from(document.querySelectorAll('.comment-item'));
        
        // 第一階段：扁平化分配（最多一層縮排）
        comments.forEach(comment => {
            const contentEl = comment.querySelector('.comment-content');
            if (!contentEl) return;

            const htmlContent = contentEl.innerHTML.trim();
            // 匹配開頭可能帶有空白或換行符的 @B{數字} 格式
            const match = htmlContent.match(/^@B(\d+)(?:\s|<br\s*\/?>)*/i);
            
            if (match) {
                const targetFloorNum = parseInt(match[1]);
                let parentComment = document.getElementById(`comment-floor-${targetFloorNum}`);
                
                // 防止自己回覆自己造成的無限自嵌套
                if (parentComment && parentComment !== comment) {
                    // 【關鍵修正】：如果目標母留言本身也是一個子回覆 (nested-reply)，則向上追溯至最頂層的根留言
                    while (parentComment && parentComment.classList.contains('nested-reply')) {
                        const closestParent = parentComment.parentElement.closest('.comment-item');
                        if (closestParent) {
                            parentComment = closestParent;
                        } else {
                            break;
                        }
                    }

                    if (parentComment && parentComment !== comment) {
                        // 清理顯示的 @B{N} 字樣，使畫面像精巧的階梯對話般精緻乾淨
                        contentEl.innerHTML = htmlContent.replace(/^@B\d+(?:\s|<br\s*\/?>)*/i, '');
                        
                        // 加上縮排與線條裝飾的樣式類別
                        comment.classList.add('nested-reply');
                        
                        // 置入目標根留言的專屬回覆放置區中
                        const repliesContainer = parentComment.querySelector('.replies-container');
                        if (repliesContainer) {
                            repliesContainer.appendChild(comment);
                        }
                    }
                }
            }
        });

        // 第二階段：【動態修正】重算並重新分配視覺樓層徽章 (避免在有子留言時，新的根留言發生跳號現象)
        const rootComments = document.querySelectorAll('.comment-item:not(.nested-reply)');
        rootComments.forEach((comment, rootIdx) => {
            // 計算真正的根留言樓層 (B1, B2, B3...)
            const visualRootFloorNum = rootIdx + 1;
            const visualRootFloorStr = `B${visualRootFloorNum}`;
            
            // 存入 data-visual-floor 以便 replyToFloor 讀取正確提示文字
            comment.setAttribute('data-visual-floor', visualRootFloorStr);
            
            // 更新根留言的 badge 視覺顯示
            const badge = comment.querySelector('.comment-floor-badge');
            if (badge) {
                badge.textContent = visualRootFloorStr;
            }

            // 更新其下的子回覆留言 (B1-1, B1-2, B1-3...)
            const repliesContainer = comment.querySelector('.replies-container');
            if (repliesContainer) {
                const nestedReplies = Array.from(repliesContainer.children);
                nestedReplies.forEach((reply, replyIdx) => {
                    const visualReplyFloorStr = `B${visualRootFloorNum}-${replyIdx + 1}`;
                    
                    // 存入 data-visual-floor 以便 replyToFloor 讀取
                    reply.setAttribute('data-visual-floor', visualReplyFloorStr);
                    
                    // 更新子留言的 badge 視覺顯示
                    const replyBadge = reply.querySelector('.comment-floor-badge');
                    if (replyBadge) {
                        replyBadge.textContent = visualReplyFloorStr;
                    }
                });
            }
        });

        // 第三階段：為含有子留言的根留言建立「查看回覆」的展開收合按鈕
        rootComments.forEach(comment => {
            const repliesContainer = comment.querySelector('.replies-container');
            if (repliesContainer && repliesContainer.children.length > 0) {
                const replyCount = repliesContainer.children.length;
                
                // 預設將回覆內容完全隱藏
                repliesContainer.style.display = 'none';

                // 動態建立切換按鈕
                const toggleBtn = document.createElement('button');
                toggleBtn.type = 'button';
                toggleBtn.className = 'replies-toggle-btn';
                toggleBtn.style.cssText = `
                    background: var(--accent-soft);
                    color: var(--accent-color);
                    border: none;
                    padding: 6px 14px;
                    border-radius: 8px;
                    font-size: 0.85rem;
                    font-weight: 700;
                    cursor: pointer;
                    margin-top: 10px;
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    transition: all 0.2s;
                    width: fit-content;
                `;
                toggleBtn.innerHTML = `💬 查看回覆 (${replyCount})`;

                // 註冊切換展開/收合事件
                toggleBtn.addEventListener('click', function() {
                    if (repliesContainer.style.display === 'none') {
                        repliesContainer.style.display = 'flex';
                        toggleBtn.innerHTML = `▲ 收起回覆`;
                        toggleBtn.style.background = 'var(--sidebar-item-hover)';
                        toggleBtn.style.color = 'var(--text-muted)';
                    } else {
                        repliesContainer.style.display = 'none';
                        toggleBtn.innerHTML = `💬 查看回覆 (${replyCount})`;
                        toggleBtn.style.background = 'var(--accent-soft)';
                        toggleBtn.style.color = 'var(--accent-color)';
                    }
                });

                // 將收合按鈕插入至回覆存放區（replies-container）的正上方
                repliesContainer.parentNode.insertBefore(toggleBtn, repliesContainer);
            }
        });
    });

    /**
     * 自定義 Toast 通知函數
     * @param {string} message - 顯示內容
     * @param {string} type - 'success' 或 'error'
     */
    function showToast(message, type = 'success') {
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        const icon = type === 'success' ? '✅' : '❌';
        toast.className = `toast ${type === 'error' ? 'error' : ''}`;
        
        toast.innerHTML = `<span>${icon}</span> <span>${message}</span>`;
        container.appendChild(toast);
        
        // 觸發進場動畫
        setTimeout(() => toast.classList.add('show'), 10);
        
        // 3秒後移除
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    // 主題控制
    const themeBtn = document.getElementById('themeBtn');
    const currentTheme = localStorage.getItem('theme') || 'light';
    document.body.setAttribute('data-theme', currentTheme);

    themeBtn.onclick = () => {
        const targetTheme = document.body.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        document.body.setAttribute('data-theme', targetTheme);
        localStorage.setItem('theme', targetTheme);
    };

    // 下拉選單控制
    const userTrigger = document.getElementById('userTrigger');
    const dropdownMenu = document.getElementById('dropdownMenu');
    if(userTrigger) {
        userTrigger.onclick = (e) => { 
            e.stopPropagation(); 
            dropdownMenu.classList.toggle('active'); 
        };
        document.addEventListener('click', () => dropdownMenu.classList.remove('active'));
    }

    // 按讚 AJAX
    document.getElementById('like-btn')?.addEventListener('click', function() {
        fetch('includes/like_ajax.inc.php?post_id=<?= $post_id ?>')
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    document.getElementById('like-icon').innerText = data.is_liked ? '❤️' : '🤍';
                    document.getElementById('like-count').innerText = data.new_count;
                }
            }).catch(err => console.error("Error:", err));
    });

    // 檢舉邏輯
    function openReport() { 
        const modal = document.getElementById('reportModal');
        modal.classList.add('active');
        document.getElementById('reportReason').focus();
    }
    
    function closeReport() { 
        const modal = document.getElementById('reportModal');
        modal.classList.remove('active');
        document.getElementById('reportReason').value = ''; 
    }
    
    function submitReport() {
        const btn = document.getElementById('submitReportBtn');
        const reason = document.getElementById('reportReason').value.trim();
        
        if (!reason) {
            showToast('請填寫檢舉理由', 'error');
            return;
        }
        
        btn.disabled = true;
        btn.style.opacity = '0.5';
        btn.innerText = '送出中...';
        
        fetch('includes/report_ajax.inc.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `post_id=<?= $post_id ?>&reason=${encodeURIComponent(reason)}`
        })
        .then(res => res.json())
        .then(data => {
            btn.disabled = false;
            btn.style.opacity = '1';
            btn.innerText = '送出檢舉';
            
            if(data.status === 'success') { 
                showToast('已成功送出檢舉，感謝您的回報！');
                closeReport(); 
            } else {
                showToast('發生錯誤：' + (data.message || '請稍後再試'), 'error');
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.style.opacity = '1';
            btn.innerText = '送出檢舉';
            showToast('連線失敗，請檢查網路', 'error');
        });
    }
</script>
</body>
</html>