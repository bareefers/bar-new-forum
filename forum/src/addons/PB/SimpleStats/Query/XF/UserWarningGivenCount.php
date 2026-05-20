<?php


namespace PB\SimpleStats\Query\XF;


use PB\SimpleStats\Query\AbstractHandler;
use XF;

class UserWarningGivenCount extends AbstractHandler
{
	use LinkUserTrait;
	use OptionWarningTrait
	{
		getDefaultOptions as protected getDefaultReactionOptions;
	}

	protected function getDefaultOptions()
	{
		return $this->getDefaultReactionOptions() + ['display_id' => false];
	}

	public function getColumns(): array
	{
		return [
			'user_id' => [
				'title' => XF::phrase('user_id'),
				'class' => 'dataList-cell--min',
				'hide' => !$this->getOption('display_id'),
			],
			'username' => ['title' => XF::phrase('user_name')],
			'count' => [
				'title' => XF::phrase('total'),
				'class' => 'dataList-cell--min',
			],
		];
	}

	public function selectData(): array
	{
		$reportOptionsData = $this->getWhereQueryAndParams('warning');

		return $this->db->fetchAll("
			SELECT user.user_id, user.username, COUNT(`warning`.warning_user_id) AS count
			FROM xf_warning AS `warning`
			INNER JOIN xf_user AS user ON
			    (`warning`.warning_user_id = user.user_id)
			WHERE
				`warning`.warning_date BETWEEN ? AND ? 
				{$reportOptionsData['query']}
            GROUP BY user.user_id
			ORDER BY COUNT(`warning`.warning_user_id) {$this->order}
			LIMIT {$this->limit}
		", array_merge([$this->startDate, $this->endDate], $reportOptionsData['params']));
	}
}
