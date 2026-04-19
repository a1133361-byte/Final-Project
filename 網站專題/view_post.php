<?php
session_start();
require_once "includes/dbh.inc.php";

if (!isset($_GET["id"]) || empty($_GET["id"])) {
    header("Location: index.php");
    exit();
}

$post_id = $_GET["id"];

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
    // 1. 移除字串前後多餘的空白/換行（這是解決第一行縮排的關鍵）
    $content = trim($content);
    
    // 2. 進行 HTML 轉義
    $safe_content = htmlspecialchars($content);
    
    // 3. 替換圖片標籤
    $rendered = preg_replace_callback('/\[img(\d+)\]/i', function($matches) use ($images) {
        $index = intval($matches[1]) - 1;
        
        if (isset($images[$index])) {
            $url = "uploads/post_imgs/" . $images[$index];
            // 這裡使用 </div>...<div> 結構，確保圖片獨立於文字段落
            return '</div><div class="content-image-wrapper"><img src="'.$url.'" class="post-inline-img"></div><div class="content-text">';
        }
        return ''; 
    }, $safe_content);

    // 4. 最後包裝成一個初始段落，並將換行轉換為 <br>
    return '<div class="content-text">' . nl2br($rendered) . '</div>';
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($post['title']) ?> - PHP Forum</title>
    <style>
        :root {
            --bg-color: #f4f7f6;
            --card-bg: #ffffff;
            --text-color: #333333;
            --text-muted: #636e72;
            --header-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --border-color: #dddddd;
            --input-bg: #ffffff;
            --tag-bg: #eef2ff;
        }

        [data-theme="dark"] {
            --bg-color: #1a1a2e;
            --card-bg: #16213e;
            --text-color: #e9ecef;
            --text-muted: #b2bec3;
            --header-gradient: linear-gradient(135deg, #1f4068 0%, #16213e 100%);
            --border-color: #444444;
            --input-bg: #0f3460;
            --tag-bg: #1f4068;
        }

        body {
            font-family: 'Segoe UI', 'Microsoft JhengHei', sans-serif;
            background-color: var(--bg-color);
            margin: 0;
            color: var(--text-color);
            transition: background-color 0.3s, color 0.3s;
            line-height: 1.6;
        }

        header {
            background: var(--header-gradient);
            color: white; padding: 0.8rem 2rem; display: flex;
            justify-content: space-between; align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); position: sticky; top: 0; z-index: 1000;
        }
        header h1 { margin: 0; font-size: 1.5rem; }
        header h1 a { color: white; text-decoration: none; }
        .nav-links { display: flex; align-items: center; gap: 20px; }
        .nav-links a { color: white; text-decoration: none; font-weight: 500; }
        .theme-toggle { background: rgba(255, 255, 255, 0.2); border: none; color: white; padding: 8px 12px; border-radius: 20px; cursor: pointer; }
        .btn-post { background: #ff9f43; padding: 8px 18px; border-radius: 8px; font-weight: bold !important; }
        .user-link { display: flex; align-items: center; gap: 10px; padding: 5px 15px; background: rgba(255, 255, 255, 0.1); border-radius: 50px; }
        .nav-avatar-img { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; }

        .container { max-width: 800px; margin: 30px auto; padding: 0 20px; }
        .post-article {
            background: var(--card-bg); padding: 40px; border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05); transition: background-color 0.3s;
        }
        .tag { background: var(--tag-bg); color: #667eea; padding: 4px 12px; border-radius: 20px; font-size: 13px; font-weight: bold; display: inline-block; }
        h1.post-title { font-size: 2.2rem; margin: 15px 0; color: var(--text-color); }

        .post-author-box {
            display: flex; align-items: center; gap: 12px; margin-bottom: 25px;
            padding-bottom: 15px; border-bottom: 1px solid var(--border-color);
        }
        .author-avatar { width: 45px; height: 45px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border-color); }
        .meta-info { display: flex; flex-direction: column; font-size: 0.9rem; color: var(--text-muted); }
        .author-name { font-weight: bold; color: #764ba2; text-decoration: none; }

        /* --- 內容樣式 --- */
        .post-content-body { margin-top: 20px; }
        /* 使用 white-space: normal 配合 nl2br，可以徹底避免第一行意外縮排 */
        .content-text { 
            font-size: 1.1rem; 
            color: var(--text-color); 
            white-space: normal; 
            word-break: break-all;
            margin: 0;
            padding: 0;
            text-indent: 0; /* 確保沒有首行縮排 */
        }
        .content-image-wrapper { margin: 30px 0; text-align: center; }
        .post-inline-img { 
            max-width: 100%; 
            max-height: 600px;
            border-radius: 8px; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .like-section { margin-top: 30px; padding-top: 20px; border-top: 1px dashed var(--border-color); }
        .post-management {
            margin-top: 20px; padding: 15px; background: rgba(229, 62, 62, 0.05);
            border-radius: 8px; border: 1px solid var(--border-color);
        }

        .comment-section { margin-top: 40px; background: var(--card-bg); padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .comment-item { display: flex; gap: 15px; border-bottom: 1px solid var(--border-color); padding: 20px 0; }
        .comment-avatar { width: 35px; height: 35px; border-radius: 50%; object-fit: cover; }
        .comment-user { font-weight: bold; color: #764ba2; text-decoration: none; }
        .comment-date { font-size: 0.8rem; color: var(--text-muted); }
        .comment-form textarea { width: 100%; padding: 15px; border: 1px solid var(--border-color); border-radius: 8px; background: var(--input-bg); color: var(--text-color); margin-top: 10px; box-sizing: border-box; resize: vertical; outline: none; }
        .btn-submit { background: #764ba2; color: white; border: none; padding: 10px 25px; border-radius: 5px; cursor: pointer; margin-top: 10px; font-weight: bold; }
    </style>
</head>
<body>

<header>
    <h1><a href="index.php">🚀 PHP Forum</a></h1>
    <div class="nav-links">
        <button class="theme-toggle" id="themeBtn">🌙 切換模式</button>
        <a href="index.php">首頁</a>
        <?php if (isset($_SESSION["user_id"])): ?>
            <a href="create_post.php" class="btn-post">我要發文</a>
            <a href="logout.php">登出</a>
            <a href="profile.php?id=<?= $_SESSION['user_id'] ?>" class="user-link">
                <?php $nav_avatar = !empty($_SESSION['profile_img']) ? "uploads/users_profile_img/".$_SESSION['profile_img'] : "uploads/default_avatar.png"; ?>
                <img src="<?= $nav_avatar ?>" class="nav-avatar-img">
                <span><?= htmlspecialchars($_SESSION["username"]) ?></span>
            </a>
        <?php else: ?>
            <a href="login.php">登入</a>
            <a href="regster.php">註冊</a>
        <?php endif; ?>
    </div>
</header>

<div class="container">
    <article class="post-article">
        <span class="tag"><?= htmlspecialchars($post['cat_name']) ?></span>
        <h1 class="post-title"><?= htmlspecialchars($post['title']) ?></h1>

        <div class="post-author-box">
            <?php $author_img = !empty($post['profile_img']) ? "uploads/users_profile_img/".$post['profile_img'] : "uploads/default_avatar.png"; ?>
            <img src="<?= $author_img ?>" class="author-avatar">
            <div class="meta-info">
                <a href="profile.php?id=<?= $post['user_id'] ?>" class="author-name"><?= htmlspecialchars($post['username']) ?></a>
                <span>發布於 <?= $post['created_at'] ?></span>
            </div>
        </div>

        <div class="post-content-body">
            <!-- 呼叫 renderPostContent 並直接輸出，裡面的 trim() 會清掉換行造成的縮排 -->
            <?= renderPostContent($post['content'], $post_images) ?>
        </div>

        <div class="like-section">
            <?php if (isset($_SESSION['user_id'])): ?>
                <div id="like-btn" style="cursor: pointer; display: inline-flex; align-items: center; gap: 8px; user-select: none;">
                    <span id="like-icon" style="font-size: 1.5rem; transition: transform 0.2s;"><?= $user_liked ? '❤️' : '🤍' ?></span>
                    <span id="like-count" style="color: #764ba2; font-weight: bold; font-size: 1.1rem;"><?= $like_count ?></span>
                </div>
            <?php else: ?>
                <div style="font-size: 1.1rem; color: var(--text-muted);">
                    🤍 <?= $like_count ?> <small>(登入後即可按讚)</small>
                </div>
            <?php endif; ?>
        </div>

        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $post['user_id']): ?>
            <div class="post-management">
                <span style="color: #e53e3e; font-weight: bold; margin-right: 15px;">🛠️ 管理：</span>
                <a href="edit_post.php?id=<?= $post['id'] ?>" style="color: #2b6cb0; text-decoration: none; margin-right: 15px;">📝 編輯內容</a>
                <a href="includes/delete_post.inc.php?id=<?= $post['id'] ?>" onclick="return confirm('確定要永久刪除這篇文章嗎？')" style="color: #e53e3e; text-decoration: none;">🗑️ 刪除文章</a>
            </div>
        <?php endif; ?>
    </article>

    <section class="comment-section">
        <!-- 留言部分保持不變 -->
        <h3>💬 留言討論</h3>
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
                <div class="comment-body">
                    <div class="comment-header">
                        <a href="profile.php?id=<?= $c['comment_user_id'] ?>" class="comment-user"><?= htmlspecialchars($c['username']) ?></a>
                        <span class="comment-date"><?= $c['created_at'] ?></span>
                    </div>
                    <p style="margin: 0; color: var(--text-color);"><?= nl2br(htmlspecialchars($c['content'])) ?></p>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if (isset($_SESSION["user_id"])): ?>
            <div class="comment-form">
                <form action="includes/comment.inc.php" method="POST">
                    <input type="hidden" name="post_id" value="<?= $post_id ?>">
                    <textarea name="content" rows="3" placeholder="我也想說幾句話..." required></textarea>
                    <button type="submit" name="submit_comment" class="btn-submit">發表留言</button>
                </form>
            </div>
        <?php endif; ?>
    </section>
</div>

<script>
    const themeBtn = document.getElementById('themeBtn');
    if (localStorage.getItem('theme') === 'dark') {
        document.body.setAttribute('data-theme', 'dark');
        themeBtn.textContent = '☀️ 淺色模式';
    }
    themeBtn.addEventListener('click', () => {
        let theme = 'light';
        if (document.body.getAttribute('data-theme') !== 'dark') {
            document.body.setAttribute('data-theme', 'dark');
            theme = 'dark';
            themeBtn.textContent = '☀️ 淺色模式';
        } else {
            document.body.removeAttribute('data-theme');
            themeBtn.textContent = '🌙 深色模式';
        }
        localStorage.setItem('theme', theme);
    });

    document.getElementById('like-btn')?.addEventListener('click', function() {
        const postId = <?= $post_id ?>;
        fetch('includes/like_ajax.inc.php?post_id=' + postId)
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    document.getElementById('like-icon').innerText = data.is_liked ? '❤️' : '🤍';
                    document.getElementById('like-count').innerText = data.new_count;
                }
            });
    });
</script>
</body>
</html>