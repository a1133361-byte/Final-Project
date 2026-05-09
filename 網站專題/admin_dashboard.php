<?php
session_start();
require_once "includes/dbh.inc.php";

// 權限檢查：非管理員禁止進入
if (!isset($_SESSION['role']) || $_SESSION['role'] != 1) {
    header("Location: index.php");
    exit();
}

try {
    // 1. 獲取核心統計數據
    $stats = [];
    
    // 總使用者
    $stats['users'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    // 總文章數
    $stats['posts'] = $pdo->query("SELECT COUNT(*) FROM posts")->fetchColumn();
    // 總留言數
    $stats['comments'] = $pdo->query("SELECT COUNT(*) FROM comments")->fetchColumn();
    // 今日新增文章
    $stats['today_posts'] = $pdo->query("SELECT COUNT(*) FROM posts WHERE DATE(created_at) = CURDATE()")->fetchColumn();

    // 2. 看板文章分佈 (用於簡單分析)
    $cat_dist = $pdo->query("
        SELECT c.name, COUNT(p.id) as post_count 
        FROM categories c 
        LEFT JOIN posts p ON c.id = p.category_id 
        GROUP BY c.id 
        ORDER BY post_count DESC
    ")->fetchAll();

    // 3. 最近活躍用戶
    $recent_users = $pdo->query("SELECT username, profile_img, created_at FROM users ORDER BY id DESC LIMIT 5")->fetchAll();

} catch (PDOException $e) {
    die("數據讀取失敗：" . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>數據分析 - 管理後台</title>
    <style>
        :root {
            --bg-color: #f8fafc;
            --card-bg: #ffffff;
            --text-color: #0f172a;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --accent-color: #6366f1;
            --admin-color: #f59e0b;
            --sidebar-item-hover: #f1f5f9;
        }

        [data-theme="dark"] {
            --bg-color: #0f172a;
            --card-bg: #1e293b;
            --text-color: #f1f5f9;
            --text-muted: #94a3b8;
            --border-color: #334155;
            --sidebar-item-hover: #334155;
        }

        body { 
            font-family: 'Inter', system-ui, sans-serif; 
            background-color: var(--bg-color); 
            color: var(--text-color); 
            margin: 0; 
            padding: 0;
            transition: 0.3s;
        }

        .admin-header {
            background: var(--card-bg);
            border-bottom: 1px solid var(--border-color);
            padding: 15px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .back-link {
            text-decoration: none;
            color: var(--text-muted);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 20px;
            transition: 0.2s;
        }
        .back-link:hover { color: var(--accent-color); }

        /* KPI 網格 */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--card-bg);
            padding: 25px;
            border-radius: 20px;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .stat-label { color: var(--text-muted); font-size: 0.9rem; font-weight: 600; }
        .stat-value { font-size: 2rem; font-weight: 800; margin: 10px 0; color: var(--admin-color); }
        .stat-trend { font-size: 0.8rem; color: #10b981; font-weight: 700; }

        /* 主內容區佈局 */
        .dashboard-main {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
        }

        .chart-section, .list-section {
            background: var(--card-bg);
            padding: 25px;
            border-radius: 20px;
            border: 1px solid var(--border-color);
        }

        h3 { margin-top: 0; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }

        /* 簡單的長條圖樣式 */
        .bar-container { margin-bottom: 15px; }
        .bar-label { display: flex; justify-content: space-between; font-size: 0.9rem; margin-bottom: 5px; }
        .bar-bg { background: var(--bg-color); height: 8px; border-radius: 10px; overflow: hidden; }
        .bar-fill { background: var(--admin-color); height: 100%; border-radius: 10px; }

        /* 用戶列表樣式 */
        .user-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid var(--border-color);
        }
        .user-item:last-child { border-bottom: none; }
        .user-avatar { width: 35px; height: 35px; border-radius: 50%; object-fit: cover; }

        @media (max-width: 900px) {
            .dashboard-main { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body data-theme="light">

<header class="admin-header">
    <div style="display:flex; align-items:center; gap:10px;">
        <span style="font-size:1.5rem;">📊</span>
        <h1 style="margin:0; font-size:1.2rem; font-weight:800;">管理後台數據中心</h1>
    </div>
    <button id="themeBtn" style="background:none; border:none; cursor:pointer; font-size:1.2rem;">🌓</button>
</header>

<div class="container">
    <a href="index.php" class="back-link">⬅️ 返回論壇首頁</a>

    <!-- KPI 數據概覽 -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">👤 總註冊用戶</div>
            <div class="stat-value"><?= number_format($stats['users']) ?></div>
            <div class="stat-trend">持續增長中</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">📝 總文章數量</div>
            <div class="stat-value"><?= number_format($stats['posts']) ?></div>
            <div class="stat-trend">社群活力來源</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">💬 累計互動留言</div>
            <div class="stat-value"><?= number_format($stats['comments']) ?></div>
            <div class="stat-trend">高黏著度表現</div>
        </div>
        <div class="stat-card" style="border-color: var(--admin-color);">
            <div class="stat-label" style="color: var(--admin-color);">🔥 今日新增文章</div>
            <div class="stat-value"><?= $stats['today_posts'] ?></div>
            <div class="stat-trend">即時熱度指標</div>
        </div>
    </div>

    <div class="dashboard-main">
        <!-- 看板活躍度分析 -->
        <div class="chart-section">
            <h3>📈 看板熱度排行</h3>
            <?php 
            $max_posts = count($cat_dist) > 0 ? max(array_column($cat_dist, 'post_count')) : 1;
            foreach ($cat_dist as $cat): 
                $percentage = ($cat['post_count'] / $max_posts) * 100;
            ?>
                <div class="bar-container">
                    <div class="bar-label">
                        <span># <?= htmlspecialchars($cat['name']) ?></span>
                        <span style="font-weight: 700;"><?= $cat['post_count'] ?> 篇</span>
                    </div>
                    <div class="bar-bg">
                        <div class="bar-fill" style="width: <?= $percentage ?>%;"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- 最新加入成員 -->
        <div class="list-section">
            <h3>✨ 最新成員</h3>
            <?php foreach ($recent_users as $user): ?>
                <div class="user-item">
                    <img src="<?= !empty($user['profile_img']) ? "uploads/users_profile_img/".$user['profile_img'] : "uploads/default_avatar.png" ?>" class="user-avatar">
                    <div style="flex:1;">
                        <div style="font-weight:700; font-size:0.9rem;"><?= htmlspecialchars($user['username']) ?></div>
                        <div style="font-size:0.75rem; color:var(--text-muted);"><?= date('Y-m-d', strtotime($user['created_at'])) ?> 加入</div>
                    </div>
                </div>
            <?php endforeach; ?>
            <a href="admin_users.php" style="display:block; text-align:center; margin-top:20px; font-size:0.8rem; color:var(--accent-color); text-decoration:none; font-weight:700;">查看完整用戶列表 →</a>
        </div>
    </div>
</div>

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