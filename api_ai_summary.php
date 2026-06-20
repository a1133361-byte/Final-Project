<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

/**
 * 簡易版 .env 讀取函數 (無需 Composer)
 */
function loadEnv($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue; 
        if (strpos($line, '=') === false) continue;   
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

// 取得前端傳過來的資訊
$input = json_decode(file_get_contents('php://input'), true);
$postTitle = isset($input['title']) ? trim($input['title']) : '';
$postContent = isset($input['content']) ? trim($input['content']) : '';
// 新增：接收圖片的 Base64 或是圖片網址 (這裡以 Base64 為主)
$imageFormat = isset($input['image_format']) ? trim($input['image_format']) : ''; // 例如: "image/jpeg", "image/png"
$imageBase64 = isset($input['image_base64']) ? trim($input['image_base64']) : ''; // 去除掉 "data:image/jpeg;base64," 後的純 base64 字串

if (empty($postTitle) && empty($postContent) && empty($imageBase64)) {
    echo json_encode(['error' => '未提供任何文章資訊或圖片，無法產生摘要！']);
    exit();
}

// API 金鑰配置
$apiKey = getenv('GEMINI_API_KEY') ?: ($_ENV['GEMINI_API_KEY'] ?? ""); 

if (empty($apiKey)) {
    echo json_encode(['error' => '抱歉，系統設定錯誤：未找到 API 金鑰，請確認環境設定。']);
    exit();
}

// ── 核心系統提示詞（配合圖片加入微調） ──
$systemPrompt = "您是本論壇最專業、最具洞察力的『文章與圖文智能閱讀導師』。
您的任務是幫助用戶快速總結一篇文章（可能包含用戶附上的圖片），提煉核心價值，節省閱讀時間。
如果用戶有附上圖片，請務必將圖片內容納入理解，並在摘要中結合圖片所傳達的關鍵訊息。

請遵照以下排版與格式硬性規定：
1. 必須使用親切溫和、簡明扼要、具現代感的【繁體中文（台灣用語）】進行撰寫。
2. 輸出格式必須完全為 HTML 代碼，且僅包含 `<p>`、`<strong>`、`<ul>`、`<li>` 這幾種精巧的 HTML 標籤。
3. 您絕對不能附帶任何 Markdown 包裝（如不要寫 ```html 這樣的外框），直接輸出純 HTML 標籤代碼，方便前端渲染。
4. 格式大綱架構如下（一字不漏地按此架構輸出）：

<h5>📌 一秒速讀</h5>
<p><strong>[在此輸出用一句強而有力、高吸引力、精準切入核心的話，總結這篇文章與圖片到底在討論什麼。控制在 60 字內。]</strong></p>

<h5>🔑 核心要點</h5>
<ul>
  <li>[核心關鍵點 1：提煉最主要的論點、技術、圖表含意或故事，控制在 40 字內。]</li>
  <li>[核心關鍵點 2：提煉提及的次要論點、解法或精華。]</li>
  <li>[核心關鍵點 3：提供一個讀完此圖文內容的啟發、收穫或一言蔽之的結論。]</li>
</ul>";

// 構造任務文字輸入
$taskInput = "【文章標題】：\n" . $postTitle . "\n\n【文章內容】：\n" . mb_substr($postContent, 0, 3000);

// ── 動態構造 Gemini API 的 parts 陣列 ──
$parts = [];

// 1. 放入文字內容
$parts[] = ['text' => $taskInput];

// 2. 如果前端有傳圖片，則將圖片放入 parts 陣列中
if (!empty($imageBase64) && !empty($imageFormat)) {
    $parts[] = [
        'inlineData' => [
            'mimeType' => $imageFormat, // 例如 "image/jpeg"
            'data' => $imageBase64      // 純 Base64 字串
        ]
    ];
}

$payload = [
    'contents' => [
        [
            'parts' => $parts // 使用動態生成的 parts
        ]
    ],
    'systemInstruction' => [
        'parts' => [
            ['text' => $systemPrompt]
        ]
    ]
];

// 實作 5 次指數退避
$maxRetries = 5;
$retryDelay = 1; 
$responseBody = "";
$success = false;
$lastCurlError = "";
$httpCode = 0;

for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
    $scheme = "https:";
    $domain = "generativelanguage.googleapis.com";
    $path = "/v1beta/models/gemini-2.5-flash:generateContent?key=";
    
    $fullUrl = $scheme . "//" . $domain . $path . $apiKey;
    
    $ch = curl_init($fullUrl);
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    
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
    
    if (preg_match('/```html(.*?)```/s', $aiReply, $matches)) {
        $aiReply = trim($matches[1]);
    } elseif (preg_match('/```(.*?)```/s', $aiReply, $matches)) {
        $aiReply = trim($matches[1]);
    }
    
    if (!empty($aiReply)) {
        echo json_encode(['summary' => $aiReply]);
    } else {
        echo json_encode(['error' => 'AI 提煉摘要時似乎發生問題，請重試！']);
    }
} else {
    $apiErrorMessage = "";
    if (!empty($responseBody)) {
        $errorResult = json_decode($responseBody, true);
        if (isset($errorResult['error']['message'])) {
            $apiErrorMessage = " [API 錯誤: " . $errorResult['error']['message'] . "]";
        }
    }
    $debugInfo = !empty($lastCurlError) ? " (連線錯誤: " . $lastCurlError . ")" : "";
    echo json_encode(['error' => "無法連線至 AI 摘要服務。" . $apiErrorMessage . $debugInfo]);
}
?>