<?php
namespace Tapatalk\XF\Legacy;

use XF\Legacy\DataWriter as XFCP_DataWriter;

class DataWriter extends XFCP_DataWriter
{
    /**
    * Gets the fields that are defined for the table. See parent for explanation.
    *
    * @return array
    */
    protected function _getFields() {

        return array(
            'xf_tapatalk_users' => array(
                'userid'    => array(
                    'type' => self::TYPE_UINT,
                ),
                'announcement'    => array(
                    'type'            => self::TYPE_UINT,
                    'required'        => true,
                    'default'        => 1
                ),
                'pm'    => array(
                    'type'            => self::TYPE_UINT,
                    'required'        => true,
                    'default'        => 1
                ),
                'subscribe'    => array(
                    'type'            => self::TYPE_UINT,
                    'required'        => true,
                    'default'        => 1
                ),
                'quote'    => array(
                    'type'            => self::TYPE_UINT,
                    'required'        => true,
                    'default'        => 1
                ),
                'liked'    => array(
                    'type'            => self::TYPE_UINT,
                    'required'        => true,
                    'default'        => 1
                ),
                'tag'    => array(
                    'type'            => self::TYPE_UINT,
                    'required'        => true,
                    'default'        => 1
                ),
                'updated'    => array(
                    'type'            => self::TYPE_STRING,
                    'required'        => true,
                    'default'        => gmdate('Y-m-d h:i:s',time())
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
        if (!$id = $this->_getExistingPrimaryKey($data, 'userid'))
        {
            return false;
        }
        $tapatalkUsers = null;
        if ($id instanceof \Tapatalk\Entity\TapatalkUsers) {
            $tapatalkUsers = $id;
        }else if (is_numeric($id)){
            $tapatalkUsers = $this->_getTapatalkUserModel()->getTapatalkUserById($id);
        }
        if (!$tapatalkUsers) {
            return false;
        }
        return array('xf_tapatalk_users' => [$tapatalkUsers->userid => $tapatalkUsers]);
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
        return 'userid = ' . $this->_db->quote($this->getExisting('userid'));
    }

    /**
     * @return \Tapatalk\XF\Repository\TapatalkUsersRepo
     */
    protected function _getTapatalkUserModel()
    {
        $app = \XF::app();
        return $app->repository('Tapatalk\XF\Repository\TapatalkUsersRepo');
    }

    /**
     * @return \Tapatalk\XF\Repository\TapatalkUsersRepo
     */
    public function getTapatalkUserModel()
    {
        $app = \XF::app();
        return $app->repository('Tapatalk\XF\Repository\TapatalkUsersRepo');
        // return Bridge::getInstance()->getTapatalkUsersRepo();
    }
}
