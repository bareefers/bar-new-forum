<?php
class DBTC_ControllerPublic_NodeEntry extends XenForo_ControllerPublic_Abstract
{

    public function actionIndex()
    {   
		$viewParams = array(); 
		return $this->responseView('DBTC_ViewPublic_NodeEntry',	'dbtc_node_entry', $viewParams);
	}

	public function actionAddEntry()
	{
		// this action must be called via POST
		$this->_assertPostOnly();
		// guests not allowed
		$this->_assertRegistrationRequired();
		
		$permissions = XenForo_Visitor::getInstance()->getPermissions();
		$actionAllowed = XenForo_Permission::hasPermission($permissions, "forum", "postThread");
    
		if (!$actionAllowed) {  
			return $this->responseError('You do not have permissions to do this');
		}
		
		# Grab user info/model/array from db
		$userModel = XenForo_Model::create('XenForo_Model_User');
		
		// get donor id and also get the receiver's name
		$dbtc_donor_id = $this->_input->filterSingle('dbtc_donor_id', XenForo_Input::STRING);
		$dbtc_receiver_name = $this->_input->filterSingle('dbtc_receiver_name', XenForo_Input::STRING);		
		
		
		// get transaction id if it exists
		$dbtc_transaction_id = $this->_input->filterSingle('dbtc_transaction_id', XenForo_Input::UINT);	
		
		// get parent transaction id if it exists
		$dbtc_parent_transaction_id = $this->_input->filterSingle('dbtc_parent_transaction_id', XenForo_Input::UINT);	
		
		$donorModel = $userModel->getUserById($dbtc_donor_id);
		$receiverModel = $userModel->getUserByNameOrEmail($dbtc_receiver_name);

		// get user id
		$dbtc_receiver_id = $receiverModel['user_id'];
		
		// get the user based on id or error
		// $user = $this->_getUserOrError($dbtc_receiver_id);
		
		// get status id
		$dbtc_status_id = $this->_input->filterSingle('dbtc_status_id', XenForo_Input::UINT);
        
		// get date and make sure we have a 'human' versino of the date
		$dbtc_date = $this->_input->filterSingle('dbtc_date', XenForo_Input::DATE_TIME);
		$dbtc_human_date = gmdate("m/d/Y", $dbtc_date);

		# Grab avatar and link
		$avatar = XenForo_Template_Helper_Core::callHelper('avatarhtml', array($receiverModel, TRUE, array('size' => 's'), ''));

		// get all necessary inputs from this form
		$dbtc_thread_id = $this->_input->filterSingle('dbtc_thread_id', XenForo_Input::UINT);
		
		// $data = array($dbtc_thread_id, $dbtc_donor_id, $dbtc_receiver_id, $dbtc_status_id, $dbtc_date, $avatar);

		// create a new DataWriter and set user_id and message fields
		$writer = XenForo_DataWriter::create('DBTC_DataWriter_DBTCNodeEntry');
		
		// if we're editing a transaction 
		if ($dbtc_transaction_id != 0) { 
			$writer->setExistingData($dbtc_transaction_id);
		}
		
		$writer->set('dbtc_thread_id', $dbtc_thread_id);
		$writer->set('dbtc_donor_id', $dbtc_donor_id);
		$writer->set('dbtc_receiver_id', $dbtc_receiver_id);
		$writer->set('dbtc_status_id', $dbtc_status_id);
		$writer->set('dbtc_date', $dbtc_date);
		$writer->set('dbtc_parent_transaction_id', $dbtc_parent_transaction_id);
		$writer->save();
		
		// get the data that was saved
		$nodeData = $writer->getMergedData();

		$data = array(
				'dbtc_transaction_id' => $nodeData['dbtc_transaction_id'],
				'dbtc_thread_id' => $dbtc_thread_id,
				'dbtc_donor_id' => $dbtc_donor_id,
				'dbtc_receiver_id' => $dbtc_receiver_id,
				'dbtc_receiver_name' => $dbtc_receiver_name,
				'dbtc_status_id' => $dbtc_status_id,
				'dbtc_date' => $dbtc_human_date,
				'dbtc_receiver_avatar_html' => $avatar,
				'dbtc_parent_transaction_id', $dbtc_parent_transaction_id);
		

		// redirect back to the normal scratchpad index page
		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('dbtc-node-entry'),
			null,
			$data
		);
	}

	
	
}
?>