<?php
session_start();
require_once "includes/dbh.inc.php";

// 檢查網址有沒有帶 ID
if (!isset($_GET["id"])) {
    header("Location: index.php");
    exit();
}

$profile_id = $_GET["id"];

try {
    $sql = "SELECT * FROM users WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$profile_id]);
    $user = $stmt->fetch();

    if (!$user) { 
        die("<div style='text-align:center; padding:50px;'><h2>找不到該使用者！</h2><a href='index.php'>回首頁</a></div>"); 
    }
} catch (PDOException $e) {
    die("資料庫錯誤: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($user['username']) ?> 的個人檔案</title>
    <style>
        body {
            font-family: 'Segoe UI', 'Microsoft JhengHei', sans-serif;
            background-color: #f4f7f6;
            margin: 0;
            color: #333;
        }

        header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        header a { color: white; text-decoration: none; font-weight: bold; }

        .main-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 0 20px;
        }

        .profile-card {
            background: white;
            padding: 40px;
            border-radius: 20px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            position: relative;
        }

        /* 暫時替代圖片的圖示區 */
        .avatar-placeholder {
            width: 100px;
            height: 100px;
            background-color: #eee;
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 50px;
            color: #764ba2;
            border: 4px solid #fdfdfd;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        h1 {
            margin: 10px 0;
            font-size: 1.8rem;
            color: #2d3436;
        }

        .user-tag {
            background: #764ba2;
            color: white;
            font-size: 12px;
            padding: 3px 12px;
            border-radius: 20px;
            display: inline-block;
            margin-bottom: 20px;
        }

        .bio-box {
            background-color: #fafafa;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
            min-height: 100px;
            border: 1px dashed #ddd;
        }

        .bio-text {
            color: #636e72;
            font-style: italic;
            line-height: 1.6;
        }

        .btn-edit {
            display: inline-block;
            background: linear-gradient(to right, #667eea, #764ba2);
            color: white;
            text-decoration: none;
            padding: 12px 30px;
            border-radius: 30px;
            font-weight: bold;
            transition: 0.3s;
            box-shadow: 0 4px 15px rgba(118, 75, 162, 0.3);
            margin-top: 20px;
        }

        .btn-edit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(118, 75, 162, 0.4);
        }

        .stats {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-top: 30px;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }

        .stat-item b { font-size: 1.2rem; color: #764ba2; }
        .stat-item span { display: block; font-size: 12px; color: #999; }
    </style>
</head>
<body>

<header>
    <a href="index.php">← 返回論壇</a>
    <span>使用者檔案</span>
</header>

<div class="main-container">
    <div class="profile-card">
        
        <div class="avatar-placeholder">👤</div>

        <span class="user-tag">MEMBER</span>
        <h1><?= htmlspecialchars($user['username']) ?></h1>
        
        <div class="bio-box">
            <p class="bio-text">
                <?= $user['bio'] ? nl2br(htmlspecialchars($user['bio'])) : "這傢伙很懶，什麼都沒留下..." ?>
            </p>
        </div>

        <?php if (isset($_SESSION["user_id"]) && $_SESSION["user_id"] == $profile_id): ?>
            <a href="edit_profile.php" class="btn-edit">⚙️ 編輯個人資料</a>
        <?php endif; ?>

    </div>
</div>

</body>
</html>