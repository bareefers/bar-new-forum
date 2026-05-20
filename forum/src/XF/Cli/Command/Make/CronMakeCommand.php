<?php

declare(strict_types=1);

namespace XF\Cli\Command\Make;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use XF\Entity\CronEntry;
use XF\Finder\CronEntryFinder;
use XF\Util\File;

use function array_slice, in_array, strlen;

class CronMakeCommand extends AbstractMakeCommand
{
	protected const SCHEDULE_PRESETS = [
		'hourly' => [
			'day_type' => 'dom',
			'dom' => [-1],
			'hours' => [-1],
			'minutes' => [10],
		],
		'daily' => [
			'day_type' => 'dom',
			'dom' => [-1],
			'hours' => [0],
			'minutes' => [0],
		],
		'weekly' => [
			'day_type' => 'dow',
			'dow' => [0],
			'hours' => [0],
			'minutes' => [0],
		],
		'monthly' => [
			'day_type' => 'dom',
			'dom' => [1],
			'hours' => [0],
			'minutes' => [0],
		],
	];

	protected function configure(): void
	{
		parent::configure();

		$this
			->setName('xf-make:cron')
			->setDescription('Create a cron class and register a cron entry')
			->addArgument(
				'entry',
				InputArgument::REQUIRED,
				'The cron entry ID (e.g. "Demo/Thing:myCleanUp" or "myCleanUp" with --addon)'
			)
			->addOption(
				'class',
				'c',
				InputOption::VALUE_REQUIRED,
				'Callback class name relative to add-on namespace',
				'Cron'
			)
			->addOption(
				'method',
				'm',
				InputOption::VALUE_REQUIRED,
				'Callback method name (default: same as entry_id)'
			)
			->addOption(
				'title',
				't',
				InputOption::VALUE_REQUIRED,
				'Title phrase text (default: humanized entry_id)'
			)
			->addOption(
				'schedule',
				's',
				InputOption::VALUE_REQUIRED,
				'Schedule preset: hourly, daily, weekly, monthly'
			)
			->addOption(
				'hours',
				null,
				InputOption::VALUE_REQUIRED,
				'Hours to run (comma-separated, 0-23, or -1 for any)'
			)
			->addOption(
				'minutes',
				null,
				InputOption::VALUE_REQUIRED,
				'Minutes to run (comma-separated, 0-59)',
				'0'
			)
			->addOption(
				'dom',
				null,
				InputOption::VALUE_REQUIRED,
				'Days of month to run (comma-separated, 1-31, or -1 for any)'
			)
			->addOption(
				'dow',
				null,
				InputOption::VALUE_REQUIRED,
				'Days of week to run (comma-separated, 0-6 where 0=Sunday, or -1 for any)'
			)
			->addOption(
				'inactive',
				null,
				InputOption::VALUE_NONE,
				'Create the cron entry as inactive'
			);
	}

