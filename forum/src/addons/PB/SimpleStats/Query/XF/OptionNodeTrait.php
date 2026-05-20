<?php


namespace PB\SimpleStats\Query\XF;


trait OptionNodeTrait
{
	protected function filterOptions()
	{
		return [
			'node_ids' => 'array-uint',
		];
	}

	protected function getDefaultOptions()
	{
		return [
			'node_ids' => [],
		];
	}

	public function getDefaultTemplateParams()
	{
		/** @var \XF\Repository\Node $nodeRepo */
		$nodeRepo = $this->app->repository('XF:Node');
		return parent::getDefaultTemplateParams() + [
				'forums' => $nodeRepo->getNodeOptionsData(false, 'Forum')
			];
	}

	/**
	 * @return string|null
	 */
	public function getOptionsTemplate()
	{
		return 'admin:pb_ss_options_node';
	}

	protected function getNodeWhereQueryAndParams($nodeTableName = 'node')
	{
		$otherWhereConditions = [];
		$whereParams = [];
		$reactionIdConditions = '';

		$nodeIds = $this->getOption('node_ids');
		if ($nodeIds)
		{
			$nodeIds = implode(',', $nodeIds);
			$nodeIds = $this->db->escapeString($nodeIds);
			$reactionIdConditions .= " $nodeTableName.node_id IN ($nodeIds) ";
		}

		if ($reactionIdConditions)
		{
			$otherWhereConditions[] = $reactionIdConditions;
		}

		$otherWhereSql = '';
		foreach ($otherWhereConditions as $otherWhereCondition)
		{
			$otherWhereSql .= ' AND (' . $otherWhereCondition . ') ';
		}

		return ['query' => $otherWhereSql, 'params' => $whereParams];
	}
}