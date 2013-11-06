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

define('KS_MSG_SITEWIDE_TOP',
  '<table width=100% id="site-message" style="background: #660000; border: solid red 2px;"><tr><td align=center>'
  .'<span style="color: white; font-weight: bold;">VBZ.NET IS CURRENTLY DOWN - most of the catalog works, '
  .'but the checkout process is <a  style="color: yellow;" href="http://vbz.net/wiki/User:Woozle/blog/2013/10/06/1339/Updates_and_such">being rebuilt</a>.'
  .'<br>I apologize for the nuisance, and am <a style="color: yellow;" href="https://plus.google.com/u/0/communities/112143136769960982581">working</a> to fix it.</span></td></tr></table>');

define('KS_STORE_NAME','vbz.net');
define('KS_STORE_NAME_META',KS_STORE_NAME.' online retail');
define('KS_STORE_DOMAIN','vbz.net');		// root domain of store - used for cookies
define('KS_TOOLS_SERVER','vbz.net');	// domain or IP of server where tools are kept
define('KWP_TOOLS','/tools');
define('KWP_TOOLS_DTREE',KWP_TOOLS.'/aux/dtree');
define('KWP_LOGO_HEADER',KWP_TOOLS.'/img/logos/v/');
define('KS_PAGE_SERVER','vbz.net');	// domain or IP of server where pages are kept

define('KWP_STORE_SECURE','https://'.KS_PAGE_SERVER.'/');
define('KWP_CKOUT',KWP_STORE_SECURE.'checkout/');
define('KWP_LOGIN',KWP_STORE_SECURE.'login/');
define('KWP_UACCT',KWP_STORE_SECURE.'account/');
define('KWP_LOGOUT',KWP_LOGIN.'?exit');

define('KS_FMT_TOPICID','%04u');			// default format for topic numbers in URLs
define('KS_EMAIL_ADMIN','woozle@hypertwins.org');	// admin email -- for internal use only (don't show to customers)
define('KS_SHIP_ZONE_DEFAULT','US');

// wiki embedding (optional)
define('KF_USE_WIKI',false);	// use wiki embedding?
if (KF_USE_WIKI) {
    define('KFP_WIKI',KFP_HOST_ACCT.'wiki.vbz.net/');	// path to wiki files
}
// wiki linking (needed for help pages and embedded text):
define('KWP_WIKI','/wiki/');
//define('KWP_HELP',KWP_WIKI);	// what is this for?
define('KWP_HELP_ROOT',KWP_WIKI);
define('KWP_HELP_HOME',KWP_HELP_ROOT.'help');	// help: main index
define('KWP_HELP_ABOUT',KWP_HELP_ROOT.'about');	// help: about the store
define('KWP_HELP_CONTACT',KWP_HELP_ROOT.'contact');	// help: contact us
define('KWP_HELP_POLICY_SHIP',KWP_HELP_ROOT.'Shipping_Policies');	// help: shipping policies
define('KWP_HELP_NO_STOCK_BUT_AVAIL',KWP_HELP_ROOT.'Available_but_not_in_stock');
define('KWP_TPLT_EMAIL_ORD_RECEIPT',KWP_HELP_ROOT.'Help:Embedded/email/order_receipt');

//define('KWP_IMG_MERCH','http://img.vbz.net/titles/');
//define('KWP_SECURE','https://ssl.vbz.net/');	// is anything using this? it's wrong now. 2010-10-19
//define('KWP_ADMIN',KWP_SECURE.'admin/');

