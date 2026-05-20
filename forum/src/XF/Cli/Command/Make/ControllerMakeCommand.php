<?php

declare(strict_types=1);

namespace XF\Cli\Command\Make;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use XF\Util\Str;

use function in_array;

class ControllerMakeCommand extends AbstractClassMakeCommand
{
	/**
	 * @var string
	 */
	protected $type = 'Controller';

	/**
	 * @var string
	 */
	protected $controllerType = 'pub';

	protected function configure(): void
	{
		parent::configure();

		$this
			->setName('xf-make:controller')
			->setDescription('Create a new controller class')
			->addOption(
				'type',
				't',
				InputOption::VALUE_REQUIRED,
				'The type of controller: pub, admin, or api',
				'pub'
			);
	}

	protected function getDefaultSubNamespace(): string
	{
		switch ($this->controllerType)
		{
			case 'admin':
				return 'Admin\\Controller';
			case 'api':
				return 'Api\\Controller';
			default:
				return 'Pub\\Controller';
		}
	}

	protected function getStub(): string
	{
		switch ($this->controllerType)
		{
			case 'admin':
				return 'controller.admin.stub';
			case 'api':
				return 'controller.api.stub';
			default:
				return 'controller.pub.stub';
		}
	}

	protected function interact(InputInterface $input, OutputInterface $output): void
	{
		parent::interact($input, $output);

		$io = new SymfonyStyle($input, $output);

		$type = $input->getOption('type');
		if (!in_array($type, ['pub', 'admin', 'api']))
		{
			$type = $io->choice(
				'What type of controller?',
				[
					'pub' => 'Public (frontend)',
					'admin' => 'Admin (admin panel)',
					'api' => 'API',
				],
				'pub'
			);
			$input->setOption('type', $type);
		}
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$this->controllerType = $input->getOption('type');

		if (!in_array($this->controllerType, ['pub', 'admin', 'api']))
		{
			$io = new SymfonyStyle($input, $output);
			$io->error("Invalid controller type '$this->controllerType'. Must be: pub, admin, or api");
			return self::FAILURE;
		}

		return parent::execute($input, $output);
	}

	protected function buildClass(string $name): string
	{
		$stub = parent::buildClass($name);

		$className = $this->getClassName($name);
		$controllerName = $this->stripControllerSuffix($className);

		$addOnShortName = str_replace('/', '\\', $this->addOnId);
		$viewClass = $controllerName . '\\Index';
		$templateName = $this->generateTemplateName($controllerName);
		$permissionId = Str::toSnakeCase($controllerName);
		$apiGroup = $controllerName;
		$apiScope = Str::toSnakeCase($controllerName);

		return str_replace(
			[
				'{{ addOnId }}',
				'{{ viewClass }}',
				'{{ templateName }}',
				'{{ permissionId }}',
				'{{ apiGroup }}',
				'{{ apiScope }}',
			],
			[
				$addOnShortName,
				$viewClass,
				$templateName,
				$permissionId,
				$apiGroup,
				$apiScope,
			],
			$stub
		);
	}

	protected function stripControllerSuffix(string $name): string
	{
		if (substr($name, -10) === 'Controller')
		{
			return substr($name, 0, -10);
		}
		return $name;
	}

	protected function generateTemplateName(string $controllerName): string
	{
		$snakeName = Str::toSnakeCase($controllerName);

		switch ($this->controllerType)
		{
			case 'admin':
				return $snakeName . '_list';
			default:
				return $snakeName . '_index';
		}
	}

	protected function afterGenerate(InputInterface $input, OutputInterface $output, SymfonyStyle $io): void
	{
		$name = $input->getArgument('name');
		$qualifiedName = $this->qualifyClass($name);
		$className = $this->getClassName($qualifiedName);
		$controllerName = $this->stripControllerSuffix($className);

		$typeLabel = '';
		switch ($this->controllerType)
		{
			case 'admin':
				$typeLabel = 'Admin';
				break;
			case 'api':
				$typeLabel = 'API';
				break;
			default:
				$typeLabel = 'Public';
				break;
		}

		$rows = [
			['Class', $qualifiedName],
			['File', $this->getPath($qualifiedName)],
			['Type', $typeLabel],
		];

		if ($this->controllerType !== 'api')
		{
			$rows[] = ['Template', $this->generateTemplateName($controllerName)];
		}

		$io->table(['Property', 'Value'], $rows);
	}
}
