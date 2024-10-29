<?php


if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly



/* AJAX HOOK */
function AWP_ajax_call()
{
	
	
	$poptions  = get_option('wp_awp_options');

	if($poptions['datelastscan'] < time() - (6*60*60) || isset($_GET['force']) || !isset($poptions['datelastscan']) || empty($poptions['datelastscan']))
	{
		if(isset($_GET['rescanall']))
		{
			$poptions['oldestfile'] = 0;
			update_option('wp_awp_options', $poptions);
		}
		$AWP = new AWPAntivirus();
		$AWP->performScan('..');
		$poptions  = get_option('wp_awp_options');
	}
	echo $poptions['lastscan'];
	exit();
}

function AWP_footer_script() {
	// Your PHP goes here
	$services = AWP_get_services();
	
	if($services['scan']):
	?>
	<script type="text/javascript">
		var xhr; 
		try {  xhr = new ActiveXObject('Msxml2.XMLHTTP');   }
		catch (e) 
		{
			try {   xhr = new ActiveXObject('Microsoft.XMLHTTP'); }
			catch (e2) 
			{
			   try {  xhr = new XMLHttpRequest();  }
			   catch (e3) {  xhr = false;   }
			 }
		}
		xhr.onreadystatechange  = function() 
		{ 
		}; 
	   xhr.open( "GET", "<?php echo admin_url('admin-ajax.php'); ?>?action=awp_scan",  true); 
	   xhr.send(null); 
	</script>
	<?php
	endif;
}

add_action( 'wp_footer', 'AWP_footer_script' );
add_action( 'wp_ajax_awp_scan', 'AWP_ajax_call' );
add_action( 'wp_ajax_nopriv_awp_scan', 'AWP_ajax_call' );



/* MEDIA VALIDATION HOOK */

function AWP_media_upload_validation()
{
	$services = AWP_get_services();
	if(!$services['media']) return;
	//$AWP = new AWPAntivirus();
	//$AWP->performScan('..');
	$poptions  = get_option('wp_awp_options');
	$poptions['datelastscan'] = 0;
	update_option('wp_awp_options', $poptions);
}

add_action('add_attachment', 'AWP_media_upload_validation');


/* NOTICE MESSAGE HOOK */

function AWP_admin_notice__error() {
	
	$poptions  = get_option('wp_awp_options');
	if(isset($poptions['enddate']) && $poptions['enddate']>=date('Y-m-d')) return true;
	$awp = new AWPAntivirus();
	
	$class = 'notice notice-error';
	$message = '<p>'.__('You are currently using a trial version of AWP Antivirus. Corrupted files won\'t be automatically corrected.', 'awp-antivirus').'</p>';
	$message .= '<p><a href="'.$awp->getUpgradeUrl().'" target="_blank" class="button">'.__('Upgrade', 'awp-antivirus').'</a> <a href="'.admin_url( '?page=AWP').'" class="button">'.__('Type in your API key', 'awp-antivirus').'</a></p>';

	printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message ); 
}
add_action( 'admin_notices', 'AWP_admin_notice__error' );







/* TRANSLATION HOOK */
function awp_load_textdomain() {
	load_plugin_textdomain( 'awp-antivirus', false, dirname( plugin_basename(str_replace('inc/hooks.php', 'AWP-antivirus.php', __FILE__)) ) . '/languages/' );
}
add_action('plugins_loaded', 'awp_load_textdomain');

$services = AWP_get_services();

if($services['update'])
{
	add_filter( 'allow_minor_auto_core_updates', '__return_true' );
	add_filter( 'allow_major_auto_core_updates', '__return_true' );
	add_filter( 'auto_update_plugin', '__return_true' );
	add_filter( 'auto_update_theme', '__return_true' );
	add_filter( 'auto_update_translation', '__return_true' );
	add_filter( 'auto_core_update_send_email', '__return_false' );
}

/* LOCK IPS */

function AWP_fail_login()
{
	$ips = AWP_get_failed_records();
	$ip = $_SERVER['REMOTE_ADDR'];
	if(!isset($ips[$ip])) $ips[$ip] = 0;
	$ips[$ip]++;
	if($ips[$ip] >= 5)
	{
		unset($ips[$ip]);
		AWP_lock_ip($ip);
	}
	update_option('wp_awp_failed_ip', $ips);
}

function AWP_success_login()
{
	$ips = AWP_get_failed_records();
	$ip = $_SERVER['REMOTE_ADDR'];
	if(isset($ips[$ip])) unset($ips[$ip]);
	
	update_option('wp_awp_failed_ip', $ips);
}

function AWP_authenticate_filter($user, $username, $password)
{
	$ips = AWP_get_ip_locked();
	if(isset($ips[$_SERVER['REMOTE_ADDR']]))
	{
		$user = new WP_Error('authentication_failed', __('Your IP has been locked for too many attempts failed.'));
	}
	return $user;
}

if($services['fail2ban'])
{
	add_action('wp_login_failed', 'AWP_fail_login');
	add_action('wp_login', 'AWP_success_login');
	add_filter( 'authenticate', 'AWP_authenticate_filter', 30, 3 );
}
