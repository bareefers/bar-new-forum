<?php

	include('config.php');
	
	@ini_set("post_max_size", "1000M");
	@ini_set("upload_max_filesize", "1000M");
	
	mysql_connect( $DB_HOST, $DB_USER, $DB_PASSWORD ) or die (__LINE__.mysql_error());
	mysql_query("SET NAMES " . $DB_CHARSET ) or die (__LINE__.mysql_error());
	mysql_select_db( $DB_NAME ) or die (__LINE__.mysql_error());
	if( $_POST['ppgc_hidden'] == 'imported' ){
		
		$ERROR = false;
		if( $_POST['from_iue_email'] ){ $_from = stripslashes($_POST['from_iue_email']); } else { $ERROR = '"FROM" email address is missed'; }
		if( $_POST['ppgc_iue_subject'] ){ $_subject = stripslashes($_POST['ppgc_iue_subject']); } else { $ERROR = 'Email subject is missed'; }
		if( $_POST['ppgc_iue_text'] ){ $_message_original = stripslashes($_POST['ppgc_iue_text']); } else { $ERROR = 'Email body is empty'; }
		
		if( $ERROR ){
			?>
			<div class="error"><p><strong><?php echo $ERROR; ?></strong></p></div>
			<?php
		}
		else{
			$ppgc_users = mysql_query("SELECT `login`,`email`,`password` FROM `".$DB_PREFIX."ppgc_users` WHERE `note` = '0' ORDER BY `id` ASC");
			$ssstr = '';
			while ( $_u = mysql_fetch_array($ppgc_users) ){
				
				if( !in_array($_u['email'], $_POST['send_to'] )) continue;
				$_message = $_POST['ppgc_iue_text'];
				
				if( trim($_message) == '' ) $_message = $_message_original;
				$_to = trim($_u['email']);
				$_login = $_u['login'];
				$_password = $_u['password'];
				
				$vars = array( '[site-url]'=> $SITE_URL, '[site-title]'=> $SITE_TITLE, '[username]' => $_login, '[password]'=> $_password );
				foreach( $vars as $k => $v ){
					$_message = str_replace( $k, $v, $_message);
					$_subject = str_replace( $k, $v, $_subject);
				}
				$_message = '<html><body>' . preg_replace( '|\r|', "<br />", $_message ) . '</body></html>';
				$_header = '';
				$_header .= 'MIME-Version: 1.0' . "\r\n";
				$_header .= 'Content-type: text/html; charset=utf-8' . "\r\n";
				$_header .= "From: ".str_replace( array( '>', '<', '#' ), '', $SITE_TITLE)."<".$_from.">";
				
				$ssstr .= "\n\rTO:".$_to . "\n\rSUBJECT:" . $_subject . "\n\rMESSAGE:\n\r" . $_message . "\n\r================================";
				
				if( $_POST['ppgc_test_mode'] ){
					$TEST_MODE = "<span style=\"color:#FF0000\">TEST MODE IS ON</span>";
				}
				else{
					$TEST_MODE = "<span style=\"color:#006600\">TEST MODE IS OFF</span>";
					if( trim($_message) == '') {
						$ERROR = 'Email body is empty'; 
					}
					else{
						if( mail($_to, $_subject, $_message , $_header) ){
							mysql_query("UPDATE `".$DB_PREFIX."ppgc_users` SET `note` = '1' WHERE  `email` = '$_to' AND `login` = '$_login'");
							$SEM = "Emails have been sent successfully!";
						}
						else{
							$SEM = "<span style='color:#FF0000'>Your hosting service doesn't support PHP email() function, please contact to your gConverter developer.</span>";
						}
					}
				}
			}
			echo "<div style=\"text-align:center; margin:10px auto; padding:10px;\">";
			echo "<textarea cols=\"60\" rows=\"10\">";
			echo $ssstr;
			echo "</textarea>";
			
			$LEC = mysql_fetch_array(mysql_query("SELECT count(*) as count FROM `".$DB_PREFIX."ppgc_users` WHERE `note` = '0'"));
			if( $LEC[0] ){
				$M = "<br />There is hosting service limitation for the number of sent emails every time the user re-loads the page.<br /> Email Not Received Users: <strong>".$LEC[0]."</strong>. Please click again on the \"Send New Passwords\" button to complete.";
			}
			else{
				$M = "<br /> All users have received new passwords.";
			}
			echo "<p style=\"color:#006600\">
					<strong>".$TEST_MODE."</strong><br />
					<strong>".$SEM."</strong>
					".$M."
				</p></div>";
		
		}
	}
	else{
		if( $_POST['ppgc_hidden'] == 'imported' ){
		?>
		<div class="error"><p><strong><?php echo 'Users from this board had not been imported'; ?></strong></p></div>
		<?php
        }
	}
	
	

