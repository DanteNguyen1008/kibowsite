<?php

class UserController extends Zend_Controller_Action {
	
	public function init() {
		parent::init ();
		$option = array (
				"layout" => "layout",
				"layoutPath" => APPLICATION_PATH . "/layouts/scripts/default" 
		);
		Zend_Layout::startMvc ( $option );
	}
	public function indexAction() {
		
		$formInvitation = new Form_UserInvite ();
		
		$this->view->form = $formInvitation;
		if ($this->_request->isPost () && $formInvitation->isValid ( $_POST )) {
			echo 'a';
		}
		
		$formErrors = $formInvitation->getMessages ();
		
		foreach ( $formErrors as $err ) {
			foreach ( $err as $r ) {
				echo $r . '<br />';
			}
		
		}
	}
	
	public function signOutAction() {
		$authAdapter = Zend_Auth::getInstance ();
		$authAdapter->clearIdentity ();
		return $this->_redirect ( BASE_PATH . '/user' );
	}
	
	public function listAction() {
		
		$auth = Zend_Auth::getInstance ();
		
		if ($auth->getIdentity () == NULL) {
			return $this->_redirect ( BASE_PATH . '/user/sign-in' );
		}
		
		
		$modelUser = new Model_User();
		
		$adapter = new Zend_Paginator_Adapter_DbSelect(
				$modelUser->select ());
		
		$paginator = new Zend_Paginator($adapter);
		$paginator->setItemCountPerPage(5);
		$page = $this->_request->getParam('page', 1);
		$paginator->setCurrentPageNumber($page);
		
		$this->view->paginator = $paginator;
		$this->view->curPageCount = $paginator->getCurrentItemCount();
		
		if (isset($_POST['submit_option'])) {
			if ('ordering' == $_POST['submit_option']) {
				$this->ordering($paginator, $_POST['ordering']);
			} 
			else if ('delete' == $_POST['submit_option']) {
				$this->deleteUser($_POST['cid']);
				
				
				return $this->_redirect ( BASE_PATH . '/user/list' );
			} 
			else if ('reset' == $_POST['submit_option']) {
				unset($_SESSION['user']);
				return $this->_redirect('cp/user/list');
			}
		}
		
	}
	
	public function deleteUser ($ids)
	{
		
		$auth = Zend_Auth::getInstance();
	
		if ($auth->getIdentity() == NULL) {
			return $this->_redirect ( BASE_PATH . '/user/sign-in' );
		}
		
		
	
		$modelUser = new Model_User();
		if (is_array($ids)) {
			foreach ($ids as $id) {
				if ($id != 1) {
					$modelUser->deleteUser($id);
				}
			}
			
		} 
		else {
			$modelUser->deleteUser($ids);
			
		}
		
		
	}
	
	public function signInAction() {
		$msgErrors = array ();
		$formSignIn = new Form_UserSignIn ();
		
		if ($this->_request->isPost () && $formSignIn->isValid ( $_POST )) {
			
			/*
			 * Get value from Invitation form.
			 */
			$email = $formSignIn->getValue ( 'txtEmail' );
			$password = md5 ( $formSignIn->getValue ( 'txtPassword' ) );
			
			$db = Zend_Db_Table::getDefaultAdapter ();
			$authAdapter = new Zend_Auth_Adapter_DbTable ( $db, 'kb_user', 'email', 'password' );
			$authAdapter->setIdentity ( $email );
			$authAdapter->setCredential ( $password );
			
			$authResult = $authAdapter->authenticate ();
			
			if ($authResult->isValid ()) {
				// store the username, first and last names of the user
				$auth = Zend_Auth::getInstance ();
				$storage = $auth->getStorage ();
				$storage->write ( $authAdapter->getResultRowObject ( array (
						'user_id',
						'email',
						'display_name',
						'user_group_id'
				) ) );
				return $this->_redirect ( BASE_PATH . '/user' );
			} else {
				$msgErrors [] = "Email or Password incorrect.";
			}
		
		}
		
		$formErrors = $formSignIn->getMessages ();
		foreach ( $formErrors as $err ) {
			foreach ( $err as $r ) {
				
				$msgErrors [] = $r;
			}
		}
		
		$this->view->msgErrors = $msgErrors;
		$this->view->form = $formSignIn;
	}
	
