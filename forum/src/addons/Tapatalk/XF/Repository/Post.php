<?php

namespace Tapatalk\XF\Repository;

class Post extends XFCP_Post
{
    /**
     * @param $postId
     * @return \XF\Entity\Post
     */
    public function findPostById($postId)
    {
        /** @var \XF\Entity\Post $post */
        $post = $this->finder('XF:Post')->whereId($postId)->fetchOne();
        return $post;
    }

    /**
     * @param \XF\Entity\Post $post
     * @return string
     */
    public function getQuoteTextForPost(\XF\Entity\Post $post)
    {
        if (!$post->isVisible()) {
            // non-visible posts shouldn't be quoted
            return '';
        }
        $app = \XF::app();
        $message = $post->message;

        $message = $app->stringFormatter()->getBbCodeForQuote($message, 'post');
        return $this->_getQuoteWrapperBbCode($post->toArray(), $message);
    }

    protected function _getQuoteWrapperBbCode(array $post, $message)
    {
        return '[QUOTE="' . $post['username']
            . ', post: ' . $post['post_id']
            . (!empty($post['user_id']) ? ', member: ' . $post['user_id'] : '')
            . '"]'
            . $message
            . "[/QUOTE]\n";
    }

    /**
     * @param $postIds
     * @return \XF\Mvc\Entity\ArrayCollection
     */
    public function getPostsByIds($postIds)
    {
        if (!is_array($postIds)) {
            $postIds = explode(',', $postIds);
        }
        $postIds = array_unique($postIds);
        return $this->finder('XF:Post')->whereIds($postIds)->fetch();
    }


}