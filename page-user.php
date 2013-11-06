<?php
/*
  FILE: user.php
  HISTORY:
    2013-09-15 created for handling log-ins during checkout
    2013-09-25 split most of clsPageUser into clsPageLogin
    2013-10-10 split clsPageUser off from user.php to page-user.php to reduce unnecessary lib loading
*/

require_once('config-admin.php');

class clsPageUser extends clsPageCkout {
    private $isLogged;
    private $sPgKey;
    private $isLogin;	// this is an attempt to log in
    private $doEmail;	// user requested pw reset via emaile
    private $isReset;	// user is changing username/pw
    private $isAuth;	// user has clicked an authorization link
    private $doLogout;	// user has requested a log-out
    private $sUser;	// entered username
    private $sPass;	// entered password
    private $sEmail;	// entered email address
    private $sAuth;	// email authorization code
    private $idEmail;	// email ID for authorization

    /*-----
      ACTION: Grab any expected input and interpret it
	In this case, first check which button was pressed...
    */
    protected function ParseInput() {
	$this->doNavBar = FALSE;	// we don't need the navigation buttons
	//$this->GetObjects();		// get session and cart

	$this->isLogged = FALSE;
	$this->sAuth = NULL;
	$this->doReset = FALSE;

	$isLogin = !empty($_POST['btnLogIn']);
	$isReset = !empty($_POST['btnSetLogIn']);
	$isEmReq = !empty($_POST['btnPassReset']);
	$doGetLogin = $isLogin || $isReset;
	$doGetAuth = !empty($_GET['auth']);
	$doLogout = array_key_exists('exit',$_GET);

	$this->isLogin = $isLogin;
	$this->isReset = $isReset;
	$this->isAuth = $doGetAuth;
	$this->doEmail = $isEmReq;
	$this->doLogout = $doLogout;

	if ($doGetLogin || $doGetAuth) {
	    if ($doGetLogin) {
		$this->sUser = $_POST['uname'];
		$this->sPass = $_POST['upass'];
	    }
	} elseif ($isEmReq) {	// requesting password request email
	    $this->sEmail = $_POST['uemail'];
	}
    }
    /*-----
      ACTION: Take the parsed input and do any needed processing (e.g. looking up data)
    */
    protected function HandleInput() {
	if ($this->isLogin) {
	    $this->SessObj()->UserLogin($this->sUser,$this->sPass);
	}

	//$this->sPgKey = 'user';
	$this->sPgKey = 'login';
	$this->pgShow = $this->sPgKey;
	if ($this->IsLoggedIn()) {
	    $this->sTitle = 'User Options';
	} elseif ($this->doEmail) {
	    $this->sTitle = 'Send Password Reset Email';
	} elseif ($this->isAuth) {
	    $this->sTitle = 'Authorize Password Reset';
	} elseif ($this->isReset) {
	    $this->sTitle = 'Password Resetting';
	} else {
	    $this->sTitle = 'User Login';
	}
    }

