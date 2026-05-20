<?php

declare(strict_types=1);

namespace XF\Cli\Command\Make;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use XF\Entity\CodeEvent;
use XF\Entity\CodeEventListener;
use XF\Finder\CodeEventFinder;
use XF\Finder\CodeEventListenerFinder;
use XF\Util\File;

class ListenerMakeCommand extends AbstractMakeCommand
{
	protected function configure(): void
	{
		parent::configure();

		$this
			->setName('xf-make:listener')
			->setDescription('Create a listener class and register an event listener')
			->addArgument(
				'event',
				InputArgument::REQUIRED,
				'The event to listen for (e.g. "Demo/Thing:app_setup" or "app_setup" with --addon)'
			)
			->addOption(
				'class',
				'c',
				InputOption::VALUE_REQUIRED,
				'Callback class name relative to add-on namespace',
				'Listener'
			)
			->addOption(
				'method',
				'm',
				InputOption::VALUE_REQUIRED,
				'Callback method name (default: camelCase of event_id)'
			)
			->addOption(
				'description',
				'd',
				InputOption::VALUE_REQUIRED,
				'Description of the listener',
				''
			)
			->addOption(
				'hint',
				null,
				InputOption::VALUE_REQUIRED,
				'Hint for the listener (e.g. class name for entity_structure event)',
				''
			)
			->addOption(
				'execute-order',
				'o',
				InputOption::VALUE_REQUIRED,
				'Execution priority order',
				'10'
			)
			->addOption(
				'inactive',
				null,
				InputOption::VALUE_NONE,
				'Create the listener as inactive'
			);
	}

