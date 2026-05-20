<?php
namespace Tapatalk\Listener;

class LoadClassMail
{
    public static function loadClassMailListener($class, &$extend)
    {
        if ($class == 'XenForo_Mail'){
            $extend[] = 'Tapatalk_Mail';
        }
    }
}