<?php

/*
 * Configuration for check site status. The location of this 
 * configuration file should be passed on the command line.
 * 
 * @author Brian Roy
 * @date 10-17-2011
 * 
 */

/* Log file for this monitor... used for daily, weekly and monthly reporting. */
define("LOGFILE", "/usr/local/SiteMonitor/home_status.log");
/* Status file for this monitor... maintains status between runs. */
define("STATUSFILE", "/usr/local/SiteMonitor/home_status_stat");


/* Array of URLs to be monitored */
$aryURL = array(
    "https://mymonitoredhost.com" =>
        array("displayname" => "Index", // Name for URL to display in reports/alerts.
            "maxdelay" => 4,            // Maximum delay to load the url. Longer = fail
            "alertafter" => 2,          // Number of "fail" attempts to alert after
            "emailalertmultiple" => 3), // This number is multiplied by the "alertafter" value to determine when to send email alerts. In this case an email alert will be sent every 2*3 sequential failed monitor attempts.
    "https://mymonitoredhost.com/api/v1/list/10/13/2011" =>
        array("displayname" => "API",
            "maxdelay" => 2,
            "alertafter" => 2,
            "emailalertmultiple" => 3),
);

$aryAlertUsers = array(
    "user" => array(
        "name" => "Brian Roy",      // User Name
        "alertmethods" => array(
            0 => array("type" => "IM",                          // IM (XMPP) alerts
                        "address" => "me@myjabberdomain.com",   // XMPP Address
                        "active" => true),                      // Active true/false - controls if this type of alerts are sent for the user.
            1 => array("type" => "SMS",                         // SMS based alerts
                        "address" => "16508675309",             // SMS address - phone number.
                        "active" => true),                      // Active true/false - controls if this type of alerts are sent for the user.
            2 => array("type" => "EMAIL",                       // EMAIL alerts/reports
                "address" => "me@myemail.com",                  // Email address
                "alert" => true,                                // Send Email Alerts? true/false
                "active" => true,                               // Active true/false - controls if this type of alerts are sent for the user.
                "daily" => true,                                // Send daily summary report
                "weekly" => true,                               // Send weekly summary report
                "monthly" => true),                             // Send monthly summary report
        ),
    ),  
);


?>
