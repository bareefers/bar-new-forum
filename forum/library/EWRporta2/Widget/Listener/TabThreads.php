<?php

class EWRporta2_Widget_Listener_TabThreads
{
    public static function controller($class, array &$extend)
    {
		switch ($class)
		{
			case 'EWRporta2_ControllerPublic_Widgets':
				$extend[] = 'EWRporta2_Widget_Controller_TabThreads';
				break;
		}
    }
}