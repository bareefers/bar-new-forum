<?php
/**
* KeyCAPTCHA Plugin for XenoForo v1.0
* Copyright (C) 2011 Mersane, Ltd (www.keycaptcha.com). All rights reserved.
* License GNU/GPL 2.0 (http://www.gnu.org/licenses/gpl-2.0.html)
**/
/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/


if ( !class_exists('KeyCAPTCHA_CLASS') ) {
	class KeyCAPTCHA_CLASS
	{
		var $c_kc_keyword = "accept";
		var $p_kc_visitor_ip;
		var $p_kc_session_id;
		var $p_kc_web_server_sign;
		var $p_kc_web_server_sign2;
		var $p_kc_js_code;
		var $p_kc_private_key;

		function get_web_server_sign($use_visitor_ip = 0)
		{
			return md5($this->p_kc_session_id . (($use_visitor_ip) ? ($this->p_kc_visitor_ip) :("")) . $this->p_kc_private_key);
		}

		function KeyCAPTCHA_CLASS($a_private_key='')
		{
			if ( $a_private_key != '' )
			{
				$kc_pk_u = explode ("0", trim($a_private_key), 2);
				if ( count($kc_pk_u) > 1 )
				{
					$this->p_kc_private_key = $kc_pk_u[0];
					$this->p_kc_js_code = "	<!-- KeyCAPTCHA code (www.keycaptcha.com)-->
								<script type=\"text/javascript\">
									var s_s_c_user_id = '".$kc_pk_u[1]."';
									var s_s_c_session_id = '#KC_SESSION_ID#';
									var s_s_c_captcha_field_id = 'hash';
									var s_s_c_submit_button_id = 'sbutton-#-r';
									var s_s_c_web_server_sign = '#KC_WSIGN#';
									var s_s_c_web_server_sign2 = '#KC_WSIGN2#';
								</script>
								<script type=\"text/javascript\" src=\"http://backs.keycaptcha.com/swfs/cap.js\"></script>
								<!-- end of KeyCAPTCHA code-->";
				}
			}
			$this->p_kc_session_id = uniqid() . '-.2.1.25';
			$this->p_kc_visitor_ip = $_SERVER["REMOTE_ADDR"];
			$this->p_kc_web_server_sign = "";
			$this->p_kc_web_server_sign2 = "";
		}

		function http_get($path)
		{
			$arr = parse_url($path);
			$host = $arr['host'];
			$page = $arr['path'];
			if ( $page=='' ) {
				$page='/';
			}
			if ( isset( $arr['query'] ) ) {
				$page.='?'.$arr['query'];
			}
			$errno = 0;
			$errstr = '';
			$fp = fsockopen ($host, 80, $errno, $errstr, 30);
			if (!$fp){ return ""; }
			$request = "GET $page HTTP/1.0\r\n";
			$request .= "Host: $host\r\n";
			$request .= "Connection: close\r\n";
			$request .= "Cache-Control: no-store, no-cache\r\n";
			$request .= "Pragma: no-cache\r\n";
			$request .= "User-Agent: KeyCAPTCHA\r\n";
			$request .= "\r\n";

			fwrite ($fp,$request);
			$out = '';

			while (!feof($fp)) $out .= fgets($fp, 250);
			fclose($fp);
			$ov = explode("close\r\n\r\n", $out);

			return $ov[1];
		}

		function check_result($response)
		{
			$kc_vars = explode("|", $response);
			if ( count( $kc_vars ) < 4 )
			{
				return false;
			}
			if ($kc_vars[0] == md5($this->c_kc_keyword . $kc_vars[1] . $this->p_kc_private_key . $kc_vars[2]))
			{
				if (strpos(strtolower($kc_vars[2]), "http://") !== 0)
				{
					$kc_current_time = time();
					$kc_var_time = split('[/ :]', $kc_vars[2]);
					$kc_submit_time = gmmktime($kc_var_time[3], $kc_var_time[4], $kc_var_time[5], $kc_var_time[1], $kc_var_time[2], $kc_var_time[0]);
					if (($kc_current_time - $kc_submit_time) < 15)
					{
						return true;
					}
				}
				else
				{
					if ($this->http_get($kc_vars[2]) == "1")
					{
						return true;
					}
				}
			}
			return false;
		}

		function render_js ()
		{
			if ( isset($_SERVER['HTTPS']) && ( $_SERVER['HTTPS'] == 'on' ) )
			{
				$this->p_kc_js_code = str_replace ("http://","https://", $this->p_kc_js_code);
			}
			$this->p_kc_js_code = str_replace ("#KC_SESSION_ID#", $this->p_kc_session_id, $this->p_kc_js_code);
			$this->p_kc_js_code = str_replace ("#KC_WSIGN#", $this->get_web_server_sign(1), $this->p_kc_js_code);
			$this->p_kc_js_code = str_replace ("#KC_WSIGN2#", $this->get_web_server_sign(), $this->p_kc_js_code);
			return $this->p_kc_js_code;
		}
	}
}

