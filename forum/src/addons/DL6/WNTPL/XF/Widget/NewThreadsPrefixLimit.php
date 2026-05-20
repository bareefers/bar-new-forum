<?php

namespace DL6\WNTPL\XF\Widget;

use XF\Widget\AbstractWidget;

class NewThreadsPrefixLimit extends AbstractWidget
{
	protected $defaultOptions = [
		'limit' => 5,
		'node_ids' => '',
		'prefix_ids' => '',
		'header_url' => '',
		'style' => 'simple',
		'show_expanded_title' => false,
		'limit_char' => 0
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
		$style = $options['style'];
		$nodeIds = $options['node_ids'];
		$prefixIds = $options['prefix_ids'];
		$headerUrl = $options['header_url'];
		$limitChar = $options['limit_char'];			

		$router = $this->app->router('public');

		/** @var \XF\Repository\Thread $threadRepo */
		$threadRepo = $this->repository('XF:Thread');

		$threadFinder = $threadRepo->findLatestThreads();
		$title = \XF::phrase('latest_threads');
		if ($headerUrl != '')
		{
			$link = $router->buildLink($headerUrl);
		}
		else
		{
			$link = $router->buildLink('whats-new/posts', null, ['skip' => 1]);
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

		if ($style == 'full')
		{
			$threadFinder->with('fullForum');
		}
		if ($style == 'expanded')
		{
			$threadFinder->with('FirstPost');
		}

		/** @var \XF\Entity\Thread $thread */
		foreach ($threads = $threadFinder->fetch() AS $threadId => $thread)
		{
			if (!$thread->canView()
				|| $visitor->isIgnoring($thread->user_id)
			)
			{
				unset($threads[$threadId]);
			}

			if ($options['style'] != 'expanded' && $visitor->isIgnoring($thread->last_post_user_id))
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
			'hasMore' => $total > $threads->count(),
			'showExpandedTitle' => $options['show_expanded_title']
		];
		return $this->renderer('wntpl_widget_new_threads_prefix_limit', $viewParams);
	}

	public function verifyOptions(\XF\Http\Request $request, array &$options, &$error = null)
	{
		$options = $request->filter([
			'limit' => 'uint',
			'style' => 'str',
			'header_url' => 'str',
			'prefix_ids' => 'array-uint',
			'node_ids' => 'array-uint',
			'limit_char' => 'uint',
			'show_expanded_title' => 'bool'
		]);
		if ($options['limit'] < 1)
		{
			$options['limit'] = 1;
		}
		
		if ($options['style'] != 'expanded')
		{
			$options['show_expanded_title'] = false;
		}
		
		if ($options['style'] == 'full' || $options['style'] == 'simple')
		{
		    $options['limit_char'] = 0;
		}
		
		if (!$options['prefix_ids'])
		{
			$error = \XF::phrase('wrtpl_error_message_no_prefix_option');
			return false;
		}
		
		return true;
	}
}