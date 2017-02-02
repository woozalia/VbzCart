<?php
/*
  PURPOSE: Overrides vbz-app clases with their admin descendants.
  HISTORY:
    2016-03-07 Created in an attempt to straighten things out...
*/
// ADMIN stuff which might not be loaded yet, so App will just assume it's there


// -- features
define('KS_FEATURE_USER_SECURITY','user.security');
define('KS_FEATURE_USER_ACCOUNT_ADMIN','user.admin.acct');
define('KS_FEATURE_USER_SECURITY_ADMIN','user.security.admin');
define('KS_FEATURE_USER_SESSION_ADMIN','user.admin.sess');

class vcMenuKiosk_admin extends fcMenuKiosk_admin {

    public function GetBasePath() {
	return KWP_PAGE_BASE;	// index.php for each Page type should define this
    }
//TODO
}

class vcAppAdmin extends vcApp {

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
    /*
    protected function GetDropinClass() {
	return 'fcaDropInModule';
    }
    */

    // -- CLASS NAMES -- //
    
}
trait vtLoggableAdminObject {
    // REQUIRED BY ftLoggableRecord
    protected function SystemEventsClass() {
	return KS_CLASS_EVENT_LOG;
    }
}