<?php

require 'vendor/autoload.php';

use LINE\LINEBot;
use LINE\LINEBot\HTTPClient\CurlHTTPClient;
use LINE\LINEBot\MessageBuilder\TextMessageBuilder;
use LINE\LINEBot\MessageBuilder\FlexMessageBuilder;
use LINE\LINEBot\MessageBuilder\Flex\ContainerBuilder\BubbleContainerBuilder;
use LINE\LINEBot\MessageBuilder\Flex\ContainerBuilder\CarouselContainerBuilder;
use LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder;
use LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\BoxComponentBuilder;

//設定金鑰
$weatherKey = 'YOUR_WEATHER_KEY'; //氣象局
$aqiKey = 'YOUR_AQI_KEY'; //環保署

//設定Token 
$ChannelSecret = 'YOUR_CHANNEL_SECRET'; 
$ChannelAccessToken = 'YOUR_CHANNEL_ACCESS_TOKEN'; 
 
//讀取資訊 
$HttpRequestBody = file_get_contents('php://input'); 
$HeaderSignature = $_SERVER['HTTP_X_LINE_SIGNATURE']; 
 
//驗證來源是否是LINE官方伺服器 
$Hash = hash_hmac('sha256', $HttpRequestBody, $ChannelSecret, true); 
$HashSignature = base64_encode($Hash); 
if($HashSignature != $HeaderSignature) { 
    die('hash error!'); 
}
$httpClient = new CurlHTTPClient($ChannelAccessToken);
$bot = new LINEBot($httpClient, ['channelSecret' => $ChannelSecret]);

//解析
$dataBody = json_decode($HttpRequestBody, true);

//逐一處理事件 
foreach($dataBody['events'] as $Events) {
    if($Events['type'] == 'message') {

        $msgType = $Events['message']['type'];

        //處理文字訊息
        if($msgType == 'text') {

            $msgText = $Events['message']['text'];

            if(substr($msgText, -6) == '天氣') {
                $city = substr($msgText, 0, (strlen($msgText) - 6));
                if(!$city) {
                    $replyMsg = new TextMessageBuilder('請輸入要查詢的縣市名稱。');
                }
                else {
                    $city = str_replace('台', '臺', $city);

                    $listCity = ['基隆市', '臺北市', '新北市', '桃園市', '新竹市', '臺中市', '嘉義市', '臺南市', '高雄市', '新竹縣', '苗栗縣', '彰化縣', '南投縣', '雲林縣', '屏東縣', '宜蘭縣', '花蓮縣', '臺東縣', '澎湖縣', '金門縣', '連江縣', '嘉義縣'];
                    $listShii = ['基隆', '臺北', '新北', '桃園', '新竹', '臺中', '嘉義', '臺南', '高雄'];
                    $listShan = ['新竹', '苗栗', '彰化', '南投', '雲林', '屏東', '宜蘭', '花蓮', '臺東', '澎湖', '金門', '連江', '嘉義'];

                    if(!(strpos($city, '市') || strpos($city, '縣'))) {
                        if(in_array($city, $listShii)) {
                            $city = $city . '市';
                        }
                        else if(in_array($city, $listShan)) {
                            $city = $city . '縣';
                        }
                    }

                    if(in_array($city, $listCity)) {
                        $content = file_get_contents('https://opendata.cwb.gov.tw/api/v1/rest/datastore/F-C0032-001?Authorization=' . $weatherKey . '&format=JSON&locationName=' . $city);
                        $json = json_decode($content, true);

                        $aqiContent = file_get_contents('https://data.epa.gov.tw/api/v1/aqx_p_432?api_key=' . $aqiKey);
                        $aqiJson = json_decode($aqiContent, true);

                        $msg = array();
                        $description = ['天氣簡述', '降雨機率', '最低溫', '體感簡述', '最高溫'];
                        for($i = 0; $i <= 2; $i++) {
                            $msg[$i] = "[" . $json['records']['location'][0]['weatherElement'][0]['time'][$i]['startTime'] . ' ~ ' . $json['records']['location'][0]['weatherElement'][0]['time'][$i]['endTime'] . "]\n\n";
                            for($k = 0; $k <= 4; $k++) {
                                $weatherStatus = $json['records']['location'][0]['weatherElement'][$k]['time'][$i];
                                $switchLine = ($k != 0) ? "\n\n" : "";
                                $msg[$i] = $msg[$i] . $switchLine . $description[$k] . '：' . $weatherStatus['parameter']['parameterName'];
                            }
                            
                            if($i == 2) {
                                $num = 0;
                                $found = 0;
                                while($found != 1) {
                                    if($aqiJson['records'][$num]['County'] != $city) {
                                        $num++;
                                    }
                                    else {
                                        $msg[$i] = $msg[$i] . "\n\n空氣品質：" . $aqiJson['records'][$num]['AQI'] . ' (' . $aqiJson['records'][$num]['SiteName'] . ')';
                                        $found = 1;
                                    }
                                }
                            }
                        }

                        $bubbleFst = new BubbleContainerBuilder(
                            null,
                            null,
                            null,
                            new BoxComponentBuilder('vertical', [new TextComponentBuilder($msg[0], null, null, null, null, null, true)]),
                            null,
                            null,
                            null
                        );

                        $bubbleSnd = new BubbleContainerBuilder(
                            null,
                            null,
                            null,
                            new BoxComponentBuilder('vertical', [new TextComponentBuilder($msg[1], null, null, null, null, null, true)]),
                            null,
                            null,
                            null
                        );

                        $bubbleTrd = new BubbleContainerBuilder(
                            null,
                            null,
                            null,
                            new BoxComponentBuilder('vertical', [new TextComponentBuilder($msg[2], null, null, null, null, null, true)]),
                            null,
                            null,
                            null
                        );

                        $carouselContainer = new CarouselContainerBuilder([$bubbleFst, $bubbleSnd, $bubbleTrd]);

                        $replyMsg = new FlexMessageBuilder($city . '天氣簡述', $carouselContainer);
                    }
                    else {
                        $replyMsg = new TextMessageBuilder('請輸入正確的縣市名稱。');
                    }
                }
                $response = $bot->replyMessage($Events['replyToken'], $replyMsg);
            }
        }
    }
}
