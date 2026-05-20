<?php

namespace XFMG\Entity;

use XF\Api\Result\EntityResult;
use XF\BbCode\RenderableContentInterface;
use XF\Behavior\ActivityLoggable;
use XF\CustomField\Set;
use XF\Draft;
use XF\Entity\ApprovalQueue;
use XF\Entity\Attachment;
use XF\Entity\BookmarkItem;
use XF\Entity\BookmarkTrait;
use XF\Entity\ContainableInterface;
use XF\Entity\ContainableTrait;
use XF\Entity\DatableInterface;
use XF\Entity\DatableTrait;
use XF\Entity\DeletionLog;
use XF\Entity\EmbedResolverTrait;
use XF\Entity\FeaturedContent;
use XF\Entity\FeatureTrait;
use XF\Entity\LinkableInterface;
use XF\Entity\ReactionContent;
use XF\Entity\ReactionTrait;
use XF\Entity\User;
use XF\Entity\ViewableInterface;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Mvc\Router;
use XF\Repository\ActivityLogRepository;
use XF\Repository\UserAlert;
use XF\Util\Arr;
use XF\Util\File;
use XFMG\Exif\Formatter;
use XFMG\Repository\Media;

use function boolval, in_array, strval;

/**
 * COLUMNS
 * @property int|null $media_id
 * @property string $media_hash
 * @property string $title
 * @property string $title_
 * @property string $description
 * @property string $description_
 * @property int $media_date
 * @property int $last_edit_date
 * @property string $media_type
 * @property string $media_tag
 * @property string $media_embed_url
 * @property string $media_state
 * @property int $album_id
 * @property string|null $album_state
 * @property int $category_id
 * @property int $user_id
 * @property string $username
 * @property int $ip_id
 * @property int $view_count
 * @property bool $watermarked
 * @property array|null $custom_fields_
 * @property array|null $exif_data
 * @property int $warning_id
 * @property string $warning_message
 * @property int $position
 * @property int $imported
 * @property int $thumbnail_date
 * @property int $custom_thumbnail_date
 * @property int $poster_date
 * @property array|null $tags
 * @property int $last_comment_date
 * @property int $last_comment_id
 * @property int $last_comment_user_id
 * @property string $last_comment_username
 * @property int $comment_count
 * @property int $rating_count
 * @property int $rating_sum
 * @property float $rating_avg
 * @property float $rating_weighted
 * @property int $reaction_score
 * @property array|null $reactions_
 * @property array|null $reaction_users_
 * @property bool $featured
 *
 * GETTERS
 * @property-read string $lightbox_type
 * @property-read string $lightbox_src
 * @property-read string|null $thumbnail_url
 * @property-read mixed $current_thumbnail_url
 * @property-read bool $has_thumbnail
 * @property-read mixed $poster_url
 * @property-read mixed $has_poster
 * @property-read int $min_tags
 * @property-read string $media_site_id
 * @property-read Formatter $exif
 * @property Set $custom_fields
 * @property-read array $player_setup
 * @property-read array $structured_data
 * @property-read \XFMG\XF\Entity\Attachment|null $MirrorAttachment
 * @property-read Draft $draft_comment
 * @property-read string $content_type
 * @property-read mixed $comment_ids
 * @property-read array $rating_ids
 * @property mixed $reactions
 * @property mixed $reaction_users
 *
 * RELATIONS
 * @property-read Attachment|null $Attachment
 * @property-read Album|null $Album
 * @property-read Category|null $Category
 * @property-read ApprovalQueue|null $ApprovalQueue
 * @property-read AbstractCollection<MediaWatch> $Watch
 * @property-read DeletionLog|null $DeletionLog
 * @property-read User|null $User
 * @property-read AbstractCollection<MediaUserView> $Viewed
 * @property-read AbstractCollection<\XF\Entity\Draft> $DraftComments
 * @property-read Comment|null $LastComment
 * @property-read User|null $LastCommenter
 * @property-read AbstractCollection<MediaCommentRead> $CommentRead
 * @property-read AbstractCollection<Rating> $Ratings
 * @property-read AbstractCollection<ReactionContent> $Reactions
 * @property-read FeaturedContent|null $Feature
 * @property-read AbstractCollection<BookmarkItem> $Bookmarks
 */
class MediaItem extends Entity implements ContainableInterface, DatableInterface, LinkableInterface, RenderableContentInterface, ViewableInterface
{
	use BookmarkTrait;
	use CommentableTrait
	{
		commentAdded as protected _commentAdded;
		commentRemoved as protected _commentRemoved;
	}
	use ContainableTrait;
	use DatableTrait;
	use EmbedResolverTrait;
	use FeatureTrait;
	use RateableTrait;
	use ReactionTrait;

	public function canView(&$error = null)
	{
		if (!$this->hasPermission('view'))
		{
			return false;
		}

		$canView = false;

		if ($this->category_id && $this->Category)
		{
			$canView = $this->Category->canView($error);
		}
		else if ($this->album_id && $this->Album)
		{
			$canView = $this->Album->canView($error);
		}

		if (!$canView)
		{
			return false;
		}

		/** @var \XFMG\XF\Entity\User $visitor */
		$visitor = \XF::visitor();

		if ($this->media_state == 'moderated')
		{
			if (
				!$this->hasPermission('viewModerated')
				&& (!$visitor->user_id || $visitor->user_id != $this->user_id)
			)
			{
				$error = \XF::phraseDeferred('xfmg_requested_media_item_not_found');
				return false;
			}
		}
		else if ($this->media_state == 'deleted')
		{
			if (!$this->hasPermission('viewDeleted'))
			{
				$error = \XF::phraseDeferred('xfmg_requested_media_item_not_found');
				return false;
			}
		}

		return true;
	}

	public function canEdit(&$error = null)
	{
		$visitor = \XF::visitor();
		if (!$visitor->user_id)
		{
			return false;
		}

		if ($this->hasPermission('editAny'))
		{
			return true;
		}

		if ($this->user_id == $visitor->user_id && $this->hasPermission('editOwn'))
		{
			$editLimit = $this->hasPermission('editOwnMediaTimeLimit');
			if ($editLimit != -1 && (!$editLimit || $this->media_date < \XF::$time - 60 * $editLimit))
			{
				$error = \XF::phrase('xfmg_time_limit_to_edit_this_media_x_minutes_has_expired', ['editLimit' => $editLimit]);
				return false;
			}

			return true;
		}

		return false;
	}

