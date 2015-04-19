<?php
/*
FILE: site.php
PURPOSE: VbzCart settings for the web site
FUTURE: This should be split apart into site-related and preferences-related pieces
HISTORY:
  2009-10-06 Now using proper syntax for "define()"
  2009-10-14 KWP_WIKI needs to be defined even if KF_USE_WIKI is FALSE
  2010-06-12 KWP_TOPICS_REL
  2010-11-16 KWP_IMG_MERCH is now looked up in cat_folders
  2012-05-10 KWP_TOOLS
  2012-12-13 moved database credentials to site-creds.php
*/
require_once('site-creds.php');

define('KS_MSG_SITEWIDE_TOP', <<<__END__

<table width=100% id="site-message" style="background: #660000; border: solid red 2px;">
  <tr>
    <td align=center>
      <span style="color: white; font-weight: bold;">VBZ.NET IS CURRENTLY DOWN - most of the catalog works,
        but the checkout process is <a  style="color: yellow;" href="http://vbz.net/wiki/User:Woozle/blog/2013/10/06/1339/Updates_and_such">being rebuilt</a>.
        <br>I apologize for the nuisance, and am <a style="color: yellow;" href="https://plus.google.com/u/0/communities/112143136769960982581">working</a> to fix it.
      </span>
    </td>
  </tr>
</table>
__END__
);

define('KS_SITE_NAME','vbz.net');
define('KS_SITE_SHORT','VBZ');		// shortest version of site name
define('KS_SITE_NAME_META',KS_SITE_NAME.' online retail');
define('KS_SITE_DOMAIN','vbz.net');		// root domain of store - used for cookies
define('KS_TOOLS_SERVER','vbz.net');	// domain or IP of server where tools are kept
define('KWP_TOOLS','/tools');
define('KWP_TOOLS_DTREE',KWP_TOOLS.'/aux/dtree');
define('KWP_LOGO_HEADER',KWP_TOOLS.'/img/logos/v/');
define('KS_PAGE_SERVER','vbz.net');	// domain or IP of server where pages are kept

define('KS_USER_SESSION_KEY','vbzcart-session');	// name of cookie for storing session key

// file locations
define('KFP_LIB_VBZ',KFP_LIB.'/VbzCart');		// VbzCart library files
define('KFP_VBZ_DROPINS',KFP_LIB_VBZ.'/dropins');	// drop-in module folder
define('KFN_DROPIN_INDEX','index.vbz.php');		// name for drop-in index
define('KFP_CONFIG',KFP_DATA.'/config/vbzcart');

// icons
define('KWP_ICON_ALERT'		,'/tools/img/icons/button-red-X.20px.png');
define('KWP_ICON_WARN'		,'/tools/img/icons/button-red-X.20px.png');	// TODO: separate warning icon
define('KWP_ICON_OKAY'		,'/tools/img/icons/chkbox.gif');

define('KS_CHAR_PATH_SEP','/');	// path separator
define('KS_CHAR_URL_ASSIGN',':');	// path argument assignment operator
define('KS_FMT_TOPICID','%04u');			// default format for topic numbers in URLs
define('KS_EMAIL_ADMIN','woozle@hypertwins.org');	// admin email -- for internal use only (don't show to customers)
define('KS_SHIP_ZONE_DEFAULT','US');

// ==== URLS FOR PAGES

define('KWP_STORE_SECURE','https://'.KS_PAGE_SERVER.'/');
define('KWP_CKOUT',KWP_STORE_SECURE.'checkout/');
define('KWP_LOGIN',KWP_STORE_SECURE.'login/');
define('KWP_UADMIN',KWP_STORE_SECURE.'admin/');	// URL for user admin functions
define('KWP_EMAIL',KWP_STORE_SECURE.'email/');
define('KWP_LOGOUT',KWP_LOGIN.'?exit');

