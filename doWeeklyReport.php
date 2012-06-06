#!/usr/bin/php
<?php

/*
 * This script will generate a weekly report for the previous 
 * week.
 * The report is pulled from the log files.
 * 
 * @author: Brian Roy
 * @date: 10/19/2011
 */

require_once("sendMailFunctions.php");

define("PATTERNDOWN", "DOWN");
define("PATTERNSLOW", "DELAYED");
define("FAILDELIMIT", " - ");

define("PATTERNALERTS", "Sending Alerts");
define("PATTERNMONITORFAIL", "MONITOR-TEST-FAIL");

// Get log file to digest
if($argc != 3) {
    echoUsage();
} else {
    $first = true;
    $thisTarget= false;
    $thisConfig = false;
    foreach($argv as $arg) {
        if(!$first) {
            $cfParts = explode("=", $arg);
            if($cfParts[0] == "logfile") {
                if(!is_file($cfParts[1])) badLogFile($cfParts[1]);
                $thisTarget = $cfParts[1];
            } else if($cfParts[0] == "cf") {
                if(!is_file($cfParts[1])) badLogFile($cfParts[1]);
                $thisConfig = $cfParts[1];
            }
        } else {
            $first = false;
        }
    }
}
if(!$thisTarget || !$thisConfig) echoUsage();

require_once($thisConfig);

// Yesterday's date
$lastWeek = strtotime("-1 week");
$strDate = date('W', $lastWeek);
$strDate = "Week: ".$strDate;

$cmdBase = 'grep "'.$strDate.'" '.$thisTarget.' |grep ';

/*
 * Delay Lines
 */

$tCmd = $cmdBase.PATTERNSLOW;

$rslt = exec($tCmd, $arySlow);


/*
 * Down Lines
 */
$tCmd = $cmdBase.PATTERNDOWN;

$rslt = exec($tCmd, $aryDown);

/*
 * Alerts Lines
 */
$tCmd = $cmdBase.'"'.PATTERNALERTS.'"';

$rslt = exec($tCmd, $aryAlerts);

/*
 * Monitor Test Fail Lines
 */
$tCmd = $cmdBase.PATTERNMONITORFAIL;

$rslt = exec($tCmd, $aryMonitorFail);

$msg = "<html><head></head><body><h1>Site Monitor Report for the week beginning: ".date("l F jS Y", $lastWeek)."</h1>";

$msg .= "<p>URLs Monitored:</p>";
foreach($aryURL as $mUrl => $info) $msg .= "<a href='".$mUrl."' target='_blank' >".$info['displayname']."</a><br/>";

$msg .= "<p><b>Site Unreachable (non 200 HTTP response) Events: ".count($aryDown)."</b></p>";
if(count($aryDown) > 0) {
    $msg .= "<p>Down Event List: </p>";
    $msg .= '<table border="1" style="border-color: black;"><tr><td style="background-color: #dee5de;">';
    foreach($aryDown as $line) $msg .= $line."</td></tr><tr><td style='background-color: #dee5de;'>";
    $msg .= "</table>";
}
$msg .= "<p><b>Site Slow (delayed response): ".count($arySlow)."</b></p>";
if(count($arySlow) > 0) {
    $msg .= '<table border="1" style="border-color: black;"><tr><td style="background-color: #dee5de;">';
    foreach($arySlow as $line) $msg .= $line."</td></tr><tr><td style='background-color: #dee5de;'>";
    $msg .= "</table>";
}

$msg .= "<p><b>Monitor Failures (individual monitor attempts that failed): ".count($aryMonitorFail)."</b></p>";
$msg .= "<h2>Alerts Sent: ".count($aryAlerts)."</h2>";
$msg .= "</body></html>";

$msgText = "Site Monitor Report for: ".$strDate."\n\n";
$msgText .= "This message is best viewed in HTML, please enable HTML mail to view it.\n";

sendEmails($msgText, $msg, "Weekly SiteMonitor Report", $aryAlertUsers);

function echoUsage() {
    echo "Usage: \nYou must specific the log file for this script to process and the configuration file for the site monitor.\n\n
                    php doDailyReport.php logfile=/path/to/log/file cf=/path/to/configuration/file\n\n
                    Please try again.\n";
    exit();
}   
function badLogFile($file) {
    echo "The file specified (".$file." does not exist.\n\n";
    echoUsage();
}

function sendEmails($tMsg, $hMsg, $subj, $aryAlertTo) {
    
    foreach($aryAlertTo['user'] as $user) {
        foreach($user['alertmethods'] as $method) {
            if($method['type'] == "EMAIL" && $method['active'] && $method['weekly']) {
                sendMail($method['address'], $subj, $hMsg, $tMsg);
            }
        }
    }
    
}

?>
