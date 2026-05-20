<?php
use Tapatalk\Bridge;

defined('MBQ_IN_IT') or exit;

MbqMain::$oClk->includeClass('MbqBaseWrCommon');


Class MbqWrCommon extends MbqBaseWrCommon
{

    public function __construct()
    {
    }

    public function setApiKey($apiKey)
    {
        $bridge = Bridge::getInstance();
        $optionModel = $bridge->getOptionRepo();
        $input['options']['tp_push_key'] = $apiKey;

        /** @var \XF\Mvc\Entity\ArrayCollection $result */
        $result = $optionModel->updateOptions($input['options']);

        /** @var \XF\Entity\Option $checkOption */
        $checkOption = $optionModel->getOptionById('tp_push_key');
        $tp_push_key = '';
        if ($checkOption) {
            $tp_push_key = $checkOption->getOptionValue();
        }

        if ($apiKey == $tp_push_key) {
            return true;
        }
        return false;
    }

    public function SetSmartbannerInfo($smartbannerInfo)
    {
        $bridge = Bridge::getInstance();
        $optionModel = $bridge->getOptionRepo();

        $input['options']['tapatalk_banner_control'] = serialize($smartbannerInfo);
        $input['options']['tapatalk_banner_update'] = time();

        if (isset($smartBannerInfo) && isset($smartBannerInfo['forum_id']) && !empty($smartBannerInfo['forum_id'])) {
            $input['options']['tapatalk_forum_id'] = $smartBannerInfo['forum_id'];
        }
        /** @var \XF\Mvc\Entity\ArrayCollection $result */
        $result = $optionModel->updateOptions($input['options']);
        $checkOption = $optionModel->getOptionById('tapatalk_banner_control');

        if ($checkOption && serialize($smartbannerInfo) == $checkOption->getOptionValue()) {
            return true;
        }
        return false;
    }
}