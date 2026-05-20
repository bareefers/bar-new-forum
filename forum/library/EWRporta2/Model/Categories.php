<?php

class EWRporta2_Model_Categories extends XenForo_Model
{
	public function findTags($query, $limit)
	{
		$where = 'category_name LIKE ' . XenForo_Db::quoteLike($query, 'lr');
	
		$tags = $this->_getDb()->fetchAll("
			SELECT *
				FROM EWRporta2_categories
			WHERE $where
				AND category_type = 'tag'
			ORDER BY category_name ASC
			LIMIT ?
		", $limit);
		
		return $tags;
	}
	
	public function sortCategories($categories)
	{
		$cats = array();
		$tags = array();
		
		foreach ($categories AS $key => $category)
		{
			if ($category['category_type'] == 'category')
			{
				$cats[$key] = $category;
			}
			else
			{
				$tags[$key] = $category;
			}
		}
		
		return array('cats' => $cats, 'tags' => $tags);
	}

	public function getCategoryById($categoryID)
	{
		if (!$category = $this->_getDb()->fetchRow("
			SELECT * FROM EWRporta2_categories WHERE category_id = ?
		", $categoryID))
		{
			return false;
		}

		return $category;
	}
	
	public function getAllCategories($type = false)
	{
		$categories = $this->fetchAllKeyed("
			SELECT *
				FROM EWRporta2_categories
			" . (empty($type) ? "" : "WHERE category_type = '$type'") . "
			ORDER BY category_name
		", 'category_id');
		
		if (!$type)
		{
			$categories = $this->sortCategories($categories);
		}
		
		return $categories;
	}

	public function updateCategory($input)
	{
		$dw = XenForo_DataWriter::create('EWRporta2_DataWriter_Categories');

		if (!empty($input['category_id']) && $category = $this->getCategoryById($input['category_id']))
		{
			$dw->setExistingData($category);
		}
		
		$dw->bulkSet($input);
		$dw->save();
		
		return $dw->getMergedData();
	}

	public function deleteCategory($input)
	{
		$dw = XenForo_DataWriter::create('EWRporta2_DataWriter_Categories');
		$dw->setExistingData($input);
		$dw->delete();
		
		return true;
	}
}