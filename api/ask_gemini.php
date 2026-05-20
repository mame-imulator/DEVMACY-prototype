<?php
header('Content-Type: application/json');

// Load environment variables from .env
$envPath = __DIR__ . '/../.env';
$env = file_exists($envPath) ? parse_ini_file($envPath) : [];
$apiKey = $env['GEMINI_API_KEY'] ?? '';

if (empty($apiKey)) {
    echo json_encode(["error" => "API key not configured."]);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$user_prompt = $data['prompt'] ?? $_POST['prompt'] ?? '';

if (!empty($user_prompt)) {
    $system_prompt = "You are a clinical AI assistant for a pharmacy POS system. Briefly analyze the following symptoms or question, providing a concise clinical perspective: " . $user_prompt;

    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey;

    $payload = [
        "contents" => [
            [
                "parts" => [
                    ["text" => $system_prompt]
                ]
            ]
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        echo json_encode(["error" => "Error: " . curl_error($ch)]);
    } else {
        $response_data = json_decode($response, true);
        $result = $response_data['candidates'][0]['content']['parts'][0]['text'] ?? "Unable to fetch response.";
        echo json_encode(["response" => $result]);
    }
    curl_close($ch);
} else {
    echo json_encode(["error" => "No prompt provided."]);
}
?>
