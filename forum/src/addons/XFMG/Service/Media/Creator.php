<?php

namespace XFMG\Service\Media;

use XF\App;
use XF\CustomField\Set;
use XF\Entity\Attachment;
use XF\Entity\User;
use XF\Mvc\Entity\Entity;
use XF\PrintableException;
use XF\Service\AbstractService;
use XF\Service\Tag\Changer;
use XF\Service\ValidateAndSavableTrait;
use XF\Util\File;
use XFMG\Entity\Album;
use XFMG\Entity\Category;
use XFMG\Entity\MediaItem;
use XFMG\Entity\MediaTemp;

class Creator extends AbstractService
{
	use ValidateAndSavableTrait;

	/**
	 * @var MediaTemp
	 */
	protected $mediaTemp;

	/**
	 * @var MediaItem
	 */
	protected $mediaItem;

	/**
	 * @var Album|Category
	 */
	protected $container;

	/**
	 * @var Attachment
	 */
	protected $attachment;

	/**
	 * @var Preparer
	 */
	protected $mediaItemPreparer;

	/**
	 * @var Changer
	 */
	protected $tagChanger;

	protected $performValidations = true;

	public function __construct(App $app, MediaTemp $mediaTemp)
	{
		parent::__construct($app);
		$this->setMediaTemp($mediaTemp);
	}

	protected function setMediaTemp(MediaTemp $mediaTemp)
	{
		$this->mediaTemp = $mediaTemp;
		$this->mediaItem = $mediaTemp->getNewMediaItem();
		$this->mediaItem->media_type = $mediaTemp->media_type;
		$this->mediaItemPreparer = $this->service('XFMG:Media\Preparer', $this->mediaItem);

		$this->setUser(\XF::visitor());
	}

	public function setUser(User $user)
	{
		$this->mediaItem->user_id = $user->user_id;
		$this->mediaItem->username = $user->username;
	}

	public function getMediaItem()
	{
		return $this->mediaItem;
	}

	public function getMediaItemPreparer()
	{
		return $this->mediaItemPreparer;
	}

	public function logIp($logIp)
	{
		$this->mediaItemPreparer->logIp($logIp);
	}

	public function setPerformValidations($perform)
	{
		$this->performValidations = (bool) $perform;
	}

	public function getPerformValidations()
	{
		return $this->performValidations;
	}

	public function setIsAutomated()
	{
		$this->logIp(false);
		$this->setPerformValidations(false);
	}

	public function setContainer(Entity $container)
	{
		if ($container instanceof Album)
		{
			$this->setAlbum($container);
			if ($container->Category)
			{
				$this->setCategory($container->Category);
			}
		}
		else if ($container instanceof Category)
		{
			$this->setCategory($container);
		}
		else
		{
			throw new \InvalidArgumentException("Container entity must be an album or category.");
		}

		$this->mediaItem->media_state = $container->getNewContentState();

		$this->container = $container;
		$this->tagChanger = $this->service('XF:Tag\Changer', 'xfmg_media', $container);
	}

	public function setAlbum(Album $album)
	{
		$mediaItem = $this->mediaItem;
		$mediaItem->album_id = $album->album_id;
	}

	public function setCategory(Category $category)
	{
		$mediaItem = $this->mediaItem;
		$mediaItem->category_id = $category->category_id;
	}

	public function setTitle($title, $description = '')
	{
		$this->mediaItem->title = $title;

		$this->mediaItem->set(
			'title',
			$title,
			['forceConstraint' => !$this->performValidations]
		);

		if ($description)
		{
			$this->mediaItemPreparer->setDescription($description, true, $this->performValidations);
		}
	}

	public function setTags($tags)
	{
		if (!$tags)
		{
			return;
		}

		if ($this->tagChanger->canEdit())
		{
			$this->tagChanger->setEditableTags($tags);
		}
	}

