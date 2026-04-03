<?php
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SESSION["user_id"])) {
    
    $title = $_POST["title"];
    $content = $_POST["content"];
    $category_id = $_POST["category_id"];
    
    $user_id = $_SESSION["user_id"]; 

    try {
        require_once "dbh.inc.php";
        $query = "INSERT INTO posts (title, content, user_id, category_id) VALUES (?, ?, ?, ?);";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$title, $content, $user_id, $category_id]);

        header("Location: ../index.php?post=success");
        exit();
    } catch (PDOException $e) {
        die("發文失敗: " . $e->getMessage());
    }
} else {
    header("Location: ../index.php");
    exit();
}