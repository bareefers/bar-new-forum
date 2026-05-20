<?php

class EWRporta2_DataWriter_Widgets extends XenForo_DataWriter
{
	protected $_existingDataErrorPhrase = 'porta2_requested_widget_not_found';

	protected function _getFields()
	{
		return array(
			'EWRporta2_widgets' => array(
				'widget_id'					=> array('type' => self::TYPE_STRING, 'required' => true, 'verification' => array('$this', '_verifyWidgetId')),
				'widget_title'				=> array('type' => self::TYPE_STRING, 'required' => true),
				'widget_desc'				=> array('type' => self::TYPE_STRING, 'required' => false),
				'widget_string'				=> array('type' => self::TYPE_STRING, 'default' => ''),
				'widget_version'			=> array('type' => self::TYPE_UINT, 'default' => 0),
				'widget_install_class'		=> array('type' => self::TYPE_STRING, 'default' => ''),
				'widget_install_method'		=> array('type' => self::TYPE_STRING, 'default' => ''),
				'widget_uninstall_class'	=> array('type' => self::TYPE_STRING, 'default' => ''),
				'widget_uninstall_method'	=> array('type' => self::TYPE_STRING, 'default' => ''),
				'widget_url'				=> array('type' => self::TYPE_STRING, 'default' => ''),
				'widget_values'				=> array('type' => self::TYPE_SERIALIZED, 'default' => 'a:0:{}'),
				'locked'					=> array('type' => self::TYPE_BOOLEAN, 'default' => 0),
				'display'					=> array('type' => self::TYPE_STRING, 'required' => true, 'default' => 'show',
						'allowedValues' => array('show', 'hide')
				),
				'groups'					=> array('type' => self::TYPE_STRING, 'default' => ''),
				'ctime'						=> array('type' => self::TYPE_STRING, 'default' => ''),
				'cdate'						=> array('type' => self::TYPE_UINT, 'default' => 0),
				'cache'						=> array('type' => self::TYPE_SERIALIZED, 'default' => 'a:0:{}'),
				'active'					=> array('type' => self::TYPE_BOOLEAN, 'default' => 1)
			)
		);
	}

	protected function _getExistingData($data)
	{
		if (!$widgetID = $this->_getExistingPrimaryKey($data, 'widget_id'))
		{
			return false;
		}

		return array('EWRporta2_widgets' => $this->getModelFromCache('EWRporta2_Model_Widgets')->getWidgetById($widgetID));
	}

	protected function _getUpdateCondition($tableName)
	{
		return 'widget_id = ' . $this->_db->quote($this->getExisting('widget_id'));
	}
	
	protected function _verifyWidgetId(&$widgetID)
	{
		if (preg_match('/[^a-zA-Z0-9_]/', $widgetID))
		{
			$this->error(new XenForo_Phrase('please_enter_an_id_using_only_alphanumeric'), 'widget_id');
			return false;
		}

		if ($this->isInsert() || $widgetID != $this->getExisting('widget_id'))
		{
			if ($this->getModelFromCache('EWRporta2_Model_Widgets')->getWidgetById($widgetID))
			{
				$this->error(new XenForo_Phrase('porta2_widget_ids_must_be_unique'), 'widget_id');
				return false;
			}
		}

		return true;
	}
	
	protected function _preSave()
	{
		if ($this->get('widget_install_class') || $this->get('widget_install_method'))
		{
			$class = $this->get('widget_install_class');
			$method = $this->get('widget_install_method');

			if (!XenForo_Helper_Php::validateCallbackPhrased($class, $method, $errorPhrase))
			{
				$this->error($errorPhrase, 'widget_install_method');
			}
		}

		if ($this->get('widget_uninstall_class') || $this->get('widget_uninstall_method'))
		{
			$class = $this->get('widget_uninstall_class');
			$method = $this->get('widget_uninstall_method');

			if (!XenForo_Helper_Php::validateCallbackPhrased($class, $method, $errorPhrase))
			{
				$this->error($errorPhrase, 'widget_uninstall_method');
			}
		}
	}
	
	protected function _postSave()
	{
		if ($this->isUpdate() && $this->isChanged('widget_id'))
		{
			$db = $this->_db;
			$updateClause = 'widget_id = ' . $db->quote($this->getExisting('widget_id'));
			$updateValue = array('widget_id' => $this->get('widget_id'));

			$db->update('EWRporta2_options', $updateValue, $updateClause);
			$db->update('EWRporta2_widlinks', $updateValue, $updateClause);
			$db->update('EWRporta2_widopts', $updateValue, $updateClause);
		}
	}
	
	protected function _preDelete()
	{
		if ($this->get('widget_uninstall_class') && $this->get('widget_uninstall_method'))
		{
			$class = $this->get('widget_uninstall_class');
			$method = $this->get('widget_uninstall_method');

			if (!XenForo_Application::autoload($class) || !method_exists($class, $method))
			{
				$this->error(new XenForo_Phrase('porta2_files_necessary_uninstallation_widget_not_found'));
			}
		}
	}
	
	protected function _postDelete()
	{
		if ($this->get('widget_uninstall_class') && $this->get('widget_uninstall_method'))
		{
			call_user_func(
				array($this->get('widget_uninstall_class'), $this->get('widget_uninstall_method')),
				$this->getMergedData()
			);
		}

		$db = $this->_db;
		$db->delete('EWRporta2_options', 'widget_id = ' . $db->quote($this->get('widget_id')));
		$db->delete('EWRporta2_widlinks', 'widget_id = ' . $db->quote($this->get('widget_id')));
		$db->delete('EWRporta2_widopts', 'widget_id = ' . $db->quote($this->get('widget_id')));
		
		$db->delete('xf_code_event_listener', 'description LIKE ' . $db->quote('EWRwidget_'.$this->get('widget_id').'%'));
		$db->delete('xf_phrase', 'language_id = 0 AND title LIKE ' . $db->quote('EWRwidget_'.$this->get('widget_id').'%'));
		$db->delete('xf_template', 'style_id = 0 AND title LIKE ' . $db->quote('EWRwidget_'.$this->get('widget_id').'%'));
	}
}