?>
<style type="text/css">
.error{
	margin:10px auto;
	color:#FF0000;
	text-align:center;
	width:300px;
}
.ppgc_option_table {
	background-color:#CCCCCC;
}
.ppgc_option_th {
	text-align:left;
	font-weight:100;
	padding:2px;
	width:60%;
}
.ppgc_option_td {
	text-align:left;
	font-weight:100;
	padding:2px;
	width:40%;
}
.ppgc_option_top_th {
	text-align:left;
	font-weight:bold;
	padding:2px;
	width:60%;
}
.ppgc_option_top_td {
	text-align:left;
	font-weight:bold;
	padding:2px;
	width:40%;
}
.ppgc{
	width:800px;
	padding:20px;
	border:outset #006600;
	background:#E6FFE6;
	margin:10px auto;
}
.ppgc .checkbox_list{
	width:400px; 
	height:150px; 
	overflow-x:hidden;
	margin:5px 3px;
	font-size:12px;
	background:#FFFFFF;
	padding-left:5px;
}
.ppgc .checkbox_list li, ul {
	padding:0px;
	list-style:none;
	font-size:13px;
}
.ppgc .checkbox_list li {
	border-bottom:0px;
	margin-bottom:0px;
	margin-top:0px;
}
.ppgc .checkbox_list .sub{
	padding-left:15px;
	margin-top:0px;
}
.inactive{
	color:#999999;
}
</style>

<?php 
if( $_POST['ppgc_hidden'] == 'imported' ){
	$iue_subject = $_POST['ppgc_iue_subject'];
	$iue_text = $_POST['ppgc_iue_text'];
	$from_iue_email = $_POST['from_iue_email'];
}
else{
		$iue_subject = '[site-title] | Your Password has been changed';
		$iue_text = 'Hi [username]!
                        
Here are new log-in data for [site-title]
Username: [username]
Password: [password]

';
		$from_iue_email = '';
}

///////////////////////////////////////////////////////
$_users = mysql_query("SELECT * FROM `".$DB_PREFIX."ppgc_users` ORDER BY `id` ASC");
while ( $_user = mysql_fetch_array( $_users ) ){
	if( $_user['note'] == '1' ) $ppgc_stat['y'] += 1;
	if( $_user['note'] == '0' ) $ppgc_stat['n'] += 1;
	$ppgc_stat['t'] += 1;
	$udata[$_user['login']] = $_user['email'];
	$usent[$_user['login']] = $_user['note'];
}
?>
<script language="javascript">
ppgc_checked = true;
function ppgc_checkedAll ( mode ) {
		var aa= document.getElementById('form_options');
		if (ppgc_checked == false){
			ppgc_checked = true
		}
		else{
			  ppgc_checked = false
		}
		for (var i =0; i < aa.elements.length; i++) {
			if( aa.elements[i].name == 'send_to[]' ){
				aa.elements[i].checked = ppgc_checked;
			}
		}
}
</script>
<div class="ppgc">

<form name="form_options" id="form_options" method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">

