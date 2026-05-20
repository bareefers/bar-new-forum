<?php
class DBTC_Model_DBTC extends XenForo_Model
{
    /**
    * Get all all rows of catalog table
    *
    */
    public function getAllDBTC()
    {
        return $this->fetchAllKeyed('SELECT * FROM dbtc_catalog ORDER BY dbtc_date DESC', 'dbtc_thread_id');
    }
	
    /**
    * Get the dbtc catalog entry of the thread 
    */
    public function getDBTCCatalogByThreadId($dbtc_thread_id)
    {
        return $this->_getDb()->fetchRow('
            SELECT * FROM dbtc_catalog WHERE dbtc_thread_id = ?', $dbtc_thread_id);
   
	}
	
	public function getDBTCCatalogByDonorId($dbtc_donor_id)
    {
        return $this->_getDb()->fetchAll('
            SELECT * FROM dbtc_catalog WHERE dbtc_donor_id = ?', $dbtc_donor_id);
	}

	/**
	* Get all transactions by a donor for a particular thread
	*/
	public function getDBTCTransByDonorId($dbtc_thread_id, $dbtc_donor_id) 
	{
		$transactions = $this->_getDb()->fetchAll(
			'SELECT * FROM dbtc_transactions WHERE dbtc_thread_id = ? AND dbtc_donor_id = ?', array($dbtc_thread_id, $dbtc_donor_id));
		return $transactions;
	}
	
	/*
	Gets all of the transactions for the dbtc
	*/
	public function getDBTCTransByThreadId($dbtc_thread_id) 
	{	
		return $this->_getDb()->fetchAll(
			'SELECT * FROM dbtc_transactions WHERE dbtc_thread_id = ?', $dbtc_thread_id);
	}
	
	/*
	Gets all of the transactions for a user
	*/
	public function getDBTCTransByUserId($dbtc_user_id) 
	{	
		return $this->_getDb()->fetchAll(
			'SELECT * FROM dbtc_transactions WHERE dbtc_donor_id = ? OR dbtc_receiver_id = ?', array($dbtc_user_id, $dbtc_user_id));
	}
	
	/* returns the transaction
	      given thread id
		  donor id
		  and receiver id
	*/
	

	public function getDBTCTrans($transInfo) {
	
		$dbtc_thread_id = $transInfo['dbtc_thread_id'];
		$dbtc_donor_id = $transInfo['dbtc_donor_id'];
		$dbtc_receiver_id = $transInfo['dbtc_receiver_id'];
		
		return $this->_getDb()->fetchRow(
			'SELECT * FROM dbtc_transactions 
			 WHERE dbtc_thread_id = ? 
				AND dbtc_donor_id = ? 
				AND dbtc_receiver_id = ?', 
			array($dbtc_thread_id, $dbtc_donor_id, $dbtc_reciver_id));
	}
	
	/* get transaction by transaction id */
	public function getDBTCTransById($transId) {
	
		return $this->_getDb()->fetchRow(
			'SELECT * FROM dbtc_transactions WHERE dbtc_transaction_id = ?', $transId);
	}
	
	// get the donor who started the dbtc from database
	public function getOrigDonorFromDB($threadId) {
		return $this->_getDb()->fetchRow(
			'SELECT * FROM dbtc_transactions 
				WHERE dbtc_donor_id = 0
				AND dbtc_thread_id = ?', $threadId);
	}
	
	public function returnDBTCData($transactions) 
	{	
		// index: donor_id => list of child transactions
		// data: trans_id => transaction info
		
		$index = array();
		$data = array();
		// Get all transactions for this dbtc

		// setup index of transactions
		foreach ($transactions as &$trans) {
			$id = $trans["dbtc_transaction_id"];
			$donor_id = $trans["dbtc_donor_id"];
			$data[$id] = $trans; 
			$index[$donor_id][] = $id;
		
		}
		return array("dbtc_index" => $index,
					 "dbtc_transactions" => $data, );
	}
	
	// return original donor who started the dbtc given a list
	public function getOrigDonorFromList($transList) {
	
		foreach ($transList as &$trans) {
			if ($trans['dbtc_donor_id'] == 0) {
				return $trans;
			}
		}
		return 0;
	}
	
	
	public function returnDBTCTree($list, $root = 0) {
		$tree = array();
		foreach ($list as $transkey => $trans) { // traverse current list and search for children of root
			$childtrans = $trans['dbtc_parent_transaction_id'];
			if ($childtrans == $root) {
				unset($list[$transkey]);
				$tree[$transkey] = $this->returnDBTCTree($list, $transkey);
			}
		}
		return $tree;  
	}
}
?>