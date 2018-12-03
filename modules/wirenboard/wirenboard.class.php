<?php
/**
 * Wirenboard
 *
 * Mqtt
 *
 * @package project
 * @author Serge J. <jey@tut.by>
 * @copyright http://www.atmatic.eu/ (c)
 * @version 0.1 (wizard, 13:07:08 [Jul 19, 2013])
 */
//
//
class wirenboard extends module
{
    /**
     * mqtt
     *
     * Module class constructor
     *
     * @access private
     */
    function wirenboard()
    {
        $this->name = "wirenboard";
        $this->title = "Wirenboard";
        $this->module_category = "<#LANG_SECTION_DEVICES#>";
        $this->checkInstalled();
    }

    /**
     * saveParams
     *
     * Saving module parameters
     *
     * @access public
     */
    function saveParams($data = 0)
    {
        $p = array();
        if (IsSet($this->id)) {
            $p["id"] = $this->id;
        }
        if (IsSet($this->view_mode)) {
            $p["view_mode"] = $this->view_mode;
        }
        if (IsSet($this->edit_mode)) {
            $p["edit_mode"] = $this->edit_mode;
        }
        if (IsSet($this->tab)) {
            $p["tab"] = $this->tab;
        }
        if (IsSet($this->data_source)) {
            $p["data_source"] = $this->data_source;
        }
        return parent::saveParams($p);
    }

    /**
     * getParams
     *
     * Getting module parameters from query string
     *
     * @access public
     */
    function getParams()
    {
        global $id;
        global $mode;
        global $view_mode;
        global $edit_mode;
        global $tab;
        global $data_source;
        if (isset($id)) {
            $this->id = $id;
        }
        if (isset($mode)) {
            $this->mode = $mode;
        }
        if (isset($view_mode)) {
            $this->view_mode = $view_mode;
        }
        if (isset($edit_mode)) {
            $this->edit_mode = $edit_mode;
        }
        if (isset($tab)) {
            $this->tab = $tab;
        }
        if (isset($data_source)) {
            $this->data_source = $data_source;
        }
    }

    /**
     * Run
     *
     * Description
     *
     * @access public
     */
    function run()
    {
        global $session;
        $out = array();
        if ($this->action == 'admin') {
            $this->admin($out);
        } else {
            $this->usual($out);
        }
        if (IsSet($this->owner->action)) {
            $out['PARENT_ACTION'] = $this->owner->action;
        }
        if (IsSet($this->owner->name)) {
            $out['PARENT_NAME'] = $this->owner->name;
        }
        $out['VIEW_MODE'] = $this->view_mode;
        $out['EDIT_MODE'] = $this->edit_mode;
        $out['MODE'] = $this->mode;
        $out['ACTION'] = $this->action;
        $out['TAB'] = $this->tab;
        if (IsSet($this->location_id)) {
            $out['IS_SET_LOCATION_ID'] = 1;
        }
        if ($this->single_rec) {
            $out['SINGLE_REC'] = 1;
        }
        $this->data = $out;
        $p = new parser(DIR_TEMPLATES . $this->name . "/" . $this->name . ".html", $this->data, $this);
        $this->result = $p->result;
    }

    function pathToTree($array)
    {
        $tree = array();
        foreach ($array AS $item) {
            $pathIds = explode("/", ltrim($item["PATH"], "/") . '/' . $item["ID"]);
            $current = &$tree;
            $cp = '';
            foreach ($pathIds AS $id) {
                if (!isset($current["CHILDS"][$id])) {
                    $current["CHILDS"][$id] = array('CP' => $cp);
                }
                $current = &$current["CHILDS"][$id];
                if ($id == $item["ID"]) {
                    $current = $item;
                }
            }
        }
        return ($this->childsToArray($tree['CHILDS']));
    }

    function childsToArray($items, $prev_path = '')
    {
        $res = array();
        foreach ($items as $k => $v) {
            if (!$v['PATH']) {
                $v['TITLE'] = $k . ' ' . $v['CP'];
                $pp = $k;
            } else {
                $v['TITLE'] = '';
                $pp = '';
            }
            if (isset($v['CHILDS'])) {
                $items = $this->childsToArray($v['CHILDS'], $prev_path != '' ? $prev_path . '/' . $pp : $pp);
                if (count($items) == 1) {
                    $v = $items[0];
                    $v['TITLE'] = $pp . ($v['TITLE'] != '' ? '/' . $v['TITLE'] : '');
                } else {
                    $v['RESULT'] = $items;
                }
                unset($v['CHILDS']);
            }
            $res[] = $v;
        }
        return $res;
    }

