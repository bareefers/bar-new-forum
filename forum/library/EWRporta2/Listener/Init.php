<?php

class EWRporta2_Listener_Init
{
	public static function listen(XenForo_Dependencies_Abstract $dependencies, array $data)
	{
        XenForo_Template_Helper_Core::$helperCallbacks['author_image'] = array('EWRporta2_Template_Helper', 'getAuthorImage');
        XenForo_Template_Helper_Core::$helperCallbacks['feature_image'] = array('EWRporta2_Template_Helper', 'getFeatureImage');
	}
}