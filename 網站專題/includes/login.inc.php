<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST["username"];
    $pwd = $_POST["pwd"];

    try {
        require_once "dbh.inc.php";

       
        $query = "SELECT * FROM users WHERE username = :username;";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(":username", $username);
        $stmt->execute();

       
        $user = $stmt->fetch();

        if ($user && password_verify($pwd, $user["password"])) {
            
            session_start();
            $_SESSION["user_id"] = $user["id"];
            $_SESSION["username"] = $user["username"];
            $_SESSION["role"] = $user["role"];

            
            header("Location: ../index.php?login=success");
            exit();
        } else {
            header("Location: ../login.php?error=wronglogin");
            exit();
        }

    } catch (PDOException $e) {
        die("查詢失敗: " . $e->getMessage());
    }
} else {
    header("Location: ../login.php");
    exit();
}