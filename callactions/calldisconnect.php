<?php
if(file_exists("/var/www/html/base_path.php")){
    include_once("/var/www/html/base_path.php");
}

$header_name = $_SERVER['HTTP_HOST'];
$param       = file_get_contents('php://input');
$param       = json_decode($param, true);
$final       = "";

$fields_string = "";
$resFormat     = $_REQUEST['resFormat'];
$host   = $header_name;

if($host !="127.0.0.1" && $host!="localhost"){
  print json_encode(array("message"=>"Host $host not allowed to send request","status"=>"FAILURE"));
  exit;
}


//$url = "$header_name/apps/addlead.php";
$url = "http://127.0.0.1/apps/appsHandler.php";
$ch  = curl_init();

foreach ($param as $i => $v) {
    if (is_array($v)) {
        $fields_string = " ";
        $mobile        = '';
        foreach ($v as $k => $val) {
            $fields_string .= (($fields_string == " ") ? "$k=$val" : "&$k=$val");
            if ($k == 'mobile') {
                $mobile = $val;
            }
        }

        $result = do_remote($url, trim($fields_string));
        $p = xml_parser_create();
        xml_parser_set_option($p, XML_OPTION_CASE_FOLDING, false);
        xml_parse_into_struct($p, $result, $vals);
        xml_parser_free($p);

        foreach ($vals as $key1 => $val1) {
            if ($vals[$key1]["tag"] == 'response') {
                $result = $vals[$key1]["value"];
            }
        }
        query_logs(print_r($var, true), "Parameters at " . $i . ":");
        query_logs($result, "Response at " . $i . ":");
        if ($resFormat) {
            $final .= $result;
        } else {
            $final .= "<RES" . $i . "><mobile>" . $mobile . "</mobile><result>" . $result . "</result></RES" . $i . ">";
        }
        $fields_string = "";
        $result        = "";
    } else {
        query_logs("Request is Empty", "Request");
    }
}
if ($resFormat) {
    print $final;
} else {
    print "<xml><response>$final</response></xml>";
}

function do_remote($url, $fields_string)
{
    $urlstr = "$url?$fields_string";
    $cmd = "curl -X GET '$urlstr' -H 'cache-control: no-cache'";

    exec($cmd, $result);
    return $result[0];
}

function query_logs($query, $log_type)
{
    global $add_lead_path;
    if (file_exists("$add_lead_path/isave_status_bulk.txt")) {
        $fth = fopen("$add_lead_path/isave_status_bulk.txt", "a+");
        fwrite($fth, date("d-m-Y H:i:s") . " $log_type $query \n");
        fclose($fth);
    }
}