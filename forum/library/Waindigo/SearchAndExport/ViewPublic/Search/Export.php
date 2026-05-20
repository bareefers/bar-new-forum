<?php

class Waindigo_SearchAndExport_ViewPublic_Search_Export extends XenForo_ViewPublic_Base
{

    public function renderRaw()
    {
        $results = $this->_params['results'];
        $contentType = $this->_params['contentType'];

        $results['results'] = $this->_removeOtherContentTypesFromResults($results['results'], $contentType);

        $searchExportProfileId = $this->_params['searchExportProfileId'];
        $searchExportProfiles = $this->_params['searchExportProfiles'];

        $searchExportProfile = array();
        if ($searchExportProfileId && !empty($searchExportProfiles[$searchExportProfileId])) {
            $searchExportProfile = $searchExportProfiles[$searchExportProfileId];
        }

        $enabledOnly = true;
        $columns = $this->_getColumns($contentType, $searchExportProfile, $enabledOnly);
        $serializedColumns = $this->_unserializeData($results['results'], $columns);

        $contents = array();

        $searchModel = XenForo_Model::create('XenForo_Model_Search');

        if (!$enabledOnly) {
            $columns = array();
        }

        $results = $searchModel->prepareSearchResultsForExport($results['results'], $columns, $serializedColumns);

        $firstResult = reset($results);
        $headers = array_keys($firstResult);

        $contents = $this->_getCsv($headers);

        foreach ($results as $result) {
            $contents .= $this->_getCsv($result);
        }

        $this->_response->setHeader('Content-Type: text/csv; charset=utf-8', true);
        $this->setDownloadFileName($contentType . '.csv');
        $this->_response->setHeader('Content-Length', strlen($contents), true);

        echo $contents;
    } /* END renderRaw */

    public function renderHtml()
    {
        $xenOptions = XenForo_Application::get('options');

        $results = $this->_params['results'];
        $contentType = $this->_params['contentType'];

        $results['results'] = $this->_removeOtherContentTypesFromResults($results['results'], $contentType);

        $searchExportProfileId = $this->_params['searchExportProfileId'];
        $searchExportProfiles = $this->_params['searchExportProfiles'];

        $searchExportProfile = array();
        if ($searchExportProfileId && !empty($searchExportProfiles[$searchExportProfileId])) {
            $searchExportProfile = $searchExportProfiles[$searchExportProfileId];
        }

        $enabledOnly = true;
        $columns = $this->_getColumns($contentType, $searchExportProfile, $enabledOnly);
        $serializedColumns = $this->_unserializeData($results['results'], $columns);

        $contents = array();

        $searchModel = XenForo_Model::create('XenForo_Model_Search');

        if (!$enabledOnly) {
            $columns = array();
        }

        $results = $searchModel->prepareSearchResultsForExport($results['results'], $columns, $serializedColumns);

        $headers = array_keys(reset($results));

        foreach ($results as $contentId => $result) {
            foreach ($result as $resultKey => $resultValue) {
                if (is_array($resultValue) || is_object($resultValue)) {
                    $results[$contentId][$resultKey] = serialize($resultValue);
                }
            }
        }

        $this->_params['headers'] = $headers;
        $this->_params['results'] = $results;
    } /* END renderHtml */

    protected function _getCsv($fields = array(), $delimiter = ',', $enclosure = '"')
    {
        $str = '';
        $escape_char = '\\';
        foreach ($fields as $value) {
            if (is_array($value) || is_object($value)) {
                $value = serialize($value);
            }
            if (strpos($value, $delimiter) !== false || strpos($value, $enclosure) !== false ||
                 strpos($value, "\n") !== false || strpos($value, "\r") !== false || strpos($value, "\t") !== false ||
                 strpos($value, ' ') !== false) {
                $str2 = $enclosure;
                $escaped = 0;
                $len = strlen($value);
                for ($i = 0; $i < $len; $i++) {
                    if ($value[$i] == $escape_char) {
                        $escaped = 1;
                    } else
                        if (!$escaped && $value[$i] == $enclosure) {
                            $str2 .= $enclosure;
                        } else {
                            $escaped = 0;
                        }
                    $str2 .= $value[$i];
                }
                $str2 .= $enclosure;
                $str .= $str2 . $delimiter;
            } else {
                $str .= $value . $delimiter;
            }
        }
        $str = substr($str, 0, -1);
        $str .= "\n";
        return $str;
    } /* END _getCsv */

