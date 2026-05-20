<?php


namespace PB\SimpleStats\Query\XFMG;


use PB\SimpleStats\Query\AbstractHandler;
use PB\SimpleStats\Query\XF\OptionReactionTrait;
use XF;

class MediaReactionCount extends AbstractHandler
{
	use LinkMediaTrait;
	use OptionReactionTrait
	{
		getDefaultOptions as protected getDefaultReactionOptions;
		getDefaultTemplateParams as protected getDefaultReactionTemplateParams;
	}

	protected function getDefaultOptions()
	{
		return $this->getDefaultReactionOptions() + ['display_id' => false];
	}

	public function getDefaultTemplateParams()
	{
		$params = self::getDefaultReactionTemplateParams();
		$params['contentTypes'] = [];
		return $params;
	}

	public function getColumns(): array
	{
		return [
			'media_id' => [
				'title' => 'ID',
				'class' => 'dataList-cell--min',
				'hide' => !$this->getOption('display_id'),
			],
			'title' => ['title' => XF::phrase('title')],
			'description' => ['title' => XF::phrase('description')],
			'count' => [
				'title' => XF::phrase('reactions'),
				'class' => 'dataList-cell--min',
			],
		];
	}

	public function selectData(): array
	{
		$reactionOptionsData = $this->getReactionWhereQueryAndParams();

		return $this->db->fetchAll("
			SELECT media.media_id,
				media.title,
				media.description,
				COUNT(reaction_content.reaction_content_id) AS count
			FROM xf_reaction_content AS reaction_content
			INNER JOIN xf_reaction AS reaction ON
				(reaction_content.reaction_id = reaction.reaction_id)
			INNER JOIN xf_mg_media_item AS media ON
				(
					reaction_content.content_type = 'xfmg_media'
					AND reaction_content.content_id = media.media_id
				)
			LEFT JOIN xf_mg_album AS album ON
			    (media.album_id = album.album_id)
			WHERE
				media.media_state = 'visible'
                AND (album.album_state = 'visible' OR album.album_hash IS NULL)
				AND media.media_date BETWEEN ? AND ? 
				AND reaction_content.is_counted = 1
				{$reactionOptionsData['query']}
				# AND reaction.reaction_score >= 1 # Only positive reactions
			GROUP BY media.media_id
			ORDER BY COUNT(reaction_content.reaction_content_id) {$this->order}
			LIMIT {$this->limit}
		", array_merge([$this->startDate, $this->endDate], $reactionOptionsData['params']));
	}
}
