#!/usr/bin/php -q
<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * This file is part of A2Billing (http://www.a2billing.net/)
 *
 * A2Billing, Commercial Open Source Telecom Billing platform,
 * powered by Star2billing S.L. <http://www.star2billing.com/>
 *
 * @copyright   Copyright (C) 2004-2015 - Star2billing S.L.
 * @author      Belaid Arezqui <areski@gmail.com>
 * @license     http://www.fsf.org/licensing/licenses/agpl-3.0.html
 * @package     A2Billing
 *
 * Software License Agreement (GNU Affero General Public License)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 *
**/

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

$charge_callback = 0;
$G_startime = time();
$agi_version = "A2Billing - v2.2.0";

if ($argc > 1 && ($argv[1] == '--version' || $argv[1] == '-v')) {
    echo "$agi_version\n";
    exit;
}

$agi = new AGI();

$optconfig = array();
if ($argc > 1 && strstr($argv[1], "+")) {
    /*
    This change allows some configuration overrides on the AGI command-line by allowing the user to add them after the configuration number, like so:
    exten => 0312345678, 3, AGI(a2billing.php, "1+use_dnid=0&extracharge_did=12345")
    */
    //check for configuration overrides in the first argument
    $idconfig = substr($argv[1], 0, strpos($argv[1], "+"));
    $configstring = substr($argv[1], strpos($argv[1], "+") + 1);

    foreach (explode("&", $configstring) as $conf) {
        $var = substr($conf, 0, strpos($conf, "="));
        $val = substr($conf, strpos($conf, "=") + 1);
        $optconfig[$var] = $val;
    }
} elseif ($argc > 1 && is_numeric($argv[1]) && $argv[1] >= 0) {
    $idconfig = $argv[1];
} else {
    $idconfig = 1;
}

if ($dynamic_idconfig = intval($agi->get_variable("IDCONF", true))) {
    $idconfig = $dynamic_idconfig;
}

if ($argc > 2 && strlen($argv[2]) > 0 && $argv[2] == 'did')                      $mode = 'did';
elseif ($argc > 2 && strlen($argv[2]) > 0 && $argv[2] == 'callback')             $mode = 'callback';
elseif ($argc > 2 && strlen($argv[2]) > 0 && $argv[2] == 'cid-callback')         $mode = 'cid-callback';
elseif ($argc > 2 && strlen($argv[2]) > 0 && $argv[2] == 'cid-prompt-callback')  $mode = 'cid-prompt-callback';
elseif ($argc > 2 && strlen($argv[2]) > 0 && $argv[2] == 'all-callback')         $mode = 'all-callback';
elseif ($argc > 2 && strlen($argv[2]) > 0 && $argv[2] == 'voucher')              $mode = 'voucher';
elseif ($argc > 2 && strlen($argv[2]) > 0 && $argv[2] == 'campaign-callback')    $mode = 'campaign-callback';
elseif ($argc > 2 && strlen($argv[2]) > 0 && $argv[2] == 'conference-moderator') $mode = 'conference-moderator';
elseif ($argc > 2 && strlen($argv[2]) > 0 && $argv[2] == 'conference-member')    $mode = 'conference-member';
else                                                                             $mode = 'standard';

// get the area code for the cid-callback, all-callback and cid-prompt-callback
if ($argc > 3 && strlen($argv[3]) > 0) {
    $caller_areacode = $argv[3];
}

if ($argc > 4 && strlen($argv[4]) > 0) {
    $groupid = $argv[4];
    $A2B->group_mode = true;
    $A2B->group_id = $groupid;
}

if ($argc > 5 && strlen($argv[5]) > 0) {
    $cid_1st_leg_tariff_id = $argv[5];
}

$A2B = new A2Billing();
$A2B->load_conf($agi, NULL, 0, $idconfig, $optconfig);
$A2B->mode = $mode;
$A2B->G_startime = $G_startime;

$A2B->debug(INFO, $agi, __FILE__, __LINE__, "IDCONFIG : $idconfig");
$A2B->debug(INFO, $agi, __FILE__, __LINE__, "MODE : $mode");

$A2B->CC_TESTING = isset($A2B->agiconfig['debugshell']) && $A2B->agiconfig['debugshell'];
//$A2B->CC_TESTING = true;

define("DB_TYPE", isset($A2B->config["database"]['dbtype']) ? $A2B->config["database"]['dbtype'] : null);
define("SMTP_SERVER", isset($A2B->config['global']['smtp_server']) ? $A2B->config['global']['smtp_server'] : null);
define("SMTP_HOST", isset($A2B->config['global']['smtp_host']) ? $A2B->config['global']['smtp_host'] : null);
define("SMTP_USERNAME", isset($A2B->config['global']['smtp_username']) ? $A2B->config['global']['smtp_username'] : null);
define("SMTP_PASSWORD", isset($A2B->config['global']['smtp_password']) ? $A2B->config['global']['smtp_password'] : null);

// Print header
$A2B->debug(DEBUG, $agi, __FILE__, __LINE__, "AGI Request:\n" . print_r($agi->request, true));
$A2B->debug(DEBUG, $agi, __FILE__, __LINE__, "[INFO : $agi_version]");

/* GET THE AGI PARAMETER */
$A2B->get_agi_request_parameter($agi);

/* GET DIVERSION HEADER */

$DIVERSION_HEADER = $agi->get_variable("SIP_HEADER(TO)");
$agi->verbose("DIVERSION HEADER: " . $DIVERSION_HEADER["data"]);

// Just get the number portion of the Diversion Header
preg_match('/sip:(\d+)@/', $DIVERSION_HEADER["data"], $m );

$DIVERSION_NUMBER = $m[1];

$agi->verbose("DIVERSION_NUMBER: " . $DIVERSION_NUMBER);

if (!$A2B->DbConnect()) {
    $agi->stream_file('prepaid-final', '#');
    exit;
}

define("WRITELOG_QUERY", true);
$instance_table = new Table();
$A2B->set_instance_table($instance_table);

/* CHECK IF DIVERSION HEADER (CONTAINS TO) IS A CUSTOMER */

$QUERY = "select * from cc_callerid, cc_card where cc_card.id=cc_callerid.id_cc_card and cid='" . $DIVERSION_NUMBER . "'"; 
$result = $A2B->instance_table->SQLExec($A2B->DBHandle, $QUERY, 1, 300);

if (is_array($result)) {
    $num_cur = count($result);
	$credit = $result[0]['credit'];	
	$A2B->write_log("Found: $DIVERSION_NUMBER belongs to an active customer with a credit of $credit", 0);
   
	if ($credit <= 0)
		$agi->exec("Dial", "SIP/telcobridges/57" . $DIVERSION_NUMBER);
	 
    /*for ($i = 0; $i < $num_cur; $i++) {
        $currencies_list[$result[$i][1]] = array(1=>$result[$i][2], 2=>$result[$i][3]);
    }*/
}
else { //Not a valid Blue Wireless Number
	$A2B->write_log("Not Found: $DIVERSION_NUMBER is NOT a Blue Wireless number", 0);
	$agi->exec("Dial", "SIP/telcobridges/57" . $DIVERSION_NUMBER);
}

$A2B->write_log("[exit]", 0);