	public function setCustomFields(array $customFields)
	{
		/** @var Set $fieldSet */
		$fieldSet = $this->mediaItem->custom_fields;
		$fieldDefinition = $fieldSet->getDefinitionSet()
			->filterEditable($fieldSet, 'user')
			->filterOnly($this->container->field_cache)
			->filter('display_add_media');

		$customFieldsShown = array_keys($fieldDefinition->getFieldDefinitions());

		if ($customFieldsShown)
		{
			$fieldSet->bulkSet($customFields, $customFieldsShown);
		}
	}

	public function setAttachment($attachmentId, $attachmentHash = null)
	{
		if ($attachmentId instanceof Attachment)
		{
			$attachment = $attachmentId;
		}
		else if (!$attachmentId || !$attachmentHash)
		{
			return;
		}
		else
		{
			$attachment = $this->em()->find('XF:Attachment', $attachmentId, 'Data');

			if (!$attachment || $attachment['temp_hash'] != $attachmentHash)
			{
				throw new PrintableException('There was a problem associating the current attachment.');
			}
		}

		$this->attachment = $attachment;
	}

	public function setMediaSite($mediaEmbedUrl, $mediaTag)
	{
		if (!$mediaEmbedUrl || !$mediaTag)
		{
			return;
		}

		$this->mediaItem->media_embed_url = $mediaEmbedUrl;
		$this->mediaItem->media_tag = $mediaTag;
	}

	public function checkForSpam()
	{
		if ($this->mediaItem->media_state == 'visible' && \XF::visitor()->isSpamCheckRequired())
		{
			$this->mediaItemPreparer->checkForSpam();
		}
	}

	protected function finalSetup()
	{
		$mediaItem = $this->mediaItem;
		$mediaItem->media_date = time();
		$mediaItem->exif_data = $this->mediaTemp->exif_data;
	}

	protected function _validate()
	{
		$this->finalSetup();

		if ($this->performValidations && !$this->container->canAddMedia($error))
		{
			return [$error];
		}

		$mediaItem = $this->mediaItem;
		$mediaItem->preSave();
		$errors = $mediaItem->getErrors();

		if ($this->performValidations)
		{
			if ($this->tagChanger->canEdit())
			{
				$tagErrors = $this->tagChanger->getErrors();
				if ($tagErrors)
				{
					$errors = array_merge($errors, $tagErrors);
				}
			}
		}

		return $errors;
	}

	protected function _save()
	{
		$mediaItem = $this->mediaItem;

		$mediaItem->save();

		$this->mediaItemPreparer->afterInsert();

		if ($this->tagChanger->canEdit())
		{
			$this->tagChanger
				->setContentId($mediaItem->media_id, true)
				->save($this->performValidations);
		}

		if ($this->attachment)
		{
			$attachment = $this->attachment;

			// If the Attachment relation was accessed earlier NULL will be cached now so
			// hydrate the Attachment relation on the MediaItem entity to ensure it is available.
			$mediaItem->hydrateRelation('Attachment', $attachment);

			$this->db()->update('xf_attachment', [
				'content_id' => $mediaItem->media_id,
				'temp_hash' => '',
				'unassociated' => 0,
			], 'attachment_id = ?', $attachment->attachment_id);

			$attachment->getHandler()->onAssociation($attachment, $mediaItem);
		}
		else if ($mediaItem->media_type == 'embed')
		{
			$tempMedia = $this->mediaTemp;

			if ($tempMedia->thumbnail_date)
			{
				$tempThumbPath = $tempMedia->getAbstractedTempThumbnailPath();
				$mediaThumbPath = $mediaItem->getAbstractedThumbnailPath();

				$tempFile = File::copyAbstractedPathToTempFile($tempThumbPath);
				File::copyFileToAbstractedPath($tempFile, $mediaThumbPath);

				$mediaItem->fastUpdate('thumbnail_date', $tempMedia->thumbnail_date);

				File::deleteFromAbstractedPath($tempThumbPath);
			}

			$tempMedia->delete();
		}

		return $mediaItem;
	}

	public function sendNotifications()
	{
		/** @var Notifier $notifier */
		$notifier = $this->service('XFMG:Media\Notifier', $this->mediaItem);
		$notifier->setMentionedUserIds($this->mediaItemPreparer->getMentionedUserIds());
		$notifier->notifyAndEnqueue(3);
	}
}
