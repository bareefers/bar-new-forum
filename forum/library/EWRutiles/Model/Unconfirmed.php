<?php

class EWRutiles_Model_Unconfirmed extends XenForo_Model
{
	public function deleteUsers()
	{
		$options = XenForo_Application::get('options');
		$today = XenForo_Application::$time;

		$cutoff['end'] = $options->EWRutiles_unconfirmed_cutoff;
		$cutoff['two'] = floor($cutoff['end'] * (2/3));
		$cutoff['one'] = floor($cutoff['end'] * (1/3));

		$users = $this->_getDb()->fetchAll("SELECT * FROM xf_user WHERE user_state = 'email_confirm' && message_count = '0'");
		$unconfirmed = array('one' => array(), 'two' => array(), 'end' => array(), 'non' => array());

		foreach ($users AS &$user)
		{
			$user['days_since'] = floor(($today - $user['register_date']) / (60 * 60 * 24));
			$user['days_left'] = $cutoff['end'] - $user['days_since'];
			if ($user['days_left'] < 0) { $user['days_left'] = 0; }

			switch ($user['days_left'])
			{
				case $cutoff['one']:
					$unconfirmed['one'][] = $user['username']." (".$user['days_left'].")";
					if ($options->EWRutiles_unconfirmed_email)
					{
						$this->emailUser($user, $options->boardTitle);
					}
					break;
				case $cutoff['two']:
					$unconfirmed['two'][] = $user['username']." (".$user['days_left'].")";
					if ($options->EWRutiles_unconfirmed_email)
					{
						$this->emailUser($user, $options->boardTitle);
					}
					break;
				case 0:
					$unconfirmed['end'][] = $user['username']." (".$user['days_left'].")";
					$this->deleteUser($user);
					break;
				default:
					$unconfirmed['non'][] = $user['username']." (".$user['days_left'].")";
			}
		}

		$unconfirmed['one'] = implode(', ', $unconfirmed['one']);
		$unconfirmed['two'] = implode(', ', $unconfirmed['two']);
		$unconfirmed['end'] = implode(', ', $unconfirmed['end']);
		$unconfirmed['non'] = implode(', ', $unconfirmed['non']);

		$params = array(
			'cutoff' => $cutoff,
			'unconfirmed' => $unconfirmed,
			'boardTitle' => $options->boardTitle
		);

		$mail = new XenForo_Mail('ewrutiles_admin_email_confirmation', $params);
		$mail->enableAllLanguagePreCache();
		$mail->queue($options->defaultEmailAddress);

		return true;
	}

	public function emailUser($user, $boardTitle)
	{
		$confirmation = $this->getModelFromCache('XenForo_Model_UserConfirmation')->generateUserConfirmationRecord($user['user_id'], 'email');

		$params = array(
			'user' => $user,
			'confirmation' => $confirmation,
			'boardTitle' => $boardTitle
		);

		$mail = new XenForo_Mail('ewrutiles_user_email_confirmation', $params, $user['language_id']);
		$mail->enableAllLanguagePreCache();
		$mail->queue($user['email'], $user['username']);

		return true;
	}

	public function deleteUser($user)
	{
		$dw = XenForo_DataWriter::create('XenForo_DataWriter_User');
		$dw->setExistingData($user);
		$dw->delete();

		return true;
	}
}