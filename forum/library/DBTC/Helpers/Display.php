<?php
class DBTC_Helpers_Display
{
	# setup global dbtc variable crap
	protected static $dbtctree = "";
	protected static $alltrans = array();
	protected static $alltransindex = array();
	protected static $alltranstree = array();
	
	# input: list of all transactions for a thread, donor id are keys
	public static function renderTree($index, $transactions, $transactions_tree, $cataloginfo)
    { 	
		global $level, $alltrans, $dbtctree, $alltranstree, $alltransindex;
		$alltrans = $transactions;
		$alltransindex = $index;
		$alltranstree = $transactions_tree; // return entire transaction list);
	
	    if (empty($cataloginfo)) {
		  return "Please email your web administrator with a link to this thread if you want to add this thread to the DBTC database!!";
		}
	
		$dbtctree = '<ul class="dbtc"><li>';
		$dbtctree = $dbtctree . DBTC_Helpers_Display::renderNode($alltrans[key($alltranstree)]);
		DBTC_Helpers_Display::renderTrans(reset($alltranstree));
		// close it off
		$dbtctree = $dbtctree . '</li></ul>';
		return $dbtctree;
    }
	
	public static function renderTrans($trans) 
	{	
		global $alltrans, $alltransindex, $dbtctree;
		
		$precode = '<ul>';
		$dbtctree = $dbtctree . $precode;

		// render transactions
		if (!empty($trans)) {
			foreach ($trans as $dbtc_trans_id => $childtransactions) { // foreach transaction
			
				$dbtctree = $dbtctree . '<li>' . DBTC_Helpers_Display::renderNode($alltrans[$dbtc_trans_id]); // display the node
				if (!empty($childtransactions)) {
				  $dbtctree = $dbtctree . DBTC_Helpers_Display::renderTrans($childtransactions);
				}
				$dbtctree = $dbtctree . "</li>";
			}
		} 
		
		$postcode = "</ul>";
		$dbtctree = $dbtctree . $postcode;
	}
	public static function renderNode($transInfo) {

		$userModel = XenForo_Model::create('XenForo_Model_User');
		$userData = $userModel->getUserById($transInfo['dbtc_receiver_id']);
		
		# Grab avatar and link
		$avatarHtml = XenForo_Template_Helper_Core::callHelper('avatarhtml', array($userData, TRUE, array('size' => 's'), ''));
		
		# Grab username and link
		// $userName = XenForo_Template_Helper_Core::callHelper('usernamehtml', array($user));
		
		$dbtc_parent_transaction_id = $transInfo['dbtc_parent_transaction_id'];
		$dbtcParentTransactionHtml = '<div id="dbtc_parent_transaction_id" class="dbtc_parent_transaction_id" value="' . $dbtc_parent_transaction_id .'"></div>';
		
		// add hidden form stuff
		$dbtc_thread_id = $transInfo['dbtc_thread_id'];
		$dbtcThreadHtml = '<div id="dbtc_thread_id" class="dbtc_thread_id" value="' . $dbtc_thread_id .'"></div>';
		
		$dbtc_transaction_id = $transInfo['dbtc_transaction_id'];
		
		$dbtc_donor_id = $transInfo['dbtc_donor_id'];
		$dbtcDonorHtml = '<div class="dbtc_donor_id" id="dbtc_donor_id" value="' . $dbtc_donor_id .'"></div>';
		
		$userName = $userData['username'];
		$userNameHtml = '<div class="dbtc_username" id="dbtc_username" value="'. $userName . '">' . $userName . '</div>';
		
		$userId	= $transInfo['dbtc_receiver_id'];
		$userIdHtml = '<div class="dbtc_receiver_id" id="dbtc_receiver_id" value="'. $userId . '" style="display: none;">' . $userId . '</div>';
		// $userId = '<div class="dbtc_receiver_id" id="dbtc_receiver_id" value="'. $userId . '">' . $userId . '</div>';

		# Grab and translate unix time
		
		$dbtcDate = gmdate("m/d/Y", $transInfo["dbtc_date"]);
		$dbtcDateHtml = '<div id="dbtc_date" class="dbtc_date">' . $dbtcDate . "</div>";
		
		# grab dbtc status info
		$dbtcStatus_id = $transInfo["dbtc_status_id"];
		# list of statuses
		$status = array(0 => "growing",
						  1 => "available",
						  2 => "failed",
						  3 => "unknown");
						  

		$dbtcStatusHtml = '<div id="dbtc_status_id" class="status">' . $dbtcStatus_id . '</div>';
		
		$nodeClassStartHtml = '<div id="dbtc_transaction" data-id="'. $dbtc_transaction_id .'" class="node ' . $status[$dbtcStatus_id] .'">';
		$nodeClassEndHtml = '</div>';
		
		// create string
		$nodeString = $nodeClassStartHtml . $dbtcThreadHtml . $dbtcParentTransactionHtml . $dbtcDonorHtml . $avatarHtml . $userNameHtml . $userIdHtml . $dbtcDateHtml . $nodeClassEndHtml;
		// print($nodeString);
		
		return $nodeString;
	}
	
