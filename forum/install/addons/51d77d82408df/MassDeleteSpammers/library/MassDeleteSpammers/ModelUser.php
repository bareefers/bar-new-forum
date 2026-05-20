<?php
class MassDeleteSpammers_ModelUser extends XFCP_MassDeleteSpammers_ModelUser
{
	public function findAccounts($start, $stop)
	{
		$options = XenForo_Application::get('options');
		$start = ($start - 1) * $stop;

		$spam = $this->_getDb()->fetchAll("
			SELECT 	xf_user.user_id, 
					xf_user.username, 
					xf_user.message_count, 
					xf_user.is_banned,
					xf_user_profile.homepage, 
					xf_user_profile.signature, 
					xf_user_profile.about
			FROM 	xf_user, xf_user_profile 
			WHERE 	xf_user_profile.user_id = xf_user.user_id
			AND 	xf_user.message_count <= ?
			AND
			 ((homepage LIKE '%URL%' OR signature LIKE '%URL%' OR about LIKE '%URL%') OR
			 (homepage LIKE '%HTTP%' OR signature LIKE '%HTTP%' OR about LIKE '%HTTP%'))
			ORDER BY xf_user.user_id DESC
			LIMIT ?, ?
		", array($options->MDS_limit, $start, $stop));

		return $spam;
	}

	public function getAccountsCount()
	{
		$options = XenForo_Application::get('options');

        $count = $this->_getDb()->fetchOne("
			SELECT 	COUNT(*) AS total
			FROM 	xf_user, xf_user_profile 
			WHERE	xf_user_profile.user_id = xf_user.user_id
			AND		xf_user.message_count <= ? 
			AND
			 ((homepage LIKE '%URL%' OR signature LIKE '%URL%' OR about LIKE '%URL%') OR
			 (homepage LIKE '%HTTP%' OR signature LIKE '%HTTP%' OR about LIKE '%HTTP%'))
		", $options->MDS_limit);

		return $count;
	}
}
?>
