<?php
session_start();
require_once "includes/dbh.inc.php";

// 1. 檢查是否登入
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

// 2. 檢查是否有文章 ID
if (!isset($_GET["id"]) || empty($_GET["id"])) {
    header("Location: index.php");
    exit();
}

$post_id = $_GET["id"];
$user_id = $_SESSION["user_id"];

try {
    // 3. 抓取文章資料
    $sql = "SELECT * FROM posts WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$post_id]);
    $post = $stmt->fetch();

    if (!$post) {
        die("找不到這篇文章！");
    }

    // 4. 權限檢查
    if ($post['user_id'] != $user_id) {
        header("Location: view_post.php?id=$post_id&error=unauthorized");
        exit();
    }

    // 5. 抓取所有分類
    $cat_sql = "SELECT * FROM categories";
    $categories = $pdo->query($cat_sql)->fetchAll();

} catch (PDOException $e) {
    die("讀取失敗: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>編輯文章 - PHP Forum</title>
    <style>
        /* --- 1. 定義顏色變數 (同步全站) --- */
        :root {
            --bg-color: #f4f7f6;
            --card-bg: #ffffff;
            --text-color: #333333;
            --text-muted: #636e72;
            --header-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --border-color: #dddddd;
            --input-bg: #fafafa;
            --input-focus-bg: #ffffff;
        }

        [data-theme="dark"] {
            --bg-color: #1a1a2e;
            --card-bg: #16213e;
            --text-color: #e9ecef;
            --text-muted: #b2bec3;
            --header-gradient: linear-gradient(135deg, #1f4068 0%, #16213e 100%);
            --border-color: #444444;
            --input-bg: #0f3460;
            --input-focus-bg: #1f4068;
        }

        body { 
            font-family: 'Segoe UI', 'Microsoft JhengHei', sans-serif; 
            background-color: var(--bg-color); 
            margin: 0; 
            color: var(--text-color);
            transition: background-color 0.3s, color 0.3s;
            padding-bottom: 50px; 
        }

        /* --- 2. 導覽列 (同步全站) --- */
        header { 
            background: var(--header-gradient); 
            color: white; padding: 0.8rem 2rem; display: flex; 
            justify-content: space-between; align-items: center; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); position: sticky; top: 0; z-index: 1000; 
        }
        header h1 { margin: 0; font-size: 1.5rem; }
        header h1 a { color: white; text-decoration: none; }

        .nav-links { display: flex; align-items: center; gap: 20px; }
        .nav-links a { color: white; text-decoration: none; font-weight: 500; transition: 0.3s; }
        
        .theme-toggle {
            background: rgba(255, 255, 255, 0.2);
            border: none; color: white; padding: 8px 12px; border-radius: 20px;
            cursor: pointer; font-size: 14px;
        }

        .user-link { display: flex; align-items: center; gap: 10px; padding: 5px 15px; background: rgba(255, 255, 255, 0.1); border-radius: 50px; }
        .nav-avatar-img { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; }

        /* --- 3. 表單容器設計 --- */
        .form-container { 
            max-width: 700px; 
            margin: 40px auto; 
            background: var(--card-bg); 
            padding: 35px; 
            border-radius: 12px; 
            box-shadow: 0 5px 15px rgba(0,0,0,0.1); 
            transition: background-color 0.3s;
        }
        h2 { 
            color: #764ba2; 
            border-bottom: 2px solid var(--border-color); 
            padding-bottom: 10px; 
            margin-bottom: 25px; 
        }

        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: bold; color: var(--text-color); }
        
        input[type="text"], select, textarea {
            width: 100%; 
            padding: 12px; 
            border: 1px solid var(--border-color); 
            border-radius: 8px; 
            box-sizing: border-box; 
            font-size: 16px;
            background-color: var(--input-bg);
            color: var(--text-color);
            transition: all 0.3s;
            outline: none;
        }

        input:focus, select:focus, textarea:focus {
            border-color: #764ba2;
            background-color: var(--input-focus-bg);
            box-shadow: 0 0 0 4px rgba(118, 75, 162, 0.1);
        }

        textarea { resize: vertical; min-height: 250px; line-height: 1.6; }

        .btn-group { display: flex; gap: 15px; margin-top: 30px; }
        .btn-save { 
            background: linear-gradient(to right, #667eea, #764ba2);
            color: white; border: none; padding: 14px 25px; border-radius: 8px; 
            cursor: pointer; font-weight: bold; flex: 2; transition: 0.3s; 
            box-shadow: 0 4px 15px rgba(118, 75, 162, 0.3);
        }
        .btn-save:hover { transform: translateY(-2px); opacity: 0.9; }

        .btn-cancel { 
            background: var(--bg-color); 
            color: var(--text-muted); 
            text-decoration: none; padding: 14px 25px; border-radius: 8px; 
            text-align: center; flex: 1; transition: 0.3s; 
            border: 1px solid var(--border-color);
        }
    </style>
</head>
<body>

<header>
    <h1><a href="index.php">🚀 PHP Forum</a></h1>
    <div class="nav-links">
        <button class="theme-toggle" id="themeBtn">🌙 切換模式</button>
        <a href="index.php">首頁</a>
        <a href="profile.php?id=<?= $_SESSION['user_id'] ?>" class="user-link">
            <?php 
                $nav_avatar = !empty($_SESSION['profile_img']) 
                    ? "uploads/users_profile_img/".$_SESSION['profile_img'] 
                    : "uploads/default_avatar.png";
            ?>
            <img src="<?= $nav_avatar ?>" class="nav-avatar-img">
            <span><?= htmlspecialchars($_SESSION["username"]) ?></span>
        </a>
    </div>
</header>

<div class="form-container">
    <h2>📝 編輯您的文章</h2>
    
    <form action="includes/edit_post.inc.php" method="POST">
        <input type="hidden" name="post_id" value="<?= $post['id'] ?>">

        <div class="form-group">
            <label>文章分類</label>
            <select name="category_id" required>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= ($cat['id'] == $post['category_id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>標題</label>
            <input type="text" name="title" value="<?= htmlspecialchars($post['title']) ?>" required placeholder="輸入吸引人的標題...">
        </div>

        <div class="form-group">
            <label>內容</label>
            <textarea name="content" required placeholder="寫點什麼吧..."><?= htmlspecialchars($post['content']) ?></textarea>
        </div>

        <div class="btn-group">
            <a href="view_post.php?id=<?= $post_id ?>" class="btn-cancel">取消修改</a>
            <button type="submit" name="submit_edit" class="btn-save">儲存並更新</button>
        </div>
    </form>
</div>

<script>
    // --- 深色模式 JS 邏輯 ---
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
</script>

</body>
</html>