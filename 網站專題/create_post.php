<?php
session_start();
// 1. 安全檢查：沒登入不能進來
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php?error=please_login");
    exit();
}

require_once "includes/dbh.inc.php";

// 2. 抓取所有看板分類
try {
    $sql = "SELECT * FROM categories ORDER BY id ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    die("資料庫錯誤: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>發表新文章 - PHP Forum</title>
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

        /* --- 3. 表單卡片設計 --- */
        .main-container {
            max-width: 700px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .post-form-card {
            background: var(--card-bg);
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            transition: background-color 0.3s;
        }

        h2 {
            margin-top: 0;
            color: var(--text-color);
            font-size: 1.6rem;
            text-align: center;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-color);
        }

        /* 輸入框通用樣式 */
        select, input[type="text"], textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            box-sizing: border-box;
            font-size: 16px;
            transition: all 0.3s;
            outline: none;
            background-color: var(--input-bg);
            color: var(--text-color);
        }

        select:focus, input[type="text"]:focus, textarea:focus {
            border-color: #764ba2;
            background-color: var(--input-focus-bg);
            box-shadow: 0 0 8px rgba(118, 75, 162, 0.1);
        }

        textarea {
            resize: vertical;
            min-height: 250px;
            line-height: 1.6;
        }

        /* 按鈕區塊 */
        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .btn-submit {
            flex: 2;
            background: linear-gradient(to right, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 14px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
            box-shadow: 0 4px 15px rgba(118, 75, 162, 0.3);
        }

        .btn-submit:hover {
            opacity: 0.95;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(118, 75, 162, 0.4);
        }

        .btn-cancel {
            flex: 1;
            background-color: var(--bg-color);
            color: var(--text-muted);
            text-align: center;
            text-decoration: none;
            padding: 14px;
            border-radius: 8px;
            font-size: 16px;
            transition: 0.3s;
            border: 1px solid var(--border-color);
        }

        .btn-cancel:hover {
            opacity: 0.8;
        }
    </style>
</head>
<body>

<header>
    <h1><a href="index.php">🚀 PHP Forum</a></h1>
    <div class="nav-links">
        <button class="theme-toggle" id="themeBtn">🌙 切換模式</button>
        <a href="index.php">返回首頁</a>
        
        <?php if (isset($_SESSION["user_id"])): ?>
            <a href="profile.php?id=<?= $_SESSION['user_id'] ?>" class="user-link">
                <?php 
                    $nav_avatar = !empty($_SESSION['profile_img']) 
                        ? "uploads/users_profile_img/".$_SESSION['profile_img'] 
                        : "uploads/default_avatar.png";
                ?>
                <img src="<?= $nav_avatar ?>" class="nav-avatar-img">
                <span><?= htmlspecialchars($_SESSION["username"]) ?></span>
            </a>
        <?php endif; ?>
    </div>
</header>

<div class="main-container">
    <div class="post-form-card">
        <h2>✍️ 分享你的想法</h2>
        
        <form action="includes/post.inc.php" method="POST">
            <div class="form-group">
                <label>選擇看板</label>
                <select name="category_id" required>
                    <option value="" disabled selected>-- 請選擇一個看板 --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>文章標題</label>
                <input type="text" name="title" placeholder="標題越吸引人，點閱率越高喔！" required>
            </div>

            <div class="form-group">
                <label>內容</label>
                <textarea name="content" placeholder="在這裡輸入你的精彩內容..." required></textarea>
            </div>

            <div class="button-group">
                <a href="index.php" class="btn-cancel">取消</a>
                <button type="submit" name="submit_post" class="btn-submit">發布文章</button>
            </div>
        </form>
    </div>
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
