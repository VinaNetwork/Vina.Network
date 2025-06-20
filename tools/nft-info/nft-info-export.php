<?php
// File: tools/nft-info/nft-info-export.php
// Description: Export NFT details to CSV or JSON.
// Author: Vina Network
// ============================================================================

if (!defined('VINANETWORK')) define('VINANETWORK', true);
require_once '../bootstrap.php';

// Cache file and input validation
$cache_file = __DIR__ . '/cache/nft_info_cache.json';
$mintAddress = $_POST['mintAddress'] ?? '';
$export_format = $_POST['export_format'] ?? 'csv';

if (!file_exists($cache_file) || empty($mintAddress)) {
    header('HTTP/1.1 400 Bad Request');
    die("Error: Cache file or Mint Address missing.");
}

$cache_data = json_decode(file_get_contents($cache_file), true) ?? [];
if (!isset($cache_data[$mintAddress])) {
    header('HTTP/1.1 404 Not Found');
    die("Error: No data found for this NFT.");
}

$data = $cache_data[$mintAddress]['data'];

// Export data
if ($export_format === 'json') {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="nft_info_' . substr($mintAddress, 0, 8) . '.json"');
    echo json_encode($data, JSON_PRETTY_PRINT);
} else {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="nft_info_' . substr($mintAddress, 0, 8) . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Mint Address', 'Name', 'Image', 'Attributes', 'Owner', 'Collection', 'Compressed', 'Burned', 'Listed']);
    fputcsv($output, [
        $data['mint_address'],
        $data['name'],
        $data['image'],
        $data['attributes'],
        $data['owner'],
        $data['collection'],
        $data['is_compressed'] ? 'Yes' : 'No',
        $data['is_burned'] ? 'Yes' : 'No',
        $data['is_listed'] ? 'Yes' : 'No'
    ]);
    fclose($output);
}
exit;
?>
