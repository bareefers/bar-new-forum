<?php

function mobiquo_hide_forum_array()
{
    $hiddenForums = array();
   
    return $hiddenForums;
}
function mobiquo_hide_forum($forumId)
{
    $hiddenForums = mobiquo_hide_forum_array();
    return in_array($forumId, $hiddenForums);
}

function mobiquo_hide_forum_topicWhere()
{
    $hiddenForums = mobiquo_hide_forum_array();
    if(!empty($hiddenForums))
    {
        return array('forums_topics.forum_id NOT IN (?)', implode(',',$hiddenForums));
    }
    return null;
}
function mobiquo_format_date($date)
{
    return $date;
}

function check_return_user_type($member)
{
    
    if($member->isBanned())
    {
        $user_type = 'banned';
    }
    else if($member->isAdmin())
    {
        $user_type = 'admin';
    }
    else if($member->modPermission() != null)
    {
        $user_type = 'mod';
    }
    //else if($user_row['user_type'] == USER_INACTIVE && $config['require_activation'] == USER_ACTIVATION_ADMIN)
    //{
    //    $user_type = 'unapproved';
    //}
    //else if($user_row['user_type'] == USER_INACTIVE)
    //{
    //    $user_type = 'inactive';
    //}
    else
    {
        $user_type = 'normal';
    }
    return $user_type;
}

function get_user_avatar_url($avatar, $avatar_type, $ignore_config = false)
{
    switch($avatar_type)
    {
        case 'custom':
            {
                return $avatar->rfc3986();    
            }
    }
    return null;
    //global $config, $phpbb_home, $phpEx;

    //if (empty($avatar) || (isset($config['allow_avatar']) && !$config['allow_avatar'] && !$ignore_config))
    //{
    //    return '';
    //}
    
    //$avatar_img = '';

    //switch ($avatar_type)
    //{
    //    case AVATAR_UPLOAD:
    //        if (isset($config['allow_avatar_upload']) && !$config['allow_avatar_upload'] && !$ignore_config)
    //        {
    //            return '';
    //        }
    //        $avatar_img = $phpbb_home . "download/file.$phpEx?avatar=";
    //        break;

    //    case AVATAR_GALLERY:
    //        if (isset($config['allow_avatar_local']) && !$config['allow_avatar_local'] && !$ignore_config)
    //        {
    //            return '';
    //        }
    //        $avatar_img = $phpbb_home . $config['avatar_gallery_path'] . '/';
    //        break;

    //    case AVATAR_REMOTE:
    //        if (isset($config['allow_avatar_remote']) && !$config['allow_avatar_remote'] && !$ignore_config)
    //        {
    //            return '';
    //        }
    //        break;
    //    default:
    //        $avatar_img = $phpbb_home . "download/file.$phpEx?avatar=";
    //        break;
    //}

    //$avatar_img .= $avatar;
    //$avatar_img = str_replace(' ', '%20', $avatar_img);
    
    //return $avatar_img;
}


function TT_process_short_content($post_text, $length = 200)
{
    $addDots = false;
    $post_text = TT_convertToTapatalkBBCode($post_text);
    //if(mb_strlen($post_text) > 200)
    //{
    //    $addDots = true;
    //}
    $array_reg = array(
        array('reg' => '/\[quote(.*?)\](.*?)\[\/quote(.*?)\]/si','replace' => '[quote]'),
        // array('reg' => '/\[code(.*?)\](.*?)\[\/code(.*?)\]/si','replace' => '[code]'),
        array('reg' => '/\[code(.*?)\](.*?)\[\/code(.*?)\]/si','replace' => '$2'),
        array('reg' => '/\[video(.*?)\](.*?)\[\/video(.*?)\]/si','replace' => ''),
        array('reg' => '/\[attachment(.*?)\](.*?)\[\/attachment(.*?)\]/si','replace' => '[attach]'),
        array('reg' => '/\[url.*?\].*?\[\/url.*?\]/','replace' => '[url]'),
        array('reg' => '/(https?|ftp|mms):\/\/([A-z0-9]+[_\-]?[A-z0-9]+\.)*[A-z0-9]+\-?[A-z0-9]+\.[A-z]{2,}(\/.*)*\/?/is','replace' => ''),
        array('reg' => '/\[img.*?\].*?\[\/img.*?\]/','replace' => '[img]'),
        array('reg' => '/[\n\r\t]+/','replace' => ' '),
        array('reg' => '/\[flash(.*?)\](.*?)\[\/flash(.*?)\]/si','replace' => '[flash]'),
        array('reg' => '/\[spoiler(.*?)\](.*?)\[\/spoiler(.*?)\]/si','replace' => '[spoiler]'),
        array('reg' => '/\[spoil(.*?)\](.*?)\[\/spoil(.*?)\]/si','replace' => '[spoiler]'),
    );
    //echo $post_text;die();
    foreach ($array_reg as $arr)
    {
        $post_text = preg_replace($arr['reg'], $arr['replace'], $post_text);
    }
    $post_text = html_entity_decode($post_text, ENT_QUOTES, 'UTF-8');
    $post_text = function_exists('mb_substr') ? mb_substr($post_text, 0, $length) : substr($post_text, 0, $length);
    $post_text = trim(strip_tags($post_text));
    $post_text = preg_replace('/\\s+|\\r|\\n/', ' ', $post_text);
    if($addDots)
    {
        $post_text .= "...";
    }
    return $post_text;
}


