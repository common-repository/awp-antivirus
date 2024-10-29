<?php
/*
Plugin Name: AWP Antivirus
Plugin URI: http://www.awp-antivirus.com
Description: Antivirus Wordpress that protects your website
Version: 1.0.3
Author: CWS Agency
Author URI: http://cws.agency
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

$currentFile = __FILE__;
$currentFolder = dirname($currentFile);

require_once $currentFolder . '/inc/AWPAntivirus.class.php';
require_once $currentFolder . '/inc/functions.php';
require_once $currentFolder . '/inc/hooks.php';

function createAwpMenus()
{
	add_menu_page('AWP Antivirus', 'AWP Antivirus', 'manage_options', 'AWP', 'AWP_options', plugins_url( 'images/icone.png' , __FILE__  ), 102);
	
}

function AWP_options()
{
	if ( ! is_admin()) { return; }
	$AWP = new AWPAntivirus();
	return $AWP->options();
	
}

add_action("admin_menu", "createAwpMenus");
