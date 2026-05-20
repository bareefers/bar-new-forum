<?php
class DBTC_DataWriter_DBTCCatalogEntry extends XenForo_DataWriter
{
    /**
    * Gets the fields that are defined for the table. See parent for explanation.
    *
    * @return array
    */
    protected function _getFields() {
        return array(
            'dbtc_catalog' => array(
				
                'dbtc_id'  => array(
                    'type' => self::TYPE_UINT,
					// 'default'  => 0,
                    'autoIncrement' => true
                ),
				'dbtc_thread_id' => array(
					'type'		=> self::TYPE_UINT,
					#'required'  => true,
				),
				'dbtc_donor_id' => array(
					'type'		=> self::TYPE_UINT,
					#'required'  => true,
				),
				'dbtc_type_id' => array(
					'type'		=> self::TYPE_UINT,
					#'required'  => true,
				),
				'dbtc_description'  => array(
					'type'      	=> self::TYPE_STRING, 
					'default'  => 'frag',
					#'required' => true
                ),
				'dbtc_date'    => array(
					'type'     => self::TYPE_UINT,
					#'required' => false,
					'default'  => XenForo_Application::$time
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
        if (!$id = $this->_getExistingPrimaryKey($data, 'dbtc_thread_id'))
        {
            return false;
        }
        return array('dbtc_catalog' => $this->_getDBTCModel()->getDBTCCatalogByThreadId($id));
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
        return 'dbtc_thread_id = ' . $this->_db->quote($this->getExisting('dbtc_thread_id'));
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