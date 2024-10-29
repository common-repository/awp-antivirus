<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


class AWPAntivirus
{
	private $found = array();
	private $files = array();
	private $filehide = array();
	private $counts = array('file'=>0, 'dir'=>0, 'hole'=>0, 'possible'=>0);
	private $apiurl = 'http://api.awp-antivirus.com/1.1'; //api.awp-antivirus.com
	private $upgradeurl = 'http://www.awp-antivirus.com/pricing/?site=';
	private $folderBl = array();
	private $fileBl = array();
	private $codeBl = array();
	private $oldest = 0;
	private $noldest = 0;
	private $price;
	
	public function  __construct() 
	{
		// Rien pour l’instant
		$this->upgradeurl .= home_url();
		$this->price = ' ('.__('4€ /month', 'awp-antivirus').')';
		$this->price = '';
	}
	
	public function init()
	{
		
		//~ wp_enqueue_style('wp_prettyphoto', '/wp-content/plugins/c1f/css/prettyPhoto.css'); 
		//~ wp_enqueue_script('jquery2','/wp-content/plugins/c1f/js/jquery.js' ,array() ,'1.8.3',false);
		
	}
	
	public function getUpgradeUrl()
	{
		return $this->upgradeurl;
	}
	
	public function preloadBlaclists()
	{
		$curl = curl_init();
		
		
		curl_setopt_array($curl, Array(
			CURLOPT_URL			=> $this->apiurl.'/blacklist.php',
			CURLOPT_RETURNTRANSFER => TRUE,
			CURLOPT_ENCODING	   => 'UTF-8',
		));

		$data = curl_exec($curl);
		
		$bls = json_decode($data);
		$this->folderBl = (array)$bls->folder;
		$this->fileBl = (array)$bls->file;
		$this->codeBl = (array)$bls->code;
		
	}
	
	public function codeBlacklist()
	{
		return $this->codeBl;
	}
	
	public function fileBlacklist()
	{
		return $this->fileBl;
	}
	
	public function folderBlacklist()
	{
		return $this->folderBl;
	}
	
	public function switchServices()
	{
		if(check_admin_referer( 'AWP_switchservice' ))
		{
			$services = AWP_get_services();
			
			
			if(isset($services[$_GET['switchservice']])) $services[$_GET['switchservice']] = !$services[$_GET['switchservice']];
			
			if($_GET['switchservice'] == 'firewall')
			{
				$firewall = @file_get_contents(plugins_url( 'files/firewallrules.txt' , str_replace('inc/AWPAntivirus.class.php', 'AWP-antivirus.php', __FILE__)  ));
				$htaccess = file_get_contents('../.htaccess');
				$htaccess = str_replace($firewall, '', $htaccess);
				if($services['firewall'])
				{
					$htaccess = $firewall.$htaccess;
				}
				
				$f = fopen('../.htaccess', 'w');
				fwrite($f, $htaccess);
				fclose($f);
			}
			
			update_option('wp_awp_services', $services);
		}
	}
	
