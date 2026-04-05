<?php
session_start();
require_once "includes/dbh.inc.php";

// 安全檢查：沒登入不能進來
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

// 抓取目前的資料
$uid = $_SESSION["user_id"];
try {
    $sql = "SELECT * FROM users WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$uid]);
    $user = $stmt->fetch();
} catch (PDOException $e) {
    die("讀取失敗: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>編輯個人檔案 - PHP Forum</title>
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

        /* --- 2. 導覽列風格 (同步全站) --- */
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
            max-width: 600px;
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

        /* 輸入框美化 */
        input[type="text"], textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            box-sizing: border-box;
            font-size: 16px;
            transition: all 0.3s;
            background-color: var(--input-bg);
            color: var(--text-color);
            outline: none;
        }

        /* 唯讀狀態 */
        input[disabled] {
            opacity: 0.6;
            cursor: not-allowed;
            border-style: dashed;
        }

        textarea {
            resize: vertical;
            min-height: 120px;
            line-height: 1.6;
        }

        input:focus, textarea:focus {
            border-color: #764ba2;
            background-color: var(--input-focus-bg);
            box-shadow: 0 0 0 4px rgba(118, 75, 162, 0.1);
        }

        small {
            display: block;
            color: var(--text-muted);
            margin-top: 5px;
            font-size: 12px;
        }

        /* 按鈕群組 */
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
            transform: translateY(-2px);
            opacity: 0.95;
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

        #preview {
            border: 2px solid var(--border-color);
            background-color: var(--bg-color);
        }
    </style>
</head>
<body>

    <header>
        <h1><a href="index.php">🚀 PHP Forum</a></h1>
        <div class="nav-links">
            <button class="theme-toggle" id="themeBtn">🌙 切換模式</button>
            <a href="index.php">首頁</a>
            <a href="profile.php?id=<?= $uid ?>" class="user-link">
                <?php 
                    $nav_avatar = !empty($user['profile_img']) 
                        ? "uploads/users_profile_img/".$user['profile_img'] 
                        : "uploads/default_avatar.png";
                ?>
                <img src="<?= $nav_avatar ?>" class="nav-avatar-img">
                <span><?= htmlspecialchars($user["username"]) ?></span>
            </a>
        </div>
    </header>

    <div class="main-container">
        <div class="post-form-card">
            <h2>👤 編輯個人資料</h2>
            
            <form action="includes/update_profile.inc.php" method="POST" enctype="multipart/form-data">
                
                <div class="form-group">
                    <label>個人頭像</label>
                    <div style="margin-bottom: 15px; display: flex; align-items: center; gap: 20px;">
                        <img src="<?= $nav_avatar ?>" id="preview" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover;">
                        <input type="file" name="profile_image" id="imageInput" accept="image/*" style="font-size: 13px;">
                    </div>
                    <small>支援格式：JPG, PNG, GIF (建議 500x500 像素)</small>
                </div>

                <div class="form-group">
                    <label>使用者名稱</label>
                    <input type="text" value="<?= htmlspecialchars($user['username']) ?>" disabled>
                    <small>⚠️ 為了帳號識別安全，使用者名稱不可修改。</small>
                </div>

                <div class="form-group">
                    <label>個人簡介 (Bio)</label>
                    <textarea name="bio" placeholder="跟大家介紹一下你自己吧..."><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                </div>

                <div class="button-group">
                    <a href="profile.php?id=<?= $uid ?>" class="btn-cancel">取消</a>
                    <button type="submit" name="submit_profile" class="btn-submit">儲存修改</button>
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

        // 圖片預覽邏輯
        document.getElementById('imageInput').onchange = function (evt) {
            const [file] = this.files;
            if (file) {
                document.getElementById('preview').src = URL.createObjectURL(file);
            }
        }
    </script>
</body>
</html>