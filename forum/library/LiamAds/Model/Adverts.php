<?php

class LiamAds_Model_Adverts extends XenForo_Model
{
	
	public function getAdvertById($id)
	{
		return $this->_getDb()->fetchRow("SELECT * FROM `liamads_adverts` WHERE `advert_id` = $id");
	}
	
	public function getAllAds()
	{
		return $this->fetchAllKeyed('
			SELECT *
			FROM liamads_adverts
			ORDER BY advert_id
		', 'advert_id');
	}
	
}