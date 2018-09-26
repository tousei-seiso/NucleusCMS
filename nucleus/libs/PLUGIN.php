<?php
/*
 * Nucleus: PHP/MySQL Weblog CMS (http://nucleuscms.org/)
 * Copyright (C) The Nucleus Group
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 * (see nucleus/documentation/index.html#license for more info)
 *
 * This is an (abstract) class of which all Nucleus Plugins must inherit
 *
 * for more information on plugins and how to write your own, see the
 * plugins.html file that is included with the Nucleus documenation
 *
 * @license http://nucleuscms.org/license.txt GNU General Public License
 * @copyright Copyright (C) The Nucleus Group
 */
class NucleusPlugin {
    public $is_db_sqlite = false;
    public $is_db_mysql  = false;

    // these functions _have_ to be redefined in your plugin

    function getName()        { return get_class($this); }
    function getAuthor()      { return 'Undefined'; }
    function getURL()         { return 'Undefined'; }
    function getVersion()     { return '0.0'; }
    function getDescription() { return 'Undefined'; }

    // these function _may_ be redefined in your plugin

    function getMinNucleusVersion()    { return 350; }
    function getMinNucleusPatchLevel() { return 0; }
    function getEventList()            { return $this->_getEventList(); }
    function getTableList()            { return array(); }
    function hasAdminArea()            { return 0; }

    function install() {}
    function unInstall() {}

    function init() {}

    function doSkinVar($skinType) {}
    function doTemplateVar(&$item) {
        $args = func_get_args();
        array_shift($args);
        array_unshift($args, 'template');
        call_user_func_array(array($this,'doSkinVar'), $args);
    }
    function doTemplateCommentsVar(&$item, &$comment) {
        $args = func_get_args();
        array_shift($args);
        array_shift($args);
        array_unshift($args, 'template');
        call_user_func_array(array($this,'doSkinVar'), $args);
    }
    function doAction($type) { return _ERROR_PLUGIN_NOSUCHACTION; }
    function doIf($key,$value) { return false; }
    function doItemVar (&$item) {}

    /**
     * Checks if a plugin supports a certain feature.
     *
     * @returns 1 if the feature is reported, 0 if not
     * @param $feature
     *        Name of the feature. See plugin documentation for more info
     *            'HelpPage' -> if the plugin provides a helppage
     *            'SqlApi'   -> if the plugin uses the complete sql_* api (must also require nucleuscms 3.5)
     */
    function supportsFeature($feature) {
        return 0;
    }

    /**
     * Report a list of plugin that is required to function
     *
     * @returns an array of names of plugin, an empty array indicates no dependency
     */
    function getPluginDep() { return array(); }

    // these helper functions should not be redefined in your plugin

    /**
      * Creates a new option for this plugin
      *
      * @param name
      *        A string uniquely identifying your option. (max. length is 20 characters)
      * @param description
      *        A description that will show up in the nucleus admin area (max. length: 255 characters)
      * @param type
      *        Either 'text', 'yesno' or 'password'
      *        This info is used when showing 'edit plugin options' screens
      * @param value
      *        Initial value for the option (max. value length is 128 characters)
      */
    function createOption($name, $desc, $type, $defValue = '', $typeExtras = '') {
        return $this->_createOption('global', $name, $desc, $type, $defValue, $typeExtras);
    }
    function createBlogOption($name, $desc, $type, $defValue = '', $typeExtras = '') {
        return $this->_createOption('blog', $name, $desc, $type, $defValue, $typeExtras);
    }
    function createMemberOption($name, $desc, $type, $defValue = '', $typeExtras = '') {
        return $this->_createOption('member', $name, $desc, $type, $defValue, $typeExtras);
    }
    function createCategoryOption($name, $desc, $type, $defValue = '', $typeExtras = '') {
        return $this->_createOption('category', $name, $desc, $type, $defValue, $typeExtras);
    }
    function createItemOption($name, $desc, $type, $defValue = '', $typeExtras = '') {
        return $this->_createOption('item', $name, $desc, $type, $defValue, $typeExtras);
    }

    /**
      * Removes the option from the database
      *
      * Note: Options get erased automatically on plugin uninstall
      */
    function deleteOption($name) {
        return $this->_deleteOption('global', $name);
    }
    function deleteBlogOption($name) {
        return $this->_deleteOption('blog', $name);
    }
    function deleteMemberOption($name) {
        return $this->_deleteOption('member', $name);
    }
    function deleteCategoryOption($name) {
        return $this->_deleteOption('category', $name);
    }
    function deleteItemOption($name) {
        return $this->_deleteOption('item', $name);
    }

