<?php
session_start();
require_once "includes/dbh.inc.php";

try {
    $sql = "SELECT posts.*, users.username, categories.name AS cat_name 
            FROM posts 
            JOIN users ON posts.user_id = users.id 
            JOIN categories ON posts.category_id = categories.id 
            ORDER BY posts.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
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
        /* 基礎風格 */
        body {
            font-family: 'Segoe UI', 'Microsoft JhengHei', sans-serif;
            background-color: #f4f7f6;
            margin: 0;
            color: #333;
        }

        /* 導覽列 */
        header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        header h1 { margin: 0; font-size: 1.5rem; }

        .nav-links a {
            color: white;
            text-decoration: none;
            margin-left: 15px;
            font-weight: 500;
            transition: 0.3s;
        }

        .nav-links a:hover { opacity: 0.8; }

        .btn-post {
            background: #ff9f43;
            padding: 8px 15px;
            border-radius: 5px;
            font-weight: bold !important;
        }

        /* 主內容區 */
        .main-container {
            max-width: 900px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .section-title {
            border-left: 5px solid #764ba2;
            padding-left: 15px;
            margin-bottom: 25px;
        }

        /* 文章卡片 */
        .post-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            transition: transform 0.2s, box-shadow 0.2s;
            position: relative;
            overflow: hidden;
        }

        .post-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
        }

        .tag {
            background: #eef2ff;
            color: #667eea;
            font-size: 12px;
            font-weight: bold;
            padding: 4px 10px;
            border-radius: 20px;
            display: inline-block;
            margin-bottom: 10px;
        }

        .post-card h3 {
            margin: 10px 0;
            font-size: 1.4rem;
            color: #2d3436;
        }

        .post-card h3 a {
            color: inherit;
            text-decoration: none;
        }

        .post-info {
            font-size: 0.85rem;
            color: #b2bec3;
            margin-bottom: 15px;
        }

        .post-content {
            color: #636e72;
            line-height: 1.6;
            margin-bottom: 15px;
        }

        .read-more {
            color: #764ba2;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .read-more:hover { text-decoration: underline; }

        /* 訪客提示 */
        .guest-box {
            background: #fff;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 20px;
            border: 1px dashed #764ba2;
        }
    </style>
</head>
<body>

    <header>
        <h1>🚀 PHP Forum</h1>
        <div class="nav-links">
            <a href="index.php">首頁</a>
            <?php if (isset($_SESSION["user_id"])): ?>
                <a href="create_post.php" class="btn-post">我要發文</a>
                <a href="logout.php">登出 (<?= htmlspecialchars($_SESSION["username"]) ?>)</a>
            <?php else: ?>
                <a href="login.php">登入</a>
                <a href="regster.php">註冊</a>
            <?php endif; ?>
        </div>
    </header>

    <div class="main-container">
        
        <?php if (!isset($_SESSION["user_id"])): ?>
            <div class="guest-box">
                👋 你好，訪客！想參與討論嗎？<a href="login.php" style="color:#764ba2; font-weight:bold;">登入</a> 後即可發文。
            </div>
        <?php endif; ?>

        <h2 class="section-title">最新探索</h2>

        <?php if (count($posts) > 0): ?>
            <?php foreach ($posts as $post): ?>
                <div class="post-card">
                    <span class="tag"><?= htmlspecialchars($post['cat_name']) ?></span>
                    <h3>
                        <a href="view_post.php?id=<?= $post['id'] ?>">
                            <?= htmlspecialchars($post['title']) ?>
                        </a>
                    </h3>
                    <div class="post-info">
                        👤 <?= htmlspecialchars($post['username']) ?> • 
                        📅 <?= date('Y-m-d', strtotime($post['created_at'])) ?>
                    </div>
                    <div class="post-content">
                        <?= htmlspecialchars(mb_substr(strip_tags($post['content']), 0, 80)) ?>...
                    </div>
                    <a href="view_post.php?id=<?= $post['id'] ?>" class="read-more">繼續閱讀 →</a>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="text-align: center; padding: 50px; color: #b2bec3;">
                <p>目前還沒有任何文章，快來搶頭香！</p>
            </div>
        <?php endif; ?>

    </div>

</body>
</html>