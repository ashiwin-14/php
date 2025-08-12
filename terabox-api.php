<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

function extractTeraboxVideo($teraboxUrl) {
    // Multiple extraction methods for maximum reliability
    $methods = [
        'ashlynn_api' => function($url) { return extractViaAshlynn($url); },
        'direct_scraping' => function($url) { return extractViaScraping($url); },
        'proxy_method' => function($url) { return extractViaProxy($url); },
        'backup_api' => function($url) { return extractViaBackupAPI($url); }
    ];
    
    foreach ($methods as $methodName => $method) {
        try {
            $result = $method($teraboxUrl);
            if ($result && isset($result['videoUrl']) && !empty($result['videoUrl'])) {
                $result['method'] = $methodName;
                $result['success'] = true;
                return $result;
            }
        } catch (Exception $e) {
            error_log("Method $methodName failed: " . $e->getMessage());
            continue;
        }
    }
    
    return ['error' => 'All extraction methods failed', 'success' => false];
}

function extractViaAshlynn($teraboxUrl) {
    // Primary working API
    $apiUrl = 'https://ashlynn.serv00.net/Ashlynnterabox.php/?url=' . urlencode($teraboxUrl);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        
        if (json_last_error() === JSON_ERROR_NONE && 
           (isset($data['downloadUrl']) || isset($data['videoUrl']) || isset($data['directUrl']))) {
            
            $videoUrl = $data['downloadUrl'] ?? $data['videoUrl'] ?? $data['directUrl'];
            
            if (!empty($videoUrl)) {
                return [
                    'videoUrl' => $videoUrl,
                    'title' => $data['title'] ?? $data['filename'] ?? 'TeraBox Video',
                    'size' => formatFileSize($data['size'] ?? $data['fileSize'] ?? 0),
                    'thumbnail' => $data['thumbnail'] ?? $data['thumb'] ?? null
                ];
            }
        }
    }
    
    throw new Exception('Ashlynn API failed or returned invalid data');
}

function extractViaBackupAPI($teraboxUrl) {
    // Backup API method
    $apiUrl = 'https://teraboxdownloader.com/api/extract';
    $postData = json_encode(['url' => $teraboxUrl]);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        
        if (json_last_error() === JSON_ERROR_NONE && 
           (isset($data['directUrl']) || isset($data['downloadUrl']))) {
            
            $videoUrl = $data['directUrl'] ?? $data['downloadUrl'];
            
            if (!empty($videoUrl)) {
                return [
                    'videoUrl' => $videoUrl,
                    'title' => $data['filename'] ?? $data['title'] ?? 'TeraBox Video',
                    'size' => formatFileSize($data['fileSize'] ?? 0)
                ];
            }
        }
    }
    
    throw new Exception('Backup API failed');
}

function extractViaScraping($teraboxUrl) {
    // Direct scraping method
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $teraboxUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $content = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $content) {
        // Extract video URLs using multiple patterns
        $patterns = [
            '/videoUrl["\']?\s*:\s*["\']([^"\']+)["\']/',
            '/dlink["\']?\s*:\s*["\']([^"\']+)["\']/',
            '/"(https:\/\/[^"]*\.mp4[^"]*)"/',
            '/downloadUrl["\']?\s*:\s*["\']([^"\']+)["\']/'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $videoUrl = $matches[1];
                if (!empty($videoUrl) && filter_var($videoUrl, FILTER_VALIDATE_URL)) {
                    return [
                        'videoUrl' => $videoUrl,
                        'title' => extractTitle($content),
                        'size' => 'Unknown'
                    ];
                }
            }
        }
    }
    
    throw new Exception('Scraping method failed');
}

function extractViaProxy($teraboxUrl) {
    // CORS proxy method
    $proxyUrl = 'https://api.allorigins.win/get?url=' . urlencode($teraboxUrl);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $proxyUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response) {
        $data = json_decode($response, true);
        
        if (isset($data['contents'])) {
            // Look for video URLs in the content
            if (preg_match('/(https:\/\/[^"\'\\s]+\.(?:mp4|m3u8|webm)(?:\?[^"\'\\s]*)?)/i', $data['contents'], $matches)) {
                return [
                    'videoUrl' => $matches[1],
                    'title' => 'TeraBox Video',
                    'size' => 'Unknown'
                ];
            }
        }
    }
    
    throw new Exception('Proxy method failed');
}

function extractTitle($content) {
    // Extract title from HTML content
    $patterns = [
        '/<title>([^<]+)<\/title>/i',
        '/<meta[^>]*property=["\']og:title["\'][^>]*content=["\']([^"\']+)["\'][^>]*>/i',
        '/<h1[^>]*>([^<]+)<\/h1>/i'
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $content, $matches)) {
            return trim(strip_tags($matches[1]));
        }
    }
    
    return 'TeraBox Video';
}

function formatFileSize($bytes) {
    if ($bytes == 0) return 'Unknown';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
}

function parseTeraboxUrl($url) {
    // Extract share ID from various TeraBox URL formats
    $patterns = [
        '/\/s\/([^\/\?]+)/',           // /s/xxxxx
        '/surl=([^&?#]+)/',           // surl=xxxxx  
        '/\/share\/([^\/\?]+)/',      // /share/xxxxx
        '/\/link\/([^\/\?]+)/',       // /link/xxxxx
        '/\/shared\/([^\/\?]+)/'      // /shared/xxxxx
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }
    }
    
    return null;
}

// Main execution
try {
    // Get input
    $input = json_decode(file_get_contents('php://input'), true);
    $teraboxUrl = $input['url'] ?? $_POST['url'] ?? $_GET['url'] ?? '';
    
    if (empty($teraboxUrl)) {
        echo json_encode(['error' => 'No URL provided', 'success' => false]);
        exit;
    }
    
    // Validate TeraBox URL
    $validDomains = [
        'terabox.com', '1024tera.com', 'teraboxapp.com', 
        'terasharelink.com', 'teraboxlink.com', 'nephobox.com',
        'terafileshare.com', 'mirrobox.com'
    ];
    
    $isValid = false;
    foreach ($validDomains as $domain) {
        if (strpos(strtolower($teraboxUrl), strtolower($domain)) !== false) {
            $isValid = true;
            break;
        }
    }
    
    if (!$isValid) {
        echo json_encode(['error' => 'Invalid TeraBox URL', 'success' => false]);
        exit;
    }
    
    // Extract video
    $result = extractTeraboxVideo($teraboxUrl);
    echo json_encode($result);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Server error: ' . $e->getMessage(), 'success' => false]);
}
?>
