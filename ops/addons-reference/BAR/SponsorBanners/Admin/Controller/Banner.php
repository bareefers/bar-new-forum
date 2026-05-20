<?php

namespace BAR\SponsorBanners\Admin\Controller;

use BAR\SponsorBanners\Service\RemoteImageProbe;
use XF\Admin\Controller\AbstractController;
use XF\Http\Upload;
use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;

class Banner extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertAdminPermission('style');
	}

	public function actionIndex()
	{
		$bannerRepo = $this->getBannerRepo();

		$sponsorWidgets = $this->finder('XF:Widget')
			->where('definition_id', 'bar_sponsor_banners')
			->order('widget_id')
			->fetch();

		$firstSponsorWidget = $sponsorWidgets->count() ? $sponsorWidgets->first() : null;

		return $this->view(
			'BAR\\SponsorBanners:Banner\\Listing',
			'bar_sb_banner_list',
			[
				'banners' => $bannerRepo->findBannersForList()->fetch(),
				'sponsorWidgets' => $sponsorWidgets,
				'firstSponsorWidget' => $firstSponsorWidget,
			]
		);
	}

	public function actionAdd()
	{
		$banner = $this->em()->create('BAR\\SponsorBanners:Banner');

		return $this->bannerAddEdit($banner);
	}

	public function actionEdit(ParameterBag $params)
	{
		$banner = $this->assertBannerExists($params->banner_id);

		return $this->bannerAddEdit($banner);
	}

	public function actionSave(ParameterBag $params)
	{
		$this->assertPostOnly();

		if ($params->banner_id)
		{
			$banner = $this->assertBannerExists($params->banner_id);
		}
		else
		{
			$banner = $this->em()->create('BAR\\SponsorBanners:Banner');
		}

		$this->bannerSaveProcess($banner)->run();

		$redirectMessage = null;
		if (trim($banner->remote_url) !== '')
		{
			$warn = RemoteImageProbe::getWarning($this->app(), $banner->remote_url);
			if ($warn)
			{
				$redirectMessage = \XF::phrase('your_changes_have_been_saved')->render() . ' ' . $warn->render();
			}
		}

		return $this->redirect($this->buildLink('bar-sponsor-banners'), $redirectMessage);
	}

	public function actionToggleActive(ParameterBag $params)
	{
		$this->assertPostOnly();

		$banner = $this->assertBannerExists($params->banner_id);
		$banner->is_active = !$banner->is_active;
		$banner->save();

		$message = $banner->is_active
			? \XF::phrase('bar_sb_now_visible_on_site')
			: \XF::phrase('bar_sb_now_hidden_from_site');

		return $this->redirect($this->buildLink('bar-sponsor-banners'), $message);
	}

	public function actionDelete(ParameterBag $params)
	{
		$banner = $this->assertBannerExists($params->banner_id);

		if ($this->isPost())
		{
			$banner->delete();
			$this->getBannerRepo()->renumberAllDisplayOrders();

			return $this->redirect($this->buildLink('bar-sponsor-banners'));
		}

		return $this->view(
			'BAR\\SponsorBanners:Banner\\Delete',
			'bar_sb_banner_delete',
			['banner' => $banner]
		);
	}

	protected function bannerAddEdit(\BAR\SponsorBanners\Entity\Banner $banner)
	{
		return $this->view(
			'BAR\\SponsorBanners:Banner\\Edit',
			'bar_sb_banner_edit',
			[
				'banner' => $banner,
				'fileDimensions' => $this->getImageFileDimensions($banner),
			]
		);
	}

	/**
	 * @return array{width: int, height: int}|null
	 */
	protected function getImageFileDimensions(\BAR\SponsorBanners\Entity\Banner $banner): ?array
	{
		if ($banner->source_width > 0 && $banner->source_height > 0)
		{
			return [
				'width' => (int) $banner->source_width,
				'height' => (int) $banner->source_height,
			];
		}

		if (!$banner->image_path)
		{
			return null;
		}

		$path = \XF::getRootDirectory() . '/' . ltrim($banner->image_path, '/');
		if (!is_file($path))
		{
			return null;
		}

		$info = @getimagesize($path);
		if (!$info)
		{
			return null;
		}

		return [
			'width' => (int) $info[0],
			'height' => (int) $info[1],
		];
	}

	protected function bannerSaveProcess(\BAR\SponsorBanners\Entity\Banner $banner): FormAction
	{
		$form = $this->formAction();

		$input = $this->filter([
			'title' => 'str',
			'remote_url' => 'str',
			'target_url' => 'str',
			'alt_text' => 'str',
			'is_active' => 'bool',
			'display_width' => 'uint',
			'display_height' => 'uint',
		]);

		if (isset($input['remote_url']) && strlen($input['remote_url']) > 512)
		{
			$input['remote_url'] = substr($input['remote_url'], 0, 512);
		}

		$form->setup(function () use ($banner, $input)
		{
			$banner->bulkSet($input);

			if ($banner->isInsert())
			{
				$banner->display_order = $this->getBannerRepo()->getNextDisplayOrder();
			}

			$upload = $this->request->getFile('banner_image');
			if (!($upload instanceof Upload) || !$upload->isValid($errors))
			{
				return;
			}

			$info = $this->writeBannerUpload($upload, $banner);
			$banner->image_path = $info['path'];
			$banner->source_width = $info['source_width'];
			$banner->source_height = $info['source_height'];

			if ((int) $banner->display_width === 0 && (int) $banner->display_height === 0)
			{
				$banner->display_width = $info['source_width'];
				$banner->display_height = $info['source_height'];
			}
		});

		$form->validate(function (FormAction $fa) use ($banner)
		{
			$banner->preSave();

			$path = trim($banner->image_path);
			$remote = trim($banner->remote_url);
			if ($path === '' && $remote === '')
			{
				$fa->logError(\XF::phrase('bar_sb_need_image_or_url'), 'image_path');
			}
			if ($remote !== '' && !preg_match('#^https?://#i', $remote))
			{
				$fa->logError(\XF::phrase('bar_sb_remote_scheme_invalid'), 'remote_url');
			}

			$fa->logErrors($banner->getErrors());
		});

		$form->apply(function (FormAction $fa) use ($banner)
		{
			$banner->save(true, $fa->isUsingTransaction() ? false : true);
		});

		return $form;
	}

	/**
	 * @return array{path: string, source_width: int, source_height: int}
	 */
	protected function writeBannerUpload(Upload $upload, \BAR\SponsorBanners\Entity\Banner $banner): array
	{
		$upload->requireImage();
		$upload->setAllowedExtensions(['jpg', 'jpeg', 'png', 'gif', 'webp']);

		if (!$upload->isValid($errors))
		{
			throw $this->exception($this->error(implode("\n", $errors)));
		}

		$extension = $upload->getExtension() ?: 'png';
		$safe = preg_replace('/[^a-z0-9]+/i', '-', strtolower($banner->title));
		$safe = trim($safe ?: 'banner', '-');
		$fileName = sprintf('%d-%s.%s', \XF::$time, $safe, $extension);

		$dir = \XF::getRootDirectory() . '/sponsor_banners';
		if (!is_dir($dir))
		{
			mkdir($dir, 0775, true);
		}

		$target = $dir . '/' . $fileName;
		if (!copy($upload->getTempFile(), $target))
		{
			throw $this->exception($this->error(\XF::phrase('please_enter_valid_value')));
		}

		$info = @getimagesize($target);
		$w = $info ? (int) $info[0] : (int) $upload->getImageWidth();
		$h = $info ? (int) $info[1] : (int) $upload->getImageHeight();

		return [
			'path' => 'sponsor_banners/' . $fileName,
			'source_width' => $w,
			'source_height' => $h,
		];
	}

	protected function assertBannerExists($bannerId)
	{
		return $this->assertRecordExists('BAR\\SponsorBanners:Banner', $bannerId);
	}

	protected function getBannerRepo(): \BAR\SponsorBanners\Repository\Banner
	{
		return $this->repository('BAR\\SponsorBanners:Banner');
	}
}
