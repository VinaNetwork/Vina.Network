// Get total items from session
$total_expected = isset($_SESSION['total_items'][$mintAddress]) ? $_SESSION['total_items'][$mintAddress] : 0;
file_put_contents(EXPORT_LOG_PATH, "export-holders: Total expected items from session: $total_expected - " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

if ($total_expected === 0) {
    $result = getItems($mintAddress, 1, 100);
    if (isset($result['error'])) {
        throw new Exception('API error: ' . json_encode($result['error']));
    }
    $total_expected = $result['total'];
    file_put_contents(EXPORT_LOG_PATH, "export-holders: Total expected items from API fallback: $total_expected - " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
}
