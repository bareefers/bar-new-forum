<?php


namespace PB\SimpleStats\Query\XF;


use PB\SimpleStats\Addon;
use PB\SimpleStats\Query\AbstractHandler;
use XF;
use XF\App;

class PostReactionCount extends AbstractHandler
{
	protected function filterOptions()
	{
		return [
			'reaction_ids' => 'array-int',
			'display_message' => 'bool'
		];
	}

	protected function getDefaultOptions()
	{
		return [
			'reaction_ids' => 0,
			'display_id' => false,
			'display_message' => false
		];
	}

	protected function assertUrl($stat, $key, $value, bool $canonical = false)
	{
		switch ($key)
		{
			case 'title':
			case 'post_id':
				return $this->app->router('public')
					->buildLink($canonical ? 'canonical:posts' : 'posts', ['post_id' => $stat['post_id']]);
			case 'thread_id':
				return $this->app->router('public')
					->buildLink($canonical ? 'canonical:threads' : 'threads', ['thread_id' => $value]);
			case 'username':
				return $this->app->router('public')
					->buildLink($canonical ? 'canonical:members' : 'members', [
						'user_id' => $stat['user_id'],
						'username' => $stat['username'],
					]);
		}

		return null;
	}

	public function getDefaultTemplateParams()
	{
		return parent::getDefaultTemplateParams() + [
				'reactionsCache' => $this->app->container('reactions')
			];
	}

	public function getColumns(): array
	{
		$displayIds = $this->getOption('display_id');
		$displayMessage = $this->getOption('display_message');

		return [
			'post_id' => [
				'title' => Addon::phrase('post_id'),
				'class' => 'dataList-cell--min',
				'hide' => !$displayIds,
			],
			'thread_id' => [
				'title' => XF::phrase('thread'),
				'class' => 'dataList-cell--min',
				'hide' => !$displayIds,
			],
			'title' => [
				'title' => XF::phrase('title'),
				'class' => !$displayMessage ? '' : 'dataList-cell--min',
			],
			'message' => [
				'title' => XF::phrase('message'),
				'hide' => !$displayMessage,
			],
			'username' => [
				'title' => XF::phrase('user_name'),
				'class' => 'dataList-cell--min',
			],
			'count' => [
				'title' => XF::phrase('total'),
				'class' => 'dataList-cell--min',
			],
		];
	}

	/**
	 * @return string|null
	 */
	public function getOptionsTemplate()
	{
		return 'admin:pb_ss_options_post_reaction_count';
	}

	protected function getReactionWhereQueryAndParams($reactionTableName = 'reaction', $reactionContentTableName = 'reaction_content')
	{
		$otherWhereConditions = [];
		$whereParams = [];
		$reactionIdConditions = '';

		$reactionIds = $this->getOption('reaction_ids');
		if ($reactionIds)
		{
			$reactionIdConditions .= " $reactionTableName.reaction_id IN(?) ";
			$whereParams[] = implode(', ', $reactionIds);
		}

		if ($reactionIdConditions)
		{
			$otherWhereConditions[] = $reactionIdConditions;
		}

		$otherWhereSql = '';
		foreach ($otherWhereConditions as $otherWhereCondition)
		{
			$otherWhereSql .= ' AND (' . $otherWhereCondition . ') ';
		}

		return ['query' => $otherWhereSql, 'params' => $whereParams];
	}

	public function selectData(): array
	{
		$reactionOptionsData = $this->getReactionWhereQueryAndParams();

		return $this->db->fetchAll("
			SELECT post.post_id,
				thread.thread_id,
				thread.title,
				post.message,
				user.user_id AS user_id,
				user.username AS username,
				COUNT(reaction_content.reaction_content_id) AS count
			FROM xf_reaction_content AS reaction_content
			INNER JOIN xf_reaction AS reaction ON
				(reaction_content.reaction_id = reaction.reaction_id)
			INNER JOIN xf_post AS post ON
				(reaction_content.content_id = post.post_id)
			INNER JOIN xf_thread AS thread ON
				(post.thread_id = thread.thread_id)
			INNER JOIN xf_user AS user ON
				(post.user_id = user.user_id)
			WHERE
				post.post_date BETWEEN ? AND ?
			  	AND reaction_content.content_type = 'post'
				AND post.message_state = 'visible'
				AND thread.discussion_state = 'visible'
				AND reaction_content.is_counted = 1
				{$reactionOptionsData['query']}
				# AND reaction.reaction_score >= 1 # Only positive reactions
			GROUP BY post.post_id
			ORDER BY COUNT(reaction_content.reaction_content_id) {$this->order}
			LIMIT {$this->limit}
		", array_merge([$this->startDate, $this->endDate], $reactionOptionsData['params']));
	}
}
