#!/usr/bin/php
<?PHP
    // Change this as needed to the correct path to pheanstalk on your system.
    require_once("/usr/local/api-base/pheanstalk/pheanstalk_init.php");
    require_once("sendMailFunctions.php");

    // Get configuration file
    if($argc != 2) {
        echoUsage();
    } else {
        $cfParts = explode("=", $argv[1]);
        if($cfParts[0] != "cf") echoUsage();

        if(!is_file($cfParts[1])) badConfigFile($cfParts[1]);

        require_once($cfParts[1]);
    }

	// Read in our status file
	$aryStatus = array();
	$sFile = file(STATUSFILE);
	if($sFile && count($sFile) >= 1) {
		// Have the file and lines... parse it
		foreach($sFile as $fLine) {
			$lParts = explode("|", $fLine);
			if(count($lParts) == 4) {
				$aryStatus[(trim($lParts[0]))] = array(
						"sequential_failures" => trim($lParts[1]),
						"last_alert_sent" => trim($lParts[2]),
						"failed_at" => trim($lParts[3]),
					);
			} else {
				$strLog = "ERROR: ".date('r')." - Week: ".date('W')." Bad status file line found **".$fLine."**\n";
                                error_log($strLog, 3, LOGFILE);
			}
		}
	} 
	foreach($aryURL as $tUrl => $config) {

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $tUrl); 
        	curl_setopt($ch, CURLOPT_HEADER, TRUE); 
        	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE); 
        	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);	
		$head = curl_exec($ch); 
        	$cInfo = curl_getinfo($ch);
		$httpCode = $cInfo['http_code'];
		$httpTime = $cInfo['total_time']; 
        	curl_close($ch);

		if($httpTime > $config['maxdelay']) {
			$strLog = "ERROR: ".date('r')." - Week: ".date('W')." Recieved DELAYED HTTP - ".$httpCode." - from ".$tUrl." in: ".$httpTime."\n";
                        error_log($strLog, 3, LOGFILE);
     	           	$strLog = "ERROR: ".date('r')." - Week: ".date('W')." Headers: ".$head."\n";
                        error_log($strLog, 3, LOGFILE);
			$strLog = "ERROR: ".date('r')." - Week: ".date('W')." Full HTTP Transaction Details: ".var_export($cInfo, true)."\n";
                        error_log($strLog, 3, LOGFILE);
			$isDown = true;
		} else {
	
			if($httpCode == "200") {
				$strLog = "NOTICE: ".date('r')." - Week: ".date('W')." Recieved NORMAL HTTP - ".$httpCode." - from ".$tUrl." in: ".$httpTime."\n";
                                error_log($strLog, 3, LOGFILE);
				$isDown = false;
			} else {
				$isDown = true;
				$strLog = "ERROR: ".date('r')." - Week: ".date('W')." Recieved DOWN HTTP - ".$httpCode." - from ".$tUrl." in: ".$httpTime."\n";
                                error_log($strLog, 3, LOGFILE);
				$strLog = "ERROR: ".date('r')." - Week: ".date('W')." Headers: ".$head."\n";
                                error_log($strLog, 3, LOGFILE);
			}
		}
		if($isDown) {
			// check $aryStatus and see if we need to alert
            if(array_key_exists($tUrl, $aryStatus)) checkEmailAlerts($tUrl, $aryURL, $aryAlertUsers, $aryStatus, $httpCode, $httpTime, $config['alertafter'], $config['emailalertmultiple']);
			if(array_key_exists($tUrl, $aryStatus) && $aryStatus[$tUrl]['sequential_failures'] >= $config['alertafter']
						&& $aryStatus[$tUrl]['last_alert_sent'] < (time() - 120)) {

				// Send Our Alert
				$strLog = "DEBUG: ".date('r')." - Week: ".date('W')." Sending Alerts for ".$tUrl."\n";
                                error_log($strLog, 3, LOGFILE);
                sendAlerts($tUrl, $aryURL, $aryAlertUsers, $aryStatus, $httpCode, $httpTime, $config['alertafter']);
				$aryStatus[$tUrl]['last_alert_sent'] = time();
				$aryStatus[$tUrl]['sequential_failures'] = $aryStatus[$tUrl]['sequential_failures'] + 1;
			} else {
				$strLog = "DEBUG: ".date('r')." - Week: ".date('W')." Not sending alerts - fails: ".$aryStatus[$tUrl]['sequential_failures']." Last Alert: ".date('r', $aryStatus[$tUrl]['last_alert_sent'])."\n";
                                error_log($strLog, 3, LOGFILE);
				if(array_key_exists($tUrl, $aryStatus)) {
					// Update existing
					$aryStatus[$tUrl]['sequential_failures'] = $aryStatus[$tUrl]['sequential_failures'] + 1;
				} else {
					// Create New
					$aryStatus[$tUrl] = array(
						'sequential_failures' => 1,
						'last_alert_sent' => 0,
						'failed_at' => time(),
					);
				}
			}

		} else {
			if(array_key_exists($tUrl, $aryStatus)) unset($aryStatus[$tUrl]);
		}	
	}
	// Re-Write the status file


	$fpS = fopen(STATUSFILE, 'w');
	foreach($aryStatus as $sUrl => $status) {
		if(array_key_exists($sUrl, $aryURL)) {
			$outLine = $sUrl."|".$status['sequential_failures']."|".$status['last_alert_sent']."|".$status['failed_at']."\n";
			fwrite($fpS, $outLine);
			$strLog = "MONITOR-TEST-FAIL: ".date('r')." - Week: ".date('W')." The URL ".$sUrl." has been failing since: ".date('r', $status['failed_at'])." - ".$status['sequential_failures']." tests have failed in sequence.\n"; 
                        error_log($strLog, 3, LOGFILE);
		}
	}
	fclose($fpS);
        
