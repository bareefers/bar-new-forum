<?php


namespace PB\SimpleStats\Query\XF;


use PB\SimpleStats\Query\AbstractHandler;
use XF;

class UserAttachmentSize extends AbstractHandler
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
			'username' => [
				'title' => XF::phrase('user_name'),
			],
			'size' => [
				'title' => XF::phrase('size'),
				'filters' => [['file_size', '']],
				'class' => 'dataList-cell--min',
			],
		];
	}

	protected function selectData(): array
	{
		$userOptionsData = $this->getUserWhereQueryAndParams();

		return $this->db->fetchAll("
			SELECT user.user_id, user.username, SUM(attachment_data.file_size) AS size
			FROM xf_attachment_data AS attachment_data
            INNER JOIN xf_user AS user ON
            	(attachment_data.user_id = user.user_id)
			WHERE
				attachment_data.upload_date BETWEEN ? AND ?
				{$userOptionsData['query']}
            GROUP BY user.user_id
			ORDER BY SUM(attachment_data.file_size) {$this->order}
			LIMIT {$this->limit}
		",
			array_merge([$this->startDate, $this->endDate], $userOptionsData['params'])
		);
	}
}
