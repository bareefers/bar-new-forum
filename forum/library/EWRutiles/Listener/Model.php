<?php

class EWRutiles_Listener_Model
{
    public static function model($class, array &$extend)
    {
		switch ($class)
		{
			case 'XenForo_Model_Post':
				$extend[] = 'EWRutiles_Model_Post';
				break;
		}
    }
}