    /**
      * Sets the value of an option to something new
      */
    function setOption($name, $value) {
        return $this->_setOption('global', 0, $name, $value);
    }
    function setBlogOption($blogid, $name, $value) {
        return $this->_setOption('blog', $blogid, $name, $value);
    }
    function setMemberOption($memberid, $name, $value) {
        return $this->_setOption('member', $memberid, $name, $value);
    }
    function setCategoryOption($catid, $name, $value) {
        return $this->_setOption('category', $catid, $name, $value);
    }
    function setItemOption($itemid, $name, $value) {
        return $this->_setOption('item', $itemid, $name, $value);
    }

    /**
      * Retrieves the current value for an option
      */
    function getOption($name)
    {
        static $rs = array();
        
        if(isset($rs[$name])) {
            return $rs[$name];
        }
        
        // only request the options the very first time. On subsequent requests
        // the static collection is used to save SQL queries.
        if ($this->plugin_options == 0)
        {
            $this->plugin_options = array();
            $ph['opid'] = intval($this->getID());
            $sql = 'SELECT d.oname as name, CASE WHEN o.ovalue is null THEN d.odef ELSE o.ovalue END as value '
                    . ' FROM [@prefix@]plugin_option_desc d '
                    . ' LEFT JOIN [@prefix@]plugin_option o ON d.oid=o.oid AND o.ocontextid=0 '
                    . " WHERE d.opid=[@opid@] AND d.ocontext='global' AND o.ocontextid=0"
                    . ' group by d.oid'
                    ;
            if ($res = sql_query(parseQuery($sql, $ph))) {
                while ( $row = sql_fetch_object($res) )
                {
                    $this->plugin_options[strtolower($row->name)] = $row->value;
                }
            }
        }
        if (isset($this->plugin_options[strtolower($name)]))
            $rs[$name] = $this->plugin_options[strtolower($name)];
        else
            $rs[$name] = $this->_getOption('global', 0, $name);
        
        return $rs[$name];
    }

    function getBlogOption($blogid, $name) {
        return $this->_getOption('blog', $blogid, $name);
    }
    function getMemberOption($memberid, $name) {
        return $this->_getOption('member', $memberid, $name);
    }
    function getCategoryOption($catid, $name) {
        return $this->_getOption('category', $catid, $name);
    }
    function getItemOption($itemid, $name) {
        return $this->_getOption('item', $itemid, $name);
    }

    /**
     * Retrieves an associative array with the option value for each
     * context id
     */
    function getAllBlogOptions($name) {
        return $this->_getAllOptions('blog', $name);
    }
    function getAllMemberOptions($name) {
        return $this->_getAllOptions('member', $name);
    }
    function getAllCategoryOptions($name) {
        return $this->_getAllOptions('category', $name);
    }
    function getAllItemOptions($name) {
        return $this->_getAllOptions('item', $name);
    }

    /**
     * Retrieves an indexed array with the top (or bottom) of an option
     * (delegates to _getOptionTop())
     */
    function getBlogOptionTop($name, $amount = 10, $sort = 'desc') {
        return $this->_getOptionTop('blog', $name, $amount, $sort);
    }
    function getMemberOptionTop($name, $amount = 10, $sort = 'desc') {
        return $this->_getOptionTop('member', $name, $amount, $sort);
    }
    function getCategoryOptionTop($name, $amount = 10, $sort = 'desc') {
        return $this->_getOptionTop('category', $name, $amount, $sort);
    }
    function getItemOptionTop($name, $amount = 10, $sort = 'desc') {
        return $this->_getOptionTop('item', $name, $amount, $sort);
    }

    /**
      * Returns the plugin ID
      *
      * public
      */
    function getID() {
        return $this->plugid;
    }

    /**
      * Returns the URL of the admin area for this plugin (in case there's
      * no such area, the returned information is invalid)
      *
      * public
      */
    function getAdminURL()
    {
        global $CONF, $manager, $DIR_PLUGINS;

        if ($this->supportsFeature('pluginadmin')) {
            return $manager->addTicketToUrl('index.php?plugid=' . $this->getID() . '&action=pluginadmin');
        }
        $dirPrefix = isset($this->plugin_admin_url_prefix) ? $this->plugin_admin_url_prefix : '';
        $path = $DIR_PLUGINS . $dirPrefix.get_class($this);
        if (is_dir($path))
        {
            return $CONF['PluginURL'] . $dirPrefix.get_class($this) . '/';
        }
        else {
            return $CONF['PluginURL'] . $dirPrefix.$this->getShortName() . '/';
        }
    }

