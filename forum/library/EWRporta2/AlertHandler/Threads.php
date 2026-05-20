<?php

class EWRporta2_AlertHandler_Threads extends XenForo_AlertHandler_Abstract
{
	public function getContentByIds(array $contentIds, $model, $userId, array $viewingUser)
	{
		return $model->getModelFromCache('XenForo_Model_Thread')->getThreadsByIds($contentIds);
	}
}