<?php

class EWRutiles_Model_StopForumSpam extends XenForo_Model
{
	public function challengeRegistration($data)
	{
		$options = XenForo_Application::get('options');
		$matches = array();
		$errors = array();
		$refuse = false;
		$count = 0;
		
		$result = $this->submitChallenge($data);
		
		if (empty($result['success']))
		{
			if (!$options->EWRutiles_stopforumspam_failure)
			{
				$count = 9;
				$errors[] = new XenForo_Phrase('stopforumspam_failure');
			}
		}
		else
		{
			if ($options->EWRutiles_stopforumspam_username && !empty($result['username']['appears']))
			{
				$count++;
				$matches['username'] = true;
				$errors[] = new XenForo_Phrase('stopforumspam_username', array('username' => $data['username']));
			}

			if (!empty($result['email']['appears']))
			{
				$count++;
				$matches['email'] = true;
				$errors[] = new XenForo_Phrase('stopforumspam_email', array('email' => $data['email']));
			}

			if (!empty($result['ip']['appears']))
			{
				$count++;
				$matches['ip'] = true;
				$errors[] = new XenForo_Phrase('stopforumspam_ip', array('ip' => $data['ip']));
			}
		}
		
		if ($count >= $options->EWRutiles_stopforumspam_checks)
		{
			$refuse = true;
		}
		
		return array($matches, $errors, $refuse);
	}

	public function submitChallenge($data)
	{
		$feed = 'http://www.stopforumspam.com/api?'.
			($data['username'] ? 'username='.rawurlencode($data['username']).'&' : '').
			($data['email'] ? 'email='.rawurlencode($data['email']).'&' : '').
			($data['ip'] ? 'ip='.rawurlencode($data['ip']).'&' : '').
			'f=json';

		$client = new Zend_Http_Client($feed);

		try
		{
			$feed = $client->request()->getBody();
		}
		catch (Zend_Http_Client_Adapter_Exception $e)
		{
			return false;
		}

		return json_decode($feed, true);
	}

	public function submitSpammer($data)
	{
		$feed = 'http://www.stopforumspam.com/add.php?'.
			'username='.rawurlencode($data['username']).'&'.
			'email='.rawurlencode($data['email']).'&'.
			'ip_addr='.rawurlencode($data['ip']).'&'.
			'api_key='.XenForo_Application::get('options')->EWRutiles_stopforumspam_apikey;

		$client = new Zend_Http_Client($feed);

		try
		{
			$feed = $client->request();
		}
		catch (Zend_Http_Client_Adapter_Exception $e)
		{
			return false;
		}

		return true;
	}
}