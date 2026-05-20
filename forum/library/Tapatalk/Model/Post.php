<?php

class Tapatalk_Model_Post extends XFCP_Tapatalk_Model_Post
{
    /**
     * Sends an alert to members directly quoted in a post
     * Override to alert tag(@) too for tapatalk
     * @param array $post
     */
    public function alertQuotedMembers(array $post, array $thread = array(), array $forum = array())
    {
        $options = XenForo_Application::get('options');
        if($options->currentVersionId < 1020070)
            $quotedUsers = parent::alertQuotedMembers($post);
        else
            $quotedUsers = parent::alertQuotedMembers($post, $thread, $forum);
        $options = XenForo_Application::get('options');
        if($options->Tag_Function_Open)
        {
            $tagedUsers = $this->get_tagged_users($post['message']);
            foreach($tagedUsers as $idx => $user)
                $tagedUsers[$idx] = preg_replace('/\[USER=.*\](.*)\[\/USER\]/','$1',$user);
            
            if(!empty($tagedUsers))
            {
                $real_alerted_users = array();
                $user_Model = $this->_getUserModel();
                $users = $user_Model->getUsersByNames($tagedUsers);
                foreach ($users as $index => $user)
                {
                    if ($user['user_id'] == $post['user_id'])
                        continue;
                    if (in_array($user['user_id'], $quotedUsers)) 
                        continue;
                    $real_alerted_users[] = $user['user_id'];

                    XenForo_Model_Alert::alert($user['user_id'],
                            $post['user_id'], $post['username'],
                            'post', $post['post_id'],
                            'tag'
                    );
                }
                $quotedUsers = array_unique(array_merge($quotedUsers, $real_alerted_users));
            }
        }
        
        return $quotedUsers;
    }
    /**
     * Return users which match tag
     * @param string $str
     * @return array
     */
    protected function get_tagged_users($str)
    {
        if ( preg_match_all( '/(?<=^@|\s@)(#(.{1,50})#|\S{1,50}(?=[,\.;!\?]|\s|$))/U', $str, $tags ) )
        {
            foreach ($tags[2] as $index => $tag)
            {
                if ($tag) $tags[1][$index] = $tag;
            }
           
            return array_unique($tags[1]);
        }
       
        return array();
    }
}