<?php
session_start();
require_once "includes/dbh.inc.php";

// 權限檢查
if (!isset($_SESSION['role']) || $_SESSION['role'] != 1) {
    header("Location: index.php");
    exit();
}

$msg = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $user_id = $_SESSION['user_id'];

    try {
        // 1. 尋找或確保有「系統公告」看板
        $cat_stmt = $pdo->prepare("SELECT id FROM categories WHERE name = '系統公告' LIMIT 1");
        $cat_stmt->execute();
        $cat = $cat_stmt->fetch();
        
        if (!$cat) {
            $pdo->prepare("INSERT INTO categories (name) VALUES ('系統公告')")->execute();
            $category_id = $pdo->lastInsertId();
        } else {
            $category_id = $cat['id'];
        }

        // 2. 插入公告文章
        $sql = "INSERT INTO posts (user_id, category_id, title, content, created_at) VALUES (?, ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, $category_id, $title, $content]);

        $msg = "公告已成功發布！";
    } catch (PDOException $e) {
        $msg = "發布失敗: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>發布系統公告 - 管理員專區</title>
    <style>
        body { font-family: sans-serif; background: #f8fafc; padding: 40px; color: #334155; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 15px; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); }
        h1 { font-size: 1.5rem; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], textarea { width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 8px; box-sizing: border-box; }
        textarea { height: 200px; }
        button { background: #f59e0b; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: bold; }
        .back { display: inline-block; margin-top: 20px; color: #64748b; text-decoration: none; font-size: 0.9rem; }
        .alert { padding: 10px; background: #dcfce7; color: #166534; border-radius: 8px; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📢 發布系統公告</h1>
        <?php if($msg): ?><div class="alert"><?= $msg ?></div><?php endif; ?>
        <form action="" method="POST">
            <div class="form-group">
                <label>公告標題</label>
                <input type="text" name="title" required placeholder="例如：伺服器維護通知">
            </div>
            <div class="form-group">
                <label>公告內容</label>
                <textarea name="content" required placeholder="請輸入詳細公告內容..."></textarea>
            </div>
            <button type="submit">確認發布</button>
        </form>
        <a href="index.php" class="back">← 返回首頁</a>
    </div>
</body>
</html>