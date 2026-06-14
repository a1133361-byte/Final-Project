<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

// 僅限 POST 存取
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Method Not Allowed']);
    exit();
}

// 取得前端傳過來的文章標題與內文
$input = json_decode(file_get_contents('php://input'), true);
$postTitle = isset($input['title']) ? trim($input['title']) : '';
$postContent = isset($input['content']) ? trim($input['content']) : '';

if (empty($postTitle) || empty($postContent)) {
    echo json_encode(['error' => '文章資訊不完整，無法產生摘要！']);
    exit();
}

// API 金鑰配置 (優先讀取環境變數)
$apiKey = getenv('GEMINI_API_KEY') ?: ($_ENV['GEMINI_API_KEY'] ?? ""); 

if (empty($apiKey)) {
    // 💡 這裡保留了您填入的 API Key
    $apiKey = ""; 
}

// ── 核心系統提示詞：限制格式為極簡大綱 ──
$systemPrompt = "您是本論壇最專業、最具洞察力的『文章智能閱讀導師』。
您的任務是幫助用戶快速總結一篇文章，提煉核心價值，節省閱讀時間。

請遵照以下排版與格式硬性規定：
1. 必須使用親切溫和、簡明扼要、具現代感的【繁體中文（台灣用語）】進行撰寫。
2. 輸出格式必須完全為 HTML 代碼，且僅包含 `<p>`、`<strong>`、`<ul>`、`<li>` 這幾種精巧的 HTML 標籤。
3. 您絕對不能附帶任何 Markdown 包裝（如不要寫 ```html 這樣的外框），直接輸出純 HTML 標籤代碼，方便前端渲染。
4. 格式大綱架構如下（一字不漏地按此架構輸出）：

<h5>📌 一秒速讀</h5>
<p><strong>[在此輸出用一句強而有力、高吸引力、精準切入核心的話，總結這篇文章到底在討論什麼。控制在 60 字內。]</strong></p>

<h5>🔑 核心要點</h5>
<ul>
  <li>[核心關鍵點 1：提煉文章最主要的論點、技術或故事，控制在 40 字內。]</li>
  <li>[核心關鍵點 2：提煉文章提及的次要論點、解法或精華。]</li>
  <li>[核心關鍵點 3：提供一個讀完此文章的啟發、收穫或一言蔽之的結論。]</li>
</ul>";

// 合併文章資訊為單一任務輸入
$taskInput = "【文章標題】：\n" . $postTitle . "\n\n【文章內容】：\n" . mb_substr($postContent, 0, 3000); // 限制長度避免爆 Token

$payload = [
    'contents' => [
        [
            'parts' => [
                ['text' => $taskInput]
            ]
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
    // 💡 核心安全修復：使用 PHP 字串拼接構造網址，徹底繞過 Markdown 渲染器的 auto-link 超連結干擾 bug！
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
    
    // 忽略 SSL 驗證，保障本地環境 XAMPP 的連線暢通
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
    
    // 清理 AI 可能不小心帶上的 markdown 標記
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
    // 輸出具體原因協助除錯
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