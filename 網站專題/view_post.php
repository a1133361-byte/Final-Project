<?php
session_start();
require_once "includes/dbh.inc.php";

if (!isset($_GET["id"]) || empty($_GET["id"])) {
    header("Location: index.php");
    exit();
}

$post_id = $_GET["id"];

try {
    $sql = "SELECT posts.*, users.username, categories.name AS cat_name 
            FROM posts 
            JOIN users ON posts.user_id = users.id 
            JOIN categories ON posts.category_id = categories.id 
            WHERE posts.id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$post_id]);
    $post = $stmt->fetch();

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($post['title']) ?> - 論壇</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f7f6; margin: 0; line-height: 1.6; }
        header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 1rem 2rem; }
        header a { color: white; text-decoration: none; font-weight: bold; }
        
        .container { max-width: 800px; margin: 30px auto; padding: 0 20px; }
        
        /* 文章主體 */
        .post-article { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .tag { background: #eef2ff; color: #667eea; padding: 4px 12px; border-radius: 20px; font-size: 13px; font-weight: bold; }
        h1 { font-size: 2rem; margin-top: 15px; color: #2d3436; }
        .meta { font-size: 0.9rem; color: #b2bec3; margin-bottom: 30px; border-bottom: 1px solid #eee; padding-bottom: 15px; }
        .content { font-size: 1.1rem; color: #444; white-space: pre-wrap; }

        /* 留言區 */
        .comment-section { margin-top: 40px; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .comment-item { border-bottom: 1px solid #f1f1f1; padding: 15px 0; }
        .comment-item:last-child { border: none; }
        .comment-user { font-weight: bold; color: #764ba2; margin-right: 10px; }
        .comment-date { font-size: 0.8rem; color: #ccc; }
        
        /* 留言表單 */
        .comment-form textarea { width: 100%; padding: 15px; border: 1px solid #ddd; border-radius: 8px; margin-top: 10px; box-sizing: border-box; }
        .btn-submit { background: #764ba2; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; margin-top: 10px; }
    </style>
</head>
<body>

<header>
    <div style="max-width: 800px; margin: auto; display: flex; justify-content: space-between;">
        <a href="index.php">← 返回首頁</a>
    </div>
</header>

<div class="container">
    <article class="post-article">
        <span class="tag"><?= htmlspecialchars($post['cat_name']) ?></span>
        <h1><?= htmlspecialchars($post['title']) ?></h1>
        <div class="meta">
            👤 作者：<?= htmlspecialchars($post['username']) ?> | 📅 時間：<?= $post['created_at'] ?>
        </div>
        <div class="content"><?= nl2br(htmlspecialchars($post['content'])) ?></div>
    </article>

    <section class="comment-section">
        <h3>💬 留言討論</h3>
        
        <?php
        $c_sql = "SELECT comments.*, users.username FROM comments JOIN users ON comments.user_id = users.id WHERE post_id = ? ORDER BY created_at ASC";
        $c_stmt = $pdo->prepare($c_sql);
        $c_stmt->execute([$post_id]);
        $comments = $c_stmt->fetchAll();

        foreach ($comments as $c): ?>
            <div class="comment-item">
                <span class="comment-user"><?= htmlspecialchars($c['username']) ?></span>
                <span class="comment-date"><?= $c['created_at'] ?></span>
                <p style="margin: 5px 0 0;"><?= nl2br(htmlspecialchars($c['content'])) ?></p>
            </div>
        <?php endforeach; ?>

        <?php if (isset($_SESSION["user_id"])): ?>
            <div class="comment-form" style="margin-top: 30px;">
                <form action="includes/comment.inc.php" method="POST">
                    <input type="hidden" name="post_id" value="<?= $post_id ?>">
                    <textarea name="content" rows="3" placeholder="我也想說幾句話..." required></textarea>
                    <button type="submit" name="submit_comment" class="btn-submit">發表留言</button>
                </form>
            </div>
        <?php endif; ?>
    </section>
</div>

</body>
</html>