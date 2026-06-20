<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>忘記密碼</title>
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
            margin-bottom: 15px;
            font-weight: 600;
        }

        p {
            color: #666;
            font-size: 14px;
            margin-bottom: 25px;
            line-height: 1.5;
        }

        .input-group {
            margin-bottom: 15px;
            text-align: left;
        }

        input[type="email"] {
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

        .footer-links {
            margin-top: 15px;
            font-size: 14px;
            color: #666;
        }

        .footer-links a {
            color: #764ba2;
            text-decoration: none;
        }

        .footer-links a:hover {
            text-decoration: underline;
        }

        .alert {
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 14px;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>

<div class="login-container">
    <h2>找回密碼</h2>
    <p>請輸入您註冊時所使用的電子郵件，我們將向您發送一封重設密碼的郵件。</p>

    <!-- 提示狀態訊息 -->
    <?php
    if (isset($_GET['reset'])) {
        if ($_GET['reset'] == "success") {
            echo '<div class="alert alert-success">重設郵件已成功寄出！請至您的信箱收信。</div>';
        } elseif ($_GET['reset'] == "empty") {
            echo '<div class="alert alert-error">請填寫電子郵件欄位。</div>';
        } elseif ($_GET['reset'] == "emailnotfound") {
            echo '<div class="alert alert-error">找不到該電子郵件註冊的帳號。</div>';
        } elseif ($_GET['reset'] == "failed") {
            echo '<div class="alert alert-error">郵件寄送失敗，請稍後再試。</div>';
        }
    }
    ?>

    <form action="includes/forgot-password.inc.php" method="POST">
        <div class="input-group">
            <input type="email" name="email" placeholder="電子郵件" required>
        </div>
        <button type="submit" name="forgot-submit">發送重設郵件</button>
    </form>

    <div class="footer-links">
        <a href="login.php">返回登入</a>
    </div>
</div>

</body>
</html>