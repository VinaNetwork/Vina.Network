<?php
// ============================================================================
// File: tools/nft-creator/nft-creator.php
// Description: Check NFTs and Collections created by a Solana wallet address.
// Created by: Vina Network
// ============================================================================

// Log sớm để debug
error_log("[" . date('Y-m-d H:i:s') . "] [INFO] nft_creator: Starting nft-creator.php", 3, '/var/www/vinanetwork/public_html/tools/logs/nft_creator_log.txt');

// Define constants
if (!defined('VINANETWORK')) define('VINANETWORK', true);
if (!defined('VINANETWORK_ENTRY')) define('VINANETWORK_ENTRY', true);

// Load bootstrap
try {
    require_once dirname(__DIR__) . '/bootstrap.php';
    log_message("nft_creator: bootstrap.php loaded", 'nft_creator_log.txt', 'INFO');
} catch (Throwable $e) {
    error_log("[" . date('Y-m-d H:i:s') . "] [CRITICAL] nft_creator: bootstrap.php error: " . $e->getMessage() . ", File: " . $e->getFile() . ", Line: " . $e->getLine(), 3, '/var/www/vinanetwork/public_html/tools/logs/nft_creator_log.txt');
    http_response_code(500);
    echo '<div class="result-error"><p>Error loading bootstrap: ' . htmlspecialchars($e->getMessage()) . '</p></div>';
    exit;
}

// Cache directory and file
try {
    if (!defined('NFT_CREATOR_PATH')) {
        error_log("[" . date('Y-m-d H:i:s') . "] [CRITICAL] nft_creator: NFT_CREATOR_PATH not defined", 3, '/var/www/vinanetwork/public_html/tools/logs/nft_creator_log.txt');
        http_response_code(500);
        echo '<div class="result-error"><p>NFT_CREATOR_PATH not defined</p></div>';
        exit;
    }
    $cache_dir = NFT_CREATOR_PATH . 'cache/';
    $cache_file = $cache_dir . 'nft_creator_cache.json';
    if (!ensure_directory_and_file($cache_dir, $cache_file, 'nft_creator_log.txt')) {
        log_message("nft_creator: Cache setup failed for $cache_dir or $cache_file", 'nft_creator_log.txt', 'ERROR');
        http_response_code(500);
        echo '<div class="result-error"><p>Cache setup failed</p></div>';
        exit;
    }
    log_message("nft_creator: Cache setup completed, cache_dir=$cache_dir", 'nft_creator_log.txt', 'INFO');
} catch (Throwable $e) {
    error_log("[" . date('Y-m-d H:i:s') . "] [CRITICAL] nft_creator: Cache setup error: " . $e->getMessage() . ", File: " . $e->getFile() . ", Line: " . $e->getLine(), 3, '/var/www/vinanetwork/public_html/tools/logs/nft_creator_log.txt');
    http_response_code(500);
    echo '<div class="result-error"><p>Cache setup error: ' . htmlspecialchars($e->getMessage()) . '</p></div>';
    exit;
}

// Load API helper
try {
    require_once dirname(__DIR__) . '/tools-api.php';
    log_message("nft_creator: tools-api.php loaded", 'nft_creator_log.txt', 'INFO');
} catch (Throwable $e) {
    error_log("[" . date('Y-m-d H:i:s') . "] [CRITICAL] nft_creator: tools-api.php error: " . $e->getMessage() . ", File: " . $e->getFile() . ", Line: " . $e->getLine(), 3, '/var/www/vinanetwork/public_html/tools/logs/nft_creator_log.txt');
    http_response_code(500);
    echo '<div class="result-error"><p>Error loading tools-api: ' . htmlspecialchars($e->getMessage()) . '</p></div>';
    exit;
}
?>

