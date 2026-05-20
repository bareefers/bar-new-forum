<?php

class EWRutiles_Model_SpamLogs extends XenForo_Model
{
	public function getSpamLogById($spamLogID)
	{
		if (!$spamLog = $this->_getDb()->fetchRow("
			SELECT *, spamlog_username AS username, spamlog_email AS email, spamlog_ip AS ip
				FROM EWRutiles_spamlogs
			WHERE spamlog_id = ?
		", $spamLogID))
		{
			return false;
		}

		return $spamLog;
	}
	
	public function getSpamLogs($start, $stop)
	{
		$start = ($start - 1) * $stop;

		if (!$spamLogs = $this->_getDb()->fetchAll("
			SELECT spamlog_id, spamlog_date, spamlog_engine, spamlog_username, spamlog_username_match,
					spamlog_email, spamlog_email_match, spamlog_ip, spamlog_ip_match, spamlog_time,
					NULL as user_id
				FROM EWRutiles_spamlogs
			UNION ALL
			SELECT NULL AS spamlog_id, register_date AS spamlog_date, NULL AS spamlog_engine,
					username AS spamlog_username, NULL AS spamlog_username_match, email AS spamlog_email,
					NULL AS spamlog_email_match, NULL AS spamlog_ip, NULL AS spamlog_ip_match, NULL AS spamlog_time,
					user_id
				FROM xf_user
			ORDER BY spamlog_date DESC
			LIMIT ?, ?
		", array($start, $stop)))
		{
			return false;
		}

		return $spamLogs;
	}

	public function getSpamLogsCount()
	{
        $spams = $this->_getDb()->fetchRow("
			SELECT COUNT(*) AS total
				FROM EWRutiles_spamlogs
		");
		
        $users = $this->_getDb()->fetchRow("
			SELECT COUNT(*) AS total
				FROM xf_user
		");

		return $spams['total'] + $users['total'];
	}
	
	public function insertSpamLog($data, $matches, $engine)
	{
		$dw = XenForo_DataWriter::create('EWRutiles_DataWriter_SpamLogs');
		$dw->bulkSet(array(
			'spamlog_engine' => $engine,
			'spamlog_username' => $data['username'],
			'spamlog_username_match' => !empty($matches['username']) ? 1 : 0,
			'spamlog_email' => $data['email'],
			'spamlog_email_match' => !empty($matches['email']) ? 1 : 0,
			'spamlog_ip' => $data['ip'],
			'spamlog_ip_match' => !empty($matches['ip']) ? 1 : 0,
			'spamlog_time' => $data['time'],
		));
		$dw->save();

		return true;
	}

	public function clearRegistrationLog()
	{
		$this->_getDb()->query('TRUNCATE TABLE EWRutiles_spamlogs');
	}
}