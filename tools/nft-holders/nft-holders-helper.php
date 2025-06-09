<?php
function getNFTHolders($mintAddress, $offset = 0, $size = 50) {
    $params = [
        'groupKey' => 'collection',
        'groupValue' => $mintAddress,
        'page' => ceil(($offset + $size) / $size),
        'limit' => $size
    ];

    file_put_contents('/var/www/vinanetwork/public_html/tools/debug_log.txt', date('Y-m-d H:i:s') . " - Calling Helius API for holders - mintAddress: $mintAddress, offset: $offset, size: $size, page: {$params['page']}\n", FILE_APPEND);
    error_log("nft-holders-helper.php: Calling Helius API for holders - mintAddress: $mintAddress, offset: $offset, size: $size, page: {$params['page']} at " . date('Y-m-d H:i:s'));

    $data = callHeliusAPI('getAssetsByGroup', $params, 'POST');

    if (isset($data['error'])) {
        error_log("nft-holders-helper.php: getAssetsByGroup error - " . json_encode($data) . " at " . date('Y-m-d H:i:s'));
        return ['error' => 'This is not an NFT collection address. Please enter a valid NFT Collection address.'];
    }

    if (isset($data['result']['items']) && !empty($data['result']['items'])) {
        $holders = array_map(function($item) {
            return [
                'owner' => $item['ownership']['owner'] ?? 'unknown',
                'amount' => 1
            ];
        }, $data['result']['items']);
        return ['holders' => $holders];
    }

    error_log("nft-holders-helper.php: No holders found for address $mintAddress at " . date('Y-m-d H:i:s'));
    return ['error' => 'This is not an NFT collection address. Please enter a valid NFT Collection address.'];
}
