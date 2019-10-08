<?php

//
// 支払いタイプと金額抽出関数
//

function check_paytype($buff){
    if(preg_match("/楽天ペイ/ui",$buff,$match)){
        return $match[0];
    }elseif(preg_match("/LINE.Pay/ui",$buff,$match)){
        return $match[0];
    }elseif(preg_match("/PayPay/ui",$buff,$match)){
        return $match[0];
    }elseif(preg_match("/メルペイ/ui",$buff,$match)){
        return $match[0];
    }
}

function check_ammount($paytype,$buff){

    $ng_str = array();

    if(preg_match("/楽天/ui",$paytype,$match)){
        //$pattern = "/^利用金額([0-9 \,]+)円$/ui";
        $pattern = "/([0-9 \,]+)円/ui";
        $ng_str[]  = "/残高/ui";
        $ng_str[]  = "/利用前/ui";
        $ng_str[]  = "/取引前/ui";
    }elseif(preg_match("/LINE/ui",$paytype,$match)){
        //$pattern = "/^お支払い金額([0-9 \,]+)$/ui";
        //$pattern = "/^お支払い金額([0-9 \,]+)/ui";
        //>お支払い金額</td><td style="margin: 0px; padding: 15px 26px; border-top: 1px solid rgb(231, 233, 236);">10</td>
        $pattern = "/>お支払い金額<\/td><td[^>]*>([0-9 \,]+)<\/td>/ui";
    }elseif(preg_match("/PayPay/ui",$paytype,$match)){
        //$pattern = "/^([0-9,-]+)円$/ui";
        //$pattern = "/^([0-9,-]+)円/ui";
        $pattern = "/total-amount[^>]*><span>([0-9,-]+)<\/span>/ui";
    }elseif(preg_match("/メルペイ/ui",$paytype,$match)){
        $pattern = "/>メルペイで￥([0-9 \,]+)の支払いを受付けました<\/p>/ui";
    }

    $lines = explode("\n",$buff);

    for($i=0;$i<count($lines);$i++){
        if(preg_match($pattern,$lines[$i],$match)){
            $skip = false;
            for($j=0;$j<count($ng_str);$j++){
                if(preg_match($ng_str[$j],$lines[$i],$dummy))$skip = true;
            }
            if(!$skip){
                return trim(str_replace(",","",$match[1]));
            }
        }
    }
    return "developping";
}


//
// lineNotifyメッセージ //
//
function send_message_to_line($message,$linetoken){

    $path = dirname(__FILE__);

    if(strlen($linetoken)<=0){
        $linetoken = file_get_contents("{$path}/linetoken.txt");
        $linetoken = str_replace("\n","",$linetoken);
        $linetoken = str_replace("\r","",$linetoken);
        $linetoken = trim($linetoken);
    }

    $data = array("message" => $message);
    $data = http_build_query($data, "", "&");

    $options = array(
        'http'=>array(
            'method'=>'POST',
            'header'=>"Authorization: Bearer {$linetoken}\r\n"
                      . "Content-Type: application/x-www-form-urlencoded\r\n"
                      . "Content-Length: ".strlen($data)  . "\r\n" ,
            'content' => $data
        )
    );
    $context = stream_context_create($options);
    $json = file_get_contents("https://notify-api.line.me/api/notify",FALSE,$context );
    $res = json_decode($json,TRUE);
    echo "<pre>"; print_r($res); echo "</pre>";
}


//
// メール処理ユーティリティ関数 
//

function get_subject($buff){
    $lines = explode("\n",$buff);
    $subject_found= false;
    $subject = "";
    for($i=0;$i<count($lines);$i++){
        if(!$subject_found){
            if(preg_match("/^Subject:/i",$lines[$i],$match)){
                $subject = preg_replace("/^Subject:/i","",$lines[$i]);
                $subject_found = true;
            }
        }else{
            $first_char =substr($lines[$i],0,1);
            if($first_char==" " || $first_char == "\t"){
                $subject .= $lines[$i];
            }else{
                break;
            }
        }
    }
    return my_convertMailStr(trim($subject));
}

function get_body($buff){

    $lines = explode("\n",$buff);
    $body = "";
    $body_in = false;
    for($i=0;$i<count($lines);$i++){
        if($body_in){
            $body .= $lines[$i] ."\n";
        }else{
            if(strlen($lines[$i])<=0)$body_in=true;
        }
    }
    $charset = check_charset($buff);

//echo "charset=[{$charset}]<br>\n";

    $is_quoted_printable = check_is_quoted_printable($buff);
    $boundary = check_boundary($buff);
    $body = decode_email_body($body,$charset,$is_quoted_printable);
    $body = base64change($body,$boundary);
    $body = strip_brank_lines($body);
    return $body;
}

function my_convertMailStr($str){
   mb_language('ja');
   mb_internal_encoding('EUC-JP');
   $ret_string = mb_convert_encoding(mb_decode_mimeheader($str),"UTF-8","EUC-JP");
   mb_internal_encoding('UTF-8');
   return $ret_string;
}

