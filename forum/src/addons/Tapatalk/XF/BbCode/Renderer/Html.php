<?php
namespace Tapatalk\XF\BbCode\Renderer;

use XF\App;

class Html extends XFCP_Html
{

	protected $returnHtml = true;

	protected $_simpleReplacements = array(
		'left' => "%s\n",
		'center' => "%s\n",
		'indent' => "    %s\n",
		'right' => "%s\n"
	);

	protected $_advancedReplacements = array(
		'code' => array('$this', 'handleTagCode'),
		'php' => array('$this', 'handleTagPHP'),
		'html' => array('$this', 'handleTagHtml'),
		'quote' => array('$this', 'handleTagQuote'),
		'img' => array('$this', 'renderTagUnparsed'),
		'url' => array('$this', 'handleTagUrl'),
		'tex' => array('$this', 'handleTagTex'),
		'attach' => array('$this', 'handleTagAttach'),
		'media' => array('$this', 'handleTagMedia'),
		'list' => array('$this', 'handleTagList'),
		'spoiler' => array('$this', 'handleTagSpoiler'),
                'color' => array('$this', 'handleTagColor'),
	);

	protected $_ttMediaSites = array(
            'youtube' => 'http://www.youtube.com/watch?v={$id}',
            'vimeo' => 'http://www.vimeo.com/{$id}',
            'facebook' => 'http://www.facebook.com/video/video.php?v={$id}',
            'twitter' => 'https://twitter.com/m/status/{$id}',
            'tweet' => 'https://twitter.com/m/status/{$id}',
            'reddit' => 'https://www.reddit.com/r/{$id}',
            'instagram' => 'https://www.instagram.com/p/{$id}',
            'dailymotion' => 'https://www.dailymotion.com/embed/video/{$id}',
            'flickr'=> 'https://flic.kr/p/{$id}',
            'giphy' => 'https://giphy.com/embed/{$id}',
            'imgur' => 'https://imgur.com/{$id}',
            'liveleak' => 'http://www.liveleak.com/ll_embed?i={$id}',
            'metacafe' => 'http://www.metacafe.com/embed/{$id}',
            'soundcloud' => 'https://soundcloud.com/{$id}',
            'tumblr' => '{$id}',
            'twitch' => 'https://www.twitch.tv/{$id}',
            'pinterest' => 'https://www.pinterest.com/pin/{$id}',
            'spotify' => 'https://open.spotify.com/embed?uri=spotify:{$id}',
	);

	public function __construct($formatter, $templater)
    {
        $smilies = \XF::app()->container('smilies');
        if ($smilies && is_array($smilies)) {
            // $this->addSmilies($smilies);
        }
        parent::__construct($formatter, $templater);

        if (self::inMbq()) {
            unset($this->tags['b']);
            unset($this->tags['i']);
            unset($this->tags['u']);
            unset($this->tags['s']);
            unset($this->tags['color']);
            unset($this->tags['font']);

            $this->addTag('b', ['replace' => ['<b>', '</b>']]);
            $this->addTag('i', ['replace' => ['<i>', '</i>']]);
            $this->addTag('u', ['replace' => ['<u>', '</u>']]);
            $this->addTag('s', ['replace' => ['<s>', '</s>']]);
            $this->addTag('color', ['replace' => ['<span style="color: %s">', '</span>']]);
            $this->addTag('font', ['replace' => ['<span style="font-family: \'%s\'">', '</span>']]);
        }
    }

    /**
     * @return bool
     */
    protected static function inMbq()
    {
        return defined('MBQ_IN_IT') ? true : false;
    }

	public function filterString($string, array $options)
    {
        if (!self::inMbq()) {
            return parent::filterString($string, $options);
        }
        $string = $this->formatter->censorText($string);

        if (empty($options['stopSmilies']))
        {
            $string = $this->formatter->replaceSmiliesHtml($string);
        }

        if (empty($options['stopBreakConversion']))
        {
            $string = nl2br($string);
        }

        return $string;
        // $string = \XF::app()->stringFormatter()->censorText($string);
    }

    public function filterFinalOutput($output)
    {
        if (!self::inMbq()) {
            return parent::filterFinalOutput($output);
        }
        return trim($output);
    }

