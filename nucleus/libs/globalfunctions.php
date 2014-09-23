<?php

/*
 * Nucleus: PHP/MySQL Weblog CMS (http://nucleuscms.org/)
 * Copyright (C) 2002-2009 The Nucleus Group
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 * (see nucleus/documentation/index.html#license for more info)
 */
/**
 * @license http://nucleuscms.org/license.txt GNU General Public License
 * @copyright Copyright (C) 2002-2009 The Nucleus Group
 * @version $Id$
 */

/* needed if we include globalfunctions from install.php */
global $nucleus, $CONF, $DIR_LIBS, $DIR_LOCALES, $manager, $member, $MYSQL_HANDLER, $StartTime;

/* just for benchmark tag */
$StartTime = microtime(TRUE);

$nucleus['version'] = 'v4.00 SVN';
$nucleus['codename'] = '';

include_once('globalfunctions.inc.php');

if ( !headers_sent() ) header('Generator: Nucleus CMS ' . $nucleus['version']);

if ( version_compare(PHP_VERSION, '5.3.0', '<') ) ini_set('magic_quotes_runtime', '0');

checkVars();

if ( getNucleusPatchLevel() > 0 ) $nucleus['version'] .= '/' . getNucleusPatchLevel();

if ( isset($CONF['debug']) && $CONF['debug'] ) error_reporting(E_ALL | E_STRICT);
else                                           error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);

if(!isset($CONF['UsingAdminArea'])||$CONF['UsingAdminArea']!=1)
	ini_set('display_errors','0');

if(!isset($CONF)) $CONF = array();

/*
 * alertOnHeadersSent
 *  Displays an error when visiting a public Nucleus page and headers have
 *  been sent out to early. This usually indicates an error in either a
 *  configuration file or a translation file, and could cause Nucleus to
 *  malfunction
 */
$CONF['alertOnHeadersSent']  = !isset($CONF['alertOnHeadersSent'])  ? 1 : $CONF['alertOnHeadersSent'];

/*
 * alertOnSecurityRisk
 * Displays an error only when visiting the admin area, and when one or
 *  more of the installation files (install.php, install.sql, upgrades/
 *  directory) are still on the server.
 */
$CONF['alertOnSecurityRisk'] = !isset($CONF['alertOnSecurityRisk']) ? 1 : $CONF['alertOnSecurityRisk'];

/*
 * Set these to 1 to allow viewing of future items or draft items
 * Should really never do this, but can be useful for some plugins that might need to
 * Could cause some other issues if you use future posts otr drafts
 * So use with care
 */
$CONF['allowDrafts']         = 0;
$CONF['allowFuture']         = 0;

$CONF['installscript']       = ( !isset($CONF['installscript']) || empty($CONF['installscript']) ) ? 0 : $CONF['installscript'];
$CONF['UsingAdminArea']      = ( !isset($CONF['UsingAdminArea']) ) ? 0 : $CONF['UsingAdminArea'];

/* TODO: This is for compatibility since 4.0, should be obsoleted at future release. */
global $DIR_LOCALES,$DIR_LANG;
if ( !isset($DIR_LOCALES) ) $DIR_LOCALES = "{$DIR_NUCLEUS}locales/";
if ( !isset($DIR_LANG) )    $DIR_LANG    = $DIR_LOCALES;

/* load and initialize i18n class */
if (!class_exists('i18n', FALSE)) include("{$DIR_LIBS}i18n.php");
if ( !i18n::init('UTF-8', $DIR_LOCALES) ) exit('Fail to initialize i18n class.');

/* TODO: This is just for compatibility since 4.0, should be obsoleted at future release. */
define('_CHARSET', i18n::get_current_charset());


/*
 * NOTE: Since 4.0 release, Entity class becomes to be important class
 *  with some wrapper functions for htmlspechalchars/htmlentity PHP's built-in function
 */
include_once("{$DIR_LIBS}ENTITY.php");

/* we will use postVar, getVar, ... methods instead of $_GET, $_POST ... */
if ( $CONF['installscript'] != 1 )
{
	/* vars were already included in install.php */
	include_once($DIR_LIBS . 'vars.php');
	
	/* added for 4.0 DB::* wrapper and compatibility sql_* */
	include_once($DIR_LIBS . 'sql/sql.php');
}

