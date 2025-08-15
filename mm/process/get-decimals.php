<?php
// ============================================================================
// File: mm/process/get-decimals.php
// Description: Get Decimals Token Solana with caching and periodic cache cleanup
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = __DIR__ . '/../../';
require_once $root_path . 'config/bootstrap.php';
require_once $root_path . 'config/csrf.php';
require_once $root_path . 'mm/network.php';
require_once $root_path . 'mm/header-auth.php';

ob_start(); // Start output buffering

// Initialize logging context
$log_context = [
    'endpoint' => 'get-decimals',
    'client_ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown',
    'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'unknown'
];

// Log request details
$session_id = session_id() ?: 'none';
$headers = apache_request_headers();
$request_method = $_SERVER['REQUEST_METHOD'];
$request_uri = $_SERVER['REQUEST_URI'];
$cookies = isset($_SERVER['HTTP_COOKIE']) ? $_SERVER['HTTP_COOKIE'] : 'none';
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
    $csrf_token = isset($_SESSION[CSRF_TOKEN_NAME]) ? $_SESSION[CSRF_TOKEN_NAME] : 'none';
    log_message("get-decimals.php: Request received, method=$request_method, uri=$request_uri, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined') . ", session_id=$session_id, cookies=$cookies, headers=" . json_encode($headers) . ", CSRF_TOKEN: $csrf_token", 'make-market.log', 'make-market', 'DEBUG', $log_context);
}

// Khởi tạo session và kiểm tra CSRF cho yêu cầu POST
if (!ensure_session()) {
    log_message("Failed to initialize session for CSRF, method=$request_method, uri=$request_uri, session_id=$session_id, cookies=$cookies", 'make-market.log', 'make-market', 'ERROR', $log_context);
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Session initialization failed'], JSON_UNESCAPED_UNICODE);
    ob_end_flush();
    exit;
}

// Kiểm tra CSRF token
try {
    csrf_protect();
} catch (Exception $e) {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'none';
    log_message("CSRF validation failed: " . $e->getMessage() . ", method=$request_method, uri=$request_uri, session_id=$session_id, user_id=$user_id", 'make-market.log', 'make-market', 'ERROR', $log_context);
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'CSRF validation failed'], JSON_UNESCAPED_UNICODE);
    ob_end_flush();
    exit;
}

define('CACHE_DIR', MAKE_MARKET_PATH . 'cache/');
define('CACHE_FILE', CACHE_DIR . 'cache-decimals.json');
define('CACHE_TTL', 24 * 60 * 60); // Cache TTL: 24 hours in seconds

// Function to read from cache and clean up expired entries
function read_from_cache($tokenMint, $network, $log_context) {
    if (!file_exists(CACHE_FILE)) {
        log_message("Cache file does not exist", 'make-market.log', 'make-market', 'NOTICE', array_merge($log_context, [
            'cache_file' => CACHE_FILE,
            'network' => defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'
        ]));
        return null;
    }

    $cache_content = file_get_contents(CACHE_FILE);
    if ($cache_content === false) {
        log_message("Failed to read cache file", 'make-market.log', 'make-market', 'ERROR', array_merge($log_context, [
            'cache_file' => CACHE_FILE,
            'error' => isset(error_get_last()['message']) ? error_get_last()['message'] : 'Unknown error'
        ]));
        return null;
    }

    $cache_data = json_decode($cache_content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        log_message("Failed to parse cache file", 'make-market.log', 'make-market', 'ERROR', array_merge($log_context, [
            'cache_file' => CACHE_FILE,
            'json_error' => json_last_error_msg(),
            'network' => defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'
        ]));
        return null;
    }

    // Remove expired cache entries
    $current_time = time();
    $initial_count = count($cache_data);
    $cache_data = array_filter($cache_data, function($entry) use ($current_time) {
        return $entry['timestamp'] + CACHE_TTL > $current_time;
    });

    // Write back cleaned cache if any entries were removed
    if (count($cache_data) !== $initial_count) {
        if (!ensure_directory_and_file(CACHE_DIR, CACHE_FILE)) {
            log_message("Failed to ensure cache directory or file for cleanup", 'make-market.log', 'make-market', 'ERROR', array_merge($log_context, [
                'cache_file' => CACHE_FILE,
                'network' => defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'
            ]));
        } elseif (file_put_contents(CACHE_FILE, json_encode($cache_data, JSON_PRETTY_PRINT), LOCK_EX) === false) {
            log_message("Failed to write cleaned cache file", 'make-market.log', 'make-market', 'ERROR', array_merge($log_context, [
                'cache_file' => CACHE_FILE,
                'network' => defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined',
                'error' => isset(error_get_last()['message']) ? error_get_last()['message'] : 'Unknown error'
            ]));
        } else {
            log_message("Cleaned up expired cache entries", 'make-market.log', 'make-market', 'INFO', array_merge($log_context, [
                'removed_entries' => ($initial_count - count($cache_data)),
                'remaining_entries' => count($cache_data),
                'network' => defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'
            ]));
        }
    }

    $cache_key = $tokenMint . '_' . $network;
    if (isset($cache_data[$cache_key]) && $cache_data[$cache_key]['timestamp'] + CACHE_TTL > $current_time) {
        return $cache_data[$cache_key]['decimals'];
    }

    return null;
}

