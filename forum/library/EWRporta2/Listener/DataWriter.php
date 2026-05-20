<?php

class EWRporta2_Listener_DataWriter
{
    public static function datawriter($class, array &$extend)
    {
		switch ($class)
		{
			case 'XenForo_DataWriter_Discussion_Thread':
				$extend[] = 'EWRporta2_DataWriter_Discussion_Thread';
				break;
			case 'XenForo_DataWriter_User':
				$extend[] = 'EWRporta2_DataWriter_User';
				break;
		}
    }
}