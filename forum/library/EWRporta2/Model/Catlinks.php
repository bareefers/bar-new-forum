<?php

class EWRporta2_Model_Catlinks extends XenForo_Model
{
	public function getCategoryLinks($keys)
	{
		if (empty($keys)) { return array(); }
		
		return $this->_getDb()->fetchAll("
			SELECT EWRporta2_catlinks.*, EWRporta2_categories.*, xf_user.*
			FROM EWRporta2_catlinks
				INNER JOIN EWRporta2_categories ON (EWRporta2_categories.category_id = EWRporta2_catlinks.category_id)
				LEFT JOIN xf_user ON (xf_user.user_id = EWRporta2_catlinks.user_id)
			WHERE EWRporta2_catlinks.thread_id IN (" . $this->_getDb()->quote($keys) . ")
			ORDER BY EWRporta2_categories.category_name
		");
	}
	
	public function getCatlinkById($catlinkID)
	{
		if (!$catlink = $this->_getDb()->fetchRow("
			SELECT * FROM EWRporta2_catlinks WHERE catlink_id = ?
		", $catlinkID))
		{
			return false;
		}

		return $catlink;
	}
	
	public function getCatlinksByThread($thread, $type = false, $links = true)
	{
		if ($links)
		{
			$catlinks = $this->fetchAllKeyed("
				SELECT EWRporta2_catlinks.*, EWRporta2_categories.*, xf_user.*
				FROM EWRporta2_catlinks
					INNER JOIN EWRporta2_categories ON (EWRporta2_categories.category_id = EWRporta2_catlinks.category_id)
					LEFT JOIN xf_user ON (xf_user.user_id = EWRporta2_catlinks.user_id)
				WHERE EWRporta2_catlinks.thread_id = ?
					" . (empty($type) ? "" : "AND EWRporta2_categories.category_type = '$type'") . "
				ORDER BY EWRporta2_categories.category_name
			", 'category_id', array($thread['thread_id']));
		}
		else
		{
			$catlinks = $this->fetchAllKeyed("
				SELECT EWRporta2_categories.*
				FROM EWRporta2_categories
					LEFT JOIN EWRporta2_catlinks ON (EWRporta2_catlinks.category_id = EWRporta2_categories.category_id AND EWRporta2_catlinks.thread_id = ?)
				WHERE EWRporta2_catlinks.thread_id IS NULL
					" . (empty($type) ? "" : "AND EWRporta2_categories.category_type = '$type'") . "
				ORDER BY EWRporta2_categories.category_name
			", 'category_id', array($thread['thread_id']));
		}
		
		if (!$type)
		{
			return $this->getModelFromCache('EWRporta2_Model_Categories')->sortCategories($catlinks);
		}
		
		return $catlinks;
	}
	
	public function updateCatlinks($input)
	{
		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);
		
		foreach ($input['oldlinks'] AS $key => $link)
		{
			if (in_array($link, $input['catlinks']))
			{
				unset($input['catlinks'][$key]);
			}
			else
			{
				$db->delete('EWRporta2_catlinks', 'catlink_id = ' . $key);
			}
		}
		
		foreach ($input['catlinks'] AS $link)
		{	
			$db->insert('EWRporta2_catlinks', array(
				'thread_id' => $input['thread_id'],
				'category_id' => $link,
				'user_id' => XenForo_Visitor::getUserId()
			));
		}
		
		XenForo_Db::commit($db);
		return $this->getCatlinksByThread($input, 'category');
	}
	
	public function updateTaglinks($input)
	{
		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);
		
		foreach ($input['oldlinks'] AS $key)
		{
			if (in_array($key, $input['taglinks']))
			{
				unset($input['taglinks'][$key]);
			}
			else
			{
				$db->delete('EWRporta2_catlinks', 'catlink_id = ' . $key);
			}
		}
		
		XenForo_Db::commit($db);
		return $this->getCatlinksByThread($input, 'tag');
	}
	
	public function updateTags($input)
	{
		$_perms = $this->getModelFromCache('EWRporta2_Model_Perms')->getPermissions();
		$_tagsall = $this->getModelFromCache('EWRporta2_Model_Categories')->getAllCategories();
		$_existing = $this->getCatlinksByThread($input);
		
		$_tagsall = $_tagsall['cats'] + $_tagsall['tags'];
		$_existing = $_existing['cats'] + $_existing['tags'];
		
		$tagsall = array();
		$existing = array();
		$newlinks = array();
		
		foreach ($_tagsall AS $key => $category)
		{
			$tagsall[$key] = $category['category_name'];
		}
		
		foreach ($_existing AS $key => $category)
		{
			$existing[$key] = $category['category_name'];
		}
		
		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);
		
		foreach ($input['new_tags'] AS $key => $tag)
		{
			$tag = trim($tag);
			if (empty($tag)) { continue; }
		
			$search = array_search($tag, $tagsall);
		
			if ($search === false && $_perms['tags'])
			{
				$dw = XenForo_DataWriter::create('EWRporta2_DataWriter_Categories');
				$dw->set('category_name', $tag);
				$dw->set('category_type', 'tag');
				$dw->save();
				
				$newlinks[$dw->get('category_id')] = $tag;
			}
			elseif (!in_array($tag, $existing))
			{
				$newlinks[$search] = $tag;
			}
		}
		
		foreach ($newlinks AS $key => $link)
		{
			$dw = XenForo_DataWriter::create('EWRporta2_DataWriter_Catlinks');
			$dw->set('thread_id', $input['thread_id']);
			$dw->set('category_id', $key);
			$dw->save();
		}
		
		XenForo_Db::commit($db);
		return $this->getCatlinksByThread($input, 'tag');
	}
}