function sendAlerts($tUrl, $aryUrls, $aryAlertTo, $aryStatus, $lastCode, $lastTime, $threshold) {

    $bs = new Pheanstalk("localhost:11300");
    
    // Generate the alert
    $alert = $aryUrls[$tUrl]['displayname']." has failed monitoring (".$aryStatus[$tUrl]['sequential_failures']." in a row) since: ".date('r', $aryStatus[$tUrl]['failed_at']);
    $alert .= " Last HTTP Code: ".$lastCode." Last HTTP Time: ".$lastTime." - ".date('r');

    foreach($aryAlertTo['user'] as $user) {
        foreach($user['alertmethods'] as $method) {
            if($method['type'] == "IM" && $method['active']) {
                $aryMsg = array("imTo" => $method['address'], "imMsg" => $alert);
                $bs->useTube("prod_send_xmpp_queue")->put(json_encode($aryMsg));
            }
            if($method['type'] == "SMS" && $method['active']) {
                $aryMsg = array("smsTo" => $method['address'], "imMsg" => $alert);
		        $bs->useTube("prod_send_gvsms_queue")->put(json_encode($aryMsg));
            }
        }
    }
    
}
function checkEmailAlerts($tUrl, $aryUrls, $aryAlertTo, $aryStatus, $lastCode, $lastTime, $threshold, $emailMultiple) {

    $bs = new Pheanstalk("localhost:11300");

    // Generate the alert
    $alert = $aryUrls[$tUrl]['displayname']." has failed monitoring (".$aryStatus[$tUrl]['sequential_failures']." in a row) since: ".date('r', $aryStatus[$tUrl]['failed_at']);
    $alert .= " Last HTTP Code: ".$lastCode." Last HTTP Time: ".$lastTime." - ".date('r');

    foreach($aryAlertTo['user'] as $user) {
        foreach($user['alertmethods'] as $method) {
            if($method['type'] == "EMAIL" && $method['active'] & $method['alert']
                && ($aryStatus[$tUrl]['sequential_failures'] % ($threshold * $emailMultiple) == 0)) {
                sendMail($method['address'], "SiteMonitor Alert", "", $alert);
            }
        }
    }

}
function echoUsage() {
    echo "Usage: \nYou must specific the configuration for this script.\n\n
                    php check_site_status.php cf=/path/to/configuraiton/file\n\n
                    Please try again.\n";
    exit();
}   
function badConfigFile($file) {
    echo "The configuration file specified (".$file." does not exist.\n\n";
    echoUsage();
}
?>
