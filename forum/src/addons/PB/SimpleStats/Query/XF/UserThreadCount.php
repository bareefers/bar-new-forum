<?php


namespace PB\SimpleStats\Query\XF;


use PB\SimpleStats\Query\AbstractHandler;
use XF;

class UserThreadCount extends AbstractHandler
{
	use LinkUserTrait;
	use OptionUserTrait
	{
		OptionUserTrait::getDefaultOptions as protected getDefaultUserOptions;
		OptionUserTrait::filterOptions as protected filterUserOptions;
	}
	use OptionNodeTrait
	{
		OptionNodeTrait::getDefaultOptions as protected getDefaultNodeOptions;
		OptionNodeTrait::filterOptions as protected filterNodeOptions;
	}

	protected function filterOptions()
	{
		return $this->filterNodeOptions() + $this->filterUserOptions();
	}

	public function getOptionsTemplate()
	{
		return 'admin:pb_ss_options_user_node';
	}

	protected function getDefaultOptions()
	{
		return $this->getDefaultNodeOptions() + $this->getDefaultUserOptions() + ['display_id' => false];
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

	protected function selectData(): array
	{
		$nodeOptionsData = $this->getNodeWhereQueryAndParams('thread');
		$userOptionsData = $this->getUserWhereQueryAndParams('user');

		return $this->db->fetchAll("
			SELECT user.user_id, user.username, COUNT(thread.thread_id) AS count
			FROM xf_thread AS thread
			INNER JOIN xf_forum AS forum ON
				(thread.node_id = forum.node_id)
            INNER JOIN xf_user AS user ON
            	(thread.user_id = user.user_id)
			WHERE
				forum.count_messages = 1
				AND thread.post_date BETWEEN ? AND ?
				{$nodeOptionsData['query']} {$userOptionsData['query']}
            GROUP BY user.user_id
			ORDER BY COUNT(thread.thread_id) {$this->order}
			LIMIT {$this->limit}
		", array_merge([$this->startDate, $this->endDate], $nodeOptionsData['params'] + $userOptionsData['params']));
	}
}
