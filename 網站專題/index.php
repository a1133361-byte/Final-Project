<?php
    session_start();
    require_once "includes/dbh.inc.php";

    $searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
    $catID = isset($_GET['category']) ? $_GET['category'] : '';
    $currentCatName = "";

    try {
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
        die("讀取文章失敗: " . $e->getMessage());
    }
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP 論壇 - 探索社群</title>
    <style>
        /* --- 1. 定義顏色變數 --- */
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

        /* --- 2. 深色模式顏色設定 --- */
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

        /* --- 基礎風格 (套用變數) --- */
        body { 
            font-family: 'Segoe UI', 'Microsoft JhengHei', sans-serif; 
            background-color: var(--bg-color); 
            margin: 0; 
            color: var(--text-color);
            transition: background-color 0.3s, color 0.3s; /* 平滑切換動畫 */
        }
        
        header { 
            background: var(--header-gradient); 
            color: white; padding: 0.8rem 2rem; display: flex; 
            justify-content: space-between; align-items: center; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); position: sticky; top: 0; z-index: 1000; 
        }
        header a{
            text-decoration: none;
            color: white;
        }
        header a h1 { 
            margin: 0; font-size: 1.5rem; 
        }

        .nav-links { display: flex; align-items: center; gap: 20px; }
        .nav-links a { color: white; text-decoration: none; font-weight: 500; transition: 0.3s; }
        
        /* 深色模式切換按鈕 */
        .theme-toggle {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            padding: 8px 12px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 14px;
            margin-right: 10px;
        }

        .btn-post { background: #ff9f43; padding: 8px 18px; border-radius: 8px; font-weight: bold !important; }
        .user-link { display: flex; align-items: center; gap: 10px; padding: 5px 15px; background: rgba(255, 255, 255, 0.1); border-radius: 50px; }
        .nav-avatar-img { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; }

        .main-container { max-width: 900px; margin: 30px auto; padding: 0 20px; }
        .section-title { border-left: 5px solid #764ba2; padding-left: 15px; margin-bottom: 25px; }

        .filter-container { 
            display: flex; gap: 15px; margin-bottom: 30px; 
            background: var(--card-bg); padding: 20px; border-radius: 12px; 
            box-shadow: 0 2px 8px rgba(0,0,0,0.05); align-items: center; 
        }
        .search-form { display: flex; flex: 2; align-items: center; gap: 10px; }
        .search-input { 
            flex: 1; padding: 12px; border: 1px solid var(--border-color); 
            border-radius: 8px; outline: none; font-size: 15px; 
            background-color: var(--input-bg); color: var(--text-color);
        }
        .btn-search { padding: 12px 20px; background: #764ba2; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; }

        .cat-select { 
            flex: 1; padding: 12px; border: 1px solid var(--border-color); 
            border-radius: 8px; background-color: var(--input-bg); 
            font-size: 15px; color: var(--text-color); cursor: pointer; outline: none; 
        }

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

    <div class="main-container">
        <h2 class="section-title">
            <?php 
                if ($searchTerm !== '') echo "🔍 搜尋結果：$searchTerm";
                elseif ($currentCatName !== '') echo "📁 分類：$currentCatName";
                else echo "最新探索";
            ?>
        </h2>

        <div class="filter-container">
            <form action="index.php" method="GET" class="search-form" id="searchForm">
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
            <div style="text-align: center; padding: 50px; color: #b2bec3;">
                <p>找不到符合條件的文章。</p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // --- 4. 深色模式 JS 邏輯 ---
        const themeBtn = document.getElementById('themeBtn');
        const currentTheme = localStorage.getItem('theme');

        // 初始化檢查：如果上次選的是深色，就套用
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
            // 儲存選擇到本地
            localStorage.setItem('theme', theme);
        });

        function filterByCategory(catId) {
            const searchTerm = document.querySelector('input[name="search"]').value;
            let url = 'index.php?category=' + catId;
            if (searchTerm) url += '&search=' + encodeURIComponent(searchTerm);
            window.location.href = url;
        }
    </script>
</body>
</html>