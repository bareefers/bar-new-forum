<?php

class EWRutiles_NewsFeedHandler_Usernames extends XenForo_NewsFeedHandler_Abstract
{
	public function getContentByIds(array $contentIds, $model, array $viewingUser)
	{
		return $model->getModelFromCache('EWRutiles_Model_UserNames')->getUserNamesByIDs($contentIds);
	}
}