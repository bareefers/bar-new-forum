<?php


namespace PB\SimpleStats\Query\XFMG;


use PB\SimpleStats\Query\AbstractHandler;
use PB\SimpleStats\Query\XF\LinkUserTrait;
use PB\SimpleStats\Query\XF\OptionUserTrait;
use XF;

class UserMediaCount extends AbstractHandler
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
			'username' => ['title' => XF::phrase('user_name')],
			'count' => [
				'title' => XF::phrase('total'),
				'class' => 'dataList-cell--min',
			],
		];
	}

	public function selectData(): array
	{
		$userOptionsData = $this->getUserWhereQueryAndParams();

		return $this->db->fetchAll("
			SELECT user.user_id, user.username, COUNT(media.media_id) AS count
			FROM xf_mg_media_item AS media
			LEFT JOIN xf_mg_album AS album ON
			    (media.album_id = album.album_id)
            INNER JOIN xf_user AS user ON
            	(media.user_id = user.user_id)
			WHERE
				media.media_state = 'visible'
                AND (album.album_state = 'visible' OR album.album_hash IS NULL)
				AND media.media_date BETWEEN ? AND ? 
				{$userOptionsData['query']}
            GROUP BY user.user_id
			ORDER BY COUNT(media.media_id) {$this->order}
			LIMIT {$this->limit}
		", array_merge([$this->startDate, $this->endDate], $userOptionsData['params']));
	}
}
