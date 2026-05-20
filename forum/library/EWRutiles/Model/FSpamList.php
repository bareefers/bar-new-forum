<?php

class EWRutiles_Model_FSpamList extends XenForo_Model
{
	public function challengeRegistration($data)
	{
		$options = XenForo_Application::get('options');
		$matches = array();
		$errors = array();
		$refuse = false;
		$count = 0;
		
		$result = $this->submitChallenge($data, $options->EWRutiles_fspamlist_apikey);

		if (empty($result[0]))
		{
			if (!$options->EWRutiles_fspamlist_failure)
			{
				$count = 9;
				$errors[] = new XenForo_Phrase('fspamlist_failure');
			}
		}
		else
		{
			if ($options->EWRutiles_fspamlist_username && !empty($result[0]['isspammer']) && $result[0]['isspammer'] == 'true')
			{
				$count++;
				$matches['username'] = true;
				$errors[] = new XenForo_Phrase('fspamlist_username', array('username' => $data['username']));
			}

			if (!empty($result[1]['isspammer']) && $result[1]['isspammer'] == 'true')
			{
				$count++;
				$matches['email'] = true;
				$errors[] = new XenForo_Phrase('fspamlist_email', array('email' => $data['email']));
			}

			if (!empty($result[2]['isspammer']) && $result[2]['isspammer'] == 'true')
			{
				$count++;
				$matches['ip'] = true;
				$errors[] = new XenForo_Phrase('fspamlist_ip', array('ip' => $data['ip']));
			}
		}
		
		if ($count >= $options->EWRutiles_fspamlist_checks)
		{
			$refuse = true;
		}
		
		return array($matches, $errors, $refuse);
	}
	
	public function submitChallenge($data, $apikey)
	{
		$feed = 'http://www.fspamlist.com/api.php?spammer='.
			($data['username'] ? rawurlencode($data['username']).',' : '').
			($data['email'] ? rawurlencode($data['email']).',' : '').
			($data['ip'] ? rawurlencode($data['ip']).',' : '').
			'&key='.$apikey.'&json';

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
	
	
	
	
	
	
	
	
	
	
	
	
	public function checkDatabase($data, &$matches, &$count)
	{
		$options = XenForo_Application::get('options');
		$errors = array();

		if (!$apikey = $options->EWRutiles_fspamlist_apikey) { return $errors; }
		$result = $this->retrieveData($data, $apikey);

		if (empty($result[0]))
		{
			if (!$options->EWRutiles_registration_failure)
			{
				$count = 3;
				$matches[] = 'SFS ERROR';
				$errors[] = new XenForo_Phrase('fspamlist_failure');
			}
		}
		else
		{
			if (!empty($result[0]['isspammer']) && $result[0]['isspammer'] == 'true')
			{
				$count++;
				$matches['username'] = new XenForo_Phrase('user_name');
				$errors[] = new XenForo_Phrase('fspamlist_username', array('username' => $data['username']));
			}

			if (!empty($result[1]['isspammer']) && $result[1]['isspammer'] == 'true')
			{
				$count++;
				$matches['email'] = new XenForo_Phrase('email');
				$errors[] = new XenForo_Phrase('fspamlist_email', array('email' => $data['email']));
			}

			if (!empty($result[2]['isspammer']) && $result[2]['isspammer'] == 'true')
			{
				$count++;
				$matches['ip'] = new XenForo_Phrase('ip');
				$errors[] = new XenForo_Phrase('fspamlist_ip', array('ip' => $data['ip']));
			}
		}

		return $errors;
	}

	public function retrieveData($data, $apikey)
	{
		$feed = 'http://www.fspamlist.com/api.php?spammer='.
			($data['username'] ? rawurlencode($data['username']).',' : '').
			($data['email'] ? rawurlencode($data['email']).',' : '').
			($data['ip'] ? rawurlencode($data['ip']).',' : '').
			'&key='.$apikey.'&json';

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
}