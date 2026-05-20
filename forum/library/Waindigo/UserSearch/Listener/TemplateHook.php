<?php

class Waindigo_UserSearch_Listener_TemplateHook extends Waindigo_Listener_TemplateHook
{

    protected function _getHooks()
    {
        return array(
            'navigation_tabs_members',
            'search_form_tabs'
        );
    } /* END _getHooks */

    public static function templateHook($hookName, &$contents, array $hookParams, XenForo_Template_Abstract $template)
    {
        $templateHook = new Waindigo_UserSearch_Listener_TemplateHook($hookName, $contents, $hookParams, $template);
        $contents = $templateHook->run();
    } /* END templateHook */

    protected function _navigationTabsMembers()
    {
        $this->_appendTemplate('waindigo_navigation_tabs_members_usersearch');
    } /* END _navigationTabsMembers */

    protected function _searchFormTabs()
    {
        $this->_appendTemplate('waindigo_search_form_tabs_usersearch');
    } /* END _searchFormTabs */
}