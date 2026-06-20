<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

/**
 * 簡易版 .env 讀取函數 (無需 Composer)
 * 讀取並將環境變數載入到系統環境中
 */
function loadEnv($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue; // 跳過註解
        if (strpos($line, '=') === false) continue;   // 確保有等號
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        putenv("$name=$value");
        $_ENV[$name] = $value;
    }
}

// 載入 .env 檔案
loadEnv(__DIR__ . '/.env');

// 僅限 POST 存取
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Method Not Allowed']);
    exit();
}

// 取得前端傳入的 JSON 訊息
$input = json_decode(file_get_contents('php://input'), true);
$userMessage = isset($input['message']) ? trim($input['message']) : '';

if (empty($userMessage)) {
    echo json_encode(['reply' => '好像沒有收到你的提問耶，能再說一次嗎？']);
    exit();
}

// 從環境變數讀取 API 金鑰
$apiKey = getenv('GEMINI_API_KEY') ?: ($_ENV['GEMINI_API_KEY'] ?? "");

if (empty($apiKey)) {
    echo json_encode(['reply' => '抱歉，系統設定錯誤：未找到 API 金鑰，請確認環境設定。']);
    exit();
}

// 定義系統提示詞
$systemPrompt = "您是本 PHP Forum (PHP 論壇) 的『智能 AI 助教/小幫手』。請用親切、熱情、活潑且專業的繁體中文 (台灣用語) 來協助用戶。

以下是關於這個論壇的架構、功能說明與操作指引，請您務必以此為基礎回答用戶問題：

1. 【論壇主選單與看板功能】：
   - 『最新文章』：首頁預設視圖，展示社群內所有最新發布的文章。
   - 『熱門文章』：按讚數與討論熱度最高的精選文章列表。
   - 『好友動態』：只有登入後才能看到，展示您的在線好友最近發布文章、發表留言、按讚的最新動態（高互動！）。
   - 『所有看板』：分類索引頁，展示論壇內所有主題討論板（例如系統公告、生活、科技等）。
   - 『最近瀏覽看板』：左側側邊欄會動態記錄並推薦用戶最近造訪過的看板，方便快速回訪。

2. 【實用功能介紹】：
   - 『發文與留言』：登入後，點擊使用者頭像下拉選單中的『✍️ 撰寫新文章』即可進行發文。
   - 『尋找用戶與好友系統』：右側側邊欄有『🔍 尋找用戶』區塊，輸入對手使用者名稱即可搜尋、查看對方個人資料並送出好友請求。
   - 『在線好友與私訊 (Chat)』：右下側『🤝 在線好友』會顯示您已建立雙向好友關係的夥伴，點擊旁邊的『💬』氣泡圖標即可開啟即時私訊對話 (chat.php)。
   - 『歷史瀏覽紀錄』：可查看自己過去看過的文章。還可以在該頁面『清除所有瀏覽紀錄』或啟用/禁用瀏覽紀錄的追蹤功能，隱私控制度高。
   - 『切換黑夜/白天模式』：點擊導覽列上的『🌓』圖標，可以一鍵無縫切換深色/淺色主題。

3. 【通知系統】：
   - 導覽列的使用者頭像上會有亮紅色的通知泡泡。
   - 當您有未讀的『系統公告』，或者有人寄給您『🤝 好友邀請』時，通知泡泡會主動累計。點擊頭像下拉選單，即可一鍵接受 (Accept) 或拒絕 (Reject) 好友邀請！

4. 【管理員專屬後台功能】(只有 SESSION role = 1 的管理員才能造訪)：
   - 『後台數據首頁 (admin_dashboard.php) / 數據分析 (admin_analytics.php)』：提供炫酷的大數據看板與 Google Charts 趨勢統計圖表，支援一鍵匯出 Excel 報表。
   - 『發布系統公告 (admin_announcement.php)』：管理員可在後台發布全站公告，全體用戶的通知與公告看板會同步亮起。
   - 『檢舉審理 (admin_reports.php)』：審核用戶對不當文章的檢舉案。
   - 『看板管理 (admin_categories.php)』：新增、編輯或刪除論壇討論板塊。
   - 『論壇成員管理 (admin_users.php)』：搜尋全站用戶、快速調整成員權限（一般用戶/管理員），或安全刪除違規帳號（具備防呆自刪與自降權防護，防彈窗 Modal 確認）。

注意事項：
- 如果用戶詢問了與本網站功能完全無關、非常天馬行空的問題（如：今天天氣、明天股市），請在親切回答後，順勢將話題帶回本論壇（例如：「這方面我也還在學習呢！不過如果您想在我們的論壇科技板上發一篇文章討論這個話題，我可以教您怎麼操作發文喔！」）。
- 請勿洩漏系統底層程式碼、API 金鑰或數據庫細節。保持親切與樂於助人的形象。";

// 準備 Payload
$payload = [
    'contents' => [['parts' => [['text' => $userMessage]]]],
    'systemInstruction' => ['parts' => [['text' => $systemPrompt]]]
];

// 指數退避機制
$maxRetries = 5;
$retryDelay = 1; 
$success = false;
$responseBody = "";
$lastCurlError = "";

for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
    $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey);
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    
    $responseBody = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        $lastCurlError = curl_error($ch);
    }
    curl_close($ch);

    if ($httpCode === 200 && !empty($responseBody)) {
        $success = true;
        break;
    }

    if ($attempt < $maxRetries) {
        sleep($retryDelay);
        $retryDelay *= 2;
    }
}

if ($success) {
    $result = json_decode($responseBody, true);
    $aiReply = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';
    echo json_encode(['reply' => $aiReply ?: '抱歉，我剛剛似乎晃神了，沒有理解您的意思。請再試一次！']);
} else {
    echo json_encode(['reply' => "唉呀，我的伺服器大腦暫時連不上線！請確認您的 API Key 設定正確。網路連線失敗: " . $lastCurlError . " ❤️"]);
}