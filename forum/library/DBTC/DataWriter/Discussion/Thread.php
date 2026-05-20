<?php
class DBTC_DataWriter_Discussion_Thread extends XFCP_DBTC_DataWriter_Discussion_Thread
{
	
    protected function _discussionPostSave()
    {
        $threadId = $this->get('thread_id');
        XenForo_Application::set('last_thread',$threadId);
    }
}
?>