<?php
session_start();
require_once "includes/dbh.inc.php";

// 權限檢查：非管理員禁止進入
if (!isset($_SESSION['role']) || $_SESSION['role'] != 1) {
    header("Location: index.php");
    exit();
}

$message = "";
$error = "";

// 處理新增看板請求
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add') {
    $catName = trim($_POST['name']);
    if (!empty($catName)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
            $stmt->execute([$catName]);
            $message = "成功新增看板：$catName";
        } catch (PDOException $e) {
            $error = "新增失敗：" . $e->getMessage();
        }
    }
}

// 處理刪除看板請求
if (isset($_GET['delete'])) {
    $deleteId = $_GET['delete'];
    try {
        // 注意：實務上可能需要先處理該看板下的文章，這裡僅演示刪除看板
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$deleteId]);
        $message = "看板已成功刪除。";
    } catch (PDOException $e) {
        $error = "刪除失敗，該看板可能仍有相關文章。";
    }
}

// 讀取所有看板
try {
    $stmt = $pdo->query("SELECT * FROM categories ORDER BY id DESC");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    die("讀取看板失敗");
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>看板管理 - 管理後台</title>
    <style>
        :root {
            --bg-color: #f8fafc;
            --card-bg: #ffffff;
            --text-color: #0f172a;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --accent-color: #6366f1;
            --admin-color: #f59e0b;
            --danger-color: #ef4444;
            --sidebar-item-hover: #f1f5f9;
        }

        [data-theme="dark"] {
            --bg-color: #0f172a;
            --card-bg: #1e293b;
            --text-color: #f1f5f9;
            --text-muted: #94a3b8;
            --border-color: #334155;
            --sidebar-item-hover: #334155;
        }

        body { 
            font-family: 'Inter', system-ui, sans-serif; 
            background-color: var(--bg-color); 
            color: var(--text-color); 
            margin: 0; 
            padding: 0;
            transition: 0.3s;
        }

        .admin-header {
            background: var(--card-bg);
            border-bottom: 1px solid var(--border-color);
            padding: 15px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .container {
            max-width: 1000px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .back-link {
            text-decoration: none;
            color: var(--text-muted);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 20px;
            transition: 0.2s;
        }
        .back-link:hover { color: var(--accent-color); }

        .admin-card {
            background: var(--card-bg);
            border-radius: 20px;
            border: 1px solid var(--border-color);
            padding: 30px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        h2 { margin-top: 0; display: flex; align-items: center; gap: 10px; }

        /* 表單樣式 */
        .add-form {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            padding-bottom: 30px;
            border-bottom: 1px dashed var(--border-color);
        }
        .add-form input {
            flex: 1;
            padding: 12px 20px;
            border-radius: 12px;
            border: 2px solid var(--border-color);
            background: var(--bg-color);
            color: var(--text-color);
            outline: none;
            font-size: 1rem;
        }
        .add-form input:focus { border-color: var(--admin-color); }
        .add-form button {
            background: var(--admin-color);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s;
        }
        .add-form button:hover { opacity: 0.9; transform: translateY(-2px); }

        /* 表格樣式 */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th {
            text-align: left;
            color: var(--text-muted);
            font-size: 0.85rem;
            text-transform: uppercase;
            padding: 12px;
            border-bottom: 2px solid var(--border-color);
        }
        td {
            padding: 15px 12px;
            border-bottom: 1px solid var(--border-color);
        }
        tr:last-child td { border-bottom: none; }

        .btn-action {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.85rem;
            text-decoration: none;
            font-weight: 600;
            transition: 0.2s;
            display: inline-block;
        }
        .btn-edit { background: var(--accent-soft); color: var(--accent-color); margin-right: 5px; }
        .btn-edit:hover { background: var(--accent-color); color: white; }
        .btn-delete { background: rgba(239, 68, 68, 0.1); color: var(--danger-color); }
        .btn-delete:hover { background: var(--danger-color); color: white; }

        .alert {
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-weight: 600;
        }
        .alert-success { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .alert-error { background: rgba(239, 68, 68, 0.1); color: #ef4444; }

        /* 刪除確認 Modal */
        #confirmOverlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5); backdrop-filter: blur(5px);
            display: none; justify-content: center; align-items: center; z-index: 1000;
        }
    </style>
</head>
<body data-theme="light">

<header class="admin-header">
    <div style="display:flex; align-items:center; gap:10px;">
        <span style="font-size:1.5rem;">🛠️</span>
        <h1 style="margin:0; font-size:1.2rem; font-weight:800;">管理後台</h1>
    </div>
    <button id="themeBtn" style="background:none; border:none; cursor:pointer; font-size:1.2rem;">🌓</button>
</header>

<div class="container">
    <a href="index.php" class="back-link">⬅️ 返回論壇首頁</a>

    <?php if($message): ?>
        <div class="alert alert-success"><?= $message ?></div>
    <?php endif; ?>
    <?php if($error): ?>
        <div class="alert alert-error"><?= $error ?></div>
    <?php endif; ?>

    <div class="admin-card">
        <h2>📂 看板分類管理</h2>
        <p style="color:var(--text-muted); margin-bottom:25px;">您可以在此處新增、重新命名或刪除論壇的看板分類。</p>

        <!-- 新增看板表單 -->
        <form action="admin_categories.php" method="POST" class="add-form">
            <input type="hidden" name="action" value="add">
            <input type="text" name="name" placeholder="輸入新看板名稱 (例如: 遊戲開發、生活閒聊...)" required>
            <button type="submit">＋ 新增看板</button>
        </form>

        <!-- 看板列表 -->
        <table>
            <thead>
                <tr>
                    <th width="80">ID</th>
                    <th>看板名稱</th>
                    <th width="150" style="text-align:right;">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $cat): ?>
                <tr>
                    <td>#<?= $cat['id'] ?></td>
                    <td><strong><?= htmlspecialchars($cat['name']) ?></strong></td>
                    <td style="text-align:right;">
                        <a href="#" class="btn-action btn-edit" onclick="editCat(<?= $cat['id'] ?>, '<?= htmlspecialchars($cat['name']) ?>')">編輯</a>
                        <a href="#" class="btn-action btn-delete" onclick="confirmDelete(<?= $cat['id'] ?>, '<?= htmlspecialchars($cat['name']) ?>')">刪除</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- 刪除確認彈窗樣式 (簡單版) -->
<div id="confirmOverlay">
    <div style="background:var(--card-bg); padding:30px; border-radius:20px; text-align:center; max-width:400px; border:1px solid var(--border-color);">
        <h3 style="margin-top:0;">確定要刪除？</h3>
        <p id="confirmText" style="color:var(--text-muted);"></p>
        <div style="display:flex; gap:10px; margin-top:20px;">
            <button onclick="hideConfirm()" style="flex:1; padding:10px; border-radius:10px; border:1px solid var(--border-color); background:none; cursor:pointer; color:var(--text-color);">取消</button>
            <a id="confirmBtn" href="#" style="flex:1; padding:10px; border-radius:10px; background:var(--danger-color); color:white; text-decoration:none; font-weight:700;">確認刪除</a>
        </div>
    </div>
</div>

<script>
    // 主題切換
    const themeBtn = document.getElementById('themeBtn');
    const currentTheme = localStorage.getItem('theme') || 'light';
    document.body.setAttribute('data-theme', currentTheme);
    themeBtn.onclick = () => {
        const targetTheme = document.body.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        document.body.setAttribute('data-theme', targetTheme);
        localStorage.setItem('theme', targetTheme);
    };

    // 刪除確認
    const overlay = document.getElementById('confirmOverlay');
    const confirmText = document.getElementById('confirmText');
    const confirmBtn = document.getElementById('confirmBtn');

    function confirmDelete(id, name) {
        confirmText.innerText = `您確定要刪除「${name}」看板嗎？此操作不可逆。`;
        confirmBtn.href = `admin_categories.php?delete=${id}`;
        overlay.style.display = 'flex';
    }

    function hideConfirm() {
        overlay.style.display = 'none';
    }

    // 編輯提示 (實務上可改為 Modal 表單)
    function editCat(id, currentName) {
        const newName = prompt("請輸入新的看板名稱：", currentName);
        if (newName && newName !== currentName) {
            // 這裡可以用 AJAX 或跳轉處理
            alert("編輯功能可依需求串接後端更新 API");
        }
    }
</script>

</body>
</html>