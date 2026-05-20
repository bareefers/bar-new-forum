<?php

class EWRporta2_Widget_Option_RioLive
{
	public static function renderCategorySelect(XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
	{
		$preparedOption['formatParams'] = self::getCategoryOptions(
			$preparedOption['option_value'],
			sprintf('(%s)', new XenForo_Phrase('unspecified'))
		);

		return XenForo_ViewAdmin_Helper_Option::renderOptionTemplateInternal(
			'option_list_option_select', $view, $fieldPrefix, $preparedOption, $canEdit
		);
	}
	
	public static function renderGameSelect(XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
	{
		$preparedOption['formatParams'] = self::getGameOptions(
			$preparedOption['option_value'],
			sprintf('(%s)', new XenForo_Phrase('unspecified'))
		);

		return XenForo_ViewAdmin_Helper_Option::renderOptionTemplateInternal(
			'option_list_option_select', $view, $fieldPrefix, $preparedOption, $canEdit
		);
	}
	
	public static function getCategoryOptions($selectedCategory, $unspecifiedPhrase = false)
	{
		$categoriesModel = XenForo_Model::create('EWRrio_Model_Categories');

		$categories = $categoriesModel->getCategories();
		$options = array();
		
		foreach ($categories AS $category)
		{
			$options[] = array(
				'label' => $category['category_name'],
				'value' => $category['category_id'],
				'selected' => ($selectedCategory == $category['category_id'])
			);
		}

		if ($unspecifiedPhrase)
		{
			$options = array_merge(array(array
			(
				'label' => $unspecifiedPhrase,
				'value' => 0,
				'selected' => ($selectedCategory == 0)
			)), $options);
		}

		return $options;
	}
	
	public static function getGameOptions($selectedGame, $unspecifiedPhrase = false)
	{
		$gamesModel = XenForo_Model::create('EWRrio_Model_Games');

		$games = $gamesModel->getGames();
		$options = array();
		
		foreach ($games AS $game)
		{
			$options[] = array(
				'label' => $game['game_name'],
				'value' => $game['game_id'],
				'selected' => ($selectedGame == $game['game_id'])
			);
		}

		if ($unspecifiedPhrase)
		{
			$options = array_merge(array(array
			(
				'label' => $unspecifiedPhrase,
				'value' => 0,
				'selected' => ($selectedGame == 0)
			)), $options);
		}

		return $options;
	}
}