<?php
  
class Dark_ParseHTML_BbCode_Formatter_Ritsu extends XenForo_BbCode_Formatter_Base
{
	protected $_tags;
	protected $_otherFormatters;
	public $user_id;
	
	private static $permissionCache;
	
	public static function create($class = '', $options = array()){	
		$formatter = new self($options);		
		return $formatter;
	}
	
	protected static function configure(&$instance, $options = array()){
		
		if (is_array($options))
		{
			$baseOptions = array(
				'smilies' => null,
				'bbCode' => null,
				'view' => null
			);
			$options = array_merge($baseOptions, $options);
		}
		else
		{
			$options = array(
				'smilies' => array(),
				'bbCode' => array(),
				'view' => false
			);
		}

		if (!is_array($options['smilies']))
		{
			if (XenForo_Application::isRegistered('smilies'))
			{
				$options['smilies'] = XenForo_Application::get('smilies');
			}
			else
			{
				$options['smilies'] = XenForo_Model::create('XenForo_Model_Smilie')->getAllSmiliesForCache();
				XenForo_Application::set('smilies', $options['smilies']);
			}
		}

		if ($options['smilies'])
		{
			$instance->addSmilies($options['smilies']);
		}

		
		// pre v1.3		
		if(XenForo_Application::get('options')->currentVersionId < 1030000){
			
			if (!is_array($options['bbCode']))
			{
				if (XenForo_Application::isRegistered('bbCode'))
				{
					$options['bbCode'] = XenForo_Application::get('bbCode');
				}
				else
				{
					$options['bbCode'] = XenForo_Model::create('XenForo_Model_BbCode')->getBbCodeCache();
					XenForo_Application::set('bbCode', $options['bbCode']);
				}
			}

			if (!empty($options['bbCode']['mediaSites']))
			{
				$instance->addMediaSites($options['bbCode']['mediaSites']);
			}
			
		} else {		
			
			if (!is_array($options['bbCode']))
			{
				if (XenForo_Application::isRegistered('bbCode'))
				{
					$options['bbCode'] = XenForo_Application::get('bbCode');
				}
				else
				{
					$options['bbCode'] = XenForo_Model::create('XenForo_Model_BbCode')->getBbCodeCache();
					XenForo_Application::set('bbCode', $options['bbCode']);
				}
			}

			if (!empty($options['bbCode']['mediaSites']))
			{
				$instance->addMediaSites($options['bbCode']['mediaSites']);
			}


			if (!empty($options['bbCode']['bbCodes']))
			{
				$instance->addCustomTags($options['bbCode']['bbCodes']);
			}	
		}

		if ($options['view'])
		{
			$instance->setView($options['view']);
		}	
		
	}
	
	public function __construct($options = array())
	{
		self::configure($this, $options);		
		$class = XenForo_Application::resolveDynamicClass('XenForo_BbCode_Formatter_Base', 'bb_code');
		$this->_otherFormatters = new $class();
		self::configure($this->_otherFormatters, $options);
		
		$this->_tags = $this->getTags();
		$this->preLoadData();

		if (XenForo_Visitor::hasInstance())
		{
			$visitor = XenForo_Visitor::getInstance();
			if (!empty($visitor['ignoredUsers']))
			{
				$this->_ignoredUsers = $visitor['ignoredUsers'];
			}

			$language = $visitor->getLanguage();
			$this->_textDirection = $language['text_direction'];
		}
	}
	
	public function getTags()
	{
		if ($this->_tags !== null && !empty($this->_tags['parsehtml']))
		{
			return $this->_tags;
		}
		
		$this->_tags = $this->_otherFormatters->getTags();

		$this->_tags['parsehtml'] = array(
			'hasOption' => false,
			'plainChildren' => true,
			'stopSmilies' => true,
			'stopLineBreakConversion' => true,
			'trimLeadingLinesAfter' => 1,
			'callback' => array($this, 'renderTagParseHtml')
		);
		
		return $this->_tags;
	}
	
	public function filterString($string, array $rendererStates){
		$string = $this->_otherFormatters->filterString($string, $rendererStates);
		return $string;
	}	
	
	public function renderTagParseHtml(array $tag, array $rendererStates)
	{
		if($this->user_id < 1)
			return $this->renderTagUnparsed($tag, $rendererStates);
			
		if(empty(self::$permissionCache[$this->user_id])){	
			$oldUserId = XenForo_Visitor::getUserId();		
			$user = XenForo_Visitor::setup($this->user_id);
			self::$permissionCache[$this->user_id] = $user->hasPermission('dark_parsehtml', 'thread');
			XenForo_Visitor::setup($oldUserId);
		}
			
		if(!self::$permissionCache[$this->user_id])	
			return $this->renderTagUnparsed($tag, $rendererStates);		
			
		
		$content = $this->stringifyTree($tag['children']);
		
		$content = str_ireplace(array("[url]", "[/url]", "[email]", "[/email]", "[media]", "[/media]"), "", $content);
		
		$content = XenForo_Helper_String::censorString($content);

		return '<div class="parseHTML">' . $content . '</div>';
	}
		 
}