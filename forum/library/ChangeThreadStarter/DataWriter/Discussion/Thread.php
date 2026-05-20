<?php

class ChangeThreadStarter_DataWriter_Discussion_Thread extends XFCP_ChangeThreadStarter_DataWriter_Discussion_Thread
{
	/**
	 * Returns true if the changes made require the search index to be updated.
	 *
	 * @return boolean
	 */
	protected function _needsSearchIndexUpdate()
	{
		return parent::_needsSearchIndexUpdate() || ($this->get('discussion_state') == 'visible' && $this->isChanged('username'));
	}	
}