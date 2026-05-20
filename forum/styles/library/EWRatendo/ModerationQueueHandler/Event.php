<?php

class EWRatendo_ModerationQueueHandler_Event extends XenForo_ModerationQueueHandler_Abstract
{
	public function getVisibleModerationQueueEntriesForUser(array $contentIds, array $viewingUser)
	{
		$eventModel = XenForo_Model::create('EWRatendo_Model_Events');
		$events = $eventModel->getEventsByIDs($contentIds);

		$output = array();
		foreach ($events AS $event)
		{
			$description = $event['formatted_strtime']." ".new XenForo_Phrase('till')." ".$event['formatted_endtime']."\n".
				($event['event_recur_count'] ? new XenForo_Phrase('recur_this_event_every').": ".$event['event_recur_count']." ".$event['event_recur_units']."\n" : '');


			$output[$event['event_id']] = array(
				'message' => $description,
				'user' => array(
					'user_id' => $event['user_id'],
					'username' => $event['username']
				),
				'title' => $event['event_title'],
				'link' => XenForo_Link::buildPublicLink('events/edit', $event),
				'contentTypeTitle' => new XenForo_Phrase('event')
			);
		}

		return $output;
	}

	public function approveModerationQueueEntry($contentId, $message, $title)
	{
		$queueModel = XenForo_Model::create('XenForo_Model_ModerationQueue');
		$eventModel = XenForo_Model::create('EWRatendo_Model_Events');
		$event = $eventModel->getEventById($contentId);

		$dw = XenForo_DataWriter::create('EWRatendo_DataWriter_Events', XenForo_DataWriter::ERROR_SILENT);
		$dw->setExistingData($contentId);
		$dw->set('event_state', 'visible');
		$dw->save();

		return $queueModel->deleteFromModerationQueue('event', $contentId);
	}

	public function deleteModerationQueueEntry($contentId)
	{
		$queueModel = XenForo_Model::create('XenForo_Model_ModerationQueue');
		$eventModel = XenForo_Model::create('EWRatendo_Model_Events');

		$event = $eventModel->getEventById($contentId);
		$eventModel->deleteEvent($event);

		return $queueModel->deleteFromModerationQueue('event', $contentId);
	}
}