	public function canEditImage(&$error = null)
	{
		if ($this->media_type != 'image')
		{
			$error = \XF::phraseDeferred('xfmg_only_images_can_be_manipulated');
			return false;
		}

		return $this->canEdit($error);
	}

	public function canChangeThumbnail(&$error = null)
	{
		return $this->canEdit($error);
	}

	public function canMove(&$error = null)
	{
		$visitor = \XF::visitor();
		if (!$visitor->user_id)
		{
			return false;
		}

		if ($this->hasPermission('moveAny'))
		{
			return true;
		}

		if ($this->user_id == $visitor->user_id && $this->hasPermission('moveOwn'))
		{
			return true;
		}

		return false;
	}

	protected function canBookmarkContent(&$error = null)
	{
		return $this->isVisible();
	}

	/**
	 * @param Entity|Album|Category $container
	 * @param null $error
	 * @return bool
	 */
	public function canMoveMediaTo(Entity $container, &$error = null)
	{
		if (!$this->canMove($error))
		{
			return false;
		}

		if (!in_array($this->media_type, $container->allowed_types))
		{
			$error = \XF::phrase('xfmg_selected_container_does_not_support_this_media_type');
			return false;
		}

		if ($container instanceof Album)
		{
			if ($container->album_id == $this->album_id)
			{
				return false;
			}

			if ($this->hasPermission('moveAny'))
			{
				return true;
			}

			return $container->canAddMedia($error);
		}
		else if ($container instanceof Category)
		{
			if ($container->category_id == $this->category_id)
			{
				return false;
			}

			if ($this->hasPermission('moveAny'))
			{
				return true;
			}

			return $container->canAddMedia($error);
		}
		else
		{
			return false;
		}
	}

	public function canDelete($type = 'soft', &$error = null)
	{
		$visitor = \XF::visitor();
		if (!$visitor->user_id)
		{
			return false;
		}

		if ($type != 'soft' && !$this->hasPermission('hardDeleteAny'))
		{
			return false;
		}

		if ($this->hasPermission('deleteAny'))
		{
			return true;
		}

		if ($this->user_id == $visitor->user_id && $this->hasPermission('deleteOwn'))
		{
			$editLimit = $this->hasPermission('editOwnMediaTimeLimit');
			if ($editLimit != -1 && (!$editLimit || $this->media_date < \XF::$time - 60 * $editLimit))
			{
				$error = \XF::phrase('xfmg_time_limit_to_delete_this_media_x_minutes_has_expired', ['editLimit' => $editLimit]);
				return false;
			}

			return true;
		}

		return false;
	}

	public function canUndelete(&$error = null)
	{
		$visitor = \XF::visitor();
		return $visitor->user_id && $this->hasPermission('undelete');
	}

	public function canApproveUnapprove(&$error = null)
	{
		$visitor = \XF::visitor();
		return $visitor->user_id && $this->hasPermission('approveUnapprove');
	}

	public function canFeatureUnfeature(&$error = null): bool
	{
		$visitor = \XF::visitor();
		return $visitor->user_id && $this->hasPermission('featureUnfeature');
	}

	public function canWarn(&$error = null)
	{
		$visitor = \XF::visitor();

		if (!$this->user_id
			|| !$visitor->user_id
			|| $this->user_id == $visitor->user_id
			|| !$this->hasPermission('warn')
		)
		{
			return false;
		}

		if ($this->warning_id)
		{
			$error = \XF::phraseDeferred('user_has_already_been_warned_for_this_content');
			return false;
		}

		return ($this->User && $this->User->isWarnable());
	}

	public function canReport(&$error = null, ?User $asUser = null)
	{
		$asUser = $asUser ?: \XF::visitor();
		return $asUser->canReport($error);
	}

	public function canReact(&$error = null)
	{
		$visitor = \XF::visitor();
		if (!$visitor->user_id)
		{
			return false;
		}

		if ($this->media_state != 'visible')
		{
			return false;
		}

		if ($this->user_id == $visitor->user_id)
		{
			$error = \XF::phraseDeferred('reacting_to_your_own_content_is_considered_cheating');
			return false;
		}

		return $this->hasPermission('react');
	}

	public function canSetAsAvatar()
	{
		$visitor = \XF::visitor();

		return ($visitor->user_id && $visitor->canUploadAvatar() && $this->media_type == 'image');
	}

	public function canAddNote(&$error = null)
	{
		$visitor = \XF::visitor();

		if (!$visitor->user_id || $this->media_type != 'image')
		{
			return false;
		}

		if ($this->hasPermission('addNoteAny'))
		{
			return true;
		}

		if ($visitor->user_id == $this->user_id && $this->hasPermission('addNoteOwn'))
		{
			return true;
		}

		return false;
	}

	public function canCleanSpam()
	{
		return (\XF::visitor()->canCleanSpam() && $this->User && $this->User->isPossibleSpammer());
	}

	public function canSendModeratorActionAlert()
	{
		$visitor = \XF::visitor();

		if (!$visitor->user_id || $visitor->user_id == $this->user_id)
		{
			return false;
		}

		if ($this->media_state != 'visible')
		{
			return false;
		}

		return true;
	}

	public function canWatch(&$error = null)
	{
		return \XF::visitor()->user_id;
	}

	public function canEditTags(&$error = null)
	{
		if ($this->category_id && $this->Category)
		{
			return $this->Category->canEditTags($this, $error);
		}
		else
		{
			return $this->Album->canEditTags($this, $error);
		}
	}

	public function canUseInlineModeration(&$error = null)
	{
		$visitor = \XF::visitor();

		if (!$visitor->user_id)
		{
			return false;
		}

		return ($this->hasPermission('inlineMod') || $visitor->user_id == $this->user_id);
	}

	public function canViewModeratorLogs(&$error = null)
	{
		$visitor = \XF::visitor();
		return $visitor->user_id && ($this->hasPermission('editAny') || $this->hasPermission('deleteAny'));
	}

	public function canAddMediaWithoutWatermark()
	{
		return $this->hasPermission('addWithoutWatermark');
	}

	public function canAddWatermark($checkPermission = true, &$error = null)
	{
		$option = $this->app()->options()->xfmgWatermarking;

		if (empty($option['enabled']) || empty($option['watermark_hash']))
		{
			$error = \XF::phraseDeferred('xfmg_watermarking_is_not_enabled');
			return false;
		}

		$watermarkPath = $this->getMediaRepo()->getAbstractedWatermarkPath($option['watermark_hash']);
		if (!$this->app()->fs()->has($watermarkPath))
		{
			$error = \XF::phraseDeferred('xfmg_cannot_find_watermark_image_at_provided_path');
			return false;
		}

		return $this->_canAddRemoveWatermark($checkPermission, $error);
	}