    /**
     * Title
     *
     * Description
     *
     * @access public
     */
    function setProperty($id, $value, $set_linked = 0)
    {
        $rec = SQLSelectOne("SELECT * FROM wirenboard WHERE ID='" . $id . "'");

        if (!$rec['ID'] || !$rec['PATH']) {
            return 0;
        }


        if ($rec['REPLACE_LIST'] != '') {
            $list = explode(',', $rec['REPLACE_LIST']);
            foreach ($list as $pair) {
                $pair = trim($pair);
                list($new, $old) = explode('=', $pair);
                if ($value == $old) {
                    $value = $new;
                    break;
                }
            }
        }
        //if ($new_connection) {

        $device_rec=SQLSelectOne("SELECT * FROM wirenboard_devices WHERE ID=".$rec['DEVICE_ID']);
        if (!$device_rec['ID']) {
            return 0;
        }
        include_once("./lib/mqtt/phpMQTT.php");

        $this->getConfig();
        $client_name = "MajorDoMo MQTT Client";

            $username = $device_rec['MQTT_USERNAME'];
            $password = $device_rec['MQTT_PASSWORD'];

        if ($device_rec['MQTT_HOST']) {
            $host = $device_rec['MQTT_HOST'];
        } else {
            $host = 'localhost';
        }
        if ($device_rec['MQTT_PORT']) {
            $port = $device_rec['MQTT_PORT'];
        } else {
            $port = 1883;
        }

        $mqtt_client = new phpMQTT($host, $port, $client_name . ' Write');

        if (!$mqtt_client->connect(true, NULL, $username, $password)) {
            DebMes("Failed to connect to $host:$port ($username/$password);",'wirenboard');
            return 0;
        }

        if ($rec['DATA_TYPE']=='switch') {
            $rec['PATH_WRITE']=$rec['PATH'].'/on';
        }

        if ($rec['PATH_WRITE']) {
            if (preg_match('/^http:/', $rec['PATH_WRITE'])) {
                $url = $rec['PATH_WRITE'];
                $url = str_replace('%VALUE%', $value, $url);
                getURL($url, 0);
            } else {
                //DebMes("Publishing to ".$rec['PATH_WRITE'].":".$value,'wirenboard');
                $mqtt_client->publish($rec['PATH_WRITE'], $value, (int)$rec['QOS'], (int)$rec['RETAIN']);
            }
        } else {
            $mqtt_client->publish($rec['PATH'], $value, (int)$rec['QOS'], (int)$rec['RETAIN']);
        }
        $mqtt_client->close();

        /*
        } else {

         $this->prepareQueueTable();
         $data=array();
         $data['PATH']=$rec['PATH'];
         $data['VALUE']=$value;
         SQLInsert('mqtt_queue', $data);

        }
        */

        $rec['VALUE'] = $value . '';
        $rec['UPDATED'] = date('Y-m-d H:i:s');
        SQLUpdate('wirenboard', $rec);

        if ($set_linked && $rec['LINKED_OBJECT'] && $rec['LINKED_PROPERTY']) {
            setGlobal($rec['LINKED_OBJECT'] . '.' . $rec['LINKED_PROPERTY'], $value, array($this->name=>'0'));
        }

    }