	public function options()
	{
		
		$poptions  = get_option('wp_awp_options');
		
		if ( $_POST["key"] && check_admin_referer( 'AWP_check_api' )) 
		{
			$apikey = sanitize_text_field($_POST["key"]);
			if(strlen($apikey)>255) $apikey = '';
			if(!preg_match('/^[A-Za-z0-9]+$/', $apikey)) $apikey = '';
			$poptions['key'] = $apikey;
			update_option('wp_awp_options', $poptions);
			$this->checkapi($poptions['key']);
			$poptions  = get_option('wp_awp_options');
		}
		
		if(isset($_POST['iptolock']) &&  check_admin_referer( 'AWP_lock_ip' ))
		{
			$iptolock = sanitize_text_field($_POST["iptolock"]);
			$iptolock = filter_var($iptolock, FILTER_VALIDATE_IP);
			if($iptolock != 0)
			AWP_lock_ip($iptolock);
		}
		
		if(isset($_GET['iptounlock']) &&  check_admin_referer( 'AWP_unlock_ip' ))
		{
			$iptounlock = sanitize_text_field($_GET["iptounlock"]);
			$iptounlock = filter_var($iptounlock, FILTER_VALIDATE_IP);
			AWP_unlock_ip($iptounlock);
		}
		
		if(isset($_GET['switchservice']))
		{
			$this->switchServices();
			
		}
		
		$services = AWP_get_services();
		
		
		
		
		$pdata = get_plugin_data( str_replace('inc/AWPAntivirus.class.php', 'AWP-antivirus.php', __FILE__));
		
		
		
		echo '<div class="wrap"><div id="icon-options-general" class="icon32"><br /></div><h2>AWP Antivirus Wordpress ('.$pdata['Version'].')</h2><br /><br />';
		
		
		echo '<div id="poststuff">';
		echo '<div class="postbox">';
		
		echo '<h3 class="hndle">'.__('Status', 'awp-antivirus').'</h3>';
		echo '<div class="inside">';
		if(isset($poptions['enddate']) && $poptions['enddate']>=date('Y-m-d'))
		{
			echo '<table style="background-color:#73ad59;color:#ffffff;width: 100%;">';
			echo '<tr><td style="width:100px;background:url('.plugins_url( 'images/check.png' , str_replace('inc/AWPAntivirus.class.php', 'AWP-antivirus.php', __FILE__)  ).') no-repeat center center;"></td>';
			echo '<td><h3 style="color:#ffffff;">'.__('Your website is protected', 'awp-antivirus').'</h3><p>'.__('You have a valid licence key for this antivirus until', 'awp-antivirus').' '.date('d/m/Y', strtotime($poptions['enddate'])).'.<br />'.__('Relax', 'awp-antivirus').'.</p></td></tr>';
			echo '</table>';
		}
		else
		{
			echo '<table style="background-color:#B20711;color:#ffffff;width: 100%;">';
			echo '<tr><td style="width:100px;background:url('.plugins_url( 'images/croix.png' , str_replace('inc/AWPAntivirus.class.php', 'AWP-antivirus.php', __FILE__)  ).') no-repeat center center;"></td>';
			echo '<td><h3 style="color:#ffffff;">'.__('Your website is not protected', 'awp-antivirus').'</h3><p>'.__('You are currently using the trial version of this antivirus.', 'awp-antivirus').'<br /><a href="'.$this->upgradeurl .'" target="_blank" class="button button-primary">'.__('Upgrade','awp-antivirus'). ''.$this->price.'</a></p></td></tr>';
			echo '</table>';
		}
		echo '</div>';
		echo '</div>';
		if(isset($_GET['scan']) && check_admin_referer( 'AWP_check_scan' ))
		{
			$this->performScan('..');
			$poptions  = get_option('wp_awp_options');
		}
		
		$validkey = isset($poptions['enddate']) && $poptions['enddate']>=date('Y-m-d');
		
		
		echo '<div class="postbox">';
		echo '<h3 class="hndle">'.__('Services', 'awp-antivirus').'</h3>';
		echo '<div class="inside">';
		echo '<div style="width:50%;float:left;box-sizing:border-box;padding-right:15px;">';
		echo '<p><a href="'.wp_nonce_url( admin_url( '?page=AWP&switchservice=scan'), 'AWP_switchservice' ).'" class="button'.(!$services['scan']?' button-primary':'').'" style="width:120px;text-align:center;margin-right: 15px;">'.($services['scan']?__('Stop', 'awp-antivirus'):__('Activate', 'awp-antivirus')).'</a> '.__('Automatic scan : Scans your website up to 4 times a day.', 'awp-antivirus').'</p>';
		echo '</div>';
		echo '<div style="width:50%;float:left;box-sizing:border-box;padding-left:15px;">';
		echo '<p><a href="'.($validkey?wp_nonce_url( admin_url( '?page=AWP&switchservice=clean'), 'AWP_switchservice' ):$this->upgradeurl.'" target="_blank').'" class="button'.(!$services['clean'] || !$validkey?' button-primary':'').'" style="width:120px;text-align:center;margin-right: 15px;">'.($validkey?($services['clean'] && $validkey?__('Stop', 'awp-antivirus'):__('Activate', 'awp-antivirus')):__('Upgrade', 'awp-antivirus')).'</a> '.__('Automatic cleanning : Remove malware code when it is found.', 'awp-antivirus').'</p>';
		echo '</div>';
		
		echo '<hr/ >';
		
		echo '<div style="width:50%;float:left;box-sizing:border-box;padding-right:15px;">';
		echo '<p><a href="'.($validkey?wp_nonce_url( admin_url( '?page=AWP&switchservice=firewall'), 'AWP_switchservice' ):$this->upgradeurl.'" target="_blank').'" class="button'.(!$services['firewall'] || !$validkey?' button-primary':'').'" style="width:120px;text-align:center;margin-right: 15px;">'.($validkey?($services['firewall'] && $validkey?__('Stop', 'awp-antivirus'):__('Activate', 'awp-antivirus')):__('Upgrade', 'awp-antivirus')).'</a> '.__('Firewall : Prevent most of the attacks.', 'awp-antivirus').'</p>';
		echo '</div>';
		echo '<div style="width:50%;float:left;box-sizing:border-box;padding-left:15px;">';
		echo '<p><a href="'.($validkey?wp_nonce_url( admin_url( '?page=AWP&switchservice=media'), 'AWP_switchservice' ):$this->upgradeurl.'" target="_blank').'" class="button'.(!$services['media'] || !$validkey?' button-primary':'').'" style="width:120px;text-align:center;margin-right: 15px;">'.($validkey?($services['media'] && $validkey?__('Stop', 'awp-antivirus'):__('Activate', 'awp-antivirus')):__('Upgrade', 'awp-antivirus')).'</a> '.__('Media upload validation : Checks every uploaded media.', 'awp-antivirus').'</p>';
		echo '</div>';
		echo '<hr/ >';
		
		echo '<div style="width:50%;float:left;box-sizing:border-box;padding-right:15px;">';
		echo '<p><a href="'.($validkey?wp_nonce_url( admin_url( '?page=AWP&switchservice=fail2ban'), 'AWP_switchservice' ):$this->upgradeurl.'" target="_blank').'" class="button'.(!$services['fail2ban'] || !$validkey?' button-primary':'').'" style="width:120px;text-align:center;margin-right: 15px;">'.($validkey?($services['fail2ban'] && $validkey?__('Stop', 'awp-antivirus'):__('Activate', 'awp-antivirus')):__('Upgrade', 'awp-antivirus')).'</a> '.__('Fail2ban : Bans IP addresses after several login attempts failed.', 'awp-antivirus').'</p>';
		echo '</div>';
		echo '<div style="width:50%;float:left;box-sizing:border-box;padding-left:15px;">';
		echo '<p><a href="'.($validkey?wp_nonce_url( admin_url( '?page=AWP&switchservice=update'), 'AWP_switchservice' ):$this->upgradeurl.'" target="_blank').'" class="button'.(!$services['update'] || !$validkey?' button-primary':'').'" style="width:120px;text-align:center;margin-right: 15px;">'.($validkey?($services['update'] && $validkey?__('Stop', 'awp-antivirus'):__('Activate', 'awp-antivirus')):__('Upgrade', 'awp-antivirus')).'</a> '.__('Auto update : Updates WP, plugins and themes.', 'awp-antivirus').'</p>';
		echo '</div>';
		
		
		echo '<div style="clear:both;"></div>';
		echo '</div>';
		echo '</div>';
		
		echo '<div class="postbox">';
		
		echo '<h3 class="hndle">'.__('San the website now', 'awp-antivirus').'</h3>';
		echo '<div class="inside">';
		echo '<p>'.__('Perform a manual scan now. All the corrupted files will be either corrected or deleted (if you have a valid licence key).', 'awp-antivirus').'</p>';
		echo '<a href="'.wp_nonce_url( admin_url( '?page=AWP&scan=1'), 'AWP_check_scan' ).'" id="buttonscan" class="button button-primary">'.__('Scan now', 'awp-antivirus').'</a>';
		
		
		echo '<div id="lastscan">';
		if(isset($poptions['lastscan']))
		{
			echo $poptions['lastscan'];
		}
		echo '</div>';
		echo '</div>';
		echo '</div>';
		?>
		<script type="text/javascript">
			function awp_scan(url, imageurl)
			{
				jQuery('#lastscan').html('<h3><?php echo __('Scanning, please wait', 'awp-antivirus'); ?></h3><img src="'+imageurl+'" alt="loading" />');
				jQuery.get(url+"?action=awp_scan&force=1", '', function(data){
					jQuery('#lastscan').html(data);
				}, 'html');
				return false;
			}
			jQuery('#buttonscan').click(function(){
				awp_scan('<?php echo admin_url('admin-ajax.php'); ?>', '<?php echo plugins_url( 'images/ajax-loader.gif' , str_replace('inc/AWPAntivirus.class.php', 'AWP-antivirus.php', __FILE__)  ); ?>');
				return false;
			});
		</script>
		<?php
		echo '<div class="postbox">';
		
		echo '<h3 class="hndle">'.__('Your licence key', 'awp-antivirus').'</h3>';
		echo '<div class="inside">';
		echo '<form method="post" action="'.admin_url( '?page=AWP').'">';
		if(!isset($poptions['enddate']) || !$poptions['enddate']>=date('Y-m-d')) echo '<p>'.__('You are currently using the trial version, you can', 'awp-antivirus').' <a href="'.$this->upgradeurl .'" target="_blank">'.__('Upgrade', 'awp-antivirus').''.$this->price.'</a> '.__('and type in your key', 'awp-antivirus').'</p>';
		else echo '<p>'.__('Vous are currently using the full version.', 'awp-antivirus').'</p>';
		echo '<label for="formulaire" style="display:inline-block;width:200px;padding-right:15px;text-align:right;">'.__('Licence key', 'awp-antivirus').'</label><input type="text" value="'.@$poptions['key'].'" name="key" value="" /> ';
		wp_nonce_field( 'AWP_check_api' );
		echo '<input type="submit" value="'.__('Save', 'awp-antivirus').'" class="button button-primary"></form></div>';
		if(!isset($poptions['enddate']) || !$poptions['enddate']>=date('Y-m-d')) echo '<p><label for="" style="display:inline-block;width:200px;padding-right:15px;text-align:right;"></label>'.__('or', 'awp-antivirus').' <a href="'.$this->upgradeurl .'" target="_blank" class="button button-primary">'.__('Upgrade', 'awp-antivirus').''.$this->price.'</a></p>';
		
		echo '</div>';
		
		
		echo '<div class="postbox">';
		echo '<h3 class="hndle">'.__('Banned IP', 'awp-antivirus').'</h3>';
		echo '<div class="inside">';
		echo '<form action="'.admin_url( '?page=AWP').'" method="post">';
		echo '<label for="formulaire" style="display:inline-block;width:200px;padding-right:15px;text-align:right;">'.__('IP to lock', 'awp-antivirus').'</label><input type="text" name="iptolock" value="" /> ';
		wp_nonce_field( 'AWP_lock_ip' );
		echo '<input type="submit" value="'.__('Add', 'awp-antivirus').'" class="button button-primary">';
		echo '</form>';
		$ips = AWP_get_ip_locked();
		if(count($ips) == 0) 
		{
			echo '<p>'.__('No IP was banned so far', 'awp-antivirus').'</p>';
		}
		else
		{
			echo '<ul>';
			foreach($ips as $ip=>$time)
			{
				echo '<li>'.$ip.' '.__('until', 'awp-antivirus').' '.date('d/m/Y H:i:s', $time).' <a href="'.wp_nonce_url( admin_url( '?page=AWP&iptounlock='.$ip), 'AWP_unlock_ip' ).'">'.__('unlock', 'awp-antivirus').'</a></li>';
			}
			echo '</ul>';
		}
		echo '</div>';
		echo '</div>';
		echo '</div>';
		echo '</div>';
	}
	
