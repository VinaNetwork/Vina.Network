<?php
require_once __DIR__ . '/api-helper.php';

function getNFTHolders($mintAddress, $offset = 0, $size = 10) {
    // Validate mint address
    if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $mintAddress)) {
        error_log("nft-holders.php: Invalid mint address: $mintAddress");
        return ['error' => 'Invalid mint address.'];
    }

    $params = [
        'groupKey' => 'collection',
        'groupValue' => $mintAddress,
        'page' => ceil($offset / $size) + 1, // Helius API bắt đầu từ page 1
        'limit' => $size
    ];
    
    error_log("nft-holders.php: Calling Helius API for holders - mintAddress: $mintAddress, offset: $offset, size: $size, page: {$params['page']}");
    
    $data = callHeliusAPI('getAssetsByGroup', $params, 'POST');
    
    if (isset($data['error'])) {
        error_log("nft-holders.php: Helius API error - {$data['error']}");
        return ['error' => $data['error']];
    }
    
    if (isset($data['result']['items']) && !empty($data['result']['items'])) {
        $holders = array_map(function($item) {
            return [
                'owner' => $item['ownership']['owner'],
                'amount' => 1 // Mỗi NFT có amount là 1
            ];
        }, array_slice($data['result']['items'], $offset % $size, $size));
        
        return [
            'holders' => $holders,
            'total' => $data['result']['total'] ?? count($data['result']['items'])
        ];
    }
    
    error_log("nft-holders.php: No holders found for mintAddress: $mintAddress");
    return ['error' => 'No holders found for this mint address.'];
}
?>
