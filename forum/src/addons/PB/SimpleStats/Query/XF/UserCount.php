<?php


namespace PB\SimpleStats\Query\XF;


use PB\SimpleStats\Query\AbstractHandler;
use XF;

class UserCount extends AbstractHandler
{
	use OptionUserTrait;

	public function getColumns(): array
	{
		return [
			'count' => ['title' => XF::phrase('total')],
		];
	}

	public function selectData(): array
	{
		$userOptionsData = $this->getUserWhereQueryAndParams();

		return $this->db->fetchAll("
			SELECT COUNT(user.user_id) AS count
			FROM xf_user AS user
			WHERE
				user.register_date BETWEEN ? AND ? 
		" . $userOptionsData['query'],
			array_merge([$this->startDate, $this->endDate], $userOptionsData['params'])
		);
	}
}
