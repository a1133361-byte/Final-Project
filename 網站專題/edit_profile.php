<?php
session_start();
require_once "includes/dbh.inc.php";

// 安全檢查：沒登入不能進來
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

// 抓取目前的資料
$uid = $_SESSION["user_id"];
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$uid]);
$user = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>編輯個人檔案 - PHP 論壇</title>
    <style>
        /* 基礎風格設定 */
        body {
            font-family: 'Segoe UI', 'Microsoft JhengHei', sans-serif;
            background-color: #f4f7f6;
            margin: 0;
            color: #333;
        }

        /* 直接寫入導覽列樣式，不依賴外部 include */
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
        header a { color: white; text-decoration: none; font-weight: bold; }

        .main-container {
            max-width: 600px;
            margin: 40px auto;
            padding: 0 20px;
        }

        /* 表單卡片設計 */
        .post-form-card {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
        }

        h2 {
            margin-top: 0;
            color: #2d3436;
            font-size: 1.6rem;
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

        /* 輸入框美化 */
        input[type="text"], textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #e1e1e1;
            border-radius: 8px;
            box-sizing: border-box;
            font-size: 16px;
            transition: all 0.3s;
            background-color: #fafafa;
            outline: none;
        }

        /* 唯讀狀態（使用者名稱）的樣式 */
        input[disabled] {
            background-color: #f0f0f0;
            color: #999;
            cursor: not-allowed;
            border-style: dashed;
        }

        textarea {
            resize: vertical;
            min-height: 150px;
            line-height: 1.6;
        }

        input:focus, textarea:focus {
            border-color: #764ba2;
            background-color: #fff;
            box-shadow: 0 0 0 4px rgba(118, 75, 162, 0.1);
        }

        small {
            display: block;
            color: #999;
            margin-top: 5px;
            font-size: 12px;
        }

        /* 按鈕群組 */
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
            box-shadow: 0 4px 15px rgba(118, 75, 162, 0.3);
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            opacity: 0.95;
            box-shadow: 0 6px 20px rgba(118, 75, 162, 0.4);
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
            background-color: #e0e0e0;
        }
    </style>
</head>
<body>

    <header>
        <h1>🚀 PHP Forum</h1>
        <a href="profile.php?id=<?= $uid ?>">← 返回檔案</a>
    </header>

    <div class="main-container">
        <div class="post-form-card">
            <h2>👤 編輯個人資料</h2>
            
            <form action="includes/update_profile.inc.php" method="POST">
                
                <div class="form-group">
                    <label>使用者名稱</label>
                    <input type="text" value="<?= htmlspecialchars($user['username']) ?>" disabled>
                    <small>⚠️ 為了安全起見，使用者名稱不可修改。</small>
                </div>

                <div class="form-group">
                    <label>個人簡介 (Bio)</label>
                    <textarea name="bio" placeholder="跟大家介紹一下你自己吧..."><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                </div>

                <div class="button-group">
                    <a href="profile.php?id=<?= $uid ?>" class="btn-cancel">取消</a>
                    <button type="submit" name="submit_profile" class="btn-submit">儲存修改</button>
                </div>

            </form>
        </div>
    </div>

</body>
</html>