<?php

class EWRporta2_Model_Authors extends XenForo_Model
{
	public function getAuthorById($authorID)
	{
		if (!$author = $this->_getDb()->fetchRow("
			SELECT EWRporta2_authors.*, xf_user.*
			FROM EWRporta2_authors
				LEFT JOIN xf_user ON (xf_user.user_id = EWRporta2_authors.user_id)
			WHERE EWRporta2_authors.user_id = ?
		", $authorID))
		{
			return false;
		}

		return $author;
	}
	
	public function sortAuthors($authors)
	{
		$active = array();
		$inactive = array();
		
		foreach ($authors AS $author)
		{
			if ($author['author_order'])
			{
				$active[] = $author;
			}
			else
			{
				$inactive[] = $author;
			}
		}
		
		return array('active' => $active, 'inactive' => $inactive);
	}
	
	public function getAllAuthors($type = false)
	{
		$authors = $this->_getDb()->fetchAll("
			SELECT EWRporta2_authors.*, xf_user.*
			FROM EWRporta2_authors
				LEFT JOIN xf_user ON (xf_user.user_id = EWRporta2_authors.user_id)
			ORDER BY EWRporta2_authors.author_order, EWRporta2_authors.author_name
		");
		
		if (!$type)
		{
			$authors = $this->sortAuthors($authors);
		}
		
		return $authors;
	}

	public function updateAuthor($input)
	{
		$dw = XenForo_DataWriter::create('EWRporta2_DataWriter_Authors');

		if (!empty($input['user_id']) && $author = $this->getAuthorById($input['user_id']))
		{
			$dw->setExistingData($author);
		}
		
		$dw->bulkSet($input);
		$dw->save();
		
		return $dw->getMergedData();
	}
	
	public function deleteAuthor($input)
	{
		$dw = XenForo_DataWriter::create('EWRporta2_DataWriter_Authors');
		$dw->setExistingData($input);
		$dw->delete();
		
		return true;
	}
	
	public function updateAuthorOrders($orders)
	{
		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);
		
		foreach ($orders AS $key => $value)
		{
			$dw = XenForo_DataWriter::create('EWRporta2_DataWriter_Authors');
			$dw->setExistingData($key, true);
			$dw->set('author_order', $value);
			$dw->save();
		}
		
		XenForo_Db::commit($db);
		
		return true;
	}
	
	public function updateAuthorImage($author, $fileURL)
	{
		try
		{
			$target = XenForo_Helper_File::getExternalDataPath().'/authors/'.$author['user_id'].'.jpg';
			$width = 150;
			$height = 200;
			
			$imageInfo = getimagesize($fileURL);
			
			if ($image = XenForo_Image_Abstract::createFromFile($fileURL, $imageInfo[2]))
			{
				$ratio = $width / $height;
				
				$w = $image->getWidth();
				$h = $image->getHeight();
				
				if ($w / $h > $ratio)
				{
					$image->thumbnail($w, $height);
				}
				else
				{
					$image->thumbnail($width, $h);
				}

				$w = $image->getWidth();
				$h = $image->getHeight();
				$offWidth = ($w - $width) / 2;
				$offHeight = ($h - $height) / 2;

				$image->crop($offWidth, $offHeight, $width, $height);
				$image->output(IMAGETYPE_JPEG, $target);
			}
		
			return true;
		}
		catch (Exception $e)
		{
			throw new XenForo_Exception(new XenForo_Phrase('porta2_author_image_fail'), true);
		}
	}
}