// Function to write to cache
function write_to_cache($tokenMint, $network, $decimals, $log_context) {
    $cache_data = [];
    if (file_exists(CACHE_FILE)) {
        $cache_content = file_get_contents(CACHE_FILE);
        if ($cache_content !== false) {
            $cache_data = json_decode($cache_content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                log_message("Failed to parse existing cache file", 'make-market.log', 'make-market', 'ERROR', array_merge($log_context, [
                    'cache_file' => CACHE_FILE,
                    'json_error' => json_last_error_msg(),
                    'network' => defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'
                ]));
                $cache_data = [];
            }
        }
    }

    $cache_key = $tokenMint . '_' . $network;
    $cache_data[$cache_key] = [
        'decimals' => $decimals,
        'timestamp' => time()
    ];

    if (!ensure_directory_and_file(CACHE_DIR, CACHE_FILE)) {
        log_message("Failed to ensure cache directory or file", 'make-market.log', 'make-market', 'ERROR', array_merge($log_context, [
            'cache_file' => CACHE_FILE,
            'network' => defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'
        ]));
        return;
    }

    $json_data = json_encode($cache_data, JSON_PRETTY_PRINT);
    if ($json_data === false) {
        log_message("Failed to encode cache data", 'make-market.log', 'make-market', 'ERROR', array_merge($log_context, [
            'cache_file' => CACHE_FILE,
            'json_error' => json_last_error_msg(),
            'network' => defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'
        ]));
        return;
    }

    if (file_put_contents(CACHE_FILE, $json_data, LOCK_EX) === false) {
        log_message("Failed to write to cache file", 'make-market.log', 'make-market', 'ERROR', array_merge($log_context, [
            'cache_file' => CACHE_FILE,
            'error' => isset(error_get_last()['message']) ? error_get_last()['message'] : 'Unknown error',
            'network' => defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'
        ]));
    } else {
        log_message("Cache updated successfully", 'make-market.log', 'make-market', 'INFO', array_merge($log_context, [
            'token_mint' => $tokenMint,
            'network' => $network,
            'decimals' => $decimals,
            'server_network' => defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined',
            'cache_size' => strlen($json_data)
        ]));
    }
}

// Validate and process input
$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['tokenMint']) || !isset($data['network'])) {
    header('Content-Type: application/json');
    http_response_code(400);
    $response = ['status' => 'error', 'message' => 'Invalid or missing tokenMint or network'];
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    log_message("Invalid request data", 'make-market.log', 'make-market', 'WARNING', array_merge($log_context, [
        'input_data' => json_encode($data),
        'server_network' => defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'
    ]));
    ob_end_flush();
    exit;
}

$tokenMint = $data['tokenMint'];
$network = $data['network'];
$log_context['token_mint'] = $tokenMint;
$log_context['client_network'] = $network;

if (!in_array($network, ['testnet', 'mainnet', 'devnet'])) {
    header('Content-Type: application/json');
    http_response_code(400);
    $response = ['status' => 'error', 'message' => 'Invalid network'];
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    log_message("Invalid network specified", 'make-market.log', 'make-market', 'WARNING', array_merge($log_context, [
        'server_network' => defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'
    ]));
    ob_end_flush();
    exit;
}

// Check network consistency
if ($network !== SOLANA_NETWORK) {
    header('Content-Type: application/json');
    http_response_code(400);
    $response = ['status' => 'error', 'message' => "Network mismatch: client ($network) vs server (" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined') . ")"];
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    log_message("Network mismatch", 'make-market.log', 'make-market', 'WARNING', $log_context);
    ob_end_flush();
    exit;
}

// Check cache first
$cached_decimals = read_from_cache($tokenMint, $network, $log_context);
if ($cached_decimals !== null) {
    log_message("Cache hit", 'make-market.log', 'make-market', 'INFO', array_merge($log_context, [
        'decimals' => $cached_decimals,
        'server_network' => defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'
    ]));
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'decimals' => $cached_decimals]);
    ob_end_flush();
    exit;
}

// Use RPC_ENDPOINT from network.php
if (empty(RPC_ENDPOINT)) {
    header('Content-Type: application/json');
    http_response_code(500);
    $response = ['status' => 'error', 'message' => 'Server configuration error: Missing RPC endpoint'];
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    log_message("RPC endpoint not configured", 'make-market.log', 'make-market', 'ERROR', array_merge($log_context, [
        'server_network' => defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'
    ]));
    ob_end_flush();
    exit;
}

