<?php
/*
  PURPOSE: app, kiosk, & page classes for administration stuff
    This will probably just be one abstract base class for now.
  HISTORY:
    2013-09-16 created page/vbz-page-admin.php
    2013-12-01 the inheritance structure here probably needs to be reorganized
      Should probably be renamed something like "clsVbzPage_sober"
    2016-11-18 gutted because of Page class redesign; now adds nothing
    2016-03-07 Created app/vbz-app-admin.php in an attempt to straighten things out...
    2016-12-16 Repurposed vbz-page-admin.php, under the page structure redesign, to actually be what its name implies.
    2017-04-17 Removing vtLoggableAdminObject because the only content was SystemEventsClass() and I'm also removing that.
      We're going to go to the App object to retrieve the event log now.
*/

// user security permissions
// - permissions for security admin - these need to go in Ferreteria somewhere
define('KS_PERM_SEC_PERM_EDIT','fe.sec.user.perm.edit');
define('KS_PERM_SEC_GROUP_EDIT','fe.user.group.edit');

define('KS_PERM_RAW_DATA_EDIT','fe.data.admin');	// edit fields that might wreck (or restore) data integrity
define('KS_PERM_EVENTS_VIEW','fe.event.view');		// (needs rename) use event data viewing tools
define('KS_PERM_EVENTS_EDIT','syslog.admin');		// (needs rename) modify event data
define('KS_PERM_SITE_VIEW_CONFIG','site.view.config');
define('KS_PERM_RSTK_VIEW','rstk.view');
define('KS_PERM_RSTK_EDIT','rstk.edit');
define('KS_PERM_ORDER_PROCESS','order.process');	// normal handling of orders
define('KS_PERM_CART_ADMIN','cart.admin');
define('KS_PERM_SHIP_ADMIN','ship.admin');
define('KS_PERM_LCAT_ADMIN','lcat.admin');
define('KS_PERM_SCAT_ADMIN','scat.admin');
define('KS_PERM_CUST_ADMIN','cust.admin');
define('KS_PERM_STOCK_VIEW','stock.view');
define('KS_PERM_STOCK_ADMIN','stock.admin');
define('KS_PERM_SYSLOG_ADMIN','syslog.admin');
// PERMISSIONS
// 2017-03-25 These probably belong somewhere else. The plan now is for them to be auto-generated...
define('KS_USER_SEC_ACCT_EDIT','fe.user.acct.edit');		// can modify user accounts
define('KS_USER_SEC_PERM_VIEW','fe.user.perm.view');		// can view available permits
define('KS_USER_SEC_GROUP_VIEW','fe.user.group.view');		// can view user groups
//define('KS_USER_ACCT_PERM_EDIT','fe.user.acct.perm.edit');
define('KS_PERM_USER_CONN_DATA','fe.user.conn.view');		// can view user connection data (browser ID, IP address)
define('KS_PERM_SEC_USER_VIEW','fe.user.acct.view');		// can view all user accounts

// -- features
define('KS_FEATURE_USER_SECURITY','user.security');
define('KS_FEATURE_USER_ACCOUNT_ADMIN','user.admin.acct');
define('KS_FEATURE_USER_SECURITY_ADMIN','user.security.admin');
define('KS_FEATURE_USER_SESSION_ADMIN','user.admin.sess');

// -- events
define('KS_EVENT_VBZCART_CASCADE_UPDATE','vc.cascade');		// data change which may result in a cascade of changes

class vcMenuKiosk_admin extends fcMenuKiosk_admin {
    public function GetBasePath() {
	return vcGlobals::Me()->GetWebPath_forAdminPages();
    }
}

class vcAppAdmin extends fcAppStandardAdmin {
    use vtApp;

    // ++ CLASS NAMES ++ //
    
    // OVERRIDE
    protected function GetSessionsClass() {
	if (fcDropInManager::Me()->HasModule('ferreteria.users')) {
	    return KS_CLASS_ADMIN_USER_SESSIONS;	// admin features
	} else {
	    return parent::GetSessionsClass();		// basic logic
	}
    }
    protected function GetPageClass() {
	return 'vcPageAdmin';
    }
    protected function GetKioskClass() {
	return 'vcMenuKiosk_admin';
    }

    // -- CLASS NAMES -- //
    
}

class vcPageHeader_admin extends fcContentHeader_login {
    public function __construct() {
	// setup defaults
	$this->SetTitleString('No title set!');	// 2017-01-28 not sure if this actually does anything now
    }
}
class vcNavbar_admin extends fcMenuFolder {

    // ++ CEMENTING ++ //
    
    protected function OnCreateElements() {
	fcDropInManager::ScanDropins(vcGlobals::Me()->GetFilePath_forDropins(),$this);	// add all the dropins as subnodes
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
    }
    protected function Class_forTagHTML() : string {
	return 'vcTag_html_admin';
    }

    // -- CEMENTING -- //

}