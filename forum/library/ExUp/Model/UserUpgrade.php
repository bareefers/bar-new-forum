<?php

/**
 * Model for user upgrades.
 *
 * @package ExUp
 */
class ExUp_Model_UserUpgrade extends XFCP_ExUp_Model_UserUpgrade
{
	/**
	 * Alerts and/or emails user upon expiring/expired/reversed upgrades
	 *
	 * @param array $record user upgrade record
	 */
	protected function _notifyUser(array $record, $action)
	{
		if (empty($record) || ($action != 'purchased_upgrade' && $action != 'expiring_upgrade' && $action != 'expiring_subscription' && $action != 'expired_upgrade' && $action != 'upgrade_payment_reversal'))
		{
			return;
		}
		
		$options = XenForo_Application::get('options');
		$notifyBy = $options->exup_notify_by;
		
		$params = array(
			'username' => $record['username'],
			'upgrade_title' => $record['title']
		);
		
		if ($action == 'expiring_upgrade' || $action == 'expiring_subscription')
		{
			$numDays = $options->exup_days_remaining;
			$cutOff = XenForo_Application::$time + (86400 * $numDays);
			$numDays = ceil($numDays - (($cutOff - $record['end_date']) / 86400));
			if ($numDays < 0)
				$numDays = 0;
			$params['num_days'] = $numDays;
		}
		
		if ($notifyBy == 'alert' || $notifyBy == 'alert_and_email')
		{
			XenForo_Model_Alert::alert(
				$record['user_id'],
				$record['user_id'],
				$record['username'],
				'exup',
				$record['user_id'],
				$action,
				$params
			);
		}
		
		if ($notifyBy == 'email' || $notifyBy == 'alert_and_email')
		{
			// has email, is valid, and not banned
			if ($record['email'] && $record['user_state'] == 'valid' && !$record['is_banned'])
			{
				if (!$options->exup_respect_email_privacy || $record['receive_admin_email'])
				{
					$params['boardUrl'] = $options->boardUrl;
					$params['boardTitle'] = $options->boardTitle;
					$templateName = 'exup_email_' . $action;
					
					$mail = XenForo_Mail::create($templateName, $params, $record['language_id']);
					$mail->enableAllLanguagePreCache();
					$mail->queue($record['email'], $record['username']);
				}
			}		
		}
	}
	
	/**
	 * Downgrades the specified user upgrade records.
	 *
	 * @param array $upgrades List of user upgrade records to downgrade
	 */
	public function downgradeUserUpgrades(array $upgrades)
	{
		$options = XenForo_Application::get('options');
		
		if (!empty($upgrades) && $options->exup_notify_expired)
		{
			$upgradeRecordIds = array();
			
			foreach ($upgrades AS $upgrade)
			{
				$upgradeRecordIds[] = $upgrade['user_upgrade_record_id'];
			}
			
			// fetch the full records before parent deletes them
			$records = $this->getUserUpgradeActiveByIds($upgradeRecordIds);
			
			// ensure all went OK
			parent::downgradeUserUpgrades($upgrades);
			
			if (!empty($records))
			{
				foreach ($records AS $record)
				{
					$this->_notifyUser($record, 'expired_upgrade');
				}
			}
		}
		else
		{
			parent::downgradeUserUpgrades($upgrades);
		}
	}
	
	/**
	 * Upgrades the user with the specified upgrade.
	 *
	 * @param integer $userId
	 * @param array $upgrade Info about upgrade to apply
	 * @param boolean $allowInsertUnpurchasable Allow insert of a new upgrade even if not purchasable
	 * @param integer|null $endDate Forces a specific end date; if null, don't overwrite
	 *
	 * @return integer|false User upgrade record ID
	 */
	public function upgradeUser($userId, array $upgrade, $allowInsertUnpurchasable = false, $endDate = null)
	{
		$notifyUponPurchase = XenForo_Application::get('options')->exup_notify_purchase;
		
		if ($notifyUponPurchase) // fetch addition info
			$active = $this->getUserUpgradeActiveRecord($userId, $upgrade['user_upgrade_id']);
		else
			$active = $this->getActiveUserUpgradeRecord($userId, $upgrade['user_upgrade_id']);
		
		$recordId = parent::upgradeUser($userId, $upgrade, $allowInsertUnpurchasable, $endDate);
		
		if ($recordId)
		{
			// reset the notified flag
			$params = array('notified_date' => 0);
			
			// not an extension or subscription renewal
			if (empty($active))
			{
				if ($notifyUponPurchase) // fetch addition info
					$active = $this->getUserUpgradeActiveById($recordId);
				else
					$active = $this->getActiveUserUpgradeRecordById($recordId);
				
				// save the current user groups and recurring so we can later know if the upgrade details have changed.
				// we use this in order to know whether to display the 'Extend Upgrade' button.
				$activeExtra = unserialize($active['extra']);
				$activeExtra['extra_group_ids'] = $upgrade['extra_group_ids'];
				$activeExtra['recurring'] = $upgrade['recurring'];
				$params['extra'] = serialize($activeExtra);
			}
			
			$db = $this->_getDb();
			$db->update('xf_user_upgrade_active',
				$params,
				'user_upgrade_record_id = ' . $db->quote($recordId)
			);
			
			if ($notifyUponPurchase)
				$this->_notifyUser($active, 'purchased_upgrade');
		}
		
		return $recordId;
	}
	
