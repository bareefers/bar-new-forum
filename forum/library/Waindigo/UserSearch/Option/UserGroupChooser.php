<?php

/**
 * Helper for UserSearch to render a list of UserGroups.
 */
class Waindigo_UserSearch_Option_UserGroupChooser
{

    public static function renderCheckbox(XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
    {
        return self::_render('option_list_option_checkbox', $view, $fieldPrefix, $preparedOption, $canEdit);
    } /* END renderCheckbox */

    /**
     * Fetches a list of user group options.
     *
     * @param string|array $selectedGroupIds Array or comma delimited list
     *
     * @return array
     */
    public static function getUserGroupOptions($selectedGroup)
    {
        /* @var $userGroupModel XenForo_Model_UserGroup */
        $userGroupModel = XenForo_Model::create('XenForo_Model_UserGroup');

        $options = $userGroupModel->getUserGroupOptions($selectedGroup);

        return $options;
    } /* END getUserGroupOptions */

    /**
     * Renders the user group chooser option.
     *
     * @param string Name of template to render
     * @param XenForo_View $view View object
     * @param string $fieldPrefix Prefix for the HTML form field name
     * @param array $preparedOption Prepared option info
     * @param boolean $canEdit True if an "edit" link should appear
     *
     * @return XenForo_Template_Abstract Template object
     */
    protected static function _render($templateName, XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
    {
        $preparedOption['formatParams'] = self::getUserGroupOptions($preparedOption['option_value']);

        $extra['class'] = 'checkboxColumns';

        return XenForo_ViewAdmin_Helper_Option::renderOptionTemplateInternal($templateName, $view, $fieldPrefix,
            $preparedOption, $canEdit, $extra);
    } /* END _render */
}