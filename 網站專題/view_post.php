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

        /* 留言區塊 */
        .comment-section { margin-top: 30px; background: var(--card-bg); padding: 30px; border-radius: 24px; border: 1px solid var(--border-color); }
        .comment-item { display: flex; gap: 15px; border-bottom: 1px solid var(--border-color); padding: 20px 0; }
        .comment-item:last-child { border-bottom: none; }
        .comment-avatar { width: 38px; height: 38px; border-radius: 50%; object-fit: cover; }
        .comment-user { font-weight: 700; color: var(--text-color); text-decoration: none; font-size: 0.95rem; }
        .comment-date { font-size: 0.8rem; color: var(--text-muted); font-weight: 500; }
        .comment-content { margin-top: 6px; font-size: 1rem; color: var(--text-color); opacity: 0.9; }

        .comment-form textarea { 
            width: 100%; padding: 15px; border: 2px solid var(--border-color); border-radius: 16px; 
            background: var(--bg-color); color: var(--text-color); margin-top: 15px; 
            box-sizing: border-box; resize: vertical; outline: none; transition: 0.2s; font-family: inherit;
        }
        .comment-form textarea:focus { border-color: var(--accent-color); }
        .btn-submit { background: var(--accent-color); color: white; border: none; padding: 10px 25px; border-radius: 12px; cursor: pointer; margin-top: 10px; font-weight: 700; font-size: 0.95rem; transition: 0.2s; }
        .btn-submit:hover { opacity: 0.9; transform: translateY(-1px); }

        /* 管理員樣式小盒 */
        .post-management {
            margin-top: 20px; padding: 15px 20px; background: var(--admin-soft);
            border-radius: 14px; border: 1px solid var(--admin-color); font-size: 0.9rem;
            display: flex; align-items: center; gap: 15px;
        }

        /* Modal: 跟 index.php 一樣的模糊效果 */
        #reportModal {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5); backdrop-filter: blur(8px);
            display: none; justify-content: center; align-items: center; z-index: 2000;
        }
        .modal-content {
            background: var(--card-bg); padding: 30px; border-radius: 25px;
            width: 90%; max-width: 450px; border: 1px solid var(--border-color);
        }
    </style>
</head>
<body>

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
                    <div class="dropdown-menu" id="dropdownMenu">
                        <div style="padding: 10px 20px; font-size: 0.7rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase;">使用者功能</div>
                        <a href="profile.php?id=<?= $_SESSION['user_id'] ?>">👤 我的個人資料</a>
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
            <div class="comment-form">
                <form action="includes/comment.inc.php" method="POST">
                    <input type="hidden" name="post_id" value="<?= $post_id ?>">
                    <textarea name="content" rows="3" placeholder="分享您的看法..." required></textarea>
                    <div style="text-align: right;">
                        <button type="submit" name="submit_comment" class="btn-submit">發表留言</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <div style="margin-top: 20px;">
            <?php
            $c_sql = "SELECT comments.*, users.username, users.id AS comment_user_id, users.profile_img AS comment_avatar
                      FROM comments
                      JOIN users ON comments.user_id = users.id
                      WHERE post_id = ?
                      ORDER BY created_at ASC";
            $c_stmt = $pdo->prepare($c_sql);
            $c_stmt->execute([$post_id]);
            $comments = $c_stmt->fetchAll();

            foreach ($comments as $c): ?>
                <div class="comment-item">
                    <?php $c_img = !empty($c['comment_avatar']) ? "uploads/users_profile_img/".$c['comment_avatar'] : "uploads/default_avatar.png"; ?>
                    <img src="<?= $c_img ?>" class="comment-avatar">
                    <div style="flex: 1;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <a href="profile.php?id=<?= $c['comment_user_id'] ?>" class="comment-user"><?= htmlspecialchars($c['username']) ?></a>
                            <span class="comment-date"><?= date('m/d H:i', strtotime($c['created_at'])) ?></span>
                        </div>
                        <div class="comment-content"><?= nl2br(htmlspecialchars($c['content'])) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
</div>

<div id="reportModal">
    <div class="modal-content">
        <h3 style="margin-top:0; font-weight:800;">🚩 檢舉此文章</h3>
        <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom:10px;">請敘述檢舉理由：</p>
        <textarea id="reportReason" style="width:100%; height:120px; border-radius:12px; border:2px solid var(--border-color); background:var(--bg-color); color:var(--text-color); padding:15px; box-sizing:border-box; outline:none; font-family:inherit;" placeholder="例如：內容包含不當言論..."></textarea>
        <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:20px;">
            <button style="background:var(--sidebar-item-hover); color:var(--text-color); border:none; padding:10px 20px; border-radius:10px; cursor:pointer; font-weight:700;" onclick="closeReport()">取消</button>
            <button style="background:#ef4444; color:white; border:none; padding:10px 20px; border-radius:10px; cursor:pointer; font-weight:700;" onclick="submitReport()">送出檢舉</button>
        </div>
    </div>
</div>

<script>
    // Theme Control
    const themeBtn = document.getElementById('themeBtn');
    const currentTheme = localStorage.getItem('theme') || 'light';
    document.body.setAttribute('data-theme', currentTheme);

    themeBtn.onclick = () => {
        const targetTheme = document.body.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        document.body.setAttribute('data-theme', targetTheme);
        localStorage.setItem('theme', targetTheme);
    };

    // Dropdown Control
    const userTrigger = document.getElementById('userTrigger');
    const dropdownMenu = document.getElementById('dropdownMenu');
    if(userTrigger) {
        userTrigger.onclick = (e) => { 
            e.stopPropagation(); 
            dropdownMenu.classList.toggle('active'); 
        };
        document.addEventListener('click', () => dropdownMenu.classList.remove('active'));
    }

    // Like Ajax
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

    // Report Logic
    function openReport() { document.getElementById('reportModal').style.display = 'flex'; }
    function closeReport() { document.getElementById('reportModal').style.display = 'none'; }
    
    function submitReport() {
        const reason = document.getElementById('reportReason').value.trim();
        if (!reason) return alert('請填寫理由');
        
        fetch('includes/report_ajax.inc.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `post_id=<?= $post_id ?>&reason=${encodeURIComponent(reason)}`
        })
        .then(res => res.json())
        .then(data => {
            if(data.status === 'success') { alert('已送出檢舉'); closeReport(); }
        });
    }
</script>
</body>
</html>