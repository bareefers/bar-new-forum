<?php


namespace PB\SimpleStats\Query\XF;


use PB\SimpleStats\Addon;
use PB\SimpleStats\Query\AbstractHandler;
use XF;

class ThreadWatchCount extends AbstractHandler
{
	use LinkThreadTrait;
	use OptionNodeTrait
	{
		getDefaultOptions as protected getDefaultNodeOptions;
	}

	protected function getDefaultOptions()
	{
		return $this->getDefaultNodeOptions() + ['display_id' => false];
	}

	public function getColumns(): array
	{
		return [
			'thread_id' => [
				'title' => Addon::phrase('thread_id'),
				'class' => 'dataList-cell--min',
				'hide' => !$this->getOption('display_id'),
			],
			'title' => [
				'title' => XF::phrase('title'),
			],
			'count' => [
				'title' => XF::phrase('total'),
				'class' => 'dataList-cell--min',
			],
		];
	}

	protected function selectData(): array
	{
		$nodeOptionsData = $this->getNodeWhereQueryAndParams('thread');

		return $this->db->fetchAll("
			SELECT thread.thread_id, thread.title, COUNT(watch.thread_id) AS count
			FROM xf_thread_watch AS watch
			INNER JOIN xf_thread AS thread ON
				(watch.thread_id = thread.thread_id)
			WHERE
				thread.post_date BETWEEN ? AND ?
				AND thread.discussion_state = 'visible'
				{$nodeOptionsData['query']}
			GROUP BY watch.thread_id
			ORDER BY COUNT(watch.thread_id) {$this->order}
			LIMIT {$this->limit}
		", array_merge([$this->startDate, $this->endDate], $nodeOptionsData['params']));
	}
}
