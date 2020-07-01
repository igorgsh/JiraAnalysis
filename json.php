<?php>

$jsonArray = array();

$jsonArray = json_decode($_POST["txt"], true);

print "Result=";
print_r($jsonArray);
