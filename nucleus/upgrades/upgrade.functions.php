<?
	/**
	  * Nucleus: PHP/MySQL Weblog CMS (http://nucleuscms.org/) 
	  * Copyright (C) 2002 The Nucleus Group
	  *
	  * This program is free software; you can redistribute it and/or
	  * modify it under the terms of the GNU General Public License
	  * as published by the Free Software Foundation; either version 2
	  * of the License, or (at your option) any later version.
	  * (see nucleus/documentation/index.html#license for more info)
	  *	
	  * Some functions common to all upgrade scripts
	  */

	include('../../config.php');

	function upgrade_checkinstall($version) {
		$installed = 0;

		switch($version) {
			case '95':
				$query = 'SELECT bconvertbreaks FROM '.sql_table('blog').' LIMIT 1';
				$minrows = -1;
				break;
			case '96':
				$query = 'SELECT cip FROM '.sql_table('comment').' LIMIT 1';
				$minrows = -1;			
				break;
			case '10':
				$query = 'SELECT mcookiekey FROM '.sql_table('member').' LIMIT 1';
				$minrows = -1;			
				break;			
			case '11':
				$query = 'SELECT bnotifytype FROM '.sql_table('blog').' LIMIT 1';
				$minrows = -1;			
				break;
			case '15':
				$query = 'SELECT * FROM '.sql_table('plugin_option').' LIMIT 1';
				$minrows = -1;			
				break;			
			case '20':
				$query = 'SELECT oid FROM '.sql_table('plugin_option').' LIMIT 1';
				$minrows = -1;			
				break;				
		}

		$res = mysql_query($query);
		$installed = ($res != 0) && (mysql_num_rows($res) > $minrows);

		return $installed;
	}
	
	
	/** this function gets the nucleus version, even if the getNucleusVersion
	 * function does not exist yet
	 * return 96 for all versions < 100
	 */
	function upgrade_getNucleusVersion() {
		if (!function_exists('getNucleusVersion')) return 96;
		return getNucleusVersion();
	}
	
	function upgrade_showLogin($type) {
		upgrade_head();
	?>
		<h1>Please Log in First</h1>
		<p>Enter your data below:</p>
		
		<form method="post" action="<?=$type?>">

			<ul>
				<li>Name: <input name="login" /></li>
				<li>Password <input name="password" type="password" /></li>
			</ul>

			<p>
				<input name="action" value="login" type="hidden" />
				<input type="submit" value="Log in" />
			</p>
		
		</form>
	<?
		upgrade_foot();
		exit;
	}
	
	function upgrade_head() {
	?>
			<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
			<html>
			<head>
				<title>Nucleus Upgrade</title>
				<style><!--
					@import url('../styles/manual.css');
					.warning {
						color: red;
					}
					.ok {
						color: green;
					}
				--></style>
			</head>
			<body>		
	<?
	}

	function upgrade_foot() {
	?>
			</body>
			</html>	
	<?
	}	
	
	function upgrade_error($msg) {
		upgrade_head();
		?>
		<h1>Error!</h1>

		<p>Message was:</p>
		
		<blockquote><div>
		<?=$msg?>
		</div></blockquote>

		<p><a href="index.php" onclick="history.back();">Go Back</a></p>
		<?

		upgrade_foot();
		exit;
	}
	
	
	function upgrade_start() {
		global $upgrade_failures;
		$upgrade_failures = 0;
		
		upgrade_head();
		?>
		<h1>Executing Upgrades</h1>
		<ul>
		<?
	}
	
	function upgrade_end($msg = "") {
		global $upgrade_failures;
		if ($upgrade_failures > 0)
			$msg = "Some queries have failed. If you've runned this upgrade script before, this should be normal.";
	
		?>
		</ul>
		
		<h1>Upgrade Completed!</h1>

		<p><?=$msg?></p>
		
		<p>Back to the <a href="index.php">Upgrades Overview</a></p>

		<?

		upgrade_foot();
		exit;
	}	
	
	/**
	  * Tries to execute a query, gives a message when failed
	  *
	  * @param friendly name
	  * @param query		
	  */
	function upgrade_query($friendly, $query) {
		global $upgrade_failures;
		
		echo "<li>$friendly ... ";
		$res = mysql_query($query);
		if (!$res) {
			echo "<span style='color:red'>FAILED</span>\n";
			echo "<blockquote>Error was: " . mysql_error() . " </blockquote>";
			$upgrade_failures++;
		} else {
			echo "<span style='color:green'>SUCCESS!</span><br />\n";
		}
		echo "</li>";
		return $res;
	}
	



?>