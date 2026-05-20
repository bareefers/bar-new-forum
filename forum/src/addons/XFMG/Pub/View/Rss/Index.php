<?php

namespace XFMG\Pub\View\Rss;

use Laminas\Feed\Writer\Entry;
use Laminas\Feed\Writer\Feed;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\View;
use XF\Pub\View\FeedHelper;
use XFMG\Entity\MediaItem;

use function strval;

class Index extends View
{
	/**
	 * @return string
	 */
	public function renderRss()
	{
		/** @var string $title */
		$title = $this->params['feedTitle'];
		/** @var string $description */
		$description = $this->params['feedDescription'];
		/** @var string $feedLink */
		$feedLink = $this->params['feedLink'];
		/** @var string $link */
		$link = $this->params['link'];
		/** @var AbstractCollection<MediaItem> $mediaItems */
		$mediaItems = $this->params['mediaItems'];

		$feed = $this->createFeed($title, $description, $feedLink, $link);


		foreach ($mediaItems AS $mediaItem)
		{
			$feed->addEntry($this->createEntry($feed, $mediaItem));
		}

		return $this->exportFeed($feed);
	}

	protected function createFeed(
		string $title,
		string $description,
		string $feedLink,
		string $link
	): Feed
	{
		$feed = new Feed();

		FeedHelper::setupFeed(
			$feed,
			$title,
			$description,
			$feedLink,
			$link
		);

		return $feed;
	}

	protected function createEntry(Feed $feed, MediaItem $mediaItem): Entry
	{
		$entry = $feed->createEntry();

		$router = \XF::app()->router('public');

		$entry->setId((string) $mediaItem->media_id);

		$entry->setTitle($mediaItem->title ?: \XF::phrase('xfmg_media_item')->render());

		if ($mediaItem->description)
		{
			$entry->setDescription($mediaItem->description);
		}

		$entry->setLink($router->buildLink('canonical:media', $mediaItem))
			->setDateCreated($mediaItem->media_date);

		if ($mediaItem->last_edit_date)
		{
			$entry->setDateModified($mediaItem->last_edit_date);
		}

		if ($mediaItem->category_id && $mediaItem->Category)
		{
			$entry->addCategory([
				'term' => $mediaItem->Category->title,
				'scheme' => $router->buildLink('canonical:media/categories', $mediaItem->Category),
			]);
		}
		if ($mediaItem->album_id && $mediaItem->Album)
		{
			$entry->addCategory([
				'term' => $mediaItem->Album->title,
				'scheme' => $router->buildLink('canonical:media/albums', $mediaItem->Album),
			]);
		}

		$content = $this->renderer->getTemplater()->renderTemplate('public:xfmg_rss_content', [
			'mediaItem' => $mediaItem,
		]);

		$entry->setContent($content);

		$entry->addAuthor([
			'name' => $mediaItem->username ?: strval(\XF::phrase('guest')),
			'email' => 'invalid@example.com',
			'uri' => $router->buildLink('canonical:members', $mediaItem),
		]);
		if ($mediaItem->comment_count)
		{
			$entry->setCommentCount($mediaItem->comment_count);
		}

		return $entry;
	}

	protected function exportFeed(Feed $feed): string
	{
		return $feed->orderByDate()->export('rss', true);
	}
}