    public function TitleStr() {
	return $this->sTitle;
    }
    protected function PageNavKey() {
	return 'lgi';
    }
    protected function CreateNavBar() {
	$oNav = parent::CreateNavBar();
	$oNav->AddAfter(KSQ_PAGE_CART,new clsNavText($oNav,KSQ_PAGE_LOGIN,'Log In'));
	  //$oNav->Popup('log in or set up user account');
	$oNav->AddAfter(KSQ_PAGE_LOGIN,new clsNavText($oNav,KSQ_PAGE_USER,'Profile'));
	  //$oNav->Popup('manage your user account');
	return $oNav;
    }
    /*-----
      ACTION: Render main content -- stuff that changes
      TODO: lots of things need to be logged
    */
    public function DoContent() {
	$oSkin = $this->Skin();
	$oSess = $this->App()->Session();
	$tblEmails = $this->EmailAuth();
	if ($this->doEmail) {
	    $sEmail = $this->sEmail;
	    // check to see if this is a known email address
	    $ht = $tblEmails->SendPassReset_forAddr($sEmail);
	    echo $ht;
	}
	if ($this->doLogout) {
	    $oSess->UserLogout();
	    echo $oSkin->RenderSuccess('You are now logged out.');
	}

	echo $oSkin->RenderTableHLine();

	if ($this->isAuth) {
	    // auth pre-empts regular log-in stuff, to avoid confusion
	    $ar = $tblEmails->CheckAuth('You can now set your username and password.');	// check token
	    $ht = $ar['html'];
	    echo $ht;
	} elseif ($this->isReset) {	// new username/password submitted
	    // check token, but don't display messages
	    $ar = $tblEmails->CheckAuth(NULL);
	    if ($ar['ok']) {
		// auth token checks out
		// check for duplicate username
		$tblUsers = $this->UserRecs();
		$sUser = $this->sUser;

		if (!$this->IsLoggedIn() && $tblUsers->UserExists($sUser)) {
		    // display error -- trying to create user account with username that already exists
		    $ht = $this->Skin()->RenderError('The username "'.$sUser.'" already exists; please choose another.<br>');
		    $ht .= $this->Skin()->RenderUserSet($ar['auth'],NULL);
		    echo $ht;
		} else {
		    // name is available:

		    // -- create the record
		    $rcUser = $tblUsers->AddUser($this->sUser,$this->sPass);
		    if (is_null($rcUser)) {
			// display error message
			$ht = $oSkin->RenderError('There has been a mysterious database problem.');
			$ht .= 'For some reason, your username could not be created. The admin is being alerted.';
			echo $ht;
			throw new exception('Dumping stack to help with debugging.');
		    } else {
			// display success message
			$oSkin->RenderSuccess('Account created -- welcome, <b>'.$sUser.'</b>!');
			// record user ID in session (so we're logged in)
			$idUser = $rcUser->KeyValue();
			$oSess->SetUser($idUser);

			$sEmail = $ar['em_s'];
			$this->AttachEmail($sEmail,$idUser);
		    } // END account created
		} // END name is available
	    } else {	// END authorized
		// this can only happen if someone is doing naughty hacking
	    }
	} elseif ($this->IsLoggedIn()) {
	    if ($this->DidTryLogin()) {
		echo $oSkin->RenderSuccess('Log in successful -- <b>welcome, '.$this->App()->User()->RecObj()->FullName().'!</b>');
	    }
	    echo 'You may now access your <a href="'.KWP_UACCT.'" title="view and manage your account">user profile</a>.';
	    echo '<ul>'
	      .'<li>Orders you place while logged in will now be listed there.</li>'
	      .'<li>When you pay for an order, you can select from any payment sources you have stored there.</li>'
	      .'</ul>';
	    if ($oSess->HasCartContents()) {
		echo 'You may also <a href="'.KWP_CKOUT.'">continue with checkout</a> (recommended) and place your order.';
	    }
	} else {
	    echo '<b>If you have forgotten your password<br>or have not set up an account</b> &ndash; ';
	    echo $oSkin->RenderForm_Email_RequestReset($this->sEmail);
	    echo '<br>This emails you a password-reset link.';

	    echo $oSkin->RenderTableHLine();
	    echo '<b>If you already have an account</b> &ndash; ';
	    echo $oSkin->RenderLogin($this->sUser);
	    if ($this->DidTryLogin()) {
		echo $oSkin->RenderError('Sorry, the given username/password combination was not valid.');
	    }
	    //echo "\n</td></tr></table>";
	}
    }
    protected function AttachEmail($sEmail,$idUser) {
	// -- find all customer profiles with the same email address, and assign them to the new user:
	$tEmails = $this->App()->Data()->CustEmails();
	$nRows = $tEmails->AssignUser_toAddr($idUser,$sEmail);
	$oSkin = $this->Skin();
	$ok = $nRows > 0;
	if ($ok) {
	    $rcUser = $this->App()->User();
	    $ht = $oSkin->RenderSuccess($nRows.' additional customer profile'.Pluralize($nRows,' is','s are').' now attached to user account "'.$rcUser->UserName().'".');
	    // TODO: log this
	} else {
	    $ht = $oSkin->RenderError('No customer profiles added for the email address "'.$sEmail.'".');
	    // TODO: log this
	}
	$ar = array(
	  'html'	=> $ht,
	  'ok'		=> $ok
	  );
	return $ar;
    }
    /*-----
      ACTION: Render content footer -- anything that displays after main content on all pages of a given class
      
    */
    protected function DoContFtr() {
	echo $this->Skin()->RenderTableHLine();

	$oNav = $this->MakeFtrNavBar();
	// set the CSS class to use for the active item
	$oNav->CSSClass('nav-item-active',clsNavLink::KI_CURRENT);

	$sPgNav = $this->PageNavKey();

	//$oNav->States(NULL,clsNavLink::KI_DEFAULT,NULL);	// default state
	$oNav->States(NULL,clsNavLink::KI_DEFAULT,NULL);	// debug
	$oNav->Node($sPgNav)->State(clsNavLink::KI_CURRENT);	// override parent class
	$oNav->CSSClass('nav-link-current',clsNavLink::KI_CURRENT);	// use 'nav-link-current' for whichever link is current

	echo $this->Skin()->RenderNavbar_H($oNav);
    }
/*
    protected function PageNavKey() {
	return KSQ_PAGE_LOGIN;
    }
*/
    protected function MakeFtrNavBar() {
	$oNav = new clsNavbar_flat();
	  $oi = new clsNavLink($oNav,'cko','checkout');
	    $oi->URL(KWP_CKOUT);
	    $oi->Popup('return to checkout / finish your order');

	if ($this->IsLoggedIn()) {
	    $oi = new clsNavLink($oNav,'lgi','setup');
	      $oi->URL(KWP_LOGIN);
	      $oi->Popup('log in or set up your user account');
	      $oi->State(clsNavLink::KI_CURRENT);	// active
	    $oi = new clsNavLink($oNav,'acc','account');
	      $oi->URL(KWP_UACCT);
	      $oi->Popup('manage your user account');
	    $oi = new clsNavLink($oNav,'lgo','log out');
	      $oi->URL(KWP_LOGOUT);
	      $oi->Popup('log out of the system - browser will forget who you are');
	}
	$oNav->Decorate('[',']',' ... ');
	$oNav->CSSClass('nav-item',		clsNavLink::KI_DEFAULT);
	$oNav->CSSClass('nav-item-current',	clsNavLink::KI_CURRENT);
	
	return $oNav;
    }

    protected function DidTryLogin() {
	return $this->isLogin;
    }
    private function UserRecObj() {
	return $this->SessObj()->UserObj();
    }
    /*----
      USAGE: called from account setup page *and* account management page
    */
    protected function EmailAuth() {
	return $this->App()->Data()->Make('clsEmailAuth');
    }
    private function UserRecs() {
	return $this->App()->Data()->Users();
    }
}
