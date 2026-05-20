<?php

declare(strict_types=1);

namespace XF\Cli\Command\Make;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use XF\Entity\Route;
use XF\Finder\RouteFinder;
use XF\Util\Str;

use function in_array;

class RouteMakeCommand extends AbstractMakeCommand
{
	protected const ROUTE_TYPES = ['public', 'admin', 'api'];

	protected function configure(): void
	{
		parent::configure();

		$this
			->setName('xf-make:route')
			->setDescription('Create a route and optionally its controller')
			->addArgument(
				'prefix',
				InputArgument::REQUIRED,
				'Route prefix (e.g. "articles" or "Demo/Blog:articles")'
			)
			->addOption(
				'type',
				't',
				InputOption::VALUE_REQUIRED,
				'Route type: public, admin, or api',
				'public'
			)
			->addOption(
				'sub-name',
				's',
				InputOption::VALUE_REQUIRED,
				'Sub-route name',
				''
			)
			->addOption(
				'format',
				null,
				InputOption::VALUE_REQUIRED,
				'URL format pattern (e.g. ":int<article_id>/")',
				''
			)
			->addOption(
				'controller',
				'c',
				InputOption::VALUE_REQUIRED,
				'Controller class name (relative to type namespace)'
			)
			->addOption(
				'context',
				null,
				InputOption::VALUE_REQUIRED,
				'Route context',
				''
			)
			->addOption(
				'action-prefix',
				null,
				InputOption::VALUE_REQUIRED,
				'Action prefix',
				''
			)
			->addOption(
				'no-controller',
				null,
				InputOption::VALUE_NONE,
				'Skip controller creation'
			);
	}

	protected function interact(InputInterface $input, OutputInterface $output): void
	{
		$io = new SymfonyStyle($input, $output);

		$this->interactAddOn($input, $io, 'prefix', false);

		if (!$input->getArgument('prefix'))
		{
			$prefix = $io->ask(
				'Enter the route prefix (e.g. articles)',
				null,
				function ($value)
				{
					if (empty($value))
					{
						throw new \InvalidArgumentException('Prefix cannot be empty.');
					}
					return $value;
				}
			);
			$input->setArgument('prefix', $prefix);
		}

		$type = $input->getOption('type');
		if (!in_array($type, self::ROUTE_TYPES, true))
		{
			$type = $io->choice(
				'What type of route?',
				[
					'public' => 'Public (frontend)',
					'admin' => 'Admin (admin panel)',
					'api' => 'API',
				],
				'public'
			);
			$input->setOption('type', $type);
		}

		// For API routes, prompt for controller name if not provided, due to
		// different conventions regarding singular and pluralised controller names
		if ($type === 'api' && !$input->getOption('controller') && !$input->getOption('no-controller'))
		{
			$prefix = $input->getArgument('prefix');
			$suggestedName = $this->deriveControllerName($prefix);

			$controllerName = $io->ask(
				'What should the controller be named?',
				$suggestedName
			);
			$input->setOption('controller', $controllerName);
		}
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);

		$addOnId = $input->getOption('addon');
		if (!$addOnId)
		{
			$io->error('The --addon option is required.');
			return Command::FAILURE;
		}

		if (!$this->validateAddOn($addOnId, $io))
		{
			return Command::FAILURE;
		}

		$prefix = $input->getArgument('prefix');
		$routeType = $input->getOption('type');
		$subName = $input->getOption('sub-name');
		$format = $input->getOption('format');
		$context = $input->getOption('context');
		$actionPrefix = $input->getOption('action-prefix');
		$noController = $input->getOption('no-controller');
		$force = $input->getOption('force');

		if (!in_array($routeType, self::ROUTE_TYPES, true))
		{
			$io->error("Invalid route type '$routeType'. Must be: public, admin, or api");
			return Command::FAILURE;
		}

		if (!preg_match('/^[a-z0-9_-]+$/i', $prefix))
		{
			$io->error('Route prefix must contain only letters, numbers, hyphens, and underscores.');
			return Command::FAILURE;
		}

		$existingRoute = $this->getExistingRoute($routeType, $prefix, $subName);
		$routeName = $prefix . '/' . $subName;

		if ($existingRoute && !$force)
		{
			$io->error("Route '$routeName' already exists for type '$routeType'.");
			$io->note('Use --force to overwrite.');
			return Command::FAILURE;
		}

		$controllerName = $input->getOption('controller');
		if (!$controllerName)
		{
			$controllerName = $this->deriveControllerName($prefix);
			if ($routeType !== 'api')
			{
				$controllerName = $this->singularize($controllerName);
			}
		}

