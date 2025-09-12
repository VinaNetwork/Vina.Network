<?php
// ============================================================================
// File: include/header.php
// Description: Shared <head> section for all pages on Vina Network.
// Created by: Vina Network
// ============================================================================

// Default meta values
$page_title = isset($page_title) ? $page_title : 'Vina Network - Leading Web3 Blockchain Ecosystem';
$page_description = isset($page_description) ? $page_description : 'Vina Network is a leading Web3 ecosystem focused on blockchain technology, cryptocurrencies ($VINA), stablecoins, and DeFi solutions. Join us!';
$page_keywords = isset($page_keywords) ? $page_keywords : 'Vina Network, Web3, blockchain, cryptocurrency, $VINA, DeFi, stablecoin';
$page_author = isset($page_author) ? $page_author : 'Vina Network';

$page_og_title = isset($page_og_title) ? $page_og_title : $page_title;
$page_og_description = isset($page_og_description) ? $page_og_description : $page_description;
$page_og_image = isset($page_og_image) ? $page_og_image : BASE_URL . 'img/logo.png';
$page_og_url = isset($page_og_url) ? $page_og_url : BASE_URL;
$page_og_type = isset($page_og_type) ? $page_og_type : 'website';

$page_css = isset($page_css) ? $page_css : [];
$page_theme_color = isset($page_theme_color) ? $page_theme_color : '#1a1a1a';

$page_canonical = isset($page_canonical) ? $page_canonical : $page_og_url;
?>

<head>
    <!-- Charset, Viewport & Theme Color -->
    <meta charset="UTF-8">
    <meta name="robots" content="index, follow">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="<?php echo htmlspecialchars($page_theme_color); ?>">
    <meta name="msapplication-navbutton-color" content="<?php echo htmlspecialchars($page_theme_color); ?>">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    
    <!-- SEO: Title & Meta Description -->
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($page_description); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($page_keywords); ?>">
    <meta name="author" content="<?php echo htmlspecialchars($page_author); ?>">
    
    <!-- Open Graph for Facebook/LinkedIn -->
    <meta property="og:title" content="<?php echo htmlspecialchars($page_og_title); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($page_og_description); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($page_og_image); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($page_og_url); ?>">
    <meta property="og:type" content="<?php echo htmlspecialchars($page_og_type); ?>">
    
    <!-- Twitter Card for better preview -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($page_og_title); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($page_og_description); ?>">
    <meta name="twitter:image" content="<?php echo htmlspecialchars($page_og_image); ?>">
    <!-- Optional override robots -->
    <meta name="robots" content="<?php echo isset($page_robots) ? htmlspecialchars($page_robots) : 'index, follow'; ?>">
    <!-- Canonical URL -->
    <link rel="canonical" href="<?php echo htmlspecialchars($page_canonical); ?>">
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?php echo BASE_URL; ?>img/favicon.ico">
    
    <!-- Core Stylesheets -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/vina.css">
    <?php if (!empty($page_css)): ?>
        <?php foreach ($page_css as $css): ?>
            <link rel="stylesheet" href="<?php echo htmlspecialchars($css); ?>">
        <?php endforeach; ?>
    <?php endif; ?>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/poppins.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/all.css">
    
    <!-- Font Preloading (Performance Optimization) -->
    <link rel="preload" href="<?php echo BASE_URL; ?>webfonts/fa-brands-400.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="<?php echo BASE_URL; ?>webfonts/fa-regular-400.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="<?php echo BASE_URL; ?>webfonts/fa-solid-900.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="<?php echo BASE_URL; ?>fonts/poppins-400.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="<?php echo BASE_URL; ?>fonts/poppins-600.woff2" as="font" type="font/woff2" crossorigin>
    
    <!-- JSON-LD Structured Data for SEO -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Organization",
        "name": "Vina Network",
        "url": "<?php echo htmlspecialchars($page_og_url); ?>",
        "logo": "<?php echo htmlspecialchars($page_og_image); ?>",
        "description": "<?php echo htmlspecialchars($page_description); ?>",
        "sameAs": [
            "https://x.com/Vina_Network",
            "https://t.me/Vina_Network"
        ],
        "contactPoint": {
            "@type": "ContactPoint",
            "email": "contact@vina.network",
            "contactType": "Customer Support"
        }
    }
    </script>
    
    <!-- Google Analytics Tracking -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-9PX6BGXB5N"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', 'G-9PX6BGXB5N');
    </script>
</head>
