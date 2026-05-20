<?php
class DBTC_ViewPublic_View extends XenForo_ViewPublic_Base
{
    public function renderJson()
    {
        $output = $this->_renderer->getDefaultOutputArray(get_class($this), $this->_params, $this->_templateName);
 
        $output['dbtc_transaction_id'] = $this->_params['dbtc_transaction_id'];
		$output['dbtc_thread_id'] = $this->_params['dbtc_thread_id'];
		$output['dbtc_donor_id'] = $this->_params['dbtc_donor_id'];     
		$output['dbtc_receiver_id'] = $this->_params['dbtc_receiver_id'];
		$output['dbtc_receiver_avatar_html'] = $this->_params['dbtc_receiver_avatar_html'];
		
		$output['dbtc_status_id'] = $this->_params['dbtc_status_id'];
 		$output['dbtc_receiver_name'] = $this->_params['dbtc_receiver_name'];	
		$output['dbtc_date'] = $this->_params['dbtc_date'];
		
        return XenForo_ViewRenderer_Json::jsonEncodeForOutput($output);
    }
}
?>