	public function canRemoveWatermark($checkPermission = true, &$error = null)
	{
		return $this->_canAddRemoveWatermark($checkPermission, $error);
	}

	protected function _canAddRemoveWatermark($checkPermission = true, &$error = null)
	{
		if (!$this->Attachment)
		{
			return false;
		}

		if ($this->media_type != 'image' || $this->Attachment->extension == 'gif')
		{
			$error = \XF::phraseDeferred('xfmg_only_non_animated_images_can_be_watermarked');
			return false;
		}

		if ($checkPermission)
		{
			if ($this->hasPermission('watermarkAny'))
			{
				return true;
			}

			if ($this->user_id == \XF::visitor()->user_id && $this->hasPermission('watermarkOwn'))
			{
				return true;
			}

			return false;
		}
		else
		{
			return true;
		}
	}

	public function hasPermission($permission)
	{
		if ($this->category_id && $this->Category)
		{
			return $this->Category->hasPermission($permission);
		}
		else if ($this->album_id && $this->Album)
		{
			return $this->Album->hasPermission($permission);
		}
		else
		{
			// no category, no album, no album inside a category, so just get the global value
			// most likely in the process of creating an album while uploading
			return \XF::visitor()->hasPermission('xfmg', $permission);
		}
	}

	public function isVisible()
	{
		return ($this->media_state == 'visible');
	}

	public function isIgnored()
	{
		return \XF::visitor()->isIgnoring($this->user_id);
	}

	public function getAttachmentConstraints()
	{
		if ($this->category_id && $this->Category)
		{
			return $this->Category->getAttachmentConstraints();
		}
		else
		{
			return $this->Album->getAttachmentConstraints();
		}
	}

	public function getBreadcrumbs($includeSelf = true)
	{
		/** @var Router $router */
		$router = $this->app()->container('router.public');

		if ($this->album_id && $this->Album)
		{
			$output = $this->Album->getBreadcrumbs();
		}
		else
		{
			$output = $this->Category->getBreadcrumbs();
		}

		if ($includeSelf)
		{
			$output[] = [
				'value' => $this->title,
				'href' => $router->buildLink('media', $this),
			];
		}

		return $output;
	}

	/**
	 * @return string
	 */
	public function getLightboxType(): string
	{
		switch ($this->media_type)
		{
			case 'video':
			case 'audio':
			case 'embed':
				return 'ajax';
			default:
				return 'image';
		}
	}

	/**
	 * @param bool $canonical
	 *
	 * @return string
	 */
	public function getLightboxSrc(bool $canonical = false): string
	{
		$router = $this->app()->router('public');

		switch ($this->media_type)
		{
			case 'video':
			case 'audio':
			case 'embed':
				return $router->buildLink(($canonical ? 'canonical:' : '') . 'media/lightbox', $this);
			default:
				return $router->buildLink(
					($canonical ? 'canonical:' : '') . 'media/full',
					$this,
					['d' => $this->last_edit_date ?: null]
				);
		}
	}

	public function hasPoster()
	{
		return boolval($this->poster_date);
	}

	public function hasThumbnail(): bool
	{
		return boolval($this->thumbnail_date || $this->custom_thumbnail_date);
	}

	/**
	 * @param bool $canonical
	 *
	 * @return string|null
	 */
	public function getThumbnailUrl($canonical = false)
	{
		if (!$this->thumbnail_date)
		{
			return null;
		}

		$mediaId = $this->media_id;

		$path = sprintf(
			"xfmg/thumbnail/%d/%d-%s.jpg?{$this->thumbnail_date}",
			floor($mediaId / 1000),
			$mediaId,
			$this->media_hash
		);
		return $this->app()->applyExternalDataUrl($path, $canonical);
	}

	/**
	 * @param bool $canonical
	 *
	 * @return mixed|null
	 */
	public function getCustomThumbnailUrl($canonical = false)
	{
		if (!$this->custom_thumbnail_date)
		{
			return null;
		}

		$mediaId = $this->media_id;

		$path = sprintf(
			"xfmg/custom_thumbnail/%d/%d-%s.jpg?{$this->custom_thumbnail_date}",
			floor($mediaId / 1000),
			$mediaId,
			$this->media_hash
		);
		return $this->app()->applyExternalDataUrl($path, $canonical);
	}

	public function getCurrentThumbnailUrl($canonical = false)
	{
		if ($this->custom_thumbnail_date)
		{
			return $this->getCustomThumbnailUrl($canonical);
		}
		else if ($this->thumbnail_date)
		{
			return $this->getThumbnailUrl($canonical);
		}
		else
		{
			return null;
		}
	}

	public function getPosterUrl($canonical = false)
	{
		if ($this->poster_date)
		{
			$mediaId = $this->media_id;

			$path = sprintf(
				"xfmg/poster/%d/%d-%s.jpg?{$this->poster_date}",
				floor($mediaId / 1000),
				$mediaId,
				$this->media_hash
			);
			return $this->app()->applyExternalDataUrl($path, $canonical);
		}
		else
		{
			return $this->getCurrentThumbnailUrl($canonical);
		}
	}

	public function getVideoUrl($canonical = false)
	{
		if ($this->media_type != 'video' || !$this->Attachment)
		{
			return null;
		}

		return $this->Attachment->getDirectUrl($canonical);
	}

	public function getAudioUrl($canonical = false)
	{
		if ($this->media_type != 'audio' || !$this->Attachment)
		{
			return null;
		}

		return $this->Attachment->getDirectUrl($canonical);
	}

	public function getAbstractedThumbnailPath()
	{
		$mediaId = $this->media_id;

		return sprintf(
			'data://xfmg/thumbnail/%d/%d-%s.jpg',
			floor($mediaId / 1000),
			$mediaId,
			$this->media_hash
		);
	}

	public function getAbstractedCustomThumbnailPath()
	{
		$mediaId = $this->media_id;

		return sprintf(
			'data://xfmg/custom_thumbnail/%d/%d-%s.jpg',
			floor($mediaId / 1000),
			$mediaId,
			$this->media_hash
		);
	}

	public function getAbstractedCustomThumbnailOriginalPath()
	{
		$mediaId = $this->media_id;

		return sprintf(
			'internal-data://xfmg/custom_thumbnail/%d/%d-%s.jpg',
			floor($mediaId / 1000),
			$mediaId,
			$this->media_hash
		);
	}

