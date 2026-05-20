<?php


namespace PB\SimpleStats\Query\XF;


use PB\SimpleStats\Query\AbstractHandler;
use XF;

class ThreadCount extends AbstractHandler
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
			SELECT COUNT(thread.thread_id) AS count
			FROM xf_thread AS thread
			WHERE
				thread.post_date BETWEEN ? AND ?
				AND thread.discussion_state = 'visible'
				{$nodeOptionsData['query']}
		", array_merge([$this->startDate, $this->endDate], $nodeOptionsData['params']));
	}
}
