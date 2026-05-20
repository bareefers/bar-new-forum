<?php

/**
 * Cron entry for alerting and/or emailing users who's upgrades are about to expire
 *
 * @package ExUp
 */
class ExUp_CronEntry_ExpiringUpgrades
{
	/**
	 * Alert expiring user upgrades.
	 */
	public static function notifyExpiringUpgrades()
	{
		$upgradeModel = XenForo_Model::create('XenForo_Model_UserUpgrade');
		
		$upgradeModel->notifyExpiringUpgrades();
	}
}