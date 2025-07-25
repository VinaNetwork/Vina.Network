<?php
// File: check_extensions.php
echo "BC Math: " . (extension_loaded('bcmath') ? 'Yes' : 'No') . "\n";
echo "GMP: " . (extension_loaded('gmp') ? 'Yes' : 'No') . "\n";