	protected function interact(InputInterface $input, OutputInterface $output): void
	{
		$io = new SymfonyStyle($input, $output);

		$this->interactAddOn($input, $io, 'entry');

		if (!$input->getArgument('entry'))
		{
			$entryId = $io->ask(
				'Enter the cron entry ID (e.g. myCleanUp)',
				null,
				function ($value)
				{
					if (empty($value))
					{
						throw new \InvalidArgumentException('Entry ID cannot be empty.');
					}
					return $value;
				}
			);
			$input->setArgument('entry', $entryId);
		}

		if (!$this->hasScheduleOptions($input))
		{
			$this->promptForSchedule($input, $io);
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

		$entryId = $this->normalizeEntryId($input->getArgument('entry'));

		if (!preg_match('/^[a-zA-Z0-9]+$/', $entryId))
		{
			$io->error('Entry ID must be alphanumeric (letters and numbers only).');
			$io->note("Input was normalized to: '$entryId'");
			return Command::FAILURE;
		}

		if (strlen($entryId) > 25)
		{
			$io->error('Entry ID must be 25 characters or less.');
			return Command::FAILURE;
		}

		$existingEntry = $this->getExistingCronEntry($entryId);
		if ($existingEntry)
		{
			$io->error("Cron entry '$entryId' already exists.");
			return Command::FAILURE;
		}

		$runRules = $this->buildRunRules($input, $io);
		if ($runRules === null)
		{
			return Command::FAILURE;
		}

		$className = $input->getOption('class');
		$methodName = $input->getOption('method') ?: $entryId;
		$force = $input->getOption('force');

		$filePath = $this->ensureCronClassExists($io, $className);
		if ($filePath === false)
		{
			return Command::FAILURE;
		}

		$methodAdded = $this->addMethodToClass($io, $filePath, $methodName, $force);
		if ($methodAdded === false)
		{
			return Command::FAILURE;
		}

		$fullClassName = str_replace('/', '\\', $this->addOnId) . '\\' . str_replace('/', '\\', $className);
		$title = $input->getOption('title') ?: $this->humanizeEntryId($entryId);

		$this->registerCronEntry($input, $entryId, $fullClassName, $methodName, $title, $runRules);

		$scheduleDesc = $this->describeSchedule($runRules);
		$io->success("Cron entry '$entryId' registered ($scheduleDesc)");

		return Command::SUCCESS;
	}

	protected function hasScheduleOptions(InputInterface $input): bool
	{
		return $input->getOption('schedule')
			|| $input->getOption('hours')
			|| $input->getOption('dom')
			|| $input->getOption('dow');
	}

	protected function promptForSchedule(InputInterface $input, SymfonyStyle $io): void
	{
		$choices = [
			'hourly' => 'Every hour (at :10)',
			'daily' => 'Once per day (at midnight)',
			'weekly' => 'Once per week (Sunday at midnight)',
			'monthly' => 'Once per month (1st at midnight)',
			'custom' => 'Custom schedule...',
		];

		$schedule = $io->choice('How often should this task run?', $choices, 'daily');

		if ($schedule === 'custom')
		{
			$this->promptForCustomSchedule($input, $io);
		}
		else
		{
			$input->setOption('schedule', $schedule);
		}
	}

	protected function promptForCustomSchedule(InputInterface $input, SymfonyStyle $io): void
	{
		$minutes = $io->ask(
			'Which minutes? (comma-separated, 0-59, or -1 for any)',
			'0',
			function ($value)
			{
				return $this->validateTimeInput($value, 0, 59);
			}
		);
		$input->setOption('minutes', $minutes);

		$hours = $io->ask(
			'Which hours? (comma-separated, 0-23, or -1 for any)',
			'0',
			function ($value)
			{
				return $this->validateTimeInput($value, 0, 23);
			}
		);
		$input->setOption('hours', $hours);

		$dayType = $io->choice(
			'Run on specific days of the month or week?',
			[
				'dom' => 'Days of month (1-31)',
				'dow' => 'Days of week (0=Sunday through 6=Saturday)',
			],
			'dom'
		);

		if ($dayType === 'dom')
		{
			$dom = $io->ask(
				'Which days of the month? (comma-separated, 1-31, or -1 for any)',
				'-1',
				function ($value)
				{
					return $this->validateTimeInput($value, 1, 31);
				}
			);
			$input->setOption('dom', $dom);
		}
		else
		{
			$dow = $io->ask(
				'Which days of the week? (comma-separated, 0-6 where 0=Sunday, or -1 for any)',
				'0',
				function ($value)
				{
					return $this->validateTimeInput($value, 0, 6);
				}
			);
			$input->setOption('dow', $dow);
		}
	}

	protected function validateTimeInput(string $value, int $min, int $max): string
	{
		$parts = array_map('trim', explode(',', $value));
		foreach ($parts AS $part)
		{
			$intVal = (int) $part;
			if ($intVal === -1)
			{
				continue;
			}
			if ($intVal < $min || $intVal > $max)
			{
				throw new \InvalidArgumentException(
					"Value {$intVal} is out of range ({$min}-{$max})"
				);
			}
		}
		return $value;
	}

	protected function buildRunRules(InputInterface $input, SymfonyStyle $io): ?array
	{
		$schedule = $input->getOption('schedule');

		if ($schedule)
		{
			if (!isset(self::SCHEDULE_PRESETS[$schedule]))
			{
				$io->error("Invalid schedule preset: '$schedule'. Valid options: hourly, daily, weekly, monthly");
				return null;
			}

			$runRules = self::SCHEDULE_PRESETS[$schedule];

			if ($input->getOption('hours'))
			{
				$runRules['hours'] = $this->parseIntList($input->getOption('hours'));
			}
			if ($input->getOption('minutes') !== '0')
			{
				$runRules['minutes'] = $this->parseIntList($input->getOption('minutes'));
			}

			return $runRules;
		}

		$hours = $input->getOption('hours');
		$dom = $input->getOption('dom');
		$dow = $input->getOption('dow');

		if (!$hours && !$dom && !$dow)
		{
			$io->error('Schedule is required. Use --schedule or specify timing options (--hours, --dom, --dow).');
			return null;
		}

		$runRules = [
			'minutes' => $this->parseIntList($input->getOption('minutes')),
		];

		if ($hours)
		{
			$runRules['hours'] = $this->parseIntList($hours);
		}
		else
		{
			$runRules['hours'] = [-1];
		}

		if ($dow)
		{
			$runRules['day_type'] = 'dow';
			$runRules['dow'] = $this->parseIntList($dow);
		}
		else
		{
			$runRules['day_type'] = 'dom';
			$runRules['dom'] = $dom ? $this->parseIntList($dom) : [-1];
		}

		return $runRules;
	}

	protected function parseIntList(string $value): array
	{
		return array_map('intval', array_map('trim', explode(',', $value)));
	}

	/**
	 * @return string|false
	 */
	protected function ensureCronClassExists(SymfonyStyle $io, string $className)
	{
		$filePath = $this->getClassFilePath($className);

		if (file_exists($filePath))
		{
			$io->note("Cron class already exists at $filePath");
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

		$stub = file_get_contents($this->resolveStubPath('cron.stub'));
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
		$io->success("Cron class created at $filePath");

		return $filePath;
	}

	protected function addMethodToClass(SymfonyStyle $io, string $filePath, string $methodName, bool $force): bool
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

		$stubPath = $this->resolveStubPath('cron.method.stub');
		$methodStub = file_get_contents($stubPath);
		$methodStub = str_replace('{{ method }}', $methodName, $methodStub);

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

		File::writeFile($filePath, $newContent, false);

		$io->success("Method '$methodName' added to $filePath");
		return true;
	}

	protected function registerCronEntry(
		InputInterface $input,
		string $entryId,
		string $className,
		string $methodName,
		string $title,
		array $runRules
	): void
	{
		$db = \XF::db();
		$db->beginTransaction();

		try
		{
			$cronEntry = \XF::em()->create(CronEntry::class);
			$cronEntry->entry_id = $entryId;
			$cronEntry->cron_class = $className;
			$cronEntry->cron_method = $methodName;
			$cronEntry->run_rules = $runRules;
			$cronEntry->active = !$input->getOption('inactive');
			$cronEntry->addon_id = $this->addOnId;
			$cronEntry->save();

			$masterPhrase = $cronEntry->getMasterPhrase();
			$masterPhrase->phrase_text = $title;
			$masterPhrase->save();

			$db->commit();
		}
		catch (\Exception $e)
		{
			$db->rollback();
			throw $e;
		}
	}

	protected function getClassFilePath(string $className): string
	{
		$classPath = str_replace('\\', '/', $className) . '.php';
		return $this->getAddOnDirectory() . '/' . $classPath;
	}

	protected function getExistingCronEntry(string $entryId): ?CronEntry
	{
		return \XF::finder(CronEntryFinder::class)
			->where('entry_id', $entryId)
			->fetchOne();
	}

	protected function normalizeEntryId(string $entryId): string
	{
		$entryId = str_replace('-', '_', $entryId);

		if (strpos($entryId, '_') !== false)
		{
			$parts = explode('_', $entryId);
			$entryId = $parts[0] . implode('', array_map('ucfirst', array_slice($parts, 1)));
		}

		return lcfirst($entryId);
	}

	protected function humanizeEntryId(string $entryId): string
	{
		$parts = preg_split('/(?=[A-Z])/', $entryId, -1, PREG_SPLIT_NO_EMPTY);

		$humanized = strtolower(implode(' ', $parts));

		return ucfirst($humanized);
	}

	protected function describeSchedule(array $runRules): string
	{
		$hours = $runRules['hours'] ?? [-1];
		$minutes = $runRules['minutes'] ?? [0];
		$dayType = $runRules['day_type'] ?? 'dom';

		$minStr = ':' . str_pad((string) ($minutes[0] ?? 0), 2, '0', STR_PAD_LEFT);

		if (in_array(-1, $hours))
		{
			$timeStr = "hourly at {$minStr}";
		}
		else
		{
			$timeStr = implode(', ', array_map(function ($h) use ($minStr)
			{
				return $h . $minStr;
			}, $hours));
		}

		if ($dayType === 'dow')
		{
			$days = $runRules['dow'] ?? [0];
			$dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
			if (in_array(-1, $days))
			{
				return $timeStr;
			}
			$dayStr = implode(', ', array_map(function ($d) use ($dayNames)
			{
				$d = (int) $d;
				return ($d >= 0 && $d <= 6) ? $dayNames[$d] : (string) $d;
			}, $days));
			return "{$dayStr} at {$timeStr}";
		}
		else
		{
			$days = $runRules['dom'] ?? [-1];
			if (in_array(-1, $days))
			{
				return $timeStr;
			}
			$dayStr = implode(', ', $days);
			return "day {$dayStr} of month at {$timeStr}";
		}
	}
}
