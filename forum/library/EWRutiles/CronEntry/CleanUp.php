<?php

class EWRutiles_CronEntry_CleanUp
{
	public static function runDailyCleanUp()
	{
		$options = XenForo_Application::get('options');

		if ($options->EWRutiles_softdelete_cutoff)
		{
			XenForo_Model::create('EWRutiles_Model_SoftDeleted')->deleteSofts();
		}

		if ($options->EWRutiles_unconfirmed_cutoff)
		{
			XenForo_Model::create('EWRutiles_Model_Unconfirmed')->deleteUsers();
		}
		
		if ($options->EWRutiles_downvote_expiration)
		{
			XenForo_Model::create('EWRutiles_Model_DownVotes')->deleteVotes();
		}
	}
}