<?php
session_start();
require_once "includes/dbh.inc.php";

$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$catID = isset($_GET['category']) ? $_GET['category'] : '';
$currentCatName = "";

try {
    // 1. 取得所有分類
    $cat_query = $pdo->query("SELECT * FROM categories ORDER BY id ASC");
    $all_categories = $cat_query->fetchAll();

    if ($catID !== '') {
        foreach ($all_categories as $cat) {
            if ($cat['id'] == $catID) {
                $currentCatName = $cat['name'];
                break;
            }
        }
    }

    // 2. 取得好友相關資料 (僅登入者)
    $my_friends = [];
    $requests = [];
    if (isset($_SESSION['user_id'])) {
        $uid = $_SESSION['user_id'];

        // (A) 取得待處理的好友請求 (別人想加我，但我還沒同意的)
        $req_sql = "SELECT users.id, users.username, users.profile_img 
                    FROM friends 
                    JOIN users ON friends.user_id = users.id 
                    WHERE friends.friend_id = ? AND friends.status = 'pending'
                    ORDER BY friends.created_at DESC";
        $req_stmt = $pdo->prepare($req_sql);
        $req_stmt->execute([$uid]);
        $requests = $req_stmt->fetchAll();

        // (B) 取得正式好友列表 (僅限 status = 'accepted')
        $f_sql = "SELECT users.id, users.username, users.profile_img 
                  FROM friends 
                  JOIN users ON friends.friend_id = users.id 
                  WHERE friends.user_id = ? AND friends.status = 'accepted'
                  ORDER BY friends.created_at DESC LIMIT 10";
        $f_stmt = $pdo->prepare($f_sql);
        $f_stmt->execute([$uid]);
        $my_friends = $f_stmt->fetchAll();
    }

    // 3. 取得文章列表
    $sql = "SELECT posts.*, users.username, users.profile_img, categories.name AS cat_name 
            FROM posts 
            JOIN users ON posts.user_id = users.id 
            JOIN categories ON posts.category_id = categories.id 
            WHERE 1=1";

    if ($searchTerm !== '') {
        $sql .= " AND (posts.title LIKE :search OR posts.content LIKE :search)";
    }
    if ($catID !== '') {
        $sql .= " AND posts.category_id = :catID";
    }

    $sql .= " ORDER BY posts.created_at DESC";
    $stmt = $pdo->prepare($sql);

    if ($searchTerm !== '') $stmt->bindValue(':search', '%' . $searchTerm . '%');
    if ($catID !== '') $stmt->bindValue(':catID', $catID);

    $stmt->execute();
    $posts = $stmt->fetchAll();
} catch (PDOException $e) {
    die("資料讀取失敗: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP 論壇 - 探索社群</title>
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
        }
        
        header { 
            background: var(--header-gradient); 
            color: white; padding: 0.8rem 2rem; display: flex; 
            justify-content: space-between; align-items: center; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); position: sticky; top: 0; z-index: 1000; 
        }
        header a { text-decoration: none; color: white; }
        header a h1 { margin: 0; font-size: 1.5rem; }

        .nav-links { display: flex; align-items: center; gap: 20px; }
        .nav-links a { color: white; text-decoration: none; font-weight: 500; }
        
        .theme-toggle {
            background: rgba(255, 255, 255, 0.2);
            border: none; color: white; padding: 8px 12px; border-radius: 20px;
            cursor: pointer; font-size: 14px;
        }

        .btn-post { background: #ff9f43; padding: 8px 18px; border-radius: 8px; font-weight: bold !important; }
        .user-link { display: flex; align-items: center; gap: 10px; padding: 5px 15px; background: rgba(255, 255, 255, 0.1); border-radius: 50px; }
        .nav-avatar-img { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; }

        .main-wrapper {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 1fr 320px;
            gap: 30px;
        }

        .sidebar { display: flex; flex-direction: column; gap: 20px; }
        .sidebar-card {
            background: var(--card-bg);
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        .sidebar-title { 
            margin-top: 0; font-size: 1.1rem; font-weight: bold;
            padding-bottom: 10px; border-bottom: 2px solid var(--tag-bg);
            margin-bottom: 15px; display: flex; align-items: center; gap: 8px;
        }

        .friend-item {
            display: flex; align-items: center; gap: 12px;
            padding: 10px 0; text-decoration: none; color: var(--text-color);
            border-bottom: 1px solid var(--border-color);
            transition: 0.2s;
        }
        .friend-item:last-child { border-bottom: none; }
        .btn-accept {
            background: #27ae60; color: white; border: none; padding: 5px 10px;
            border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: bold;
        }
        .chat-icon {
            text-decoration: none; font-size: 1.2rem; filter: grayscale(1); transition: 0.2s;
        }
        .chat-icon:hover { filter: grayscale(0); transform: scale(1.2); }

        .filter-container { 
            display: flex; gap: 15px; margin-bottom: 30px; 
            background: var(--card-bg); padding: 20px; border-radius: 12px; 
            box-shadow: 0 2px 8px rgba(0,0,0,0.05); align-items: center; 
        }
        .search-form { display: flex; flex: 2; gap: 10px; }
        .search-input { 
            flex: 1; padding: 12px; border: 1px solid var(--border-color); 
            border-radius: 8px; background-color: var(--input-bg); color: var(--text-color);
        }
        .btn-search { padding: 12px 20px; background: #764ba2; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; }
        .cat-select { padding: 12px; border: 1px solid var(--border-color); border-radius: 8px; background-color: var(--input-bg); color: var(--text-color); cursor: pointer; }

        .section-title { border-left: 5px solid #764ba2; padding-left: 15px; margin-bottom: 25px; }
        .post-card { 
            background: var(--card-bg); border-radius: 12px; padding: 25px; 
            margin-bottom: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); transition: 0.2s; 
        }
        .post-card:hover { transform: translateY(-5px); }
        .tag { background: var(--tag-bg); color: #667eea; font-size: 12px; font-weight: bold; padding: 4px 10px; border-radius: 20px; display: inline-block; margin-bottom: 10px; }
        .post-info { font-size: 0.85rem; color: var(--text-muted); margin-bottom: 15px; display: flex; align-items: center; gap: 10px; }
        .post-avatar { width: 28px; height: 28px; border-radius: 50%; object-fit: cover; }
        .author-link { color: #764ba2; text-decoration: none; font-weight: bold; }
        .post-content { color: var(--text-muted); line-height: 1.6; margin-bottom: 15px; }
        .read-more { color: #764ba2; text-decoration: none; font-weight: 600; }

        @media (max-width: 992px) {
            .main-wrapper { grid-template-columns: 1fr; }
            .sidebar { order: 2; }
        }
    </style>
</head>
<body>

<header>
    <a href="index.php"><h1>🚀 PHP Forum</h1></a>
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

<div class="main-wrapper">
    <main class="content-area">
        <h2 class="section-title">
            <?php 
                if ($searchTerm !== '') echo "🔍 搜尋結果：$searchTerm";
                elseif ($currentCatName !== '') echo "📁 分類：$currentCatName";
                else echo "最新探索";
            ?>
        </h2>

        <div class="filter-container">
            <form action="index.php" method="GET" class="search-form">
                <input type="hidden" name="category" value="<?= htmlspecialchars($catID) ?>">
                <input type="text" name="search" class="search-input" placeholder="搜尋文章..." value="<?= htmlspecialchars($searchTerm) ?>">
                <button type="submit" class="btn-search">搜尋</button>
            </form>

            <select class="cat-select" onchange="filterByCategory(this.value)">
                <option value="" <?= ($catID === '') ? 'selected' : '' ?>>📁 所有分類</option>
                <?php foreach ($all_categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= ($catID == $cat['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if (count($posts) > 0): ?>
            <?php foreach ($posts as $post): ?>
                <div class="post-card">
                    <span class="tag"><?= htmlspecialchars($post['cat_name']) ?></span>
                    <h3><a href="view_post.php?id=<?= $post['id'] ?>" style="text-decoration:none; color:inherit;"><?= htmlspecialchars($post['title']) ?></a></h3>
                    <div class="post-info">
                        <?php $p_avatar = !empty($post['profile_img']) ? "uploads/users_profile_img/".$post['profile_img'] : "uploads/default_avatar.png"; ?>
                        <img src="<?= $p_avatar ?>" class="post-avatar">
                        <div>
                            <a href="profile.php?id=<?= $post['user_id'] ?>" class="author-link"><?= htmlspecialchars($post['username']) ?></a> • 
                            📅 <?= date('Y-m-d', strtotime($post['created_at'])) ?>
                        </div>
                    </div>
                    <div class="post-content">
                        <?= htmlspecialchars(mb_substr(strip_tags($post['content']), 0, 80)) ?>...
                    </div>
                    <a href="view_post.php?id=<?= $post['id'] ?>" class="read-more">繼續閱讀 →</a>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="text-align: center; padding: 50px; color: var(--text-muted);">
                <p>找不到符合條件的文章。</p>
            </div>
        <?php endif; ?>
    </main>

    <aside class="sidebar">
        <?php if (isset($_SESSION['user_id']) && count($requests) > 0): ?>
            <div class="sidebar-card" style="border: 1px solid #ff9f43;">
                <h3 class="sidebar-title">🔔 好友請求 (<?= count($requests) ?>)</h3>
                <?php foreach ($requests as $req): ?>
                    <div class="friend-item" style="justify-content: space-between;">
                        <div style="display:flex; align-items:center; gap:10px;">
                            <?php $r_img = !empty($req['profile_img']) ? "uploads/users_profile_img/".$req['profile_img'] : "uploads/default_avatar.png"; ?>
                            <img src="<?= $r_img ?>" class="nav-avatar-img">
                            <span style="font-size: 0.9rem;"><?= htmlspecialchars($req['username']) ?></span>
                        </div>
                        <button class="btn-accept" onclick="handleFriendRequest(<?= $req['id'] ?>, 'accept')">同意</button>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['user_id'])): ?>
            <div class="sidebar-card">
                <h3 class="sidebar-title">🤝 我的好友</h3>
                <?php if (count($my_friends) > 0): ?>
                    <?php foreach ($my_friends as $f): ?>
                        <div class="friend-item" style="justify-content: space-between;">
                            <a href="profile.php?id=<?= $f['id'] ?>" style="display:flex; align-items:center; gap:12px; text-decoration:none; color:inherit;">
                                <?php $f_img = !empty($f['profile_img']) ? "uploads/users_profile_img/".$f['profile_img'] : "uploads/default_avatar.png"; ?>
                                <img src="<?= $f_img ?>" class="nav-avatar-img">
                                <span><?= htmlspecialchars($f['username']) ?></span>
                            </a>
                            <a href="chat.php?user_id=<?= $f['id'] ?>" class="chat-icon" title="傳送私訊">💬</a>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: var(--text-muted); font-size: 0.9rem; text-align: center; margin: 20px 0;">目前尚無好友</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </aside>
</div>

<script>
    // 主題切換邏輯
    const themeBtn = document.getElementById('themeBtn');
    const currentTheme = localStorage.getItem('theme');
    if (currentTheme === 'dark') {
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

    // 分類跳轉
    function filterByCategory(catId) {
        const searchInput = document.querySelector('input[name="search"]');
        const searchTerm = searchInput ? searchInput.value : '';
        let url = 'index.php?category=' + catId;
        if (searchTerm) url += '&search=' + encodeURIComponent(searchTerm);
        window.location.href = url;
    }

    // AJAX 處理好友同意
    function handleFriendRequest(friendId, action) {
        fetch(`includes/friend_action.inc.php?friend_id=${friendId}&action=${action}`)
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    location.reload(); 
                } else {
                    alert(data.message);
                }
            })
            .catch(err => console.error('Error:', err));
    }
</script>
</body>
</html>