	protected function interact(InputInterface $input, OutputInterface $output): void
	{
		$io = new SymfonyStyle($input, $output);

		$this->interactAddOn($input, $io, 'event', false);

		if (!$input->getArgument('event'))
		{
			$events = $this->getAvailableEvents();
			if ($events)
			{
				$eventId = $io->choice('Which event do you want to listen for?', $events);
			}
			else
			{
				$eventId = $io->ask('Enter the event ID (e.g. app_setup)');
			}
			$input->setArgument('event', $eventId);
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

		if (!$this->validateAddOn($addOnId, $io, false))
		{
			return Command::FAILURE;
		}

		$eventId = $input->getArgument('event');

		$event = $this->getEvent($eventId);
		if (!$event)
		{
			$io->error("The event '$eventId' does not exist.");
			$io->note('Use the admin panel to view available events: Admin > Development > Code events');
			return Command::FAILURE;
		}

		$className = $input->getOption('class');
		$methodName = $input->getOption('method') ?: $this->eventToMethodName($eventId);
		$force = $input->getOption('force');

		$filePath = $this->ensureListenerClassExists($io, $className);
		if ($filePath === false)
		{
			return Command::FAILURE;
		}

		$methodAdded = $this->addMethodToClass($io, $filePath, $methodName, $event, $force);
		if ($methodAdded === false)
		{
			return Command::FAILURE;
		}

		$fullClassName = str_replace('/', '\\', $this->addOnId) . '\\' . str_replace('/', '\\', $className);
		$hint = $input->getOption('hint') ?: '';

		$existingListener = $this->getExistingListener($eventId, $fullClassName, $methodName, $hint, $this->addOnId);
		if ($existingListener)
		{
			$io->note("Listener for event '$eventId' with callback $fullClassName::$methodName already registered.");
			return Command::SUCCESS;
		}

		$this->registerListener($input, $eventId, $fullClassName, $methodName);
		$io->success("Listener registered: $fullClassName::$methodName for event '$eventId'");

		return Command::SUCCESS;
	}

	/**
	 * @return string|false
	 */
	protected function ensureListenerClassExists(SymfonyStyle $io, string $className)
	{
		$filePath = $this->getClassFilePath($className);

		if (file_exists($filePath))
		{
			$io->note("Listener class already exists at $filePath");
			return $filePath;
		}

		$namespace = str_replace('/', '\\', $this->addOnId);
		$shortClassName = $className;

		if (strpos($className, '/') !== false || strpos($className, '\\') !== false)
		{
			$parts = preg_split('/[\/\\\\]/', $className);
			$shortClassName = array_pop($parts);
			$namespace .= '\\' . implode('\\', $parts);
		}

		$stubPath = $this->resolveStubPath('listener.stub');
		$stub = file_get_contents($stubPath);

		if ($stub === false)
		{
			throw new \RuntimeException("Failed to load stub file: $stubPath");
		}

		$stub = str_replace(
			[
				'{{ namespace }}',
				'{{ class }}',
			],
			[
				$namespace,
				$shortClassName,
			],
			$stub
		);

		$this->ensureDirectory($filePath);

		File::writeFile($filePath, $stub, false);
		$io->success("Listener class created at $filePath");

		return $filePath;
	}

	protected function addMethodToClass(SymfonyStyle $io, string $filePath, string $methodName, CodeEvent $event, bool $force): bool
	{
		$content = file_get_contents($filePath);

		if (preg_match('/function\s+' . preg_quote($methodName, '/') . '\s*\(/i', $content))
		{
			if (!$force)
			{
				$io->error("Method '$methodName' already exists in $filePath");
				$io->note('Use --force to skip this check (method will NOT be overwritten).');
				return false;
			}
			$io->note("Method '$methodName' already exists, skipping method creation.");
			return true;
		}

		$stubPath = $this->resolveStubPath('listener.method.stub');
		$methodStub = file_get_contents($stubPath);
		$methodStub = str_replace('{{ method }}', $methodName, $methodStub);

		$params = $event->callback_signature;
		$methodStub = str_replace('{{ params }}', $params, $methodStub);

		$methodStub = trim($methodStub);

		$methodStub = "\t" . str_replace("\n", "\n\t", $methodStub);
		$methodStub = preg_replace("/\n\t$/", "\n", $methodStub);

		$lastBrace = strrpos($content, '}');
		if ($lastBrace === false)
		{
			$io->error("Could not parse $filePath - missing closing brace.");
			return false;
		}

		$beforeBrace = rtrim(substr($content, 0, $lastBrace));

		$hasExistingMethods = preg_match('/function\s+\w+\s*\(/i', $beforeBrace);
		$separator = $hasExistingMethods ? "\n\n" : "\n";

		$newContent = $beforeBrace . $separator . $methodStub . "\n}\n";

		if (!File::writeFile($filePath, $newContent, false))
		{
			$io->error("Failed to write to $filePath");
			return false;
		}

		$io->success("Method '$methodName' added to $filePath");
		return true;
	}

	protected function registerListener(
		InputInterface $input,
		string $eventId,
		string $className,
		string $methodName
	): void
	{
		$listener = \XF::em()->create(CodeEventListener::class);
		$listener->setOption('skip_callback_validation', true);
		$listener->event_id = $eventId;
		$listener->callback_class = $className;
		$listener->callback_method = $methodName;
		$listener->execute_order = (int) $input->getOption('execute-order');
		$listener->description = $input->getOption('description') ?: '';
		$listener->hint = $input->getOption('hint') ?: '';
		$listener->active = !$input->getOption('inactive');
		$listener->addon_id = $this->addOnId;
		$listener->save();
	}

	protected function getClassFilePath(string $className): string
	{
		$classPath = str_replace('\\', '/', $className) . '.php';
		return $this->getAddOnDirectory() . '/' . $classPath;
	}

	protected function getEvent(string $eventId): ?CodeEvent
	{
		return \XF::finder(CodeEventFinder::class)
			->where('event_id', $eventId)
			->fetchOne();
	}

	protected function getAvailableEvents(): array
	{
		$events = \XF::finder(CodeEventFinder::class)
			->order('event_id')
			->fetch();

		$result = [];
		foreach ($events AS $event)
		{
			$result[$event->event_id] = $event->event_id;
		}

		return $result;
	}

	protected function getExistingListener(
		string $eventId,
		string $className,
		string $methodName,
		string $hint,
		string $addOnId
	): ?CodeEventListener
	{
		return \XF::finder(CodeEventListenerFinder::class)
			->where([
				'event_id' => $eventId,
				'callback_class' => $className,
				'callback_method' => $methodName,
				'hint' => $hint,
				'addon_id' => $addOnId,
			])
			->fetchOne();
	}

	protected function eventToMethodName(string $eventId): string
	{
		return lcfirst(str_replace('_', '', ucwords($eventId, '_')));
	}
}
