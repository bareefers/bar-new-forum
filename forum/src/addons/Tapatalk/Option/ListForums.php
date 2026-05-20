<?php
namespace Tapatalk\Option;

use XF\Entity\AddOn;
use XF\Entity\Node;
use XF\Option\AbstractOption;
use XF\PreEscaped;
use Tapatalk\Bridge;
use XF\SubTree;
use XF\Tree;

class ListForums extends AbstractOption
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
        $optionId = $option->option_id; // hideForums
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

        $entry['forum_list'][0] = ['label' => 'Show All', '_type' => 'option', 'value' => '0'];
        /** @var \XF\Repository\Node $nodeRepo */
        $nodeRepo = $app->repository('XF:Node');

        /** @var Tree $nodeTree */
        $nodeTree = $nodeRepo->createNodeTree($nodeRepo->getFullNodeList(null, 'NodeType'));

        $rootNode = $nodeTree->children($nodeTree->getRoot());
        if ($rootNode && is_array($rootNode)) {
            $treeList = [];
            self::treeNodeList($nodeTree, $rootNode, $treeList, 0);

            $treeList = array_map(function($v) {
                $arr['label'] = \XF::escapeString($v['name']);
                $arr['_type'] = 'option';
                $arr['value'] = $v['id'];
                return $arr;
            }, $treeList);
            $entry['forum_list'] += $treeList;
        }
//        /** @var Node $node */
//        foreach ($nodeList as $node) {
//            $entry['forum_list'][$node->get('node_id')] = $node->get('title');
//        }

        $data = self::getSelectData($option, $htmlParams);
        $data['controlOptions']['multiple'] = true;
        $data['controlOptions']['size'] = 8;
        $data['choices'] =  $entry['forum_list'];

        return self::getTemplater()->formSelectRow(
            $data['controlOptions'], $data['choices'], $data['rowOptions']
        );

//        return self::getTemplate('admin:tapatalk_option_multi_forum_select', $option, $htmlParams, [
//            'entry' => $entry,
//            'optionId' => $optionId,
//            'fieldPrefix' => 'options',
//            'optionTitle' => $optionTitle,
//            'optionExplain' => $optionExplain,
//        ]);
    }

    protected static function treeNodeList($nodeTree, $rootNode, &$output, $i, $isSub=false)
    {
        /**
         * @var int $treeKey
         * @var SubTree $treeVal
         */
        foreach ($rootNode as $treeKey => $treeVal) {
            $output[$treeKey] = ['name' => ($isSub ? '  |' :'') . str_repeat('--', $i) . $treeVal->record->title , 'id' => $treeKey];
            if ($subNode = $nodeTree->children($treeKey)) {
                $i++;
                self::treeNodeList($nodeTree, $subNode, $output, $i, true);
            }
            if (!$isSub) { $i = 0; }
        }
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