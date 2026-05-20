<?php

namespace BAR\SponsorBanners;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;

class Setup extends AbstractSetup
{
	use StepRunnerInstallTrait;
	use StepRunnerUninstallTrait;
	use StepRunnerUpgradeTrait;

	public function installStep1(): void
	{
		$this->schemaManager()->createTable('xf_bar_sponsor_banner', function(Create $table)
		{
			$table->addColumn('banner_id', 'int')->autoIncrement();
			$table->addColumn('title', 'varchar', 100);
			$table->addColumn('image_path', 'varchar', 255)->setDefault('');
			$table->addColumn('remote_url', 'varchar', 512)->setDefault('');
			$table->addColumn('target_url', 'varchar', 255)->setDefault('');
			$table->addColumn('alt_text', 'varchar', 255)->setDefault('');
			$table->addColumn('display_order', 'int')->setDefault(10);
			$table->addColumn('is_active', 'tinyint')->setDefault(1);
			$table->addColumn('created_date', 'int')->setDefault(0);
			$table->addColumn('source_width', 'int')->setDefault(0);
			$table->addColumn('source_height', 'int')->setDefault(0);
			$table->addColumn('display_width', 'int')->setDefault(0);
			$table->addColumn('display_height', 'int')->setDefault(0);
			$table->addPrimaryKey('banner_id');
			$table->addKey(['is_active', 'display_order'], 'is_active_order');
		});
	}

	public function uninstallStep1(): void
	{
		$this->schemaManager()->dropTable('xf_bar_sponsor_banner');
	}

	public function upgrade1000011Step1(): void
	{
		// New widget definition + templates are imported via add-on data upgrade.
	}

	public function upgrade1000012Step1(): void
	{
		// Admin list template cleanup (imported via add-on data upgrade).
	}

	public function upgrade1000013Step1(): void
	{
		// Widget class: no options template (imported via add-on class refresh on upgrade).
	}

	public function upgrade1000014Step1(): void
	{
		// Admin nav phrase, widget max-width option + templates (imported via add-on data upgrade).
	}

	public function upgrade1000015Step1(): void
	{
		// Widget rotation_mode option + phrases (imported via add-on data upgrade).
	}

	public function upgrade1000016Step1(): void
	{
		$this->schemaManager()->alterTable('xf_bar_sponsor_banner', function (Alter $table)
		{
			$table->addColumn('source_width', 'int')->setDefault(0);
			$table->addColumn('source_height', 'int')->setDefault(0);
			$table->addColumn('display_width', 'int')->setDefault(0);
			$table->addColumn('display_height', 'int')->setDefault(0);
		});
	}

	public function upgrade1000016Step2(): void
	{
		$root = \XF::getRootDirectory();
		foreach ($this->db()->fetchAll('SELECT banner_id, image_path FROM xf_bar_sponsor_banner') AS $row)
		{
			$path = $root . '/' . $row['image_path'];
			if (!is_file($path))
			{
				continue;
			}

			$info = @getimagesize($path);
			if (!$info)
			{
				continue;
			}

			$this->db()->update('xf_bar_sponsor_banner', [
				'source_width' => $info[0],
				'source_height' => $info[1],
			], 'banner_id = ?', $row['banner_id']);
		}
	}

	public function upgrade1000017Step1(): void
	{
		$sm = $this->schemaManager();

		if (!$sm->columnExists('xf_bar_sponsor_banner', 'remote_url'))
		{
			$sm->alterTable('xf_bar_sponsor_banner', function (Alter $table)
			{
				$table->addColumn('remote_url', 'varchar', 512)->setDefault('');
			});
		}

		$sm->alterTable('xf_bar_sponsor_banner', function (Alter $table)
		{
			$table->changeColumn('image_path')->setDefault('');
		});
	}

	public function upgrade1000018Step1(): void
	{
		$this->app->repository('BAR\SponsorBanners:Banner')->renumberAllDisplayOrders();
	}

	public function upgrade1000019Step1(): void
	{
		// Phrases + edit form hints (imported via add-on data upgrade).
	}

	public function upgrade1000020Step1(): void
	{
		// Entity getter public_image_src + list/edit delete UX (imported via add-on data upgrade).
	}

	public function upgrade1000021Step1(): void
	{
		// Sponsor banners list: copy-paste widget line + create-widget CTA (imported via add-on data upgrade).
	}

	public function upgrade1000022Step1(): void
	{
		// List template: advertising embed uses xf:widget class= (imported via add-on data upgrade).
	}

	public function upgrade1000023Step1(): void
	{
		// Fix null/empty $banners.count() template errors (imported via add-on data upgrade).
	}

	public function upgrade1000024Step1(): void
	{
		// Phrases: standard ad slot container_breadcrumb_top_above (imported via add-on data upgrade).
	}

	public function upgrade1000025Step1(): void
	{
		// Macro: use $banners/$class/$max_width (merged arg names), not $arg-* (imported via add-on data upgrade).
	}

	public function upgrade1000026Step1(): void
	{
		// Public macro: center-align sponsor row (imported via add-on data upgrade).
	}

	public function upgrade1000027Step1(): void
	{
		// Legacy-style alignCenter + base_url(image_path); remote_url unchanged (imported via add-on data upgrade).
	}

	public function upgrade1000028Step1(): void
	{
		// Admin list: show Shown/Hidden status vs action buttons (imported via add-on data upgrade).
	}

	public function upgrade1000029Step1(): void
	{
		// Admin list: collapsible setup / paste block (imported via add-on data upgrade).
	}
}