	/**
	 * Gets the specified active user upgrade record, based on user and upgrade.
	 *
	 * @param integer $userId
	 * @param integer $upgradeId
	 *
	 * @return array|false
	 */
	public function getUserUpgradeActiveRecord($userId, $upgradeId)
	{
		return $this->_getDb()->fetchRow('
			SELECT active.*, upgrade.title, upgrade.recurring, user.*, IF(user_option.receive_admin_email IS NULL, 0, user_option.receive_admin_email) AS receive_admin_email
			FROM xf_user_upgrade_active AS active
			INNER JOIN xf_user_upgrade AS upgrade ON (upgrade.user_upgrade_id = active.user_upgrade_id)
			INNER JOIN xf_user AS user ON (user.user_id = active.user_id)
			LEFT JOIN xf_user_option AS user_option ON (user_option.user_id = user.user_id)
			WHERE active.user_id = ? AND active.user_upgrade_id = ?
		', array($userId, $upgradeId));
	}
	
	/**
	 * Gets the specified upgrade.
	 *
	 * @param integer $upgradeRecordId
	 *
	 * @return array|false
	 */
	public function getUserUpgradeActiveById($upgradeRecordId)
	{
		return $this->_getDb()->fetchRow('
			SELECT active.*, upgrade.title, upgrade.recurring, user.*, IF(user_option.receive_admin_email IS NULL, 0, user_option.receive_admin_email) AS receive_admin_email
			FROM xf_user_upgrade_active AS active
			INNER JOIN xf_user_upgrade AS upgrade ON (upgrade.user_upgrade_id = active.user_upgrade_id)
			INNER JOIN xf_user AS user ON (user.user_id = active.user_id)
			LEFT JOIN xf_user_option AS user_option ON (user_option.user_id = user.user_id)
			WHERE active.user_upgrade_record_id = ?
		', $upgradeRecordId);
	}
	
	/**
	 * Gets the specified upgrade.
	 *
	 * @param array $upgradeRecordIds
	 *
	 * @return array|false
	 */
	public function getUserUpgradeActiveByIds(array $upgradeRecordIds)
	{
		return $this->fetchAllKeyed('
			SELECT active.*, upgrade.title, upgrade.recurring, user.*, IF(user_option.receive_admin_email IS NULL, 0, user_option.receive_admin_email) AS receive_admin_email
			FROM xf_user_upgrade_active AS active
			INNER JOIN xf_user_upgrade AS upgrade ON (upgrade.user_upgrade_id = active.user_upgrade_id)
			INNER JOIN xf_user AS user ON (user.user_id = active.user_id)
			LEFT JOIN xf_user_option AS user_option ON (user_option.user_id = user.user_id)
			WHERE active.user_upgrade_record_id IN (' . $this->_getDb()->quote($upgradeRecordIds) . ') 
		', 'active.user_upgrade_record_id');
	}
	
	/**
	 * Get all user upgrades that are about to expire but are still listed as active.
	 *
	 * @return array [upgrade record id] => info
	 */
	public function getExpiringUserUpgrades($cutOff)
	{
		$includeRecurring = XenForo_Application::get('options')->exup_days_remaining_subscriptions;
		
		return $this->fetchAllKeyed('
			SELECT active.*, upgrade.title, upgrade.recurring, user.*, IF(user_option.receive_admin_email IS NULL, 0, user_option.receive_admin_email) AS receive_admin_email
			FROM xf_user_upgrade_active AS active
			INNER JOIN xf_user_upgrade AS upgrade ON (upgrade.user_upgrade_id = active.user_upgrade_id)
			INNER JOIN xf_user AS user ON (user.user_id = active.user_id)
			LEFT JOIN xf_user_option AS user_option ON (user_option.user_id = user.user_id)
			WHERE active.end_date > 0 AND active.end_date < ? AND active.notified_date = 0' . ($includeRecurring ? '' : ' AND upgrade.recurring = 0') . '
		', 'active.user_upgrade_record_id', $cutOff);
	}
	
	/**
	 * Downgrades the specified user upgrade records.
	 *
	 */
	public function notifyExpiringUpgrades()
	{
		$timeNow = XenForo_Application::$time;
		$options = XenForo_Application::get('options');
		$notifyBy = $options->exup_notify_by;
		$numDays = $options->exup_days_remaining;
		$cutOff = $timeNow + (86400 * $numDays); 
		
		$upgrades = $this->getExpiringUserUpgrades($cutOff);
		
		foreach ($upgrades AS $upgrade)
		{
			// mark upgrade as being notified
			$db = $this->_getDb();
			
			$db->update('xf_user_upgrade_active',
				array('notified_date' => $timeNow),
				'user_upgrade_record_id = ' . $db->quote($upgrade['user_upgrade_record_id'])
			);
			
			$this->_notifyUser($upgrade, ($upgrade['recurring'] ? 'expiring_subscription' : 'expiring_upgrade'));
		}
	}
	
	/**
	 * Prepares a list of user upgrade record conditions.
	 *
	 * @param array $conditions
	 * @param string $baseTable Base table to query against
	 * @param array $fetchOptions
	 *
	 * @return string
	 */
	public function prepareUserUpgradeRecordConditions(array $conditions, $baseTable, array &$fetchOptions)
	{
		$sqlConditions = parent::prepareUserUpgradeRecordConditions($conditions, $baseTable, $fetchOptions);
		
		// searching expired list by user name
		if (isset($conditions['user_id']))
		{
			$sqlConditions .= ' AND ' . $baseTable . '.user_id = ' . $this->_getDb()->quote($conditions['user_id']);
		}
		
		return $sqlConditions;
	}
	
	/**
	 * Downgrades the specified user upgrade record while keeping any extended time intact.
	 *
	 * @param array $upgrade the upgrade record
	 */
	public function downgradeUserUpgradeUponPaymentReversal(array $upgrade)
	{
		if (empty($upgrade) || $upgrade['end_date'] == 0)
		{
			return;
		}
		
		// check if end date is longer than start date plus upgrade length.
		// if so, adjust end date accordingly and do not downgrade
		$upgradeExtra = unserialize($upgrade['extra']);
		$extendedStartDate = strtotime('-' . $upgradeExtra['length_amount'] . ' ' . $upgradeExtra['length_unit'], $upgrade['end_date']);
		
		if ($extendedStartDate > $upgrade['start_date'])
		{
			$db = $this->_getDb();
			
			// adjust end date and reset notified flag
			$db->update('xf_user_upgrade_active',
				array('end_date' => $extendedStartDate, 'notified_date' => 0),
				'user_upgrade_record_id = ' . $db->quote($upgrade['user_upgrade_record_id'])
			);
			
			$this->_notifyUser($upgrade, 'upgrade_payment_reversal');
		}
		else
		{
			$this->downgradeUserUpgrades(array($upgrade));
		}
	}
	
	/**
	 * Downgrades the specified user upgrade record.
	 *
	 * @param array $upgrade
	 */
	public function downgradeUserUpgrade(array $upgrade)
	{
		if (isset($upgrade['payment_reversal']))
		{
			// fetch complete upgrade and user info
			$record = $this->getUserUpgradeActiveById($upgrade['user_upgrade_record_id']);
			if (!empty($record) && $record['end_date'] > 0) // not permanent
			{
				$doReversalDowngarde = true;
				
				// find out if upgrade was changed to recurring after purchase
				if ($record['recurring'])
				{
					$recordExtra = unserialize($record['extra']);
					
					// was recurring when originally purchased
					if (!isset($recordExtra['recurring']) || $recordExtra['recurring'])
					{
						$doReversalDowngarde = false;
					}
				}
				
				if ($doReversalDowngarde)
				{
					$this->downgradeUserUpgradeUponPaymentReversal($record);
					return;
				}
			}
		}
		
		parent::downgradeUserUpgrade($upgrade);
	}
	
	/**
	 * Gets a list of upgrades that are applicable to the specified user.
	 *
	 * @param array|null $viewingUser
	 *
	 * @return array
	 * 		[available] -> list of upgrades that can be purchased,
	 * 		[purchased] -> list of purchased, with [record] key inside for specific info
	 */
	public function getUserUpgradesForPurchaseList(array $viewingUser = null)
	{
		$lists = parent::getUserUpgradesForPurchaseList($viewingUser);
		
		if (XenForo_Application::get('options')->exup_extend_upgrade_button)
		{
			// find out if to display the "Extend Upgrade" button
			// upgrade details must remain the same in case payment is reversed/refunded
			foreach ($lists['purchased'] AS &$purchased)
			{
				// can purchase, not permanent, and not subscription
				if ($purchased['can_purchase'] && $purchased['length_unit'] && !$purchased['recurring'])
				{
					$recordExtra = unserialize($purchased['record']['extra']);
					
					// was not a recurring subscription when originally purchased
					if (isset($recordExtra['recurring']) && $purchased['recurring'] == $recordExtra['recurring'])
					{
						// user groups have not changed
						if (isset($recordExtra['extra_group_ids']) && $purchased['extra_group_ids'] == $recordExtra['extra_group_ids'])
						{
							// length and cost details are still the same
							if ($purchased['cost_amount'] == $recordExtra['cost_amount'] && 
								$purchased['cost_currency'] == $recordExtra['cost_currency'] &&
								$purchased['length_amount'] == $recordExtra['length_amount'] &&
								$purchased['length_unit'] == $recordExtra['length_unit'])
							{
								$purchased['can_extend'] = true;
							}
						}
					}
				}
			}
		}
		
		return $lists;
	}
}