define('KWP_ROOT','http://'.KS_PAGE_SERVER);
define('KWP_HOME_REL','/');	// change this if store is not in the root folder
define('KWP_HOME_ABS',KWP_ROOT.KWP_HOME_REL);	// this defines the home folder for the vbzcart installation
define('KWP_CAT_REL',KWP_HOME_REL.'cat/');
define('KWP_CAT',KWP_ROOT.KWP_CAT_REL);
define('KWP_CART_FROM_HOME','cart/');
define('KWP_CART_REL',KWP_HOME_REL.KWP_CART_FROM_HOME);
define('KWP_CART_ABS',KWP_HOME_ABS.KWP_CART_FROM_HOME);
define('KWP_SHOP_SUPP',KWP_CAT_REL);		// list of suppliers and summaries of what they have
define('KWP_SHOP_STOCK',KWP_HOME_REL.'stock/');	// what's in stock
define('KWP_SHOP_TOPICS',KWP_HOME_REL.'topic/');
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
define('KS_URL_PAGE_SESSION',	'sess');
define('KS_URL_PAGE_ORDER',	'ord');	// must be consistent with events already logged
define('KS_URL_PAGE_ORDERS',	'orders');

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
/* no longer used in <body> tag:
bgcolor=000044"
TEXT=CCFFFF"
LINK=33FF33"
VLINK=33CCFF"
ALINK=FFCC00"
*/
);
// shopping cart
//define('KHT_CART_MSG','Please <a href="http://wiki.vbz.net/Contact">let me know</a> if you have any questions.');
define('KHT_CART_FTR', <<<__END__
<tr><td colspan=9><input type=submit name=recalc value="Recalculate">
<input type=submit name=finish value="Check Out >>>" class=btn-next>
</form></tr></td></tr>
<tr><td colspan=9>
<hr>Please <a href="http://wiki.vbz.net/Contact">let me know</a> if you have any questions.
</td></tr></table><td></tr></table>
__END__
);
/*
define('KHT_CART_MSG', <<<__END__
(2010-06-12) <b>The checkout system currently does not send out a notification email when the order is completed.</b>
<a href="http://wiki.vbz.net/To_do">Working on this</a>.
If you do place an order, please <a href="http://wiki.vbz.net/Contact">send me a message</a> to let me know.
Sorry for the inconvenience!
__END__
); */
// order receipt
define('KF_RCPT_TIMESTAMP','F j, Y - g:i a');	// January 1, 2000 - 3:00 pm
define('KHT_RCPT_TPLT', <<<__END__
\{{\}}\<center><font size=5>Order # <b>{{ord.num}}</b></font>
<br><a href="{{url.shop}}">VBZ.NET</a>
<br>{{timestamp}}
</center><hr>
<table>
<tr><td><h3>ITEMS ORDERED:</h3></td></tr>
<tr><td colspan=2><table>{{cart.detail}}</table></td></tr>
<tr>
<td valign=top>
<h3>SHIP TO:</h3>
<b>{{ship.name}}</b><br>
{{ship.addr}}
</td>
<td valign=top>
<h3>PAYMENT:</h3>
<b>{{pay.name}}</b><br>
{{pay.spec}}
</td>
</tr>
</table>
<small>Cart ID: {{cart.id}} - Session ID: {{sess.id}}</small>
<hr>
<center>http://vbz.net - 122 Pinecrest Rd. - Durham, NC 27705 - {{email.short}}<br><br>
Please print or save this for your records.</center>
__END__
);
// error email
define('KS_TEXT_EMAIL_ADDR_ERROR','vbz-error@hypertwins.org');
// template syntax specs
define('KS_TPLT_OPEN','{{');
define('KS_TPLT_SHUT','}}');
// confirmation emails
define('KS_EMAIL_DOMAIN',		'vbz.net');
define('KS_TPLT_EMAIL_SUBJECT', 	'ORDER #{{ord-num}} at '.KS_EMAIL_DOMAIN);
define('KS_TPLT_EMAIL_ADDR_CUST',	'{{cust-name}} <{{cust-email}}>');
define('KS_TPLT_EMAIL_ADDR_SELF', 	KS_STORE_NAME.' order system <order-{{ord-num}}@'.KS_EMAIL_DOMAIN.'>');
define('KS_TPLT_EMAIL_ADDR_ADMIN',	KS_STORE_NAME.' admin system <admin-{{tag}}@'.KS_EMAIL_DOMAIN.'>');
define('KS_TPLT_EMAIL_MSG_TOP', <<<__END__
This is an automatic confirmation email from vbz.net
to let you know that we have received your order.

If you have any questions, please contact us:
    http://wiki.vbz.net/Contact

ORDER #{{ord-num}}
__END__
);

/*----
  RETURNS: secure (https) URL corresponding to the current insecure URL
*/
function SecureURL() {
    return 'https://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
}