<?php
/*
 * NFT Holders Checker - Vina Network
 * Update 4: Added reCAPTCHA v3 with enhanced client-side debug
 */

// Disable display of errors in production
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Define constants to mark script entry
if (!defined('VINANETWORK')) {
    define('VINANETWORK', true);
}
if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

// Load bootstrap dependencies
$bootstrap_path = __DIR__ . '/../bootstrap.php';
if (!file_exists($bootstrap_path)) {
    log_message("nft-holders: bootstrap.php not found at $bootstrap_path", 'nft_holders_log.txt', 'ERROR');
    die('Error: bootstrap.php not found');
}
require_once $bootstrap_path;

// Start session and configure error logging
session_start();
ini_set('log_errors', true);
ini_set('error_log', ERROR_LOG_PATH);

// Set up page variables and include layout headers
$root_path = '../../';
$page_title = 'Check NFT Holders - Vina Network';
$page_description = 'Check NFT holders for a Solana collection address.';
$page_css = ['../../css/vina.css', '../tools.css'];
include $root_path . 'include/header.php';
include $root_path . 'include/navbar.php';

// Include tools API helper
$api_helper_path = dirname(__DIR__) . '/tools-api.php';
if (!file_exists($api_helper_path)) {
    log_message("nft-holders: tools-api.php not found at $api_helper_path", 'nft_holders_log.txt', 'ERROR');
    die('Internal Server Error: Missing tools-api.php');
}
log_message("nft-holders: Including tools-api.php from $api_helper_path", 'nft_holders_log.txt');
include $api_helper_path;

