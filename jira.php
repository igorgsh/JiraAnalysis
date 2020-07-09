<?php

require_once('getURL.php');
//require_once('jsonpath.php');

$NEWLINE = "\n";

function readPortion($url, $start) {
	$hdr = array(
		"Content-Type" => "application/json"
    );
	$usrPwd = 'igorgsh@gmail.com:NQTdRRcaizU9jc2kcof72B51';
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
//$defTZ = new DateTimeZone("Europe/Kiev");
$defTZ = new DateTimeZone("GMT+0");

if (!array_key_exists("begin", $_GET)) {
    $dateBeg = new Datetime('now', $defTZ);
} else {
    $dateBeg = date_create_from_format('Y-m-d',$_GET["begin"], $defTZ);
}    

if (!array_key_exists("end", $_GET)) {
    $dateEnd = new Datetime('now', $defTZ);
} else {
    $dateEnd = date_create_from_format('Y-m-d',$_GET["end"], $defTZ);
}    
$dateBeg -> setTime(0,0,0);
$dateEnd -> setTime(0,0,0);

if (!array_key_exists("jql", $_GET) || $_GET["jql"] == "") {
    $jql = "";
} else {
		$jql = "(". $_GET["jql"].")+AND+";
}    

$packetSize=10;
$DELIM = ";";
$QUOTESIGN = '"';
$fieldList = "resolution,project,assignee,status,summary,reporter,customfield_10034,customfield_10036,customfield_10039,customfield_10040,aggregatetimeoriginalestimate,aggregatetimespent,created,timeoriginalestimate";

$baseUrl = "https://flyingdonkey.atlassian.net/rest/api/3";
$issueUrl = "https://flyingdonkey.atlassian.net/browse/";
//$method = "search?jql=".$jql."((created<=".date_format($dateEnd,'Y-m-d').")and"."(updated>=".date_format($dateBeg,'Y-m-d')."))&expand=changelog"  ;
$method = "search?jql=".$jql."((created<=".date_format($dateEnd,'Y-m-d').")and"."(updated>=".date_format($dateBeg,'Y-m-d')."))"  ;
$method = $method."&fields=".$fieldList;
$fullUrl = $baseUrl.'/'.$method;
$fullUrl = urlencode($fullUrl);



//echo "FullUrlD=".urldecode($fullUrl)."\n";
//echo "FullUrl0=".$fullUrl."\n";
//echo $NEWLINE;

set_time_limit(3000);
$startPos = 0;


$filename="report_".date_format($dateBeg,'Y-m-d')."_".date_format($dateEnd,'Y-m-d').".csv";

header("Cache-Control: public");
header("Content-Description: File Transfer");
header("Content-Disposition: attachment; filename=$filename");
header("Content-Type: application/txt");

echo "Date Change;Project;Issue;Status;Resolution;Reporter;Assignee;Initial Estimation;Current Estimation;Total Time Spent;Credit;Summary;Total Reopen Counter;URL;Author of Change;Estimation Change;Reopen Counter;Reopen Reason;Time Spent Logged;Total Time Spent for ticket;Key-Author;Initial Estimation Set;Date of close";

echo $NEWLINE;


for ($startIssues = 0, $jsonArray = readPortion($fullUrl,$startIssues); 
	$jsonArray && $startIssues < $jsonArray["total"]; 
	$startIssues += $jsonArray["maxResults"], $jsonArray = readPortion($fullUrl,$startIssues)) {


    $issues = $jsonArray["issues"];
// Loop for all issues included in resultset
    foreach ($issues as $issue ) {
		$url = $baseUrl."/issue/".$issue["key"]."/changelog?dummy";
		
		$clDay = array();
		$initialEstimation="";
		$initialEstimationDate = date_create_from_format('Y-m-d\TH:i:s.ve',$issue["fields"]["created"], $defTZ);
		$closeDate = $initialEstimationDate;
		$spentAuthor = array();
		
//echo "created=".$issue["fields"]["created"];
//var_dump($initialEstimationDate);

		for ($startClog = 0, $clogArray = readPortion($url,$startClog); 
			$clogArray && $startClog < $clogArray["total"]; 
			$startClog += $clogArray["maxResults"], $clogArray = readPortion($url,$startClog)) {
			$histories = $clogArray["values"];

		
			foreach ($histories as $hist) {

				$clDate = date_create_from_format('Y-m-d\TH:i:s.uO',$hist["created"], $defTZ);
				$dayCL = date_format($clDate,'Y-m-d');
				$author = $hist["author"]["displayName"];
				foreach ($hist["items"] as $item) {
				//Get Initial Estimation
					$EstChange = 0;
					$ReOpenCounter = 0;
					$ReOpenReason = "";
				

					//if ($item["field"] == "Dev Estimate") { 
					if ($item["field"] == "timeoriginalestimate") {
						if ($clDate < $initialEstimationDate) {
							$initialEstimation=$item["toString"];
							$initialEstimationDate = $clDate;
						}
					}
					if ($item["field"] == "status" AND $item["toString"]=="Done") {
						if ($clDate > $closeDate) {
							$closeDate = $clDate;
						}
					}
					
					if ($clDate >= $dateBeg AND $clDate <=  $dateEnd) {
						if ($item["field"] == "Dev Estimate" AND !is_null($item["fromString"]) ) { //Dev Estimation changed
							$EstChange= $item["toString"] - $item["fromString"];
						} 
						if ($item["field"] == "Re-open counter") {
							$ReOpenCounter = 1;
						}
						if ($item["field"] == "Re-open reason") {
							$ReOpenReason = $item["toString"]; 
						}
					
						if ($EstChange !=0 OR $ReOpenCounter != 0 OR $ReOpenReason!="") {
							if ( !array_key_exists($dayCL, $clDay) ) {	
								$clDay[$dayCL] = array();
								$clDay[$dayCL]["day"] = $dayCL;
								$clDay[$dayCL]["author"] = array();
							}
							if ( !array_key_exists($author, $clDay[$dayCL]["author"]) ) {
								$clDay[$dayCL]["author"][$author] = array();
								$clDay[$dayCL]["author"][$author]["ReOpenCounter"] =0;
								$clDay[$dayCL]["author"][$author]["EstChange"] = 0;
								$clDay[$dayCL]["author"][$author]["ReOpenReason"] = "";
							}
							$clDay[$dayCL]["author"][$author]["ReOpenReason"] = $ReOpenReason;	
							$clDay[$dayCL]["author"][$author]["ReOpenCounter"] += $ReOpenCounter;
							$clDay[$dayCL]["author"][$author]["EstChange"] += $EstChange;
							$clDay[$dayCL]["author"][$author]["displayName"] = $author; 	    
							$clDay[$dayCL]["author"][$author]["timespent"] = 0; 	
							$clDay[$dayCL]["author"][$author]["TotalSpent"] = 0;
						}
					}
				}
			}
		}		
	
	// Second - count the timespent
		$wlURL=$baseUrl."/issue/".$issue["key"]."/worklog?dummy";
		//$wlArray = sequentialRead($wlURL,"worklogs",$packetSize,0,false);
		for ($startWL = 0, $wlArray = readPortion($wlURL,$startWL);
			$wlArray && $startWL < $wlArray["total"]; 
			$startWL += $wlArray["maxResults"], $wlArray = readPortion($wlURL,$startWL)) {

			$worklogs = $wlArray["worklogs"];
//loop for calculate total spent by any author
		
			foreach ($worklogs as $wlItem) {
				if ($wlItem["updateAuthor"]["displayName"]=="FD Reporting") {
					$author = $wlItem["comment"]["content"][0]["content"][0]["text"];
				} else {
					$author = $wlItem["updateAuthor"]["displayName"];
				}
				if ( !array_key_exists($author, $spentAuthor) ) {
					$spentAuthor[$author] = 0;
				}
				$spentAuthor[$author] += $wlItem["timeSpentSeconds"];
			}
			foreach ($worklogs as $wlItem) {

				$wlDate = date_create_from_format('Y-m-d\TH:i:s.uO',$wlItem["started"], $defTZ);
				$dayWL = date_format($wlDate,'Y-m-d');
				if ($wlDate >= $dateBeg AND $wlDate <=  $dateEnd) {
					if ($wlItem["updateAuthor"]["displayName"]=="FD Reporting") {
						$author = $wlItem["comment"]["content"][0]["content"][0]["text"];
					} else {
						$author = $wlItem["updateAuthor"]["displayName"];
					}				
					if ( !array_key_exists($dayWL, $clDay) ) {
						$clDay[$dayWL] = array();
						$clDay[$dayWL]["day"] = $dayWL;
						$clDay[$dayWL]["author"] = array();
					}

					if (!array_key_exists($author, $clDay[$dayWL]["author"])) {
						$clDay[$dayWL]["author"][$author] = array();
						$clDay[$dayWL]["author"][$author]["timespent"] = 0;
						$clDay[$dayWL]["author"][$author]["ReOpenReason"] = "";
						$clDay[$dayWL]["author"][$author]["displayName"] = $author; 	    
						$clDay[$dayWL]["author"][$author]["ReOpenCounter"] = 0;
						$clDay[$dayWL]["author"][$author]["EstChange"] = 0;
						$clDay[$dayWL]["author"][$author]["TotalSpent"] = $spentAuthor[$author];
					}			
					$clDay[$dayWL]["author"][$author]["timespent"] += $wlItem["timeSpentSeconds"] ;
					$clDay[$dayWL]["author"][$author]["TotalSpent"] = $spentAuthor[$author] ;
				}
			}
		}
		
//echo "Date Change;Project;Issue;Status;Resolution;Reporter;Assignee;Initial Estimation;Current Estimation;Total Time Spent;Credit;Summary;Total Reopen Counter;URL;Author of Change;Estimation Change;Reopen Counter;Reopen Reason;Time Spent Logged;Total Time Spent for ticket;Key-Author;Initial Estimation Set;Date of close";

		foreach ($clDay as $dayKey => $dayValue) {
			foreach ($dayValue["author"] as $dAuthor => $vAuthor) {
//var_dump($vAuthor);				
				echo $QUOTESIGN.$dayKey.$QUOTESIGN;
    	        echo $DELIM;	
    
				echo $QUOTESIGN.$issue["fields"]["project"]["name"].$QUOTESIGN;
				echo $DELIM;	


				echo $QUOTESIGN.$issue["key"].$QUOTESIGN;
				echo $DELIM;

				echo $QUOTESIGN.$issue["fields"]["status"]["name"].$QUOTESIGN;
				echo $DELIM;	
			
				if (array_key_exists("resolution", $issue) AND $QUOTESIGN.$issue["fields"]["resolution"]) { 
					echo $QUOTESIGN.$issue["fields"]["resolution"]["name"].$QUOTESIGN;
				} else {
					echo $QUOTESIGN.$QUOTESIGN;
				}
				echo $DELIM;
	

				echo $QUOTESIGN.$issue["fields"]["reporter"]["displayName"].$QUOTESIGN;
				echo $DELIM;	

				if (array_key_exists("assignee", $issue) AND $QUOTESIGN.$issue["fields"]["assignee"]) { 
					echo $QUOTESIGN.$issue["fields"]["assignee"]["displayName"].$QUOTESIGN;
				} else {
					echo $QUOTESIGN.$QUOTESIGN;
				}
				echo $DELIM;	

				echo $QUOTESIGN.$initialEstimation.$QUOTESIGN;
				echo $DELIM;	


//Dev Estimate
//				echo $QUOTESIGN.$issue["fields"]["customfield_10034"].$QUOTESIGN;
				echo $QUOTESIGN.round($issue["fields"]["aggregatetimeoriginalestimate"]/3600,2).$QUOTESIGN;
				echo $DELIM;	

				echo $QUOTESIGN.round($issue["fields"]["aggregatetimespent"]/3600,2).$QUOTESIGN;
				echo $DELIM;

//Total Credit Hours
				echo $QUOTESIGN.$issue["fields"]["customfield_10036"].$QUOTESIGN;
				echo $DELIM;	
				
				echo $QUOTESIGN.$issue["fields"]["summary"].$QUOTESIGN;
				echo $DELIM;	

//Re-open Counter
				echo $QUOTESIGN.$issue["fields"]["customfield_10039"].$QUOTESIGN;
				echo $DELIM;	

//URL
				echo $QUOTESIGN.$issueUrl.$issue["key"].$QUOTESIGN;
				echo $DELIM;

				echo $QUOTESIGN.$vAuthor["displayName"].$QUOTESIGN;
				echo $DELIM;

				echo $QUOTESIGN.$vAuthor["EstChange"].$QUOTESIGN;
				echo $DELIM;
				
				echo $QUOTESIGN.$vAuthor["ReOpenCounter"].$QUOTESIGN;
				echo $DELIM;

				echo $QUOTESIGN.$vAuthor["ReOpenReason"].$QUOTESIGN;
				echo $DELIM;

				echo $QUOTESIGN.round($vAuthor["timespent"]/3600,2).$QUOTESIGN;
				echo $DELIM;

				echo $QUOTESIGN.round($vAuthor["TotalSpent"]/3600,2).$QUOTESIGN;
				echo $DELIM;

				echo $QUOTESIGN.$issue["key"]."_".$vAuthor["displayName"].$QUOTESIGN;
				echo $DELIM;

				echo $QUOTESIGN.$initialEstimationDate->format('Y-m-d').$QUOTESIGN;
				echo $DELIM;

				if ($issue["fields"]["status"]["name"]=="Done") {
					echo $QUOTESIGN.$closeDate->format('Y-m-d').$QUOTESIGN;
				} else {
					echo $QUOTESIGN.$QUOTESIGN;		
				}
				//echo $DELIM;

				echo $NEWLINE;
		
			}
		}
    }
//    echo "#####################";

}

//readfile($filename); 


?>
