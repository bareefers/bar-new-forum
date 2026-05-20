<?php

class LiamAds_DataWriter_Adverts extends XenForo_DataWriter
{

	/**
	 * Gets the fields that are defined for the table. See parent for explanation.
	 *
	 * @return array
	 */
	protected function _getFields()
	{
		return array(
				'liamads_adverts' => array(
						'advert_id'   => array('type' => self::TYPE_UINT, 'autoIncrement' => true),
						'advert_name' => array('type' => self::TYPE_STRING, 'required' => true, 'requiredError' => 'liamads_name_required', 'verification' => array('$this', '_verifyName')),
						'advert_code' => array('type' => self::TYPE_UNKNOWN, 'required' => true, 'requiredError' => 'liamads_code_required'),
						'user_criteria' => array('type' => self::TYPE_UNKNOWN, 'required' => true,
								'verification' => array('$this', '_verifyCriteria')
						),
						'page_criteria' => array('type' => self::TYPE_UNKNOWN, 'required' => true, 'verification' => array('$this', '_verifyCriteria')),
						'mass_click' => array('type' => self::TYPE_BOOLEAN, 'required' => true),
						'advert_location' => array('type' => self::TYPE_STRING, 'required' => true, 'requiredError' => 'liamads_location_required')
				)
		);
	}

	/**
	 * Gets the actual existing data out of data that was passed in. See parent for explanation.
	 *
	 * @param mixed
	 *
	 * @return array|false
	 */
	protected function _getExistingData($data)
	{
		if (!$id = $this->_getExistingPrimaryKey($data))
		{
			return false;
		}

		return array('liamads_adverts' => $this->_getAdsModel()->getAdvertById($id));
	}

	/**
	 * Gets SQL condition to update the existing record.
	 *
	 * @return string
	 */
	protected function _getUpdateCondition($tableName)
	{
		return 'advert_id = ' . $this->_db->quote($this->getExisting('advert_id'));
	}

	/**
	 * Verifies that the criteria is valid and formats is correctly.
	 * Expected input format: [] with children: [rule] => name, [data] => info
	 *
	 * @param array|string $criteria Criteria array or serialize string; see above for format. Modified by ref.
	 *
	 * @return boolean
	 */
	protected function _verifyCriteria(&$criteria)
	{
		$criteriaFiltered = XenForo_Helper_Criteria::prepareCriteriaForSave($criteria);
		$criteria = serialize($criteriaFiltered);
		return true;
	}

	protected function _verifyName($value)
	{

		if (preg_match('/^[a-zA-Z0-9_]*$/', $value) === 1)
		{
			return true;
		}
		else
		{
			$this->_errors[] = new XenForo_Phrase('liamads_invalid_name');
			return false;
		}
	}



	/**
	 * @return LiamAds_Model_Adverts
	 */
	protected function _getAdsModel()
	{
		return $this->getModelFromCache('LiamAds_Model_Adverts');
	}

}