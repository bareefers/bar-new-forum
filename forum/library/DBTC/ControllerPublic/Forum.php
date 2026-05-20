<?php
class DBTC_ControllerPublic_Forum extends XenForo_ControllerPublic_Forum
{
    /**
    * Write to the database for whatever was written in the form
    */
    public function actionAddThread()
    {
	    // get parent    
        $parent = parent::actionAddThread();
        $userId = XenForo_Visitor::getUserId();   
		$visitor = XenForo_Visitor::getInstance();
		$styleId = $visitor->style_id;
		// print_r($styleId);
				
		// get all necessary inputs from this form
		$dbtc_thread_id = XenForo_Application::get('last_thread');
		$dbtc_donor_id = $userId;
		$dbtc_date = $this->_input->filterSingle('dbtc_date', XenForo_Input::DATE_TIME);
		$dbtc_type_id = $this->_input->filterSingle('dbtc_type_id', XenForo_Input::UINT);
        $dbtc_description = $this->_input->filterSingle('dbtc_description', XenForo_Input::STRING);

		# make sure we've got dbtc data
		# TEMP WE NEED BETTER CHECK. MAKE SURE WE ARE SUBMITTING FROM DBTC STYLE
		
		if (empty($dbtc_description)) {
			return $parent;
		}
		
		
        //Create a instance of our DataWriter for the catalog
        $dwDBTCcatalog = XenForo_DataWriter::create('DBTC_DataWriter_DBTCCatalogEntry');
		$dwDBTCtrans = XenForo_DataWriter::create('DBTC_DataWriter_DBTCNodeEntry');
		
		$dataCatalog = array(	// 'dbtc_id' => $dbtc_id, 
								'dbtc_thread_id' => $dbtc_thread_id,
								'dbtc_donor_id' => $dbtc_donor_id,
								'dbtc_type_id' => $dbtc_type_id,
								'dbtc_description' => $dbtc_description,
								'dbtc_date' => $dbtc_date );
		
		$dataTrans = array(		'dbtc_thread_id' => $dbtc_thread_id,
								'dbtc_receiver_id' => $dbtc_donor_id,
								'dbtc_status_id' => "0",
								'dbtc_donor_id' => "0",
								'dbtc_date' => $dbtc_date,
								'dbtc_parent_transaction_id' => 0);						
				

	
		$dwDBTCcatalog->bulkSet($dataCatalog);
        $dwDBTCcatalog->save();	

		$dwDBTCtrans->bulkSet($dataTrans);
		$dwDBTCtrans->save();
		
		
		return $parent;
    }

    /**
    * Get all the saved text in our table.
    */
    public function actionRead()
    {
        //Get all rows from our table and set it to a variable
        $viewParams = array('dbtc_catalog' => $this->_getDBTCModel()->getAllDBTC());
 
        //Send a response view, using a template, to show all the data that we get it.
        return $this->responseView('XenForo_ViewPublic_Base', 'dbtc_view', $viewParams);
 
    }
	
	 /**
    * Get the dbtc model.
    *
    * @return DBTC_Model_DBTC
    */
 
    protected function _getDBTCModel()
    {
        return $this->getModelFromCache ( 'DBTC_Model_DBTC' );
    }
 
}
?>