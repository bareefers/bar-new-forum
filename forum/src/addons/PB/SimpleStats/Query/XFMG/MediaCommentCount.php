<?php


namespace PB\SimpleStats\Query\XFMG;


use PB\SimpleStats\Query\AbstractHandler;
use XF;

class MediaCommentCount extends AbstractHandler
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
				'title' => XF::phrase('comments'),
				'class' => 'dataList-cell--min',
			],
		];
	}

	public function selectData(): array
	{
		return $this->db->fetchAll("
			SELECT media.media_id,
				media.title,
				media.description,
				COUNT(comment.comment_id) AS count
			FROM xf_mg_comment AS comment
			INNER JOIN xf_mg_media_item AS media ON
				comment.content_id = media.media_id
			LEFT JOIN xf_mg_album AS album ON
			    (media.album_id = album.album_id)
			WHERE
			    comment.content_type = 'xfmg_media'
				AND media.media_state = 'visible'
                AND (album.album_state = 'visible' OR album.album_hash IS NULL)
				AND media.media_date BETWEEN ? AND ? 
			GROUP BY media.media_id
			ORDER BY COUNT(comment.comment_id) {$this->order}
			LIMIT ?
		", [
			$this->startDate,
			$this->endDate,
			$this->limit,
		]);
	}
}