	public static function renderStats($index, $transactions) {
	
		$preHtml = '<dl class="pairsJustified">';
		$postHtml = '</dl>';
		
		if (empty($transactions)) {
		  return "None";
		}

		$chainlength = (max(array_column($transactions, 'dbtc_date')) - min(array_column($transactions, 'dbtc_date')))/86400;
		
		// count of how many transactions each person has
		$transAmounts = array_map(count, $index);
		$maxdonors = array_keys($transAmounts, max($transAmounts));

		// setup user model to grab the usernames from the ids
		$maxDonorsHtml = "";
		$userModel = XenForo_Model::create('XenForo_Model_User');
		
		$maxDonors = array();
		
		foreach ($maxdonors as $donorId) {
			$user = $userModel->getUserById($donorId);
			$donorName = $user['username'];
			$maxDonors[] = $donorName;
		}
		// growing|available|failed|unknown'
		
		$statuses = array_count_values(array_column($transactions, 'dbtc_status_id'));
		
		$statusGrowing = empty($statuses[0]) ? "0" : $statuses[0];
		$statusAvailable = empty($statuses[1]) ? "0" : $statuses[1];
		$statusFailed = empty($statuses[2]) ? "0" : $statuses[2];
		$statusUnknown = empty($statuses[3]) ? "0" : $statuses[3];
		
		$maxDonorsHtml = implode(", ", $maxDonors);
		
		$mostdonations = count(max($index));
		
		$longestchainHtml = '<dt>Chain Length:</dt><dd>' . floor($chainlength) . " days</dd>";
		$startDateHtml = '<dt>Chain Started on:</dt><dd>' . gmdate("m/d/Y", min(array_column($transactions, 'dbtc_date'))) . '</dd>';
		$lastDateHtml = '<dt>Latest Updated on:</dt><dd>' .  gmdate("m/d/Y", max(array_column($transactions, 'dbtc_date'))) . '</dd>';
		$mostgenerousdonorsHtml = '<dt>Most Generous Donors:</dt><dd>' . $maxDonorsHtml . '</dd>';
		$mostsingledonationsHtml = '<dt>Most Single Donations:</dt><dd>' . $mostdonations . '</dd>';
		$numberoftransactionsHtml = '<dt>Number of Transactions:</dt><dd>' . count($transactions) . '</dd>';
		$numbergrowingHtml = '<dt>Currently Growing:</dt><dd>' . $statusGrowing . '</dd>';
		$numberavailableHtml =  '<dt>Currently Available:</dt><dd>' . $statusAvailable . '</dd>';
		$numberfailedHtml =  '<dt>Currently Failures:</dt><dd>' . $statusFailed . '</dd>';
		$numberunknownHtml =  '<dt>Currently Unknown:</dt><dd>' . $statusUnknown . '</dd>';
		
		$threadIdArray = array_column($transactions, 'dbtc_thread_id');
		$threadId = $threadIdArray[0];
		$attachmentsHtml = DBTC_Helpers_Display::returnAttachmentsHtml(DBTC_Helpers_Display::returnAttachments($threadId));

		return $preHtml . $startDateHtml . $lastDateHtml . $longestchainHtml .  $mostgenerousdonorsHtml . $mostsingledonationsHtml . $numberoftransactionsHtml . 
				$numbergrowingHtml . $numberavailableHtml . $numberfailedHtml . $numberunknownHtml . $postHtml . $attachmentsHtml;
	
	}
	
	public static function renderHeader($dbtc_catalog_info) {
	
		if (empty($dbtc_catalog_info)) {
		  return "No Transactions";
		}
	
		$headerHtml = $dbtc_catalog_info['dbtc_description'];
		return $headerHtml;
	}
	
	public static function returnAttachmentsHtml($attachmentsData) {
		$preHtml = '<div class="dbtc_attached_images"><ul class="attachmentList SquareThumbs">';
		$postHtml = '</div></ul>';
		
		$attachmentModel = XenForo_Model::create('XenForo_Model_Attachment');

		
		$thumbnailLinks = "";
		$lightboxLink = XenForo_Link::buildPublicLink('misc/lightbox');
		
		foreach ($attachmentsData as $attachment) {
			$preparedAattachment = $attachmentModel->prepareAttachment($attachment);
			$thumbnailLinks .= '<li class="attachment image title="'. $preparedAattachment['filename'] .'">';
			$thumbnailLinks .= '<div class="thumbnail">';
			
			$thumbnailLinks .= '<a class="LbTrigger SquareThumb" data-href="' . $lightboxLink . '"' .
							   'data-author=""' .
							   'target="_blank" href="' . $preparedAattachment['viewUrl'] . '">' .
							   '<img class="LbImage" alt="' . $preparedAattachment['filename'] . '" src="' . $preparedAattachment['thumbnailUrl'] . '"' .
							   'style="height: 50px;">' .
							   '</img></a>';
			
			$thumbnailLinks .= '</div></li>';
		}
		
		
		return $preHtml . $thumbnailLinks . $postHtml;
	}
	
	public static function returnAttachments($threadId) {
	
	
	    /* Let's get all the attachments of this post, if exists  */
		$attachmentModel = XenForo_Model::create('XenForo_Model_Attachment');
		
		$postModel = XenForo_Model::create('XenForo_Model_Post');
		$posts = $postModel->getPostsInThread($threadId);
		
		$attachmentsData = array();
		foreach ($posts as $post) {
			$attachmentData = $attachmentModel->getAttachmentsByContentId('post', $post['post_id']);
			if (!empty($attachmentData)) {
				foreach ($attachmentData as $attachment) {
					$attachmentsData[] = $attachment;
				}
			}
		}
		// print_r($attachmentsData);
		return $attachmentsData;
	}
}
?>