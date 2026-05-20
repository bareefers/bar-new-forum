<?php

class DigitalPointAdPositioning_Listener_TemplateHook
{
	public static function insertTemplate($name, &$contents, $params, $template)
	{	
		if ($name === 'ad_message_below')
		{			
			if (isset($GLOBALS['showAdUnderPostCounter']))
			{
				if (isset($GLOBALS['fc']))
				{
					if ($GLOBALS['fc']->route()->getControllerName() === 'XenForo_ControllerPublic_Thread')
					{
						$post = array_slice($template->getParam('posts'), $GLOBALS['showAdUnderPostCounter'], 1);
				
						if (@$post['0']['showAdUnderPost'])
						{
							$contents .= '<div class="underPost" style="padding-top:30px;text-align:center">' . (isset($GLOBALS['dpAds']['adBottom']) ? $GLOBALS['dpAds']['adBottom'] : XenForo_Application::get('options')->get('dppa_afterpost_html')) . '</div>';
							unset($GLOBALS['showAdUnderPostCounter']);
						}
						else
						{
							$GLOBALS['showAdUnderPostCounter']++;
						}
						unset($post);
					}
					elseif (XenForo_Application::get('options')->get('dppa_aftermessage'))
					{
						$messages = $template->getParam('messages');						
						$message = array_slice($messages, $GLOBALS['showAdUnderPostCounter'], 1);

						if (@$message[0]['showAdUnderPost'] || count($messages) == ($GLOBALS['showAdUnderPostCounter'] + 1))
						{
							$contents .= '<div class="underPost" style="padding-top:10px;text-align:center;clear:both">' . (isset($GLOBALS['dpAds']['adMiddle']) ? $GLOBALS['dpAds']['adMiddle'] : XenForo_Application::get('options')->get('dppa_aftermessage_html')) . '</div>';
							unset($GLOBALS['showAdUnderPostCounter']);
						}
						else
						{
							$GLOBALS['showAdUnderPostCounter']++;
						}
						unset($post);
					}
				}
			}
		}
		elseif ($name === 'footer_links' && XenForo_Application::get('options')->get('boardUrl') !== 'http://forums.digitalpoint.com')
		{
			if (isset($GLOBALS['fc']))
			{
				if ($GLOBALS['fc']->route()->getControllerName() === 'XenForo_ControllerPublic_Thread')
				{
					$contents .= '</ul><ul class="footerLinks" style="padding-left:20px;float:none"><li><a href="http://advertising.digitalpoint.com/" target="_blank" style="display:inline-block">Advertising Positioning</a>by Digital Point</li>';
				}
			}
		}
	}
}