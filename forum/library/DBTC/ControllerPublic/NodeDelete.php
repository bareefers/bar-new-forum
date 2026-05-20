<?php
class DBTC_ControllerPublic_NodeDelete extends XenForo_ControllerPublic_Abstract
{

    public function actionIndex()
    {   
		$viewParams = array(); 
		return $this->responseView('DBTC_ViewPublic_NodeDelete', 'dbtc_node_delete', $viewParams);
	}

	
	public function actionDeleteEntry()
	{
		// this action must be called via POST
		$this->_assertPostOnly();
		
		// Check permissions!
		$this->_assertRegistrationRequired();
		$permissions = XenForo_Visitor::getInstance()->getPermissions();
		$actionAllowed = XenForo_Permission::hasPermission($permissions, "forum", "postThread");
		if (!$actionAllowed) {  
			return $this->responseError('You do not have permissions to do this');
		}
		
		$dbtc_transaction_id = $this->_input->filterSingle('dbtc_transaction_id', XenForo_Input::STRING);
		
		$writer = XenForo_DataWriter::create('DBTC_DataWriter_DBTCNodeEntry');
		
		$writer->setExistingData($dbtc_transaction_id);
		$writer->delete();

		$data = array('dbtc_transaction_id' => $dbtc_transaction_id);
		
		// redirect back to the normal page
		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('dbtc-node-delete'),
			new XenForo_Phrase('Removed!'),
			$data // make sure to return transaction id data so jquery knows to do something
		);
	}
	
    protected function _getDBTCModel()
    {
        return $this->getModelFromCache ( 'DBTC_Model_DBTC' );
    }
	
		/**
	 * Gets the specified user or throws an exception.
	 *
	 * @param string $id
	 *
	 * @return array
	 */
	 
	protected function _getUserOrError($id)
	{
		$userModel = $this->_getUserModel();

		return $this->getRecordOrError(
			$id, $userModel, 'getFullUserById',
			'requested_user_not_found'
		);
	}
	
	protected function _getUserModel()
	{
		return $this->getModelFromCache('XenForo_Model_User');
	}
	
	
}
?>