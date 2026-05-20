<?php


namespace PB\SimpleStats\Query\XF;


trait OptionReportTrait
{
	protected function filterOptions()
	{
		return [
			'content_types' => 'array-str',
			'report_states' => 'array-str'
		];
	}

	protected function getDefaultOptions()
	{
		return [
			'content_types' => [],
			'report_states' => []
		];
	}

	public function getReportStates()
	{
		return [
			'open' => \XF::phrase('report_state.open'),
			'assigned' => \XF::phrase('report_state.assigned'),
			'resolved' => \XF::phrase('report_state.resolved'),
			'rejected' => \XF::phrase('report_state.rejected')
		];
	}

	public function getDefaultTemplateParams()
	{
		return parent::getDefaultTemplateParams() + [
				'contentTypes' => $this->app->getContentTypePhrases(true, 'report_handler_class'),
				'reportStates' => $this->getReportStates()
			];
	}

	/**
	 * @return string|null
	 */
	public function getOptionsTemplate()
	{
		return 'admin:pb_ss_options_report';
	}

	protected function getReportWhereQueryAndParams($tableName = 'report')
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

		$reportStateConditions = '';
		if ($reportStates = $this->getOption('report_states'))
		{
			$reportStates = $this->db->quote($reportStates);
			$reportStateConditions .= " $tableName.report_state IN ($reportStates) ";
		}

		if ($reportStateConditions)
		{
			$otherWhereConditions[] = $reportStateConditions;
		}

		$otherWhereSql = '';
		foreach ($otherWhereConditions as $otherWhereCondition)
		{
			$otherWhereSql .= ' AND (' . $otherWhereCondition . ') ';
		}

		return ['query' => $otherWhereSql, 'params' => $whereParams];
	}
}