	public function getAbstractedPosterPath()
	{
		$mediaId = $this->media_id;

		return sprintf(
			'data://xfmg/poster/%d/%d-%s.jpg',
			floor($mediaId / 1000),
			$mediaId,
			$this->media_hash
		);
	}

	public function getAvailableAbstractedDataPath()
	{
		$abstractedDataPath = null;

		if ($this->watermarked)
		{
			$originalPath = $this->getOriginalAbstractedDataPath();
			if ($this->app()->fs()->has($originalPath))
			{
				$abstractedDataPath = $originalPath;
			}
		}

		if (!$abstractedDataPath
			&& $this->Attachment
			&& $this->Attachment->Data
		)
		{
			$abstractedDataPath = $this->Attachment->Data->getAbstractedDataPath();
		}

		return $abstractedDataPath;
	}

	public function getAbstractedDataPath()
	{
		if ($this->watermarked)
		{
			return $this->getOriginalAbstractedDataPath();
		}
		else if ($this->Attachment && $this->Attachment->Data)
		{
			return $this->Attachment->Data->getAbstractedDataPath();
		}
		return null;
	}

	public function getOriginalAbstractedDataPath()
	{
		return sprintf(
			'internal-data://xfmg/original/%d/%d-%s.data',
			floor($this->media_id / 1000),
			$this->media_id,
			$this->media_hash
		);
	}

	/**
	 * @return int
	 */
	public function getMinTags()
	{
		if ($this->category_id && $this->Category)
		{
			return $this->Category->min_tags;
		}
		else
		{
			return $this->Album->min_tags;
		}
	}

	/**
	 * @return string
	 */
	public function getMediaSiteId()
	{
		if (preg_match('/\[MEDIA=([a-z0-9_]+)\].*\[\/MEDIA\]/i', $this->media_tag, $match))
		{
			if (isset($match[1]))
			{
				return $match[1];
			}
		}

		return '';
	}

	public function getSiteMediaId()
	{
		if (preg_match('/\[MEDIA=[a-z0-9_]+\](.*)\[\/MEDIA\]/i', $this->media_tag, $match))
		{
			if (isset($match[1]))
			{
				return $match[1];
			}
		}

		return '';
	}

	/**
	 * @return Formatter
	 */
	public function getExif()
	{
		$class = \XF::extendClass('XFMG\Exif\Formatter');
		return new $class($this, $this->exif_data);
	}

	public function getFieldEditMode()
	{
		$visitor = \XF::visitor();

		$isSelf = ($visitor->user_id == $this->user_id || !$this->media_id);
		$isMod = ($visitor->user_id && $this->hasPermission('editAny'));

		if ($isMod || !$isSelf)
		{
			return $isSelf ? 'moderator_user' : 'moderator';
		}
		else
		{
			return 'user';
		}
	}

	/**
	 * @return Set
	 */
	public function getCustomFields()
	{
		$class = 'XF\CustomField\Set';
		$class = $this->app()->extendClass($class);

		$fieldDefinitions = $this->app()->container('customFields.xfmgMediaFields');

		return new $class($fieldDefinitions, $this);
	}

	public function getExtraFieldBlocks()
	{
		if (!$this->getValue('custom_fields'))
		{
			// if they haven't set anything, we can bail out quickly
			return [];
		}

		/** @var Set $fieldSet */
		$fieldSet = $this->custom_fields;
		$definitionSet = $fieldSet->getDefinitionSet()
			->filterOnly($this->category_id ? $this->Category->field_cache : $this->Album->field_cache)
			->filterGroup('new_sidebar_block')
			->filterWithValue($fieldSet);

		$output = [];
		foreach ($definitionSet AS $fieldId => $definition)
		{
			$output[$fieldId] = $definition;
		}

		return $output;
	}

	/**
	 * @return MediaNote
	 */
	public function getNewNote()
	{
		$note = $this->em()->create('XFMG:MediaNote');
		$note->media_id = $this->media_id;

		return $note;
	}

	/**
	 * @return array
	 */
	public function getPlayerSetup()
	{
		$setup = [
			'controls' => true,
			'autoplay' => false,
			'preload' => 'auto',
		];

		if ($this->media_type == 'audio')
		{
			$setup['controlBar'] = ['fullscreenToggle' => false];

			if ($this->thumbnail_date)
			{
				$setup['poster'] = $this->getThumbnailUrl();
			}
			else
			{
				$setup['aspectRatio'] = '1:0';
				$setup['fluid'] = true;
			}
		}

		return $setup;
	}

