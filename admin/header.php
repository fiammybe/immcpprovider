<?php

$siteRoot = dirname($_SERVER['SCRIPT_FILENAME'], 4);

include_once $siteRoot . '/include/cp_header.php';

icms_loadLanguageFile('mcpapi', 'modinfo');
icms_loadLanguageFile('mcpapi', 'admin');

$autoload = dirname(__DIR__) . '/vendor/autoload.php';

if (! is_file($autoload)) {
    icms_cp_header();
    echo '<div class="errorMsg">The vendor/ directory is missing. Run "composer install --no-dev" inside the module directory, or install a release archive that includes vendor/.</div>';
    icms_cp_footer();

    exit;
}

require_once $autoload;
