<?php

class EWRporta2_ViewPublic_Thread_ViewPosts extends XFCP_EWRporta2_ViewPublic_Thread_ViewPosts
{
    public function renderJson()
    {
        $response = parent::renderJson();
        $articleModel = XenForo_Model::create('EWRporta2_Model_Articles');
       
        if ($article = $articleModel->getArticleByThreadIdOrAuto($this->_params['thread']['thread_id']))
        {
            if(!empty($article['article_options']['comments']))
            {
                foreach ($this->_params['posts'] AS $postId => $post)
                {
					if ($post['post_id'] != $this->_params['thread']['first_post_id'])
					{
						$output['messagesTemplateHtml']["#post-$postId"] =
							$this->createTemplateObject('EWRporta2_Article_Post', array_merge($this->_params, array('post' => $post)))->render();
					}
					else
					{
						if (empty($article['article_options']['attach']))
						{
							$post['attachments'] = false;
						}
						$post['signature'] = false;
						
						$output['messagesTemplateHtml']["#post-$postId"] =
							$this->createTemplateObject('post', array_merge($this->_params, array('post' => $post)))->render();
					}
                }
			
				$template = $this->createTemplateObject('', array());

				$output['css'] = $template->getRequiredExternals('css');
				$output['js'] = $template->getRequiredExternals('js');
				
				return XenForo_ViewRenderer_Json::jsonEncodeForOutput($output);
            }
        }
       
        return $response;
    }
}