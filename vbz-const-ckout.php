<?php
/*
  PURPOSE: constants for checkout process
  HISTORY:
    2013-09-13 split off from cart-data.php
    2013-11-07 reorganizing/renaming cart data constants
    2013-11-10 renamed from cart-const.php to vbz-const-ckout.php
  USED BY:
    cart.php uses KSF_SHIP_ZONE
    cart-data.php uses all of them (I think)
      especially in clsCartVars and VbzAdminCartDatum
    admin.cart.php uses a lot but not all
*/

// ============================================
// field keys for retrieving checkout form data
//

define('KSF_SHIP_IS_CARD'	,'ship-is-billing');	// TRUE = shipping address same as billing/card
define('KSF_SHIP_MESSAGE'	,'ship-message');

// == common prefixes

define('_KSF_CONT_ID'		,'cont-id');	// contact ID
define('_KSF_CONT_NAME'		,'addr-name');
define('_KSF_CONT_STREET'	,'addr-street');
define('_KSF_CONT_CITY'		,'addr-city');
define('_KSF_CONT_STATE'	,'addr-state');
define('_KSF_CONT_ZIP'		,'addr-zip');
define('_KSF_CONT_COUNTRY'	,'addr-country');
define('_KSF_CONT_EMAIL'	,'addr-email');
define('_KSF_CONT_PHONE'	,'addr-phone');

// == buyer

define('KSF_PFX_BUYER'		,'buyer-');			// common prefix for buyer fields
define('KSF_BUYER_ID'		,KSF_PFX_BUYER._KSF_CONT_ID);	// person profile for buyer
define('KSF_BUYER_EMAIL'	,KSF_PFX_BUYER.'email');
define('KSF_BUYER_PHONE'	,KSF_PFX_BUYER.'phone');

// == payments
define('KSF_PFX_PAY'		,'pay-');			// common prefix for payment types

// == payment card

define('KSF_PFX_PAY_CARD'	,KSF_PFX_PAY.'card-');		// common prefix for payment-by-card fields
define('KSF_PAY_CARD_NAME'	,KSF_PFX_PAY_CARD.'name');	// cardholder name
// - account info
define('KSF_PAY_CARD_NUM'	,KSF_PFX_PAY_CARD.'num');	// card account number
define('KSF_PAY_CARD_ENCR'	,KSF_PFX_PAY_CARD.'encr');	// encrypted card info - same as cust_cards.Encrypted
define('KSF_PAY_CARD_EXP'	,KSF_PFX_PAY_CARD.'exp');
// - verification
define('KSF_PAY_CARD_STREET'	,KSF_PFX_PAY_CARD.'street');
define('KSF_PAY_CARD_CITY'	,KSF_PFX_PAY_CARD.'city');
define('KSF_PAY_CARD_STATE'	,KSF_PFX_PAY_CARD.'state');
define('KSF_PAY_CARD_ZIP'	,KSF_PFX_PAY_CARD.'zip');
define('KSF_PAY_CARD_COUNTRY'	,KSF_PFX_PAY_CARD.'country');

// == payment check info

define('KSF_PAY_CHECK_NUM'	,KSF_PFX_PAY.'check-num');

// == recipient

define('KSF_SHIP_ZONE'		,'ship-zone');
define('KSF_PFX_RECIP'		,'recip-');			// common prefix for recipient fields
define('KSF_RECIP_ID'		,KSF_PFX_RECIP._KSF_CONT_ID);	// person profile for shipping
define('KSF_RECIP_NAME'		,KSF_PFX_RECIP._KSF_CONT_NAME);
define('KSF_RECIP_STREET'	,KSF_PFX_RECIP._KSF_CONT_STREET);
define('KSF_RECIP_CITY'		,KSF_PFX_RECIP._KSF_CONT_CITY);
define('KSF_RECIP_STATE'	,KSF_PFX_RECIP._KSF_CONT_STATE);
define('KSF_RECIP_ZIP'		,KSF_PFX_RECIP._KSF_CONT_ZIP);
define('KSF_RECIP_COUNTRY'	,KSF_PFX_RECIP._KSF_CONT_COUNTRY);
define('KSF_RECIP_EMAIL'	,KSF_PFX_RECIP._KSF_CONT_EMAIL);
define('KSF_RECIP_PHONE'	,KSF_PFX_RECIP._KSF_CONT_PHONE);
define('KSF_RECIP_IS_BUYER'	,KSF_PFX_RECIP.'is-buyer');
// -- payment type
define('KSF_PTYP_CARD_HERE'	,'pay-card-here');

// =====================================
// keys for retrieving stored field data
//

// == recipient

define('KI_SHIP_ZONE'		,100);
define('KI_RECIP_ID'		,151);	// added 2013-11-07
define('KI_RECIP_NAME'		,101);
define('KI_RECIP_STREET'	,102);
define('KI_RECIP_CITY'		,103);
define('KI_RECIP_STATE'		,104);
define('KI_RECIP_ZIP'		,105);
define('KI_RECIP_COUNTRY'	,106);
define('KI_RECIP_MESSAGE'	,107);
define('KI_RECIP_IS_BUYER'	,110);
define('KSI_SHIP_IS_CARD'	,113);	// does this still make sense?

define('KI_RECIP_EMAIL'		,111);
define('KI_RECIP_PHONE'		,112);

// == buyer

define('KI_BUYER_ID'		,251);	// added 2013-11-07
define('KI_BUYER_EMAIL'		,211);
define('KI_BUYER_PHONE'		,212);

// == payment card

define('KI_PAY_CARD_NAME'	,204);	// 2013-11-07: for now, this is assumed to be the buyer's name. Later, we should differentiate.
define('KI_PAY_CARD_NUM'	,202);
define('KI_PAY_CARD_EXP'	,203);
define('KI_PAY_CARD_STREET'	,205);
define('KI_PAY_CARD_CITY'	,206);
define('KI_PAY_CARD_STATE'	,207);
define('KI_PAY_CARD_ZIP'	,208);
define('KI_PAY_CARD_COUNTRY'	,209);

// == payment check

define('KI_PAY_CHECK_NUM'	,230);

// calculated data
define('KI_CALC_SALE_TOTAL'	,301);
define('KI_CALC_PER_ITEM_TOTAL',302);
define('KI_CALC_PER_PKG_TOTAL'	,303);
