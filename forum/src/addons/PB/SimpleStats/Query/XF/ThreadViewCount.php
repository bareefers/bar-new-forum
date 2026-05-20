<?php


namespace PB\SimpleStats\Query\XF;


use PB\SimpleStats\Addon;
use PB\SimpleStats\Query\AbstractHandler;
use XF;

class ThreadViewCount extends AbstractHandler
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
				'title' => XF::phrase('views'),
				'class' => 'dataList-cell--min',
			],
		];
	}

	protected function selectData(): array
	{
		$nodeOptionsData = $this->getNodeWhereQueryAndParams('thread');

		return $this->db->fetchAll("
			SELECT thread.thread_id, thread.title, thread.view_count AS count
			FROM xf_thread AS thread
			WHERE
				thread.post_date BETWEEN ? AND ?
				AND thread.discussion_state = 'visible'
				{$nodeOptionsData['query']}
			ORDER BY thread.view_count {$this->order}
			LIMIT {$this->limit}
		", array_merge([$this->startDate, $this->endDate], $nodeOptionsData['params']));
	}
}
