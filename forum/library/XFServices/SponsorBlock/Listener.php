<?php

class XFServices_SponsorBlock_Listener
{
    public static function templateHook($hookName, &$contents, array $hookParams, XenForo_Template_Abstract $template)
    {
        $options = XenForo_Application::get('options');
        
            switch ($hookName) {
                case 'page_container_breadcrumb_bottom':
                    {
                        if ($options->XFServicesSponsorBoxPosition == 'bottom') {
				            $contents .= $template->create('XFServices_SponsorBox', $template->getParams());
                        }
				        break;    
                    }
                case 'page_container_breadcrumb_top':
                    {
                        if ($options->XFServicesSponsorBoxPosition == 'top') {
				            $contents .= $template->create('XFServices_SponsorBox', $template->getParams());
                        }
				        break;   
                    }
                case 'ad_sidebar_bottom':
                    {
                        if ($options->XFServicesSponsorBoxPosition == 'side') {
				            $contents .= $template->create('XFServices_SponsorBox', $template->getParams());
                        }
				        break;
                    }
                case 'ad_message_below':
                    {
                        if ($options->XFServicesSponsorBoxPosition == 'threads') {
				            $contents .= $template->create('XFServices_SponsorBox', $template->getParams()); // todo, change to a bubble ads type of set up.
                        }
				        break;
                    }
            }
    }
}
