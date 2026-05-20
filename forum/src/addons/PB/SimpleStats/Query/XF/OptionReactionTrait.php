<?php


namespace PB\SimpleStats\Query\XF;


trait OptionReactionTrait
{
	protected function filterOptions()
	{
		return [
			'reaction_ids' => 'array-int',
			'content_types' => 'array-str'
		];
	}

	protected function getDefaultOptions()
	{
		return [
			'reaction_ids' => 0,
			'content_types' => []
		];
	}

	public function getDefaultTemplateParams()
	{
		return parent::getDefaultTemplateParams() + [
				'reactionsCache' => $this->app->container('reactions'),
				'contentTypes' => $this->app->getContentTypePhrases(true, 'reaction_handler_class')
			];
	}

	/**
	 * @return string|null
	 */
	public function getOptionsTemplate()
	{
		return 'admin:pb_ss_options_reaction';
	}

	protected function getReactionWhereQueryAndParams($reactionTableName = 'reaction', $reactionContentTableName = 'reaction_content')
	{
		$otherWhereConditions = [];
		$whereParams = [];
		$reactionIdConditions = '';

		$reactionIds = $this->getOption('reaction_ids');
		if ($reactionIds)
		{
			$reactionIdConditions .= " $reactionTableName.reaction_id IN(?) ";
			$whereParams[] = implode(', ', $reactionIds);
		}

		if ($reactionIdConditions)
		{
			$otherWhereConditions[] = $reactionIdConditions;
		}

		// Content types

		$contentTypeConditions = '';

		$contentTypes = $this->getOption('content_types');
		if ($contentTypes)
		{
			$contentTypes = $this->db->quote($contentTypes);
			$contentTypeConditions .= " $reactionContentTableName.content_type IN ($contentTypes) ";
		}

		if ($contentTypeConditions)
		{
			$otherWhereConditions[] = $contentTypeConditions;
		}

		$otherWhereSql = '';
		foreach ($otherWhereConditions as $otherWhereCondition)
		{
			$otherWhereSql .= ' AND (' . $otherWhereCondition . ') ';
		}

		return ['query' => $otherWhereSql, 'params' => $whereParams];
	}
}