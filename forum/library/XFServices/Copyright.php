<?php

class XFServices_Copyright
{
    public static function templateHook($hookName, &$contents, array $hookParams, XenForo_Template_Abstract $template)
    {
        
            switch ($hookName) {
                case 'footer_links':
                    {
                        if (defined('RUNONCE')) {
                            $contents .= '';
                        } else {
                        $contents .= '<li><a href="http://xfservices.com" class="concealed Tooltip" title="XenForo Services">Add ons provided by XenForo Services & Setup</a></li>';
                        define('RUNONCE', true);
                        break;
                        }
                    }
                
            }
    }
}
