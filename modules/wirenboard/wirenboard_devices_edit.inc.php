<?php
/*
* @version 0.1 (wizard)
*/
  if ($this->owner->name=='panel') {
   $out['CONTROLPANEL']=1;
  }
  $table_name='wirenboard_devices';
  $rec=SQLSelectOne("SELECT * FROM $table_name WHERE ID='$id'");
  if ($this->mode=='update') {
   $ok=1;
  //updating '<%LANG_TITLE%>' (varchar, required)
   $rec['TITLE']=gr('title');
   if ($rec['TITLE']=='') {
    $out['ERR_TITLE']=1;
    $ok=0;
   }
  //updating 'MQTT_HOST' (varchar)
   $rec['MQTT_HOST']=gr('mqtt_host');
  //updating 'MQTT_USERNAME' (varchar)
   $rec['MQTT_USERNAME']=gr('mqtt_username');
  //updating 'MQTT_PASSWORD' (varchar)
   $rec['MQTT_PASSWORD']=gr('mqtt_password');
  //updating 'MQTT_PORT' (varchar)
   $rec['MQTT_PORT']=gr('mqtt_port');
  //updating 'MQTT_QUERY' (varchar)
   $rec['MQTT_QUERY']=gr('mqtt_query');
  //UPDATING RECORD
   if ($ok) {
    if ($rec['ID']) {
     SQLUpdate($table_name, $rec); // update
    } else {
     $new_rec=1;
     $rec['ID']=SQLInsert($table_name, $rec); // adding new record
    }
    $out['OK']=1;
       $this->restartCycle();
       $this->redirect("?data_source=".$this->data_source);
   } else {
    $out['ERR']=1;
   }
  }
  if (is_array($rec)) {
   foreach($rec as $k=>$v) {
    if (!is_array($v)) {
     $rec[$k]=htmlspecialchars($v);
    }
   }
  }
  outHash($rec, $out);