	public function checkapi($key)
	{
		$curl = curl_init();
		
		
		curl_setopt_array($curl, Array(
			CURLOPT_URL			=> $this->apiurl.'/licence.php?site='.home_url().'&key='.$key,
			CURLOPT_RETURNTRANSFER => TRUE,
			CURLOPT_ENCODING	   => 'UTF-8',
		));

		$data = curl_exec($curl);
		$poptions  = get_option('wp_awp_options');
		$poptions['enddate'] = $data;
		$poptions['oldestfile'] = 0;
		update_option('wp_awp_options', $poptions);
		curl_close($curl);
	}
	
	public function performScan($folder)
	{
		$poptions  = get_option('wp_awp_options');
		$lock = get_option('wp_awp_lock');
		
		if($lock  > time() - 60)
		{
			
			return true;
		}
		else
		{
			$lock  = time();
			update_option('wp_awp_lock', $lock );
		}
		ob_start();
		clearstatcache();
		$this->oldest = isset($poptions['oldestfile'])?$poptions['oldestfile']:0;
		$this->noldest = $this->oldest;
		$this->scan($folder, $folder);
		if(count($this->files)>0)
		{
			$return = $this->request();
			$this->doactions($return);
		}
		else
		{
			$return = new stdClass;
			$return->check = 0;
			$return->delete = 0;
			$return->update = 0;
			$return->virus = 0;
			$return->licence = $poptions['enddate']>=date('Y-m-d');
		}
		$poptions  = get_option('wp_awp_options');
		if($return->virus == 0 && $return->check == 0) $poptions['oldestfile'] = $this->noldest;
		ob_get_clean();
		ob_start();
		echo '<h3>'.__('Result of the latest scan', 'awp-antivirus').'</h3>';
		echo '<em>'.__('Done on', 'awp-antivirus').'  '.date('d/m/Y').' '.__('at', 'awp-antivirus').' '.date('H:i:s').' </em><br />';
		echo '<ul>';
		echo '<li>'.$this->counts['dir'].' '.__('folder(s) opened and', 'awp-antivirus').' '.$this->counts['file'].' '.__('file(s) scanned', 'awp-antivirus').'</li>';
		echo '<li>'.$this->counts['possible'].' '.__('potentially dangerous line(s) of code.', 'awp-antivirus').'</li>';
		if($return->virus == 0 && $return->check == 0)
		{
			echo '<li style="color:green;">'.__('No virus found.', 'awp-antivirus').'</li>';
		}
		else
		{
			if($return->check > 0)
				echo '<li style="color:orange;">'.$return->check.' '.__('file(s) were submitted for deeper analysis (it will be checked within a few hours).', 'awp-antivirus').'</li>';
			if($return->virus > 0)
				echo '<li style="color:red;">'.$return->virus.' '.__('infected file(s) found', 'awp-antivirus').' : '.$return->delete.' '.__('to be deleted and', 'awp-antivirus').' '.$return->update.' '.__('to be corrected', 'awp-antivirus').'</li>';
		}
		echo '</ul>';
		if($return->licence == 0) echo '<p><span style="color:red;">'.__('You don\'t have a valid licence key, files won\'t be deleted.', 'awp-antivirus').' '.__('Upgrade', 'awp-antivirus').''.$this->price.' '.__('so it is done automatically.', 'awp-antivirus').'</span><br /><a href="'.$this->upgradeurl .'" target="_blank" class="button button-primary">'.__('Upgrade', 'awp-antivirus').''.$this->price.'</a></p>';
		else echo '<p>'.__('You have a valid licence key, files will be corrected automatically.', 'awp-antivirus').'</p>';
		if(isset($return->debug)) echo '<p><em>'.$return->debug.'</em></p>';
		$poptions['lastscan'] = ob_get_clean();
		$poptions['datelastscan'] = time();
		
		update_option('wp_awp_options', $poptions);
		
	}
	
