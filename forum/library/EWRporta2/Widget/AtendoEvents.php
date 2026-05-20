<?php

class EWRporta2_Widget_AtendoEvents extends XenForo_Model
{
	public function getCachedData(&$widget, $options)
	{
		if ((!$addon = $this->getModelFromCache('XenForo_Model_AddOn')->getAddOnById('EWRatendo')) || empty($addon['active']))
		{
			return 'killWidget';
		}
		
		$events = $this->_getDb()->fetchAll("
			SELECT EWRatendo_events.*
			FROM EWRatendo_events
			WHERE EWRatendo_events.event_endtime >= ?
				AND EWRatendo_events.event_strtime <= ?
				AND EWRatendo_events.event_state = 'visible'
			ORDER BY EWRatendo_events.event_strtime ASC
			LIMIT ?
		", array(XenForo_Application::$time, XenForo_Application::$time + (86400 * $options['atendoevents_range']), $options['atendoevents_limit']));
		
		foreach ($events AS &$event)
		{
			$event = $this->getModelFromCache('EWRatendo_Model_Events')->formatDates($event);
		}
		
		return $events;
	}
}