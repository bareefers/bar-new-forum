<?php

class Waindigo_UserSearch_Listener_TemplateCreate extends Waindigo_Listener_TemplateCreate
{

    protected function _getTemplates()
    {
        return array(
            'PAGE_CONTAINER'
        );
    } /* END _getTemplates */

    public static function templateCreate(&$templateName, array &$params, XenForo_Template_Abstract $template)
    {
        $templateCreate = new Waindigo_UserSearch_Listener_TemplateCreate($templateName, $params, $template);
        list($templateName, $params) = $templateCreate->run();
    } /* END templateCreate */

    protected function _pageContainer()
    {
        $this->_preloadTemplate('waindigo_navigation_tabs_members_usersearch');
    } /* END _pageContainer */
}