#!/usr/bin/php -q
<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

declare(ticks = 1);
if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGHUP, SIG_IGN);
}

error_reporting(E_ALL ^ (E_NOTICE | E_WARNING));

require_once __DIR__ . '/../vendor/autoload.php';

include(dirname(__FILE__) . "/lib/Class.Table.php");
include(dirname(__FILE__) . "/lib/Class.A2Billing.php");
include(dirname(__FILE__) . "/lib/Class.RateEngine.php");
include(dirname(__FILE__) . "/lib/phpagi/phpagi.php");
include(dirname(__FILE__) . "/lib/phpagi/phpagi-asmanager.php");
include(dirname(__FILE__) . "/lib/Misc.php");
include(dirname(__FILE__) . "/lib/interface/constants.php");

$agi_version = "A2Billing - v2.2.0";

if ($argc > 1 && ($argv[1] == '--version' || $argv[1] == '-v')) {
    echo "$agi_version\n";
    exit;
}

$agi = new AGI();
$TO_HEADER = $agi->exec("SIP_HEADER(To)");
$agi->noop("To Header: $TO_HEADER");
echo $TO_HEADER;

?>