	public function request()
	{
		global $wp_version;
		$poptions  = get_option('wp_awp_options');
		$key = isset($poptions['key'])?$poptions['key']:'';
		$pdata = get_plugin_data( str_replace('inc/AWPAntivirus.class.php', 'AWP-antivirus.php', __FILE__));
		$post = array('site'=>home_url(), 'licence'=>$key, 'wpversion'=>$wp_version, 'awpversion'=>$pdata['Version'], 'email'=>get_option( 'admin_email' ), 'ajaxurl'=>admin_url('admin-ajax.php'));
		
		
		foreach($this->files as $fname => $content)
		{
			$post['wpfiles'][$fname]['content'] = $content;
			$post['wpfiles'][$fname]['lines'] = $this->found[$fname];
			$post['wpfiles'][$fname]['description'] = '';
			foreach($this->found[$fname] as $found)
			{
				$post['wpfiles'][$fname]['description'] .= '<div class="block">';
				$post['wpfiles'][$fname]['description'] .= 'Type : <strong style="color:#ff0000;">'.$found[0].'</strong><br />Position : <strong style="color:#ff0000;">'.$found[1].'</strong><br /><br />';
				$post['wpfiles'][$fname]['description'] .= '<em>'.str_replace($found[0],'<strong style="color:#ff0000;">'.$found[0].'</strong>',htmlspecialchars ($found[2])).'</em>';
				$post['wpfiles'][$fname]['description'] .= '</div>';
			}
		}
		
		$curl = curl_init();

		curl_setopt_array($curl, Array(
			CURLOPT_URL			=> $this->apiurl.'/',
			CURLOPT_RETURNTRANSFER => TRUE,
			CURLOPT_ENCODING	   => 'UTF-8',
			CURLOPT_POST => TRUE,
			CURLOPT_POSTFIELDS => array('data'=>serialize($post))
			
		));

		$data = curl_exec($curl);
		curl_close($curl);
		return json_decode($data);
		
	}
	
