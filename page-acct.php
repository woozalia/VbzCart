<?php
/*
  PURPOSE: page class for administering user account
  HISTORY:
    2013-10-10 created
*/

class clsPageAdmin_Acct extends clsPageUser {
    private $doAddEmail, $sEmail;
    private $doGetAuth, $sAuth;
    private $isLoggedIn;

    /*----
      RETURNS: the administrative version of the users table object
    */
/* turns out not to be needed
    protected function UsersTbl() {
	$tbl = $this->Engine()->Make('clsVbzUserRec_admin');
	return $tbl;
    }
*/
    /*----
      RETURNS: the administrative version of the current user object
    */
/*
    public function UserObj() {
	$oU = $this->App()->User();
	$tU = $this->App()->Data()->Make('clsVbzUserRecs_admin');
	$oUA = $tU->SpawnItem();
	$oUA->Values($oU->Values());	// copy row data to admin object
	return $oUA;
    }
*/
    public function CurPageKey() {
	return 'user'; 
    }
    /*-----
      ACTION: Grab any expected input and interpret it
    */
    protected function ParseInput() {
	//$this->GetObjects();	// 2013-10-14 this should be obsolete now
	// TODO - there will be a user account management form

	$this->doAddEMail = !empty($_POST['btnAddEmail']);
	if ($this->doAddEMail) {
	    $this->sEmail = $_POST['uemail'];
	}
	$this->doGetAuth = !empty($_GET['auth']);
	if ($this->doGetAuth) {
	    $this->sAuth = $_GET['auth'];
	}
    }
    /*-----
      ACTION: Take the parsed input and do any needed processing (e.g. looking up data)
    */
    protected function HandleInput() {
	if (!$this->IsLoggedIn()) {
	    http_redirect(KWP_LOGIN);
	}
	if ($this->doAddEmail) {
	    $this->sTitle = 'Account: Send Email Authorization Link';
	} elseif ($this->doGetAuth) {
	    $this->sTitle = 'Account: Authorize Email Address';
	} else {
	    $this->sTitle = 'Account Management';
	}
    }
    /*-----
      ACTION: Render content footer -- anything that displays after main content on all pages of a given class
    */
    protected function DoContFtr() {
	parent::DoContFtr();
    	// not sure why parent::DoContFtr() isn't taking care of this:
	echo '</table></table></table></form>';
    }
    protected function PageNavKey() {
	return 'acc';
    }
    /*-----
      ACTION: Render main content -- stuff that changes
    */
    public function DoContent() {
	$tEmails = $this->EmailAuth();

	$isLoggedIn = $this->isLoggedIn;
	if ($this->doAddEMail) {
	    echo $tEmails->SendPassReset_forAddr($this->sEmail);
	    echo $this->Skin()->RenderTableHLine();
	} elseif ($this->doGetAuth) {
	    if ($isLoggedIn) {
		$ar = $tEmails->CheckAuth('The email address is being attached to your account.');	// check token
		//$ht = $ar['html'];
		//echo $ht;
		$ok = $ar['ok'];
		if ($ok) {
		    $sEmail = $ar['em_s'];
		    echo 'Now attaching user account(s) for email address "'.$sEmail.'"...';
		    $oUser = $this->App()->User();
		    $idUser = $oUser->KeyValue();
		    $ar = $this->AttachEmail($sEmail,$idUser);
		    echo $ar['html'];
		} else {
		    echo $ar['html'];	// display error message(s)
		}
	    } else {
		// TODO: display an error. User must stay logged in while authorizing email
	    }
	    echo $this->Skin()->RenderTableHLine();
	}
	// show current customer profiles
	echo '<center>'.$this->Render_Customers().'</center>';
	echo $this->Skin()->RenderTableHLine();

	// option to add more emails
	echo 'If you have previously placed orders using other email addresses to which you currently have access, you may claim ownership of those orders here.<br>';
	echo $this->Skin()->RenderForm_Email_RequestAdd(Nz($this->sEmail));
	// TODO
    }
    /*----
      ACTION: render HTML to show all customer profiles for this user
    */
    public function Render_Customers() {
	$id = $this->App()->User()->KeyValue();
	$tCust = $this->CustsTbl();
	$rc = $tCust->GetRecs_forUser($id);
	$ht = $rc->Render_asTable();
	return $ht;
    }
    protected function CustsTbl() {
	return $this->App()->Data()->Make('VbzAdminCusts');
    }
}