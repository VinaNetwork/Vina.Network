<?php
// --------------------------------------------------------------------------
// File: include/widget.php
// Description: Reusable section to display real-time crypto prices (BTC, ETH, SOL).
// Created by: Vina Network
// --------------------------------------------------------------------------
?>

<!-- Crypto Price Widget Section -->
<section class="widget-crypto crypto-widget">
    <!-- Bitcoin ($BTC) Widget -->
    <div class="widget-crypto-item coinmarketcap-currency-widget"
         data-currencyid="1"
         data-base="USD"
         data-secondary=""
         data-ticker="true"
         data-rank="true"
         data-marketcap="true"
         data-volume="true"
         data-stats="USD">
    </div>
    <!-- Ethereum ($ETH) Widget -->
    <div class="widget-crypto-item coinmarketcap-currency-widget"
         data-currencyid="1027"
         data-base="USD"
         data-secondary=""
         data-ticker="true"
         data-rank="true"
         data-marketcap="true"
         data-volume="true"
         data-stats="USD">
    </div>
    <!-- Solana ($SOL) Widget -->
    <div class="widget-crypto-item coinmarketcap-currency-widget"
         data-currencyid="5426"
         data-base="USD"
         data-secondary=""
         data-ticker="true"
         data-rank="true"
         data-marketcap="true"
         data-volume="true"
         data-stats="USD">
    </div>

    <!-- Load CoinMarketCap widget script (only include once per page) -->
    <script type="text/javascript" src="https://files.coinmarketcap.com/static/widget/currency.js"></script>
</section>
