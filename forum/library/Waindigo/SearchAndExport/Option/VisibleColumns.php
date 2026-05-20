<?php

/**
 * Helper for visible columns option.
 *
 * @package XenForo_Options
 */
abstract class Waindigo_SearchAndExport_Option_VisibleColumns
{

    /**
     * Renders the censor words option row.
     *
     * @param XenForo_View $view View object
     * @param string $fieldPrefix Prefix for the HTML form field name
     * @param array $preparedOption Prepared option info
     * @param boolean $canEdit True if an "edit" link should appear
     *
     * @return XenForo_Template_Abstract Template object
     */
    public static function renderOption(XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
    {
        $value = $preparedOption['option_value'];
        $choices = array();
        if (!empty($value['all'])) {
            foreach ($value['all'] as $contentType => $columns) {
                foreach ($columns as $columnName => $column) {
                    $choices[] = array(
                        'content_type' => $contentType,
                        'column' => $columnName,
                        'enabled' => isset($value['enabled'][$contentType]) &&
                             in_array($columnName, $value['enabled'][$contentType]),
                        'type' => $column['type']
                    );
                }
            }
        }

        $editLink = $view->createTemplateObject('option_list_option_editlink',
            array(
                'preparedOption' => $preparedOption,
                'canEditOptionDefinition' => $canEdit
            ));

        /* @var $searchModel XenForo_Model_Search */
        $searchModel = XenForo_Model::create('XenForo_Model_Search');

        $searchContentTypeOptions = array();
        foreach ($searchModel->getSearchDataHandlers() as $contentType => $handler) {
            $searchContentTypeOptions[$contentType] = $handler->getSearchContentTypePhrase();
        }

        return $view->createTemplateObject('waindigo_option_template_columns_searchandexport',
            array(
                'fieldPrefix' => $fieldPrefix,
                'listedFieldName' => $fieldPrefix . '_listed[]',
                'preparedOption' => $preparedOption,
                'formatParams' => $preparedOption['formatParams'],
                'editLink' => $editLink,

                'choices' => $choices,
                'nextCounter' => count($choices),
                'searchContentTypeOptions' => $searchContentTypeOptions
            ));
    } /* END renderOption */

    /**
     * Verifies and prepares the visible columns option to the correct format.
     *
     * @param array $columns List of columns to export. Keys: content_type,
     * column, enabled
     * @param XenForo_DataWriter $dw Calling DW
     * @param string $fieldName Name of field/option
     *
     * @return true
     */
    public static function verifyOption(array &$columns, XenForo_DataWriter $dw, $fieldName)
    {
        $enabled = array();
        $all = array();

        foreach ($columns as $column) {
            if (empty($column['content_type']) || empty($column['column'])) {
                continue;
            }

            if (!empty($column['enabled'])) {
                $enabled[$column['content_type']][] = $column['column'];
            }
            $all[$column['content_type']][$column['column']] = array(
                'type' => $column['type']
            );
        }

        $columns = array(
            'enabled' => $enabled,
            'all' => $all
        );

        return true;
    } /* END verifyOption */
}