<?php
session_start();
require_once "includes/dbh.inc.php";

try {
    // 獲取所有屬於「系統公告」類別的文章
    $sql = "SELECT posts.*, users.username FROM posts 
            JOIN categories ON posts.category_id = categories.id 
            JOIN users ON posts.user_id = users.id
            WHERE categories.name = '系統公告' 
            ORDER BY posts.created_at DESC";
    $stmt = $pdo->query($sql);
    $announcements = $stmt->fetchAll();
} catch (PDOException $e) {
    die("讀取公告失敗");
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>系統公告 - PHP Forum</title>
    <style>
        body { font-family: sans-serif; background: #f8fafc; padding: 40px; }
        .container { max-width: 800px; margin: 0 auto; }
        .card { background: white; padding: 25px; border-radius: 15px; margin-bottom: 20px; border-left: 5px solid #f59e0b; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        h1 { margin-bottom: 30px; }
        h2 { margin: 0 0 10px 0; color: #1e293b; }
        .meta { font-size: 0.85rem; color: #64748b; margin-bottom: 15px; }
        .content { line-height: 1.6; color: #334155; }
        .back { display: inline-block; margin-bottom: 20px; color: #6366f1; text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="back">← 返回探索牆</a>
        <h1>📢 系統公告通知</h1>
        <?php if(count($announcements) > 0): ?>
            <?php foreach($announcements as $a): ?>
                <div class="card">
                    <h2><?= htmlspecialchars($a['title']) ?></h2>
                    <div class="meta">
                        發布者：<?= htmlspecialchars($a['username']) ?> | 
                        時間：<?= date('Y-m-d H:i', strtotime($a['created_at'])) ?>
                    </div>
                    <div class="content">
                        <?= nl2br(htmlspecialchars($a['content'])) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>目前沒有任何系統公告。</p>
        <?php endif; ?>
    </div>
</body>
</html>