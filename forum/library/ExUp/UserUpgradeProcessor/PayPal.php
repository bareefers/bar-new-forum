<?php

/**
 * Handles user upgrade processing with PayPal.
 *
 * @package ExUp
 */
class ExUp_UserUpgradeProcessor_PayPal extends XFCP_ExUp_UserUpgradeProcessor_PayPal
{
	/**
	 * Once all conditions are validated, process the transaction.
	 *
	 * @return array [0] => log type (payment, cancel, info), [1] => log message
	 */
	public function processTransaction()
	{
		if ($this->_filtered['payment_status'] == 'Refunded' || $this->_filtered['payment_status'] == 'Reversed')
		{
			if ($this->_upgradeRecord && $this->_upgradeRecord['end_date'] > 0) // not permanent
			{
				$record = $this->_upgradeRecord;
				$record['payment_reversal'] = 1;
				$this->_upgradeRecord = $record;
			}
		}
		
		return parent::processTransaction();
	}
}