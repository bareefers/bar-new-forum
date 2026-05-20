<?php

class EWRatendo_Listener_DataWriter
{
    public static function datawriter($class, array &$extend)
    {
		switch ($class)
		{
			case 'XenForo_DataWriter_Discussion_Thread':
				$extend[] = 'EWRatendo_DataWriter_Discussion_Thread';
				break;
		}
    }
}