    protected function _removeOtherContentTypesFromResults(array $results, $contentType)
    {
        foreach ($results as $resultKey => &$rows) {
            if ($rows[0] != $contentType) {
                unset($results[$resultKey]);
                continue;
            }
        }

        $results = array_values($results);

        return $results;
    } /* END _removeOtherContentTypesFromResults */

    protected function _getColumns($contentType, array $searchExportProfile = array(), &$enabledOnly = true)
    {
        $xenOptions = XenForo_Application::get('options');

        if (empty($xenOptions->waindigo_searchAndExport_visibleColumns['all'])) {
            return array();
        }

        $all = $xenOptions->waindigo_searchAndExport_visibleColumns['all'];
        $enabled = $xenOptions->waindigo_searchAndExport_visibleColumns['enabled'];

        $columns = array();
        if ($searchExportProfile) {
            foreach ($searchExportProfile['columns'] as $column) {
                if ($enabledOnly && !$column['enabled']) {
                    continue;
                }
                if (!empty($all[$contentType][$column['column']]) && $column['type'] == 'default') {
                    $columns[$column['column']] = $all[$contentType][$column['column']]['type'];
                    continue;
                }
                $columns[$column['column']] = $column['type'];
            }
        } else {
            if ($enabledOnly) {
                if (!empty($enabled[$contentType])) {
                    foreach ($enabled[$contentType] as $column) {
                        $columns[$column] = $all[$contentType][$column]['type'];
                    }
                }
            } else {
                if (!empty($all[$contentType])) {
                    foreach ($all[$contentType] as $columnName => $columnDetails) {
                        $columns[$columnName] = $columnDetails['type'];
                    }
                }
            }
        }

        if ($enabledOnly && empty($columns)) {
    if ($searchExportProfile) {
    }
    $enabledOnly = false;
    return $this->_getColumns($contentType, $searchExportProfile, $enabledOnly);
}

return $columns;
    } /* END _getColumns */

    protected function _unserializeData(array &$results, array $columns)
    {
        $serializedColumns = array();

        foreach ($columns as $columnName => $details) {
            if (preg_match('#^([^\[]*)\[([^\]]*)\]$#', $columnName, $matches)) {
                foreach ($results as $rowId => $rows) {
                    $results[$rowId]['content'][$columnName] = '';
                    if (isset($rows['content'][$matches[1]])) {
                        $array = $rows['content'][$matches[1]];
                        if (!is_array($array)) {
                            $array = @unserialize($array);
                        }
                        if (is_array($array) && !empty($array[$matches[2]])) {
                            $results[$rowId]['content'][$columnName] = $array[$matches[2]];
                        }
                    }
                }
            }
        }

        foreach ($results as $resultKey => &$rows) {
            $contentId = $rows[1];
            foreach ($rows['content'] as $columnName => $column) {
                if (!empty($columns[$columnName])) {
                    switch ($columns[$columnName]) {
                        case 'serialized':
                            $column = @unserialize($column);
                        case 'array':
                            if (is_array($column)) {
                                unset($rows['content'][$columnName]);
                                foreach ($column as $key => $value) {
                                    $rows['content'][$columnName . '[' . $key . ']'] = $value;
                                    $serializedColumns[$columnName][$key] = true;
                                }
                            }
                            break;
                        case 'timestamp':
                            $dateTime = @date('Y-m-d H:i:s', $column);
                            if ($dateTime) {
                                $rows['content'][$columnName] = $dateTime;
                            }
                            break;
                        case 'excel':
                            $rows['content'][$columnName] = '="' . str_replace('"', '""', $column) . '"';
                            break;
                        case 'string':
                        default:
                            break;
                    }
                }
            }
        }

        return $serializedColumns;
    } /* END _unserializeData */
}