function TT_mapEmoticonToEmoji($emoticon)
{
    $emoticons = array(">:(" => "35",
        ":D" => "3",
        "O.o" => "33",
        ":$" => "5",
        "B|" => "41",
        "¬¬" => "19",
        "^_^" => "5",
        "o.O" => "57",
        "xD" => "23",
        ":|" => "52",
        ":o" => "33",
        ":ph34r:" => "185",
        "9_9" => "16",
        ":(" => "20",
        "-_-" => "42",
        ":)" => "4",
        ":P" => "14",
        ":/" => "32",
        ":S" => "37",
        ";)" => "6",
        ":x" => "8");
    if(isset($emoticons[$emoticon]))
    {
        return '[emoji' .$emoticons[$emoticon] . ']';
    }
    return $emoticon;
}

function TT_removeTagKeepContent($tag, $text)
{
    $text = preg_replace("/<\/?" .$tag . "[^>]*\>/i", "", $text); 
    return $text;
}
function TT_getHtmlEntity($html, $tagName)
{
    $dom = new DOMDocument();
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html);

    foreach ($dom->childNodes as $item)
        if ($item->nodeType == XML_PI_NODE)
            $dom->removeChild($item);
    $dom->encoding = 'UTF-8';
    return $dom->getElementsByTagName($tagName)->item(0);
}
function TT_rgb2html($r, $g=-1, $b=-1)
{
    if (is_array($r) && sizeof($r) == 3)
        list($r, $g, $b) = $r;

    $r = intval($r); $g = intval($g);
    $b = intval($b);

    $r = dechex($r<0?0:($r>255?255:$r));
    $g = dechex($g<0?0:($g>255?255:$g));
    $b = dechex($b<0?0:($b>255?255:$b));

    $color = (strlen($r) < 2?'0':'').$r;
    $color .= (strlen($g) < 2?'0':'').$g;
    $color .= (strlen($b) < 2?'0':'').$b;
    return TT_color_convert('#'.$color);
}
function TT_get_inner_html( $node ) {
    $innerHTML= '';
    $children = $node->childNodes;
    foreach ($children as $child) {
        $innerHTML .= $child->ownerDocument->saveXML( $child );
    }

    return $innerHTML;
} 
function TT_color_convert($color)
{
    static $colorlist;
    
    if (preg_match('/#[\da-fA-F]{6}/is', $color))
    {
        if (!$colorlist)
        {
            $colorlist = array(
                '#000000' => 'Black',             '#708090' => 'SlateGray',       '#C71585' => 'MediumVioletRed', '#FF4500' => 'OrangeRed',
                '#000080' => 'Navy',              '#778899' => 'LightSlateGrey',  '#CD5C5C' => 'IndianRed',       '#FF6347' => 'Tomato',
                '#00008B' => 'DarkBlue',          '#778899' => 'LightSlateGray',  '#CD853F' => 'Peru',            '#FF69B4' => 'HotPink',
                '#0000CD' => 'MediumBlue',        '#7B68EE' => 'MediumSlateBlue', '#D2691E' => 'Chocolate',       '#FF7F50' => 'Coral',
                '#0000FF' => 'Blue',              '#7CFC00' => 'LawnGreen',       '#D2B48C' => 'Tan',             '#FF8C00' => 'Darkorange',
                '#006400' => 'DarkGreen',         '#7FFF00' => 'Chartreuse',      '#D3D3D3' => 'LightGrey',       '#FFA07A' => 'LightSalmon',
                '#008000' => 'Green',             '#7FFFD4' => 'Aquamarine',      '#D3D3D3' => 'LightGray',       '#FFA500' => 'Orange',
                '#008080' => 'Teal',              '#800000' => 'Maroon',          '#D87093' => 'PaleVioletRed',   '#FFB6C1' => 'LightPink',
                '#008B8B' => 'DarkCyan',          '#800080' => 'Purple',          '#D8BFD8' => 'Thistle',         '#FFC0CB' => 'Pink',
                '#00BFFF' => 'DeepSkyBlue',       '#808000' => 'Olive',           '#DA70D6' => 'Orchid',          '#FFD700' => 'Gold',
                '#00CED1' => 'DarkTurquoise',     '#808080' => 'Grey',            '#DAA520' => 'GoldenRod',       '#FFDAB9' => 'PeachPuff',
                '#00FA9A' => 'MediumSpringGreen', '#808080' => 'Gray',            '#DC143C' => 'Crimson',         '#FFDEAD' => 'NavajoWhite',
                '#00FF00' => 'Lime',              '#87CEEB' => 'SkyBlue',         '#DCDCDC' => 'Gainsboro',       '#FFE4B5' => 'Moccasin',
                '#00FF7F' => 'SpringGreen',       '#87CEFA' => 'LightSkyBlue',    '#DDA0DD' => 'Plum',            '#FFE4C4' => 'Bisque',
                '#00FFFF' => 'Aqua',              '#8A2BE2' => 'BlueViolet',      '#DEB887' => 'BurlyWood',       '#FFE4E1' => 'MistyRose',
                '#00FFFF' => 'Cyan',              '#8B0000' => 'DarkRed',         '#E0FFFF' => 'LightCyan',       '#FFEBCD' => 'BlanchedAlmond',
                '#191970' => 'MidnightBlue',      '#8B008B' => 'DarkMagenta',     '#E6E6FA' => 'Lavender',        '#FFEFD5' => 'PapayaWhip',
                '#1E90FF' => 'DodgerBlue',        '#8B4513' => 'SaddleBrown',     '#E9967A' => 'DarkSalmon',      '#FFF0F5' => 'LavenderBlush',
                '#20B2AA' => 'LightSeaGreen',     '#8FBC8F' => 'DarkSeaGreen',    '#EE82EE' => 'Violet',          '#FFF5EE' => 'SeaShell',
                '#228B22' => 'ForestGreen',       '#90EE90' => 'LightGreen',      '#EEE8AA' => 'PaleGoldenRod',   '#FFF8DC' => 'Cornsilk',
                '#2E8B57' => 'SeaGreen',          '#9370D8' => 'MediumPurple',    '#F08080' => 'LightCoral',      '#FFFACD' => 'LemonChiffon',
                '#2F4F4F' => 'DarkSlateGrey',     '#9400D3' => 'DarkViolet',      '#F0E68C' => 'Khaki',           '#FFFAF0' => 'FloralWhite',
                '#2F4F4F' => 'DarkSlateGray',     '#98FB98' => 'PaleGreen',       '#F0F8FF' => 'AliceBlue',       '#FFFAFA' => 'Snow',
                '#32CD32' => 'LimeGreen',         '#9932CC' => 'DarkOrchid',      '#F0FFF0' => 'HoneyDew',        '#FFFF00' => 'Yellow',
                '#3CB371' => 'MediumSeaGreen',    '#9ACD32' => 'YellowGreen',     '#F0FFFF' => 'Azure',           '#FFFFE0' => 'LightYellow',
                '#40E0D0' => 'Turquoise',         '#A0522D' => 'Sienna',          '#F4A460' => 'SandyBrown',      '#FFFFF0' => 'Ivory',
                '#4169E1' => 'RoyalBlue',         '#A52A2A' => 'Brown',           '#F5DEB3' => 'Wheat',           '#FFFFFF' => 'White',
                '#4682B4' => 'SteelBlue',         '#A9A9A9' => 'DarkGrey',        '#F5F5DC' => 'Beige',
                '#483D8B' => 'DarkSlateBlue',     '#A9A9A9' => 'DarkGray',        '#F5F5F5' => 'WhiteSmoke',
                '#48D1CC' => 'MediumTurquoise',   '#ADD8E6' => 'LightBlue',       '#F5FFFA' => 'MintCream',
                '#4B0082' => 'Indigo',            '#ADFF2F' => 'GreenYellow',     '#F8F8FF' => 'GhostWhite',
                '#556B2F' => 'DarkOliveGreen',    '#AFEEEE' => 'PaleTurquoise',   '#FA8072' => 'Salmon',
                '#5F9EA0' => 'CadetBlue',         '#B0C4DE' => 'LightSteelBlue',  '#FAEBD7' => 'AntiqueWhite',
                '#6495ED' => 'CornflowerBlue',    '#B0E0E6' => 'PowderBlue',      '#FAF0E6' => 'Linen',
                '#66CDAA' => 'MediumAquaMarine',  '#B22222' => 'FireBrick',       '#FAFAD2' => 'LightGoldenRodYellow',
                '#696969' => 'DimGrey',           '#B8860B' => 'DarkGoldenRod',   '#FDF5E6' => 'OldLace',
                '#696969' => 'DimGray',           '#BA55D3' => 'MediumOrchid',    '#FF0000' => 'Red',
                '#6A5ACD' => 'SlateBlue',         '#BC8F8F' => 'RosyBrown',       '#FF00FF' => 'Fuchsia',
                '#6B8E23' => 'OliveDrab',         '#BDB76B' => 'DarkKhaki',       '#FF00FF' => 'Magenta',
                '#708090' => 'SlateGrey',         '#C0C0C0' => 'Silver',          '#FF1493' => 'DeepPink',
            );
        }
        
        if (isset($colorlist[strtoupper($color)])) $color = $colorlist[strtoupper($color)];
    }
    
    return $color;
}