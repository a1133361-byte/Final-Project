<?php
session_start();
require_once "includes/dbh.inc.php";

// 1. 檢查網址有沒有傳入 id
if (!isset($_GET["id"]) || empty($_GET["id"])) {
    header("Location: index.php");
    exit();
}

$post_id = $_GET["id"];

try {
    // 2. 抓取該篇文章，並 JOIN 使用者與看板名稱
    $sql = "SELECT posts.*, users.username, categories.name AS cat_name 
            FROM posts 
            JOIN users ON posts.user_id = users.id 
            JOIN categories ON posts.category_id = categories.id 
            WHERE posts.id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$post_id]);
    $post = $stmt->fetch();

    // 3. 如果找不到文章（例如 ID 亂打）
    if (!$post) {
        die("這篇文章不存在！");
    }
} catch (PDOException $e) {
    die("讀取失敗: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($post['title']) ?></title>
    <style>
        .container { width: 80%; margin: auto; padding: 20px; border: 1px solid #ddd; }
        .meta { color: #666; font-size: 0.9em; margin-bottom: 20px; }
        .content { line-height: 1.6; font-size: 1.1em; white-space: pre-wrap; }
        .admin-tools { margin-top: 20px; padding: 10px; background: #fff3f3; border: 1px solid #ffcccc; }
    </style>
</head>
<body>

    <div class="container">
        <a href="index.php">← 回到列表</a>
        
        <h1>[<?= htmlspecialchars($post['cat_name']) ?>] <?= htmlspecialchars($post['title']) ?></h1>
        
        <div class="meta">
            作者：<strong><?= htmlspecialchars($post['username']) ?></strong> | 
            發布時間：<?= $post['created_at'] ?>
        </div>

        <div class="content">
            <?= nl2br(htmlspecialchars($post['content'])) ?>
        </div>

        <hr>

        <?php if (isset($_SESSION["role"]) && $_SESSION["role"] === 1): ?>
            <div class="admin-tools">
                <strong>管理者工具：</strong>
                <form action="includes/delete_post.inc.php" method="POST" style="display:inline;" onsubmit="return confirm('確定要刪除這篇文章嗎？');">
                    <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                    <button type="submit" name="delete" style="color:red;">刪除此文章</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
    <?php
    $comment_sql = "SELECT comments.*, users.username FROM comments 
                    JOIN users ON comments.user_id = users.id 
                    WHERE post_id = ? ORDER BY created_at ASC";
    $c_stmt = $pdo->prepare($comment_sql);
    $c_stmt->execute([$post_id]);
    $comments = $c_stmt->fetchAll();

    foreach ($comments as $c): ?>
        <div style="border-bottom: 1px solid #eee; padding: 10px 0;">
            <strong><?= htmlspecialchars($c['username']) ?></strong> 
            <small style="color: #999;"><?= $c['created_at'] ?></small>
            <p><?= nl2br(htmlspecialchars($c['content'])) ?></p>
        </div>
    <?php endforeach; ?>

    <?php if (isset($_SESSION["user_id"])): ?>
        <div style="margin-top: 30px; background: #f9f9f9; padding: 15px;">
            <h4>發表留言</h4>
            <form action="includes/comment.inc.php" method="POST">
                <input type="hidden" name="post_id" value="<?= $post_id ?>">
                <textarea name="content" rows="3" style="width: 100%;" placeholder="想說點什麼嗎？" required></textarea><br>
                <button type="submit" name="submit_comment">送出留言</button>
            </form>
        </div>
    <?php else: ?>
        <p><a href="login.php">登入</a> 後即可參與討論。</p>
    <?php endif; ?>

</body>
</html>