<link rel="stylesheet" href="/tools/nft-creator/nft-creator.css">
<div class="nft-creator">
    <!-- Render form unless rate limit exceeded -->
    <?php
    try {
        $rate_limit_exceeded = false;
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['creatorAddress'])) {
            // Rate limiting: 5 requests per minute per IP
            $ip = $_SERVER['REMOTE_ADDR'];
            $rate_limit_key = "rate_limit_nft_creator:$ip";
            $rate_limit_count = isset($_SESSION[$rate_limit_key]) ? $_SESSION[$rate_limit_key]['count'] : 0;
            $rate_limit_time = isset($_SESSION[$rate_limit_key]) ? $_SESSION[$rate_limit_key]['time'] : 0;
            if (time() - $rate_limit_time > 60) {
                $_SESSION[$rate_limit_key] = ['count' => 1, 'time' => time()];
                log_message("nft_creator: Reset rate limit for IP=$ip, count=1", 'nft_creator_log.txt', 'INFO');
            } elseif ($rate_limit_count >= 5) {
                $rate_limit_exceeded = true;
                log_message("nft_creator: Rate limit exceeded for IP=$ip, count=$rate_limit_count", 'nft_creator_log.txt', 'ERROR');
                echo "<div class='result-error'><p>Rate limit exceeded. Please try again in a minute.</p></div>";
            } else {
                $_SESSION[$rate_limit_key]['count']++;
                log_message("nft_creator: Incremented rate limit for IP=$ip, count=" . $_SESSION[$rate_limit_key]['count'], 'nft_creator_log.txt', 'INFO');
            }
        }

        if (!$rate_limit_exceeded) {
            log_message("nft_creator: Rendering form", 'nft_creator_log.txt', 'INFO');
            ?>
            <div class="tools-form">
                <h2>Check NFT Creator</h2>
                <p>Enter the <strong>Solana Wallet Address</strong> to view all NFTs and Collections created by this address. For example, find the creator address on MagicEden or other Solana marketplaces.</p>
                <form id="nftCreatorForm" method="POST" action="" data-tool="nft-creator">
                    <input type="hidden" name="csrf_token" value="<?php echo function_exists('generate_csrf_token') ? generate_csrf_token() : ''; ?>">
                    <div class="input-wrapper">
                        <input type="text" name="creatorAddress" id="creatorAddress" placeholder="Enter Solana Creator Address" required value="<?php echo isset($_POST['creatorAddress']) ? htmlspecialchars($_POST['creatorAddress']) : ''; ?>">
                        <span class="clear-input" title="Clear input">×</span>
                    </div>
                    <button type="submit" class="cta-button">Check</button>
                </form>
                <div class="loader"></div>
            </div>
            <?php
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['creatorAddress']) && !$rate_limit_exceeded) {
            log_message("nft_creator: POST request received, creatorAddress=" . ($_POST['creatorAddress'] ?? 'N/A'), 'nft_creator_log.txt', 'INFO');
            if (isset($_POST['csrf_token']) && function_exists('validate_csrf_token') && !validate_csrf_token($_POST['csrf_token'])) {
                log_message("nft_creator: Invalid CSRF token", 'nft_creator_log.txt', 'ERROR');
                throw new Exception('Invalid CSRF token');
            }

            $creatorAddress = trim($_POST['creatorAddress']);
            $creatorAddress = preg_replace('/\s+/', '', $creatorAddress);
            log_message("nft_creator: Validating creatorAddress=$creatorAddress", 'nft_creator_log.txt', 'INFO');
            if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $creatorAddress)) {
                log_message("nft_creator: Invalid Creator Address format", 'nft_creator_log.txt', 'ERROR');
                throw new Exception('Invalid Creator Address format');
            }

            $cache_data = json_decode(file_get_contents($cache_file), true) ?? [];
            $cache_expiration = 3 * 3600;
            $cache_key = $creatorAddress;
            $cache_valid = isset($cache_data[$cache_key]) && (time() - $cache_data[$cache_key]['timestamp'] < $cache_expiration);
            log_message("nft_creator: Cache valid=$cache_valid for creatorAddress=$creatorAddress", 'nft_creator_log.txt', 'INFO');

            if (!$cache_valid) {
                log_message("nft_creator: Calling getAssetsByCreator API for creatorAddress=$creatorAddress", 'nft_creator_log.txt', 'INFO');
                $params = [
                    'creatorAddress' => $creatorAddress,
                    'onlyVerified' => false,
                    'page' => 1,
                    'limit' => 1000,
                    'sortBy' => ['sortBy' => 'created', 'sortDirection' => 'asc']
                ];
                $response = callAPI('getAssetsByCreator', $params, 'POST');

                log_message("nft_creator: API raw response=" . json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), 'nft_creator_log.txt', 'DEBUG');
                $response_keys = array_keys($response);
                log_message("nft_creator: API response keys=" . implode(', ', $response_keys), 'nft_creator_log.txt', 'DEBUG');

                if (isset($response['error'])) {
                    $error_msg = is_array($response['error']) ? ($response['error']['message'] ?? 'API error') : $response['error'];
                    log_message("nft_creator: API error: $error_msg", 'nft_creator_log.txt', 'ERROR');
                    throw new Exception($error_msg);
                }

                $items = $response['items'] ?? ($response['result'] ?? []);
                log_message("nft_creator: API returned " . count($items) . " items", 'nft_creator_log.txt', 'INFO');
                if (empty($items)) {
                    log_message("nft_creator: Empty result for creatorAddress=$creatorAddress", 'nft_creator_log.txt', 'ERROR');
                    throw new Exception('No NFTs or Collections found for this creator');
                }

                log_message("nft_creator: First item=" . json_encode($items[0] ?? [], JSON_PRETTY_PRINT), 'nft_creator_log.txt', 'DEBUG');

                $formatted_data = [];
                foreach ($items as $index => $asset) {
                    $is_collection = empty($asset['grouping']) || !isset($asset['grouping'][0]['group_value']);
                    $name = $asset['content']['metadata']['name'] ?? ($asset['metadata']['name'] ?? ($asset['name'] ?? ($asset['content']['name'] ?? 'Unnamed NFT')));
                    $image = $asset['content']['links']['image'] ?? ($asset['image_url'] ?? ($asset['image'] ?? ($asset['content']['image'] ?? '')));
                    $formatted_data[] = [
                        'asset_id' => $asset['id'] ?? 'N/A',
                        'name' => $name,
                        'image' => $image,
                        'collection' => $is_collection ? ($asset['id'] ?? 'N/A') : ($asset['grouping'][0]['group_value'] ?? 'N/A'),
                        'royalty' => isset($asset['royalty']['percent']) ? number_format($asset['royalty']['percent'] * 100, 2) . '%' : ($asset['royalty'] ?? 'N/A'),
                        'verified' => isset($asset['creators'][0]['verified']) && $asset['creators'][0]['verified'] ? 'Yes' : 'No'
                    ];
                    log_message("nft_creator: Asset $index: ID={$asset['id'] ?? 'N/A'}, Name=$name", 'nft_creator_log.txt', 'DEBUG');
                }

                $cache_data[$cache_key] = [
                    'data' => $formatted_data,
                    'timestamp' => time()
                ];
                $fp = fopen($cache_file, 'c');
                if (flock($fp, LOCK_EX)) {
                    if (!file_put_contents($cache_file, json_encode($cache_data, JSON_PRETTY_PRINT))) {
                        log_message("nft_creator: Failed to write to cache file", 'nft_creator_log.txt', 'ERROR');
                        flock($fp, LOCK_UN);
                        fclose($fp);
                        throw new Exception('Failed to write to cache file');
                    }
                    flock($fp, LOCK_UN);
                } else {
                    log_message("nft_creator: Failed to lock cache file", 'nft_creator_log.txt', 'ERROR');
                    fclose($fp);
                    throw new Exception('Failed to lock cache file');
                }
                fclose($fp);
                log_message("nft_creator: Cache updated for creatorAddress=$creatorAddress", 'nft_creator_log.txt', 'INFO');
            } else {
                $formatted_data = $cache_data[$cache_key]['data'];
                log_message("nft_creator: Retrieved from cache for creatorAddress=$creatorAddress", 'nft_creator_log.txt', 'INFO');
            }

            ob_start();
            ?>
            <div class="tools-result nft-creator-result">
                <h2>NFTs and Collections by Creator</h2>
                <div class="result-summary">
                    <div class="nft-grid">
                        <?php foreach ($formatted_data as $asset): ?>
                            <div class="result-card">
                                <div class="nft-image">
                                    <?php if ($asset['image']): ?>
                                        <img src="<?php echo htmlspecialchars($asset['image']); ?>" alt="NFT Image">
                                    <?php else: ?>
                                        <p>No image available</p>
                                    <?php endif; ?>
                                </div>
                                <div class="nft-info-table">
                                    <table>
                                        <tr>
                                            <th>Asset ID</th>
                                            <td>
                                                <span><?php echo substr(htmlspecialchars($asset['asset_id']), 0, 4) . '...' . substr(htmlspecialchars($asset['asset_id']), -4); ?></span>
                                                <i class="fas fa-copy copy-icon" title="Copy full address" data-full="<?php echo htmlspecialchars($asset['asset_id']); ?>"></i>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Name</th>
                                            <td><?php echo htmlspecialchars($asset['name']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Collection</th>
                                            <td>
                                                <?php if ($asset['collection'] !== 'N/A' && preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $asset['collection'])): ?>
                                                    <span><?php echo substr(htmlspecialchars($asset['collection']), 0, 4) . '...' . substr(htmlspecialchars($asset['collection']), -4); ?></span>
                                                    <i class="fas fa-copy copy-icon" title="Copy full address" data-full="<?php echo htmlspecialchars($asset['collection']); ?>"></i>
                                                <?php else: ?>
                                                    <span>N/A</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Royalty</th>
                                            <td><?php echo htmlspecialchars($asset['royalty']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Verified</th>
                                            <td><?php echo htmlspecialchars($asset['verified']); ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php if ($cache_valid): ?>
                    <p class="cache-timestamp">Last updated: <?php echo date('d M Y, H:i', $cache_data[$cache_key]['timestamp']) . ' UTC+0'; ?>. Data will be updated every 3 hours.</p>
                <?php endif; ?>
            </div>
            <?php
            $output = ob_get_clean();
            log_message("nft_creator: Output length: " . strlen($output), 'nft_creator_log.txt', 'INFO');
            echo $output;
        }
    } catch (Throwable $e) {
        $error_msg = "Error processing request: " . $e->getMessage() . ", File: " . $e->getFile() . ", Line: " . $e->getLine();
        log_message("nft_creator: Exception: $error_msg", 'nft_creator_log.txt', 'ERROR');
        http_response_code(500);
        echo "<div class='result-error'><p>$error_msg</p></div>";
    }
    log_message("nft_creator: Script ended", 'nft_creator_log.txt', 'INFO');
    ?>

    <div class="tools-about">
        <h2>About Check NFT Creator</h2>
        <p>The Check NFT Creator tool allows you to view all NFTs and Collections created by a specific Solana wallet address. For example, find the creator address on MagicEden or other Solana marketplaces.</p>
    </div>
</div>
