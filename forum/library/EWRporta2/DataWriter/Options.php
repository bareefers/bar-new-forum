<?php

class EWRporta2_DataWriter_Options extends XenForo_DataWriter
{
	const OPTION_REBUILD_CACHE = 'rebuildCache';
	const OPTION_VALIDATE_VALUE = 'validateValue';
	
	protected $_existingDataErrorPhrase = 'porta2_requested_option_not_found';

	protected function _getFields()
	{
		return array(
			'EWRporta2_options' => array(
				'widget_id'				=> array('type' => self::TYPE_STRING, 'default' => ''),
				'option_id'				=> array('type' => self::TYPE_STRING, 'required' => true, 'verification' => array('$this', '_verifyOptionId')),
				'option_value'			=> array('type' => self::TYPE_UNKNOWN, 'default' => ''),
				'default_value'			=> array('type' => self::TYPE_UNKNOWN, 'default' => ''),
				'title'					=> array('type' => self::TYPE_STRING, 'required' => true, 'default' => ''),
				'explain'				=> array('type' => self::TYPE_STRING, 'default' => ''),
				'edit_format'			=> array('type' => self::TYPE_STRING, 'required' => true,
						'allowedValues' => array('textbox', 'spinbox', 'onoff', 'onofftextbox', 'radio', 'select', 'checkbox', 'template', 'callback')
				),
				'edit_format_params'	=> array('type' => self::TYPE_STRING, 'default' => ''),
				'data_type'				=> array('type' => self::TYPE_STRING, 'required' => true,
						'allowedValues' => array('string', 'integer', 'numeric', 'array', 'boolean', 'positive_integer', 'unsigned_integer', 'unsigned_numeric')
				),
				'sub_options'			=> array('type' => self::TYPE_STRING, 'default' => ''),
				'validation_class'		=> array('type' => self::TYPE_STRING, 'default' => ''),
				'validation_method'		=> array('type' => self::TYPE_STRING, 'default' => ''),
				'display_order'			=> array('type' => self::TYPE_UINT, 'default' => 0),
			)
		);
	}

	protected function _getExistingData($data)
	{
		if (!$optionID = $this->_getExistingPrimaryKey($data, 'option_id'))
		{
			return false;
		}

		return array('EWRporta2_options' => $this->getModelFromCache('EWRporta2_Model_Options')->getOptionById($optionID));
	}

	protected function _getUpdateCondition($tableName)
	{
		return 'option_id = ' . $this->_db->quote($this->getExisting('option_id'));
	}
	
	protected function _getDefaultOptions()
	{
		return array(
			self::OPTION_REBUILD_CACHE => true,
			self::OPTION_VALIDATE_VALUE => true
		);
	}
	
	protected function _verifyOptionId(&$optionID)
	{
		if (preg_match('/[^a-zA-Z0-9_]/', $optionID))
		{
			$this->error(new XenForo_Phrase('please_enter_an_id_using_only_alphanumeric'), 'option_id');
			return false;
		}

		if ($optionID !== $this->getExisting('option_id'))
		{
			if ($this->getModelFromCache('EWRporta2_Model_Options')->getOptionById($optionID))
			{
				$this->error(new XenForo_Phrase('option_ids_must_be_unique'), 'option_id');
				return false;
			}
		}

		return true;
	}
	
	protected function _preSave()
	{
		if ($this->isInsert() && $this->isChanged('default_value') && !$this->isChanged('option_value'))
		{
			$this->set('option_value', $this->get('default_value'));
		}
		
		if ($this->isChanged('validation_class') || $this->isChanged('validation_method'))
		{
			$this->_validateValidationClassAndMethod($this->get('validation_class'), $this->get('validation_method'));
		}

		if ($this->isChanged('edit_format') || $this->isChanged('data_type'))
		{
			$this->_validateDataTypeForEditFormat($this->get('data_type'), $this->get('edit_format'));
		}

		if ($this->get('data_type') == 'array' && $this->get('sub_options') === '')
		{
			$this->error(new XenForo_Phrase('please_enter_list_of_sub_options_for_this_array'), 'sub_options');
		}

		if ($this->isChanged('data_type') && $this->get('data_type') !== 'array')
		{
			$this->set('sub_options', '');
		}

		if ($this->isChanged('option_value') && $this->getOption(self::OPTION_VALIDATE_VALUE))
		{
			$optionValue = $this->_validateOptionValuePreSave($this->get('option_value'));
			if ($optionValue === false)
			{
				$this->error(new XenForo_Phrase('please_enter_valid_value_for_this_option'), $this->get('option_id'), false);
			}
			else
			{
				$this->_setInternal('EWRporta2_options', 'option_value', $optionValue);
			}
		}
	}
	
