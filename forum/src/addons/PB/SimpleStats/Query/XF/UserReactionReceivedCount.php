<?php


namespace PB\SimpleStats\Query\XF;


use PB\SimpleStats\Query\AbstractHandler;
use XF;

class UserReactionReceivedCount extends AbstractHandler
{
	use LinkUserTrait;
	use OptionUserTrait
	{
		OptionUserTrait::getDefaultOptions as protected getDefaultUserOptions;
		OptionUserTrait::filterOptions as protected filterUserOptions;
	}
	use OptionReactionTrait
	{
		OptionReactionTrait::getDefaultOptions as protected getReactionUserOptions;
		OptionReactionTrait::filterOptions as protected filterReactionOptions;
		OptionReactionTrait::getDefaultTemplateParams as protected getReactionDefaultTemplateParams;
	}

	protected function filterOptions()
	{
		return $this->filterReactionOptions() + $this->filterUserOptions() + ['display_id' => 'bool'];
	}

	protected function getDefaultOptions()
	{
		return $this->getReactionUserOptions() + $this->getDefaultUserOptions() + ['display_id' => false];
	}

	/**
	 * @return string|null
	 */
	public function getOptionsTemplate()
	{
		return 'admin:pb_ss_options_user_reaction';
	}

	public function getDefaultTemplateParams()
	{
		return parent::getDefaultTemplateParams() + self::getReactionDefaultTemplateParams();
	}

	public function getColumns(): array
	{
		return [
			'user_id' => [
				'title' => XF::phrase('user_id'),
				'class' => 'dataList-cell--min',
				'hide' => !$this->getOption('display_id'),
			],
			'username' => ['title' => XF::phrase('user_name')],
			'count' => [
				'title' => XF::phrase('total'),
				'class' => 'dataList-cell--min',
			],
		];
	}

	protected function selectData(): array
	{
		$userOptionsData = $this->getUserWhereQueryAndParams();
		$reactionOptionsData = $this->getReactionWhereQueryAndParams();

		return $this->db->fetchAll("
			SELECT user.user_id,
				user.username,
				COUNT(reaction_content.content_user_id) AS count
			FROM xf_reaction_content AS reaction_content
			INNER JOIN xf_reaction AS reaction ON
				(reaction_content.reaction_id = reaction.reaction_id)
			INNER JOIN xf_user AS user ON
				(reaction_content.content_user_id = user.user_id)
			WHERE
				reaction_content.reaction_date BETWEEN ? AND ?
				AND reaction_content.is_counted = 1
				# AND reaction.reaction_score >= 1 # Only positive reactions
				{$userOptionsData['query']} {$reactionOptionsData['query']}
			GROUP BY reaction_content.content_user_id
			ORDER BY COUNT(reaction_content.content_user_id) {$this->order}
			LIMIT {$this->limit}
		", array_merge([$this->startDate, $this->endDate], $userOptionsData['params'] + $reactionOptionsData['params']));
	}
}
