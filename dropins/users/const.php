<?php
/*
  PURPOSE: Constants for permissions
    These need to match whatever is defined in the KS_TABLE_USER_PERMISSION table.
  HISTORY:
    2014-01-05 created
*/

// uncomment this when setting up user permissions for the first time
//define('ID_USER_ROOT',1);	// user with this ID has all permissions
define('ID_GROUP_USERS',2);	// group to which all users automatically belong

define('KS_PERM_SEC_PERM_EDIT','sec.perm.edit');
define('KS_PERM_SEC_GROUP_EDIT','sec.group.edit');
define('KS_PERM_SEC_USER_EDIT','sec.user.edit');
define('KS_PERM_SEC_PERM_VIEW','sec.perm.view');
define('KS_PERM_SEC_GROUP_VIEW','sec.group.view');
define('KS_PERM_SEC_USER_VIEW','sec.user.view');
define('KS_PERM_SITE_VIEW_CONFIG','site.view.config');
define('KS_PERM_RSTK_VIEW','rstk.view');
define('KS_PERM_RSTK_EDIT','rstk.edit');
define('KS_PERM_ORDER_ADMIN','order.admin');
define('KS_PERM_CART_ADMIN','cart.admin');
define('KS_PERM_SHIP_ADMIN','ship.admin');
define('KS_PERM_LCAT_ADMIN','lcat.admin');
define('KS_PERM_CUST_ADMIN','cust.admin');
define('KS_PERM_STOCK_VIEW','stock.view');
define('KS_PERM_STOCK_ADMIN','stock.admin');
define('KS_PERM_SYSLOG_ADMIN','syslog.admin');