    function getAdminDir() { return $this->getDirectory(); }

    function setDirectory($path='') {
        global $DIR_PLUGINS;
        
        if ( ! $path ) {
            $path = $DIR_PLUGINS . get_class($this);
        }
        
        if(is_dir($path)) $path = rtrim($path,'/').'/';
        else              $path = $this->getDirectory();
        
        $this->plugin_admin_dir = $path;
        return $path;
    }
    
    /**
      * Returns the directory where the admin directory is located and
      * where the plugin can maintain his extra files
      *
      * public
      */
    function getDirectory() {
        global $DIR_PLUGINS;
        if ( isset($this->plugin_admin_dir) ) {
            return $this->plugin_admin_dir;
        }
        
        $path = $CONF['PluginURL'] . get_class($this);
        if (is_dir($path))
        {
            return $path . '/';
        }
        
        $path = $path . '/' . $this->getShortName();
        if(is_dir($path))
        {
            return $path . '/';
        }
        
        return $DIR_PLUGINS . $this->getShortName() . '/';
    }

    /**
      * Derives the short name for the plugin from the classname (all
      * lowercase)
      *
      * public
      */
    function getShortName() {
        return str_replace('np_','',strtolower(get_class($this)));
    }

    /**
     *    Clears the option value cache which saves the option values during
     *    the plugin execution. This function is usefull if the options has
     *    changed during the plugin execution (especially in association with
     *    the PrePluginOptionsUpdate and the PostPluginOptionsUpdate events)
     *
     *  public
     **/
    function clearOptionValueCache(){
        $this->_aOptionValues = array();
        $this->plugin_options = 0;
    }

    // internal functions of the class starts here
    // constructor doesn't seem to work in 3.65 or doesn't get called before something
    // uses the _getOID() method. Set init values here as quick workaround.

    public $_aOptionValues = array();    // oid_contextid => value
    public $_aOptionToInfo = array();    // context_name => array('oid' => ..., 'default' => ...)
    public $plugin_options = 0;    // see getOption()
    public $plugid;            // plugin id


    /**
     * Class constructor: Initializes some internal data
     */
    function __construct() {
        $this->_aOptionValues = array();    // oid_contextid => value
        $this->_aOptionToInfo = array();    // context_name => array('oid' => ..., 'default' => ...)
        $this->plugin_options = 0;

        $this->init_driver_flag();
    }

    function NucleusPlugin() {
        $this->__construct();
    }

    /**
     * Retrieves an array of the top (or bottom) of an option from a plugin.
     * @author TeRanEX
     * @param  string $context the context for the option: item, blog, member,...
     * @param  string $name    the name of the option
     * @param  int    $amount  how many rows must be returned
     * @param  string $sort    desc or asc
     * @return array           array with both values and contextid's
     * @access private
     */
    function _getOptionTop($context, $name, $amount = 10, $sort = 'desc') {
        static $rs = array();
        
        $rkey = md5(print_r(func_get_args(),true));
        if(isset($rs[$rkey])) {
            return $rs[$rkey];
        }
        
        if (($sort != 'desc') && ($sort != 'asc')) {
            $sort= 'desc';
        }

        $oid = $this->_getOID($context, $name);

        $ph = array('oid'=>$oid, 'amount'=>(int)$amount, 'sort'=>$sort);
        
        // retrieve the data and return
        $q = 'SELECT otype, oextra FROM '.sql_table('plugin_option_desc').' WHERE oid = '.$oid;
        $query = sql_query(parseQuery('SELECT otype, oextra FROM [@prefix@]plugin_option_desc WHERE oid=[@oid@]', $ph));

        $o = sql_fetch_array($query);

        if (($this->optionCanBeNumeric($o['otype'])) && ($o['oextra'] == 'number' )) {
            $ph['orderby'] = 'CAST(ovalue AS SIGNED)';
        } else {
            $ph['orderby'] = 'ovalue';
        }
        $sql = 'SELECT ovalue value, ocontextid id FROM [@prefix@]plugin_option WHERE oid=[@oid@] ORDER BY [@orderby@] [@sort@] LIMIT 0,[@amount@]';
        $query = sql_query(parseQuery($sql, $ph));

        // create the array
        $i = 0;
        $top = array();
        while($row = sql_fetch_array($query)) {
            $top[$i++] = $row;
        }

        // return the array (duh!)
        $rs[$rkey] = $top;
        return $top;
    }

