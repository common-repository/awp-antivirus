<?php


if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

function AWP_get_services()
{
	$services  = get_option('wp_awp_services');
	if(!is_array($services)) $services = array();
	$services = array_merge(
		array(
			'scan' => true,
			'clean' => false,
			'firewall' => false,
			'media' => false,
			'fail2ban' => false,
			'update' => false
		),
		
		$services
	);
	
	return $services;
}

function AWP_get_failed_records()
{
	$ips = get_option('wp_awp_failed_ip');
	if(!is_array($ips)) $ips = array();
	
	return $ips;
}



function AWP_get_ip_locked()
{
	$ips = get_option('wp_awp_locked_ip');
	if(!is_array($ips)) $ips = array();
	foreach($ips as $ip=>$time)
	{
		if($time < time()) unset($ips[$ip]);
	}
	return $ips;
}

function AWP_lock_ip($ip)
{
	$ips = AWP_get_ip_locked();
	$ips[$ip] = time() + 24*3600;
	update_option('wp_awp_locked_ip', $ips);
}

function AWP_unlock_ip($ip)
{
	$ips = AWP_get_ip_locked();
	if(isset($ips[$ip])) unset($ips[$ip]);
	update_option('wp_awp_locked_ip', $ips);
}