	public function editAction() {
		
		$auth = Zend_Auth::getInstance ();
		
		if ($auth->getIdentity () == NULL) {
			return $this->_redirect ( BASE_PATH . '/user/sign-in' );
		}
		
		$msgErrors = array ();
		$formUserEdit = new Form_UserEdit ();
		$kentHelpers = new Kent_Helpers();
		
		$infoUser = $auth->getIdentity ();
		
		$modelUser = new Model_User ();
		$getParams = $this->_request->getParams ();
		
		/*
		 * Decide currunt user or one another.
		 */
		$userId = $infoUser->user_id;		
		if (isset( $getParams[ 'id' ] ) && $getParams[ 'id' ] != '') {
			$userId = (int) $getParams[ 'id' ];			
		}	
		
		/*
		 * Get user information
		 */
		$modelUser = new Model_User ();
		$curUser = $modelUser->getUser ( array (
				'user_id = ?' => $userId
		) );			
		
		if (!$curUser) {
			$msgErrors [] = 'Can not select user.';
			$this->view->msgErrors = $msgErrors;
			return;
		}
		
		$avatar = $kentHelpers->toImgArr($curUser[0]['avatar']);
		$avatar = isset($avatar[0]) ? $avatar[0] : 'noimg.jpg';		
		
		$txtEmail = $formUserEdit->getElement ( 'txtEmail' );
		$txtEmail->setValue ( $curUser [0] ['email'] );
		
		$currentAvatar = $formUserEdit->getElement ( 'currentAvatar' );
		$currentAvatar->setAttrib('src', BASE_PATH . '/' .  UPLOAD_FOLDER . '/@files/' . $avatar);
		
		$currentDisplayName = $formUserEdit->getElement ( 'txtDisplayName' );
		$currentDisplayName->setValue ( $curUser [0] ['display_name'] );
		
		if ($this->_request->isPost () && $formUserEdit->isValid ( $_POST )) {
						
			$password = (strlen($formUserEdit->getValue ( 'txtPassword' )) > 0 ) ? md5 ( $formUserEdit->getValue ( 'txtPassword' ) ) : '';
			$passwordOld = md5 ( $formUserEdit->getValue ( 'txtPasswordOld' ) );
			$displayName = $formUserEdit->getValue ( 'txtDisplayName' );			
			$dateUpdate = time ();
			
			/*
			 * Get file avatar
			*/
			$filesUploaded = $kentHelpers->upload(UPLOAD_PATH . DS . '@files');
			$strFilesName = $kentHelpers->toImgStr($filesUploaded);
			$strFilesName = ( strlen($strFilesName) > 0 ) ? $strFilesName : $avatar;
			
			$newAvatar = $kentHelpers->toImgArr( $strFilesName );
			$newAvatar = isset($newAvatar[0]) ? $newAvatar[0] : $avatar;
			
			$userData = array( 
					'display_name' => $displayName,
					'avatar' => $strFilesName 
					);
			
			if ($password != '') {
				/*
				 * Require Old password when update password
				*/
				if ($formUserEdit->getValue ( 'txtPasswordOld' ) == '') {
					$msgErrors [] = 'Old password is required to change password.';
				}
				else {
					if ( $curUser[0][ 'password' ] ==  $passwordOld) {
						$userData[ 'password' ] = $password;
					}
					else {
						$msgErrors [] = 'Current password wrong.';
					}
					
				}
			}
			
			if (count($msgErrors) <= 0) {
				$modelUser->editUser ( $userId, $userData );
				$msgErrors [] = 'Information has been saved.';
				$currentAvatar->setAttrib('src', BASE_PATH . '/' .  UPLOAD_FOLDER . '/@files/' . $newAvatar);
			}
		}
		
		/*
		 * Assign form errors to $msgError
		 */
		$formErrors = $formUserEdit->getMessages ();
		foreach ( $formErrors as $err ) {
			foreach ( $err as $r ) {
				
				$msgErrors [] = $r;
			}
		}
		$this->view->msgErrors = $msgErrors;
		$this->view->form = $formUserEdit;
	
	}
	
