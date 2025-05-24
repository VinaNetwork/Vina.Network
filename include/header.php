<?php
// Default values (giá trị chung, tối ưu)
$page_title = isset($page_title) ? $page_title : 'Vina Network - Leading Web3 Blockchain Ecosystem';
$page_description = isset($page_description) ? $page_description : 'Vina Network is a leading Web3 ecosystem focused on blockchain technology, cryptocurrencies ($VINA), stablecoins, and DeFi solutions. Join us!';
$page_keywords = isset($page_keywords) ? $page_keywords : 'Vina Network, Web3, blockchain, cryptocurrency, $VINA, DeFi, stablecoin';
$page_author = isset($page_author) ? $page_author : 'Vina Network';
$page_og_title = isset($page_og_title) ? $page_og_title : $page_title;
$page_og_description = isset($page_og_description) ? $page_og_description : $page_description;
$page_og_image = isset($page_og_image) ? $page_og_image : 'https://vina.network/img/logo.png';
$page_og_url = isset($page_og_url) ? $page_og_url : 'https://vina.network';
$page_og_type = isset($page_og_type) ? $page_og_type : 'website';
$page_css = isset($page_css) ? $page_css : [];
$page_theme_color = isset($page_theme_color) ? $page_theme_color : '#f5f5f5';

// Xử lý đường dẫn động dựa trên vị trí file
$root_path = isset($root_path) ? $root_path : '';
?>

<head>
    <meta charset="UTF-8">
    <meta name="robots" content="index, follow">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="<?php echo htmlspecialchars($page_theme_color); ?>">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($page_description); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($page_keywords); ?>">
    <meta name="author" content="<?php echo htmlspecialchars($page_author); ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($page_og_title); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($page_og_description); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($page_og_image); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($page_og_url); ?>">
    <meta property="og:type" content="<?php echo htmlspecialchars($page_og_type); ?>">
    <link rel="icon" type="image/x-icon" href="<?php echo $root_path; ?>img/favicon.ico">
    <link rel="stylesheet" href="<?php echo $root_path; ?>css/vina.css">
    <?php if (!empty($page_css)): ?>
        <?php foreach ($page_css as $css): ?>
            <link rel="stylesheet" href="<?php echo htmlspecialchars($css); ?>">
        <?php endforeach; ?>
    <?php endif; ?>
    <link rel="stylesheet" href="<?php echo $root_path; ?>css/poppins.css">
    <link rel="stylesheet" href="<?php echo $root_path; ?>css/all.css">
    <!-- Preload critical resources -->
    <link rel="preload" href="<?php echo $root_path; ?>webfonts/fa-brands-400.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="<?php echo $root_path; ?>webfonts/fa-regular-400.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="<?php echo $root_path; ?>webfonts/fa-solid-900.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="<?php echo $root_path; ?>fonts/poppins-400.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="<?php echo $root_path; ?>fonts/poppins-600.woff2" as="font" type="font/woff2" crossorigin>
</head>