	/**
	 * @return array
	 */
	public function getStructuredData()
	{
		$attachment = $this->Attachment;
		$data = $attachment ? $attachment->Data : null;

		$options = $this->app()->options();
		$router = $this->app()->router('public');
		$strFormatter = $this->app()->stringFormatter();
		$mediaLink = $this->getContentUrl(true);
		$userLink = $this->User ? $this->User->getContentUrl(true) : null;

		switch ($this->media_type)
		{
			case 'image':
				$type = 'ImageObject';
				$contentUrl = $router->buildLink('canonical:media/full', $this);
				$encodingFormat = $attachment ? $attachment->extension : null;
				break;

			case 'audio':
				$type = 'AudioObject';
				$contentUrl = $this->getAudioUrl(true);
				$encodingFormat = 'mp3';
				break;

			case 'video':
				$type = 'VideoObject';
				$contentUrl = $this->getVideoUrl(true);
				$encodingFormat = 'mp4';
				break;

			default:
				$type = 'MediaObject';
				$contentUrl = $router->buildLink('canonical:media/full', $this);
				$encodingFormat = null;
				break;
		}

		$mainEntity = [
			'@type' => $type,
			'@id' => $mediaLink,
			'name' => $this->title,
			'dateCreated' => gmdate('c', $this->media_date),
			'dateModified' => $this->last_edit_date
				? gmdate('c', $this->last_edit_date)
				: null,
			'keywords' => $this->tags
				? implode(', ', array_column($this->tags, 'tag'))
				: null,
			'url' => $mediaLink,
			'thumbnailUrl' => $this->thumbnail_date
				? $this->getThumbnailUrl(true)
				: null,
			'uploadDate' => gmdate('c', $this->media_date),
			'contentUrl' => $contentUrl,
			'contentSize' => $attachment ? $attachment->file_size : null,
			'encodingFormat' => $encodingFormat,
			'description' => $strFormatter->snippetString($this->description, 0),
			'author' => [
				'@type' => 'Person',
				'@id' => $userLink,
				'name' => $this->User->username ?? $this->username,
				'url' => $userLink,
			],
			'interactionStatistic' => [
				[
					'@type' => 'InteractionCounter',
					'interactionType' => 'https://schema.org/LikeAction',
					'userInteractionCount' => $this->reaction_score,
				],
				[
					'@type' => 'InteractionCounter',
					'interactionType' => 'https://schema.org/ViewAction',
					'userInteractionCount' => $this->view_count,
				],
				[
					'@type' => 'InteractionCounter',
					'interactionType' => 'https://schema.org/CommentAction',
					'userInteractionCount' => $this->comment_count,
				],
			],
		];

		if ($this->media_type == 'image')
		{
			$mainEntity['width'] = [
				'@type' => 'Distance',
				'name' => strval($data ? $data->width : 0) . ' px',
			];
			$mainEntity['height'] = [
				'@type' => 'Distance',
				'name' => strval($data ? $data->height : 0) . ' px',
			];
		}

		if ($this->rating_count)
		{
			$mainEntity['aggregateRating'] = [
				'@type' => 'AggregateRating',
				'ratingCount' => $this->rating_count,
				'ratingValue' => $this->rating_avg,
			];
		}

		$logo = null;
		$style = \XF::app()->templater()->getStyle();
		if ($style)
		{
			$logo = $style->getProperty('publicMetadataLogoUrl') ?: null;
			if ($logo)
			{
				$pather = \XF::app()['request.pather'];
				$logo = $pather($logo, 'canonical');
			}
		}

		$structuredData = [
			'@context' => 'https://schema.org',
			'@type' => 'WebPage',
			'url' => $mediaLink,
			'mainEntity' => $mainEntity,
			'publisher' => [
				'@type' => 'Organization',
				'name' => $options->boardTitle,
				'alternateName' => $options->boardShortTitle ?: null,
				'description' => $options->boardDescription ?: null,
				'url' => $options->boardUrl,
				'logo' => $logo,
			],
		];

		return Arr::filterNull($structuredData, true);
	}

	/**
	 * @return \XFMG\XF\Entity\Attachment|null
	 */
	public function getMirrorAttachment()
	{
		$attachment = $this->Attachment;
		if (!$attachment)
		{
			return null;
		}

		return $this->_em->findOne('XF:Attachment', [
			'data_id' => $attachment->data_id,
			'xfmg_is_mirror_handler' => 1,
		]);
	}

	public function getBbCodeRenderOptions($context, $type)
	{
		return [
			'entity' => $this,
			'user' => $this->User,
		];
	}

	public function commentAdded(Comment $comment)
	{
		$this->_commentAdded($comment);

		$activityLogRepo = $this->repository(ActivityLogRepository::class);
		$activityLogRepo->log($this, $comment->comment_date, [
			'reply_count' => 1,
		]);
	}

	public function commentRemoved(Comment $comment)
	{
		$this->_commentRemoved($comment);

		$activityLogRepo = $this->repository(ActivityLogRepository::class);
		$activityLogRepo->log($this, $comment->comment_date, [
			'reply_count' => -1,
		]);
	}

	public function rebuildCounters()
	{
		$this->rebuildCommentCount();
		$this->rebuildLastCommentInfo();
		$this->rebuildRating();

		return true;
	}

	public function rebuildThumbnail()
	{
		/** @var Media $mediaRepo */
		$mediaRepo = $this->app()->repository('XFMG:Media');

		$success = false;

		switch ($this->media_type)
		{
			case 'image':
				if ($this->Attachment && $this->Attachment->Data)
				{
					$success = $mediaRepo->rebuildImageThumb($this);
				}
				break;

			case 'audio':
			case 'video':
				if ($this->Attachment && $this->Attachment->Data)
				{
					$success = $mediaRepo->rebuildFFmpegThumb($this);
				}
				break;

			case 'embed':
				$success = $mediaRepo->rebuildEmbedThumb($this);
				break;
		}

		if ($success)
		{
			$this->thumbnail_date = time();
		}
		else
		{
			$this->thumbnail_date = 0;
		}

		if ($this->custom_thumbnail_date)
		{
			if ($mediaRepo->rebuildCustomThumbnail($this))
			{
				$this->custom_thumbnail_date = time();
			}
			else
			{
				$this->custom_thumbnail_date = 0;
			}
		}

		$this->saveIfChanged();
	}

	public function rebuildPoster()
	{
		/** @var Media $mediaRepo */
		$mediaRepo = $this->app()->repository('XFMG:Media');

		$success = false;

		switch ($this->media_type)
		{
			case 'audio':
			case 'video':
				if ($this->Attachment && $this->Attachment->Data)
				{
					$success = $mediaRepo->rebuildFFmpegPoster($this);
				}
				break;
		}

		if ($success)
		{
			$this->poster_date = time();
		}
		else
		{
			$this->poster_date = 0;
		}

		$this->saveIfChanged();
	}

	public function softDelete($reason = '', ?User $byUser = null)
	{
		$byUser = $byUser ?: \XF::visitor();

		$db = $this->db();
		$db->beginTransaction();

		$mediaState = $db->fetchOne(
			'SELECT media_state
				FROM xf_mg_media_item
				WHERE media_id = ?
				FOR UPDATE',
			$this->media_id
		);
		if ($mediaState === 'deleted')
		{
			$db->commit();
			return false;
		}

		$this->media_state = 'deleted';

		/** @var DeletionLog $deletionLog */
		$deletionLog = $this->getRelationOrDefault('DeletionLog');
		$deletionLog->setFromUser($byUser);
		$deletionLog->delete_reason = $reason;

		$this->save(true, false);

		$db->commit();

		return true;
	}

	protected function _preSave()
	{
		if ($this->isInsert() || $this->isChanged(['rating_sum', 'rating_count']))
		{
			$this->updateRatingAverage();
		}
	}

