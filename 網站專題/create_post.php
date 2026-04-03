<?php
session_start();
// 1. 安全檢查：沒登入不能進來
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php?error=please_login");
    exit();
}

require_once "includes/dbh.inc.php";

// 2. 抓取所有看板分類，供下拉選單使用
$sql = "SELECT * FROM categories ORDER BY id ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$categories = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>發表新文章</title>
</head>
<body>
    <h1>發表新文章</h1>
    <form action="includes/post.inc.php" method="POST">
        <div>
            <label>選擇看板：</label>
            <select name="category_id" required>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <br>
        <div>
            <input type="text" name="title" placeholder="請輸入文章標題" style="width: 300px;" required>
        </div>
        <br>
        <div>
            <textarea name="content" placeholder="內容想寫什麼呢？" rows="10" style="width: 300px;" required></textarea>
        </div>
        <br>
        <button type="submit" name="submit_post">發布文章</button>
        <a href="index.php">取消</a>
    </form>
</body>
</html>
