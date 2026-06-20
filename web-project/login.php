<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登入論壇</title>
    <style>
        /* 基礎設定 */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0;
        }

        /* 登入外框 */
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

        /* 輸入框樣式 */
        .input-group {
            margin-bottom: 15px;
            text-align: left;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px 15px;
            margin: 8px 0;
            display: inline-block;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-sizing: border-box; /* 確保 padding 不會撐破外框 */
            transition: 0.3s;
            outline: none;
        }

        input:focus {
            border-color: #764ba2;
            box-shadow: 0 0 8px rgba(118, 75, 162, 0.2);
        }

        /* 按鈕樣式 */
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

        /* 底部連結 */
        .footer-links {
            margin-top: 15px;
            font-size: 14px;
            color: #666;
            line-height: 1.8;
        }

        .footer-links a {
            color: #764ba2;
            text-decoration: none;
        }

        .footer-links a:hover {
            text-decoration: underline;
        }
        
        /* 狀態訊息提示 */
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
    <h2>歡迎回來</h2>
    
    <!-- 顯示錯誤或成功訊息 -->
    <?php
    if (isset($_GET['newpwd'])) {
        if ($_GET['newpwd'] == "passwordupdated") {
            echo '<div class="alert alert-success">密碼重設成功，請使用新密碼登入！</div>';
        }
    }
    if (isset($_GET['error'])) {
        if ($_GET['error'] == "wronglogin") {
            echo '<div class="alert alert-error">帳號或密碼錯誤！</div>';
        }
    }
    ?>

    <form action="includes/login.inc.php" method="POST">
        <div class="input-group">
            <input type="text" name="username" placeholder="帳號" required>
        </div>
        <div class="input-group">
            <input type="password" name="pwd" placeholder="密碼" required>
        </div>
        <button type="submit" name="submit">立即登入</button>
    </form>

    <div class="footer-links">
        <a href="forgot-password.php">忘記密碼？</a><br>
        還沒有帳號？ <a href="regster.php">按此註冊</a><br>
        <a href="index.php">返回首頁</a>
    </div>
    
</div>

</body>
</html>