    /**
     * Creates an option in the database table plugin_option_desc
     *
     * private
     */
    function _createOption($context, $name, $desc, $type, $defValue, $typeExtras = '') {
        if ($this->_existOptionDesc($context, $name))
            return FALSE;

        global $DB_PHP_MODULE_NAME, $DB_DRIVER_NAME, $CONF;
        $name_max_len = (intval($CONF['DatabaseVersion']) >= 380 ? 50: 20);
        if ((strlen($name)>$name_max_len) && ($DB_DRIVER_NAME!='sqlite')) {
            $msg = "Maximum number of characters exceeded : name : createOption[$context] $name ";
            SYSTEMLOG::addUnique('error', 'Error', $msg);
        }

        // create in plugin_option_desc
        if ($DB_PHP_MODULE_NAME == 'pdo')
        {
            $sql = 'INSERT INTO ' . sql_table('plugin_option_desc')
                   .' (opid, oname, ocontext, odesc, otype, odef, oextra)'
                   .' VALUES (' . sprintf("%d" , intval($this->plugid))
                             . ', ? , ? , ? '
                             . ', ? , ? , ? )';
            $params = array( $name , $context , $desc
                           , $type , $defValue , $typeExtras);
            sql_prepare_execute($sql, $params);
        }
        else
        {
            $query = 'INSERT INTO ' . sql_table('plugin_option_desc')
                   .' (opid, oname, ocontext, odesc, otype, odef, oextra)'
                   .' VALUES ('.intval($this->plugid)
                             .', \''.sql_real_escape_string($name).'\''
                             .', \''.sql_real_escape_string($context).'\''
                             .', \''.sql_real_escape_string($desc).'\''
                             .', \''.sql_real_escape_string($type).'\''
                             .', \''.sql_real_escape_string($defValue).'\''
                             .', \''.sql_real_escape_string($typeExtras).'\')';
            sql_query($query);
        }
        $oid = sql_insert_id();

        $key = $context . '_' . $name;
        $this->_aOptionToInfo[$key] = array('oid' => $oid, 'default' => $defValue);
        return 1;
    }


    /**
     * Deletes an option from the database tables
     * plugin_option and plugin_option_desc
     *
     * private
     */
    function _deleteOption($context, $name) {
        $oid = $this->_getOID($context, $name);
        if (!$oid) return 0; // no such option

        $ph['oid'] = $oid;
        // delete all things from plugin_option
        sql_query(parseQuery('DELETE FROM [@prefix@]plugin_option WHERE oid=[@oid@]', $ph));

        // delete entry from plugin_option_desc
        sql_query(parseQuery('DELETE FROM [@prefix@]plugin_option_desc WHERE oid=[@oid@]', $ph));

        // clear from cache
        unset($this->_aOptionToInfo[$context . '_' . $name]);
        $this->_aOptionValues = array();
        return 1;
    }

    /**
     * Update an option in the database table plugin_option
     *
     * returns: 1 on success, 0 on failure
     * private
     */
    function _setOption($context, $contextid, $name, $value) {
        global $manager;

        $oid = $this->_getOID($context, $name);
        if (!$oid) return 0;

        // check if context id exists
        switch ($context) {
            case 'member':
                if (!MEMBER::existsID($contextid)) return 0;
                break;
            case 'blog':
                if (!$manager->existsBlogID($contextid)) return 0;
                break;
            case 'category':
                if (!$manager->existsCategory($contextid)) return 0;
                break;
            case 'item':
                if (!$manager->existsItem($contextid, true, true)) return 0;
                break;
            case 'global':
                if ($contextid != 0) return 0;
                break;
        }


        // update plugin_option
        $ph = array();
        $ph['oid']        = intval($oid);
        $ph['ocontextid'] = intval($contextid);
        $ph['ovalue']     = sql_real_escape_string($value);
        sql_query(parseQuery("DELETE FROM [@prefix@]plugin_option WHERE oid=[@oid@] AND ocontextid=[@ocontextid@]", $ph));
        sql_query(parseQuery("INSERT INTO [@prefix@]plugin_option (ovalue, oid, ocontextid) VALUES ('[@ovalue@]', [@oid@], [@ocontextid@])", $ph));

        // update cache
        $this->_aOptionValues[$oid . '_' . $contextid] = $value;

        return 1;
    }

