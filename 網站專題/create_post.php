<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php?error=please_login");
    exit();
}

require_once "includes/dbh.inc.php";

try {
    $sql = "SELECT * FROM categories ORDER BY id ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    die("資料庫錯誤: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>發表新文章 - PHP Forum</title>

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
    max-width:1000px; /* 移除側邊欄後，縮小寬度讓畫面更集中 */
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

/* Rich Editor (取代原本的 textarea) */
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

/* 限制使用者新增的媒體大小不超出編輯範圍 */
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
}

.attachment-controls{
    display:flex;
    flex-wrap:wrap;
    gap:10px;
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
                    ✍️ 分享你的想法
                </h1>

                <div class="page-desc">
                    你的文字會像流星一樣飛進論壇宇宙 ✨
                </div>

                <form id="postForm"
                      action="includes/post.inc.php"
                      method="POST"
                      enctype="multipart/form-data">

                    <!-- Category -->
                    <div class="form-group">
                        <label>📚 選擇看板</label>

                        <select name="category_id" required>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>">
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Title -->
                    <div class="form-group">
                        <label>📝 文章標題</label>

                        <input type="text"
                               name="title"
                               maxlength="120"
                               placeholder="輸入一個吸引人的標題..."
                               required>
                    </div>

                    <!-- Content -->
                    <div class="form-group">
                        <label>📖 文章內容</label>

                        <!-- 將原本的 textarea 改成可直接編輯與插入圖片的 div -->
                        <div id="postContentEditor" 
                             class="rich-editor" 
                             contenteditable="true" 
                             placeholder="輸入你的內容..."></div>
                        
                        <!-- 隱藏的 textarea 用於打包傳送給後端，名稱仍維持 content 不破壞原本功能 -->
                        <textarea name="content" id="hiddenContent" style="display:none;"></textarea>

                        <div class="attach-box">

                            <div class="attachment-controls">

                                <button type="button"
                                        class="btn-control"
                                        onclick="document.getElementById('imgInput').click()">
                                    📷 圖片
                                </button>

                                <button type="button"
                                        class="btn-control"
                                        onclick="document.getElementById('vidInput').click()">
                                    🎬 影片
                                </button>

                            </div>

                            <input type="file"
                                   id="imgInput"
                                   accept="image/*"
                                   multiple
                                   style="display:none;">

                            <input type="file"
                                   id="vidInput"
                                   accept="video/mp4,video/webm,video/ogg"
                                   multiple
                                   style="display:none;">

                        </div>
                    </div>

                    <!-- Hidden (原本對接後端檔案上傳的 input 保持不變) -->
                    <input type="file"
                           name="post_imgs[]"
                           id="hiddenFiles"
                           multiple
                           style="display:none;">

                    <input type="file"
                           name="post_vids[]"
                           id="hiddenVids"
                           multiple
                           style="display:none;">

                    <!-- Buttons -->
                    <div class="button-group">

                        <a href="index.php" class="btn-cancel">
                            取消
                        </a>

                        <button type="submit"
                                name="submit_post"
                                class="btn-submit">
                            🚀 發布文章
                        </button>

                    </div>

                </form>

            </div>

        </section>

    </main>

</div>

<script>
/* =========================
    Theme
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
    Editor & Files
========================= */

let imgList = [];
let vidList = [];

const editor = document.getElementById('postContentEditor');

// 初始化 Placeholder 效果
if (editor.innerText.trim() === '') {
    editor.innerHTML = '<span style="color: var(--text-muted);">輸入你的內容...</span>';
}
editor.addEventListener('focus', function() {
    if (editor.innerText === '輸入你的內容...') {
        editor.innerHTML = '';
    }
});
editor.addEventListener('blur', function() {
    if (editor.innerText.trim() === '') {
        editor.innerHTML = '<span style="color: var(--text-muted);">輸入你的內容...</span>';
    }
});

// 在游標處插入 HTML 節點的函式
function insertElementAtCursor(el) {
    editor.focus();
    const sel = window.getSelection();
    if (sel.getRangeAt && sel.rangeCount) {
        let range = sel.getRangeAt(0);
        
        // 如果目前還在 placeholder 狀態則清空
        if (editor.innerText === '輸入你的內容...') {
            editor.innerHTML = '';
            range = sel.getRangeAt(0);
        }
        
        range.deleteContents();
        range.insertNode(el);
        
        // 將游標移到新插入物件的後面
        range = range.cloneRange();
        range.setStartAfter(el);
        range.collapse(true);
        sel.removeAllRanges();
        sel.addRange(range);
    } else {
        editor.appendChild(el);
    }
}

/* Images 上傳與直接嵌入 */
document.getElementById('imgInput').addEventListener('change', function(){
    Array.from(this.files).forEach(file => {
        imgList.push(file);
        const fileIndex = imgList.length - 1;

        const reader = new FileReader();
        reader.onload = function(e) {
            const img = document.createElement('img');
            img.src = e.target.result;
            img.dataset.type = 'img';
            img.dataset.index = fileIndex;
            insertElementAtCursor(img);
        };
        reader.readAsDataURL(file);
    });
    this.value = '';
});

/* Videos 上傳與直接嵌入 */
document.getElementById('vidInput').addEventListener('change', function(){
    Array.from(this.files).forEach(file => {
        vidList.push(file);
        const fileIndex = vidList.length - 1;

        const reader = new FileReader();
        reader.onload = function(e) {
            const video = document.createElement('video');
            video.src = e.target.result;
            video.controls = true;
            video.dataset.type = 'vid';
            video.dataset.index = fileIndex;
            insertElementAtCursor(video);
        };
        reader.readAsDataURL(file);
    });
    this.value = '';
});

/* Submit 表單送出處理 */
document.getElementById('postForm').addEventListener('submit', function(e){
    // 檢查是否有 Placeholder 內容
    if (editor.innerText === '輸入你的內容...') {
        editor.innerHTML = '';
    }

    // 將可編輯區塊內的純文字或 HTML 結構同步到隱藏的 textarea 送出
    document.getElementById('hiddenContent').value = editor.innerHTML;

    // 重新過濾被使用者留在編輯器裡面的圖片檔案（沒被 Backspace 刪除的）
    const remainingImgs = editor.querySelectorAll('img[data-type="img"]');
    const dataTransferImg = new DataTransfer();
    remainingImgs.forEach(img => {
        const idx = img.dataset.index;
        if(imgList[idx]) {
            dataTransferImg.items.add(imgList[idx]);
        }
    });
    document.getElementById('hiddenFiles').files = dataTransferImg.files;

    // 重新過濾被使用者留在編輯器裡面的影片檔案
    const remainingVids = editor.querySelectorAll('video[data-type="vid"]');
    const dataTransferVid = new DataTransfer();
    remainingVids.forEach(vid => {
        const idx = vid.dataset.index;
        if(vidList[idx]) {
            dataTransferVid.items.add(vidList[idx]);
        }
    });
    document.getElementById('hiddenVids').files = dataTransferVid.files;
});
</script>

</body>
</html>