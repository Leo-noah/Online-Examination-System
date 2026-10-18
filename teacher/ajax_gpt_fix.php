<?php
// gpt_fix_handler.php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userInput = $_POST['text'] ?? '';
    $type = $_POST['type'] ?? 'Short Question';
    $apiKey = getenv('OPENAI_API_KEY') ?: '';
    if ($apiKey === '') {
        http_response_code(503);
        exit('AI assistance is not configured.');
    }
    $data = [
        "model" => "gpt-4o-mini",
        "messages" => [
            ["role" => "system", "content" => "Format as $type. Keep 4-5 sentences as one block."],
            ["role" => "user", "content" => $userInput]
        ]
    ];

    $ch = curl_init("https://api.openai.com/v1/chat/completions");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer $apiKey"
    ]);

    // IGNORE SSL ERRORS (Fixes the Localhost/Myanmar connection issue)
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    $result = json_decode($response, true);
    curl_close($ch);

    echo $result['choices'][0]['message']['content'] ?? $userInput;
}