log_message("nft-holders: Loaded at " . date('Y-m-d H:i:s'), 'nft_holders_log.txt');
?>
<!-- Render input form with reCAPTCHA -->
<div class="t-6 nft-holders-content">
    <div class="t-7">
        <h2>Check NFT Holders</h2>
        <p>Enter the <strong>NFT Collection</strong> address to see the total number of holders and NFTs. E.g: Find this address on MagicEden under "Details" > "On-chain Collection".</p>
        <form id="nftHoldersForm" method="POST" action="">
            <input type="text" name="mintAddress" id="mintAddressHolders" placeholder="Enter NFT Collection Address" required value="<?php echo isset($_POST['mintAddress']) ? htmlspecialchars($_POST['mintAddress']) : ''; ?>">
            <input type="hidden" name="recaptcha_token" id="recaptcha-token">
            <button type="submit" class="cta-button" id="submit-btn">Check Holders</button>
        </form>
        <div class="loader"></div>
    </div>
    <?php
    // Handle form submission and NFT data fetching
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mintAddress'])) {
        try {
            $mintAddress = trim($_POST['mintAddress']);
            log_message("nft-holders: Form submitted with mintAddress=$mintAddress, POST data=" . json_encode($_POST), 'nft_holders_log.txt');

            // Validate reCAPTCHA token
            $recaptcha_token = '';
            if (isset($_POST['recaptcha_token'])) {
                $recaptcha_token = is_scalar($_POST['recaptcha_token']) ? trim($_POST['recaptcha_token']) : '';
            }
            if (empty($recaptcha_token)) {
                log_message("reCAPTCHA: No token provided for mintAddress=$mintAddress, POST data=" . json_encode($_POST), 'nft_holders_log.txt', 'ERROR');
                throw new Exception("Please complete the CAPTCHA verification.");
            }

            $recaptcha_url = 'https://www.google.com/recaptcha/api/siteverify';
            $recaptcha_data = [
                'secret' => RECAPTCHA_SECRET_KEY,
                'response' => $recaptcha_token,
                'remoteip' => $_SERVER['REMOTE_ADDR']
            ];
            $recaptcha_options = [
                'http' => [
                    'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                    'method' => 'POST',
                    'content' => http_build_query($recaptcha_data),
                    'timeout' => 10
                ]
            ];
            $recaptcha_context = stream_context_create($recaptcha_options);
            $recaptcha_response = @file_get_contents($recaptcha_url, false, $recaptcha_context);
            if ($recaptcha_response === false) {
                $error = error_get_last();
                log_message("reCAPTCHA: Failed to contact Google API for mintAddress=$mintAddress, error=" . json_encode($error), 'nft_holders_log.txt', 'ERROR');
                throw new Exception("CAPTCHA service unavailable. Please try again later.");
            }
            $recaptcha_result = json_decode($recaptcha_response, true);

            log_message("reCAPTCHA: Verification result for mintAddress=$mintAddress, result=" . json_encode($recaptcha_result), 'nft_holders_log.txt');

            if (!isset($recaptcha_result['success']) || !$recaptcha_result['success'] || $recaptcha_result['score'] < 0.5) {
                $score = $recaptcha_result['score'] ?? 'N/A';
                $error_codes = $recaptcha_result['error-codes'] ?? [];
                log_message("reCAPTCHA: Verification failed for mintAddress=$mintAddress, score=$score, error_codes=" . json_encode($error_codes), 'nft_holders_log.txt', 'ERROR');
                throw new Exception("CAPTCHA verification failed. Please try again.");
            }

            $limit = 1000;
            $max_pages = 100;
            $cache_expiration = 3 * 3600;

            if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $mintAddress)) {
                throw new Exception("Invalid collection address. Please enter a valid Solana collection address (32-44 characters, base58).");
            }

            if (!isset($_SESSION['last_mintAddress']) || $_SESSION['last_mintAddress'] !== $mintAddress) {
                if (isset($_SESSION['total_items'][$mintAddress])) {
                    unset($_SESSION['total_items'][$mintAddress], $_SESSION['total_wallets'][$mintAddress], $_SESSION['items'][$mintAddress], $_SESSION['wallets'][$mintAddress], $_SESSION['cache_timestamp'][$mintAddress]);
                    log_message("nft-holders: Cleared session cache for new mintAddress=$mintAddress", 'nft_holders_log.txt');
                }
                $_SESSION['last_mintAddress'] = $mintAddress;
            }

            $cache_valid = isset($_SESSION['total_items'][$mintAddress]) && isset($_SESSION['cache_timestamp'][$mintAddress]) && isset($_SESSION['items'][$mintAddress]) && (time() - $_SESSION['cache_timestamp'][$mintAddress] < $cache_expiration);

            if (!$cache_valid) {
                if (!$cache_valid && isset($_SESSION['cache_timestamp'][$mintAddress])) {
                    log_message("nft-holders: Cache expired for mintAddress=$mintAddress, fetching new data", 'nft_holders_log.txt');
                } elseif (!isset($_SESSION['total_items'][$mintAddress])) {
                    log_message("nft-holders: No cache found for mintAddress=$mintAddress, fetching new data", 'nft_holders_log.txt');
                }

                ini_set('memory_limit', '512M');
                $total_items = 0;
                $api_page = 1;
                $has_more = true;
                $items = [];
                while ($has_more && $api_page <= $max_pages) {
                    $total_params = [
                        'groupKey' => 'collection',
                        'groupValue' => $mintAddress,
                        'page' => $api_page,
                        'limit' => $limit
                    ];
                    log_message("nft-holders: Calling API for total items, page=$api_page, params=" . json_encode($total_params), 'nft_holders_log.txt');
                    $total_data = callAPI('getAssetsByGroup', $total_params, 'POST');
                    log_message("nft-holders: Total API response (page $api_page): URL=https://mainnet.helius-rpc.com/?api-key=****, Response=" . json_encode($total_data, JSON_PRETTY_PRINT), 'nft_holders_log.txt');

                    if (isset($total_data['error'])) {
                        $errorMessage = is_array($total_data['error']) && isset($total_data['error']['message']) ? $total_data['error']['message'] : json_encode($total_data['error']);
                        throw new Exception("API error: " . $errorMessage);
                    }

                    if (!isset($total_data['result']['items'])) {
                        log_message("nft-holders: Invalid API response, no items found for page=$api_page, mintAddress=$mintAddress", 'nft_holders_log.txt', 'ERROR');
                        throw new Exception("Invalid API response: No items found.");
                    }

                    $page_items = $total_data['result']['items'];
                    $item_count = count($page_items);
                    $items = array_merge($items, array_map(function($item) {
                        if (!isset($item['ownership']['owner'])) {
                            log_message("nft-holders: Invalid item structure, missing owner: " . json_encode($item), 'nft_holders_log.txt', 'WARNING');
                            return null;
                        }
                        return [
                            'owner' => $item['ownership']['owner'],
                            'amount' => 1
                        ];
                    }, $page_items));
                    $items = array_filter($items);
                    $total_items += $item_count;

                    log_message("nft-holders: Page $api_page added $item_count items, total_items=$total_items, valid_items=" . count($items), 'nft_holders_log.txt');

                    $has_more = $item_count >= $limit;
                    $api_page++;
                    usleep(2000000);
                }

                if ($api_page > $max_pages && $has_more) {
                    $max_items_possible = $max_pages * $limit;
                    log_message("nft-holders: Reached max pages ($max_pages) for $mintAddress, data may be incomplete. Total items fetched: $total_items", 'nft_holders_log.txt', 'WARNING');
                    echo "<div class='result-error'>";
                    echo "<p><strong>Warning:</strong> The collection is too large, and only the first $max_items_possible NFTs were retrieved due to API limitations.</p>";
                    echo "<p>This data may be incomplete. For full details, consider checking directly on the Solana blockchain or <a href='mailto:support@vina.network'>contact our support team</a>.</p>";
                    echo "</div>";
                }

                $unique_wallets = [];
                foreach ($items as $item) {
                    if (!isset($item['owner'])) {
                        log_message("nft-holders: Skipping invalid item during deduplication: " . json_encode($item), 'nft_holders_log.txt', 'WARNING');
                        continue;
                    }
                    $owner = $item['owner'];
                    if (!isset($unique_wallets[$owner])) {
                        $unique_wallets[$owner] = $item;
                    } else {
                        $unique_wallets[$owner]['amount'] += 1;
                    }
                }
                $wallets = array_values($unique_wallets);
                $total_wallets = count($wallets);

                if ($total_items > 0 && $total_wallets === 0) {
                    log_message("nft-holders: Inconsistent data: total_items=$total_items but total_wallets=0 for $mintAddress", 'nft_holders_log.txt', 'ERROR');
                    throw new Exception("Failed to retrieve wallet data. Please try again or contact support.");
                }

                $_SESSION['total_items'][$mintAddress] = $total_items;
                $_SESSION['total_wallets'][$mintAddress] = $total_wallets;
                $_SESSION['items'][$mintAddress] = $items;
                $_SESSION['wallets'][$mintAddress] = $wallets;
                $_SESSION['cache_timestamp'][$mintAddress] = time();
                log_message("nft-holders: Cached total_items=$total_items, total_wallets=$total_wallets for $mintAddress with timestamp=" . date('Y-m-d H:i:s'), 'nft_holders_log.txt');
            } else {
                $total_items = $_SESSION['total_items'][$mintAddress];
                $total_wallets = $_SESSION['total_wallets'][$mintAddress];
                $items = $_SESSION['items'][$mintAddress];
                $wallets = $_SESSION['wallets'][$mintAddress];
                log_message("nft-holders: Retrieved total_items=$total_items, total_wallets=$total_wallets from cache for $mintAddress, cached at " . date('Y-m-d H:i:s', $_SESSION['cache_timestamp'][$mintAddress]), 'nft_holders_log.txt');
            }

            log_message("nft-holders: Final total_items=$total_items, total_wallets=$total_wallets for $mintAddress", 'nft_holders_log.txt');

            if ($total_items === 0) {
                throw new Exception("No items found or invalid collection address.");
            } elseif ($limit > 0 && $total_items % $limit === 0 && $total_items >= $limit) {
                log_message("nft-holders: Suspicious total_items ($total_items) is a multiple of limit for $mintAddress", 'nft_holders_log.txt', 'WARNING');
                echo "<div class='result-error'><p>Warning: Total items ($total_items) is a multiple of API limit ($limit). Actual number may be higher. For full details, check directly on the Solana blockchain or <a href='mailto:support@vina.network'>contact support</a>.</p></div>";
            }
            ?>
            <div class="result-section">
                <?php if ($total_wallets === 0): ?>
                    <p class="result-error">No holders found for this collection.</p>
                <?php else: ?>
                    <div class="holders-summary">
                        <div class="summary-card">
                            <div class="summary-item">
                                <i class="fas fa-wallet"></i>
                                <p>Total wallets</p>
                                <h3><?php echo number_format($total_wallets); ?></h3>
                            </div>
                            <div class="summary-item">
                                <i class="fas fa-image"></i>
                                <p>Total NFTs</p>
                                <h3><?php echo number_format($total_items); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="export-section">
                        <form method="POST" action="/tools/nft-holders/nft-holders-export.php" class="export-form">
                            <input type="hidden" name="mintAddress" value="<?php echo htmlspecialchars($mintAddress); ?>">
                            <div class="export-controls">
                                <select name="export_format" class="export-format">
                                    <option value="csv">CSV</option>
                                    <option value="json">JSON</option>
                                </select>
                                <button type="submit" name="export_type" value="all" class="cta-button export-btn" id="export-all-btn">Export All Wallets</button>
                            </div>
                        </form>
                        <div class="progress-container" style="display: none;">
                            <p>Exporting... Please wait.</p>
                            <div class="progress-bar"><div class="progress-bar-fill" style="width: 0%;"></div></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <?php
        } catch (Exception $e) {
            $error_msg = "Error processing request: " . $e->getMessage();
            log_message("nft-holders: Exception - $error_msg", 'nft_holders_log.txt', 'ERROR');
            echo "<div class='result-error'><p>$error_msg. Please try again.</p></div>";
        }
    }
    ?>
    <div class="t-9">
        <h2>About NFT Holders Checker</h2>
        <p>
            The NFT Holders Checker allows you to view the total number of holders and NFTs for a specific Solana NFT collection by entering its On-chain Collection address. 
            This tool is useful for NFT creators, collectors, or investors who want to analyze the distribution and ownership of a collection on the Solana blockchain.
        </p>
    </div>
