<?php


namespace PB\SimpleStats\Query\XFRM;


use PB\SimpleStats\Query\AbstractHandler;
use PB\SimpleStats\Query\XF\OptionUserTrait;
use XF;

class ResourceUpdateCount extends AbstractHandler
{
	use LinkResourceTrait;
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
			'resource_id' => [
				'title' => XF::phrase('title'),
				'class' => 'dataList-cell--min',
				'hide' => !$this->getOption('display_id'),
			],
			'title' => ['title' => XF::phrase('title')],
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
			SELECT resource.resource_id, resource.title, COUNT(resupdate.resource_id) AS count
			FROM xf_rm_resource AS resource
            INNER JOIN xf_rm_resource_update AS resupdate ON
            	(resource.resource_id = resupdate.resource_id)
			WHERE
				resource.resource_state = 'visible'
			  	AND resupdate.resource_update_id != resource.description_update_id
				AND resource.resource_date BETWEEN ? AND ? 
				{$userOptionsData['query']}
            GROUP BY resupdate.resource_id
			ORDER BY COUNT(resupdate.resource_id) {$this->order}
			LIMIT {$this->limit}
		", array_merge([$this->startDate, $this->endDate], $userOptionsData['params']));
	}
}