    public function renderTagAttach(array $children, $option, array $tag, array $options)
    {
        if (!self::inMbq()) {
            return parent::renderTagAttach($children, $option, $tag, $options);
        }
        if (isset($options['states']['viewAttachments'])) {
            $options['viewAttachments'] = $options['states']['viewAttachments'];
        }
        if (isset($options['states']['attachments'])) {
            $options['attachments'] = $options['states']['attachments'];
        }
        $id = intval($this->renderSubTreePlain($children));
        if (!$id)
        {
            return '';
        }

        if (!empty($options['attachments'][$id]))
        {
            $attachment = $options['attachments'][$id];
        }
        else
        {
            $attachment = null;
        }

        $viewParams = [
            'id' => $id,
            'attachment' => $attachment,
            'canView' => !empty($options['viewAttachments']),
            'full' => ($option && strtolower($option) == 'full')
        ];

        $canView = $viewParams['canView'];
        $buildLink = \XF::app()->router('public');
        if ($attachment) {
            $extension = strtolower($attachment->getExtension());
            if ($extension == 'png' || $extension == 'jpg' || $extension == 'bmp' || $extension == 'gif' || $extension == 'jpeg' || $extension == 'webp') {
                $isNotImage = false;
            }else{
                $isNotImage = true;
            }
        }else{
            $isNotImage = true;
        }
        if (!$attachment || $isNotImage) {
            $link = $buildLink->buildLink('full:attachments', ['attachment_id' => $id]);
            $phrase = \XF::phrase('view_attachment_x', ['name' => $id]);
            $output = '[url='. htmlspecialchars($link) . ']' . $phrase . '[/url]';

//        }elseif(empty($attachment['has_thumbnail'])){
//
//            $link = $buildLink->buildLink('full:attachments', $attachment, ['hash' => $attachment['temp_hash']]);
//            $phrase = \XF::phrase('view_attachment_x', ['name' => $attachment['filename']]);
//            $output = '[url='. htmlspecialchars($link) . ']' . $phrase . '[/url]';

        } elseif($canView && strtolower($viewParams['full'])){

            $imgUrl = $buildLink->buildLink('full:attachments', $attachment, ['hash' => $attachment['temp_hash']]);
            $output = '[img]'. $imgUrl . '[/img]';

        } elseif($canView){

            $imgUrl = $buildLink->buildLink('full:attachments', $attachment, ['hash' => $attachment['temp_hash']]);
            $output = "[img]". $imgUrl ."[/img]";

        } else {
            if ($attachment && $attachment instanceof \XF\Entity\Attachment) {
                if($attachment->getThumbnailUrl())
                {
                    $imgUrl = \XF::app()->request()->convertToAbsoluteUri($attachment->getThumbnailUrl());
                }
            }
            if (!isset($imgUrl) || !$imgUrl) {
                $imgUrl = \XF::app()->request()->convertToAbsoluteUri($buildLink->buildLink('attachments', $attachment));
            }
            $output = "[img]". $imgUrl ."[/img]";;
            //return $this->templater->renderTemplate('public:bb_code_tag_attach', $viewParams);
        }

        return $output;
    }

    public function renderTagImage(array $children, $option, array $tag, array $options)
    {
        if (!self::inMbq()) {
            return parent::renderTagImage($children, $option, $tag, $options);
        }

        $url = $this->renderSubTreePlain($children);

        $validUrl = $this->getValidUrl($url);
        if (!$validUrl)
        {
            return $this->filterString($url, $options);
        }

        $censored = $this->formatter->censorText($validUrl);
        if ($censored != $validUrl)
        {
            return $this->filterString($url, $options);
        }

        return '[IMG]' . $validUrl . $tag['original'][1];//$tag['original'][0] fix with [IMG width="100px"]
    }

    public function renderTagCode(array $children, $option, array $tag, array $options)
    {
        if (!self::inMbq()) {
            return parent::renderTagCode($children, $option, $tag, $options);
        }

        $content = $this->renderSubTree($children, $options);
        // a bit like ltrim, but only remove blank lines, not leading tabs on the first line
        $content = preg_replace('#^([ \t]*\r?\n)+#', '', $content);
        $content = rtrim($content);

        return $tag['original'][0] . $content . $tag['original'][1];
    }

