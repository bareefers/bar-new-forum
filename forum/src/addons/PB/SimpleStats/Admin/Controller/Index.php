<?php


namespace PB\SimpleStats\Admin\Controller;


use PB\SimpleStats\Addon;
use PB\SimpleStats\Query\AbstractHandler;
use XF\Admin\Controller\AbstractController;
use XF\Mvc\ParameterBag;

class Index extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertAdminPermission('viewStatistics');
	}

	public function actionIndex()
	{
		$stats = [];
		$statColumns = [];
		$filters = $this->getFilterInput(true);

		if ($filters && $this->isPost())
		{
			return $this->redirect($this->buildLink('simplestats', null, $filters), '');
		}

		$queryRepo = $this->getRepo();
		$options = '';

		$toExport = !empty($filters['export']);

		if (!empty($filters['query']))
		{
			$queryHandler = $queryRepo->getHandlerForQueryType($filters['query']);

			if (!$queryHandler)
			{
				return $this->notFound();
			}

			$this->applyFilters($queryHandler);
			$stats = $queryHandler->getSelectData($toExport);
			$statColumns = $queryHandler->getColumns();
			$options = $queryHandler->renderOptions();
		}

		$viewParams = [
			'link' => $this->buildLink('admin:simplestats'),
			'stats' => $stats,
			'options' => $options,
			'statColumns' => $statColumns,
			'availableQueries' => $queryRepo->getAvailableQueryTypes(true, true),
			'conditions' => $filters,
		];

		if ($toExport)
		{
			$templateName = Addon::prefix('stat_export_bbcode');
		}
		else
		{
			$templateName = Addon::prefix('stat_list');
		}

		return $this->view(Addon::shortName('Index\List'), $templateName, $viewParams);
	}

	public function actionOptions()
	{
		$filters = $this->getFilterInput(true);
		$queryRepo = $this->getRepo();
		$template = '';
		$params = [];

		if (!empty($filters['query']))
		{
			$queryHandler = $queryRepo->getHandlerForQueryType($filters['query']);
			if ($queryHandler)
			{
				$template = $queryHandler->getOptionsTemplate();
				$params = $queryHandler->getDefaultTemplateParams();
			}
		}

		return $this->view(Addon::shortName('Index\Options'), $template, $params);
	}

	private function applyFilters(AbstractHandler $queryHandler)
	{
		$filters = $this->getFilterInput();

		if ($filters['direction'])
		{
			$queryHandler->setDirection($filters['direction']);
		}

		if ($filters['limit'])
		{
			$queryHandler->setLimit($filters['limit']);
		}

		if ($filters['start_date'])
		{
			$queryHandler->setStartDate($filters['start_date']);
		}
		if ($filters['end_date'])
		{
			$queryHandler->setEndDate($filters['end_date']);
		}

		if ($filters['options'])
		{
			try
			{
				$queryHandler->setOptions($filters['options']);
			} catch (\InvalidArgumentException $e)
			{
			}
		}
	}

	private function getFilterInput($removeEmpty = false): array
	{
		$input = $this->filter([
			'query' => 'str',
			'direction' => 'str',
			'limit' => 'uint',
			'start_date' => 'datetime',
			'end_date' => 'datetime',
			'options' => 'array',
			'export' => 'bool'
		]);

		if ($removeEmpty)
		{
			$input = array_filter($input);
		}

		return $input;
	}

	/**
	 * @return \XF\Mvc\Entity\Repository|\PB\SimpleStats\Repository\Query
	 */
	protected function getRepo()
	{
		return $this->repository(Addon::shortName('Query'));
	}
}