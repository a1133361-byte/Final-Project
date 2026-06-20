<?php
if (isset($_POST['reset-password-submit'])) {

    require 'dbh.inc.php';

    $token = $_POST['token'];
    $email = $_POST['email'];
    $password = $_POST['pwd'];
    $passwordRepeat = $_POST['pwd-repeat'];

    // 1. 基本安全驗證
    if (empty($password) || empty($passwordRepeat)) {
        header("Location: ../reset-password.php?token=" . $token . "&email=" . urlencode($email) . "&error=emptyfields");
        exit();
    }

    if ($password !== $passwordRepeat) {
        header("Location: ../reset-password.php?token=" . $token . "&email=" . urlencode($email) . "&error=pwdnomatch");
        exit();
    }

    // 2. 再次與資料庫驗證 Token 是否合法且未過期
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
        // Token 無效
        header("Location: ../login.php?error=invalidtoken");
        exit();
    }

    // 3. 將新密碼進行雜湊 (Hash)
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // 4. 更新資料庫（更新密碼，同時將 reset_token 與 reset_expiry 歸空以防重複使用）
    $sql = "UPDATE users SET password = :password, reset_token = NULL, reset_expiry = NULL WHERE email = :email";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'password' => $hashedPassword,
        'email' => $email
    ]);

    // 5. 導向回登入頁面並顯示成功資訊
    header("Location: ../login.php?newpwd=passwordupdated");
    exit();

} else {
    header("Location: ../login.php");
    exit();
}