		$fullControllerClass = $this->getFullControllerClass($controllerName, $routeType);

		$templateName = null;

		if (!$noController)
		{
			$controllerType = $this->getControllerType($routeType);

			$this->runCommand('xf-make:controller', [
				'name' => $controllerName,
				'--addon' => $this->addOnId,
				'--type' => $controllerType,
				'--force' => $force,
			], $output);

			if ($routeType !== 'api')
			{
				$templateName = $this->generateTemplateName($controllerName, $routeType);
				$templateType = $routeType === 'admin' ? 'admin' : 'public';

				try
				{
					$this->runCommand('xf-make:template', [
						'title' => $templateName,
						'--addon' => $this->addOnId,
						'--type' => $templateType,
						'--force' => $force,
					], $output);
				}
				catch (\Exception $e)
				{
					$io->note("Could not create template '$templateName': " . $e->getMessage());
				}
			}
		}
		else
		{
			if (!class_exists($fullControllerClass))
			{
				$io->warning("Controller '$fullControllerClass' does not exist.");
			}
		}

		if ($existingRoute && $force)
		{
			$existingRoute->delete();
		}

		$route = $this->createRoute(
			$routeType,
			$prefix,
			$subName,
			$format,
			$fullControllerClass,
			$context,
			$actionPrefix
		);

		$this->displaySummary($io, $route, $templateName);

		$io->success("Route '$routeName' registered successfully.");

		return Command::SUCCESS;
	}

	protected function getControllerType(string $routeType): string
	{
		switch ($routeType)
		{
			case 'public':
				return 'pub';
			case 'admin':
				return 'admin';
			case 'api':
				return 'api';
			default:
				return 'pub';
		}
	}

	protected function deriveControllerName(string $prefix): string
	{
		$parts = preg_split('/[-_]/', $prefix);
		return implode('', array_map('ucfirst', $parts));
	}

	protected function singularize(string $name): string
	{
		return Str::singularize($name);
	}

	protected function getFullControllerClass(string $controllerName, string $routeType): string
	{
		$namespace = str_replace('/', '\\', $this->addOnId);
		switch ($routeType)
		{
			case 'admin':
				$typeNamespace = 'Admin\\Controller';
				break;
			case 'api':
				$typeNamespace = 'Api\\Controller';
				break;
			default:
				$typeNamespace = 'Pub\\Controller';
				break;
		}

		return $namespace . '\\' . $typeNamespace . '\\' . $controllerName;
	}

	protected function getExistingRoute(string $routeType, string $prefix, string $subName): ?Route
	{
		return \XF::finder(RouteFinder::class)
			->where('route_type', $routeType)
			->where('route_prefix', $prefix)
			->where('sub_name', $subName)
			->fetchOne();
	}

	protected function createRoute(
		string $routeType,
		string $prefix,
		string $subName,
		string $format,
		string $controller,
		string $context,
		string $actionPrefix
	): Route
	{
		$route = \XF::em()->create(Route::class);
		$route->route_type = $routeType;
		$route->route_prefix = $prefix;
		$route->sub_name = $subName;
		$route->format = $format;
		$route->controller = $controller;
		$route->context = $context;
		$route->action_prefix = $actionPrefix;
		$route->addon_id = $this->addOnId;
		$route->save();

		return $route;
	}

	protected function generateTemplateName(string $controllerName, string $routeType): string
	{
		$snakeName = Str::toSnakeCase($controllerName);

		return $routeType === 'admin'
			? $snakeName . '_list'
			: $snakeName . '_index';
	}

	protected function displaySummary(SymfonyStyle $io, Route $route, ?string $templateName = null): void
	{
		$rows = [
			['Type', $route->route_type],
			['Prefix', $route->route_prefix],
			['Sub-name', $route->sub_name ?: '(none)'],
			['Format', $route->format ?: '(none)'],
			['Controller', $route->controller ?: '(none)'],
			['Context', $route->context ?: '(none)'],
			['Action Prefix', $route->action_prefix ?: '(none)'],
		];

		if ($templateName)
		{
			$rows[] = ['Template', $templateName];
		}

		$io->table(['Property', 'Value'], $rows);
	}

	protected function runCommand(string $name, array $arguments, OutputInterface $output): void
	{
		$command = $this->getApplication()->find($name);
		$arguments['command'] = $name;
		$command->run(new ArrayInput($arguments), $output);
	}
}
