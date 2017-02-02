<?php
/*
  PURPOSE: page classes for administration stuff
    i.e. pages that should look sensible and safe
    This will probably just be one abstract base class for now.
  HISTORY:
    2013-09-16 created
    2013-12-01 the inheritance structure here probably needs to be reorganized
      Should probably be renamed something like "clsVbzPage_sober"
    2016-11-18 gutted because of Page class redesign; now adds nothing
    2016-12-16 Repurposed, under the page structure redesign, to actually be what its name implies.
*/


class vcPageHeader_admin extends fcContentHeader_login {
    public function __construct() {
	// setup defaults
	$this->SetTitleString('No title set!');	// 2017-01-28 not sure if this actually does anything now
    }
}
class vcNavbar_admin extends fcMenuFolder {

    // ++ CEMENTING ++ //
    
    protected function OnCreateElements() {
	fcDropInManager::ScanDropins(KFP_VBZ_DROPINS,$this);	// add all the dropins as subnodes
    }
    protected function RenderNodesBlock() {
	return "\n<ul class=menu>"
	  .$this->RenderNodes()
	  ."\n</ul>"
	  ;
    }
    protected function RenderSelf() {
	return NULL;
    }

      //++dropin menu++//
    
    protected function MenuHome_new() {
	throw new exception('2016-12-24 This really should not be in use anymore.');
	// the home node needs a name (e.g. 'HOME'), else it will be triggered by the base URL
	$omHome = new fcMenuLink($this->MenuRoot(),'0','Admin Home','User Control Panel');
	  $omHome->URL($this->BaseURL(),TRUE);	// gets rid of "page:", but there's probably a better way
	  $omHome->NeedPermission(NULL);
	fcDropInManager::ScanDropins(KFP_VBZ_DROPINS,$omHome);
	// tell App to un-cache existing Session object, so that next request will pull up dropin:
	$this->AppObject()->SessionTable()->ClearSession();

	return $omHome;
    }
    protected function MenuPainter_new() {
	throw new exception('2016-12-24 This really should not be in use anymore.');
	$op = new fcMenuPainter_UL('/');
	$op->BaseURL($this->GetBasePath());
	return $op;
    }

      //--dropin menu--//
    // -- CEMENTING -- //
    // ++ CALLBACKS ++ //
    
    //private $oPath;
    protected function PathArgObject() {
	throw new exception('PathArgObject() has been replaced by the Kiosk object.');
    
    /* 2017-01-01 no longer needed
	if (empty($this->oPath)) {
	    // get path fragument (current URL's path relative to base):
	    $wp = $this->GetPathFragument();
	    if (strlen($wp) > 1) {
		//$wp = '/'.trim($wp,'/');
		$arPath = fcURL::ParsePath($wp);
	    } else {
		$arPath = array();	// no path
	    }
	    $this->oPath = new $arPath);
	}
	return $this->oPath; */
    }

    // -- CALLBACKS -- //
    
}
class vcPageContent_admin extends vcPageContent {

    // ++ EVENTS ++ //

    /*----
      CEMENT
      NOTE: We *could* move stuff here from vcPageAdmin::OnRunCalculations(), but for now it's done there.
    */
    protected function OnRunCalculations() {
	//$this->AddString('This is a test.');
    }

    // -- EVENTS -- //
}
class vcTag_body_admin extends vcTag_body {

    // ++ EVENTS ++ //
    
    // OVERRIDE: Navbar needs to render before header and content.
    protected function OnCreateElements() {
    	$this->GetElement_PageNavigation();
	$this->GetElement_PageHeader();
	$this->GetElement_LoginWidget();
	$this->GetElement_PageContent();
    }
    protected function OnRunCalculations(){
    }
    
    // -- EVENTS -- //
    // ++ CEMENTING ++ //

      //++classes++//
    
    protected function Class_forPageHeader() {
	return 'vcPageHeader_admin';
    }
    protected function Class_forPageNavigation() {
	return 'vcNavbar_admin';
    }
    protected function Class_forPageContent() {
	return 'vcPageContent_admin';
    }
    
      //--classes--//

    // -- CEMENTING -- //

}
class vcTag_html_admin extends vcTag_html {

    // ++ CEMENTING ++ //

    protected function Class_forTag_body() {
	return 'vcTag_body_admin';
    }

    // -- CEMENTING -- //
}

class vcPageAdmin extends vcPage {

    // ++ CEMENTING ++ //

    // 2017-01-01 I *think* I'm making much of this redundant now; see Kiosk classes
    protected function OnRunCalculations() {
	$this->UseStyleSheet('admin');
	$this->SetPageTitle(KS_SITE_SHORT.' Control Panel');	// default page title
/* 2017-01-30 widget does this now
	$oKiosk = fcApp::Me()->GetKioskObject();
	$oReq = $oKiosk->GetInputObject();
	
	// check for any special commands (just "login" at this point):
	if ($oReq->KeyExists('do')) {
	    if ($oReq->GetString('do') == 'login') {
		$this->GetElement_LoginWidget()->SetInput_ShowLoginForm();
	    }
	}
*/	
    }
    protected function Class_forTagHTML() {
	return 'vcTag_html_admin';
    }

    // -- CEMENTING -- //
    // ++ EMAIL ++ //
    
    /*----
      PUBLIC so other elements can use it (e.g. login widget)
      TODO:
	* should be template-based, for i18n
	* should include links for notifying us and changing password
    */
    public function SendEmail_forLoginSuccess() {
	// for now, we'll always send email; later, this should probably be a user preference
	$rcUser = fcApp::Me()->GetUserRecord();

	$sUser = $rcUser->UserName();
	$sSiteName = KS_SITE_NAME;
	$sAddress = $_SERVER['REMOTE_ADDR'];
	$sBrowser = $_SERVER['HTTP_USER_AGENT'];

	$sMsg = <<<__END__
Someone, presumably you, just logged in to $sSiteName with username "$sUser". Please make sure this was actually you.
* IP address: $sAddress
* Browser: $sBrowser

If it wasn't you, please let us know, and change your password.
__END__;
	$sToAddr = $rcUser->EmailAddress();
	$sToName = $rcUser->FullName();
	$sSubj = 'login notification from '.KS_SITE_NAME;
	$ok = fcApp::Me()->DoEmail_fromAdmin_Auto($sToAddr,$sToName,$sSubj,$sMsg);
	return $ok;
    }
    
    // -- EMAIL -- //

}