// wiki linking (needed for help pages and embedded text):
define('KWP_WIKI_PUBLIC',KWP_STORE_SECURE.'wiki/');
define('KWP_WIKI_PRIVATE','/corp/wiki/');
define('KWP_HELP_ROOT',KWP_WIKI_PUBLIC);
define('KWP_HELP_HOME',KWP_HELP_ROOT.'help');	// help: main index
define('KWP_HELP_ABOUT',KWP_HELP_ROOT.'help:about');	// help: about the store
define('KWP_HELP_CONTACT',KWP_HELP_ROOT.'help:contact');	// help: contact us
define('KWP_HELP_POLICY_SHIP',KWP_HELP_ROOT.'Shipping_Policies');	// help: shipping policies
define('KWP_HELP_NO_STOCK_BUT_AVAIL',KWP_HELP_ROOT.'Available_but_not_in_stock');
define('KWP_TPLT_EMAIL_ORD_RECEIPT',KWP_HELP_ROOT.'Help:Embedded/email/order_receipt');

// technical documentation links
define('KWP_TECHDOC_HOME','http://htyp.org/VbzCart');
define('KWP_TECHDOC_PREFIX',KWP_TECHDOC_HOME.'/');
define('KWP_TECHDOC_PREFIX_TABLES',KWP_TECHDOC_PREFIX.'tables/');
define('KWP_TECHDOC_PREFIX_PROCS',KWP_TECHDOC_PREFIX.'procs/');
define('KWP_TECHDOC_PREFIX_TERMS',KWP_TECHDOC_PREFIX.'ui/terms/');

//define('KWP_IMG_MERCH','http://img.vbz.net/titles/');

define('KWP_ROOT','http://'.KS_PAGE_SERVER);
define('KWP_HOME_REL','/');	// change this if store is not in the root folder
define('KWP_HOME_ABS',KWP_ROOT.KWP_HOME_REL);	// this defines the home folder for the vbzcart installation
define('KWP_CAT_REL',KWP_HOME_REL.'cat/');
define('KWP_CAT',KWP_ROOT.KWP_CAT_REL);
define('KWP_CART_FROM_HOME','cart/');
define('KWP_CART_REL',KWP_HOME_REL.KWP_CART_FROM_HOME);
define('KWP_CART_ABS',KWP_HOME_ABS.KWP_CART_FROM_HOME);
// may need to force cart links to include specific (sub)domain if server sometimes uses alternate (e.g. www.*)
//	otherwise cookies may get stranded on the wrong domain
# define('KWP_CART',KWP_CART_ABS);		// absolute cart URL
define('KWP_CART',KWP_CART_REL);		// relative cart URL
define('KWP_SHOP_SUPP',KWP_CAT_REL);		// list of suppliers and summaries of what they have
define('KWP_SHOP_SEARCH',KWP_HOME_REL.'search/');	// search page
define('KWP_SHOP_STOCK',KWP_HOME_REL.'stock/');	// what's-in-stock page
define('KWP_SHOP_TOPICS',KWP_HOME_REL.'topic/');	// topics page
define('KWP_TOPICS_REL',KWP_SHOP_TOPICS);	// alternate -- deprecated
define('KS_SMALL_LOGO_ALT','V');		// alt text for small logo

define('KWP_CKOUT_IF_NO_CART',KWP_ROOT);	// where to go from checkout if guest has no cart set

define('KFP_KEYS',KFP_DATA.'/keys');		// where encryption keys are kept

define('KWT_DOC_ROOT','VbzCart');
define('KWT_DOC_TRX_TYPES',KWT_DOC_ROOT.'/transaction/types');

define('KC_ORD_NUM_PFX','I');		// order number prefix for current batch of orders
define('KS_ORD_NUM_FMT','%05u');	// format for converting order sequence to a string
define('KC_ORD_NUM_SORT',NULL);		// sorting index for current batch of orders

