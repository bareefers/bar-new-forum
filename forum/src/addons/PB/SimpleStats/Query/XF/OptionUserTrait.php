<?php


namespace PB\SimpleStats\Query\XF;


trait OptionUserTrait
{
	protected function filterOptions()
	{
		return [
			'user_state' => 'array-str',
			'message_count' => 'array-int',
		];
	}

	protected function getDefaultOptions()
	{
		return [
			'user_state' => [],
			'message_count' => ['start' => 0, 'end' => -1],
		];
	}

	/**
	 * @return string|null
	 */
	public function getOptionsTemplate()
	{
		return 'admin:pb_ss_options_user';
	}

	protected function getUserWhereQueryAndParams($userTableName = 'user')
	{
		$otherWhereConditions = [];
		$whereParams = [];
		$userStateConditions = '';

		foreach ($this->getOption('user_state') as $state)
		{
			if ($userStateConditions)
			{
				$userStateConditions .= " OR $userTableName.user_state = ?";
				$whereParams[] = $state;
			}
			else
			{
				$userStateConditions .= " $userTableName.user_state = ? ";
				$whereParams[] = $state;
			}
		}

		if ($userStateConditions)
		{
			$otherWhereConditions[] = $userStateConditions;
		}

		$messageCountConditions = '';

		$messageCount = $this->getOption('message_count');
		$minMessageCount = $messageCount['start'];
		$maxMessageCount = $messageCount['end'];
		$hasStart = $minMessageCount != 0;
		$hasEnd = $maxMessageCount != -1;

		if ($hasStart || $hasEnd)
		{
			if ($hasStart && $hasEnd)
			{
				$messageCountConditions .= " $userTableName.message_count BETWEEN ? AND ? ";
				$whereParams[] = $minMessageCount;
				$whereParams[] = $maxMessageCount;
			}
			else if ($hasStart)
			{
				$messageCountConditions .= " $userTableName.message_count > ? ";
				$whereParams[] = $minMessageCount;
			}
			else
			{
				$messageCountConditions .= " $userTableName.message_count < ? ";
				$whereParams[] = $maxMessageCount;
			}
		}

		if ($messageCountConditions)
		{
			$otherWhereConditions[] = $messageCountConditions;
		}

		$otherWhereSql = '';
		foreach ($otherWhereConditions as $otherWhereCondition)
		{
			$otherWhereSql .= ' AND (' . $otherWhereCondition . ') ';
		}

		return ['query' => $otherWhereSql, 'params' => $whereParams];
	}
}