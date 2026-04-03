<?php
session_start();
require_once "includes/dbh.inc.php";

// 1. 抓取所有文章（重點：使用 JOIN 同時抓取作者名與看板名）
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
    <title>我的 PHP 論壇</title>
    <style>
        .post-card { border: 1px solid #ccc; margin: 10px 0; padding: 10px; }
        .post-info { font-size: 0.8em; color: #666; }
        .tag { background: #eee; padding: 2px 5px; border-radius: 3px; }
    </style>
</head>
<body>

    <h1>🚀 歡迎來到我的論壇</h1>

    <nav>
        <?php if (isset($_SESSION["user_id"])): ?>
            <p>目前登入：<strong><?= htmlspecialchars($_SESSION["username"]) ?></strong></p>
            <a href="create_post.php" style="background: #28a745; color: white; padding: 5px 10px; text-decoration: none;">我要發文</a>
            <a href="logout.php">登出</a>
        <?php else: ?>
            <p>目前為訪客狀態</p>
            <a href="login.php">登入</a> | <a href="signup.php">註冊帳號</a>
        <?php endif; ?>
    </nav>

    <hr>

    <h2>最新文章</h2>
    <?php if (count($posts) > 0): ?>
        <?php foreach ($posts as $post): ?>
            <div class="post-card">
                <span class="tag"><?= htmlspecialchars($post['cat_name']) ?></span>
                <h3><?= htmlspecialchars($post['title']) ?></h3>
                <div class="post-info">
                    作者：<?= htmlspecialchars($post['username']) ?> | 
                    發布時間：<?= $post['created_at'] ?>
                </div>
                <p><?= nl2br(htmlspecialchars(mb_substr($post['content'], 0, 100))) ?>...</p>
                <a href="view_post.php?id=<?= $post['id'] ?>">閱讀更多</a>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p>目前還沒有任何文章，快來搶頭香！</p>
    <?php endif; ?>

</body>
</html>