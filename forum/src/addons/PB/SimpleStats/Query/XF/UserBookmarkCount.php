<?php


namespace PB\SimpleStats\Query\XF;


use PB\SimpleStats\Query\AbstractHandler;
use XF;

class UserBookmarkCount extends AbstractHandler
{
	use LinkUserTrait;

	protected function getDefaultOptions()
	{
		return [
			'display_id' => false,
		];
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
		return $this->db->fetchAll("
			SELECT user.user_id, user.username, COUNT(bookmark.bookmark_id) AS count
			FROM xf_bookmark_item AS bookmark
            INNER JOIN xf_user AS user ON
            	(bookmark.user_id = user.user_id)
			WHERE
				bookmark.bookmark_date BETWEEN ? AND ? 
            GROUP BY user.user_id
			ORDER BY COUNT(bookmark.bookmark_date) {$this->order}
			LIMIT {$this->limit}
		", [
			$this->startDate,
			$this->endDate,
		]);
	}
}
