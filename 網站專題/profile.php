<?php
session_start();
require_once "includes/dbh.inc.php";

// 檢查網址有沒有帶 ID
if (!isset($_GET["id"]) || empty($_GET["id"])) {
    header("Location: index.php");
    exit();
}

$profile_id = $_GET["id"];

try {
    // 1. 取得該用戶資料
    $sql = "SELECT * FROM users WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$profile_id]);
    $user = $stmt->fetch();

    if (!$user) { 
        die("<div style='text-align:center; padding:50px;'><h2>找不到該使用者！</h2><a href='index.php'>回首頁</a></div>"); 
    }

    // 2. 判斷與該用戶的好友關係狀態
    $friend_status = 'none'; // 預設：無關係
    if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $profile_id) {
        $check_f = "SELECT * FROM friends WHERE user_id = ? AND friend_id = ?";
        $stmt_f = $pdo->prepare($check_f);
        $stmt_f->execute([$_SESSION['user_id'], $profile_id]);
        $rel = $stmt_f->fetch();
        
        if ($rel) {
            $friend_status = $rel['status']; // 'pending' 或 'accepted'
        }
    }

} catch (PDOException $e) {
    die("資料庫錯誤: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($user['username']) ?> 的個人檔案 - PHP Forum</title>
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
        header h1 { margin: 0; font-size: 1.5rem; }
        header h1 a { color: white; text-decoration: none; }

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

        .main-container { max-width: 600px; margin: 50px auto; padding: 0 20px; }

        .profile-card {
            background: var(--card-bg);
            padding: 40px;
            border-radius: 20px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        }

        .profile-main-img {
            width: 150px; height: 150px; border-radius: 50%; 
            object-fit: cover; border: 4px solid var(--card-bg); 
            box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-bottom: 15px;
        }

        h1.user-name { margin: 10px 0; font-size: 1.8rem; color: var(--text-color); }

        .user-tag {
            background: #764ba2; color: white; font-size: 12px;
            padding: 4px 15px; border-radius: 20px; display: inline-block;
            margin-bottom: 20px; font-weight: bold;
        }

        .bio-box {
            background-color: var(--bg-color); border-radius: 12px;
            padding: 20px; margin: 20px 0; min-height: 100px;
            border: 1px dashed var(--border-color);
        }

        .bio-text { color: var(--text-muted); font-style: italic; line-height: 1.6; margin: 0; }

        .btn-action {
            display: inline-block;
            padding: 12px 30px;
            border-radius: 30px;
            font-weight: bold;
            transition: 0.3s;
            text-decoration: none;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin: 5px;
            font-size: 1rem;
        }
        .btn-edit { background: linear-gradient(to right, #667eea, #764ba2); color: white; }
        .btn-friend-none { background: linear-gradient(to right, #667eea, #764ba2); color: white; }
        .btn-friend-pending { background: #636e72; color: white; }
        .btn-friend-accepted { background: #e74c3c; color: white; } /* 顯示刪除好友 */
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

<div class="main-container">
    <div class="profile-card">
        <?php 
            $avatar_url = !empty($user['profile_img']) 
                ? "uploads/users_profile_img/" . $user['profile_img'] 
                : "uploads/default_avatar.png"; 
        ?>
        <img src="<?= $avatar_url ?>" class="profile-main-img">

        <div><span class="user-tag">MEMBER</span></div>

        <h1 class="user-name"><?= htmlspecialchars($user['username']) ?></h1>
        
        <div class="bio-box">
            <p class="bio-text">
                <?= $user['bio'] ? nl2br(htmlspecialchars($user['bio'])) : "這傢伙很懶，什麼都沒留下..." ?>
            </p>
        </div>

        <div class="action-buttons">
            <?php if (isset($_SESSION["user_id"])): ?>
                <?php if ($_SESSION["user_id"] == $profile_id): ?>
                    <a href="edit_profile.php" class="btn-action btn-edit">⚙️ 編輯個人資料</a>
                <?php else: ?>
                    <button id="addFriendBtn" class="btn-action <?= ($friend_status == 'none') ? 'btn-friend-none' : 'btn-friend-pending' ?>" 
                        data-status="<?= $friend_status ?>">
                        <?php 
                            if ($friend_status == 'none') echo "➕ 加為好友";
                            elseif ($friend_status == 'pending') echo "⏳ 已發送申請";
                            else echo "🗑️ 刪除好友";
                        ?>
                    </button>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    // --- 深色模式 ---
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

    // --- 好友互動功能 ---
    document.getElementById('addFriendBtn')?.addEventListener('click', function() {
        const friendId = <?= $profile_id ?>;
        const currentStatus = this.getAttribute('data-status');

        // 調用後端邏輯
        fetch(`includes/friend_action.inc.php?friend_id=${friendId}&action=toggle`)
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    if (data.action === 'pending') {
                        this.innerText = '⏳ 已發送申請';
                        this.className = 'btn-action btn-friend-pending';
                        this.setAttribute('data-status', 'pending');
                    } else if (data.action === 'removed') {
                        this.innerText = '➕ 加為好友';
                        this.className = 'btn-action btn-friend-none';
                        this.setAttribute('data-status', 'none');
                    }
                } else {
                    alert(data.message);
                }
            })
            .catch(err => console.error('Error:', err));
    });
</script>

</body>
</html>