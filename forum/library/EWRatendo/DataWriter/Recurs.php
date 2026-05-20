<?php

class EWRatendo_DataWriter_Recurs extends XenForo_DataWriter
{
	protected $_existingDataErrorPhrase = 'requested_page_not_found';

	protected function _getFields()
	{
		return array(
			'EWRatendo_recurs' => array(
				'event_id'			=> array('type' => self::TYPE_UINT, 'autoIncrement' => true),
				'event_strtime'		=> array('type' => self::TYPE_UINT, 'required' => true),
				'event_endtime'		=> array('type' => self::TYPE_UINT, 'required' => true),
			)
		);
	}
}