	protected function _postSave()
	{
		$visibilityChange = $this->isStateChanged('media_state', 'visible');
		$approvalChange = $this->isStateChanged('media_state', 'moderated');
		$deletionChange = $this->isStateChanged('media_state', 'deleted');

		if ($this->isUpdate())
		{
			if ($visibilityChange == 'enter')
			{
				if (!$this->Album || $this->Album->album_state == 'visible')
				{
					$this->adjustUserMediaCountIfNeeded(1);
					$this->adjustUserMediaQuotaIfNeeded();
				}
			}
			else if ($visibilityChange == 'leave')
			{
				if (!$this->Album || $this->Album->album_state == 'visible')
				{
					$this->adjustUserMediaCountIfNeeded(-1);
					$this->adjustUserMediaQuotaIfNeeded(true);
				}

				/** @var UserAlert $alertRepo */
				$alertRepo = $this->repository('XF:UserAlert');
				$alertRepo->fastDeleteAlertsForContent('xfmg_media', $this->media_id);
			}

			if ($deletionChange == 'leave' && $this->DeletionLog)
			{
				$this->DeletionLog->delete();
			}

			if ($approvalChange == 'leave' && $this->ApprovalQueue)
			{
				$this->ApprovalQueue->delete();
			}
		}
		else
		{
			// insert
			if ($this->media_state == 'visible')
			{
				if (!$this->Album || $this->Album->album_state == 'visible')
				{
					$this->adjustUserMediaCountIfNeeded(1);
					$this->adjustUserMediaQuotaIfNeeded();
				}
			}
		}

		if ($approvalChange == 'enter')
		{
			$approvalQueue = $this->getRelationOrDefault('ApprovalQueue', false);
			$approvalQueue->content_date = $this->media_date;
			$approvalQueue->save();
		}
		else if ($deletionChange == 'enter' && !$this->DeletionLog)
		{
			$delLog = $this->getRelationOrDefault('DeletionLog', false);
			$delLog->setFromVisitor();
			$delLog->save();
		}

		$this->updateContainerRecord();

		if ($this->isUpdate() && $this->isChanged('media_embed_url'))
		{
			\XF::runLater(function ()
			{
				$this->rebuildThumbnail();
			});
		}

		if ($this->isUpdate() && $this->getOption('log_moderator'))
		{
			$this->app()->logger()->logModeratorChanges('xfmg_media', $this);
		}

		if ($this->getExistingValue('custom_thumbnail_date') && !$this->custom_thumbnail_date)
		{
			File::deleteFromAbstractedPath($this->getAbstractedCustomThumbnailPath());
			File::deleteFromAbstractedPath($this->getAbstractedCustomThumbnailOriginalPath());
		}

		if ($this->getExistingValue('watermarked') && !$this->watermarked)
		{
			File::deleteFromAbstractedPath($this->getOriginalAbstractedDataPath());
		}

		if ($this->isUpdate()
			&& $this->isChanged(['thumbnail_date', 'custom_thumbnail_date'])
			&& $this->album_id
			&& $this->Album
		)
		{
			$this->Album->mediaItemThumbnailChanged($this);
		}

		$this->_postSaveBookmarks();
	}