    /**
     * Title
     *
     * Description
     *
     * @access public
     */
    function processMessage($device_id, $path, $value)
    {
        if (preg_match('/\#$/', $path)) {
            return 0;
        }
        if (preg_match('/(.+?)\/meta\/(\w+)$/',$path,$m)) {
            //todo: set meta information for topic
            $path=$m[1];
            $meta_name=$m[2];
            $meta_value=$value;
        }
        if (preg_match('/\/rpc\/v\d+\//',$path)) {
            return 0;
        }

        if (preg_match('/^{/', $value)) {
            $ar = json_decode($value, true);
            foreach ($ar as $k => $v) {
                if (is_array($v))
                    $v = json_encode($v);
                $this->processMessage($device_id, $path . '/' . $k, $v);
            }
        }

        /* Search 'PATH' in database (db) */
        $rec = SQLSelectOne("SELECT * FROM wirenboard WHERE PATH LIKE '" . DBSafe($path) . "' AND DEVICE_ID=".$device_id);

        if (!isset($meta_name)) {
            $rec['VALUE'] = $value . '';
            $rec['UPDATED'] = date('Y-m-d H:i:s');
        } else {
            if ($rec['META_DATA']!='') {
                $meta_data=json_decode($rec['META_DATA'],true);
            } else {
                $meta_data=array();
            }
            $meta_data[$meta_name]=$meta_value;
            if ($meta_name=='type') {
                $rec['DATA_TYPE']=$meta_value;
            }
            $rec['META_DATA']=json_encode($meta_data);
        }
        if (!$rec['ID']) { /* If 'PATH' not found in db */
            /* Insert new record in db */
            $rec['PATH'] = $path;
            $rec['TITLE'] = $path;
            $rec['DEVICE_ID']=$device_id;
            $rec['ID'] = null;
            SQLInsert('wirenboard', $rec);
        } else {
            /* Update values in db */
            SQLUpdate('wirenboard', $rec);
            /* Update property in linked object if it exist */
            if ($rec['LINKED_OBJECT'] && $rec['LINKED_PROPERTY']) {

                $value = $rec['VALUE'];
                if ($rec['REPLACE_LIST'] != '') {
                    $list = explode(',', $rec['REPLACE_LIST']);
                    foreach ($list as $pair) {
                        $pair = trim($pair);
                        list($new, $old) = explode('=', $pair);
                        if ($value == $new) {
                            $value = $old;
                            break;
                        }
                    }
                }
                setGlobal($rec['LINKED_OBJECT'] . '.' . $rec['LINKED_PROPERTY'], $value, array($this->name => '0'));
            }
        }
    }

    /**
     * BackEnd
     *
     * Module backend
     *
     * @access public
     */
    function admin(&$out)
    {
        if (isset($this->data_source) && !$_GET['data_source'] && !$_POST['data_source']) {
            $out['SET_DATASOURCE'] = 1;
        }

        $out['DATA_SOURCE']=$this->data_source;

        if ($this->data_source == 'wirenboard' || $this->data_source == '') {
            if ($this->view_mode == '' || $this->view_mode == 'search_wirenboard') {
                $this->search_wirenboard($out);
            }
            if ($this->view_mode == 'edit_wirenboard') {
                $this->edit_wirenboard($out, $this->id);
            }
            if ($this->view_mode == 'delete_wirenboard') {
                $this->delete_wirenboard($this->id);
                $this->redirect("?");
            }
            if ($this->view_mode == 'clear_trash') {
                $this->clear_trash();
                $this->redirect("?");
            }
        }

        if ($this->data_source == 'wirenboard_devices') {
            if ($this->view_mode == '' || $this->view_mode == 'search_wirenboard_devices') {
                $this->search_wirenboard_devices($out);
            }
            if ($this->view_mode == 'edit_wirenboard_devices') {
                $this->edit_wirenboard_devices($out, $this->id);
            }
            if ($this->view_mode == 'delete_wirenboard_devices') {
                $this->delete_wirenboard_devices($this->id);
                $this->redirect("?data_source=".$this->data_source);
            }
        }

    }

    function clear_trash()
    {
        $res = SQLSelect("SELECT ID FROM wirenboard WHERE LINKED_OBJECT='' AND LINKED_PROPERTY=''");
        $total = count($res);
        for ($i = 0; $i < $total; $i++) {
            $this->delete_wirenboard($res[$i]['ID']);
        }
    }

    /**
     * FrontEnd
     *
     * Module frontend
     *
     * @access public
     */
    function usual(&$out)
    {
        if ($this->ajax) {
            global $op;
            $result = array();
            if ($op == 'getvalues') {
                global $ids;
                if (!is_array($ids)) {
                    $ids = array(0);
                } else {
                    $ids[] = 0;
                }
                $data = SQLSelect("SELECT ID,VALUE FROM wirenboard WHERE ID IN (" . implode(',', $ids) . ")");
                $total = count($data);
                for ($i = 0; $i < $total; $i++) {
                    $data[$i]['VALUE'] = str_replace('":', '": ', $data[$i]['VALUE']);
                }
                $result['DATA'] = $data;
            }
            echo json_encode($result);
            exit;
        }
        $this->admin($out);
    }

    /**
     * mqtt search
     *
     * @access public
     */
    function search_wirenboard(&$out)
    {
        require(DIR_MODULES . $this->name . '/wirenboard_search.inc.php');
    }

    function search_wirenboard_devices(&$out)
    {
        require(DIR_MODULES . $this->name . '/wirenboard_devices_search.inc.php');
    }

    /**
     * mqtt edit/add
     *
     * @access public
     */
    function edit_wirenboard(&$out, $id)
    {
        require(DIR_MODULES . $this->name . '/wirenboard_edit.inc.php');
    }