</div>

<script src="https://www.google.com/recaptcha/api.js?render=<?php echo htmlspecialchars(RECAPTCHA_SITE_KEY); ?>" async defer></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('reCAPTCHA: Script initialization started');
    var form = document.getElementById('nftHoldersForm');
    var submitBtn = document.getElementById('submit-btn');
    if (!form || !submitBtn) {
        console.error('reCAPTCHA: Form or submit button not found');
        return;
    }
    console.log('reCAPTCHA: Form and submit button found');

    // Check if reCAPTCHA script loaded
    var checkRecaptcha = setInterval(function() {
        if (typeof grecaptcha !== 'undefined') {
            clearInterval(checkRecaptcha);
            console.log('reCAPTCHA: Script loaded successfully');
            initializeForm();
        } else {
            console.warn('reCAPTCHA: Script not yet loaded, waiting...');
        }
    }, 500);

    // Timeout after 10 seconds
    setTimeout(function() {
        if (typeof grecaptcha === 'undefined') {
            clearInterval(checkRecaptcha);
            console.error('reCAPTCHA: Script failed to load after 10 seconds');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Check Holders';
            form.insertAdjacentHTML('afterend', '<div class="result-error"><p>CAPTCHA service failed to load. Please refresh the page or disable adblock.</p></div>');
        }
    }, 10000);

    function initializeForm() {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            submitBtn.disabled = true;
            submitBtn.textContent = 'Verifying...';
            console.log('reCAPTCHA: Form submit event triggered');

            grecaptcha.ready(function() {
                console.log('reCAPTCHA: grecaptcha.ready executed');
                grecaptcha.execute('<?php echo htmlspecialchars(RECAPTCHA_SITE_KEY); ?>', { action: 'submit_nft_holders' })
                    .then(function(token) {
                        console.log('reCAPTCHA: Token generated:', token.substring(0, 10) + '...');
                        document.getElementById('recaptcha-token').value = token;
                        console.log('reCAPTCHA: Submitting form with token');
                        form.submit();
                    })
                    .catch(function(error) {
                        console.error('reCAPTCHA: Execution error:', error);
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Check Holders';
                        form.insertAdjacentHTML('afterend', '<div class="result-error"><p>CAPTCHA verification failed. Please try again.</p></div>');
                    });
            });
        });
    }
});
</script>

<?php
ob_start();
include $root_path . 'include/footer.php';
$footer_output = ob_get_clean();
log_message("nft-holders: Footer output length: " . strlen($footer_output), 'nft_holders_log.txt');
echo $footer_output;
?>
