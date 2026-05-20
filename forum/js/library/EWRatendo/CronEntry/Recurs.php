<?php

class EWRatendo_CronEntry_Recurs
{
	public static function build()
	{
		XenForo_Model::create('EWRatendo_Model_Recurs')->buildRecurs();
	}
}