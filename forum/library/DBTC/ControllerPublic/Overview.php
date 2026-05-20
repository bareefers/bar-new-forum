<?php
class DBTC_ControllerPublic_Overview extends XenForo_ControllerPublic_Abstract
{
    public function actionIndex()
    {	// extend actionSave, so we can save using a submit button?
	    // get parent    
		
	//$response = parent::actionIndex();
    //Get thread id and use it to get dbtc catalog info for this thread
	$dbtc_thread_id = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);
	$userId = XenForo_Visitor::getUserId();   
	
	# get user model from donor id
	$userModel = $this->getModelFromCache('XenForo_Model_User');
	$username = $userModel->getUserById($userId);
	

	# get userId of current person
	
	# get all dbtc started by user
	$dbtccataloginfo = $this->_getDBTCModel()->getDBTCCatalogByDonorId($userId);
	$transactions = $this->_getDBTCModel()->getDBTCTransByUserId($userId);
	
	$catalogthreadids = array_unique(array_column($dbtccataloginfo, 'dbtc_thread_id'));
	$transthreadids = array_unique(array_column($transactions, 'dbtc_thread_id'));

	$threadModel = $this->getModelFromCache('XenForo_Model_Thread');
	
	$transthreads = $threadModel->getThreadsByIds($transthreadids,
										array('order' => 'post_date','limit' => 20));
										
	$catalogthreads = $threadModel->getThreadsByIds($catalogthreadids,
										array('order' => 'post_date','limit' => 20));
																			
	
	# convert unix date and time to something we can all read
	// $date = gmdate("m-d-Y", $dbtccataloginfo['dbtc_date']);

	print_r($catalogthreadids);
	//print_r($catalogthreads);
    //Send a response view, using a template, to show all the data that we get it.
	$viewParams = array('catalogthreads' => $catalogthreads,
						'transthreads' => $transthreads);
								
	return $this->responseView('DBTC_ViewPublic_Overview', 'dbtc_overview', $viewParams);
	}
	
	/**
    *
    * @return DBTC_Model_DBTC
	*
    **/
	
    protected function _getDBTCModel()
    {
        return $this->getModelFromCache ( 'DBTC_Model_DBTC' );
    }
}
?>