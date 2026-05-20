<?php
namespace Tapatalk\XF\BbCode;

class RuleSet extends XFCP_RuleSet
{
    protected $_simpleReplacements = array(
        'left' => "%s\n",
        'center' => "%s\n",
        'right' => "%s\n"
    );

    protected $_advancedReplacements = array(
        'quote' => array('$this', 'handleTagQuote'),
        'img' => array('$this', 'handleTagImg'),
        'email' => array('$this', 'handleTagEmail'),
        'url' => array('$this', 'handleTagUrl'),
        'media' => array('$this', 'handleTagMedia'),
        'spoiler' => array('$this', 'handleTagSpoiler'),
        'attach' => array('$this', 'handleTagAttach'),
        'list' => array('$this', 'handleTagList')
    );

    public function __construct($context = null, $addDefault = true)
    {
        parent::__construct($context, $addDefault);
    }

    /**
     * Gets the list of valid BB code tags. This removes most behaviors.
     *
     * @see XenForo_BbCode_Formatter_Base::getTags()
     */
    public function getTags()
    {
        if (isset($this->_tags) && $this->_tags !== null)
        {
            return $this->_tags;
        }

        $callback = array($this, 'handleTag');

        $tags = parent::getTags();
        foreach ($tags AS $tagName => &$tag)
        {
            unset($tag['replace'], $tag['callback']);
            $tag['callback'] = $callback;
        }

        return $tags;
    }

    protected function _setupCustomTagInfo($tagName, array $tag)
    {
        $output = parent::_setupCustomTagInfo($tagName, $tag);
        if (isset($output['replace']))
        {
            if (strlen($tag['replace_text']))
            {
                $output['replace'] = $tag['replace_text'];
            }
            else
            {
                $output['replace'] = '{text}' . ($tag['trim_lines_after'] ? "\n" : '');
            }
        }

        return $output;
    }

    public function filterString($string, array $rendererStates)
    {
        $string = \XF::app()->stringFormatter()->censorText($string);

        return $string;
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

    public function handleTagQuote(array $tag, array $rendererStates)
    {
        return "[quote]";
    }

    public function handleTagImg(array $tag, array $rendererStates)
    {
        return '[emoji328]';
    }
    public function handleTagEmail(array $tag, array $rendererStates)
    {
        return '[emoji394]';
    }
    public function handleTagUrl(array $tag, array $rendererStates)
    {
        return '[emoji288]';
    }

    public function handleTagMedia(array $tag, array $rendererStates)
    {
        return "[emoji327]";
    }

    public function handleTagSpoiler(array $tag, array $rendererStates)
    {
        return "[emoji85]";
    }

    public function handleTagAttach(array $tag, array $rendererStates)
    {
        return "[emoji420]";
    }

    public function handleTagList(array $tag, array $rendererStates)
    {
        $bullets = explode('[*]', trim($this->renderSubTree($tag['children'], $rendererStates)));

        $output = '';
        foreach ($bullets AS $bullet)
        {
            $bullet = trim($bullet);
            if ($bullet !== '')
            {
                $output .= $bullet . "\n";
            }
        }

        return $output;
    }
}