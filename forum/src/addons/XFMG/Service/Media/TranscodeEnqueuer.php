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
use XFMG\Entity\Album;
use XFMG\Entity\Category;
use XFMG\Entity\MediaTemp;
use XFMG\Entity\TranscodeQueue;
use XFMG\Ffmpeg\Queue;

class TranscodeEnqueuer extends AbstractService
{
	use ValidateAndSavableTrait;

	/**
	 * @var MediaTemp
	 */
	protected $mediaTemp;

	/**
	 * @var Album|Category
	 */
	protected $container;

	/**
	 * @var TranscodeQueue
	 */
	protected $queueItem;
	protected $queueData = [];

	/**
	 * @var Attachment
	 */
	protected $attachment;

	/**
	 * @var Changer
	 */
	protected $tagChanger;

	public function __construct(App $app, MediaTemp $mediaTemp)
	{
		parent::__construct($app);
		$this->setMediaTemp($mediaTemp);
	}

	protected function setMediaTemp(MediaTemp $mediaTemp)
	{
		$this->mediaTemp = $mediaTemp;
		$this->queueData['type'] = $mediaTemp->media_type;
		$this->queueItem = $this->em()->create('XFMG:TranscodeQueue');

		$this->setUser(\XF::visitor());
		$this->queueData['ip'] = $this->app->request()->getIp();
	}

	public function setUser(User $user)
	{
		$this->queueData['user_id'] = $user->user_id;
		$this->queueData['username'] = $user->username;
	}

	public function setContainer(Entity $container)
	{
		if ($container instanceof Album)
		{
			$this->setAlbum($container);
		}
		else if ($container instanceof Category)
		{
			$this->setCategory($container);
		}
		else
		{
			throw new \InvalidArgumentException("Container entity must be an album or category.");
		}

		$this->container = $container;
		$this->tagChanger = $this->service('XF:Tag\Changer', 'xfmg_media', $container);
	}

	public function setAlbum(Album $album)
	{
		$this->queueData['album_id'] = $album->album_id;
	}

	public function setCategory(Category $category)
	{
		$this->queueData['category_id'] = $category->category_id;
	}

	public function setTitle($title, $description = '')
	{
		$this->queueData['title'] = $title;
		$this->queueData['description'] = $description;
	}

	public function setTags($tags)
	{
		$this->queueData['tags'] = $tags;

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
		$fieldSet = $this->em()->create('XFMG:MediaItem')->custom_fields;
		$fieldDefinition = $fieldSet->getDefinitionSet()
			->filterEditable($fieldSet, 'user')
			->filterOnly($this->container->field_cache)
			->filter('display_add_media');

		$customFieldsShown = array_keys($fieldDefinition->getFieldDefinitions());

		if ($customFieldsShown)
		{
			$this->queueData['custom_fields'] = $customFields;
		}
		else
		{
			$this->queueData['custom_fields'] = [];
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
			/** @var Attachment $attachment */
			$attachment = $this->em()->find('XF:Attachment', $attachmentId, 'Data');

			if (!$attachment || $attachment['temp_hash'] != $attachmentHash)
			{
				throw new PrintableException('There was a problem associating the current attachment.');
			}
		}

		$this->attachment = $attachment;
		$this->queueData['attachment_id'] = $attachment->attachment_id;
		$this->queueData['fileName'] = $attachment->Data->getAbstractedDataPath();
	}

	protected function finalSetup()
	{
		$this->queueItem->queue_data = $this->queueData;
		$this->queueItem->queue_state = 'pending';
		$this->queueItem->queue_date = time();
	}

	protected function _validate()
	{
		$this->finalSetup();

		if (!$this->container->canAddMedia($error))
		{
			return [$error];
		}

		$queueItem = $this->queueItem;
		$queueItem->preSave();
		$errors = $queueItem->getErrors();

		if ($this->tagChanger->canEdit())
		{
			$tagErrors = $this->tagChanger->getErrors();
			if ($tagErrors)
			{
				$errors = array_merge($errors, $tagErrors);
			}
		}

		return $errors;
	}

	protected function _save()
	{
		$queueItem = $this->queueItem;
		$queueItem->save();
		return $queueItem;
	}

	public function afterInsert()
	{
		$queueClass = $this->app->extendClass('XFMG\Ffmpeg\Queue');

		/** @var Queue $queue */
		$queue = new $queueClass($this->app);
		$queue->queue();
	}
}