	protected function _validateValidationClassAndMethod($class, $method)
	{
		if ($class && (!XenForo_Application::autoload($class) || !method_exists($class, $method)))
		{
			$this->error(new XenForo_Phrase('callback_class_x_for_option_y_is_not_valid',
				array('option' => $this->get('option_id'), 'class' => $class)), 'validation');
			return false;
		}

		return true;
	}
	
	protected function _validateDataTypeForEditFormat($dataType, $editFormat)
	{
		switch ($editFormat)
		{
			case 'callback':
			case 'template':
				// these can be anything
				break;

			case 'checkbox':
			case 'onofftextbox':
				if ($dataType != 'array')
				{
					$this->error(new XenForo_Phrase('please_select_data_type_array_if_you_want_to_allow_multiple_selections'), 'data_type');
					return false;
				}
				break;

			case 'textbox':
			case 'spinbox':
			case 'onoff':
			case 'radio':
			case 'select':
				if ($dataType == 'array')
				{
					$this->error(new XenForo_Phrase('please_select_data_type_other_than_array_if_you_want_to_allow_single'), 'data_type');
					return false;
				}
				break;
		}

		return true;
	}
	
	protected function _validateOptionValuePreSave($optionValue)
	{
		switch ($this->get('data_type'))
		{
			case 'string':  $optionValue = strval($optionValue); break;
			case 'integer': $optionValue = intval($optionValue); break;
			case 'numeric': $optionValue = strval($optionValue) + 0; break;
			case 'boolean': $optionValue = ($optionValue ? 1 : 0); break;

			case 'array':
				if (!is_array($optionValue))
				{
					$unserialized = @unserialize($optionValue);
					if (is_array($unserialized))
					{
						$optionValue = $unserialized;
					}
					else
					{
						$optionValue = array();
					}
				}
				break;

			case 'unsigned_integer':
				$optionValue = max(0, intval($optionValue));
				break;

			case 'unsigned_numeric':
				$optionValue = max(0, (strval($optionValue) + 0));
				break;

			case 'positive_integer':
				$optionValue = max(1, intval($optionValue));
				break;
		}

		$validationClass = $this->get('validation_class');
		$validationMethod = $this->get('validation_method');

		if ($validationClass && $validationMethod && $this->_validateValidationClassAndMethod($validationClass, $validationMethod))
		{
			$success = (boolean)call_user_func_array(
				array($validationClass, $validationMethod),
				array(&$optionValue, $this, $this->get('option_id'))
			);
			if (!$success)
			{
				return false;
			}
		}

		if (is_array($optionValue))
		{
			if ($this->get('data_type') != 'array')
			{
				$this->error(new XenForo_Phrase('only_array_data_types_may_be_represented_as_array_values'), 'data_type');
			}
			else
			{
				$subOptions = preg_split('/(\r\n|\n|\r)+/', trim($this->get('sub_options')), -1, PREG_SPLIT_NO_EMPTY);
				$newOptionValue = array();
				$allowAny = false;

				foreach ($subOptions AS $subOption)
				{
					if ($subOption == '*')
					{
						$allowAny = true;
					}
					else if (!isset($optionValue[$subOption]))
					{
						$newOptionValue[$subOption] = false;
					}
					else
					{
						$newOptionValue[$subOption] = $optionValue[$subOption];
						unset($optionValue[$subOption]);
					}
				}

				if ($allowAny)
				{
					// allow any keys, so bring all the remaining ones over
					$newOptionValue += $optionValue;
				}
				else if (count($optionValue) > 0)
				{
					$this->error(new XenForo_Phrase('following_sub_options_unknown_x', array('subOptions' => implode(', ', array_keys($optionValue)))), 'sub_options');
				}

				$optionValue = $newOptionValue;
			}

			$optionValue = serialize($optionValue);
		}

		return strval($optionValue);
	}
	
	protected function _postSave()
	{
		if ($this->getOption(self::OPTION_REBUILD_CACHE))
		{
			$this->getModelFromCache('EWRporta2_Model_Options')->rebuildOptionCache($this->get('widget_id'));
		}
	}
	
	protected function _postDelete()
	{
		if ($this->getOption(self::OPTION_REBUILD_CACHE))
		{
			$this->getModelFromCache('EWRporta2_Model_Options')->rebuildOptionCache($this->get('widget_id'));
		}
	}
	
	public function testValidation($value)
	{
		$optionValue = $this->_validateOptionValuePreSave($value);
		
		if ($optionValue === false)
		{
			$this->error(new XenForo_Phrase('please_enter_valid_value_for_this_option'), $this->get('option_id'), false);
		}
		
		return $optionValue;
	}
}