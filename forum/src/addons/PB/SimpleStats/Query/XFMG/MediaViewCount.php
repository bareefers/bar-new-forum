<?php


namespace PB\SimpleStats\Query\XFMG;


use PB\SimpleStats\Query\AbstractHandler;
use XF;

class MediaViewCount extends AbstractHandler
{
	use LinkMediaTrait;

	protected function getDefaultOptions()
	{
		return [
			'display_id' => false,
		];
	}

	public function getColumns(): array
	{
		return [
			'media_id' => [
				'title' => 'ID',
				'class' => 'dataList-cell--min',
				'hide' => !$this->getOption('display_id'),
			],
			'title' => ['title' => XF::phrase('title')],
			'description' => ['title' => XF::phrase('description')],
			'count' => [
				'title' => XF::phrase('views'),
				'class' => 'dataList-cell--min',
			],
		];
	}

	public function selectData(): array
	{
		return $this->db->fetchAll("
			SELECT media.media_id, media.title, media.view_count AS count
			FROM xf_mg_media_item AS media
			LEFT JOIN xf_mg_album AS album ON
			    (media.album_id = album.album_id)
            INNER JOIN xf_user AS user ON
            	(media.user_id = user.user_id)
			WHERE
				media.media_state = 'visible'
                AND (album.album_state = 'visible' OR album.album_hash IS NULL)
				AND media.media_date BETWEEN ? AND ? 
			ORDER BY media.view_count {$this->order}
			LIMIT ?
		", [
			$this->startDate,
			$this->endDate,
			$this->limit,
		]);
	}
}
