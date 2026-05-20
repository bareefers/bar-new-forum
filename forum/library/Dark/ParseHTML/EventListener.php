<?php

class Dark_ParseHTML_EventListener
{	
	public static function LoadClassView($class, array &$extend)
	{
		switch($class){
			case 'XenForo_ViewPublic_Thread_View':
			case 'XenForo_ViewPublic_Thread_ViewNewPosts':
				$extend[] = 'Dark_ParseHTML_ViewPublic_Thread_View';
				break;
				
			case 'XenForo_ViewPublic_Thread_ReplyPreview':
			case 'XenForo_ViewPublic_Thread_CreatePreview':
			case 'XenForo_ViewPublic_Post_EditPreview':
				$extend[] = 'Dark_ParseHTML_ViewPublic_Thread_ReplyPreview';
				break;
			
			case 'XenForo_ViewPublic_Thread_ViewPosts':
				$extend[] = 'Dark_ParseHTML_ViewPublic_Thread_ViewPosts';
				break;
			
			case 'XenForo_ViewPublic_Conversation_EditMessagePreview':
			case 'XenForo_ViewPublic_Conversation_Preview':
				$extend[] = 'Dark_ParseHTML_ViewPublic_Conversation_Preview';
				break;
				
			case 'XenForo_ViewPublic_Conversation_View':
				$extend[] = 'Dark_ParseHTML_ViewPublic_Conversation_View';
				break;
				
			case 'XenForo_ViewPublic_Conversation_ViewMessage':
				$extend[] = 'Dark_ParseHTML_ViewPublic_Conversation_ViewMessage';
				break;
				
			case 'XenForo_ViewPublic_Conversation_ViewNewMessages':
				$extend[] = 'Dark_ParseHTML_ViewPublic_Conversation_ViewNewMessages';
				break;
				
		}
		
	}
	
	public static function LoadClassBbCode($class, array &$extend){
		if($class == 'XenForo_BbCode_Formatter_BbCode_AutoLink'){			
			$extend[]='Dark_ParseHTML_BbCode_Formatter_AutoLink';
		}
	}
}