/* include core classes that are needed for login & plugin handling */
include_once("{$DIR_LIBS}MEMBER.php");
include_once("{$DIR_LIBS}ACTIONLOG.php");
include_once("{$DIR_LIBS}MANAGER.php");
include_once("{$DIR_LIBS}PLUGIN.php");

$manager =& MANAGER::instance();

/* only needed when updating logs */
if ( $CONF['UsingAdminArea'] )
{
	include_once("{$DIR_LIBS}xmlrpc.inc.php"); // XML-RPC client classes
	include_once("{$DIR_LIBS}ADMIN.php");
}

/* connect to database */
if ( !isset($MYSQL_HANDLER) )  $MYSQL_HANDLER = array('mysql', '');
if ( $MYSQL_HANDLER[0] == '' ) $MYSQL_HANDLER[0] = 'mysql';

DB::setConnectionInfo($MYSQL_HANDLER[1], $MYSQL_HOST, $MYSQL_USER, $MYSQL_PASSWORD, $MYSQL_DATABASE);


/* force locale or charset */
$locale = '';
$charset = i18n::get_current_charset();

$data = array(
	'locale'	=> &$locale,
	'charset'	=> &$charset
);
$manager->notify('ForceLocale', $data);

if ( $data['locale'] !== '' )  i18n::set_forced_locale($data['locale']);
if ( $data['charset'] !== '' ) i18n::set_forced_charset($data['charset']);

unset($locale);
unset($charset);


/* convert forced charset to current charset */
if ( i18n::get_forced_charset() != i18n::get_current_charset() )
{
	$_POST    = i18n::convert_array($_POST,    i18n::get_forced_charset());
	$_GET     = i18n::convert_array($_GET,     i18n::get_forced_charset());
	$_REQUEST = i18n::convert_array($_REQUEST, i18n::get_forced_charset());
	$_COOKIE  = i18n::convert_array($_COOKIE,  i18n::get_forced_charset());
	$_FILES   = i18n::convert_array($_FILES,   i18n::get_forced_charset());
	
	if ( session_id() !== '' )
		$_SESSION = i18n::convert_array($_SESSION, i18n::get_forced_charset());
}


/* sanitize option */
$bLoggingSanitizedResult = 0;
$bSanitizeAndContinue    = 0;
$orgRequestURI           = serverVar('REQUEST_URI');
sanitizeParams();

/* logs sanitized result if need */
$requestURI = serverVar('REQUEST_URI');
$remoteADDR = serverVar('REMOTE_ADDR');
if ( $orgRequestURI !== $requestURI )
{
	$msg = "Sanitized [{$remoteADDR}] {$orgRequestURI} -> {$requestURI}";
	if ( $bLoggingSanitizedResult ) addToLog(WARNING, $msg);
	if ( !$bSanitizeAndContinue )   exit;
}

/* get all variables that can come from the request and put them in the global scope */
$blogid		  = requestVar('blogid');
$itemid		  = intRequestVar('itemid');
$catid		  = intRequestVar('catid');
$skinid		  = requestVar('skinid');
$memberid	  = requestVar('memberid');
$archivelist  = requestVar('archivelist');
$imagepopup	  = requestVar('imagepopup');
$archive	  = requestVar('archive');
$query		  = requestVar('query');
$highlight	  = requestVar('highlight');
$amount		  = requestVar('amount');
$action		  = requestVar('action');
$nextaction	  = requestVar('nextaction');
$maxresults	  = requestVar('maxresults');
$startpos	  = intRequestVar('startpos');
$errormessage = '';
$error		  = '';
$special	  = requestVar('special');


/* read config */
getConfig();


/* Properly set $CONF['Self'] and others if it's not set...
 * usually when we are access from admin menu
 */
if ( !isset($CONF['Self']))
{
	$CONF['Self'] = $CONF['IndexURL'];
	$CONF['Self'] = rtrim($CONF['Self'], '/');
}

$CONF['ItemURL']		= $CONF['Self'];
$CONF['ArchiveURL']		= $CONF['Self'];
$CONF['ArchiveListURL']	= $CONF['Self'];
$CONF['MemberURL']		= $CONF['Self'];
$CONF['SearchURL']		= $CONF['Self'];
$CONF['BlogURL']		= $CONF['Self'];
$CONF['CategoryURL']	= $CONF['Self'];

