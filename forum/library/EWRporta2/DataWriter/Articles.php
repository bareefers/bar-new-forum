<?php

class EWRporta2_DataWriter_Articles extends XenForo_DataWriter
{
	protected $_existingDataErrorPhrase = 'porta2_requested_article_not_found';

	protected function _getFields()
	{
		return array(
			'EWRporta2_articles' => array(
				'thread_id'			=> array('type' => self::TYPE_UINT, 'required' => true),
				'article_date'		=> array('type' => self::TYPE_UINT, 'required' => true),
				'article_title'		=> array('type' => self::TYPE_STRING, 'default' => ''),
				'article_break'		=> array('type' => self::TYPE_STRING, 'default' => ''),
				'article_excerpt'	=> array('type' => self::TYPE_STRING, 'default' => ''),
				'article_icon'		=> array('type' => self::TYPE_SERIALIZED, 'default' => 'a:0:{}'),
				'article_custom'	=> array('type' => self::TYPE_UINT, 'default' => 0, 'verification' => array('$this', '_verifyCustom')),
				'article_options'	=> array('type' => self::TYPE_SERIALIZED, 'default' => 'a:0:{}'),
				'article_exclude'	=> array('type' => self::TYPE_UINT, 'default' => 0),
			)
		);
	}

	protected function _getExistingData($data)
	{
		if (!$threadID = $this->_getExistingPrimaryKey($data, 'thread_id'))
		{
			return false;
		}

		return array('EWRporta2_articles' => $this->getModelFromCache('EWRporta2_Model_Articles')->getArticleByThreadId($threadID));
	}

	protected function _getUpdateCondition($tableName)
	{
		return 'thread_id = ' . $this->_db->quote($this->getExisting('thread_id'));
	}

	protected function _verifyCustom($custom)
	{
		$excerpt = $this->get('article_excerpt');
	
		if ($custom && empty($excerpt))
		{
			$this->error(new XenForo_Phrase('porta2_excerpt_fail'), 'article_excerpt');
			return false;
		}

		return true;
	}
	
	protected function _postDelete()
	{
		$db = $this->_db;
		$db->delete('EWRporta2_catlinks', 'thread_id = ' . $db->quote($this->get('thread_id')));
	}
}