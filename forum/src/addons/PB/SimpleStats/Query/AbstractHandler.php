<?php


namespace PB\SimpleStats\Query;


use XF\App;

abstract class AbstractHandler
{
	/**
	 * @var App
	 */
	protected $app;

	/**
	 * @var \XF\Db\AbstractAdapter
	 */
	protected $db;

	public $startDate;
	public $endDate;
	public $limit;
	public $order;

	protected $options;

	protected $data = [];

	public function __construct(App $app)
	{
		$this->app = $app;
		$this->db = $app->db();
		$this->setupDefaults();
		$this->options = $this->getDefaultOptions();
	}

	public function setupDefaults()
	{
		$this->startDate = 0;
		$this->endDate = PHP_INT_MAX;
		$this->limit = 100;
		$this->order = 'DESC';
	}

	public function setStartDate(int $startDate)
	{
		$this->startDate = $startDate;
	}

	public function setEndDate(int $endDate)
	{
		$this->endDate = $endDate;
	}

	public function setLimit(int $limit)
	{
		$this->limit = $limit;
	}

	public function setDirection(string $order)
	{
		$order = strtoupper($order);
		if (in_array($order, ['DESC', 'ASC']))
		{
			$this->order = $order;
		}
	}

	protected function filterOptions()
	{
		return [];
	}

	protected function getDefaultOptions()
	{
		return [];
	}

	public function getOptions()
	{
		return $this->options;
	}

	public function getOption($name)
	{
		if (!array_key_exists($name, $this->options))
		{
			throw new \InvalidArgumentException("Invalid option '$name'");
		}

		return $this->options[$name];
	}

	public function setOption($name, $value)
	{
		if (!array_key_exists($name, $this->options))
		{
			throw new \InvalidArgumentException("Invalid option '$name'");
		}

		$filters = $this->filterOptions();
		if (isset($filters[$name]))
		{
			$filterer = $this->app->inputFilterer();
			$value = $filterer->filter($value, $filters[$name]);
		}

		$this->options[$name] = $value;
	}

	public function setOptions(array $options)
	{
		foreach ($options as $key => $option)
		{
			$this->setOption($key, $option);
		}
	}

	public function renderOptions()
	{
		$templateName = $this->getOptionsTemplate();
		if (!$templateName)
		{
			return '';
		}
		return $this->app->templater()->renderTemplate(
			$templateName, $this->getDefaultTemplateParams()
		);
	}

	/**
	 * @return string|null
	 */
	public function getOptionsTemplate()
	{
		return null;
	}

	public function getDefaultTemplateParams()
	{
		return [
			'options' => $this->options
		];
	}

	public function getPrimaryKey($value = false)
	{
		$key = '';

		foreach ($this->data[0] as $column => $unused)
		{
			$key = $column;
			break;
		}

		return $value ? $this->data[0][$key] : $key;
	}

	/**
	 * @return array
	 *        ['column']                 string
	 *            ['title']              string
	 *            ['url']                string Route to content
	 *            ['class']              string data-row class
	 *            ['filters']            array template filters to format value
	 */
	public abstract function getColumns(): array;

	protected function selectData(): array
	{
		throw new \LogicException('Method selectData() must be overridden');
	}

	public function formatValue($key, $value)
	{
		$statColumns = $this->getColumns();

		if (isset($statColumns[$key]['filters']))
		{
			$value = $this->app->templater()->filter($value, $statColumns[$key]['filters']);
		}

		return $value;
	}

	protected function assertUrl($stat, $key, $value, bool $canonical = false)
	{
		return null;
	}

	public function getSelectData($toExport)
	{
		$this->data = $this->selectData();

		$formattedData = [];

		foreach ($this->data as $stat)
		{
			foreach ($stat as $key => $value)
			{
				$url = $this->assertUrl($stat, $key, $value, $toExport);
				if ($url)
				{
					$stat['urls'][$key] = $url;
				}

				$stat[$key] = $this->formatValue($key, $value);
			}

			$formattedData[] = $stat;
		}

		return $formattedData;
	}
}