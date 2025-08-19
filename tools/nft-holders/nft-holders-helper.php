<?php
// ============================================================================
// File: tools/nft-holders/nft-holders-helper.php
// Description: Helper functions for NFT Holders (e.g. fetching holder data from API).
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = __DIR__ . '/../../';
require_once $root_path . 'tools/bootstrap.php';
require_once $root_path . 'tools/tools-api.php';

/**
 * fetchNFTCollectionHolders - Fetch all NFT ownership data in a collection using pagination
 * @param string $mintAddress - Collection mint address
 * @param int $maxPages - Maximum number of pages to fetch
 * @param int $limit - Items per page
 * @param int $delayUs - Delay between pages in microseconds (default 2s)
 * @return array [ 'wallets' => [...], 'total_items' => int, 'total_wallets' => int ]
 */
function fetchNFTCollectionHolders(string $mintAddress, int $maxPages = 500, int $limit = 1000, int $delayUs = 2000000): array {
    $wallets = [];
    $page = 1;
    while ($page <= $maxPages) {
        $params = [
            'groupKey' => 'collection',
            'groupValue' => $mintAddress,
            'page' => $page,
            'limit' => $limit
        ];
        $response = callAPI('getAssetsByGroup', $params, 'POST');

        if (!isset($response['result']['items'])) break;
        $items = $response['result']['items'];
        foreach ($items as $item) {
            if (isset($item['ownership']['owner'])) {
                $owner = $item['ownership']['owner'];
                $wallets[$owner] = ($wallets[$owner] ?? 0) + 1;
            }
        }
        if (count($items) < $limit) break;
        $page++;
        usleep($delayUs); // Delay to avoid rate limiting
    }

    $wallet_list = array_map(
        fn($owner, $amount) => ['owner' => $owner, 'amount' => $amount],
        array_keys($wallets),
        $wallets
    );

    return [
        'wallets' => $wallet_list,
        'total_items' => array_sum($wallets),
        'total_wallets' => count($wallets)
    ];
}