    /**
     * Get an option from Cache or database
     *      - if not in the option Cache read it from the database
     *   - if not in the database write default values into the database
     *
     * private
     */
    function _getOption($context, $contextid, $name) {
        
        static $rs = array();
        
        $rkey = md5(print_r(func_get_args(),true));
        
        if(isset($rs[$rkey])) {
            return $rs[$rkey];
        }
        
        $oid = $this->_getOID($context, $name);
        if (!$oid) return '';


        $key = $oid . '_' . $contextid;

        if (isset($this->_aOptionValues[$key]))
            return $this->_aOptionValues[$key];

        // get from DB
        $ph = array();
        $ph['oid']        = intval($oid);
        $ph['ocontextid'] = intval($contextid);
        $has_ovalue = sql_query(parseQuery('SELECT ovalue FROM [@prefix@]plugin_option WHERE oid=[@oid@] AND ocontextid=[@ocontextid@]', $ph));
        $has_row = intval(parseQuickQuery('SELECT count(*) FROM [@prefix@]plugin_option WHERE oid=[@oid@] AND ocontextid=[@ocontextid@] LIMIT 1', $ph));
        if (!$has_ovalue || ($has_row == 0)) {
            $this->_aOptionValues[$key] = $this->_getDefVal($context, $name);
            // fill DB with default value
            $ph['defVal'] = sql_real_escape_string($this->_aOptionValues[$key]);
            sql_query(parseQuery("INSERT INTO [@prefix@]plugin_option  (oid,ocontextid,ovalue) VALUES ([@oid@], [@ocontextid@], '[@defVal@]')", $ph));
        }
        else {
            $o = sql_fetch_object($has_ovalue);
            $this->_aOptionValues[$key] = $o->ovalue;
        }
        
        $rs[$rkey] = $this->_aOptionValues[$key];
        
        return $this->_aOptionValues[$key];
    }

    /**
     * Returns assoc array with all values for a given option
     * (one option per possible context id)
     *
     * private
     */
    function _getAllOptions($context, $name) {
        $oid = $this->_getOID($context, $name);
        if (!$oid) return array();
        $defVal = $this->_getDefVal($context, $name);

        $aOptions = array();
        switch ($context) {
            case 'blog':
                $r = sql_query(parseQuery('SELECT bnumber as contextid FROM [@prefix@]blog'));
                break;
            case 'category':
                $r = sql_query(parseQuery('SELECT catid as contextid FROM [@prefix@]category'));
                break;
            case 'member':
                $r = sql_query(parseQuery('SELECT mnumber as contextid FROM [@prefix@]member'));
                break;
            case 'item':
                $r = sql_query(parseQuery('SELECT inumber as contextid FROM [@prefix@]item'));
                break;
        }
        if ($r) {
            while ($o = sql_fetch_object($r))
                $aOptions[$o->contextid] = $defVal;
        }

        $ph = array('oid'=>(int)$oid);
        $res = sql_query(parseQuery('SELECT ocontextid, ovalue FROM [@prefix@]plugin_option WHERE oid=[@oid@]', $ph));
        while ($o = sql_fetch_object($res))
            $aOptions[$o->ocontextid] = $o->ovalue;

        return $aOptions;
    }

    final public function _getEventList() {
        static $res = NULL;
        if (!is_null($res))
            return $res;
        $res = array();
        $list = get_class_methods($this);
        if (!empty($list)) {
            foreach($list as $name)
                if (strncmp($name, "event_" , 6 )==0)
                    $res[] = substr($name, 6);
        }
        return $res;
    }