    function edit_wirenboard_devices(&$out, $id)
    {
        require(DIR_MODULES . $this->name . '/wirenboard_devices_edit.inc.php');
    }

    function propertySetHandle($object, $property, $value)
    {
        $mqtt_properties = SQLSelect("SELECT ID FROM wirenboard WHERE LINKED_OBJECT LIKE '" . DBSafe($object) . "' AND LINKED_PROPERTY LIKE '" . DBSafe($property) . "'");
        $total = count($mqtt_properties);
        if ($total) {
            for ($i = 0; $i < $total; $i++) {
                $this->setProperty($mqtt_properties[$i]['ID'], $value);
            }
        }
    }


    /**
     * mqtt delete record
     *
     * @access public
     */
    function delete_wirenboard($id)
    {
        $rec = SQLSelectOne("SELECT * FROM wirenboard WHERE ID='$id'");
        // some action for related tables
        SQLExec("DELETE FROM wirenboard WHERE ID='" . $rec['ID'] . "'");
    }

    function delete_wirenboard_devices($id)
    {
        $rec = SQLSelectOne("SELECT * FROM wirenboard_devices WHERE ID='$id'");
        // some action for related tables
        SQLExec("DELETE FROM wirenboard WHERE DEVICE_ID='" . $rec['ID'] . "'");
        SQLExec("DELETE FROM wirenboard_devices WHERE ID='" . $rec['ID'] . "'");
        $this->restartCycle();
    }


    function restartCycle() {
        setGlobal('cycle_wirenboardControl', 'restart');
    }

    /**
     * Install
     *
     * Module installation routine
     *
     * @access private
     */
    function install($data = '')
    {
        parent::install();
    }

    /**
     * Uninstall
     *
     * Module uninstall routine
     *
     * @access public
     */
    function uninstall()
    {
        SQLExec('DROP TABLE IF EXISTS wirenboard');
        parent::uninstall();
    }

    /**
     * dbInstall
     *
     * Database installation routine
     *
     * @access private
     */
    function dbInstall($data)
    {
        /*
        wirenboard
        */
        $data = <<<EOD
 wirenboard: ID int(10) unsigned NOT NULL auto_increment
 wirenboard: TITLE varchar(255) NOT NULL DEFAULT ''
 wirenboard: DEVICE_ID int(10) NOT NULL DEFAULT '0'
 wirenboard: LOCATION_ID int(10) NOT NULL DEFAULT '0'
 wirenboard: UPDATED datetime
 wirenboard: VALUE varchar(255) NOT NULL DEFAULT ''
 wirenboard: PATH varchar(255) NOT NULL DEFAULT ''
 wirenboard: PATH_WRITE varchar(255) NOT NULL DEFAULT ''
 wirenboard: REPLACE_LIST varchar(255) NOT NULL DEFAULT ''
 wirenboard: LINKED_OBJECT varchar(255) NOT NULL DEFAULT ''
 wirenboard: LINKED_PROPERTY varchar(255) NOT NULL DEFAULT ''
 wirenboard: LINKED_METHOD varchar(255) NOT NULL DEFAULT ''
 wirenboard: META_DATA varchar(255) NOT NULL DEFAULT ''
 wirenboard: DATA_TYPE varchar(30) NOT NULL DEFAULT ''
 wirenboard: QOS int(3) NOT NULL DEFAULT '0'
 wirenboard: RETAIN int(3) NOT NULL DEFAULT '0'
 wirenboard: DISP_FLAG int(3) NOT NULL DEFAULT '0'

 wirenboard_devices: ID int(10) unsigned NOT NULL auto_increment
 wirenboard_devices: TITLE varchar(255) NOT NULL DEFAULT ''
 wirenboard_devices: MQTT_HOST varchar(255) NOT NULL DEFAULT 'localhost'
 wirenboard_devices: MQTT_USERNAME varchar(255) NOT NULL DEFAULT ''
 wirenboard_devices: MQTT_PASSWORD varchar(255) NOT NULL DEFAULT ''
 wirenboard_devices: MQTT_PORT int(10) NOT NULL DEFAULT 1883
 wirenboard_devices: MQTT_QUERY varchar(255) NOT NULL DEFAULT '/#'
 
 
EOD;
        parent::dbInstall($data);
    }
// --------------------------------------------------------------------
}

/*
*
* TW9kdWxlIGNyZWF0ZWQgSnVsIDE5LCAyMDEzIHVzaW5nIFNlcmdlIEouIHdpemFyZCAoQWN0aXZlVW5pdCBJbmMgd3d3LmFjdGl2ZXVuaXQuY29tKQ==
*
*/
?>
