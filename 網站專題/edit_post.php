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
    <title>編輯文章 - PHP Forum</title>
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

        /* Header */
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
        input[type="text"],
        textarea {
            width: 100%;

            border: 1px solid var(--border-color);
            background: var(--input-bg);
            color: var(--text-color);

            border-radius: 16px;

            padding: 14px 16px;

            font-size: .96rem;
            font-family: inherit;

            transition: .2s;
        }

        textarea {
            resize: vertical;
            min-height: 250px;
            line-height: 1.6;
        }

        select:focus,
        input[type="text"]:focus,
        textarea:focus {
            outline: none;
            border-color: var(--accent-color);
            box-shadow: 0 0 0 4px var(--accent-soft);
        }

        /* Attachment & Image Management Box */
        .attach-box{
            margin-top:18px;
            background:var(--bg-color);
            border:1px solid var(--border-color);
            border-radius:20px;
            padding:18px;
        }

        .img-management-section {
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
            display:flex;
            flex-wrap:wrap;
            justify-content: space-between;
            align-items: center;
            gap:15px;
            margin-top: 15px;
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
            white-space: pre-wrap; /* 保持換行格式 */
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
            <h1>🚀 PHP Forum</h1>
        </a>

        <button id="themeBtn" class="btn-control">
            🌓 主題
        </button>
    </div>
</header>

<div class="main-wrapper">

    <!-- Main -->
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

                    <!-- Category -->
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

                    <!-- Title -->
                    <div class="form-group">
                        <label>📝 文章標題</label>
                        <input type="text" name="title" value="<?= htmlspecialchars($post['title']) ?>" required>
                    </div>

                    <!-- Content -->
                    <div class="form-group">
                        <label>📖 文章內容 (維持 [imgX] 標籤可顯示圖片)</label>
                        <textarea id="postContent" name="content" required><?= htmlspecialchars($post['content']) ?></textarea>
                    </div>

                    <!-- Image Management -->
                    <div class="form-group">
                        <label>🎨 圖片與媒體管理</label>
                        <div class="attach-box">
                            <div class="img-management-section">
                                <small style="color:var(--text-muted); font-weight:800;">現有圖片 (點擊 ✕ 標記刪除)：</small>
                                <div class="preview-grid" id="existingPhotosPreview">
                                    <?php foreach ($existing_images as $index => $img): ?>
                                        <div class="preview-item" id="old-img-container-<?= $img['id'] ?>">
                                            <img src="uploads/post_imgs/<?= $img['image_path'] ?>">
                                            <span class="preview-tag">[img<?= $index+1 ?>]</span>
                                            <button type="button" class="remove-btn" onclick="toggleDeleteOldImage(<?= $img['id'] ?>)">✕</button>
                                            <input type="checkbox" name="delete_imgs[]" value="<?= $img['id'] ?>" id="delete-check-<?= $img['id'] ?>" style="display:none;">
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <hr style="border:0; border-top:1px solid var(--border-color); margin:10px 0;">
                                
                                <small style="color:var(--text-muted); font-weight:800;">追加新圖片：</small>
                                <div class="preview-grid" id="newPhotosPreview"></div>
                            </div>

                            <div class="attachment-controls">
                                <div class="btn-upload-group">
                                    <label for="newImgInput" class="btn-control">📷 選擇相片</label>
                                    <input type="file" id="newImgInput" accept="image/*" multiple style="display:none;">
                                </div>

                                <!-- ===== 新增：AI 文章潤色工具列 ===== -->
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
                        </div>
                    </div>

                    <!-- 存放新選取檔案的隱藏欄位 -->
                    <input type="file" name="new_post_imgs[]" id="hiddenNewFiles" multiple style="display:none;">

                    <!-- Buttons -->
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

<!-- ===== 新增：AI 潤色對比確認彈窗 ===== -->
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
    let newFilesArray = [];
    const newImgInput = document.getElementById('newImgInput');
    const newPhotosPreview = document.getElementById('newPhotosPreview');
    const hiddenNewFiles = document.getElementById('hiddenNewFiles');
    const postContent = document.getElementById('postContent');
    const existingCount = <?= count($existing_images) ?>;

    // --- 1. 處理舊圖片的刪除標記 ---
    function toggleDeleteOldImage(imgId) {
        const container = document.getElementById(`old-img-container-${imgId}`);
        const checkbox = document.getElementById(`delete-check-${imgId}`);
        
        if (!checkbox.checked) {
            checkbox.checked = true;
            container.classList.add('to-delete');
        } else {
            checkbox.checked = false;
            container.classList.remove('to-delete');
        }
    }

    // --- 2. 處理追加新圖片 ---
    newImgInput.addEventListener('change', function() {
        const files = Array.from(this.files);
        files.forEach(file => {
            newFilesArray.push(file);
            const newTagIndex = existingCount + newFilesArray.length;
            postContent.value += `\n[img${newTagIndex}]\n`;
        });
        renderNewPreviews();
        this.value = ''; 
    });

    function renderNewPreviews() {
        newPhotosPreview.innerHTML = '';
        newFilesArray.forEach((file, index) => {
            const reader = new FileReader();
            const tagIndex = existingCount + index + 1;
            reader.onload = function(e) {
                const div = document.createElement('div');
                div.className = 'preview-item';
                div.innerHTML = `
                    <img src="${e.target.result}">
                    <span class="preview-tag">[img${tagIndex}]</span>
                    <button type="button" class="remove-btn" onclick="removeNewImage(${index})">✕</button>
                `;
                newPhotosPreview.appendChild(div);
            }
            reader.readAsDataURL(file);
        });
    }

    window.removeNewImage = function(index) {
        newFilesArray.splice(index, 1);
        renderNewPreviews();
    };

    // --- 3. 表單送出前同步 DataTransfer ---
    document.getElementById('editForm').addEventListener('submit', function() {
        const dt = new DataTransfer();
        newFilesArray.forEach(file => dt.items.add(file));
        hiddenNewFiles.files = dt.files;
    });

    // --- 4. AI 文章潤色互動邏輯 ---
    const polishModal = document.getElementById('polishModal');
    const originalTextContent = document.getElementById('originalTextContent');
    const polishedTextContent = document.getElementById('polishedTextContent');
    const aiPolishBtn = document.getElementById('aiPolishBtn');
    let polishedResultHTML = ""; // 保存 AI 回傳的修飾成果

    async function polishArticle() {
        const currentContent = postContent.value.trim();
        if (currentContent === '') {
            alert("請先輸入一些文章內容再進行 AI 潤色喔！✍️");
            return;
        }

        const selectedStyle = document.getElementById('aiStyleSelect').value;

        // 進入載入狀態
        aiPolishBtn.disabled = true;
        const originalBtnText = aiPolishBtn.innerHTML;
        aiPolishBtn.innerHTML = `<span class="spinner"></span> 魔法修飾中...`;

        try {
            // 與共用的 api_ai_polish.php 通訊
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
                polishedResultHTML = data.polished_content;

                // 渲染至對比彈窗
                originalTextContent.textContent = currentContent;
                polishedTextContent.textContent = polishedResultHTML;

                // 開啟對比彈窗
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
            postContent.value = polishedResultHTML;
        }
        closePolishModal();
    }

    polishModal.onclick = (e) => {
        if (e.target === polishModal) {
            closePolishModal();
        }
    };

    // --- 5. 統一的主題切換控制 ---
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
</script>

</body>
</html>