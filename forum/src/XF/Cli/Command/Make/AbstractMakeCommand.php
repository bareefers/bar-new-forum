<?php

declare(strict_types=1);

namespace XF\Cli\Command\Make;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;
use XF\AddOn\AddOn;
use XF\Cli\Command\AbstractCommand;
use XF\Cli\Command\Development\RequiresDevModeTrait;
use XF\Util\File;
use XF\Util\Str;

use function count, in_array;

abstract class AbstractMakeCommand extends AbstractCommand
{
	use RequiresDevModeTrait;

	/**
	 * @var string
	 */
	protected $addOnId;

	/**
	 * @var AddOn|null
	 */
	protected $addOnObj = null;

	protected function configure(): void
	{
		$this
			->addOption(
				'addon',
				'a',
				InputOption::VALUE_REQUIRED,
				'Add-on ID (not required if using AddOn:Name format)'
			)
			->addOption(
				'force',
				'f',
				InputOption::VALUE_NONE,
				'Force operation even if target exists'
			);
	}

	protected function getAvailableAddOns(bool $includeXF = true): array
	{
		$skipAddOns = \XF::config('development')['skipAddOns'] ?? [];

		$addOnManager = \XF::app()->addOnManager();

		$addOns = [];

		if ($includeXF && !in_array('XF', $skipAddOns))
		{
			$addOns = ['XF' => 'XF - XenForo'];
		}

		foreach ($addOnManager->getInstalledAddOns() AS $addOn)
		{
			$addOnId = $addOn->getAddOnId();
			if (!in_array($addOnId, $skipAddOns))
			{
				$addOns[$addOnId] = $addOnId . ' - ' . $addOn->title;
			}
		}

		return $addOns;
	}

	protected function validateAddOn(string $addOnId, SymfonyStyle $io, bool $allowXF = true): bool
	{
		if (!$allowXF && $addOnId === 'XF')
		{
			$io->error('This command cannot be used with XF core.');
			return false;
		}

		$this->addOnId = $addOnId;

		if ($addOnId === 'XF')
		{
			$this->addOnObj = null;
			return true;
		}

		$this->addOnObj = \XF::app()->addOnManager()->getById($addOnId);

		if ($this->addOnObj === null || !$this->addOnObj->isInstalled())
		{
			$io->error("Add-on '$addOnId' is not installed.");
			return false;
		}

		return true;
	}

	protected function parseColonSyntax(string $value): array
	{
		if (strpos($value, ':') !== false)
		{
			[$addOnId, $name] = explode(':', $value, 2);
			return ['addOnId' => $addOnId, 'name' => $name];
		}

		return ['addOnId' => null, 'name' => $value];
	}

	protected function truncateText(string $text, int $maxLength): string
	{
		$text = str_replace(["\r\n", "\r", "\n"], ' ', $text);

		if (Str::strlen($text) <= $maxLength)
		{
			return $text;
		}

		return Str::substr($text, 0, $maxLength - 3) . '...';
	}

	protected function promptForAddOn(SymfonyStyle $io, bool $includeXF = true): ?string
	{
		$addOns = $this->getAvailableAddOns($includeXF);
		$defaultAddOn = \XF::config('development')['defaultAddOn'] ?? null;

		if (count($addOns) === 0)
		{
			return $io->ask('Enter the add-on ID (e.g. Vendor/AddOn)', $defaultAddOn);
		}

		if (count($addOns) === 1)
		{
			return array_keys($addOns)[0];
		}

		return $io->choice('Which add-on is this for?', $addOns, $defaultAddOn);
	}

	protected function interactAddOn(
		InputInterface $input,
		SymfonyStyle $io,
		string $argumentName,
		bool $includeXF = true
	): void
	{
		$argumentValue = $input->getArgument($argumentName);

		if ($argumentValue)
		{
			$parsed = $this->parseColonSyntax($argumentValue);
			if ($parsed['addOnId'])
			{
				$input->setOption('addon', $parsed['addOnId']);
				$input->setArgument($argumentName, $parsed['name']);
			}
		}

		if (!$input->getOption('addon'))
		{
			$addOnId = $this->promptForAddOn($io, $includeXF);
			$input->setOption('addon', $addOnId);
		}
	}

	protected function resolveStubPath(string $stub): string
	{
		if ($this->addOnObj !== null)
		{
			$customPath = $this->addOnObj->getAddOnDirectory() . '/_stubs/' . $stub;
			if (file_exists($customPath))
			{
				return $customPath;
			}
		}

		return __DIR__ . '/stubs/' . $stub;
	}

	protected function ensureDirectory(string $path): void
	{
		$dir = dirname($path);
		if (!is_dir($dir))
		{
			File::createDirectory($dir, false);
		}
	}

	protected function getAddOnDirectory(): string
	{
		if ($this->addOnId === 'XF')
		{
			return \XF::getSourceDirectory() . '/XF';
		}

		if ($this->addOnObj === null)
		{
			throw new \LogicException('addOnObj is null. Ensure validateAddOn() is called before getAddOnDirectory().');
		}

		return $this->addOnObj->getAddOnDirectory();
	}
}
