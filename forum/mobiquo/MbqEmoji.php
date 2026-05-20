<?php
use Tapatalk\Bridge;

defined('MBQ_IN_IT') or exit;

/**
 * common method class
 */
Class MbqEmoji extends MbqBaseEmoji {
    
    public function __construct() {
        parent::__construct();
    }

    function DoReplace($str)
    {
        $originalStr = $str;
        if(MbqMain::$oMbqConfig->getCfg('user.emoji_support')->oriValue == MbqBaseFdt::getFdt('MbqFdtConfig.user.emoji_support.range.support'))
        {
            $str = $this->UnicodeToEmoji($str);
            $str = $this->UTF8ToEmoji($str);
        }
        $bridge = Bridge::getInstance();

        $str = $bridge->_input->CleanString($str);
        if(empty($str))//if something went wrong and str ends in empty string, fallback to original one.
        {
            return $originalStr;
        }
        return $str;
    }
 }

