<?php
/*
  PURPOSE: VbzCart drop-in descriptor for user access management
  HISTORY:
    2013-12-18 started
*/

// CONSTANTS

require('const.php');	// constants to match defined permissions

// ACTIONS

define('KS_ACTION_USER_ACCOUNT',	'usr');
define('KS_ACTION_USER_GROUP',		'grp');
define('KS_ACTION_USER_PERMISSION',	'perm');
define('KS_ACTION_USER_SESSION',	'sess');
define('KS_ACTION_USER_CLIENT',	'cli');

// CLASS NAMES

define('KS_CLASS_ADMIN_USER_ACCOUNTS',		'VCM_UserAccts');
define('KS_CLASS_ADMIN_USER_ACCOUNT',		'VC_UserAcct');
define('KS_CLASS_ADMIN_USER_GROUPS',		'VCM_UserGroups');
define('KS_CLASS_ADMIN_USER_GROUP',		'VC_UserGroup');
define('KS_CLASS_ADMIN_USER_PERMISSIONS',	'VCM_UserPerms');
define('KS_CLASS_ADMIN_USER_PERMISSION',	'VC_UserPerm');
define('KS_CLASS_ADMIN_UACCT_X_UGROUP',	'clsUAcct_x_UGroup_admin');

define('KS_CLASS_ADMIN_USER_SESSIONS',		'VCT_AdminSessions');
define('KS_CLASS_ADMIN_USER_CLIENTS',		'VCT_UserClients');

// FEATURES

define('KS_FEATURE_USER_SECURITY','user.security');
define('KS_FEATURE_USER_ACCOUNT_ADMIN','user.admin.acct');
define('KS_FEATURE_USER_SECURITY_ADMIN','user.security.admin');
define('KS_FEATURE_USER_SESSION_ADMIN','user.admin.sess');

// MENU ADDITIONS

$om = new clsMenuFolder(NULL, '*users','User Permissions','User Access Management','user/group security management');
  //$om->NeedPermission(KS_PERM_SEC_USER_VIEW);
  $om->NeedPermission(NULL);
  $omi = new clsMenuLink($om,KS_ACTION_USER_ACCOUNT,'Users','User Accounts','user account management');
    $omi->Controller(KS_CLASS_ADMIN_USER_ACCOUNTS);
    //$omi->NeedPermission(KS_PERM_SEC_USER_VIEW);
    $omi->NeedPermission(NULL);
  $omi = new clsMenuLink($om,KS_ACTION_USER_GROUP,'Groups','Security Groups','security groups to which users can belong');
    $omi->Controller(KS_CLASS_ADMIN_USER_GROUPS);
    $omi->NeedPermission(KS_PERM_SEC_GROUP_VIEW);
  $omi = new clsMenuLink($om,KS_ACTION_USER_PERMISSION,'Perms','Security Permissions','security permissions assignable to groups');
    $omi->Controller(KS_CLASS_ADMIN_USER_PERMISSIONS);
    $omi->NeedPermission(KS_PERM_SEC_PERM_VIEW);
  $omi = new clsMenuLink($om,KS_ACTION_USER_SESSION,'Sess','User Sessions','user session records');
    $omi->Controller(KS_CLASS_ADMIN_USER_SESSIONS);
    $omi->NeedPermission(KS_PERM_SEC_GROUP_VIEW);
  $omi = new clsMenuLink($om,KS_ACTION_USER_CLIENT,'Clients','User Clients','user web client records');
    $omi->Controller(KS_CLASS_ADMIN_USER_CLIENTS);
    $omi->NeedPermission(KS_PERM_SEC_GROUP_VIEW);

// MODULE SPEC ARRAY

$arDropin = array(
  'name'	=> 'vbz.users',
  'descr'	=> 'user/group security management',
  'version'	=> '0.0',
  'date'	=> '2014-09-18',
  'URL'		=> NULL,	// nothing yet
  'classes'	=> array(	// list of files and the classes they contain
    'uacct.php'			=> array(KS_CLASS_ADMIN_USER_ACCOUNTS),
    'ugroup.php'		=> array(KS_CLASS_ADMIN_USER_GROUPS,KS_CLASS_ADMIN_USER_GROUP),
    'uperm.php'			=> array(KS_CLASS_ADMIN_USER_PERMISSIONS,KS_CLASS_ADMIN_USER_PERMISSION),
    'uacct-x-ugroup.php'	=> array(KS_CLASS_ADMIN_UACCT_X_UGROUP),

    'usess.php'			=> array(KS_CLASS_ADMIN_USER_SESSIONS),
    'uclient.php'		=> array(KS_CLASS_ADMIN_USER_CLIENTS),
     ),
  'menu'	=> $om,
  'requires'	=> array('vbz.syslog'),	// other drop-ins used by this drop-in
  'features'	=> array(
    KS_FEATURE_USER_SECURITY,
    KS_FEATURE_USER_ACCOUNT_ADMIN,
    KS_FEATURE_USER_SECURITY_ADMIN,
    KS_FEATURE_USER_SESSION_ADMIN
    ),
  );