	public function registerAction() {
		
		$msgErrors = array ();
		$formUserRegister = new Form_UserRegister ();
		$kentHelpers = new Kent_Helpers();
		
		$getParams = $this->_request->getParams ();
		
		/*
		 * Check invitation code is stored in database or not.
		 */
		$modelReferenceCode = new Model_ReferenceCode ();
		$getReferenceCode = $modelReferenceCode->getReferenceCode ( array (
				'reference_code = ?' => $getParams ['reference-code'] 
		) );
		
		/*
		 * If there have an Reference Code be stored in database. Then, Show
		 * form input for Singup process. Else, Show user unaccept message.
		 */
		if (is_array ( $getReferenceCode ) && count ( $getReferenceCode ) > 0) {
			
			/*
			 * Fill email with email address have been invited.
			 */
			$txtEmail = $formUserRegister->getElement ( 'txtEmail' );
			$txtEmail->setValue ( $getReferenceCode [0] ['email'] );
			
			/*
			 * When submit form. Check validation. Continue process if data is
			 * validated.
			 */
			
			
			if ($this->_request->isPost () && $formUserRegister->isValid ( $_POST )) {
				
				/*
				 * Get value from Invitation form.
				 */
				$email = $formUserRegister->getValue ( 'txtEmail' );
				$password = md5 ( $formUserRegister->getValue ( 'txtPassword' ) );
				$displayName = $formUserRegister->getValue ( 'txtDisplayName' );
				$referenceCodeId = $getReferenceCode [0] ['reference_code_id'];
				$dateCreate = time ();
				
				/*
				 * Get file avatar
				*/
				$filesUploaded = $kentHelpers->upload(UPLOAD_PATH . DS . '@files');
				$strFilesName = $kentHelpers->toImgStr($filesUploaded);
				
				
				if ($email == $getReferenceCode [0] ['email'] && $referenceCodeId == $getReferenceCode [0] ['reference_code_id']) {
					
					$modelUser = new Model_User ();
					/*
					 * Check for duplicate email
					 */
					$duplicateEmail = $modelUser->getUser ( array (
							'email = ?' => $email 
					) );
					if (is_array ( $duplicateEmail ) && count ( $duplicateEmail ) > 0) {
						$msgErrors [] = 'This email has already existed.';
					} else {
						echo 'a';
						$userData = array (
								'email' => $email,
								'password' => $password,
								'user_group_id' => 2, 
								'display_name' => $displayName,
								'avatar' => $strFilesName,
								'reference_code_id' => $referenceCodeId,
								'date_create' => $dateCreate 
						);
						
						$modelUser->addUser ( $userData );
						
						return $this->_redirect ( $this->_request->getParam ( 'module', 'default' ) . '/' . $this->_request->getParam ( 'controller' ) . '/' . 'register-success' );
					}
				} else {
					$msgErrors [] = 'Sorry! You have not been invited yet.';
				}
			}
		} else {
			$msgErrors [] = 'Your Reference Code is wrong.';
		}
		
		/*
		 * Assign form errors to $msgError
		 */
		$formErrors = $formUserRegister->getMessages ();
		foreach ( $formErrors as $err ) {
			foreach ( $err as $r ) {
				
				$msgErrors [] = $r;
			}
		}
		$this->view->msgErrors = $msgErrors;
		$this->view->form = $formUserRegister;
	
	}
	
	public function registerSuccessAction() {
	
	}
	
	public function sendInvitationSuccessAction() {
	
	}
	
	public function sendInvitationAction() {
		
		$msgErrors = array ();
		$formInvitation = new Form_UserInvite ();
		
		if ($this->_request->isPost () && $formInvitation->isValid ( $_POST )) {
			
			/*
			 * Get value from Invitation form.
			 */
			$txtEmail = $formInvitation->getValue ( 'txtEmail' );
			$txtName = $formInvitation->getValue ( 'txtName' );
			
			/*
			 * Genarate random reference code. Then, insert Reference Code into
			 * reference_code table
			 */
			$referenceCode = md5 ( rand ( 0, 100000 ) * time () );
			
			$modelReferenceCode = new Model_ReferenceCode ();
			
			$checkDuplicateEmail = $modelReferenceCode->getReferenceCode ( array (
					'email = ?' => $txtEmail 
			) );
			
			/*
			 * If Email address has been exist. Then, ask for resend invitation
			 * email.
			 */
			if (is_array ( $checkDuplicateEmail ) && count ( $checkDuplicateEmail ) > 0) {
				
				$msgErrors [] = 'This email has been exist.';
			} else {
				$referenceCodeData = array (
						'reference_code' => $referenceCode,
						'email' => $txtEmail,
						'date_create' => time (),
						'creator_id' => '1',
						'language_id' => '1' 
				);
				$modelReferenceCode->addReferenceCode ( $referenceCodeData );
				
				/*
				 * Send Email with URL contain Reference Code
				 */
				// load the template
				$registrationLink = BASE_PATH . '/user/register/reference-code/' . $referenceCode;
				$bodyMail = '<h1>Hi ' . $txtName . '! You have been invited to join Kibow site</h1>';
				$bodyMail .= '<p>';
				$bodyMail .= 'Click following link to register your account: <br />';
				$bodyMail .= '<a href="' . $registrationLink . '" title="Click to get an account and join Kibow site">' . $registrationLink . '</a>';
				$bodyMail .= '</p>';
				
				// send mail
				$mail = new Zend_Mail ( 'UTF-8' );
				// set the subject
				$mail->setSubject ( 'Kibow site! Get an account.' );
				// set the message's from address to the person who submitted
				// the
				// form
				$mail->setFrom ( $txtEmail, '' );
				// for the sake of this example you can hardcode the recipient
				$mail->addTo ( SYS_EMAIL, 'Kibow\'s Admin' );
				$mail->setBodyHtml ( $bodyMail );
				// $result = $mail->send ();
				// if ($result) {
				// $msgErrors [] = 'Email have sent successfully!';
				// } else {
				// $msgErrors [] = 'Send mail error. Try again please!';
				// }
				echo $bodyMail;
				return $this->_redirect ( $this->_request->getParam ( 'module', 'default' ) . '/' . $this->_request->getParam ( 'controller' ) . '/' . 'send-invitation-success' );
			}
		
		}
		
		$formErrors = $formInvitation->getMessages ();
		foreach ( $formErrors as $err ) {
			foreach ( $err as $r ) {
				
				$msgErrors [] = $r;
			}
		}
		
		$this->view->msgErrors = $msgErrors;
		$this->view->form = $formInvitation;
	
	}
}
