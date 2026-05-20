<?php
class DBTC_ControllerPublic_Thread extends XenForo_ControllerPublic_Thread
{
    public function actionIndex()
    {	// extend actionSave, so we can save using a submit button?
	    // get parent    
		
	$response = parent::actionIndex();
    //Get thread id and use it to get dbtc catalog info for this thread
	$dbtc_thread_id = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);
	
	# userModel to get username
	$userModel = $this->getModelFromCache('XenForo_Model_User');
	# get userId of current person
	
	# get catalog fields from database
	$dbtccataloginfo = $this->_getDBTCModel()->getDBTCCatalogByThreadId($dbtc_thread_id);
	
	# get user model from donor id 
	$username = $userModel->getUserById($dbtccataloginfo['dbtc_donor_id']);
	
	# convert unix date and time to something we can all read
	$date = gmdate("m-d-Y", $dbtccataloginfo['dbtc_date']);
	
	# get all of the transactions for this dbtc catalog entry
	# then create the index
	
	$dbtctrans = $this->_getDBTCModel()->getDBTCTransByThreadId($dbtc_thread_id);
	$dbtcdata = $this->_getDBTCModel()->returnDBTCData($dbtctrans);
	

	// separate out the data
	$dbtctrans = $dbtcdata["dbtc_transactions"];
    $dbtctreeindex = $dbtcdata["dbtc_index"];
	$dbtcSorted = $this->_getDBTCModel()->returnDBTCTree($dbtctrans);
	
    //Send a response view, using a template, to show all the data that we get it.
	$response->params += array(	'dbtc_transactions_index' => $dbtctreeindex,
								'dbtc_transactions' => $dbtctrans,
								'dbtc_transactions_tree' => $dbtcSorted,
								'dbtc_catalog_info' => $dbtccataloginfo );
	return $response;
	}
	    /**
    *
    * @return DBTC_Model_DBTC
    */
    protected function _getDBTCModel()
    {
        return $this->getModelFromCache ( 'DBTC_Model_DBTC' );
    }
}
?>