<h2 style="color:#333333; font-family:Georgia, 'Times New Roman', Times, serif; font-size:18px;">Users "New Password" Sender Tool | <label for="ppgc_test_mode" style="cursor:pointer; color:#FF0000">TURN ON TEST MODE</label> <input type="checkbox" name="ppgc_test_mode" id="ppgc_test_mode" value="1" /><hr size="1" /></h2>

	
		<input type="hidden" name="ppgc_hidden" value="imported">
		<table style="width:98%;" border="0" cellspacing="1" class="widefat">
			<tr valign="top">
				<th class="ppgc_option_th">
                	
                    <table style="width:100%; border:none;" border="0" cellpadding="0">
                      <tr>
                        <td style="vertical-align:middle; font-size:13px;">From (email address):</td>
                        <td style="border:none;">
                        <input type="hidden" name="ppgc_iue_board" value="forum" />
                        <input type="text" name="from_iue_email" value="<?php echo str_replace('"','&quot;',stripslashes($from_iue_email)) ?>" style="font-size:13px; padding:4px; width:400px; margin:0px;" />
                        </td>
                      </tr>
                      <tr>
                        <td style="vertical-align:middle; font-size:13px;">To user(s):</td>
                        <td style="border:none;">
                        	<div class="checkbox_list" style="height:100px;">
                                <ul>
                                     <?php
                                     $e = 1;
                                     foreach ( $udata as $_login => $_email ) {
									 	if( $e > 500) break;
										if( $usent[$_login] ){
											echo '<li> ----- <label class=\'inactive\'> '.$_login.' &lt;' . $_email . '&gt;</label></li>';
										}
										else{
									 		echo '<li><input type="checkbox" checked="checked" name="send_to[]" id="e-' . $e . '" value="' . $_email . '"> <label for="e-' . $e . '"> '.$_login.' &lt;' . $_email . '&gt;</label></li>';
									 		$e ++;
										}
										
									 }
                                
                                ?>
                                </ul>
                            </div>          
                             <input checked="checked" type="checkbox" id="dgf2" onclick="ppgc_checkedAll('categories')" />
                    		<label for="dgf2" style="font-size:12px; cursor:pointer">Check/Uncheck All</span></label>
                            <br />       <br />     
                        </td>
                      </tr>
                      <tr>
                        <td style="vertical-align:middle; font-size:13px;">Email Subject:</td>
                        <td style="border:none;"><input type="text" name="ppgc_iue_subject" value="<?php echo str_replace('"','&quot;',stripslashes($iue_subject)) ?>" style="font-size:13px; padding:4px; width:400px; margin:0px;" /></td>
                      </tr>
                      <tr>
                        <td style="vertical-align:top; font-size:13px;border:none;">
                        Email Text:<br />
                        <span style="font-size:11px">(HTML Tags are not supported)</span>
                        <br /><br />
                        <span style="font-size:12px;">
                        You can use these shortcodes in email:<br />
                        <strong>[site-title]</strong> - Title of your current site.<br />
                        <strong>[username]</strong> - Username of recipient.<br />
                        <strong>[password]</strong> - Password of recipient.<br />
                        (!) Do not forget to add current Web Site Address.
                        </span>
                        </td>
                        <td style="border:none;">
                        <textarea name="ppgc_iue_text" style="font-size:13px; padding:4px; margin:0px; width:400px; height:130px;"><?php echo stripslashes($iue_text) ?></textarea>
                        </td>
                      </tr>
                      <tr>
                      	<td>&nbsp;</td>
                        <td class="ppgc_option_th">
                                <input type="submit" name="Submit" value="Send New Passwords" style="margin-top:10px; cursor:pointer;" />
                         </td>
                      </tr>
                    </table>

                    
				</th>
			</tr>
            <tr>
				<td class="ppgc_option_th">
                <fieldset style="border:#009933 1px dotted; padding:3px; width:200px; margin-bottom:5px; float:left; margin-right:7px; font-size:12px;">
                <legend style="color:#009900">Noticed Users Stat</legend>
                Email Received Users: <?php echo (int)$ppgc_stat['y'] ?><br />
                Email Not Received Users: <?php echo (int)$ppgc_stat['n'] ?><br />
                Total Converted Users: <?php echo (int)$ppgc_stat['t'] ?>
                </fieldset>
               </td>
			</tr>
            <tr>
				<td style="font-size:12px; color:#666666; text-align:center;">&copy; <?php date('Y') ?> <a href="http://gconverter.com" style="color:#666666; text-decoration:none;">gConverter.com LLC</a> Solutions.</td>
            </tr>
		</table>
	</form>