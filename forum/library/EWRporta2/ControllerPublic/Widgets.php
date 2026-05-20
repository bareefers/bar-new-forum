<?php

class EWRporta2_ControllerPublic_Widgets extends XenForo_ControllerPublic_Abstract
{
	public function actionIndex()
	{
		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT,
			XenForo_Link::buildPublicLink('articles')
		);
	}

	public static function getSessionActivityDetailsForList(array $activities)
	{
        $output = array();
        foreach ($activities as $key => $activity)
		{
			$output[$key] = new XenForo_Phrase('porta2_viewing_widget_extension');
        }

        return $output;
	}
}