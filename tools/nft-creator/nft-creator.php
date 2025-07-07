<?php
// ============================================================================
// File: tools/nft-creator/nft-creator.php
// Description: Check NFTs and Collections created by a Solana wallet address.
// Created by: Vina Network
// ============================================================================

// Define constants
if (!defined('VINANETWORK')) define('VINANETWORK', true);
if (!defined('VINANETWORK_ENTRY')) define('VINANETWORK_ENTRY', true);

// Load bootstrap
$bootstrap_path = dirname(__DIR__) . '/bootstrap.php';
if (!file_exists($bootstrap_path)) {
    echo '<div class="result-error"><p>Cannot find bootstrap.php</p></div>';
    exit;
}
require_once $bootstrap_path;
$log_file = dirname(__DIR__) . '/logs/nft_creator_log.txt';
$can_log = is_writable($log_file) || (!file_exists($log_file) && is_writable(dirname($log_file)));
if ($can_log) {
    log_message("nft_creator: bootstrap.php loaded", 'nft_creator_log.txt', 'DEBUG');
} else {
    @file_put_contents(NFT_CREATOR_PATH . 'cache/debug_log.txt', "[" . date('Y-m-d H:i:s') . "] [ERROR] Cannot write to nft_creator_log.txt\n", FILE_APPEND);
}

// Cache directory and file
$cache_dir = NFT_CREATOR_PATH . 'cache/';
$cache_file = $cache_dir . 'nft_creator_cache.json';

// Check and create cache directory and file
if (!ensure_directory_and_file($cache_dir, $cache_file, 'nft_creator_log.txt')) {
    if ($can_log) {
        log_message("nft_creator: Cache setup failed for $cache_dir or $cache_file", 'nft_creator_log.txt', 'ERROR');
    } else {
        @file_put_contents($cache_dir . 'debug_log.txt', "[" . date('Y-m-d H:i:s') . "] [ERROR] Cache setup failed for $cache_dir or $cache_file\n", FILE_APPEND);
    }
    echo '<div class="result-error"><p>Cache setup failed</p></div>';
    exit;
}
if ($can_log) {
    log_message("nft_creator: Cache setup completed", 'nft_creator_log.txt', 'DEBUG');
}

// Load API helper
$api_helper_path = dirname(__DIR__) . '/tools-api.php';
if (!file_exists($api_helper_path)) {
    if ($can_log) {
        log_message("nft_creator: tools-api.php not found at $api_helper_path", 'nft_creator_log.txt', 'ERROR');
    } else {
        @file_put_contents($cache_dir . 'debug_log.txt', "[" . date('Y-m-d H:i:s') . "] [ERROR] tools-api.php not found at $api_helper_path\n", FILE_APPEND);
    }
    echo '<div class="result-error"><p>Server error: Missing tools-api.php</p></div>';
    exit;
}
require_once $api_helper_path;
if ($can_log) {
    log_message("nft_creator: tools-api.php loaded", 'nft_creator_log.txt', 'DEBUG');
}
?>

