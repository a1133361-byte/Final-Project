<?php
session_start();
require_once "includes/dbh.inc.php";

$u_search = isset($_GET['u_search']) ? trim($_GET['u_search']) : '';
$current_user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] == 1;

$users_results = [];

try {
    if ($u_search !== '') {
        // 搜尋用戶，並排除自己（選用），同時可以關聯一些好友狀態（若有需要可擴充）
        $sql = "SELECT id, username, profile_img, bio FROM users WHERE username LIKE :search AND id != :my_id LIMIT 50";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':search', '%' . $u_search . '%');
        $stmt->bindValue(':my_id', $current_user_id);
        $stmt->execute();
        $users_results = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    die("搜尋失敗: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>搜尋用戶: <?= htmlspecialchars($u_search) ?> - PHP Forum</title>
    <style>
        /* 延用 index.php 的變數設定 */
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
        }

        body { 
            font-family: 'Inter', system-ui, sans-serif; 
            background-color: var(--bg-color); 
            margin: 0; 
            color: var(--text-color); 
            transition: background-color 0.3s, color 0.3s; 
        }

        /* 導航欄樣式一致 */
        header { 
            background: var(--nav-bg); 
            backdrop-filter: blur(10px); 
            border-bottom: 1px solid var(--border-color); 
            position: sticky; top: 0; z-index: 1000; padding: 12px 0; 
        }
        .nav-container { max-width: 1200px; margin: 0 auto; padding: 0 25px; display: flex; justify-content: space-between; align-items: center; }
        .logo h1 { margin: 0; font-size: 1.4rem; font-weight: 800; background: var(--header-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }

        .main-content { max-width: 900px; margin: 40px auto; padding: 0 20px; }

        .search-header { margin-bottom: 30px; }
        .search-header h2 { font-size: 1.8rem; font-weight: 800; margin-bottom: 10px; }

        /* 用戶卡片設計 */
        .user-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; }
        .user-card { 
            background: var(--card-bg); 
            border-radius: 24px; 
            padding: 25px; 
            border: 1px solid var(--border-color); 
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            text-align: center;
            transition: 0.3s;
        }
        .user-card:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(0,0,0,0.08); border-color: var(--accent-color); }

        .user-avatar { 
            width: 90px; height: 90px; border-radius: 50%; object-fit: cover; 
            margin-bottom: 15px; border: 4px solid var(--bg-color); box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        .user-info h3 { margin: 0; font-size: 1.2rem; font-weight: 800; }
        .user-info p { color: var(--text-muted); font-size: 0.9rem; margin: 10px 0 20px 0; line-height: 1.5; height: 3em; overflow: hidden; }

        .action-btns { display: flex; gap: 10px; width: 100%; }
        .btn { 
            flex: 1; padding: 10px; border-radius: 12px; font-weight: 700; font-size: 0.85rem; 
            text-decoration: none; text-align: center; cursor: pointer; transition: 0.2s; border: none;
        }
        .btn-primary { background: var(--accent-color); color: white; }
        .btn-primary:hover { opacity: 0.9; transform: scale(1.02); }
        .btn-secondary { background: var(--sidebar-item-hover); color: var(--text-color); }
        .btn-secondary:hover { background: var(--border-color); }

        .empty-state { text-align: center; padding: 60px 20px; background: var(--card-bg); border-radius: 30px; border: 1px dashed var(--border-color); }
        .empty-state h3 { font-size: 1.5rem; color: var(--text-muted); }

        .back-link { display: inline-flex; align-items: center; gap: 8px; color: var(--accent-color); text-decoration: none; font-weight: 700; margin-bottom: 20px; }
        
        /* 下拉選單跟主題按鈕延用 index.php 的 CSS... (此處簡略以保持檔案精簡) */
        .user-trigger { display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 5px 12px; border-radius: 50px; }
    </style>
</head>
<body data-theme="light">

<header>
    <div class="nav-container">
        <a href="index.php" class="logo" style="text-decoration:none"><h1>🚀 PHP Forum</h1></a>
        <div style="display:flex; align-items:center; gap:15px;">
            <button id="themeBtn" style="background:none; border:none; cursor:pointer; font-size:1.3rem;">🌓</button>
            <?php if (isset($_SESSION["user_id"])): ?>
                <div class="user-trigger">
                    <img src="<?= !empty($_SESSION['profile_img']) ? "uploads/users_profile_img/".$_SESSION['profile_img'] : "uploads/default_avatar.png" ?>" style="width:32px; height:32px; border-radius:50%; object-fit:cover;">
                    <span style="font-weight:700;"><?= htmlspecialchars($_SESSION["username"]) ?></span>
                </div>
            <?php endif; ?>
        </div>
    </div>
</header>

<main class="main-content">
    <a href="index.php" class="back-link">⬅ 返回探索牆</a>

    <div class="search-header">
        <h2>🔍 搜尋用戶結果</h2>
        <p style="color: var(--text-muted);">正在搜尋關於 「<strong><?= htmlspecialchars($u_search) ?></strong>」 的結果...</p>
    </div>

    <?php if (count($users_results) > 0): ?>
        <div class="user-grid">
            <?php foreach ($users_results as $user): ?>
                <div class="user-card">
                    <img src="<?= !empty($user['profile_img']) ? "uploads/users_profile_img/".$user['profile_img'] : "uploads/default_avatar.png" ?>" class="user-avatar">
                    <div class="user-info">
                        <h3><?= htmlspecialchars($user['username']) ?></h3>
                        <p><?= !empty($user['bio']) ? htmlspecialchars(mb_substr($user['bio'], 0, 45)) . '...' : '這傢伙很懶，什麼都沒寫。' ?></p>
                    </div>
                    <div class="action-btns">
                        <a href="profile.php?id=<?= $user['id'] ?>" class="btn btn-secondary">查看資料</a>
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <a href="add_friend.php?id=<?= $user['id'] ?>" class="btn btn-primary">加好友</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <h3>🛸 哎呀！找不到該用戶</h3>
            <p style="color: var(--text-muted);">試試搜尋其他名字，或者檢查一下拼字是否正確。</p>
            <form action="search_users.php" method="GET" style="margin-top:20px; display:flex; justify-content:center; gap:10px;">
                <input type="text" name="u_search" placeholder="再次搜尋..." style="padding:12px 20px; border-radius:12px; border:1px solid var(--border-color); width:250px; outline:none;" required>
                <button type="submit" style="background:var(--accent-color); color:white; border:none; padding:10px 25px; border-radius:12px; font-weight:700; cursor:pointer;">搜尋</button>
            </form>
        </div>
    <?php endif; ?>
</main>

<script>
    // 主題切換邏輯
    const themeBtn = document.getElementById('themeBtn');
    const currentTheme = localStorage.getItem('theme') || 'light';
    document.body.setAttribute('data-theme', currentTheme);

    themeBtn.onclick = () => {
        const targetTheme = document.body.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        document.body.setAttribute('data-theme', targetTheme);
        localStorage.setItem('theme', targetTheme);
    };
</script>
</body>
</html>