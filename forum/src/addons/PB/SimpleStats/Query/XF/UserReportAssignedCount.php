<?php


namespace PB\SimpleStats\Query\XF;


use PB\SimpleStats\Query\AbstractHandler;
use XF;

class UserReportAssignedCount extends AbstractHandler
{
	use LinkUserTrait;
	use OptionReportTrait
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
		$reportOptionsData = $this->getReportWhereQueryAndParams('report');

		return $this->db->fetchAll("
			SELECT user.user_id, user.username, COUNT(`report`.assigned_user_id) AS count
			FROM xf_report AS `report`
			INNER JOIN xf_user AS user ON
			    (`report`.assigned_user_id = user.user_id)
			WHERE
				`report`.first_report_date BETWEEN ? AND ? 
				{$reportOptionsData['query']}
            GROUP BY user.user_id
			ORDER BY COUNT(report.assigned_user_id) {$this->order}
			LIMIT {$this->limit}
		", array_merge([$this->startDate, $this->endDate], $reportOptionsData['params']));
	}
}