    /**
     * Gets the 'option identifier' that corresponds to a given option name.
     * When this method is called for the first time, all the OIDs for the plugin
     * are loaded into memory, to avoid re-doing the same query all over.
     */
    function _getOID($context, $name) {
        $key = $context . '_' . $name;
        if (array_key_exists($key, $this->_aOptionToInfo)) {
            $info = $this->_aOptionToInfo[$key];
            if (is_array($info)) return $info['oid'];
        }

        // load all OIDs for this plugin from the database
        $this->_aOptionToInfo = array();
        $ph = array('opid'=>intval($this->plugid));
        $res = sql_query(parseQuery('SELECT oid, oname, ocontext, odef FROM [@prefix@]plugin_option_desc WHERE opid=[@opid@]', $ph));
        if ($res) {
            while ($o = sql_fetch_object($res)) {
                $k = $o->ocontext . '_' . $o->oname;
                $this->_aOptionToInfo[$k] = array('oid' => $o->oid, 'default' => $o->odef);
            }
            sql_free_result($res);
        }

        if (array_key_exists($key, $this->_aOptionToInfo)) {
            return $this->_aOptionToInfo[$key]['oid'];
        } else {
            return null;
        }
    }

    function _getDefVal($context, $name) {
        $key = $context . '_' . $name;
        $info = $this->_aOptionToInfo[$key];
        if (is_array($info)) return $info['default'];
    }


    /**
     * Deletes all option values for a given context and contextid
     * (used when e.g. a blog, member or category is deleted)
     *
     * (static method)
     */
    public static function _deleteOptionValues($context, $contextid) {
        // delete all associated plugin options
        $aOIDs = array();
            // find ids
        $ph = array('ocontext'=>sql_real_escape_string($context));
        $res = sql_query(parseQuery("SELECT oid FROM [@prefix@]plugin_option_desc WHERE ocontext='[@ocontext@]'", $ph));
        while ($o = sql_fetch_object($res))
            $aOIDs[] = $o->oid;
        sql_free_result($res);
            // delete those options. go go go
        if (count($aOIDs) > 0) {
            $ph = array('aOIDs'=>join(',',$aOIDs), 'ocontextid'=>intval($contextid));
            sql_query(parseQuery('DELETE FROM [@prefix@]plugin_option WHERE oid in ([@aOIDs@]) AND ocontextid=[@ocontextid@]', $ph));
        }
    }

    /**
     * splits the option's typeextra field (at ;'s) to split the meta collection
     * @param string $typeExtra the value of the typeExtra field of an option
     * @return array array of the meta-key/value-pairs
     * @author TeRanEX
     * @static
     */
    public static function getOptionMeta($typeExtra) {
        $tmpMeta = explode(';', $typeExtra);
        $meta = array();
        for ($i = 0; $i < count($tmpMeta); $i++) {
            if (($i == 0) && (!strstr($tmpMeta[0], '='))) {
                // we have the select-list
                $meta['select'] = $tmpMeta[0];
            } else {
                $tmp = explode('=', $tmpMeta[$i]);
                $meta[$tmp[0]] = $tmp[1];
            }
        }
        return $meta;
    }

    /**
     * filters the selectlists out of the meta collection
     * @param string $typeExtra the value of the typeExtra field of an option
     * @return string the selectlist
     * @author TeRanEX
     */
    public static function getOptionSelectValues($typeExtra) {
        $meta = NucleusPlugin::getOptionMeta($typeExtra);
        //the select list must always be the first part
        return $meta['select'];
    }

    /**
     * checks if the eventlist in the database is up-to-date
     * @return bool if it is up-to-date it return true, else false
     * @author TeRanEX
     */
    function subscribtionListIsUptodate() {
    	$ph = array('pid'=>$this->getID());
        $res = sql_query(parseQuery('SELECT event FROM [@prefix@]plugin_event WHERE pid = [@pid@]',$ph));
        $ev = array();
        while($a = sql_fetch_array($res)) {
            $ev[] = $a['event'];
        }
        $pl_event_list = $this->_getEventList();
        if (count($ev) != count($pl_event_list)) {
            return false;
        }
        $d = array_diff($ev, $pl_event_list);
        if (count($d) > 0) {
            // there are differences so the db is not up-to-date
            return false;
        }
        return true;
    }