/* automatically use simpler toolbar for mozilla */
if ( ($CONF['DisableJsTools'] == 0)
   && i18n::strpos(serverVar('HTTP_USER_AGENT'), 'Mozilla/5.0') !== FALSE
   && i18n::strpos(serverVar('HTTP_USER_AGENT'), 'Gecko') !== FALSE )
{
	$CONF['DisableJsTools'] = 2;
}

/* login processing */
$member = new Member();
switch($action)
{
	case 'login':
		$login    = postVar('login');
		$password = postVar('password');
		$shared   = intPostVar('shared');
		$member->login($login, $password, $shared);
		break;
	case 'logout':
		$member->logout();
		break;
	default:
		$member->cookielogin();
}

/* first, let's see if the site is disabled or not. always allow admin area access. */
if ( $CONF['DisableSite'] && !$member->isAdmin() && !$CONF['UsingAdminArea'] )
{
	redirect($CONF['DisableSiteURL']);
	exit;
}

/* TODO: This is for backward compatibility, should be obsoleted near future. */
if ( !preg_match('#^(.+)_(.+)_(.+)$#', $CONF['Locale'])
  && ($CONF['Locale'] = i18n::convert_old_language_file_name_to_locale($CONF['Locale'])) === FALSE )
{
	$CONF['Locale'] = 'en_Latn_US';
}

if ( !isset($CONF['Language']) )
{
	$CONF['Language'] = i18n::convert_locale_to_old_language_file_name($CONF['Locale']);
}

$locale = $CONF['Locale'];

/* NOTE: include translation file and set locale */
if ( $member->isLoggedIn() )
{
	if ( $member->getLocale() ) $locale = $member->getLocale();
}
elseif ( i18n::get_forced_locale() !== '' )
	$locale = i18n::get_forced_locale();

include_translation($locale);
i18n::set_current_locale($locale);


/* login completed */
$data = array('loggedIn' => $member->isLoggedIn());
$manager->notify('PostAuthentication', $data);

/* next action */
if ( $member->isLoggedIn() && $nextaction )
	$action = $nextaction;

/* load other classes */
include_once("{$DIR_LIBS}PARSER.php");
include_once("{$DIR_LIBS}SKIN.php");
include_once("{$DIR_LIBS}TEMPLATE.php");
include_once("{$DIR_LIBS}BLOG.php");
include_once("{$DIR_LIBS}BODYACTIONS.php");
include_once("{$DIR_LIBS}COMMENTS.php");
include_once("{$DIR_LIBS}COMMENT.php");
include_once("{$DIR_LIBS}NOTIFICATION.php");
include_once("{$DIR_LIBS}BAN.php");
include_once("{$DIR_LIBS}SEARCH.php");
include_once("{$DIR_LIBS}LINK.php");

/* set lastVisit cookie (if allowed) */
$now = time();
if ( !headers_sent() )
{
	if ( $CONF['LastVisit'] )
		setcookie($CONF['CookiePrefix'] . 'lastVisit', $now, $now + 2592000, $CONF['CookiePath'], $CONF['CookieDomain'], $CONF['CookieSecure']);
	else
		setcookie($CONF['CookiePrefix'] . 'lastVisit', '', $now - 2592000,   $CONF['CookiePath'], $CONF['CookieDomain'], $CONF['CookieSecure']);
}

/* for path resolving */
$virtualpath = getVar('virtualpath');
if ( $virtualpath === '' )
	$virtualpath = serverVar('PATH_INFO');

/*
 * switch URLMode back to normal when $CONF['Self'] ends in .php
 * this avoids urls like index.php/item/13/index.php/item/15
 */
if ( !isset($CONF['URLMode']) || ($CONF['URLMode'] !== 'pathinfo') )
	$CONF['URLMode'] = 'normal';
elseif ( i18n::substr($CONF['Self'], i18n::strlen($CONF['Self']) - 4) != '.php' )
	decodePathInfo($virtualpath);

/*
 * PostParseURL is a place to cleanup any of the path-related global variables before the selector function is run.
 * It has 2 values in the data in case the original virtualpath is needed, but most the use will be in tweaking
 * global variables to clean up (scrub out catid or add catid) or to set someother global variable based on
 * the values of something like catid or itemid
 * New in 3.60
 */
$data = array(
	'type' => basename(serverVar('SCRIPT_NAME')),
	'info' => $virtualpath
);
$manager->notify('PostParseURL', $data);

// NOTE: Here is the end of initialization
