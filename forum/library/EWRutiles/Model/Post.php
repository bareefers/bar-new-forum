<?php

class EWRutiles_Model_Post extends XFCP_EWRutiles_Model_Post
{
	public function getQuoteTextForPost(array $post, $maxQuoteDepth = 0)
	{
		$response = parent::getQuoteTextForPost($post, $maxQuoteDepth);
		$response = preg_replace('#\[notice.*?\].*?\[/notice\]#i', '', $response);
		return $response;
	}
}