<?php


namespace PB\SimpleStats\Query\XF;


use PB\SimpleStats\Query\AbstractHandler;
use XF;

class PostCount extends AbstractHandler
{
	use OptionNodeTrait;

	public function getColumns(): array
	{
		return [
			'count' => ['title' => XF::phrase('total')],
		];
	}

	protected function selectData(): array
	{
		$nodeOptionsData = $this->getNodeWhereQueryAndParams('thread');

		return $this->db->fetchAll("
			SELECT COUNT(post.post_id) AS count
			FROM xf_post AS post
			INNER JOIN xf_thread AS thread ON
				(post.thread_id = thread.thread_id)
			WHERE
				thread.post_date BETWEEN ? AND ?
				AND post.message_state = 'visible'
				AND thread.discussion_state = 'visible'
				{$nodeOptionsData['query']}
		", array_merge([$this->startDate, $this->endDate], $nodeOptionsData['params']));
	}
}
