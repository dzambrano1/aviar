<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// ChatPDF API configuration
$apiKey = 'sec_AdQUXMlHjjhyrwud6dGCP9DFtUt8ZS7T';
$baseUrl = 'https://api.chatpdf.com/v1';

// Get the action from query parameter
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'upload':
            handleUpload($apiKey, $baseUrl);
            break;
        case 'chat':
            handleChat($apiKey, $baseUrl);
            break;
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

function handleUpload($apiKey, $baseUrl) {
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error');
    }

    $file = $_FILES['file'];
    
    // Validate file type
    if ($file['type'] !== 'application/pdf') {
        throw new Exception('Only PDF files are allowed');
    }

    // Prepare the file for upload
    $cFile = new CURLFile($file['tmp_name'], $file['type'], $file['name']);
    
    // Prepare cURL for ChatPDF API
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $baseUrl . '/sources/add-file',
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => ['file' => $cFile],
        CURLOPT_HTTPHEADER => [
            'x-api-key: ' . $apiKey,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_error($ch)) {
        curl_close($ch);
        throw new Exception('cURL Error: ' . curl_error($ch));
    }
    
    curl_close($ch);

    if ($httpCode !== 200) {
        $errorData = json_decode($response, true);
        throw new Exception($errorData['error'] ?? 'Error uploading to ChatPDF API');
    }

    $responseData = json_decode($response, true);
    
    if (!isset($responseData['sourceId'])) {
        throw new Exception('Invalid response from ChatPDF API');
    }

    echo json_encode([
        'success' => true,
        'sourceId' => $responseData['sourceId']
    ]);
}

function handleChat($apiKey, $baseUrl) {
    // Get JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data || !isset($data['sourceId']) || !isset($data['messages'])) {
        throw new Exception('Invalid request data');
    }

    // Prepare the request for ChatPDF API
    $postData = [
        'sourceId' => $data['sourceId'],
        'messages' => $data['messages']
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $baseUrl . '/chats/message',
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($postData),
        CURLOPT_HTTPHEADER => [
            'x-api-key: ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 60
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_error($ch)) {
        curl_close($ch);
        throw new Exception('cURL Error: ' . curl_error($ch));
    }
    
    curl_close($ch);

    if ($httpCode !== 200) {
        $errorData = json_decode($response, true);
        throw new Exception($errorData['error'] ?? 'Error communicating with ChatPDF API');
    }

    $responseData = json_decode($response, true);
    
    if (!isset($responseData['content'])) {
        throw new Exception('Invalid response from ChatPDF API');
    }

    echo json_encode([
        'success' => true,
        'content' => $responseData['content']
    ]);
}
?> 