<?php

class EWRporta2_ControllerAdmin_Porta2 extends XenForo_ControllerAdmin_Abstract
{
	private $xp2perms;

	public function actionWidgetsAdd()
	{
		echo "test"; exit;
	}

	protected function _preDispatch($action)
	{
		parent::_preDispatch($action);

		$this->xp2perms = $this->getModelFromCache('EWRporta2_Model_Perms')->getPermissions();
	}
}