    public function renderTagUrl(array $children, $option, array $tag, array $options)
    {
        if (!self::inMbq()) {
            return parent::renderTagUrl($children, $option, $tag, $options);
        }

        if ($option !== null) {
            $url = $option;
            $text = $this->renderSubTree($children, $options);
        } else {
            $url = $this->renderSubTreePlain($children);
            $text = rawurldecode($url);
            if (!preg_match('/./u', $text)) {
                $text = $url;
            }
            $text = $this->formatter->censorText($text);

            if (!empty($options['shortenUrl'])) {
                $length = utf8_strlen($text);
                if ($length > 100) {
                    $text = utf8_substr_replace($text, '...', 35, $length - 35 - 45);
                }
            }

            $text = htmlspecialchars($text);
        }
        $url = $this->getValidUrl($url);
        if (!$url) {
            return $text;
        }
        $url = $this->formatter->censorText($url);

        return '<a href="' . htmlspecialchars($url) . '">' . $text . '</a>';
    }

    public function renderTagSpoiler(array $children, $option, array $tag, array $options)
    {
        if (!self::inMbq()) {
            return parent::renderTagSpoiler($children, $option, $tag, $options);
        }

        if (!$children) {
            return '';
        }

        $this->trimChildrenList($children);

        $content = $this->renderSubTree($children, $options);
        if ($content === '') {
            return '';
        }

        if ($option) {
            $title = $this->filterString($option, array_merge($options, [
                'stopSmilies' => 1,
                'stopBreakConversion' => 1
            ]));
        } else {
            $title = null;
        }

        return $tag['original'][0] . $content . $tag['original'][1];
    }

    public function renderTagQuote(array $children, $option, array $tag, array $options)
    {
        if (!self::inMbq()) {
            return parent::renderTagQuote($children, $option, $tag, $options);
        }

        if (!$children) {
            return '';
        }

        $this->trimChildrenList($children);

        $content = $this->renderSubTree($children, $options);
        if ($content === '') {
            return '';
        }

        $name = null;
        $attributes = [];
        $source = [];

        if ($option !== null && strlen($option)) {
            $parts = explode(',', $option);
            $name = $this->filterString(array_shift($parts), array_merge($options, [
                'stopSmilies' => 1,
                'stopBreakConversion' => 1
            ]));

            foreach ($parts AS $part) {
                $attributeParts = explode(':', $part, 2);
                if (isset($attributeParts[1])) {
                    $attrName = trim($attributeParts[0]);
                    $attrValue = trim($attributeParts[1]);
                    if ($attrName !== '' && $attrValue !== '') {
                        $attributes[$attrName] = $attrValue;
                    }
                }
            }

            if ($attributes) {
                $firstValue = reset($attributes);
                $firstName = key($attributes);
                if ($firstName != 'member') {
                    $source = ['type' => $firstName, 'id' => intval($firstValue)];
                }
            }
        }

        return $tag['original'][0] . $content . $tag['original'][1];
    }

    public function renderTagIndent(array $children, $option, array $tag, array $options)
    {
        if (!self::inMbq()) {
            return parent::renderTagIndent($children, $option, $tag, $options);
        }

        $output = trim($this->renderSubTree($children, $options));

        $amount = $option ? intval($option) : 1;
        $amount = max(1, min($amount, 5));

        $invisibleSpace = $this->endsInBlockTag($output) ? '' : '&#8203;';

        $side = \XF::language()->isRtl() ? 'right' : 'left';
        return $this->wrapHtml(
            '<div style="margin-' . $side . ': ' . ($amount * 20) . 'px">',
            $output . $invisibleSpace,
            '</div>'
        );
    }

    public function renderTagMedia(array $children, $option, array $tag, array $options)
    {
        if (!self::inMbq()) {
            return parent::renderTagMedia($children, $option, $tag, $options);
        }

        $mediaKey = trim($this->renderSubTreePlain($children));
        if (preg_match('#[&?"\'<>\r\n]#', $mediaKey) || strpos($mediaKey, '..') !== false) {
            return '';
        }

        $censored = $this->formatter->censorText($mediaKey);
        if ($censored != $mediaKey) {
            return '';
        }

        $mediaSiteId = strtolower($option);
        if (!isset($this->mediaSites[$mediaSiteId])) {
            return '';
        }
        $site = $this->mediaSites[$mediaSiteId];

        $mediaTypeName = strtolower($option);
        if (isset($this->_ttMediaSites[$mediaTypeName])) {
            $embedHtml = $this->_ttMediaSites[$mediaTypeName];
            return "[url]" . str_replace('{$id}', $mediaKey, $embedHtml) . "[/url]<br />";
        } else {
            return "Unsupported video ([media={$mediaSiteId}]{$mediaKey}[/media])";
        }
    }

