<?php
/*
  PURPOSE: Overrides vbz-app clases with their admin descendants.
  HISTORY:
    2016-03-07 Created in an attempt to straighten things out...
    2017-04-17 Removing vtLoggableAdminObject because the only content was SystemEventsClass() and I'm also removing that.
      We're going to go to the App object to retrieve the event log now.
*/
// ADMIN stuff which might not be loaded yet, so App will just assume it's there

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

    // -- CLASS NAMES -- //
    
}
