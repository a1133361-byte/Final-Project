<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST["username"];
    $pwd = $_POST["pwd"];
    $email = $_POST["email"];

    try {
        require_once "dbh.inc.php";

        $hashedPwd = password_hash($pwd, PASSWORD_DEFAULT);

        $query = "INSERT INTO users (username, password, email) VALUES (?, ?, ?);";
        $stmt = $pdo->prepare($query);
        
        $stmt->execute([$username, $hashedPwd, $email]);

        header("Location: ../login.php?signup=success");
        exit();

    } catch (PDOException $e) {
        die("註冊失敗，可能帳號已存在: " . $e->getMessage());
    }
} else {
    header("Location: ../signup.php");
    exit();
}