if ( !class_exists('XenForo_Captcha_KeyCaptcha') ) {
	class XenForo_Captcha_KeyCaptcha extends XenForo_Captcha_Abstract
	{
	
		public function __construct(array $config = null) {
		}

		public static function KeyCAPTCHA_Install($installedAddon){
			$db = XenForo_Application::get('db');
			$db->query("
					UPDATE	xf_option
					SET	option_value = 0
					WHERE	option_id = 'lostPasswordCaptcha'
                		");
			$db->query("
					CREATE TABLE IF NOT EXISTS kc_backup (
					option_id varchar(50) NOT NULL,
					option_value mediumblob NOT NULL,
					default_value mediumblob NOT NULL,
					edit_format enum('textbox','spinbox','onoff','radio','select','checkbox','template','callback') NOT NULL,
					edit_format_params mediumtext NOT NULL,
					data_type enum('string','integer','numeric','array','boolean','positive_integer','unsigned_integer','unsigned_numeric') NOT NULL,
					sub_options mediumtext NOT NULL,
					can_backup tinyint(3) unsigned NOT NULL,
					validation_class varchar(75) NOT NULL,
					validation_method varchar(50) NOT NULL,
					addon_id varchar(25) NOT NULL default '',
					PRIMARY KEY  (option_id)
					) DEFAULT CHARSET=utf8;
				");
			$db->query("
					INSERT INTO kc_backup (option_id, option_value, default_value, edit_format, edit_format_params, data_type, sub_options, can_backup, validation_class, validation_method, addon_id)
						SELECT	option_id, option_value, default_value, edit_format, edit_format_params, data_type, sub_options, can_backup, validation_class, validation_method, addon_id
						FROM	xf_option
						WHERE	option_id = 'captcha'
				");
			$db->query("
					UPDATE	xf_option
					SET	edit_format = 'radio',
						edit_format_params = '	0={xen:phrase no}
									KeyCaptcha={xen:phrase use_keycaptcha}
									ReCaptcha={xen:phrase use_recaptcha}
									Question={xen:phrase use_question_captcha}'
					WHERE	option_id = 'captcha'
				");
		}

		public static function KeyCAPTCHA_UnInstall($installedAddon){
			$db = XenForo_Application::get('db');
			$db->query("
					UPDATE	xf_option t1
						INNER JOIN kc_backup t2
					SET	t1.option_id = t2.option_id,
						t1.option_value = t2.option_value,
						t1.default_value = t2.default_value,
						t1.edit_format = t2.edit_format,
						t1.edit_format_params = t2.edit_format_params,
						t1.data_type = t2.data_type,
						t1.sub_options = t2.sub_options,
						t1.can_backup = t2.can_backup,
						t1.validation_class = t2.validation_class,
						t1.validation_method = t2.validation_method,
						t1.addon_id = t2.addon_id
					WHERE	t1.option_id = 'captcha'
				");
			$db->query("
					DROP TABLE kc_backup
				");
		}

		public function isValid(array $input) {		
			$options = XenForo_Application::get('options')->getOptions();
		
			if (empty($options['keycaptcha_private_key'])) return false;

			$kc_o = new KeyCAPTCHA_CLASS($options['keycaptcha_private_key']);
			if (isset($input['hash'])) 
				if ($kc_o->check_result($input["hash"])) return true;
			return false;					
		}

		public function renderInternal(XenForo_View $view) {
			$options = XenForo_Application::get('options')->getOptions();
		
			if (empty($options['keycaptcha_private_key'])) return '';			
				
			$kc_o = new KeyCAPTCHA_CLASS($options['keycaptcha_private_key']);

			return $view->createTemplateObject('captcha_keycaptcha', array('keycaptcha' => '<input type="hidden" name="hash" id="hash">'.$kc_o->render_js(),'noscript_code_notice' => new XenForo_Phrase('keycaptcha_noscript_code_notice'),));
		}

	}
}
?>
