<?php

namespace DL6\WNTPL\XF\Widget;

use XF\Widget\AbstractWidget;

class NewPostsPrefixLimit extends AbstractWidget
{
	protected $defaultOptions = [
		'limit' => 5,
		'style' => 'simple',
		'filter' => 'latest',
		'prefix_ids' => '',
		'header_url' => '',
		'node_ids' => []
	];

	protected function getDefaultTemplateParams($context)
	{
		$params = parent::getDefaultTemplateParams($context);
		if ($context == 'options')
		{
			$nodeRepo = $this->app->repository('XF:Node');
			$params['nodeTree'] = $nodeRepo->createNodeTree($nodeRepo->getFullNodeList());
			$prefixRepo = $this->app->repository('XF:ThreadPrefix');
			$params['prefixList'] = $prefixRepo->getPrefixListData();
		}
		return $params;
	}

	public function render()
	{
		$visitor = \XF::visitor();

		$options = $this->options;
		$limit = $options['limit'];
		$filter = $options['filter'];
		$nodeIds = $options['node_ids'];
		$prefixIds = $options['prefix_ids'];
		$headerUrl = $options['header_url'];

		if (!$visitor->user_id)
		{
			if ($filter != 'latest_replies')
			{
			    $filter = 'latest';
			}
			else
			{
			    $filter = 'latest_replies';
			}
		}

		$router = $this->app->router('public');

		/** @var \XF\Repository\Thread $threadRepo */
		$threadRepo = $this->repository('XF:Thread');

		switch ($filter)
		{
			default:
			case 'latest':
				$threadFinder = $threadRepo->findThreadsWithLatestPosts();
				$title = \XF::phrase('widget.latest_posts');
				$link = $router->buildLink('whats-new/posts', null, ['skip' => 1]);
				break;

			case 'latest_replies':
				$threadFinder = $threadRepo->findThreadsWithLatestPosts();
				$threadFinder->where('reply_count', '>', 0);
				$title = \XF::phrase('wntpl_widget.latest_replies');
				$link = $router->buildLink('whats-new/posts', null, ['skip' => 1]);
				break;

			case 'unread':
				$threadFinder = $threadRepo->findThreadsWithUnreadPosts();
				$title = \XF::phrase('widget.unread_posts');
				$link = $router->buildLink('whats-new/posts', null, ['unread' => 1]);
				break;

			case 'watched':
				$threadFinder = $threadRepo->findThreadsForWatchedList();
				$title = \XF::phrase('widget.latest_watched');
				$link = $router->buildLink('whats-new/posts', null, ['watched' => 1]);
				break;
		}

		if ($headerUrl != '')
		{
			$link = $router->buildLink($headerUrl);
		}

		$threadFinder
			->with('Forum.Node.Permissions|' . $visitor->permission_combination_id)
			->limit(max($limit * 2, 10));

		if ($prefixIds)
		{
		$threadFinder->where('prefix_id', $prefixIds);
		}

		if ($nodeIds && !in_array(0, $nodeIds))
		{
			$threadFinder->where('node_id', $nodeIds);
		}

		if ($options['style'] == 'full')
		{
			$threadFinder->forFullView(true);
		}
		else
		{
			$threadFinder
				->with('LastPoster')
				->withReadData();
		}

		/** @var \XF\Entity\Thread $thread */
		foreach ($threads = $threadFinder->fetch() AS $threadId => $thread)
		{
			if (!$thread->canView()
				|| $visitor->isIgnoring($thread->user_id)
				|| $visitor->isIgnoring($thread->last_post_user_id)
			)
			{
				unset($threads[$threadId]);
			}
		}
		$total = $threads->count();
		$threads = $threads->slice(0, $limit, true);

		$viewParams = [
			'title' => $this->getTitle() ?: $title,
			'link' => $link,
			'threads' => $threads,
			'style' => $options['style'],
			'filter' => $filter,
			'hasMore' => $total > $threads->count()
		];
		return $this->renderer('widget_new_posts', $viewParams);
	}

	public function verifyOptions(\XF\Http\Request $request, array &$options, &$error = null)
	{
		$options = $request->filter([
			'limit' => 'uint',
			'style' => 'str',
			'filter' => 'str',
			'header_url' => 'str',
			'prefix_ids' => 'array-uint',
			'node_ids' => 'array-uint'
		]);
		if ($options['limit'] < 1)
		{
			$options['limit'] = 1;
		}

		return true;
	}
}