// TABLE ACTION KEYS
define('KS_PAGE_KEY_SESSION',	'sess');
define('KS_PAGE_KEY_CUST',	'cust');
define('KS_PAGE_KEY_ADDR',	'addr');
// TABLE NAMES
define('KS_TABLE_USER_ACCOUNT',	'user');
define('KS_TABLE_USER_GROUP',		'ugroup');
define('KS_TABLE_USER_PERMISSION',	'uperm');
define('KS_TABLE_UACCT_X_UGROUP',	'user_x_ugroup');
define('KS_TABLE_UGROUP_X_UPERM',	'ugroup_x_uperm');

/*
 BITS OF TEXT shown to customers
*/
// skin for shopping pages
//define('KHT_PAGE_DOCTYPE','<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">');
define('KHT_PAGE_DOCTYPE','<!DOCTYPE HTML>');
// "\[$" defines "[$" as opening mark for variables, "\$]" defines closing mark as "$]":
define('KHT_PAGE_STYLE','\[$\$]\<link rel="StyleSheet" href="/tools/styles/[$sheet$].css">');
define('KHT_PAGE_BODY_TAG', <<<__END__

<body
 TOPMARGIN=0
 LEFTMARGIN=0
 MARGINWIDTH=0
 MARGINHEIGHT=0
>
__END__
);

// template syntax specs
define('KS_TPLT_OPEN','{{');
define('KS_TPLT_SHUT','}}');
// automatic email constants
// -- general use
define('KS_EMAIL_DOMAIN',		'vbz.net');
define('KS_TPLT_EMAIL_ADDR_ADMIN',	KS_SITE_NAME.' admin system <admin-{{tag}}@'.KS_EMAIL_DOMAIN.'>');
define('KS_TEXT_EMAIL_ADDR_ERROR',	'vbz-error@hypertwins.org');
define('KS_TEXT_EMAIL_NAME_ERROR',	KS_SITE_SHORT.' site administrator');
// -- order confirmations
define('KS_TVAR_CUST_NAME',	'cust-name');
define('KS_TVAR_CUST_EMAIL',	'cust-email');
define('KS_TVAR_ORDER_NUMBER',	'ord-num');
define('KS_TPLT_ORDER_EMAIL_SUBJECT', 		'ORDER #{{'.KS_TVAR_ORDER_NUMBER.'}} at '.KS_EMAIL_DOMAIN);
define('KS_TPLT_ORDER_EMAIL_ADDR_CUST',	'{{'.KS_TVAR_CUST_NAME.'}} <{{'.KS_TVAR_CUST_EMAIL.'}}>');
define('KS_TPLT_ORDER_EMAIL_ADDR_SELF', 	KS_SITE_NAME.' order system <order-{{'.KS_TVAR_ORDER_NUMBER.'}}@'.KS_EMAIL_DOMAIN.'>');
define('KS_TPLT_ORDER_EMAIL_MSG_TOP', 		<<<__END__
This is an automatic confirmation email from vbz.net
to let you know that we have received your order.

If you have any questions, you may respond to this email
  or contact us by any of several methods listed here:
__END__
  ."\n".KWP_HELP_CONTACT."\n\nORDER #{{ord-num}}"
);
// -- password change authorization emails
define('KS_TEXT_AUTH_EMAIL_SUBJ',KS_SITE_NAME.' password reset authorization');
define('KS_TPLT_AUTH_EMAIL_TEXT',<<<__END__
Someone (hopefully you) has made a request to change the password for this email address ({{addr}}).

If you would like to {{action}}, please click the following link (i.e. load it in your web browser):

	  {{url}}
__END__
);
define('KS_TPLT_AUTH_EMAIL_WEB',<<<__END__
A link has been emailed to you at <b>{{addr}}</b>.<br>
Clicking the link will {{action}}.
__END__
);

/*----
  RETURNS: secure (https) URL corresponding to the current insecure URL
*/
function SecureURL() {
    return 'https://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
}