    public function renderTagList(array $children, $option, array $tag, array $options)
    {
        if (!self::inMbq()) {
            return parent::renderTagList($children, $option, $tag, $options);
        }

        $listType = ($option && $option === '1' ? 'ol' : 'ul');
        $elements = [];
        $lastElement = '';

        foreach ($children AS $child) {
            if (is_array($child)) {
                $childText = $this->renderTag($child, $options);
                if (preg_match('#^<(ul|ol)#', $childText)) {
                    $lastElement = rtrim($lastElement);
                    if (substr($lastElement, -6) == '<br />') {
                        $lastElement = substr($lastElement, 0, -6);
                    }
                }
                $lastElement .= $childText;
            } else {
                if (strpos($child, '[*]') !== false) {
                    $parts = explode('[*]', $child);

                    $beforeFirst = array_shift($parts);
                    if ($lastElement !== '' || trim($beforeFirst) !== '') {
                        $lastElement .= $this->renderString($beforeFirst, $options);
                    }

                    foreach ($parts AS $part) {
                        $this->appendListElement($elements, $lastElement);
                        $lastElement = $this->renderString($part, $options);
                    }
                } else {
                    $lastElement .= $this->renderString($child, $options);
                }
            }
        }

        $this->appendListElement($elements, $lastElement);

        if (!$elements) {
            return '';
        }
        $output = '';
        $preString = '*';
        if ($listType == 'ol') {
            $preString = null;
        }
        $i = 0;
        foreach ($elements AS $element) {
            $i++;
            if (!$preString) {
                $output .= "{$i}. $element <br/>";
            }else{
                $output .= "$preString $element <br/>";
            }
        }

        return $output;
    }

    public function renderTagSize(array $children, $option, array $tag, array $options)
    {
        if (!self::inMbq()) {
            return parent::renderTagSize($children, $option, $tag, $options);
        }

        $text = $this->renderSubTree($children, $options);
        if (trim($text) === '')
        {
            return $text;
        }

        $size = $this->getTextSize($option);
        if ($size)
        {
            return $this->wrapHtml(
                '<span style="font-size: ' . htmlspecialchars($size) . '">',
                $text,
                '</span>'
            );
        }
        else
        {
            return $text;
        }
    }


    public function getTags()
    {
        if ($this->_tags !== null)
        {
            return $this->_tags;
        }

        $callback = array($this, 'handleTag');

        $tags = parent::getTags();
        $tags['tex'] = array(
            'hasOption' => false,
            'plainChildren' => false
        );

        foreach ($tags AS $tagName => &$tag)
        {
            if($this->_returnHtml){
                switch($tagName){
                    case 'b':
                    case 'i':
                        break;
                    case 'u':
                        $tag['replace'] = array('<u>', '</u>');
                        break;
                    case 'color':
                        $tag['replace'] = array('<font color="%s">', '</font>');
                    case 'img':
                        break;
                    default:
                        unset($tag['replace'], $tag['callback']);
                        $tag['callback'] = $callback;
                        break;
                }
            } else {
                switch($tagName){
                    case 'img':
                        break;
                    default:
                        unset($tag['replace'], $tag['callback']);
                        $tag['callback'] = $callback;
                        break;
                }
            }
        }
        return $tags;
    }

    public function addSmilies(array $smilies)
    {
        $this->_smilieTemplate = '<img src="%1$s?ttinline=true" class="mceSmilie" alt="%2$s" title="%3$s    %2$s" />';
        foreach ($smilies AS $smilie)
        {

            foreach ($smilie['smilieText'] AS $text)
            {
                $this->_smilieTranslate[$text] = "\0" . $smilie['smilie_id'] . "\0";
            }

            if (empty($smilie['sprite_params']))
            {
                if(strpos($smilie['image_url'],'http') !== 0)
                {
                    $smilie['image_url'] = XenForo_Link::convertUriToAbsoluteUri($smilie['image_url'], true);
                }
                $this->_smilieReverse[$smilie['smilie_id']] = $this->_processSmilieTemplate($smilie);
            }
            else
            {
                $pathInfo = pathinfo($smilie['image_url']);
                $fileName =  $pathInfo['filename'] . '_' . ($smilie['sprite_params']['x'] * -1) . '_' . $smilie['sprite_params']['y'] . '_' . ($smilie['sprite_params']['w'] * -1). '_' . $smilie['sprite_params']['h'] . '.png';
                $options = XenForo_Application::get('options');
                $mobiquoSmiliePath = SCRIPT_ROOT . $options->tp_directory . '/smilies/';
                try
                {
                    if(!file_exists($mobiquoSmiliePath . $fileName))
                    {
                        if(is_writeable($mobiquoSmiliePath))
                        {
                            // Create image instances
                            if($pathInfo['extension'] == 'png')
                            {
                                $src = imagecreatefrompng($smilie['image_url']);
                            }
                            else
                            {
                                $src = imagecreatefromgif($smilie['image_url']);
                            }
                            $dest = imagecreate($smilie['sprite_params']['w'], $smilie['sprite_params']['h']);

                            // Copy
                            imagecopy($dest, $src, 0, 0, $smilie['sprite_params']['x'] * -1, $smilie['sprite_params']['y']*-1, $smilie['sprite_params']['w'], $smilie['sprite_params']['h']);

                            imagepng($dest, $mobiquoSmiliePath . $fileName);
                        }
                    }
                }
                catch(Exception $ex){}
                $smilie['image_url'] = $options->tp_directory . '/smilies/' . $fileName;
                if(strpos($smilie['image_url'],'http') !== 0)
                {
                    $smilie['image_url'] = XenForo_Link::convertUriToAbsoluteUri($smilie['image_url'], true);
                }
                $this->_smilieReverse[$smilie['smilie_id']] = $this->_processSmilieTemplate($smilie);

                //$this->_smilieReverse[$smilie['smilie_id']] = $this->_processSmilieSpriteTemplate($smilie);
            }

            $this->_smiliePaths[$smilie['image_url']] = $smilie['smilie_id'];
        }
    }