function check_charset($buff){
    if(preg_match("/charsets*=s*(.+?)[;\n]/s",$buff, $match)){
        return strtolower(trim(str_replace('"',"",$match[1])));
    }else{
        return "";
    }
}

function check_is_quoted_printable($law_header){
    if(preg_match("/quoted-printable/is",$law_header,$match)){
        return true;
    }else{
        return false;
    }
}

function check_boundary($buff){
    //Content-Type: multipart/alternative; boundary="000000000000d8a4fa0587bc6b74"
    if(preg_match("/boundarys*=s*(.+?)[;\n]/s",$buff, $match)){
        return trim(str_replace('"',"",$match[1]));
    }else{
        return "";
    }
}


function decode_email_body($body,$charset="JIS",$is_quoted_printable=false){
    // http://program.station.ez-net.jp/mini/encode/quoted.asp
    // Content-Transfer-Encoding: quoted-printable は 8bitが7bitになっているのでそれを復元してやらないと文字化けする
    if($is_quoted_printable){
        $body = quoted_printable_decode($body);
    }
    // http://www.atmarkit.co.jp/ait/articles/0602/18/news009.html
    // 現在本文で使われているメールはほぼJIS
    // 海外からのスパムメールで、UTF-8か UTF-7が使われている程度
    //return mb_convert_encoding($body,"UTF-8","EUC-JP");
    $temp = mb_convert_encoding($body,"UTF-8",$charset);

    if(!check_is_right_encode($temp))$temp = mb_convert_encoding($body,"UTF-8","JIS");
    if(!check_is_right_encode($temp))$temp = mb_convert_encoding($body,"UTF-8","EUC-JP");

    return $temp;
    //$a = preg_match_all("/\(\,\(\,\(\,/",$temp,$matches); // お名前.comからのメールが化けている
    //if($a<=3){
    //    return $temp;
    //}else{
    //    return $temp;
    //}
}


function check_is_right_encode($temp){

    if(preg_match("/問い合わせ/ui",$temp,$dummy))return true;
    if(preg_match("/支払/ui",$temp,$dummy))return true;
    if(preg_match("/楽天/ui",$temp,$dummy))return true;
    if(preg_match("/PayPay/ui",$temp,$dummy))return true;
    if(preg_match("/Line Pay/ui",$temp,$dummy))return true;

    return false;
}

function base64change($body,$boundary){

    $lines = explode("\n",$body);

    $test_i = 0;

/*
    $body_buff = "";
    $septext ="";
    foreach($lines as $line){
        if(strlen($septext)<=0){
            //--000000000000a0b8be058193429b
            if(preg_match("/^--[0-9A-Za-z=_\.\-]{5,}/ui",$line)){
                if(!preg_match("/Original Message/ui",$line)){
                    $septext = str_replace("\n","",$line);
                }
            }
        }
        $body_buff .= $line."\n";
        $test_i++;
    }
*/
    //echo "Separeter=[{$septext}]";
    if(strlen($boundary)<=0){
        return $body;
    }
    $septext = "--{$boundary}";
    $parts = explode($septext,$body);

    //echo "parts count=" . count($parts);

    $body_buff2 = "";
    foreach($parts as $part){

        $part_head = "";
        $part_body = "";
        $is_body = false;
        $is_pre  = true;

        $lines = explode("\n",$part);
        //echo "<pre>"; print_r($lines); echo "</pre>";

        foreach($lines as $line){
            $line = str_replace("\n","",$line);
            $line = str_replace("\r","",$line);

            if($line!="")$is_pre = false;
            if($is_pre)continue; 
            if(!$is_body){
                if($line == ""){
                    $is_body = true;
                }else{
                    $part_head .= $line . "\n";
                }
            }else{
                $part_body .= $line ."\n";
            }
        }

        $body_buff2 .= $part_head ."\n\n";
        if(preg_match("/Content-Transfer-Encoding:[ ]*base64/ui",$part_head)){
            //echo "imap_base64<br>";
            //$body_buff2 .= imap_base64($part_body) ."\n";
            $body_buff2 .= base64_decode($part_body) ."\n";
        }elseif(preg_match("/Content-Transfer-Encoding:[ ]*quoted-printable/ui",$part_head)){
            //echo "quoted-printable<br>";
            $body_buff2 .= quoted_printable_decode($part_body) ."\n";
        }
    }
    return $head_buff . $body_buff2;
}

function strip_brank_lines($body){

    $lines = explode("\n",$body);

    $body = "";
    $in_blank_part = false;
    $i=0;
    foreach($lines as $line){
        $i++;
        $line = str_replace("\n","",$line);
        $line = str_replace("\r","",$line);
        if(strlen($line)<=0){
            $in_blank_part=true;
            //echo "[{$i}] in_blank_part[{$line}]<br>\n";
        }else{
            if($in_blank_part){
                //echo "[{$i}] off_blank_part[{$line}]<br>\n";
                $body .="\n";
                $in_blank_part=false;
            }
            $body .= $line . "\n";
        }
    }
    return $body;
}

