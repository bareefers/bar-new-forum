<?php
class DigitalPointAdPositioning_ViewPublic_Thread_View extends XFCP_DigitalPointAdPositioning_ViewPublic_Thread_View
{
	public function renderHtml()
	{
		parent::renderHtml();

		if (strpos($this->_params['unreadLink'], '#post-'))
		{
			$postIdForAd = intval(substr($this->_params['unreadLink'], strpos($this->_params['unreadLink'], '#post-') + 6));
		}
		else
		{
			$postIdForAd = $this->_params['firstPost']['post_id'];
		}

		if (!isset($this->_params['posts'][$postIdForAd]))
		{
			reset($this->_params['posts']);
			$postIdForAd = key($this->_params['posts']);
		}
		
		if (
			XenForo_Application::get('options')->get('dppa_insidepost')
				&&
			(
				in_array($this->_params['thread']['node_id'], XenForo_Application::get('options')->get('dppa_insidepost_forceforums'))
					||
				!XenForo_Model::create('XenForo_Model_User')->isMemberOfUserGroup(XenForo_Visitor::getInstance()->toArray(), XenForo_Application::get('options')->get('dppa_insidepost_hidegroups'))
			)
		)
		{
			// Castig this object to a string, so the __ToString() magic method doesn't get triggered 4 times (each time you do something like substr() on it).
			$html = (string)$this->_params['posts'][$postIdForAd]['messageHtml'];
			preg_match_all("#<br />(" . chr(13) . '|' .  chr(10) . '|' .  chr(13) . chr(10) . ") *?<br />#U", $html, $matches, PREG_OFFSET_CAPTURE);

			$matches[0][] = array (1 => strlen($html));				
			
			$pick = array_rand($matches[0]);
			$offset = $matches[0][$pick][1]; 
			$part1 = substr($html, 0, $offset) . '<br />';
			$part2 = substr($html, $offset + (isset($matches[0][$pick][0]) ? 6 : 0));
	
			
			if ((substr_count($part1, '<div ') - substr_count($part1, '</div>')) % 2 && isset($GLOBALS['dpAds']['adMiddleAlt']))
			{
				$GLOBALS['dpAds']['adMiddle'] = $GLOBALS['dpAds']['adMiddleAlt'];
			}
			
			$this->_params['posts'][$postIdForAd]['messageHtml'] = $part1 . (isset($GLOBALS['dpAds']['adMiddle']) ? $GLOBALS['dpAds']['adMiddle'] : XenForo_Application::get('options')->get('dppa_insidepost_html')) . $part2;
		}

		if (
			XenForo_Application::get('options')->get('dppa_afterpost')
				&&
			!XenForo_Model::create('XenForo_Model_User')->isMemberOfUserGroup(XenForo_Visitor::getInstance()->toArray(), XenForo_Application::get('options')->get('dppa_afterpost_hidegroups'))
		)
		{
			$this->_params['posts'][$postIdForAd]['showAdUnderPost'] = true;
		}
		$GLOBALS['showAdUnderPostCounter'] = 0;
	}
}