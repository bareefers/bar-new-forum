<?php

class Tapatalk_Push_PushControl
{
    public static function push_control($class, &$extend)
    {
        $options = XenForo_Application::get('options');

        if($class == 'XenForo_DataWriter_ConversationMaster')
        {
            $extend[] = 'Tapatalk_Push_Conversation';
        }
    }
}
