<?php
chdir(dirname(__FILE__) . '/../');

include_once("./config.php");
include_once("./lib/loader.php");
include_once("./lib/threads.php");

// connecting to database
$db = new mysql(DB_HOST, '', DB_USER, DB_PASSWORD, DB_NAME);

include_once("./load_settings.php");
include_once(DIR_MODULES . "control_modules/control_modules.class.php");

set_time_limit(0);

include_once(ROOT . "3rdparty/phpmqtt/phpMQTT.php");
include_once(DIR_MODULES . "wirenboard/wirenboard.class.php");

$wb_module = new wirenboard();

$mqtts=array();
$devices=SQLSelect("SELECT * FROM wirenboard_devices");
$total = count($devices);
for ($i = 0; $i < $total; $i++) {
    $client_name="MajorDoMo MQTT Cycle";
    if ($devices[$i]['MQTT_USERNAME'] && $devices[$i]['MQTT_PASSWORD']) {
        $username=$devices[$i]['MQTT_USERNAME'];
        $password=$devices[$i]['MQTT_PASSWORD'];
    } else {
        $username='';
        $password='';
    }
    if ($devices[$i]['MQTT_HOST']) {
        $host=$devices[$i]['MQTT_HOST'];
    } else {
        $host='localhost';
    }
    if ($devices[$i]['MQTT_PORT']) {
        $port=(int)$devices[$i]['MQTT_PORT'];
    } else {
        $port=1883;
    }

    if ($devices[$i]['MQTT_QUERY']) {
        $query = $devices[$i]['MQTT_QUERY'];
    } else {
        $query = '/#';
    }

    $mqtt_client = new Bluerhinos\phpMQTT($host, $port, $client_name);
    if ($username)
    {
        if (!$mqtt_client->connect(true, NULL, $username, $password)) {
            continue;
        }
    } else {
        if (!$mqtt_client->connect())
        {
            continue;
        }
    }

    eval("function procmsg".$devices[$i]['ID']."(\$topic, \$msg) {global \$wb_module;\$wb_module->processMessage(".$devices[$i]['ID'].",\$topic, \$msg);}");

    $query_list=explode(',', $query);
    $totalq=count($query_list);
    echo date('H:i:s')." Topics to watch: $query (Total: $total)\n";
    for($iq=0;$iq<$totalq;$iq++) {
        $path=trim($query_list[$iq]);
        echo date('H:i:s')." Path: $path\n";
        $topics[$path] = array("qos" => 0, "function" => "procmsg".$devices[$i]['ID']);
    }
    foreach($topics as $k=>$v) {
        echo date('H:i:s')." Subscribing to: $k  \n";
        $rec=array($k=>$v);
        $mqtt_client->subscribe($rec, 0);
    }
    $mqtts[$devices[$i]['ID']]=$mqtt_client;
}

$latest_set=0;

if (count($mqtts)==0) {
    echo "No devices connected";exit;
}

while(1) {
    foreach($mqtts as $device_id=>&$mqtt_client) {
        $mqtt_client->proc();
    }
    if ((time()-$latest_set)>=3) {
        setGlobal((str_replace('.php', '', basename(__FILE__))) . 'Run', time(), 1);
        $latest_set=time();
    }
    if (file_exists('./reboot') || IsSet($_GET['onetime']))
    {
        foreach($mqtts as $device_id=>&$mqtt_client) {
            $mqtt_client->close();
        }
        $db->Disconnect();
        exit;
    }
    usleep(50000);
}

 $db->Disconnect(); // closing database connection
