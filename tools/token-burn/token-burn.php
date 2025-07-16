<?php
// ============================================================================
// File: tools/token-burn/token-burn.php
// Description: Check how many tokens have been burned (sent to 111... or via burn instructions).
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK')) define('VINANETWORK', true);
if (!defined('VINANETWORK_ENTRY')) define('VINANETWORK_ENTRY', true);

// Load bootstrap
$bootstrap_path = dirname(__DIR__) . '/bootstrap.php';
if (!file_exists($bootstrap_path)) {
    log_message("token_burn: bootstrap.php not found at $bootstrap_path", 'token_burn_log.txt', 'ERROR');
    echo '<div class="result-error"><p>Cannot find bootstrap.php</p></div>';
    exit;
}
require_once $bootstrap_path;

// Load tools-api
$api_helper_path = dirname(__DIR__) . '/tools-api.php';
if (!file_exists($api_helper_path)) {
    log_message("token_burn: tools-api.php not found at $api_helper_path", 'token_burn_log.txt', 'ERROR');
    echo '<div class="result-error"><p>Server error: Missing tools-api.php</p></div>';
    exit;
}
require_once $api_helper_path;
?>

<div class="token-burn-tool">
    <h2>Check Token Burn</h2>
    <p>Enter the <strong>Token Address</strong> (Mint) to estimate how many tokens have been burned on Solana.</p>
    <form id="tokenBurnForm" method="POST" action="" data-tool="token-burn">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
        <div class="input-wrapper">
            <input type="text" name="tokenAddress" placeholder="Enter Token Mint Address" required value="<?php echo isset($_POST['tokenAddress']) ? htmlspecialchars($_POST['tokenAddress']) : ''; ?>">
            <span class="clear-input" title="Clear input">×</span>
        </div>
        <button type="submit" class="cta-button">Check</button>
        <div class="loader"></div>
    </form>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tokenAddress'])) {
    try {
        if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
            throw new Exception('Invalid CSRF token');
        }

        $mint = trim($_POST['tokenAddress']);
        $mint = preg_replace('/\s+/', '', $mint);

        if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $mint)) {
            throw new Exception('Invalid Token Address format');
        }

        // Call Helius GET endpoint via tools-api.php
        $response = callAPI("getTokenBurnStats", ['mint' => $mint], 'GET');

        if (isset($response['error'])) {
            throw new Exception($response['error']);
        }

        // Extract results
        $burned_to_null = $response['burnToNullAddress'] ?? 0;
        $burned_via_instruction = $response['burnInstructions'] ?? 0;
        $total_burned = $burned_to_null + $burned_via_instruction;
?>
        <div class="tools-result token-burn-result">
            <h3>Burn Stats for Token</h3>
            <table>
                <tr><th>Burned to <code>111111...</code></th><td><?php echo number_format($burned_to_null, 2); ?></td></tr>
                <tr><th>Burned via Instruction</th><td><?php echo number_format($burned_via_instruction, 2); ?></td></tr>
                <tr><th><strong>Total Burned</strong></th><td><strong><?php echo number_format($total_burned, 2); ?></strong></td></tr>
            </table>
        </div>
<?php
    } catch (Exception $e) {
        echo "<div class='result-error'><p>Error: " . htmlspecialchars($e->getMessage()) . "</p></div>";
        log_message("token_burn: Exception - " . $e->getMessage(), 'token_burn_log.txt', 'ERROR');
    }
}
?>

    <div class="tools-about">
        <h2>About Token Burn Checker</h2>
        <p>This tool checks how many tokens of a given Solana token have been burned either by sending to <code>11111111111111111111111111111111</code> or by executing burn instructions.</p>
    </div>
</div>
