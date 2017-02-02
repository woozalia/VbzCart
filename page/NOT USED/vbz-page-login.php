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
    2015-08-28 vbz-const-user.php no longer needed; constants are defined in ferreteria/page.php
*/

class clsVbzPageLogin extends vcPageAdmin_Acct {
    protected function BaseURL() {
	return KURL_LOGIN;
    }
    protected function ParseInput() {
	$this->ParseInput_Login();
    }
    protected function HandleInput() {
	$this->HandleInput_Login();
	$this->HandleInput_RedirUsers();
    }
    protected function PreSkinBuild() {
	$ht = $this->                                                       RenderUserAccess();
	$this->GetSkinObject()->Content('login',$ht);
    }
    protected function HandleWrapper() {
	$oSkin = $this->GetSkinObject();
	$oSkin->PieceAdd('cont.hdr','<table class="form-block-login"><tr><td><form method=post action="'.$this->BaseURL().'">');
	$oSkin->PieceAdd('cont.ftr','</form></td></tr></table>');
    }
    /*----
      ACTION: If logged in, redirect to admin page
      TODO: look for a ?redir= parameter so we can
	redirect to other pages
    */
    protected function HandleInput_RedirUsers() {
	if ($this->IsLoggedIn()) {
	    clsHTTP::Redirect(KURL_UADMIN);
	}
    }
}