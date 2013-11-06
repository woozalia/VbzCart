<?php
/*
  PURPOSE: constants for shopping-cart code
    Avoid using; refer to class functions instead where possible.
  HISTORY:
    2013-09-13 split off from cart-data.php
  USED BY:
    cart.php uses KSF_SHIP_ZONE
    cart-data.php uses all of them (I think) especially in clsCartVars
      and VbzAdminCartDatum
    admin.cart.php uses a lot but not all
*/

// FORM FIELD NAMES:
// -- cart/shipping
define('KSF_SHIP_ZONE'		,'ship-zone');
// -- shipping
//define('KSF_SHIP_TO_SELF'	,'ship-to-self');	// TRUE = use shipping info for recipient too
define('KSF_SHIP_IS_CARD'	,'ship-is-billing');	// TRUE = shipping address same as billing/card
define('KSF_SHIP_MESSAGE'	,'ship-message');
// address fields: prefix with "ship" or "card" as appropriate
define('_KSF_ADDR_NAME'		,'addr-name');
define('_KSF_ADDR_STREET'	,'addr-street');
define('_KSF_ADDR_CITY'		,'addr-city');
define('_KSF_ADDR_STATE'	,'addr-state');
define('_KSF_ADDR_ZIP'		,'addr-zip');
define('_KSF_ADDR_COUNTRY'	,'addr-country');
define('_KSF_ADDR_EMAIL'	,'addr-email');
define('_KSF_ADDR_PHONE'	,'addr-phone');

// for retrieving field data:
define('KSF_PFX_SHIP',		'ship-');
define('KSF_ADDR_SHIP_NAME'	,KSF_PFX_SHIP._KSF_ADDR_NAME);
define('KSF_ADDR_SHIP_STREET'	,KSF_PFX_SHIP._KSF_ADDR_STREET);
define('KSF_ADDR_SHIP_CITY'	,KSF_PFX_SHIP._KSF_ADDR_CITY);
define('KSF_ADDR_SHIP_STATE'	,KSF_PFX_SHIP._KSF_ADDR_STATE);
define('KSF_ADDR_SHIP_ZIP'	,KSF_PFX_SHIP._KSF_ADDR_ZIP);
define('KSF_ADDR_SHIP_COUNTRY'	,KSF_PFX_SHIP._KSF_ADDR_COUNTRY);
define('KSF_CUST_SHIP_EMAIL'	,KSF_PFX_SHIP._KSF_ADDR_EMAIL);
define('KSF_CUST_SHIP_PHONE'	,KSF_PFX_SHIP._KSF_ADDR_PHONE);

// -- payment type
define('KSF_PTYP_CARD_HERE'	,'pay-card-here');

// -- payment
define('KSF_CUST_CARD_NUM'	,'cust-card-num');
define('KSF_CUST_CARD_ENCR'	,'cust-card-encr');	// encrypted card info - same as cust_cards.Encrypted
define('KSF_CUST_CARD_EXP'	,'cust-card-exp');
define('KSF_CUST_CARD_NAME'	,'cust-card-name');
define('KSF_CUST_CARD_STREET'	,'cust-card-street');
define('KSF_CUST_CARD_CITY'	,'cust-card-city');
define('KSF_CUST_CARD_STATE'	,'cust-card-state');
define('KSF_CUST_CARD_ZIP'	,'cust-card-zip');
define('KSF_CUST_CARD_COUNTRY'	,'cust-card-country');
define('KSF_CUST_CHECK_NUM'	,'cust-check-num');
define('KSF_CUST_PAY_EMAIL'	,'cust-pay-email');
define('KSF_CUST_PAY_PHONE'	,'cust-pay-phone');

// DATA TYPE NUMBERS
define('KSI_SHIP_ZONE'		,100);

define('KSI_ADDR_SHIP_NAME'	,101);
define('KSI_ADDR_SHIP_STREET'	,102);
define('KSI_ADDR_SHIP_CITY'	,103);
define('KSI_ADDR_SHIP_STATE'	,104);
define('KSI_ADDR_SHIP_ZIP'	,105);
define('KSI_ADDR_SHIP_COUNTRY'	,106);
define('KSI_SHIP_MESSAGE'	,107);
define('KSI_SHIP_TO_SELF'	,110);
define('KSI_SHIP_IS_CARD'	,113);

define('KSI_CUST_SHIP_EMAIL'	,111);
define('KSI_CUST_SHIP_PHONE'	,112);
//define('KSI_SHIP_MISSING'	,120);
// -- payment
define('KSI_CUST_CARD_NUM'	,202);
define('KSI_CUST_CARD_EXP'	,203);

// -- buyer contact info
define('KSI_ADDR_CARD_NAME'	,204);
define('KSI_CUST_CARD_NAME'	,KSI_ADDR_CARD_NAME);	// alias
define('KSI_ADDR_CARD_STREET'	,205);
define('KSI_CUST_CARD_STREET'	,KSI_ADDR_CARD_STREET);	// alias
define('KSI_ADDR_CARD_CITY'	,206);
define('KSI_CUST_CARD_CITY'	,KSI_ADDR_CARD_CITY);
define('KSI_ADDR_CARD_STATE'	,207);
define('KSI_CUST_CARD_STATE'	,KSI_ADDR_CARD_STATE);
define('KSI_ADDR_CARD_ZIP'	,208);
define('KSI_CUST_CARD_ZIP'	,KSI_ADDR_CARD_ZIP);
define('KSI_ADDR_CARD_COUNTRY'	,209);
define('KSI_CUST_CARD_COUNTRY'	,KSI_ADDR_CARD_COUNTRY);

define('KSI_CUST_PAY_EMAIL'	,211);
define('KSI_CUST_PAY_PHONE'	,212);
//define('KSI_CUST_MISSING'	,220);
define('KSI_CUST_CHECK_NUM'	,230);

// calculated data
define('KSI_ITEM_TOTAL'		,301);
define('KSI_PER_ITEM_TOTAL'	,302);
define('KSI_PER_PKG_TOTAL'	,303);