	public function handleTagUrl(array $tag, array $rendererStates)
	{
		if (!empty($tag['option']))
		{
			$url = $tag['option'];
			$text = $this->renderSubTree($tag['children'], $rendererStates);
		}
		else
		{
			$url = $this->stringifyTree($tag['children']);
			$text = urldecode($url);
			if (!utf8_check($text))
			{
				$text = $url;
			}
			$text = \XF::app()->stringFormatter()->censorText($text);

			if (!empty($rendererStates['shortenUrl']))
			{
				$length = utf8_strlen($text);
				if ($length > 100)
				{
					$text = utf8_substr_replace($text, '...', 35, $length - 35 - 45);
				}
			}

			$text = htmlspecialchars($text);
		}

		$url = $this->_getValidUrl($url);
		if (!$url)
		{
			return $text;
		}
		else
		{
			$url = \XF::app()->stringFormatter()->censorText($url);

			return "[url={$url}]{$text}[/url]";
		}
	}

	public function handleTagTex(array $tag, array $rendererStates)
	{
		$tex = $this->stringifyTree($tag['children']);
        $tex = urlencode($tex);
		$url = "http://latex.codecogs.com/gif.latex?{$tex}";
		return "[img]{$url}[/img]";
	}

	public function handleTagMedia(array $tag, array $rendererStates)
	{
		$mediaKey = trim($this->stringifyTree($tag['children']));
		if (preg_match('#[&?"\'<>]#', $mediaKey) || strpos($mediaKey, '..') !== false)
		{
			return '';
		}

		$mediaSiteId = strtolower($tag['option']);
		if (isset($this->_ttMediaSites[$mediaSiteId]))
		{
			$embedHtml = $this->_ttMediaSites[$mediaSiteId];
			return "[url]".str_replace('{$id}', urlencode($mediaKey), $embedHtml)."[/url]";
		}
		else
		{
			return "Unsupported video ([media={$mediaSiteId}]{$mediaKey}[/media])";
		}
	}


	public function handleTag(array $tag, array $rendererStates)
	{
		$tagName = $tag['tag'];

		if (isset($this->_advancedReplacements[$tagName]))
		{
			$callback = $this->_advancedReplacements[$tagName];
			if (is_array($callback) && $callback[0] == '$this')
			{
				$callback[0] = $this;
			}

			return call_user_func($callback, $tag, $rendererStates);
		}

		$output = $this->renderSubTree($tag['children'], $rendererStates);

		if (isset($this->_simpleReplacements[$tagName]))
		{
			$output = sprintf($this->_simpleReplacements[$tagName], $output);
		}

		return $output;
	}

	public function handleTagList(array $tag, array $rendererStates)
	{
		$bullets = explode('[*]', trim($this->renderSubTree($tag['children'], $rendererStates)));

		$output = "\n";
		foreach ($bullets AS $bullet)
		{
			$bullet = trim($bullet);
			if ($bullet !== '')
			{
				$output .= " - ".$bullet . "\n";
			}
		}
		$output .= "\n";

		return $output;
	}

