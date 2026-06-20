<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>重設密碼</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0;
        }

        .login-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 350px;
            text-align: center;
        }

        h2 {
            color: #333;
            margin-bottom: 25px;
            font-weight: 600;
        }

        .input-group {
            margin-bottom: 15px;
            text-align: left;
        }

        input[type="password"] {
            width: 100%;
            padding: 12px 15px;
            margin: 8px 0;
            display: inline-block;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-sizing: border-box;
            transition: 0.3s;
            outline: none;
        }

        input:focus {
            border-color: #764ba2;
            box-shadow: 0 0 8px rgba(118, 75, 162, 0.2);
        }

        button {
            width: 100%;
            background: #764ba2;
            color: white;
            padding: 12px;
            margin: 20px 0 10px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: 0.3s;
        }

        button:hover {
            background: #5a397d;
            transform: translateY(-2px);
        }

        .alert {
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 14px;
        }
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .footer-links {
            margin-top: 15px;
            font-size: 14px;
            color: #666;
        }

        .footer-links a {
            color: #764ba2;
            text-decoration: none;
        }
    </style>
</head>
<body>

<div class="login-container">
    <h2>設定新密碼</h2>

    <?php
    // 檢查是否有傳入 Token 及 Email 參數
    $token = isset($_GET['token']) ? $_GET['token'] : '';
    $email = isset($_GET['email']) ? $_GET['email'] : '';

    if (empty($token) || empty($email)) {
        echo '<div class="alert alert-error">無效的請求！連結遺失參數。</div>';
        echo '<div class="footer-links"><a href="login.php">返回登入頁面</a></div>';
        exit();
    }

    // 引入資料庫並做預先檢查
    require 'includes/dbh.inc.php';

    // 檢查 Token 是否存在、是否過期
    $hashedToken = hash("sha256", $token);
    $currentDateTime = date("Y-m-d H:i:s");

    $sql = "SELECT * FROM users WHERE email = :email AND reset_token = :token AND reset_expiry >= :current_time";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'email' => $email,
        'token' => $hashedToken,
        'current_time' => $currentDateTime
    ]);
    $user = $stmt->fetch();

    if (!$user) {
        echo '<div class="alert alert-error">此重設連結已失效、過期或不存在！請重新申請。</div>';
        echo '<div class="footer-links"><a href="forgot-password.php">重新申請重設郵件</a></div>';
        exit();
    }

    // 提示密碼不一致的錯誤
    if (isset($_GET['error'])) {
        if ($_GET['error'] == "pwdnomatch") {
            echo '<div class="alert alert-error">兩次輸入的密碼不一致！</div>';
        } elseif ($_GET['error'] == "emptyfields") {
            echo '<div class="alert alert-error">請完整填寫欄位。</div>';
        }
    }
    ?>

    <!-- 驗證通過才顯示表單 -->
    <form action="includes/reset-password.inc.php" method="POST">
        <!-- 隱藏欄位傳遞驗證資訊 -->
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
        <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">

        <div class="input-group">
            <input type="password" name="pwd" placeholder="輸入新密碼" required minlength="6">
        </div>
        <div class="input-group">
            <input type="password" name="pwd-repeat" placeholder="再次確認新密碼" required minlength="6">
        </div>
        <button type="submit" name="reset-password-submit">確認更改密碼</button>
    </form>

</div>

</body>
</html>