	protected function adjustUserMediaCountIfNeeded($amount)
	{
		if ($this->user_id)
		{
			$this->db()->query("
				UPDATE xf_user
				SET xfmg_media_count = GREATEST(0, xfmg_media_count + ?)
				WHERE user_id = ?
			", [$amount, $this->user_id]);
		}
	}

	protected function adjustUserMediaQuotaIfNeeded($delete = false)
	{
		// TODO: This isn't behaving as I'd expect (nor how it used to IIRC)
		// TODO: Ideally we eventually want to not run this in a runLater closure.
		\XF::runLater(function () use ($delete)
		{
			$attachment = $this->Attachment;
			$user = $this->User;

			if ($attachment && $attachment->Data && !$attachment->Data->xfmg_mirror_media_id && $user)
			{
				$fileSize = $attachment->getFileSize();
				$existing = $user->xfmg_media_quota;

				if ($delete)
				{
					$user->xfmg_media_quota = ($existing - $fileSize) / 1024;
				}
				else
				{
					$user->xfmg_media_quota = ($existing + $fileSize) / 1024;
				}

				$user->save();
			}
		});
	}

	protected function updateContainerRecord()
	{
		$updateContainer = function ($container)
		{
			if (!$container)
			{
				return;
			}

			/** @var Album|Category $container */

			if ($this->isUpdate() && $this->isChanged(['album_id', 'category_id']))
			{
				// media has been moved - standard state code not used
				if ($this->media_state == 'visible')
				{
					$container->mediaItemAdded($this);
					$container->save();
				}

				if ($this->getExistingValue('media_state') == 'visible')
				{
					/** @var Album $oldAlbum */
					$oldAlbum = $this->getExistingRelation('Album');
					if ($oldAlbum)
					{
						$oldAlbum->mediaItemRemoved($this);
						$oldAlbum->save();
					}

					/** @var Category $oldCategory */
					$oldCategory = $this->getExistingRelation('Category');
					if ($oldCategory)
					{
						$oldCategory->mediaItemRemoved($this);
						$oldCategory->save();
					}
				}

				return;
			}

			$visibilityChange = $this->isStateChanged('media_state', 'visible');
			if ($visibilityChange == 'enter')
			{
				$container->mediaItemAdded($this);
				$container->save();
			}
			else if ($visibilityChange == 'leave')
			{
				$container->mediaItemRemoved($this);
				$container->save();
			}
		};

		if ($this->Album && $this->Album->exists())
		{
			$updateContainer($this->Album);
		}
		if ($this->Category && $this->Category->exists())
		{
			$updateContainer($this->Category);
		}
	}

	protected function _postDelete()
	{
		if ($this->media_state == 'visible')
		{
			if (!$this->Album || $this->Album->album_state == 'visible')
			{
				$this->adjustUserMediaCountIfNeeded(-1);
				$this->adjustUserMediaQuotaIfNeeded(true);
			}

			if ($this->Category)
			{
				$this->Category->mediaItemRemoved($this);
				$this->Category->save(false);
			}
			if ($this->Album)
			{
				$this->Album->mediaItemRemoved($this);
				$this->Album->save(false);
			}
		}
		else if ($this->media_state == 'deleted')
		{
			if ($this->DeletionLog)
			{
				$this->DeletionLog->delete();
			}
		}
		else if ($this->media_state == 'moderated')
		{
			if ($this->ApprovalQueue)
			{
				$this->ApprovalQueue->delete();
			}
		}

		$db = $this->db();

		$deleteFrom = [
			'xf_mg_media_comment_read',
			'xf_mg_media_user_view',
			'xf_mg_media_watch',
		];

		foreach ($deleteFrom AS $table)
		{
			$db->delete($table, 'media_id = ?', $this->media_id);
		}

		/** @var MediaNote[] $mediaNotes */
		$mediaNotes = $this->repository('XFMG:MediaNote')->findNotesForMedia($this->media_id)->fetch();
		foreach ($mediaNotes AS $mediaNote)
		{
			$mediaNote->delete();
		}

		if ($this->getOption('log_moderator'))
		{
			$this->app()->logger()->logModeratorAction('xfmg_media', $this, 'delete_hard');
		}

		if (!empty($this->Attachment->Data) && $this->Attachment->Data->xfmg_mirror_media_id == $this->media_id)
		{
			$this->Attachment->Data->fastUpdate('xfmg_mirror_media_id', 0);
		}

		/** @var \XF\Repository\Attachment $attachRepo */
		$attachRepo = $this->repository('XF:Attachment');
		$attachRepo->fastDeleteContentAttachments('xfmg_media', $this->media_id);

		/** @var UserAlert $alertRepo */
		$alertRepo = $this->repository('XF:UserAlert');
		$alertRepo->fastDeleteAlertsForContent('xfmg_media', $this->media_id);

		if ($this->watermarked)
		{
			$originalPath = $this->getOriginalAbstractedDataPath();
			File::deleteFromAbstractedPath($originalPath);
		}

		if ($this->thumbnail_date)
		{
			$thumbPath = $this->getAbstractedThumbnailPath();
			File::deleteFromAbstractedPath($thumbPath);
		}

		if ($this->poster_date)
		{
			$posterPath = $this->getAbstractedPosterPath();
			File::deleteFromAbstractedPath($posterPath);
		}

		if ($this->custom_thumbnail_date)
		{
			$customThumbPath = $this->getAbstractedCustomThumbnailPath();
			File::deleteFromAbstractedPath($customThumbPath);

			$originalCustomThumbPath = $this->getAbstractedCustomThumbnailOriginalPath();
			File::deleteFromAbstractedPath($originalCustomThumbPath);
		}

		$this->_postDeleteComments();
		$this->_postDeleteRatings();
		$this->_postDeleteBookmarks();
	}

	public function getContentContainerIdColumn(): string
	{
		return 'category_id';
	}

	public function getContentContainerType(): string
	{
		if ($this->album_id)
		{
			return 'xfmg_album';
		}

		if ($this->category_id)
		{
			return 'xfmg_category';
		}

		return '';
	}

	public function getContentDateColumn(): string
	{
		return 'media_date';
	}

	public function getContentUrl(bool $canonical = false, array $extraParams = [], $hash = null)
	{
		$route = ($canonical ? 'canonical:' : '') . 'media';
		return $this->app()->router('public')->buildLink($route, $this, $extraParams, $hash);
	}

	public function getContentPublicRoute()
	{
		return 'media';
	}

	public function getContentTitle(string $context = '')
	{
		if ($this->album_id)
		{
			return \XF::phrase('xfmg_media_x_in_album_y', [
				'title' => $this->title,
				'album' => $this->Album->title,
			]);
		}
		else if ($this->category_id)
		{
			return \XF::phrase('xfmg_media_x_in_category_y', [
				'title' => $this->title,
				'category' => $this->Category->title,
			]);
		}
		else
		{
			return \XF::phrase('xfmg_media_x', [
				'media' => $this->title,
			]);
		}
	}

	/**
	 * @param EntityResult $result
	 * @param int $verbosity
	 * @param array $options
	 */
	protected function setupApiResultData(
		EntityResult $result,
		$verbosity = self::VERBOSITY_NORMAL,
		array $options = []
	)
	{
		$visitor = \XF::visitor();

		$result->username = $this->User ? $this->User->username : $this->username;

		if (!empty($options['with_container']))
		{
			if ($this->category_id)
			{
				$result->includeRelation('Category');
			}
			if ($this->album_id)
			{
				$result->includeRelation('Album');
			}
		}

		if ($this->media_type == 'embed')
		{
			$result->media_embed_url = $this->media_embed_url;
		}
		else
		{
			switch ($this->media_type)
			{
				case 'audio':
					$result->media_url = $this->getAudioUrl(true);
					break;

				case 'video':
					$result->media_url = $this->getVideoUrl(true);
					break;

				default:
					$result->media_url = $this->app()->router('api')->buildLink('canonical:media/data', $this);
			}

			$attachment = $this->Attachment;

			$result->file_size = $attachment->file_size;
			$result->height = $attachment->Data->height;
			$result->width = $attachment->Data->width;
		}

		$fieldCache = $this->category_id ? $this->Category->field_cache : $this->Album->field_cache;
		$result->custom_fields = (object) $this->custom_fields->getNamedFieldValues($fieldCache);
		$result->tags = array_column($this->tags, 'tag');

		if ($visitor->user_id)
		{
			$result->is_watching = isset($this->Watch[$visitor->user_id]);
		}

		$result->thumbnail_url = $this->getCurrentThumbnailUrl(true);

		if ($result->getResultType() === EntityResult::TYPE_API)
		{
			$result->can_edit = $this->canEdit();
			$result->can_edit_tags = $this->canEditTags();
			$result->can_soft_delete = $this->canDelete();
			$result->can_hard_delete = $this->canDelete('hard');
			$result->can_react = $this->canReact();
		}

		$this->addReactionStateToApiResult($result);

		$result->view_url = $this->getContentUrl(true);
	}

	public static function getStructure(Structure $structure)
	{
		$options = \XF::options();

		$structure->table = 'xf_mg_media_item';
		$structure->shortName = 'XFMG:MediaItem';
		$structure->contentType = 'xfmg_media';
		$structure->primaryKey = 'media_id';
		$structure->columns = [
			'media_id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
			'media_hash' => ['type' => self::STR, 'maxLength' => 32, 'required' => true],
			'title' => ['type' => self::STR, 'required' => true, 'censor' => true, 'api' => true,
				'maxLength' => $options->xfmgMaxTitleLength ?? 150,
			],
			'description' => ['type' => self::STR, 'default' => '', 'censor' => true, 'api' => true,
				'maxLength' => $options->xfmgMaxDescriptionLength ?? 300,
			],
			'media_date' => ['type' => self::UINT, 'default' => \XF::$time, 'api' => true],
			'last_edit_date' => ['type' => self::UINT, 'default' => 0, 'api' => true],
			'media_type' => ['type' => self::STR, 'required' => true,
				'allowedValues' => ['image', 'video', 'audio', 'embed'], 'api' => true,
			],
			'media_tag' => ['type' => self::STR],
			'media_embed_url' => ['type' => self::STR],
			'media_state' => ['type' => self::STR, 'default' => 'visible',
				'allowedValues' => ['visible', 'moderated', 'deleted'], 'api' => true,
			],
			'album_id' => ['type' => self::UINT, 'default' => 0, 'api' => true],
			'album_state' => ['type' => self::STR, 'default' => null, 'nullable' => true,
				'allowedValues' => ['visible', 'moderated', 'deleted'], 'api' => true,
			],
			'category_id' => ['type' => self::UINT, 'default' => 0, 'api' => true],
			'user_id' => ['type' => self::UINT, 'default' => 0, 'api' => true],
			'username' => ['type' => self::STR, 'maxLength' => 50],
			'ip_id' => ['type' => self::UINT, 'default' => 0],
			'view_count' => ['type' => self::UINT, 'default' => 0, 'api' => true],
			'watermarked' => ['type' => self::BOOL, 'default' => 0],
			'custom_fields' => ['type' => self::JSON_ARRAY, 'default' => []],
			'exif_data' => ['type' => self::JSON_ARRAY, 'default' => [], 'forced' => true],
			'warning_id' => ['type' => self::UINT, 'default' => 0],
			'warning_message' => ['type' => self::STR, 'default' => '', 'maxLength' => 255, 'api' => true],
			'position' => ['type' => self::UINT, 'default' => 0],
			'imported' => ['type' => self::UINT, 'default' => 0],
			'thumbnail_date' => ['type' => self::UINT, 'default' => 0],
			'custom_thumbnail_date' => ['type' => self::UINT, 'default' => 0],
			'poster_date' => ['type' => self::UINT, 'default' => 0],
			'tags' => ['type' => self::JSON_ARRAY, 'default' => []],
		];
		$structure->behaviors = [
			'XF:Reactable' => ['stateField' => 'media_state'],
			'XF:ReactableContainer' => [
				'childContentType' => 'xfmg_comment',
				'childIds' => function ($mediaItem) { return $mediaItem->comment_ids; },
				'stateField' => 'media_state',
			],
			'XF:Taggable' => ['stateField' => 'media_state'],
			'XF:NewsFeedPublishable' => [
				'usernameField' => 'username',
				'dateField' => 'media_date',
			],
			'XF:Featurable' => [
				'stateField' => 'media_state',
			],
			'XF:CustomFieldsHolder' => [
				'valueTable' => 'xf_mg_media_field_value',
				'checkForUpdates' => ['category_id', 'album_id'],
				'getAllowedFields' => function ($mediaItem)
				{
					if ($mediaItem->category_id && $mediaItem->Category)
					{
						return $mediaItem->Category->field_cache;
					}
					else if ($mediaItem->album_id && $mediaItem->Album)
					{
						return $mediaItem->Album->field_cache;
					}
					else
					{
						return [];
					}
				},
			],
			'XF:Indexable' => [
				'checkForUpdates' => ['title', 'description', 'user_id', 'category_id', 'album_id', 'media_date', 'tags', 'media_state'],
				'enqueueIndexNow' => true,
			],
			'XF:IndexableContainer' => [
				'childContentType' => 'xfmg_comment',
				'childIds' => function ($mediaItem) { return $mediaItem->comment_ids; },
				'checkForUpdates' => ['album_id', 'category_id', 'media_state'],
			],
			'XF:Webhook' => [],
			ActivityLoggable::class => [],
		];
		$structure->getters = [
			'lightbox_type' => true,
			'lightbox_src' => true,
			'thumbnail_url' => true,
			'current_thumbnail_url' => true,
			'has_thumbnail' => ['getter' => 'hasThumbnail', 'cache' => false],
			'poster_url' => true,
			'has_poster' => ['getter' => 'hasPoster', 'cache' => false],
			'min_tags' => true,
			'media_site_id' => true,
			'exif' => true,
			'custom_fields' => true,
			'player_setup' => true,
			'structured_data' => true,
			'MirrorAttachment' => true,
		];
		$structure->relations = [
			'Attachment' => [
				'entity' => 'XF:Attachment',
				'type' => self::TO_ONE,
				'conditions' => [
					['content_type', '=', 'xfmg_media'],
					['content_id', '=', '$media_id'],
				],
			],
			'Album' => [
				'entity' => 'XFMG:Album',
				'type' => self::TO_ONE,
				'conditions' => 'album_id',
				'primary' => true,
			],
			'Category' => [
				'entity' => 'XFMG:Category',
				'type' => self::TO_ONE,
				'conditions' => 'category_id',
				'primary' => true,
			],
			'ApprovalQueue' => [
				'entity' => 'XF:ApprovalQueue',
				'type' => self::TO_ONE,
				'conditions' => [
					['content_type', '=', 'xfmg_media'],
					['content_id', '=', '$media_id'],
				],
				'primary' => true,
			],
			'Watch' => [
				'entity' => 'XFMG:MediaWatch',
				'type' => self::TO_MANY,
				'conditions' => 'media_id',
				'key' => 'user_id',
			],
			'DeletionLog' => [
				'entity' => 'XF:DeletionLog',
				'type' => self::TO_ONE,
				'conditions' => [
					['content_type', '=', 'xfmg_media'],
					['content_id', '=', '$media_id'],
				],
				'primary' => true,
			],
			'User' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => 'user_id',
				'primary' => true,
				'api' => true,
			],
			'Viewed' => [
				'entity' => 'XFMG:MediaUserView',
				'type' => self::TO_MANY,
				'conditions' => 'media_id',
				'key' => 'user_id',
			],
		];
		$structure->defaultWith = [
			'Album', 'Category', 'User', 'Attachment', 'Attachment.Data',
		];
		$structure->options = [
			'log_moderator' => true,
		];
		$structure->withAliases = [
			'api' => [
				'User.api',
				'Attachment',
				'Attachment.Data',
				function ($withParams)
				{
					if (!empty($withParams['container']))
					{
						return ['Album.api', 'Category.api'];
					}
				},
				function ()
				{
					$userId = \XF::visitor()->user_id;
					if ($userId)
					{
						return [
							'Watch|' . $userId,
						];
					}
				},
			],
		];

		static::addCommentableStructureElements($structure);
		static::addRateableStructureElements($structure);
		static::addReactableStructureElements($structure);
		static::addFeaturableStructureElements($structure);
		static::addBookmarkableStructureElements($structure);

		return $structure;
	}

	/**
	 * @return Media
	 */
	protected function getMediaRepo()
	{
		return $this->repository('XFMG:Media');
	}
}
