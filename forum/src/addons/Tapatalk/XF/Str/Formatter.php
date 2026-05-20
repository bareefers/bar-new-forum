<?php
namespace Tapatalk\XF\Str;

class Formatter extends XFCP_Formatter
{
    /**
     * @return bool
     */
    protected static function inMbq()
    {
        return defined('MBQ_IN_IT') ? true : false;
    }

    public function getDefaultSmilieHtml($id, array $smilie)
    {
        if (!self::inMbq()) {
            return parent::getDefaultSmilieHtml($id, $smilie);
        }

        $smilie_id = $smilie['smilie_id'];
        $smilieText = htmlspecialchars(reset($smilie['smilieText']));

        if (function_exists('TT_mapEmoticonToEmoji')) {
            $smilieText = TT_mapEmoticonToEmoji($smilieText);
        }
        // $smilieTitle = htmlspecialchars($smilie['title']);

        return $smilieText;
    }

}