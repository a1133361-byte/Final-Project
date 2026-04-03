<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>加入論壇 - 註冊</title>
    <style>
        /* 保持與登入頁一致的背景與基礎設定 */
        body {
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0;
        }

        .signup-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }

        h2 {
            color: #333;
            margin-bottom: 10px;
            font-weight: 700;
        }

        p.subtitle {
            color: #777;
            font-size: 14px;
            margin-bottom: 25px;
        }

        .input-group {
            margin-bottom: 15px;
            text-align: left;
        }

        .input-group label {
            display: block;
            font-size: 13px;
            color: #555;
            margin-bottom: 5px;
            margin-left: 2px;
        }

        input[type="text"],
        input[type="password"],
        input[type="email"] {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #e1e1e1;
            border-radius: 8px;
            box-sizing: border-box;
            transition: all 0.3s ease;
            font-size: 15px;
            background-color: #f8f9fa;
        }

        /* 聚焦時的華麗效果 */
        input:focus {
            border-color: #667eea;
            background-color: #fff;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
            outline: none;
        }

        button {
            width: 100%;
            background: linear-gradient(to right, #667eea, #764ba2);
            color: white;
            padding: 14px;
            margin: 20px 0 10px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            letter-spacing: 1px;
            transition: 0.3s;
            box-shadow: 0 4px 15px rgba(118, 75, 162, 0.3);
        }

        button:hover {
            opacity: 0.9;
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(118, 75, 162, 0.4);
        }

        .footer-links {
            margin-top: 20px;
            font-size: 14px;
            color: #666;
        }

        .footer-links a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }

        .footer-links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

<div class="signup-container">
    <h2>建立新帳號</h2>
    <p class="subtitle">加入我們的社群，開始分享你的想法</p>
    
    <form action="includes/signup.inc.php" method="POST">
        <div class="input-group">
            <label>使用者名稱</label>
            <input type="text" name="username" required>
        </div>
        
        <div class="input-group">
            <label>電子信箱</label>
            <input type="email" name="email" required>
        </div>
        
        <div class="input-group">
            <label>設定密碼</label>
            <input type="password" name="pwd" required>
        </div>
        
        <button type="submit" name="submit">立即註冊</button>
    </form>

    <div class="footer-links">
        已經有帳號了？ <a href="login.php">登入</a><br>
        <a href="index.php" style="color: #999; font-weight: 400; display: inline-block; margin-top: 10px;">返回首頁</a>
    </div>
</div>

</body>
</html>