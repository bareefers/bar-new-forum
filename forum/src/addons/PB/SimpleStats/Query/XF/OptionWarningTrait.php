<?php


namespace PB\SimpleStats\Query\XF;


trait OptionWarningTrait
{
	protected function filterOptions()
	{
		return [
			'content_types' => 'array-str',
			'is_expired' => 'int'
		];
	}

	protected function getDefaultOptions()
	{
		return [
			'content_types' => [],
			'is_expired' => -1
		];
	}

	public function getDefaultTemplateParams()
	{
		return parent::getDefaultTemplateParams() + [
				'contentTypes' => $this->app->getContentTypePhrases(true, 'warning_handler_class'),
			];
	}

	/**
	 * @return string|null
	 */
	public function getOptionsTemplate()
	{
		return 'admin:pb_ss_options_warning';
	}

	protected function getWhereQueryAndParams($tableName = 'warning')
	{
		$otherWhereConditions = [];
		$whereParams = [];
		$contentTypeConditions = '';

		$contentTypes = $this->getOption('content_types');
		if ($contentTypes)
		{
			$contentTypes = $this->db->quote($contentTypes);
			$contentTypeConditions .= " $tableName.content_type IN ($contentTypes) ";
		}

		if ($contentTypeConditions)
		{
			$otherWhereConditions[] = $contentTypeConditions;
		}

		// Build conditions

		$otherWhereSql = '';
		foreach ($otherWhereConditions as $otherWhereCondition)
		{
			$otherWhereSql .= ' AND (' . $otherWhereCondition . ') ';
		}

		return ['query' => $otherWhereSql, 'params' => $whereParams];
	}
}