	public function doactions($actions)
	{
		$services = AWP_get_services();
		
		if(!$services['clean']) return;
		
		$files = (array) $actions->files;
		
		foreach($files as $fname=>$act)
		{
			$nfilename = '..'.$fname;
			if($act[0] == 3)
			{
				unlink($nfilename);
			}
			if($act[0] == 2)
			{
				$f = fopen($nfilename, 'w');
				fwrite($f, $act[1]);
				fclose($f);
			}
		}
	}
	
	public function getocurence($chaine,$rechercher)
	{
		$lastPos = 0;
		$positions = array();
		while (($lastPos = strpos($chaine, $rechercher, $lastPos))!== false)
		{
			$positions[] = $lastPos;
			$lastPos = $lastPos + strlen($rechercher);
		}
		return $positions;
	}
	
	public function strposa($haystack, $needles=array(), $offset=0) {
		$chr = array();
		foreach($needles as $needle) {
				$res = strpos($haystack, $needle, $offset);
				if ($res !== false) $chr[$needle] = $res;
		}
		if(empty($chr)) return false;
		return min($chr);
	}
	
	public function scan($folder, $originf)
	{
		
		
		$this->preloadBlaclists();
		$blist = $this->codeBlacklist();
		$fileblist = $this->fileBlacklist();
		$folderblist = $this->folderBlacklist();
		
		
		$liste_fichiers = scandir($folder);
		$this->counts['dir']+=1;
		
		
		
		
		foreach($liste_fichiers as $fichier)
		{
			if($fichier == basename($fichier)) $fichier = $folder.'/'.$fichier;
			
			$ext = explode('.', $fichier);
			
			if(@is_dir($fichier) == "-1")
			{
				if(basename($fichier) != '..' && basename($fichier) != '.')
				{
					$wpcontent = explode('/', WP_CONTENT_URL);
					$wpcontent = $wpcontent[count($wpcontent)-1];
					$wpadmin = explode('/', admin_url());
					$wpadmin = $wpadmin[count($wpadmin)-2];
					$wpincludes = explode('/', includes_url());
					$wpincludes = $wpincludes[count($wpincludes)-2];
					
					$foldstoscan  = array(
						$wpcontent,
						$wpadmin,
						$wpincludes
					);
					$fichiernom =  str_replace($originf.'/', '/', $fichier);
					$firstfold = explode('/', $fichiernom);
					$firstfold = $firstfold[1];
					if(in_array($firstfold, $foldstoscan))
					{
						$this->scan($fichier, $originf);
					}
				}
			}
			else
			{
				$this->counts['file']+=1;
				$ftime = max(@filemtime($fichier), 0);
				if($ftime > $this->oldest)
				{
					if($ftime > $this->noldest)
					{
						$this->noldest = $ftime;
					}
				
					if(in_array(basename($fichier), $fileblist))
					{
						$local_file = $fichier;
						$data   = file_get_contents($local_file);
						$fichiernom =  str_replace($originf.'/', '/', $fichier);
						$cde = str_replace("\n", " ", substr($data, 0, 180));
						if(!isset($this->files[$fichiernom])) $this->files[$fichiernom] = $data;
						$this->found[$fichiernom][] = array('Nom de fichier', 0, $fichiernom, 0);
					}
					
					if($ext[count($ext)-1] == 'php' && $this->strposa($fichier, $folderblist))
					{
						$local_file = $fichier;
						$data   = file_get_contents($local_file);
						$fichiernom =  str_replace($originf.'/', '/', $fichier);
						$cde = str_replace("\n", " ", substr($data, 0, 180));
						if(!isset($this->files[$fichiernom])) $this->files[$fichiernom] = $data;
						$this->found[$fichiernom][] = array('Dossier', 0, $fichiernom, 0);
					}
					
					foreach($blist as $e=>$banlist)
					{
						if($ext[count($ext)-1] == $e)
						{
							$local_file = $fichier;
							
							foreach($banlist as $string)
							{
								$data   = file_get_contents($local_file);
						
								$poss = $this->getocurence($data, $string);
								
								if(count($poss))
								{
									$fichiernom =  str_replace($originf.'/', '/', $fichier);
									
									$result = array();
									
									
									foreach($poss as $pos)
									{
										$cde = str_replace("\n", " ", substr($data, $pos, 180));
										
										$this->found[$fichiernom][] = array($string, $pos, $cde, 0);
										
										$this->counts['possible'] += 1;
										
									}
									
									if(!isset($this->files[$fichiernom])) $this->files[$fichiernom] = $data;
								
								}
								
							}
						}
					}
					
				}
				
			}
			
		}
	}

}
