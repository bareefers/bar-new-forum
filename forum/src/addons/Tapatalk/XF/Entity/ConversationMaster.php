<?php

namespace Tapatalk\XF\Entity;

class ConversationMaster extends XFCP_ConversationMaster
{

    /**
     * Post-save handling.
     */
    protected function _postSave()
    {
        parent::_postSave();
        if (isset($_REQUEST['redirect']) && strpos($_REQUEST['redirect'], 'rebuild&success=1') !== false) {
            return;
        }
        $conversation_id = $this->conversation_id;
        if ($conversation_id) {
            $GLOBALS['tapatalk_conversation_id'] = $conversation_id;
        }
        return;
    }

}