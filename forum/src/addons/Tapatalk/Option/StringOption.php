<?php
namespace Tapatalk\Option;

use XF\Entity\AddOn;
use XF\Option\AbstractOption;
use XF\PreEscaped;

class StringOption extends AbstractOption
{
    public static function renderOption(\XF\Entity\Option $option, array $htmlParams)
    {
        $entry = [];
        $optionId = $option->option_id; // hideForums
        // return '';
        return self::getTemplate('admin:tapatalk_option_null_string', $option, $htmlParams, [
            'entry' => $entry,
            'optionId' => $optionId,
            'fieldPrefix' => 'options',
        ]);
    }

    public static function verifyOption(array &$value)
    {
        return true;
    }

}