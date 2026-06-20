<?php
// 1. 將 use 關鍵字放在檔案最頂層（大括號外部），避免語法錯誤
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (isset($_POST['forgot-submit'])) {
    
    // 2. 引入資料庫連線
    require 'dbh.inc.php';

    // 3. 引入 PHPMailer 檔案 (相對路徑 ../ 往上一層到專案根目錄)
    require '../PHPMailer/src/Exception.php';
    require '../PHPMailer/src/PHPMailer.php';
    require '../PHPMailer/src/SMTP.php';

    // ==========================================
    // 【環境變數導向：指向上一層資料夾的 .env】
    // ==========================================
    $envPath = __DIR__ . '/../.env'; // __DIR__ 加上 /../ 就會切換到上一層資料夾
    if (file_exists($envPath)) {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            // 忽略註解行
            if (strpos(trim($line), '#') === 0) continue;
            
            // 透過第一個等號分割鍵與值
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);
                
                // 為了防範安全引號，去掉前後可能包覆的單雙引號
                $value = trim($value, "\"'");
                
                if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                    putenv(sprintf('%s=%s', $name, $value));
                    $_ENV[$name] = $value;
                    $_SERVER[$name] = $value;
                }
            }
        }
    }

    $userEmail = trim($_POST['email']);

    if (empty($userEmail)) {
        header("Location: ../forgot-password.php?reset=empty");
        exit();
    }

    // 4. 檢查資料庫是否有該 Email 存在
    $sql = "SELECT * FROM users WHERE email = :email";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['email' => $userEmail]);
    $user = $stmt->fetch();

    if (!$user) {
        header("Location: ../forgot-password.php?reset=emailnotfound");
        exit();
    }

    // 5. 產生安全的隨機 Token
    $selector = bin2hex(random_bytes(8)); 
    $token = bin2hex(random_bytes(32));   

    $combinedToken = $selector . $token;
    $expires = date("Y-m-d H:i:s", strtotime('+30 minutes'));
    $hashedToken = hash("sha256", $combinedToken);

    $sql = "UPDATE users SET reset_token = :token, reset_expiry = :expiry WHERE email = :email";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'token' => $hashedToken,
        'expiry' => $expires,
        'email' => $userEmail
    ]);

    // 7. 設定發送連結
    $actualLink = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/../reset-password.php?token=" . $combinedToken . "&email=" . urlencode($userEmail);

    // 8. 使用 PHPMailer 寄送郵件
    $mail = new PHPMailer(true);
    $debugMode = false; 

    try {
        // --- SMTP 伺服器設定 ---
        if ($debugMode) {
            $mail->SMTPDebug = 2;                                   
        } else {
            $mail->SMTPDebug = 0;                                   
        }

        $mail->isSMTP();                                            
        $mail->Host       = 'smtp.gmail.com';                       
        $mail->SMTPAuth   = true;                                   
        
        // ==========================================
        // 【從上一層 .env 中安全讀取認證資訊】
        // ==========================================
        $mail->Username   = getenv('SMTP_USERNAME'); 
        $mail->Password   = getenv('SMTP_PASSWORD');                           
        
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         
        $mail->Port       = 587;                                    
        $mail->CharSet    = 'UTF-8';                                

        // --- 收發件人設定 ---
        $mail->setFrom($mail->Username, '論壇系統管理員');
        $mail->addAddress($userEmail);                              

        // --- 郵件內容 ---
        $mail->isHTML(true);                                        
        $mail->Subject = '【論壇系統】重設您的帳號密碼';
        
        $mailContent = '
        <div style="font-family: Arial, sans-serif; padding: 20px; border: 1px solid #ddd; border-radius: 10px; max-width: 500px; margin: 0 auto;">
            <h2 style="color: #764ba2; text-align: center;">重設密碼請求</h2>
            <p>您好：</p>
            <p>我們收到了您要求重設密碼的申請。請點擊下方的按鈕來設定您的新密碼：</p>
            <div style="text-align: center; margin: 30px 0;">
                <a href="' . $actualLink . '" style="background-color: #764ba2; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;">立即重設密碼</a>
            </div>
            <p style="color: #666; font-size: 14px;">此重設連結將在 <strong>30 分鐘內有效</strong>。如果您並未發出此請求，請忽略本郵件，您的密碼將保持不變。</p>
            <hr style="border: 0; border-top: 1px solid #eee; margin: 20px 0;">
            <p style="font-size: 12px; color: #999; text-align: center;">此為系統自動發送郵件，請勿直接回覆。</p>
        </div>
        ';

        $mail->Body    = $mailContent;
        $mail->AltBody = "請複製此連結至瀏覽器以重設您的密碼：\n" . $actualLink;

        $mail->send();

        if ($debugMode) {
            echo "<div style='padding: 20px; background: #eef9f1; border: 1px solid #c3e6cb; font-family: sans-serif; border-radius: 8px; max-width: 500px; margin: 20px auto; text-align: center;'>";
            echo "<h3 style='color: #155724; margin-top: 0;'>【測試成功】郵件已順利寄出！</h3>";
            echo "<p>由於您開啟了除錯模式（Debug Mode），系統未自動跳轉。您可以點擊下方按鈕返回：</p>";
            echo "<a href='../forgot-password.php?reset=success' style='display: inline-block; background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;'>返回忘記密碼頁面</a>";
            echo "</div>";
            exit();
        } else {
            header("Location: ../forgot-password.php?reset=success");
            exit();
        }

    } catch (Exception $e) {
        if ($debugMode) {
            echo "<div style='padding: 20px; background: #fee; border: 1px solid #fcc; font-family: monospace; border-radius: 8px;'>";
            echo "<h3 style='color: red; margin-top: 0;'>【發送失敗詳細錯誤報告】</h3>";
            echo "<strong>PHPMailer 錯誤訊息:</strong> " . $mail->ErrorInfo . "<br><br>";
            echo "<strong>Exception 詳細診斷:</strong> " . $e->getMessage() . "<br><br>";
            echo "<strong>SMTP 連線記錄：</strong><br><pre>" . htmlspecialchars($e->getFile()) . " line " . $e->getLine() . "</pre>";
            echo "</div>";
            exit();
        } else {
            header("Location: ../forgot-password.php?reset=failed");
            exit();
        }
    }

} else {
    header("Location: ../login.php");
    exit();
}