    /**
     * @param $aOptions: array ( 'oid' => array( 'contextid' => 'value'))
     *        (taken from request using requestVar())
     * @param $newContextid: integer (accepts a contextid when it is for a new
     *        contextid there was no id available at the moment of writing the
     *        formcontrols into the page (by ex: itemOptions for new item)
     * @static
     */
    public static function _applyPluginOptions(&$aOptions, $newContextid = 0) {
        global $manager;
        if (!is_array($aOptions)) return;

        foreach ($aOptions as $oid => $values) {

            // get option type info
            $ph = array('oid'=>intval($oid));
            $res = sql_query(parseQuery('SELECT opid, oname, ocontext, otype, oextra, odef FROM [@prefix@]plugin_option_desc WHERE oid=[@oid@]',$ph));
            if ($o = sql_fetch_object($res))
            {
                foreach ($values as $key => $value) {
                    // avoid overriding the key used by foreach statement
                    $contextid=$key;

                    // retreive any metadata
                    $meta = NucleusPlugin::getOptionMeta($o->oextra);

                    // if the option is readonly or hidden it may not be saved
                    if (!array_key_exists('access', $meta) || (($meta['access'] != 'readonly') && ($meta['access'] != 'hidden'))) {

                        switch($o->otype) {
                            case 'yesno':
                                if (($value != 'yes') && ($value != 'no')) $value = 'no';
                                break;
                            default:
                                break;
                        }

                        // check the validity of numerical options
                        if (array_key_exists('datatype', $meta) && ($meta['datatype'] == 'numerical') && (!is_numeric($value))) {
                            //the option must be numeric, but the it isn't
                            //use the default for this option
                            $value = $o->odef;
                        }

                        // decide wether we are using the contextid of newContextid
                        if ($newContextid != 0) {
                            $contextid = $newContextid;
                        }

                        //trigger event PrePluginOptionsUpdate to give the plugin the
                        //possibility to change/validate the new value for the option
                        $param = array(
                            'context'        => $o->ocontext,
                            'plugid'        => $o->opid,
                            'optionname'    => $o->oname,
                            'contextid'        => $contextid,
                            'value'            => &$value
                        );
                        $manager->notify('PrePluginOptionsUpdate', $param);

                        // delete the old value for the option
                        $ph['ocontextid'] = intval($contextid);
                        $ph['value']      = sql_real_escape_string($value);
                        sql_query(parseQuery('DELETE FROM [@prefix@]plugin_option WHERE oid=[@oid@] AND ocontextid=[@ocontextid@]', $ph));
                        sql_query(parseQuery("INSERT INTO [@prefix@]plugin_option (oid, ocontextid, ovalue) VALUES ([@oid@],[@ocontextid@],'[@value@]')", $ph));
                    }
                }
            }
            // clear option value cache if the plugin object is already loaded
            if (is_object($o)) {
                $plugin=& $manager->pidLoaded($o->opid);
                if ($plugin) $plugin->clearOptionValueCache();
            }
        }
    }

    private function init_driver_flag()
    {
        global $DB_DRIVER_NAME;
        switch (strtolower($DB_DRIVER_NAME))
        {
            case 'sqlite':
                $this->is_db_sqlite = true;
                break;
            default:
                $this->is_db_mysql  = true;
        }
    }

    private function _existOptionDesc($context, $name)
    {
        return $this->_getOID($context, $name) ? TRUE : FALSE;
        // check name in plugin_option_desc
//        $sql = 'SELECT count(*) AS result FROM ' . sql_table('plugin_option_desc')
//               . sprintf(' WHERE opid = %d AND ocontext = %s AND oname = %s LIMIT 1 ',
//                       intval($this->plugid), sql_quote_string($context), sql_quote_string($name));
//        return intval(quickQuery($sql)) > 0;
    }

    public function existOptionDesc($name)
    {
        return $this->_existOptionDesc('global' , $name);
    }

    public function existBlogOptionDesc($name)
    {
        return $this->_existOptionDesc('blog' , $name);
    }

    public function existCategoryOptionDesc($name)
    {
        return $this->_existOptionDesc('category' , $name);
    }

    public function existItemOptionDesc($name)
    {
        return $this->_existOptionDesc('item' , $name);
    }
    
    public function existMemberOptionDesc($name)
    {
        return $this->_existOptionDesc('member' , $name);
    }
    
