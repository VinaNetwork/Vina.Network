<?php
// --------------------------------------------------------------------------
// File: include/crypto_widget.php
// Description: Reusable section to display real-time crypto prices (BTC, ETH, SOL).
// Created by: Vina Network
// --------------------------------------------------------------------------

$root_path = '../';
require_once $root_path . 'config/bootstrap.php';

// Crypto Price Widget Section
<section class="crypto-widget">
    <div class="coinmarketcap-currency-widget"
         data-currencyid="1"
         data-base="USD"
         data-secondary=""
         data-ticker="true"
         data-rank="true"
         data-marketcap="true"
         data-volume="true"
         data-stats="USD">
    </div>

    <div class="coinmarketcap-currency-widget"
         data-currencyid="1027"
         data-base="USD"
         data-secondary=""
         data-ticker="true"
         data-rank="true"
         data-marketcap="true"
         data-volume="true"
         data-stats="USD">
    </div>

    <div class="coinmarketcap-currency-widget"
         data-currencyid="5426"
         data-base="USD"
         data-secondary=""
         data-ticker="true"
         data-rank="true"
         data-marketcap="true"
         data-volume="true"
         data-stats="USD">
    </div>

    // Load CoinMarketCap widget script (only include once per page)
    <script type="text/javascript" src="https://files.coinmarketcap.com/static/widget/currency.js"></script>
</section>
?>
