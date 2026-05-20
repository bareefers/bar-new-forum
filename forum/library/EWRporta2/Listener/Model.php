<?php

class EWRporta2_Listener_Model
{
    public static function model($class, array &$extend)
    {
		switch ($class)
		{
			case 'XenForo_Model_Post':
				$extend[] = 'EWRporta2_Model_Post';
				break;
			case 'XenForo_Model_User':
				$extend[] = 'EWRporta2_Model_User';
				break;
		}
    }
}