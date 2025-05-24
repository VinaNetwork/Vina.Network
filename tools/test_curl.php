<?php
$ch = curl_init('https://api.helius.xyz');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
if (curl_errno($ch)) {
    echo 'cURL error: ' . curl_error($ch);
} else {
    echo 'cURL works!';
}
curl_close($ch);
?>
