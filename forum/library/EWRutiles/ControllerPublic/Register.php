<?php

class EWRutiles_ControllerPublic_Register extends XFCP_EWRutiles_ControllerPublic_Register
{
	public function actionRegister()
	{
		$this->_assertPostOnly();
		$this->_assertRegistrationActive();
		
		$options = XenForo_Application::get('options');
		$refused = false;
		$errorsI = array();
		
		$timeMin = $options->EWRutiles_registration_timemin;
		$timeMax = $options->EWRutiles_registration_timemax;
		$timeForm = $this->_input->filterSingle($options->EWRutiles_registration_timefield, XenForo_Input::UINT);

		$data = $this->_input->filter(array(
			'username'   => XenForo_Input::STRING,
			'email'      => XenForo_Input::STRING,
		));
		$data['ip'] = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : false;
		$data['time'] = XenForo_Application::$time - $timeForm;
		
		if ($options->EWRutiles_registration_captcha)
		{
			if (!XenForo_Captcha_Abstract::validateDefault($this->_input))
			{
				$errors = array(new XenForo_Phrase('did_not_complete_the_captcha_verification_properly'));
				return $this->_getRegisterFormResponse($data, $errors);
			}
		}
		
		if ($timeMin && ($data['time'] < $timeMin || $data['time'] > $timeMax))
		{
			$matches = array();
			$errors = array(new XenForo_Phrase('registration_rejected_time'));
			$this->getModelFromCache('EWRutiles_Model_SpamLogs')->insertSpamLog($data, $matches, 'time');
		
			if ($options->EWRutiles_registration_immediate)
			{
				return $this->_getRegisterFormResponse($data, $errors);
			}
			else
			{
				$refused = true;
				$errorsI = array_merge($errorsI, $errors);
			}
		}
		
		if ($options->EWRutiles_stopforumspam_enable)
		{
			list($matches, $errors, $refuse) = $this->getModelFromCache('EWRutiles_Model_StopForumSpam')->challengeRegistration($data);
			
			if ($refuse)
			{
				$this->getModelFromCache('EWRutiles_Model_SpamLogs')->insertSpamLog($data, $matches, 'sfs');

				if ($options->EWRutiles_registration_immediate)
				{
					return $this->_getRegisterFormResponse($data, $errors);
				}
				else
				{
					$refused = true;
					$errorsI = array_merge($errorsI, $errors);
				}
			}
		}
		
		if ($options->EWRutiles_botscout_enable && $options->EWRutiles_botscout_apikey)
		{
			list($matches, $errors, $refuse) = $this->getModelFromCache('EWRutiles_Model_BotScout')->challengeRegistration($data);
			
			if ($refuse)
			{
				$this->getModelFromCache('EWRutiles_Model_SpamLogs')->insertSpamLog($data, $matches, 'bs');

				if ($options->EWRutiles_registration_immediate)
				{
					return $this->_getRegisterFormResponse($data, $errors);
				}
				else
				{
					$refused = true;
					$errorsI = array_merge($errorsI, $errors);
				}
			}
		}
		
		if ($options->EWRutiles_fspamlist_enable && $options->EWRutiles_fspamlist_apikey)
		{
			list($matches, $errors, $refuse) = $this->getModelFromCache('EWRutiles_Model_FSpamList')->challengeRegistration($data);
			
			if ($refuse)
			{
				$this->getModelFromCache('EWRutiles_Model_SpamLogs')->insertSpamLog($data, $matches, 'fsl');

				if ($options->EWRutiles_registration_immediate)
				{
					return $this->_getRegisterFormResponse($data, $errors);
				}
				else
				{
					$refused = true;
					$errorsI = array_merge($errorsI, $errors);
				}
			}
		}
		
		if ($refused)
		{
			return $this->_getRegisterFormResponse($data, $errorsI);
		}

		return parent::actionRegister();
	}
}