$maxRetries = 5;
$attempt = 0;
$rpc_timeout = 15; // seconds

while ($attempt < $maxRetries) {
    $attempt++;
    $log_context['attempt'] = $attempt;
    $log_context['max_retries'] = $maxRetries;
    
    log_message("Attempting to get token decimals from RPC", 'make-market.log', 'make-market', 'INFO', array_merge($log_context, [
        'rpc_endpoint' => RPC_ENDPOINT,
        'server_network' => defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'
    ]));

    $request_data = [
        'jsonrpc' => '2.0',
        'id' => '1',
        'method' => 'getAccountInfo',
        'params' => [$tokenMint, ['encoding' => 'jsonParsed']]
    ];
    $request_json = json_encode($request_data);
    
    $ch = curl_init(RPC_ENDPOINT);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $request_json,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($request_json)
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $rpc_timeout,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    $curl_errno = curl_errno($ch);
    curl_close($ch);

    // Log detailed request/response info
    $rpc_log_context = array_merge($log_context, [
        'http_code' => $http_code,
        'request_data' => $request_data,
        'response_length' => strlen($response),
        'rpc_timeout' => $rpc_timeout
    ]);

    if ($response === false) {
        $rpc_log_context['curl_error'] = $curl_error;
        $rpc_log_context['curl_errno'] = $curl_errno;
        
        log_message("RPC request failed", 'make-market.log', 'make-market', 'ERROR', $rpc_log_context);
        
        if ($attempt === $maxRetries) {
            header('Content-Type: application/json');
            http_response_code(500);
            $response = ['status' => 'error', 'message' => "Failed to retrieve token decimals after $maxRetries attempts: cURL error ($curl_errno): $curl_error"];
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            log_message("Final RPC attempt failed", 'make-market.log', 'make-market', 'ERROR', $rpc_log_context);
            ob_end_flush();
            exit;
        }
        
        sleep($attempt); // Exponential backoff
        continue;
    }

    if ($http_code !== 200) {
        $rpc_log_context['response_body'] = substr($response, 0, 500); // Log first 500 chars of response
        
        log_message("RPC returned non-200 status", 'make-market.log', 'make-market', 'ERROR', $rpc_log_context);
        
        if ($attempt === $maxRetries) {
            header('Content-Type: application/json');
            http_response_code(502); // Bad Gateway
            $response = ['status' => 'error', 'message' => "Failed to retrieve token decimals after $maxRetries attempts: RPC returned HTTP $http_code"];
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            log_message("Final RPC attempt failed with non-200 status", 'make-market.log', 'make-market', 'ERROR', $rpc_log_context);
            ob_end_flush();
            exit;
        }
        
        sleep($attempt);
        continue;
    }

    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $rpc_log_context['json_error'] = json_last_error_msg();
        $rpc_log_context['response_sample'] = substr($response, 0, 200);
        
        log_message("Failed to decode RPC response", 'make-market.log', 'make-market', 'ERROR', $rpc_log_context);
        
        if ($attempt === $maxRetries) {
            header('Content-Type: application/json');
            http_response_code(502);
            $response = ['status' => 'error', 'message' => "Failed to parse RPC response after $maxRetries attempts"];
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            log_message("Final RPC parse attempt failed", 'make-market.log', 'make-market', 'ERROR', $rpc_log_context);
            ob_end_flush();
            exit;
        }
        
        sleep($attempt);
        continue;
    }

    // Log successful response (debug level)
    log_message("RPC response received", 'make-market.log', 'make-market', 'DEBUG', array_merge($rpc_log_context, [
        'result_keys' => array_keys($result),
        'has_value' => isset($result['result']['value'])
    ]));

    if (!isset($result['result']['value']['data']['parsed']['type']) || $result['result']['value']['data']['parsed']['type'] !== 'mint') {
        $error_type = isset($result['result']['value']['data']['parsed']['type']) ? 
            "Invalid account type: " . $result['result']['value']['data']['parsed']['type'] : 
            "No valid account data";
        
        log_message("Invalid token account data", 'make-market.log', 'make-market', 'ERROR', array_merge($rpc_log_context, [
            'error_type' => $error_type
        ]));
        
        header('Content-Type: application/json');
        http_response_code(400);
        $response = ['status' => 'error', 'message' => $error_type];
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        ob_end_flush();
        exit;
    }

    $decimals = isset($result['result']['value']['data']['parsed']['info']['decimals']) ? $result['result']['value']['data']['parsed']['info']['decimals'] : 9;
    log_message("Successfully retrieved token decimals", 'make-market.log', 'make-market', 'INFO', array_merge($log_context, [
        'decimals' => $decimals,
        'final_attempt' => $attempt
    ]));

    // Save to cache
    write_to_cache($tokenMint, $network, $decimals, $log_context);

    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'decimals' => $decimals]);
    ob_end_flush();
    exit;
}
?>
