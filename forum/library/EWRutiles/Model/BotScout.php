<?php

class EWRutiles_Model_BotScout extends XenForo_Model
{
	public function challengeRegistration($data)
	{
		$options = XenForo_Application::get('options');
		$matches = array();
		$errors = array();
		$refuse = false;
		$count = 0;
		
		$result = $this->submitChallenge($data, $options->EWRutiles_botscout_apikey);
		
		if (empty($result))
		{
			if (!$options->EWRutiles_botscout_failure)
			{
				$count = 9;
				$errors[] = new XenForo_Phrase('botscout_failure');
			}
		}
		else
		{
			if ($options->EWRutiles_botscout_username && !empty($result['username']))
			{
				$count++;
				$matches['username'] = true;
				$errors[] = new XenForo_Phrase('botscout_username', array('username' => $data['username']));
			}

			if (!empty($result['email']))
			{
				$count++;
				$matches['email'] = true;
				$errors[] = new XenForo_Phrase('botscout_email', array('email' => $data['email']));
			}

			if (!empty($result['ip']))
			{
				$count++;
				$matches['ip'] = true;
				$errors[] = new XenForo_Phrase('botscout_ip', array('ip' => $data['ip']));
			}
		}
		
		if ($count >= $options->EWRutiles_botscout_checks)
		{
			$refuse = true;
		}
		
		return array($matches, $errors, $refuse);
	}

	public function submitChallenge($data, $apikey)
	{
		$feed = 'http://www.botscout.com/test/?multi&'.
			($data['username'] ? 'name='.rawurlencode($data['username']).'&' : '').
			($data['email'] ? 'mail='.rawurlencode($data['email']).'&' : '').
			($data['ip'] ? 'ip='.rawurlencode($data['ip']).'&' : '').
			'key='.$apikey;

		$client = new Zend_Http_Client($feed);

		try
		{
			$feed = $client->request()->getBody();
		}
		catch (Zend_Http_Client_Adapter_Exception $e)
		{
			return false;
		}

		if ($feed[0] == '!') { return false; }

		$feed = explode('|', $feed);

		return array('username' => $feed[7], 'email' => $feed[5], 'ip' => $feed[3]);
	}
	
	
	
	
	
	
	
	
	public function checkDatabase($data, &$matches, &$count)
	{
		$options = XenForo_Application::get('options');
		$errors = array();

		if (!$apikey = $options->EWRutiles_botscout_apikey) { return $errors; }
		$result = $this->retrieveData($data, $apikey);

		if (!$result)
		{
			if (!$options->EWRutiles_registration_failure)
			{
				$count = 3;
				$matches[] = 'BS ERROR';
				$errors[] = new XenForo_Phrase('botscout_failure');
			}
		}
		else
		{
			if ($result['username'])
			{
				$count++;
				$matches['username'] = new XenForo_Phrase('user_name');
				$errors[] = new XenForo_Phrase('botscout_username');
			}

			if ($result['email'])
			{
				$count++;
				$matches['email'] = new XenForo_Phrase('email');
				$errors[] = new XenForo_Phrase('botscout_email');
			}

			if ($result['ip'])
			{
				$count++;
				$matches['ip'] = new XenForo_Phrase('ip');
				$errors[] = new XenForo_Phrase('botscout_ip', array('ip' => $data['ip']));
			}
		}

		return $errors;
	}

	public function retrieveData($data, $apikey)
	{
		$feed = 'http://www.botscout.com/test/?multi&'.
			($data['username'] ? 'name='.rawurlencode($data['username']).'&' : '').
			($data['email'] ? 'mail='.rawurlencode($data['email']).'&' : '').
			($data['ip'] ? 'ip='.rawurlencode($data['ip']).'&' : '').
			'key='.$apikey;

		$client = new Zend_Http_Client($feed);

		try
		{
			$feed = $client->request()->getBody();
		}
		catch (Zend_Http_Client_Adapter_Exception $e)
		{
			return false;
		}

		if ($feed[0] == '!') { return false; }

		$feed = explode('|', $feed);

		return array('username' => $feed[7], 'email' => $feed[5], 'ip' => $feed[3]);
	}
}