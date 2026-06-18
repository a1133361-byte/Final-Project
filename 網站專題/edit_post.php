<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

require_once "includes/dbh.inc.php";

if (!isset($_GET["id"]) || empty($_GET["id"])) {
    header("Location: index.php");
    exit();
}

$post_id = $_GET["id"];
$user_id = $_SESSION["user_id"];
$current_uid = $_SESSION["user_id"];
$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] == 1;

// 初始化通知與檢舉統計 (與 profile.php 一致)
$pendingReportsCount = 0;
$unreadAnnouncementsCount = 0;
try {
    if ($isAdmin) {
        $report_stmt = $pdo->query("SELECT COUNT(*) FROM reports WHERE status = 0");
        $pendingReportsCount = (int)$report_stmt->fetchColumn();
    }
    $unread_sql = "SELECT COUNT(*) FROM announcements WHERE created_at > (SELECT IFNULL(last_announcement_view, '1970-01-01 00:00:00') FROM users WHERE id = ?)";
    $unread_stmt = $pdo->prepare($unread_sql);
    $unread_stmt->execute([$current_uid]);
    $unreadAnnouncementsCount = (int)$unread_stmt->fetchColumn();
} catch (PDOException $e) {}

try {
    $sql = "SELECT * FROM posts WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$post_id]);
    $post = $stmt->fetch();

    if (!$post) {
        die("找不到這篇文章！");
    }

    if ($post['user_id'] != $user_id) {
        header("Location: view_post.php?id=$post_id&error=unauthorized");
        exit();
    }

    $cat_sql = "SELECT * FROM categories";
    $categories = $pdo->query($cat_sql)->fetchAll();

    // 抓取現有圖片
    $img_sql = "SELECT * FROM post_images WHERE post_id = ? ORDER BY id ASC";
    $img_stmt = $pdo->prepare($img_sql);
    $img_stmt->execute([$post_id]);
    $existing_images = $img_stmt->fetchAll();

} catch (PDOException $e) {
    die("讀取失敗: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>編輯文章 - Talk Forum</title>
    <style>
        :root{
            --bg-color:#f8fafc;
            --card-bg:#ffffff;
            --text-color:#0f172a;
            --text-muted:#64748b;
            --border-color:#e2e8f0;

            --accent-color:#6366f1;
            --accent-soft:rgba(99,102,241,0.1);

            --header-gradient:linear-gradient(135deg,#6366f1 0%,#8b5cf6 100%);

            --nav-bg:rgba(255,255,255,0.85);
            --sidebar-item-hover:#f1f5f9;

            --danger-color:#ef4444;
            --success-color:#22c55e;

            --input-bg:#f8fafc;
            
            --admin-color: #f59e0b;
            --admin-soft: rgba(245, 158, 11, 0.1);
            --danger-soft: rgba(239, 68, 68, 0.1);
        }

        [data-theme="dark"]{
            --bg-color:#0f172a;
            --card-bg:#1e293b;
            --text-color:#f1f5f9;
            --text-muted:#94a3b8;
            --border-color:#334155;

            --nav-bg:rgba(15,23,42,0.9);
            --sidebar-item-hover:#334155;

            --accent-soft:rgba(99,102,241,0.2);

            --input-bg:#0f172a;
            --danger-soft: rgba(239, 68, 68, 0.15);
        }

        *{
            box-sizing:border-box;
        }

        body{
            margin:0;
            font-family:'Inter',system-ui,sans-serif;
            background:var(--bg-color);
            color:var(--text-color);
            transition:.25s;
        }

        /* Header (與 profile.php 一致) */
        header{
            background:var(--nav-bg);
            backdrop-filter:blur(10px);
            border-bottom:1px solid var(--border-color);

            position:sticky;
            top:0;
            z-index:1000;

            padding:12px 0;
        }

        .nav-container{
            max-width:1400px;
            margin:0 auto;
            padding:0 25px;

            display:flex;
            justify-content:space-between;
            align-items:center;
        }

        .logo{
            text-decoration:none;
        }

        .logo h1{
            margin:0;
            font-size:1.4rem;
            font-weight:800;

            background:var(--header-gradient);
            -webkit-background-clip:text;
            -webkit-text-fill-color:transparent;
        }

        /* 使用者下拉選單 (與 profile.php 一致) */
        .user-trigger { 
            display: flex; 
            align-items: center; 
            gap: 10px; 
            cursor: pointer; 
            padding: 5px 12px; 
            border-radius: 50px; 
            transition: 0.2s; 
            position: relative; 
        }
        .user-trigger:hover { background: var(--sidebar-item-hover); }
        .user-trigger span { font-weight: 700; font-size: 0.95rem; }
        
        .notification-badge { 
            position: absolute; 
            top: -2px; 
            right: -2px; 
            background: var(--danger-color); 
            color: white; 
            font-size: 0.65rem; 
            min-width: 18px; 
            height: 18px; 
            padding: 0 4px; 
            border-radius: 10px; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            border: 2px solid var(--card-bg); 
            font-weight: 800; 
        }
        
        .dropdown-menu { 
            position: absolute; 
            right: 0; 
            top: 125%; 
            width: 280px; 
            background: var(--card-bg); 
            border: 1px solid var(--border-color); 
            border-radius: 16px; 
            box-shadow: 0 10px 25px rgba(0,0,0,0.15); 
            display: none; 
            flex-direction: column; 
            overflow: hidden; 
            z-index: 1100; 
        }
        .dropdown-menu.active { display: flex; }
        .dropdown-menu a { 
            padding: 12px 20px; 
            text-decoration: none; 
            color: var(--text-color); 
            font-weight: 600; 
            font-size: 0.9rem; 
            transition: 0.2s; 
            border-bottom: 1px solid var(--border-color); 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
        }
        .dropdown-menu a:last-child { border-bottom: none; }
        .dropdown-menu a:hover { background: var(--sidebar-item-hover); color: var(--accent-color); }

        .admin-link { color: var(--admin-color) !important; background: var(--admin-soft); }
        .admin-link:hover { background: var(--admin-color) !important; color: white !important; }
        .badge-inline { background: var(--danger-color); color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.7rem; margin-left: auto; font-weight: 800; }

        /* Layout */
        .main-wrapper{
            max-width:1000px; 
            margin:25px auto;
            padding:0 25px;
        }

        /* Main Card */
        .form-card{
            background:var(--card-bg);
            border:1px solid var(--border-color);
            border-radius:28px;
            overflow:hidden;
        }

        .form-content{
            padding:35px;
        }

        .page-title{
            font-size:2rem;
            font-weight:900;
            margin:0 0 8px 0;
        }

        .page-desc{
            color:var(--text-muted);
            margin-bottom:35px;
            line-height:1.6;
        }

        /* Form */
        .form-group{
            margin-bottom:24px;
        }

        label{
            display:block;
            margin-bottom:10px;

            font-size:.92rem;
            font-weight:800;
            color:var(--text-color);
        }

        select,
        input[type="text"]{
            width:100%;

            border:1px solid var(--border-color);
            background:var(--input-bg);
            color:var(--text-color);

            border-radius:16px;

            padding:14px 16px;

            font-size:.96rem;
            font-family:inherit;

            transition:.2s;
        }

        select:focus,
        input[type="text"]:focus,
        .rich-editor:focus{
            outline:none;
            border-color:var(--accent-color);
            box-shadow:0 0 0 4px var(--accent-soft);
        }

        /* Rich Editor */
        .rich-editor {
            width:100%;
            min-height:300px;
            border:1px solid var(--border-color);
            background:var(--input-bg);
            color:var(--text-color);
            border-radius:16px;
            padding:14px 16px;
            font-size:.96rem;
            font-family:inherit;
            line-height:1.7;
            overflow-y:auto;
            transition:.2s;
        }

        .rich-editor img,
        .rich-editor video {
            max-width: 100%;
            max-height: 400px;
            display: block;
            margin: 10px 0;
            border-radius: 12px;
            border: 1px solid var(--border-color);
        }

        /* Attachment Box */
        .attach-box{
            margin-top:18px;
            background:var(--bg-color);
            border:1px solid var(--border-color);
            border-radius:20px;
            padding:18px;
            display:flex;
            justify-content:space-between;
            align-items:center;
            flex-wrap:wrap;
            gap:15px;
        }

        .img-management-section {
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .preview-grid {
            display: flex;
            gap: 15px;
            overflow-x: auto;
            padding: 10px 5px;
        }
        
        .preview-item {
            flex: 0 0 110px;
            position: relative;
            transition: 0.3s;
        }
        .preview-item img {
            width: 110px;
            height: 110px;
            object-fit: cover;
            border-radius: 12px;
            border: 2px solid transparent;
        }
        
        .preview-tag {
            position: absolute;
            bottom: 5px;
            left: 5px;
            background: rgba(0,0,0,0.6);
            color: white;
            font-size: 10px;
            padding: 2px 5px;
            border-radius: 4px;
            pointer-events: none;
        }
        
        .remove-btn {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--danger-color);
            color: white;
            border: none;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            z-index: 5;
            transition: .2s;
        }
        
        /* 待刪除狀態視覺效果 */
        .preview-item.to-delete img {
            opacity: 0.2;
            filter: grayscale(1);
            border: 2px dashed var(--danger-color);
        }
        .preview-item.to-delete::after {
            content: "待刪除";
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: var(--danger-color);
            font-weight: bold;
            font-size: 14px;
            pointer-events: none;
        }
        .preview-item.to-delete .remove-btn {
            background: var(--success-color);
            transform: rotate(45deg);
        }

        .attachment-controls{
            width: 100%;
            display:flex;
            flex-wrap:wrap;
            justify-content: space-between;
            align-items: center;
            gap:15px;
        }

        .btn-upload-group {
            display: flex;
            gap: 10px;
        }

        .ai-controls {
            display:flex;
            align-items:center;
            gap:8px;
        }

        .btn-control{
            border:none;
            background:var(--card-bg);
            border:1px solid var(--border-color);
            color:var(--text-color);
            padding:10px 16px;
            border-radius:12px;
            font-size:.9rem;
            font-weight:700;
            cursor:pointer;
            transition:.2s;
        }

        .btn-control:hover{
            background:var(--accent-soft);
            border-color:var(--accent-color);
            color:var(--accent-color);
        }

        .btn-ai-polish {
            background: var(--header-gradient);
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: 12px;
            font-size: .9rem;
            font-weight: 800;
            cursor: pointer;
            transition: .2s;
            box-shadow: 0 4px 12px rgba(99,102,241,0.2);
        }

        .btn-ai-polish:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(99,102,241,0.35);
        }

        .style-select {
            padding: 10px 14px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            background: var(--card-bg);
            color: var(--text-color);
            font-size: .85rem;
            font-weight: 700;
            outline: none;
            cursor: pointer;
        }

        /* Buttons */
        .button-group{
            display:flex;
            gap:15px;
            margin-top:35px;
        }

        .btn-submit,
        .btn-cancel{
            border:none;
            text-decoration:none;
            padding:15px 20px;
            border-radius:16px;
            font-weight:800;
            font-size:.95rem;
            transition:.2s;
        }

        .btn-submit{
            flex:2;
            background:var(--header-gradient);
            color:white;
            cursor:pointer;
        }

        .btn-submit:hover{
            transform:translateY(-2px);
            box-shadow:0 10px 20px rgba(99,102,241,0.25);
        }

        .btn-cancel{
            flex:1;
            background:transparent;
            border:1px solid var(--border-color);
            color:var(--text-color);
            display:flex;
            justify-content:center;
            align-items:center;
        }

        .btn-cancel:hover{
            background:var(--sidebar-item-hover);
        }

        /* AI Polish Result Modal (AI潤色對比彈窗樣式) */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            z-index: 2000;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
        }
        .modal-overlay.open {
            opacity: 1;
            pointer-events: auto;
        }
        .modal {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            max-width: 800px;
            width: 90%;
            max-height: 85vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            transform: scale(0.95);
            transition: transform 0.3s ease;
            overflow: hidden;
        }
        .modal-overlay.open .modal {
            transform: scale(1);
        }
        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-title {
            font-size: 1.2rem;
            font-weight: 900;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .modal-body {
            padding: 24px;
            overflow-y: auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        @media (max-width: 768px) {
            .modal-body {
                grid-template-columns: 1fr;
            }
        }
        .comparison-box {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .comparison-label {
            font-size: 0.8rem;
            font-weight: 800;
            text-transform: uppercase;
            color: var(--text-muted);
        }
        .comparison-content {
            border: 1px solid var(--border-color);
            background: var(--bg-color);
            border-radius: 16px;
            padding: 16px;
            font-size: 0.92rem;
            line-height: 1.6;
            min-height: 250px;
            max-height: 400px;
            overflow-y: auto;
        }
        .modal-footer {
            padding: 20px 24px;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        /* Spinner CSS */
        .spinner {
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top: 3px solid #fff;
            width: 18px;
            height: 18px;
            animation: spin 1s linear infinite;
            display: inline-block;
            vertical-align: middle;
            margin-right: 6px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @media (max-width:600px){
            .form-content{
                padding:22px;
            }

            .button-group{
                flex-direction:column;
            }

            .page-title{
                font-size:1.6rem;
            }
            
            .attachment-controls {
                flex-direction: column;
                align-items: stretch;
            }
            .ai-controls {
                justify-content: space-between;
            }
        }
    </style>
</head>

<body data-theme="light">

<header>
    <div class="nav-container">
        <a href="index.php" class="logo">
            <h1>✌️ Talk Forum</h1>
        </a>

        <div style="display:flex; align-items:center; gap:15px;">
            <button id="themeBtn" title="切換主題" style="background:none; border:none; cursor:pointer; font-size:1.3rem; padding:5px; border-radius:50%;">🌓</button>
            <?php if (isset($_SESSION["user_id"])): ?>
                <div style="position:relative;">
                    <div class="user-trigger" id="userTrigger">
                        <img src="<?= !empty($_SESSION['profile_img']) ? "uploads/users_profile_img/".$_SESSION['profile_img'] : "uploads/default_avatar.png" ?>" style="width:32px; height:32px; border-radius:50%; object-fit:cover; border: 2px solid <?= $isAdmin ? 'var(--admin-color)' : 'var(--accent-color)' ?>;">
                        <span style="<?= $isAdmin ? 'color: var(--admin-color);' : '' ?>"><?= htmlspecialchars($_SESSION["username"]) ?></span>
                        <?php 
                        $totalNotif = $unreadAnnouncementsCount + ($isAdmin ? $pendingReportsCount : 0);
                        if ($totalNotif > 0): ?>
                            <div class="notification-badge"><?= $totalNotif ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="dropdown-menu" id="dropdownMenu">
                        <div style="padding: 10px 20px; font-size: 0.7rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase;">使用者功能</div>
                        <a href="profile.php?id=<?= $_SESSION['user_id'] ?>">👤 我的個人資料</a>
                        <a href="index.php?view=history">🕒 歷史瀏覽紀錄</a>
                        <a href="create_post.php">✍️ 撰寫新文章</a>
                        <?php if ($isAdmin): ?>
                            <div style="padding: 10px 20px; font-size: 0.7rem; color: var(--admin-color); font-weight: 800; text-transform: uppercase; background: var(--admin-soft);">管理員功能</div>
                            <a href="admin_dashboard.php" class="admin-link">📊 後台數據首頁</a>
                            <a href="admin_reports.php" class="admin-link">🚩 檢舉審核 
                                <?php if($pendingReportsCount > 0): ?><span class="badge-inline"><?= $pendingReportsCount ?></span><?php endif; ?>
                            </a>
                            <a href="admin_categories.php" class="admin-link">🛠️ 看板管理</a>
                        <?php endif; ?>
                        <a href="logout.php" style="color:#ef4444; font-weight:700;">🚪 登出系統</a>
                    </div>
                </div>
            <?php else: ?>
                <a href="login.php" style="text-decoration:none; background:var(--accent-color); color:white; padding:8px 20px; border-radius:50px; font-weight:700;">登入</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<div class="main-wrapper">

    <main>

        <section class="form-card">

            <div class="form-content">

                <h1 class="page-title">
                    📝 編輯您的文章
                </h1>

                <div class="page-desc">
                    重新包裝您的文字，將完美與亮點展現給社群成員 ✨
                </div>

                <form id="editForm" action="includes/edit_post.inc.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="post_id" value="<?= $post['id'] ?>">

                    <div class="form-group">
                        <label>📚 選擇看板</label>
                        <select name="category_id" required>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= ($cat['id'] == $post['category_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>📝 文章標題</label>
                        <input type="text" name="title" value="<?= htmlspecialchars($post['title']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label>📖 文章內容</label>
                        
                        <div id="postContentEditor" 
                             class="rich-editor" 
                             contenteditable="true" 
                             placeholder="輸入你的內容..."><?= $post['content'] ?></div>
                        
                        <textarea name="content" id="hiddenContent" style="display:none;"></textarea>
                    </div>

                    <div class="form-group">
                        <label>🎨 圖片與媒體管理</label>
                        <div class="attach-box">
                            
                            <div class="attachment-controls">
                                <div class="btn-upload-group">
                                    <button type="button"
                                            class="btn-control"
                                            onclick="document.getElementById('newImgInput').click()">
                                        📷 插入圖片
                                    </button>

                                    <button type="button"
                                            class="btn-control"
                                            onclick="document.getElementById('newVidInput').click()">
                                        🎬 插入影片
                                    </button>
                                </div>

                                <div class="ai-controls">
                                    <select id="aiStyleSelect" class="style-select" title="選擇修飾風格">
                                        <option value="professional">🛡️ 專業職場</option>
                                        <option value="poetic">✨ 文學優美</option>
                                        <option value="humorous">🤪 幽默風趣</option>
                                        <option value="simple">💡 通俗易懂</option>
                                    </select>
                                    <button type="button" class="btn-ai-polish" id="aiPolishBtn" onclick="polishArticle()">
                                        🔮 AI 潤色文章
                                    </button>
                                </div>
                            </div>

                            <input type="file"
                                   id="newImgInput"
                                   accept="image/*"
                                   multiple
                                   style="display:none;">

                            <input type="file"
                                   id="newVidInput"
                                   accept="video/mp4,video/webm,video/ogg"
                                   multiple
                                   style="display:none;">
                        </div>
                    </div>

                    <input type="file" name="new_post_imgs[]" id="hiddenNewFiles" multiple style="display:none;">
                    <input type="file" name="new_post_vids[]" id="hiddenNewVids" multiple style="display:none;">

                    <div class="button-group">
                        <a href="view_post.php?id=<?= $post_id ?>" class="btn-cancel">
                            取消修改
                        </a>
                        <button type="submit" name="submit_edit" class="btn-submit">
                            🚀 儲存並更新
                        </button>
                    </div>
                </form>

            </div>

        </section>

    </main>

</div>

<div class="modal-overlay" id="polishModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">🔮 AI 潤色結果對比</h3>
            <button class="btn-control" onclick="closePolishModal()" style="padding: 5px 10px;">✕</button>
        </div>
        <div class="modal-body">
            <div class="comparison-box">
                <span class="comparison-label">原先的內容</span>
                <div class="comparison-content" id="originalTextContent" style="opacity: 0.7;"></div>
            </div>
            <div class="comparison-box">
                <span class="comparison-label" style="color: var(--accent-color);">✨ AI 修飾後的內容</span>
                <div class="comparison-content" id="polishedTextContent" style="border-color: var(--accent-color);"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-cancel" onclick="closePolishModal()" style="margin:0;">取消使用</button>
            <button class="btn-submit" onclick="applyPolishResult()" style="margin:0; flex:none; padding: 12px 28px;">✔️ 替換成此內容</button>
        </div>
    </div>
</div>

<script>
/* =========================
    Theme 主題切換控制
========================= */
const themeBtn = document.getElementById('themeBtn');
const currentTheme = localStorage.getItem('theme') || 'light';

document.body.setAttribute('data-theme', currentTheme);

themeBtn.onclick = () => {
    const targetTheme =
        document.body.getAttribute('data-theme') === 'dark'
        ? 'light'
        : 'dark';

    document.body.setAttribute('data-theme', targetTheme);
    localStorage.setItem('theme', targetTheme);
};

/* =========================
    Dropdown Menu 下拉選單切換互動
========================= */
const userTrigger = document.getElementById('userTrigger');
const dropdownMenu = document.getElementById('dropdownMenu');
if(userTrigger && dropdownMenu) {
    userTrigger.onclick = (e) => { 
        e.stopPropagation(); 
        dropdownMenu.classList.toggle('active'); 
    };
    document.addEventListener('click', (e) => {
        if (!userTrigger.contains(e.target)) dropdownMenu.classList.remove('active');
    });
}

/* =========================
    Editor & Files (新圖片/影片即時預覽與嵌入)
========================= */
let newImgList = [];
let newVidList = [];

const editor = document.getElementById('postContentEditor');

// 初始化 Placeholder 效果
if (editor.innerText.trim() === '') {
    editor.innerHTML = '<span style="color: var(--text-muted);" id="placeholderSpan">輸入你的內容...</span>';
}
editor.addEventListener('focus', function() {
    const placeholder = document.getElementById('placeholderSpan');
    if (placeholder) {
        editor.innerHTML = '';
    }
});
editor.addEventListener('blur', function() {
    if (editor.innerText.trim() === '') {
        editor.innerHTML = '<span style="color: var(--text-muted);" id="placeholderSpan">輸入你的內容...</span>';
    }
});

// 在游標處插入 HTML 節點的函式
function insertElementAtCursor(el) {
    editor.focus();
    const sel = window.getSelection();
    if (sel.getRangeAt && sel.rangeCount) {
        let range = sel.getRangeAt(0);
        
        // 如果目前還在 placeholder 狀態則清空
        const placeholder = document.getElementById('placeholderSpan');
        if (placeholder) {
            editor.innerHTML = '';
            range = sel.getRangeAt(0);
        }
        
        range.deleteContents();
        range.insertNode(el);
        
        range = range.cloneRange();
        range.setStartAfter(el);
        range.collapse(true);
        sel.removeAllRanges();
        sel.addRange(range);
    } else {
        editor.appendChild(el);
    }
}

/* 舊圖片的刪除標記互動 (保留函式以避免外部調用報錯) */
function toggleDeleteOldImage(imgId) {
    const container = document.getElementById(`old-img-container-${imgId}`);
    const checkbox = document.getElementById(`delete-check-${imgId}`);
    
    if (checkbox && container) {
        if (!checkbox.checked) {
            checkbox.checked = true;
            container.classList.add('to-delete');
        } else {
            checkbox.checked = false;
            container.classList.remove('to-delete');
        }
    }
}

/* 新相片追加與直接嵌入編輯器 */
document.getElementById('newImgInput').addEventListener('change', function(){
    Array.from(this.files).forEach(file => {
        newImgList.push(file);
        const fileIndex = newImgList.length - 1;

        const reader = new FileReader();
        reader.onload = function(e) {
            const img = document.createElement('img');
            img.src = e.target.result;
            img.dataset.type = 'new_img';
            img.dataset.index = fileIndex;
            insertElementAtCursor(img);
        };
        reader.readAsDataURL(file);
    });
    this.value = '';
});

/* 新影片追加與直接嵌入編輯器 */
document.getElementById('newVidInput').addEventListener('change', function(){
    Array.from(this.files).forEach(file => {
        newVidList.push(file);
        const fileIndex = newVidList.length - 1;

        const reader = new FileReader();
        reader.onload = function(e) {
            const video = document.createElement('video');
            video.src = e.target.result;
            video.controls = true;
            video.dataset.type = 'new_vid';
            video.dataset.index = fileIndex;
            insertElementAtCursor(video);
        };
        reader.readAsDataURL(file);
    });
    this.value = '';
});

/* =========================
    AI 文章潤色互動邏輯
========================= */
const polishModal = document.getElementById('polishModal');
const originalTextContent = document.getElementById('originalTextContent');
const polishedTextContent = document.getElementById('polishedTextContent');
const aiPolishBtn = document.getElementById('aiPolishBtn');
let polishedResultHTML = ""; // 儲存 AI 潤色完成後的完整 HTML

async function polishArticle() {
    // 檢查是否有 Placeholder 內容或空白
    const placeholder = document.getElementById('placeholderSpan');
    if (placeholder || editor.innerText.trim() === '') {
        alert("請先輸入一些文章內容再進行 AI 潤色喔！✍️");
        return;
    }

    const currentContent = editor.innerHTML;
    const selectedStyle = document.getElementById('aiStyleSelect').value;

    // 按鈕進入 Loading 狀態
    aiPolishBtn.disabled = true;
    const originalBtnText = aiPolishBtn.innerHTML;
    aiPolishBtn.innerHTML = `<span class="spinner"></span> 魔法修飾中...`;

    try {
        const response = await fetch('api_ai_polish.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                content: currentContent,
                style: selectedStyle
            })
        });

        const data = await response.json();
        
        if (data && data.polished_content) {
            // 保存潤色成果
            polishedResultHTML = data.polished_content;

            // 將 HTML 渲染到對比 Modal 中
            originalTextContent.innerHTML = currentContent;
            polishedTextContent.innerHTML = polishedResultHTML;

            // 開啟對比 Modal
            polishModal.classList.add('open');
        } else {
            alert(data.error || "潤色失敗，請稍後再試一次！💨");
        }
    } catch (error) {
        alert("網路連線失敗，請檢查您的伺服器與 API 金鑰狀態。");
    } finally {
        // 恢復按鈕狀態
        aiPolishBtn.disabled = false;
        aiPolishBtn.innerHTML = originalBtnText;
    }
}

function closePolishModal() {
    polishModal.classList.remove('open');
}

function applyPolishResult() {
    if (polishedResultHTML) {
        editor.innerHTML = polishedResultHTML;
    }
    closePolishModal();
}

// 點擊彈窗遮罩關閉彈窗
polishModal.onclick = (e) => {
    if (e.target === polishModal) {
        closePolishModal();
    }
};

/* Submit 表單送出處理 */
document.getElementById('editForm').addEventListener('submit', function(e){
    // 檢查是否有 Placeholder 內容
    const placeholder = document.getElementById('placeholderSpan');
    if (placeholder) {
        editor.innerHTML = '';
    }

    // 將可編輯區塊內的純文字或 HTML 結構同步到隱藏的 textarea 送出
    document.getElementById('hiddenContent').value = editor.innerHTML;

    // 重新過濾被使用者留在編輯器裡面的「新圖片」檔案
    const remainingNewImgs = editor.querySelectorAll('img[data-type="new_img"]');
    const dataTransferImg = new DataTransfer();
    remainingNewImgs.forEach(img => {
        const idx = img.dataset.index;
        if(newImgList[idx]) {
            dataTransferImg.items.add(newImgList[idx]);
        }
    });
    document.getElementById('hiddenNewFiles').files = dataTransferImg.files;

    // 重新過濾被使用者留在編輯器裡面的「新影片」檔案
    const remainingNewVids = editor.querySelectorAll('video[data-type="new_vid"]');
    const dataTransferVid = new DataTransfer();
    remainingNewVids.forEach(vid => {
        const idx = vid.dataset.index;
        if(newVidList[idx]) {
            dataTransferVid.items.add(newVidList[idx]);
        }
    });
    document.getElementById('hiddenNewVids').files = dataTransferVid.files;
});
</script>

</body>
</html>