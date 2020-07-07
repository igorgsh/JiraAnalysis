<?php

require_once('getURL.php');
//require_once('jsonpath.php');

//$NEWLINE = "\n";

function readPortion($url, $usrPwd, $start) {
	$hdr = array(
		"Content-Type" => "application/json"
    );
    $url0 = $url."&startAt=".$start;
//echo "URL=".$url0."\n";
	
    $json = get_remote_data($url0 , $usrPwd, $hdr);
    if ($json) {
		$resultArray = json_decode($json, true);
	} else {
		return false;
	}
	return $resultArray;
}
$defTZ = new DateTimeZone("GMT+0");

if (!array_key_exists("jql", $_GET) || $_GET["jql"] == "") {
    $jql = "";
} else {
		$jql = "(". $_GET["jql"].")";
}    

$usrPwd = 'igorgsh@gmail.com:NQTdRRcaizU9jc2kcof72B51';
$baseUrl = "https://flyingdonkey.atlassian.net/rest/api/3";
$method = "search?jql=".$jql;
$method = $method."&fields=customfield_10034";
$fullUrl = $baseUrl.'/'.$method;

//echo $fullUrl."<BR/>\n";

$fullUrl = urlencode($fullUrl);
//body
//{
//    "id": "13250",
//    "self": "https://flyingdonkey.atlassian.net/rest/api/3/issue/13250",
//    "key": "ITP-36",
//    "fields": {
//        "timetracking":{
//        "originalEstimate": "1h"
//        }
//    }
//} 
/*
	$hdr = array(
		"Content-Type" => "application/json"
    );
*/
	$hdr = array ("Content-Type: application/json");

for ($startIssues = 0, $jsonArray = readPortion($fullUrl, $usrPwd, $startIssues); 
	$jsonArray && $startIssues < $jsonArray["total"]; 
	$startIssues += $jsonArray["maxResults"], $jsonArray = readPortion($fullUrl,$usrPwd,$startIssues)) {

    $issues = $jsonArray["issues"];
// Loop for all issues included in resultset
    foreach ($issues as $issue ) {
//var_dump($issue);
//echo "<BR/>\n";	
		$est = $issue["fields"]["customfield_10034"];
		if (!$est) {
			$est = 0;
		}
		$body = '{';
		$body .= '"id":"'.$issue["id"].'",';
		$body .= '"self":"'.$issue["self"].'",';
		$body .= '"key":"'.$issue["key"].'",';
		$body .= '"fields": {"timetracking": {';
		$body .= '"originalEstimate":"'.$est.'h"';
		$body .='}}}';
		//echo $body."<BR/>\n";
print_r ($body);		
		$url = $baseUrl."/issue/".$issue["key"];
		$res = get_remote_data($url, $usrPwd, $hdr, $body, "PUT"); 
//var_dump($res);		
		if (!$res) {
			echo "Something wrong:".$res."<BR/>\n";
		} else {
			echo " :OK!";
		}
		echo "<BR/>\n";
	}
}
?>
