<?php

class EWRporta2_Model_Widlinks extends XenForo_Model
{
	public function getWidlinkById($widlinkID)
	{
		if (!$widlink = $this->_getDb()->fetchRow("
			SELECT * FROM EWRporta2_widlinks WHERE widlink_id = ?
		", $widlinkID))
		{
			return false;
		}
		
		return $widlink;
	}
	
	public function getWidlinkDetailsById($widlinkID)
	{
		if (!$widlink = $this->_getDb()->fetchRow('
			SELECT EWRporta2_widlinks.*, EWRporta2_widgets.widget_values, EWRporta2_widopts.widopt_values,
				IF(EWRporta2_widopts.widopt_id, EWRporta2_widopts.widopt_id, 0) AS widopt_id,
				IF(EWRporta2_widopts.widopt_id, EWRporta2_widopts.locked, EWRporta2_widgets.locked) AS locked,
				IF(EWRporta2_widopts.widopt_id, EWRporta2_widopts.display, EWRporta2_widgets.display) AS display,
				IF(EWRporta2_widopts.widopt_id, EWRporta2_widopts.groups, EWRporta2_widgets.groups) AS groups,
				IF(EWRporta2_widopts.widopt_id, EWRporta2_widopts.ctime, EWRporta2_widgets.ctime) AS ctime,
				IF(EWRporta2_widopts.widopt_id, EWRporta2_widopts.cdate, EWRporta2_widgets.cdate) AS cdate,
				IF(EWRporta2_widopts.widopt_id, EWRporta2_widopts.cache, EWRporta2_widgets.cache) AS cache
			FROM EWRporta2_widlinks
				INNER JOIN EWRporta2_widgets ON (EWRporta2_widgets.widget_id = EWRporta2_widlinks.widget_id)
				LEFT JOIN EWRporta2_widopts ON (EWRporta2_widopts.widopt_id = EWRporta2_widlinks.widopt_id)
			WHERE EWRporta2_widlinks.widlink_id = ?
		', $widlinkID))
		{
			return false;
		}
		
		if ($widlink['widopt_id'])
		{
			$widlink['options'] = @unserialize($widlink['widopt_values']) + @unserialize($widlink['widget_values']);
		}
		else
		{
			$widlink['options'] = @unserialize($widlink['widget_values']);
		}
		
		return $widlink;
	}
	
	public function getWidlinksByLayoutId($layoutID)
	{
		$widlinks = $this->fetchAllKeyed('
			SELECT EWRporta2_widlinks.*, EWRporta2_widgets.widget_values, EWRporta2_widopts.widopt_values,
				IF(EWRporta2_widopts.widopt_id, EWRporta2_widopts.widopt_id, 0) AS widopt_id,
				IF(EWRporta2_widopts.widopt_id, EWRporta2_widopts.locked, EWRporta2_widgets.locked) AS locked,
				IF(EWRporta2_widopts.widopt_id, EWRporta2_widopts.display, EWRporta2_widgets.display) AS display,
				IF(EWRporta2_widopts.widopt_id, EWRporta2_widopts.groups, EWRporta2_widgets.groups) AS groups,
				IF(EWRporta2_widopts.widopt_id, EWRporta2_widopts.ctime, EWRporta2_widgets.ctime) AS ctime,
				IF(EWRporta2_widopts.widopt_id, EWRporta2_widopts.cdate, EWRporta2_widgets.cdate) AS cdate,
				IF(EWRporta2_widopts.widopt_id, EWRporta2_widopts.cache, EWRporta2_widgets.cache) AS cache
			FROM EWRporta2_widlinks
				INNER JOIN EWRporta2_widgets ON (EWRporta2_widgets.widget_id = EWRporta2_widlinks.widget_id)
				LEFT JOIN EWRporta2_widopts ON (EWRporta2_widopts.widopt_id = EWRporta2_widlinks.widopt_id)
			WHERE layout_id = ?
				AND EWRporta2_widgets.active = 1
			ORDER by widlink_order
		', 'widlink_id', $layoutID);
		
		foreach ($widlinks AS &$link)
		{
			if ($link['widopt_id'])
			{
				$link['options'] = @unserialize($link['widopt_values']) + @unserialize($link['widget_values']);
			}
			else
			{
				$link['options'] = @unserialize($link['widget_values']);
			}
		}
		
		return $widlinks;
	}
	
	public function arrangeWidlinks($widlinks, $arrange)
	{
		if (is_array($arrange))
		{
			foreach ($arrange AS $key => $pos)
			{
				if (isset($widlinks[$key]) && !$widlinks[$key]['locked'])
				{
					$widlinks[$key]['widlink_position'] = $pos['widlink_position'];
					$widlinks[$key]['widlink_order'] = $pos['widlink_order'];
				}
			}
			
			usort($widlinks, array($this, 'sortArrangement'));
		}
		
		return $widlinks;
	}
	
	public function sortArrangement($a, $b)
	{
		return $a['widlink_order'] - $b['widlink_order'];
	}
	
	public function sortWidlinksToLayout($links)
	{
		$widlinks = array(
			'sidebar' => array(),
			'header' => array(),
			'footer' => array(),
			'left' => array(),
			'above' => array(),
			'below' => array(),
			'b-left' => array(),
			'b-right' => array(),
			'disabled' => array(),
		);
		
		foreach ($links AS $link)
		{
			$widlinks[$link['widlink_position']][] = $link;
		}
		
		return $widlinks;
	}

	public function updateWidlink($input)
	{
		$dw = XenForo_DataWriter::create('EWRporta2_DataWriter_Widlinks');

		if (!empty($input['widlink_id']) && $widlink = $this->getWidlinkById($input['widlink_id']))
		{
			$dw->setExistingData($widlink);
		}
		
		$dw->bulkSet($input);
		$dw->save();
		
		return $dw->getMergedData();
	}
	
	public function updateWidlinkPositions($positions)
	{
		$order = 1;
	
		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);
		
		foreach($positions AS $widlink => $position)
		{
			$dw = XenForo_DataWriter::create('EWRporta2_DataWriter_Widlinks');
			$dw->setExistingData($widlink);
			$dw->set('widlink_position', $position);
			$dw->set('widlink_order', $order++);
			$dw->save();
		}
		
		XenForo_Db::commit($db);
		
		return true;
	}

	public function deleteWidlink($input)
	{
		$dw = XenForo_DataWriter::create('EWRporta2_DataWriter_Widlinks');
		$dw->setExistingData($input);
		$dw->delete();
		
		return true;
	}
	
	public function getWidlinkParams($widlink)
	{
		$class = 'EWRporta2_Widget_'.$widlink['widget_id'];
		$params = array(
			'wWidget' => $widlink['widget_id'],
			'wLayout' => $widlink['layout_id'],
			'wWidlink' => $widlink['widlink_id'],
			'wTitle' => $widlink['widlink_title'],
			'wPosition' => $widlink['widlink_position'],
			'wDateTime' => $widlink['cdate'],
			'wOptions' => $widlink['options'],
			'tParams' => $widlink['template_params'],
		);
		
		switch ($widlink['widlink_position'])
		{
			case 'header':
			case 'footer':
			case 'above':
			case 'below':
				$params['wScale'] = 'full';		break;
			default:
				$params['wScale'] = 'tiny';		break;
		}
		
		if (XenForo_Application::autoload($class))
		{
			$model = new $class;
			
			if (empty($widlink['ctime']) || strtotime($widlink['ctime'], $widlink['cdate']) < XenForo_Application::$time)
			{
				if (method_exists($model, 'getCachedData'))
				{
					$params['wCached'] = $model->getCachedData($params, $params['wOptions']);
					if ($params['wCached'] == 'killWidget') { return false; }
					
					if (!empty($widlink['ctime']))
					{
						if ($widlink['widopt_id'])
						{
							$dw = XenForo_DataWriter::create('EWRporta2_DataWriter_Widopts');
							$dw->setExistingData($widlink);
							$dw->set('cache', $params['wCached']);
							$dw->set('cdate', XenForo_Application::$time);
							$dw->save();
						}
						else
						{
							$dw = XenForo_DataWriter::create('EWRporta2_DataWriter_Widgets');
							$dw->setExistingData($widlink);
							$dw->set('cache', $params['wCached']);
							$dw->set('cdate', XenForo_Application::$time);
							$dw->save();
						}
						
						$params['wDateTime'] = XenForo_Application::$time;
					}
				}
			}
			else
			{
				$params['wCached'] = @unserialize($widlink['cache']);
			}

			if (method_exists($model, 'getUncachedData'))
			{
				$params['wUncached'] = $model->getUncachedData($params, $params['wOptions']);
				if ($params['wUncached'] == 'killWidget') { return false; }
			}
		}
		
		return $params;
	}
}