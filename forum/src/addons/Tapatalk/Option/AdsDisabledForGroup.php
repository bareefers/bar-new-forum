<?php
namespace Tapatalk\Option;

use XF\Entity\UserGroup;
use XF\Option\AbstractOption;

class AdsDisabledForGroup extends AbstractOption
{
    protected static function getSelectData(\XF\Entity\Option $option, array $htmlParams)
    {
        $choices = [];
        return [
            'choices' => $choices,
            'controlOptions' => self::getControlOptions($option, $htmlParams),
            'rowOptions' => self::getRowOptions($option, $htmlParams)
        ];
    }

    public static function renderOption(\XF\Entity\Option $option, array $htmlParams)
    {
        $optionId = $option->option_id; // readonlyForums
//        $addonId = $option->addon_id;
//        $dataType = $option->data_type;
//        /** @var AddOn $addOn */
//        $addOn = $option->AddOn;
//        /** @var PreEscaped $listedHtml */
//        $listedHtml = $htmlParams['listedHtml'];
//        /** @var PreEscaped $inputName */
//        $inputName = $htmlParams['inputName'];

        foreach ($option->option_value AS $key => $id) {
            if (!is_numeric($id)) {
                unset($option->option_value[$key]);
            }
        }
        $entry = [];
        if (!is_array($option->option_value)) {
            $entry['choices'] = [];
        }else{
            $entry['choices'] = array_unique($option->option_value);
        }

        $app = \XF::app();

        $entry['group_list'][0] = ['label' => 'Show All', '_type' => 'option', 'value' => '0'];
        /** @var \XF\Repository\UserGroup $userGroupRepo */
        $userGroupRepo = $app->repository('XF:UserGroup');
        $userGroupList = $userGroupRepo->findUserGroupsForList()->fetch();
        if ($userGroupList) {
            $userGroupList = $userGroupList->toArray();
        }else{
            $userGroupList = [];
        }
        /** @var UserGroup $group */
        foreach ($userGroupList as $group) {
            $entry['group_list'][$group->get('user_group_id')] = ['label' => \XF::escapeString($group->get('title')), '_type' => 'option', 'value' => $group->get('user_group_id')];
        }

        $data = self::getSelectData($option, $htmlParams);
        $data['controlOptions']['multiple'] = true;
        $data['controlOptions']['size'] = 8;
        $data['choices'] =  $entry['group_list'];

        return self::getTemplater()->formSelectRow(
            $data['controlOptions'], $data['choices'], $data['rowOptions']
        );

//        return self::getTemplate('admin:tapatalk_option_multi_group_select', $option, $htmlParams, [
//            'entry' => $entry,
//            'optionId' => $optionId,
//            'fieldPrefix' => 'options',
//            'optionTitle' => \XF::phrase('option.ads_disabled_for_group')->render(),
//            'optionExplain' => \XF::phrase('option_explain.ads_disabled_for_group')->render(),
//        ]);
    }

    public static function verifyOption(array &$value)
    {
        $output = [];
//
//        foreach ($value AS $word)
//        {
//            if (!isset($word['word']) || !isset($word['replace']))
//            {
//                continue;
//            }
//
//            $cache = self::buildCensorCacheValue($word['word'], $word['replace']);
//            if ($cache)
//            {
//                $output[] = $cache;
//            }
//        }
//
//        $value = $output;

        return true;
    }
}