<div class="nft-creator">
    <!-- Render form unless rate limit exceeded -->
    <?php
    $rate_limit_exceeded = false;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['creatorAddress'])) {
        // Rate limiting: 5 requests per minute per IP
        $ip = $_SERVER['REMOTE_ADDR'];
        $rate_limit_key = "rate_limit_nft_creator:$ip";
        $rate_limit_count = isset($_SESSION[$rate_limit_key]) ? $_SESSION[$rate_limit_key]['count'] : 0;
        $rate_limit_time = isset($_SESSION[$rate_limit_key]) ? $_SESSION[$rate_limit_key]['time'] : 0;
        if (time() - $rate_limit_time > 60) {
            $_SESSION[$rate_limit_key] = ['count' => 1, 'time' => time()];
            if ($can_log) {
                log_message("nft_creator: Reset rate limit for IP=$ip, count=1", 'nft_creator_log.txt', 'INFO');
            } else {
                @file_put_contents($cache_dir . 'debug_log.txt', "[" . date('Y-m-d H:i:s') . "] [INFO] Reset rate limit for IP=$ip, count=1\n", FILE_APPEND);
            }
        } elseif ($rate_limit_count >= 5) {
            $rate_limit_exceeded = true;
            if ($can_log) {
                log_message("nft_creator: Rate limit exceeded for IP=$ip, count=$rate_limit_count", 'nft_creator_log.txt', 'ERROR');
            } else {
                @file_put_contents($cache_dir . 'debug_log.txt', "[" . date('Y-m-d H:i:s') . "] [ERROR] Rate limit exceeded for IP=$ip, count=$rate_limit_count\n", FILE_APPEND);
            }
            echo "<div class='result-error'><p>Rate limit exceeded. Please try again in a minute.</p></div>";
        } else {
            $_SESSION[$rate_limit_key]['count']++;
            if ($can_log) {
                log_message("nft_creator: Incremented rate limit for IP=$ip, count=" . $_SESSION[$rate_limit_key]['count'], 'nft_creator_log.txt', 'INFO');
            } else {
                @file_put_contents($cache_dir . 'debug_log.txt', "[" . date('Y-m-d H:i:s') . "] [INFO] Incremented rate limit for IP=$ip, count=" . $_SESSION[$rate_limit_key]['count'] . "\n", FILE_APPEND);
            }
        }
    }

    if (!$rate_limit_exceeded) {
        if ($can_log) {
            log_message("nft_creator: Rendering form", 'nft_creator_log.txt', 'INFO');
        } else {
            @file_put_contents($cache_dir . 'debug_log.txt', "[" . date('Y-m-d H:i:s') . "] [INFO] Rendering form\n", FILE_APPEND);
        }
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
        try {
            // Skip CSRF validation if function is missing
            if (isset($_POST['csrf_token']) && function_exists('validate_csrf_token') && !validate_csrf_token($_POST['csrf_token'])) {
                if ($can_log) {
                    log_message("nft_creator: Invalid CSRF token", 'nft_creator_log.txt', 'ERROR');
                } else {
                    @file_put_contents($cache_dir . 'debug_log.txt', "[" . date('Y-m-d H:i:s') . "] [ERROR] Invalid CSRF token\n", FILE_APPEND);
                }
                throw new Exception('Invalid CSRF token');
            }

            $creatorAddress = trim($_POST['creatorAddress']);
            $creatorAddress = preg_replace('/\s+/', '', $creatorAddress);
            if ($can_log) {
                log_message("nft_creator: Validating creatorAddress=$creatorAddress", 'nft_creator_log.txt', 'INFO');
            } else {
                @file_put_contents($cache_dir . 'debug_log.txt', "[" . date('Y-m-d H:i:s') . "] [INFO] Validating creatorAddress=$creatorAddress\n", FILE_APPEND);
            }
            if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $creatorAddress)) {
                if ($can_log) {
                    log_message("nft_creator: Invalid Creator Address format", 'nft_creator_log.txt', 'ERROR');
                } else {
                    @file_put_contents($cache_dir . 'debug_log.txt', "[" . date('Y-m-d H:i:s') . "] [ERROR] Invalid Creator Address format\n", FILE_APPEND);
                }
                throw new Exception('Invalid Creator Address format');
            }

            // Clear cache for this address
            $cache_data = @json_decode(@file_get_contents($cache_file), true) ?? [];
            $cache_key = $creatorAddress;
            unset($cache_data[$cache_key]);
            @file_put_contents($cache_file, json_encode($cache_data, JSON_PRETTY_PRINT));
            if ($can_log) {
                log_message("nft_creator: Cache cleared for creatorAddress=$creatorAddress", 'nft_creator_log.txt', 'INFO');
            } else {
                @file_put_contents($cache_dir . 'debug_log.txt', "[" . date('Y-m-d H:i:s') . "] [INFO] Cache cleared for creatorAddress=$creatorAddress\n", FILE_APPEND);
            }

            $params = [
                'creatorAddress' => $creatorAddress,
                'onlyVerified' => false,
                'page' => 1,
                'limit' => 1000,
                'sortBy' => ['sortBy' => 'created', 'sortDirection' => 'asc']
            ];
            if ($can_log) {
                log_message("nft_creator: Calling getAssetsByCreator API for creatorAddress=$creatorAddress", 'nft_creator_log.txt', 'INFO');
            } else {
                @file_put_contents($cache_dir . 'debug_log.txt', "[" . date('Y-m-d H:i:s') . "] [INFO] Calling getAssetsByCreator API for creatorAddress=$creatorAddress\n", FILE_APPEND);
            }
            $response = callAPI('getAssetsByCreator', $params, 'POST');

            // Debug: Save raw API response
            @file_put_contents($cache_dir . 'api_response_debug.json', json_encode($response, JSON_PRETTY_PRINT));

            if (isset($response['error'])) {
                $error_msg = is_array($response['error']) ? ($response['error']['message'] ?? 'API error') : $response['error'];
                if ($can_log) {
                    log_message("nft_creator: API error: $error_msg", 'nft_creator_log.txt', 'ERROR');
                } else {
                    @file_put_contents($cache_dir . 'debug_log.txt', "[" . date('Y-m-d H:i:s') . "] [ERROR] API error: $error_msg\n", FILE_APPEND);
                }
                throw new Exception($error_msg);
            }

            $items = $response['items'] ?? ($response['result'] ?? []);
            if ($can_log) {
                log_message("nft_creator: API returned " . count($items) . " items", 'nft_creator_log.txt', 'INFO');
            } else {
                @file_put_contents($cache_dir . 'debug_log.txt', "[" . date('Y-m-d H:i:s') . "] [INFO] API returned " . count($items) . " items\n", FILE_APPEND);
            }
            if (empty($items)) {
                if ($can_log) {
                    log_message("nft_creator: Empty result for creatorAddress=$creatorAddress", 'nft_creator_log.txt', 'ERROR');
                } else {
                    @file_put_contents($cache_dir . 'debug_log.txt', "[" . date('Y-m-d H:i:s') . "] [ERROR] Empty result for creatorAddress=$creatorAddress\n", FILE_APPEND);
                }
                throw new Exception('No NFTs or Collections found for this creator');
            }

            // Debug: Log structure of first item
            @file_put_contents($cache_dir . 'api_item_debug.json', json_encode($items[0] ?? [], JSON_PRETTY_PRINT));

            // Accept all assets (no interface filter)
            $assets = $items;

            $formatted_data = [];
            foreach ($assets as $index => $asset) {
                $is_collection = empty($asset['grouping']) || !isset($asset['grouping'][0]['group_value']);
                $formatted_data[] = [
                    'asset_id' => $asset['id'] ?? 'N/A',
                    'name' => $asset['content']['metadata']['name'] ?? ($asset['name'] ?? 'Unnamed NFT'),
                    'image' => $asset['content']['links']['image'] ?? ($asset['image'] ?? ''),
                    'collection' => $is_collection ? ($asset['id'] ?? 'N/A') : ($asset['grouping'][0]['group_value'] ?? 'N/A'),
                    'royalty' => isset($asset['royalty']['percent']) ? number_format($asset['royalty']['percent'] * 100, 2) . '%' : ($asset['royalty'] ?? 'N/A'),
                    'verified' => isset($asset['creators'][0]['verified']) && $asset['creators'][0]['verified'] ? 'Yes' : 'No'
                ];
                if ($can_log) {
                    log_message("nft_creator: Asset $index: ID={$asset['id'] ?? 'N/A'}, Name=" . ($asset['content']['metadata']['name'] ?? ($asset['name'] ?? 'N/A')), 'nft_creator_log.txt', 'DEBUG');
                } else {
                    @file_put_contents($cache_dir . 'debug_log.txt', "[" . date('Y-m-d H:i:s') . "] [DEBUG] Asset $index: ID={$asset['id'] ?? 'N/A'}, Name=" . ($asset['content']['metadata']['name'] ?? ($asset['name'] ?? 'N/A')) . "\n", FILE_APPEND);
                }
            }

            // Save to cache
            $cache_data[$cache_key] = [
                'data' => $formatted_data,
                'timestamp' => time()
            ];
            $fp = @fopen($cache_file, 'c');
            if ($fp && flock($fp, LOCK_EX)) {
                if (!@file_put_contents($cache_file, json_encode($cache_data, JSON_PRETTY_PRINT))) {
                    if ($can_log) {
                        log_message("nft_creator: Failed to write to cache file", 'nft_creator_log.txt', 'ERROR');
                    } else {
                        @file_put_contents($cache_dir . 'debug_log.txt', "[" . date('Y-m-d H:i:s') . "] [ERROR] Failed to write to cache file\n", FILE_APPEND);
                    }
                    throw new Exception('Failed to write to cache file');
                }
                flock($fp, LOCK_UN);
                fclose($fp);
            } else {
                if ($can_log) {
                    log_message("nft_creator: Failed to lock cache file", 'nft_creator_log.txt', 'ERROR');
                } else {
                    @file_put_contents($cache_dir . 'debug_log.txt', "[" . date('Y-m-d H:i:s') . "] [ERROR] Failed to lock cache file\n", FILE_APPEND);
                }
                throw new Exception('Failed to lock cache file');
            }
            if ($can_log) {
                log_message("nft_creator: Cache updated for creatorAddress=$creatorAddress", 'nft_creator_log.txt', 'INFO');
            } else {
                @file_put_contents($cache_dir . 'debug_log.txt', "[" . date('Y-m-d H:i:s') . "] [INFO] Cache updated for creatorAddress=$creatorAddress\n", FILE_APPEND);
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
                <p class="cache-timestamp">Last updated: <?php echo date('d M Y, H:i', time()) . ' UTC+0'; ?>. Data will be updated every 3 hours.</p>
            </div>
            <?php
            $output = ob_get_clean();
            if ($can_log) {
                log_message("nft_creator: Output length: " . strlen($output), 'nft_creator_log.txt', 'INFO');
            } else {
                @file_put_contents($cache_dir . 'debug_log.txt', "[" . date('Y-m-d H:i:s') . "] [INFO] Output length: " . strlen($output) . "\n", FILE_APPEND);
            }
            echo $output;
        } catch (Exception $e) {
            $error_msg = "Error processing request: " . htmlspecialchars($e->getMessage());
            if ($can_log) {
                log_message("nft_creator: Exception: $error_msg, File: " . htmlspecialchars($e->getFile()) . ", Line: " . $e->getLine(), 'nft_creator_log.txt', 'ERROR');
            } else {
                @file_put_contents($cache_dir . 'debug_log.txt', "[" . date('Y-m-d H:i:s') . "] [ERROR] Exception: $error_msg, File: " . htmlspecialchars($e->getFile()) . ", Line: " . $e->getLine() . "\n", FILE_APPEND);
            }
            echo "<div class='result-error'><p>$error_msg</p></div>";
        }
    }
    if ($can_log) {
        log_message("nft_creator: Script ended", 'nft_creator_log.txt', 'INFO');
    } else {
        @file_put_contents($cache_dir . 'debug_log.txt', "[" . date('Y-m-d H:i:s') . "] [INFO] Script ended\n", FILE_APPEND);
    }
    ?>

    <div class="tools-about">
        <h2>About Check NFT Creator</h2>
        <p>The Check NFT Creator tool allows you to view all NFTs and Collections created by a specific Solana wallet address. For example, find the creator address on MagicEden or other Solana marketplaces.</p>
    </div>
</div>
