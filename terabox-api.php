<?php
// Enhanced CORS headers for Wasmer hosting
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

function extractTeraboxVideo($teraboxUrl) {
    try {
        // Use proven working API
        $apiUrl = 'https://ashlynn.serv00.net/Ashlynnterabox.php/?url=' . urlencode($teraboxUrl);
        
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'timeout' => 30,
                'ignore_errors' => true
            ]
        ]);
        
        $response = @file_get_contents($apiUrl, false, $context);
        
        if ($response !== false) {
            $data = json_decode($response, true);
            
            if (json_last_error() === JSON_ERROR_NONE && 
               (isset($data['downloadUrl']) || isset($data['videoUrl']))) {
                
                return [
                    'success' => true,
                    'videoUrl' => $data['downloadUrl'] ?? $data['videoUrl'],
                    'title' => $data['title'] ?? 'TeraBox Video',
                    'size' => $data['size'] ?? 'Unknown',
                    'method' => 'ashlynn_api'
                ];
            }
        }
        
        throw new Exception('API returned no valid video URL');
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// Main execution
try {
    $input = json_decode(file_get_contents('php://input'), true);
    $teraboxUrl = $input['url'] ?? $_POST['url'] ?? $_GET['url'] ?? '';
    
    if (empty($teraboxUrl)) {
        echo json_encode([
            'success' => false,
            'error' => 'No URL provided'
        ]);
        exit;
    }
    
    // Validate TeraBox URL
    $validDomains = ['terabox.com', 'terasharelink.com', 'teraboxapp.com', '1024tera.com'];
    $isValid = false;
    
    foreach ($validDomains as $domain) {
        if (stripos($teraboxUrl, $domain) !== false) {
            $isValid = true;
            break;
        }
    }
    
    if (!$isValid) {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid TeraBox URL'
        ]);
        exit;
    }
    
    $result = extractTeraboxVideo($teraboxUrl);
    echo json_encode($result);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