	public function handleTagQuote(array $tag, array $rendererStates)
	{
		if (empty($rendererStates['quoteDepth']))
		{
			$rendererStates['quoteDepth'] = 1;
		}
		else
		{
			$rendererStates['quoteDepth']++;
		}
/*
		if ($this->_maxQuoteDepth > -1 && $rendererStates['quoteDepth'] > $this->_maxQuoteDepth)
		{
			return '';
		}*/

		if ($tag['option'])
		{
			$parts = explode(',', $tag['option']);
			$name = $this->filterString(array_shift($parts), $rendererStates);

			$tag['option'] = '';
			$tag['original'][0] = '[quote]';
		}
		else
		{
			$name = false;
		}

		if (!empty($tag['original']) && is_array($tag['original']))
		{
			list($prepend, $append) = $tag['original'];
		}
		else
		{
			$prepend = '';
			$append = '';
		}

		if(!empty($name)){
			$prepend_referer = new XenForo_Phrase('x_said', array('name' => $name)).": ";

			if (isset($rendererStates['returnHtml']) && $rendererStates['returnHtml'])
				$prepend .= "<b>{$prepend_referer}</b><br />";
			else
				$prepend .= "$prepend_referer\r\n";
		}

/*
		if ($rendererStates['quoteDepth'] == $this->_maxQuoteDepth)
		{
			// at the edge of the quote, so we want to ltrim whatever comes after
			foreach ($tag['children'] AS $key => $child)
			{
				if (is_array($child) && !empty($child['tag']) && $child['tag'] == 'quote' && isset($tag['children'][$key + 1]))
				{
					$after =& $tag['children'][$key + 1];
					if (is_string($after))
					{
						$after = ltrim($after);
					}
				}
			}
		}

		if ($this->_stripAllBbCode)
		{
			$prepend = '';
			$append = '';
		}*/

		return $this->filterString($prepend, $rendererStates)
			. $this->renderSubTree($tag['children'], $rendererStates)
			. $this->filterString($append, $rendererStates);
	}

	public function handleTagCode($tag, $rendererStates){
		if ($tag['option'])
		{
			$parts = explode(',', $tag['option']);
			$name = $this->filterString(array_shift($parts), $rendererStates);

			$tag['option'] = '';
			$tag['original'][0] = '[CODE]';
		}
		else
		{
			$name = false;
		}

		if (!empty($tag['original']) && is_array($tag['original']))
		{
			list($prepend, $append) = $tag['original'];
		}
		else
		{
			$prepend = '';
			$append = '';
		}

		if(!empty($name)){
			$prepend_referer = new XenForo_Phrase('x_said', array('name' => $name)).": ";

			if (isset($rendererStates['returnHtml']) && $rendererStates['returnHtml'])
				$prepend .= "<b>{$prepend_referer}</b><br />";
			else
				$prepend .= "$prepend_referer\r\n";
		}

		return $this->filterString($prepend, $rendererStates)
			. $this->renderSubTree($tag['children'], $rendererStates)
			. $this->filterString($append, $rendererStates);
	}

	public function handleTagPHP($tag, $rendererStates){
		$content = $this->renderSubTree($tag['children'], $rendererStates);
		$content = preg_replace('/\[(CODE|\/CODE)\]/', "[ $1]" , $content);
		return '[CODE]'
			. $content
			. '[/CODE]';
	}

	public function handleTagHtml($tag, $rendererStates){
		$content = $this->renderSubTree($tag['children'], $rendererStates);
		$content = preg_replace('/\[(CODE|\/CODE)\]/', "[ $1]" , $content);
		return '[CODE]'
			. $content
			. '[/CODE]';
	}

	public function handleTagSpoiler($tag, $rendererStates){
		$bullets = explode('[*]', trim($this->renderSubTree($tag['children'], $rendererStates)));

		$output = "\n";
		foreach ($bullets AS $bullet)
		{
			$bullet = trim($bullet);
			if ($bullet !== '')
			{
				$output .= "[spoiler]".$bullet . "[/spoiler]\n";
			}
		}
		$output .= "\n";
		return $output;
	}

    public function handleTagColor($tag, $rendererStates){
        $content = $this->renderSubTree($tag['children'], $rendererStates);
        return '<font color="'.$tag['option'].'">'.$content.'</font>';
    }

    protected function replaceSmiliesInText($text, $replaceCallback, $escapeCallback = null)
    {
        return \XF::app()->stringFormatter()->replaceSmiliesInText($text, $replaceCallback, $escapeCallback);
    }
}
