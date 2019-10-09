<?php
// http://sommelier.sakura.ne.jp/paycheck/paycheck.php
//

header("Last-Modified: ". gmdate("D, d M Y H:i:s"). " GMT");
header("Cache-Control: no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Content-type: text/html; charset=utf-8");
//error_reporting(0);
date_default_timezone_set('Asia/Tokyo');
mb_internal_encoding("UTF-8");

require_once(dirname(__FILE__)."/functions.php");

//
// メール受け取り
//
$buff = file_get_contents("php://stdin");
if(!$buff) {
    echo "not good buff!<br>\n";
    exit();
}


//
// tokenファイル受け取り
//
//$token = file_get_contents(dirname(__FILE__)."/linetoken.txt");
if($argc < 2){
    echo "linetokenファイルを指定してください。";
    exit;
}
echo "linetoken_file=[{$argv[1]}]<br>\n";
$tokenfile = dirname(__FILE__)."/" . $argv[1];
if(!file_exists($tokenfile)){
    echo "tokenfileが見つかりません。:{$tokenfile}<br>";
    exit;
}
$token = file_get_contents($tokenfile);
$token = preg_replace("/[\r|\n]/","",$token);
$token = trim($token);
if(strlen($token)<=0){
    echo "not good token!<br>\n";
    exit();
}

$subject = get_subject($buff);
echo "subject=[{$subject}]<br>";
$body = get_body($buff);

$paytype = check_paytype($subject);
echo "paytype=[{$paytype}]<br>";

$ammount = check_ammount($paytype,$body);
echo "ammount=[{$ammount}]<br>";

$outbuff =<<<EOF
subject=[{$subject}]
paytype=[{$paytype}]
ammount=[{$ammount}]

------------------------------
body
------------------------------
{$body}
------------------------------

------------------------------
buff
------------------------------
{$buff}
EOF;

$rakutentype = check_rakuten($subject);
$num = 0;

if($paytype!="" && $rakutentype == ""){
    echo "<h4>LineNotfiy!:</h4>\n";
    $msg = "<{$paytype}>" . number_format($ammount) ."円";
    echo "msg={$msg}<br>\n";
    send_message_to_line($msg,$token);

    $stamp = date("Ymd_His",time());
    $tempfile = dirname(__FILE__) ."/mailbody_{$stamp}.txt";
    //file_put_contents($tempfile,$outbuff);
    echo "sended!<br>";

    $result = basic($paytype,$ammount);
    echo "api_result={$result}<br>\n";
    $num = 1;

}else{
    echo "<h4>not LineNotify</h4>\n";
}

if($rakutentype!="" && $num == 0){

    $result = basic($paytype,$ammount);
    echo "api_result={$result}<br>\n";

}else{
    echo "<h4>not send message to api</h4>\n";
}


exit();

?>