    function _updateOptionDesc($context, $name, $desc, $type, $defValue, $typeExtras = '')
    {
        $context  = (string) $context;
        $name     = (string) $name;
        $desc     = (string) $desc;
        $type     = (string) $type;
        $defValue = (string) $defValue;
        $typeExtras = (string) $typeExtras;

        $e = $this->_existOptionDesc($context, $name);
        // create in plugin_option_desc
        if (!$e)
            return $this->_createOption( $context , $name , $desc , $type , $defValue , $typeExtras );

        global $DB_PHP_MODULE_NAME;
        if ($DB_PHP_MODULE_NAME == 'pdo')
        {
            $sql = parseQuery('UPDATE [@prefix@]plugin_option_desc')
                   . ' SET  odesc=? , otype=? , odef=? , oextra=? '
                   . ' WHERE opid=? AND ocontext=? AND oname=? ' ;
            $params = array( $desc , $type , $defValue, $typeExtras
                           , intval($this->plugid) , $context , $name);
            $stmt = sql_prepare_execute($sql, $params);
//      trigger_error( implode(' : ', $stmt->errorInfo) );
        }
        else
        {
            $sql = 'UPDATE ' . sql_table('plugin_option_desc')
                   . ' SET '
                   . sprintf(' odesc=%s , otype=%s , odef=%s , oextra=%s ',
                             sql_quote_string($desc),
                             sql_quote_string($type),
                             sql_quote_string($defValue),
                             sql_quote_string($typeExtras)
                           )
                   . sprintf(' WHERE opid=%d AND ocontext=%s AND oname=%s ',
                             intval($this->plugid),
                             sql_quote_string($context),
                             sql_quote_string($name)
                           ) ;
           sql_query($sql);
        }

        $oid = $this->_getOID($context, $name);
        $key = $context . '_' . $name;
        $this->_aOptionToInfo[$key] = array('oid' => $oid, 'default' => $defValue);
        return TRUE;
    }

    function updateOptionDesc($name, $desc, $type, $defValue, $typeExtras = '')
    {
        return $this->_updateOptionDesc('global', $name, $desc, $type, $defValue, $typeExtras);
    }

    function updateBlogOptionDesc($context, $name, $desc, $type, $defValue, $typeExtras = '')
    {
        return $this->_updateOptionDesc('blog', $name, $desc, $type, $defValue, $typeExtras);
    }

    function updateCategoryOptionDesc($context, $name, $desc, $type, $defValue, $typeExtras = '')
    {
        return $this->_updateOptionDesc('category', $name, $desc, $type, $defValue, $typeExtras);
    }
    
    function updateItemOptionDesc($context, $name, $desc, $type, $defValue, $typeExtras = '')
    {
        return $this->_updateOptionDesc('item', $name, $desc, $type, $defValue, $typeExtras);
    }

    function updateMemberOptionDesc($context, $name, $desc, $type, $defValue, $typeExtras = '')
    {
        return $this->_updateOptionDesc('member', $name, $desc, $type, $defValue, $typeExtras);
    }

    public function getRemoteVersion()
    {
        $NP_Name = get_class($this); // get_called_class();
        if ( in_array($NP_Name, array('NP_SkinFiles', 'NP_SecurityEnforcer', 'NP_Text')) )
            return false;  // bundled plugins
        return ADMIN::getRemotePluginVersion($NP_Name, true);
    }

    public function checkRemoteUpdate()
    {
        static $enable_db = null;
        $ret_val = array('result' => false, 'version' => '', 'download'=>'');
//        if (!function_exists('get_called_class'))
//            return $ret_val;
        $NP_Name = get_class($this); // get_called_class();
        if (is_null($enable_db))
            $enable_db = CoreCachedData::existTable();
        if (!$enable_db)
            return $ret_val;

        global $DB_PHP_MODULE_NAME;
        $use_cache   = false;
        $exist_cache = false;
        $offset = 60*60*24*3; // cache expired time 3days
        $expired_time = time() - $offset;

        $col_type      = 'plugin_remote_latest_version';
        $col_sub_type  = 'text';
        $col_name      = 'github';
        $col_sub_id    = $this->getID();

        // check cache data
        $data = CoreCachedData::getDataEx($col_type, $col_sub_type, $col_sub_id, $col_name, $expired_time);
        if (!empty($data) && !$data['expired'])
        {
            $use_cache = true;
            $ver2 = $data['value'];
        }

        if (!$use_cache)
        {
            // get latest
            $ver2 = $this->getRemoteVersion();
            if (empty($ver2))
                $ver2 = '-';
            // save db
            CoreCachedData::setDataEx($col_type, $col_sub_type, $col_sub_id, $col_name, $ver2);
        }

        if (empty($ver2) || in_array($ver2, array('-')))
            return $ret_val;
        // compare version
        $ver1 = $this->getVersion();

        if ( version_compare($ver1, $ver2, '<') )
        {
            $ret_val['result']  = true;
            $ret_val['version']  = $ver2;
            $ret_val['download'] = sprintf("https://github.com/NucleusCMS/%s/archive/master.zip", $NP_Name);
        }
        return $ret_val;
    }

} // end class

