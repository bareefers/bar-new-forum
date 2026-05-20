<?php


namespace PB\SimpleStats\Query\XFRM;


use PB\SimpleStats\Query\AbstractHandler;
use PB\SimpleStats\Query\XF\LinkUserTrait;
use PB\SimpleStats\Query\XF\OptionUserTrait;
use XF;

class UserRatingCount extends AbstractHandler
{
	use LinkUserTrait;
	use OptionUserTrait
	{
		getDefaultOptions as protected getDefaultUserOptions;
	}

	protected function getDefaultOptions()
	{
		return $this->getDefaultUserOptions() + ['display_id' => false];
	}

	public function getColumns(): array
	{
		return [
			'user_id' => [
				'title' => XF::phrase('user_id'),
				'class' => 'dataList-cell--min',
				'hide' => !$this->getOption('display_id'),
			],
			'username' =>  ['title' => XF::phrase('user_name')],
			'count' =>  [
				'title' => XF::phrase('total'),
				'class' => 'dataList-cell--min',
			],
		];
	}

	public function selectData(): array
	{
		$userOptionsData = $this->getUserWhereQueryAndParams();

		return $this->db->fetchAll("
			SELECT user.user_id, user.username, COUNT(rating.resource_rating_id) AS count
			FROM xf_rm_resource_rating AS rating
			INNER JOIN xf_rm_resource AS resource ON
            	(resource.resource_id = rating.resource_id)
            INNER JOIN xf_user AS user ON
            	(rating.user_id = user.user_id)
			WHERE
				resource.resource_state = 'visible'
				AND rating.rating_date BETWEEN ? AND ? 
				{$userOptionsData['query']}
            GROUP BY user.user_id
			ORDER BY COUNT(rating.resource_rating_id) {$this->order}
			LIMIT {$this->limit}
		", array_merge([$this->startDate, $this->endDate], $userOptionsData['params']));
	}
}
