<?php
session_start();
// 1. 安全檢查：沒登入不能進來
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php?error=please_login");
    exit();
}

require_once "includes/dbh.inc.php";

// 2. 抓取所有看板分類
$sql = "SELECT * FROM categories ORDER BY id ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$categories = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>發表新文章 - PHP 論壇</title>
    <style>
        body {
            font-family: 'Segoe UI', 'Microsoft JhengHei', sans-serif;
            background-color: #f4f7f6;
            margin: 0;
            color: #333;
        }

        /* 頂部導覽列風格統一 */
        header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        header a {
            color: white;
            text-decoration: none;
            font-weight: bold;
            float: left; /* 讓返回連結在左邊 */
        }

        .main-container {
            max-width: 700px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .post-form-card {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
        }

        h2 {
            margin-top: 0;
            color: #2d3436;
            font-size: 1.5rem;
            text-align: center;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
        }

        /* 輸入框通用樣式 */
        select, input[type="text"], textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-sizing: border-box;
            font-size: 16px;
            transition: 0.3s;
            outline: none;
            background-color: #fafafa;
        }

        select:focus, input[type="text"]:focus, textarea:focus {
            border-color: #764ba2;
            background-color: #fff;
            box-shadow: 0 0 8px rgba(118, 75, 162, 0.1);
        }

        textarea {
            resize: vertical; /* 只允許垂直拉伸 */
            min-height: 200px;
        }

        /* 按鈕區塊 */
        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .btn-submit {
            flex: 2;
            background: linear-gradient(to right, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 14px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
        }

        .btn-submit:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }

        .btn-cancel {
            flex: 1;
            background-color: #eee;
            color: #666;
            text-align: center;
            text-decoration: none;
            padding: 14px;
            border-radius: 8px;
            font-size: 16px;
            transition: 0.3s;
        }

        .btn-cancel:hover {
            background-color: #ddd;
        }
    </style>
</head>
<body>

    <header>
        <div style="max-width: 900px; margin: auto;">
            <a href="index.php">← 返回</a>
            <span>撰寫文章</span>
        </div>
    </header>

    <div class="main-container">
        <div class="post-form-card">
            <h2>✍️ 分享你的想法</h2>
            
            <form action="includes/post.inc.php" method="POST">
                <div class="form-group">
                    <label>選擇看板</label>
                    <select name="category_id" required>
                        <option value="" disabled selected>-- 請選擇一個看板 --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>文章標題</label>
                    <input type="text" name="title" placeholder="標題越吸引人，點閱率越高喔！" required>
                </div>

                <div class="form-group">
                    <label>內容</label>
                    <textarea name="content" placeholder="在這裡輸入你的精彩內容..." required></textarea>
                </div>

                <div class="button-group">
                    <a href="index.php" class="btn-cancel">取消</a>
                    <button type="submit" name="submit_post" class="btn-submit">發布文章</button>
                </div>
            </form>
        </div>
    </div>

</body>
</html>
