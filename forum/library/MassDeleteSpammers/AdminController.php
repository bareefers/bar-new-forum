<?php
class MassDeleteSpammers_AdminController extends XFCP_MassDeleteSpammers_AdminController
{
    public function actionMassDelete()
    {
        $this->_assertPostOnly();
        $userIds = $this->_input->filterSingle('user_ids', array(XenForo_Input::UINT, 'array' => true));


        foreach($userIds as $userId)
        {
            $user = $this->_getUserOrError($userId);

            $writer = XenForo_DataWriter::create('XenForo_DataWriter_User', XenForo_DataWriter::ERROR_EXCEPTION);
            $writer->setExistingData($user);
            $writer->preDelete();

            $this->getHelper('Admin')->checkSuperAdminEdit($user);

            if ($this->_getUserModel()->isUserSuperAdmin($user))
            {
                return $this->responseError(new XenForo_Phrase('mds_cannot_delete_admin'));
            }

            $this->_deleteData(
                    'XenForo_DataWriter_User', $user,
                    XenForo_Link::buildAdminLink('users/mdslist')
            );
        }
        return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('users/mdslist')
		);
    }

    public function actionMdslist()
    {
        $userModel = $this->_getUserModel();
        $options = XenForo_Application::get('options');

        $start = $this->_input->filterSingle('page', XenForo_Input::UINT) < 1 ? 1 : $this->_input->filterSingle('page', XenForo_Input::UINT);
        $stop = $options->MDS_perpage;

        $viewParams = array(
                'start' => $start,
                'stop' => $stop,
                'count' => $userModel->getAccountsCount(),
                'users' => $userModel->findAccounts($start, $stop),
        );

        return $this->responseView('XenForo_ViewAdmin_User_List', 'mds_list', $viewParams);



    }
}
?>
