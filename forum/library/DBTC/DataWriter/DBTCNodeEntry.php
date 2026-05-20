<?php
class DBTC_DataWriter_DBTCNodeEntry extends XenForo_DataWriter
{
    /**
    * Gets the fields that are defined for the table. See parent for explanation.
    *
    * @return array
    */
    protected function _getFields() {
        return array(
            'dbtc_transactions' => array(
				
                'dbtc_transaction_id'  => array(
                    'type' => self::TYPE_UINT,
                    'autoIncrement' => true,
					'default' => 0,
                ),
				'dbtc_thread_id' => array(
					'type'		=> self::TYPE_UINT,
				),
				'dbtc_receiver_id' => array(
					'type'		=> self::TYPE_UINT,
				),
				'dbtc_donor_id' => array(
					'type'		=> self::TYPE_UINT,
				),
				'dbtc_status_id' => array(
					'type'		=> self::TYPE_UINT,
				),
				'dbtc_date'    => array(
					'type'     => self::TYPE_UINT,
					'default'  => XenForo_Application::$time
                ),
				'dbtc_parent_transaction_id'  => array(
					'type'     => self::TYPE_UINT,
                ),
            )
        );
    }
    /**
    * Gets the actual existing data out of data that was passed in. See parent for explanation.
    *
    * @param mixed
    *
      * @see XenForo_DataWriter::_getExistingData()
      *
      * @return array|false
    */
 
    protected function _getExistingData($data)
    {
        if (!$id = $this->_getExistingPrimaryKey($data, 'dbtc_transaction_id'))
        {
            return false;
        }
        return array('dbtc_transactions' => $this->_getDBTCModel()->getDBTCTransById($id));
    }
 
 
    /**
    * Gets SQL condition to update the existing record.
    * 
    * @see XenForo_DataWriter::_getUpdateCondition() 
    *
    * @return string
    */
    protected function _getUpdateCondition($tableName)
    {
        return 'dbtc_transaction_id = ' . $this->_db->quote($this->getExisting('dbtc_transaction_id'));
    }
 
    /**
    * Get the simple text model.
    *
    * @return
    */
    protected function _getDBTCModel()
    {
        return $this->getModelFromCache ( 'DBTC_Model_DBTC' );
    }
 
}
?>