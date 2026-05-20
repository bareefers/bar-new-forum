<?php

class EWRporta2_DataWriter_Discussion_Thread extends XFCP_EWRporta2_DataWriter_Discussion_Thread
{
	protected function _discussionPostDelete()
	{
		$response = parent::_discussionPostDelete();

		if ($article = $this->getModelFromCache('EWRporta2_Model_Articles')->getArticleByThreadId($this->get('thread_id')))
		{
			$this->getModelFromCache('EWRporta2_Model_Articles')->deleteArticle($article);
		}

		if ($feature = $this->getModelFromCache('EWRporta2_Model_Features')->getFeatureByThreadId($this->get('thread_id')))
		{
			$this->getModelFromCache('EWRporta2_Model_Features')->deleteFeature($feature);
		}

		return $response;
	}
}