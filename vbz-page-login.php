<?php
/*
  PURPOSE: page class for handling logins
    This is mainly so we have some appropriately-named place
      to send people for logging in.
  TODO: Sort this out. Does it make sense for this to descend from
    the full-blown Admin page? Should the /login and /admin pages
    maybe be the same class, but with the user redirected to the
    appropriate URL depending on whether they are logged in?
  HISTORY:
    2013-12-01 created
*/
require_once('vbz-const-user.php');

class clsVbzPageLogin extends clsPageAdmin_Acct {
    protected function BaseURL() {
	return KWP_LOGIN;
    }
    protected function ParseInput() {
	$this->ParseInput_Login();
    }
    protected function HandleInput() {
	$this->HandleInput_Login();
	$this->HandleInput_RedirUsers();
    }
    protected function PreSkinBuild() {
	//$this->Skin()->PageTitle('User log-in');
	$this->Skin()->PageTitle($this->TitleString());
//	$this->sTitle = 'User log-in';	// this should be set in HandleInput()
	$ht = $this->RenderUserAccess();
	$this->Skin()->Content('login',$ht);
    }
    protected function HandleWrapper() {
	$this->Skin()->PieceAdd('cont.hdr','<table class="form-block-login"><tr><td><form method=post action="'.$this->BaseURL().'">');
	$this->Skin()->PieceAdd('cont.ftr','</form></td></tr></table>');
    }
/* instantiated in parent class
    protected function PostSkinBuild() {
	$this->HandleWrapper();		// add more stuff to the content wrapper
    }
    */
    /*----
      ACTION: If logged in, redirect to admin page
      TODO: look for a ?redir= parameter so we can
	redirect to other pages
    */
    protected function HandleInput_RedirUsers() {
	if ($this->IsLoggedIn()) {
	    clsHTTP::Redirect(KWP_UADMIN);
	}
    }
}