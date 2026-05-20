<?php

class EWRporta2_Model_Features extends XenForo_Model
{
	public function getFeatureByThreadId($threadID)
	{
		if (!$feature = $this->_getDb()->fetchRow("
			SELECT * FROM EWRporta2_features WHERE thread_id = ?
		", $threadID))
		{
			return false;
		}

		return $feature;
	}
	
	public function getFeatureByThreadPost($thread, $post)
	{
		$options = XenForo_Application::get('options');
		
		if (!$feature = $this->getFeatureByThreadId($thread['thread_id']))
		{
			$feature['feature_date'] = $thread['post_date'];
		}
		
		$feature['feature_title'] = !empty($feature['feature_title']) ? $feature['feature_title'] : $thread['title'];
		$feature['feature_excerpt'] = !empty($feature['feature_excerpt']) ? $feature['feature_excerpt'] : $post['message'];
		
		$datetime = new DateTime(date('r', $feature['feature_date']));
		
		if ($options->EWRporta2_promote_timezone)
		{
			$datetime->setTimezone(new DateTimeZone($options->EWRporta2_promote_timezone));
		}
		else
		{
			$visitor = XenForo_Visitor::getInstance();
			$datetime->setTimezone(new DateTimeZone($visitor['timezone']));
		}
		
		$datetime = explode('.', $datetime->format($options->EWRporta2_promote_24hour ? 'Y-m-d.H.i.A.e' : 'Y-m-d.h.i.A.e'));

		$feature['datetime'] = array(
			'date' => $datetime[0],
			'hour' => $datetime[1],
			'mins' => $datetime[2],
			'meri' => $datetime[3],
			'zone' => $datetime[4]
		);
		
		return $feature;
	}
	
	public function getFeatureParams($params)
	{
		$joins = "";
		$wheres = "";
	
		if (!empty($params['category']))
		{
			$joins .= " INNER JOIN EWRporta2_catlinks ON (EWRporta2_catlinks.thread_id = EWRporta2_features.thread_id)";
			$wheres .= " AND EWRporta2_catlinks.category_id = " . $this->_getDb()->quote($params['category']);
		}
	
		if (!empty($params['author']))
		{
			$wheres .= " AND xf_user.user_id = " . $this->_getDb()->quote($params['author']);
		}
	
		if (!empty($params['forums']))
		{
			$wheres .= " AND xf_thread.node_id IN (" . $this->_getDb()->quote($params['forums'] . ")");
		}
		
		if (!empty($params['filter']))
		{
			$joins .= " LEFT JOIN EWRporta2_catlinks AS filter ON (filter.thread_id = EWRporta2_features.thread_id)";
			$joins .= " INNER JOIN EWRporta2_categories AS cat ON (cat.category_id = filter.category_id AND cat.category_type = 'category')";
			$wheres .= " AND filter.category_id NOT IN (" . $this->_getDb()->quote($params['filter']) . ")";
		}
		
		if (empty($wheres))
		{
			$wheres .= " AND (EWRporta2_features.feature_exclude = '0' OR EWRporta2_features.feature_exclude IS NULL)";
		}
		
		return array($joins, $wheres);
	}
	
	public function getFeatures($stop, $params = array())
	{
		$options = XenForo_Application::get('options');
		
		list($joins, $wheres) = $this->getFeatureParams($params);
		
		$features = $this->fetchAllKeyed("
			SELECT EWRporta2_features.*, xf_thread.*, xf_forum.*, xf_post.message,
				IF(NOT ISNULL(xf_user.user_id), xf_user.username, xf_thread.username) AS username
			FROM EWRporta2_features
				INNER JOIN xf_thread ON (xf_thread.thread_id = EWRporta2_features.thread_id)
				INNER JOIN xf_forum ON (xf_forum.node_id = xf_thread.node_id)
				INNER JOIN xf_post ON (xf_post.post_id = xf_thread.first_post_id)
				LEFT JOIN xf_user ON (xf_user.user_id = xf_thread.user_id)
				$joins
			WHERE EWRporta2_features.feature_date < ?
				$wheres
			GROUP BY xf_thread.thread_id
			ORDER BY EWRporta2_features.feature_date DESC
			LIMIT ?
		", 'thread_id', array(XenForo_Application::$time, $stop));
		
		foreach ($features AS $key => &$feature)
		{
			if (empty($feature['feature_custom']) || empty($feature['feature_excerpt']))
			{
				$feature['feature_excerpt'] = XenForo_Helper_String::wholeWordTrim($feature['message'], $options->EWRporta2_features_excerpt);
				$feature['feature_excerpt'] = XenForo_Helper_String::bbCodeStrip($feature['feature_excerpt'], true);
			}
		
			if (empty($feature['feature_custom']) || empty($feature['feature_title']))
			{
				$feature['feature_title'] = $feature['title'];
			}
		}
		
		return $features;
	}
	
	public function prepareFeatures($features)
	{
		if (!XenForo_Application::get('options')->EWRporta2_articles_permissions)
		{
			foreach($features AS $key => $feature)
			{
				if (!$this->getModelFromCache('XenForo_Model_Thread')->canViewThreadAndContainer($feature, $feature))
				{
					unset($features[$key]);
				}
			}
		}
	
		return $features;
	}
	
	public function updateFeature($input)
	{
		$dw = XenForo_DataWriter::create('EWRporta2_DataWriter_Features');

		if ($existing = $this->getFeatureByThreadId($input['thread_id']))
		{
			$dw->setExistingData($existing);
		}
		
		if (is_array($input['feature_date']))
		{
			$input['feature_date'] = strtotime(implode(' ', array(
				$input['feature_date']['date'],
				($input['feature_date']['meri'] == 'PM' ? $input['feature_date']['hour'] + 12 : $input['feature_date']['hour'])
				. ":" . str_pad($input['feature_date']['mins'], 2, "0", STR_PAD_LEFT),
				$input['feature_date']['zone']
			)));
		}
		
		if (!empty($input['feature_custom']))
		{
			$dw->set('feature_excerpt', $input['feature_excerpt']);
		}
		
		$dw->bulkSet($input);
		$dw->save();
		$feature = $dw->getMergedData();
		
		if (!$existing)
		{
			$visitor = XenForo_Visitor::getInstance();
			$thread = $this->getModelFromCache('XenForo_Model_Thread')->getThreadById($feature['thread_id']);
			
			if ($user = $this->getModelFromCache('XenForo_Model_User')->getUserById($thread['user_id']))
			{
				if ($visitor['user_id'] != $user['user_id'] && XenForo_Model_Alert::userReceivesAlert($user, 'article', 'feature'))
				{
					XenForo_Model_Alert::alert(
						$user['user_id'],
						$visitor['user_id'],
						$visitor['username'],
						'article',
						$feature['thread_id'],
						'feature'
					);
				}
			}
		}
		
		return $feature;
	}
	
	public function updateFeatureImage($thread, $fileURL)
	{
		try
		{
			$target = XenForo_Helper_File::getExternalDataPath().'/features/'.$thread['thread_id'].'.jpg';
			$options = XenForo_Application::get('options');
			$width = $options->EWRporta2_feature_width;
			$height = $options->EWRporta2_feature_height;
			
			$imageInfo = getimagesize($fileURL);
			
			if ($image = XenForo_Image_Abstract::createFromFile($fileURL, $imageInfo[2]))
			{
				$ratio = $width / $height;
				
				$w = $image->getWidth();
				$h = $image->getHeight();
				
				if ($w / $h > $ratio)
				{
					$image->thumbnail($w, $height);
				}
				else
				{
					$image->thumbnail($width, $h);
				}

				$w = $image->getWidth();
				$h = $image->getHeight();
				$offWidth = ($w - $width) / 2;
				$offHeight = ($h - $height) / 2;

				$image->crop($offWidth, $offHeight, $width, $height);
				$image->output(IMAGETYPE_JPEG, $target);
			}
		
			return true;
		}
		catch (Exception $e)
		{
			throw new XenForo_Exception(new XenForo_Phrase('porta2_slider_image_fail'), true);
		}
	}
	
	public function deleteFeature($input)
	{
		$dw = XenForo_DataWriter::create('EWRporta2_DataWriter_Features');
		$dw->setExistingData($input);
		$dw->delete();
		
		$target = XenForo_Helper_File::getExternalDataPath().'/features/'.$input['thread_id'].'.jpg';
		if (file_exists($target)) { unlink($target); }
		
		$this->getModelFromCache('XenForo_Model_Alert')->deleteAlerts('article', $input['thread_id'], null, 'feature');
		
		return true;
	}
}