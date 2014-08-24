<?php
/*
  PURPOSE: constants for checkout process
  HISTORY:
    2013-09-13 split off from cart-data.php
    2013-11-07 reorganizing/renaming cart data constants
    2013-11-10 renamed from cart-const.php to vbz-const-ckout.php
  USED BY:
    cart.php uses KSF_CART_RECIP_SHIP_ZONE
    cart-data.php uses all of them (I think)
      especially in clsCartVars and VbzAdminCartDatum
    admin.cart.php uses a lot but not all
*/

/* ============================================
 SECTION: field keys for retrieving checkout form data
  These define the HTML names of form controls
    and are also used to retrieve the user-entered values
    from the submitted data.
*/

define('KSF_SHIP_IS_CARD'	,'ship-is-billing');	// TRUE = shipping address same as billing/card
define('KSF_SHIP_MESSAGE'	,'ship-message');

// == common prefixes

define('_KSF_CART_SFX_CONT_ID'		,'cont-id');	// contact ID
define('_KSF_CART_SFX_CONT_INTYPE'	,'addr-input');	// input type: new (entered) vs. old (selected from profile)
define('_KSF_CART_SFX_CONT_CHOICE'	,'choice-id');
define('_KSF_CART_SFX_CONT_NAME'	,'addr-name');
define('_KSF_CART_SFX_CONT_STREET'	,'addr-street');
define('_KSF_CART_SFX_CONT_CITY'	,'addr-city');
define('_KSF_CART_SFX_CONT_STATE'	,'addr-state');
define('_KSF_CART_SFX_CONT_ZIP'	,'addr-zip');
define('_KSF_CART_SFX_CONT_COUNTRY'	,'addr-country');
define('_KSF_CART_SFX_CONT_EMAIL'	,'addr-email');
define('_KSF_CART_SFX_CONT_PHONE'	,'addr-phone');

// == buyer

define('_KSF_CART_PFX_BUYER'	,'buyer-');			// common prefix for buyer fields
define('KSF_CART_BUYER_ID'	,_KSF_CART_PFX_BUYER._KSF_CART_SFX_CONT_ID);	// person profile for buyer
define('KSF_CART_BUYER_EMAIL'	,_KSF_CART_PFX_BUYER.'email');
define('KSF_CART_BUYER_PHONE'	,_KSF_CART_PFX_BUYER.'phone');

// == payments
define('_KSF_CART_PFX_PAY'		,'pay-');			// common prefix for payment types

// == payment card

define('_KSF_CART_PFX_PAY_CARD'	,_KSF_CART_PFX_PAY.'card-');				// common prefix for payment-by-card fields
define('KSF_CART_PAY_CARD_INTYPE'	,_KSF_CART_PFX_PAY_CARD._KSF_CART_SFX_CONT_INTYPE);
define('KSF_CART_PAY_CARD_CHOICE'	,_KSF_CART_PFX_PAY_CARD._KSF_CART_SFX_CONT_CHOICE);
define('KSF_CART_PAY_CARD_NAME'	,_KSF_CART_PFX_PAY_CARD._KSF_CART_SFX_CONT_NAME);	// cardholder name
// - account info
define('KSF_CART_PAY_CARD_NUM'		,_KSF_CART_PFX_PAY_CARD.'num');	// card account number
define('KSF_CART_PAY_CARD_ENCR'	,_KSF_CART_PFX_PAY_CARD.'encr');	// encrypted card info - same as cust_cards.Encrypted
define('KSF_CART_PAY_CARD_EXP'		,_KSF_CART_PFX_PAY_CARD.'exp');
// - verification
define('KSF_CART_PAY_CARD_STREET'	,_KSF_CART_PFX_PAY_CARD.'street');
define('KSF_CART_PAY_CARD_CITY'	,_KSF_CART_PFX_PAY_CARD.'city');
define('KSF_CART_PAY_CARD_STATE'	,_KSF_CART_PFX_PAY_CARD.'state');
define('KSF_CART_PAY_CARD_ZIP'		,_KSF_CART_PFX_PAY_CARD.'zip');
define('KSF_CART_PAY_CARD_COUNTRY'	,_KSF_CART_PFX_PAY_CARD.'country');

// == payment check info

define('KSF_CART_PAY_CHECK_NUM'	,_KSF_CART_PFX_PAY.'check-num');

// == recipient

define('_KSF_CART_PFX_RECIP'		,'recip-');			// common prefix for recipient fields
define('KSF_CART_RECIP_SHIP_ZONE'	,_KSF_CART_PFX_RECIP.'ship-zone');
define('KSF_CART_RECIP_CONT_INTYPE'	,_KSF_CART_PFX_RECIP._KSF_CART_SFX_CONT_INTYPE);
define('KSF_CART_RECIP_CONT_CHOICE'	,_KSF_CART_PFX_RECIP._KSF_CART_SFX_CONT_CHOICE);
define('KSF_CART_RECIP_ID'		,_KSF_CART_PFX_RECIP._KSF_CART_SFX_CONT_ID);	// person profile for shipping
define('KSF_CART_RECIP_NAME'		,_KSF_CART_PFX_RECIP._KSF_CART_SFX_CONT_NAME);
define('KSF_CART_RECIP_STREET'		,_KSF_CART_PFX_RECIP._KSF_CART_SFX_CONT_STREET);
define('KSF_CART_RECIP_CITY'		,_KSF_CART_PFX_RECIP._KSF_CART_SFX_CONT_CITY);
define('KSF_CART_RECIP_STATE'		,_KSF_CART_PFX_RECIP._KSF_CART_SFX_CONT_STATE);
define('KSF_CART_RECIP_ZIP'		,_KSF_CART_PFX_RECIP._KSF_CART_SFX_CONT_ZIP);
define('KSF_CART_RECIP_COUNTRY'	,_KSF_CART_PFX_RECIP._KSF_CART_SFX_CONT_COUNTRY);
define('KSF_CART_RECIP_EMAIL'		,_KSF_CART_PFX_RECIP._KSF_CART_SFX_CONT_EMAIL);
define('KSF_CART_RECIP_PHONE'		,_KSF_CART_PFX_RECIP._KSF_CART_SFX_CONT_PHONE);
define('KSF_CART_RECIP_IS_BUYER'	,_KSF_CART_PFX_RECIP.'is-buyer');
// -- payment type
define('KSF_CART_PTYP_CARD_HERE'	,_KSF_CART_PFX_PAY.'card-here');

// =====================================
// keys for retrieving stored field data
//

// == recipient

define('KI_CART_SHIP_ZONE'	,100);
define('KI_CART_RECIP_ID'	,151);	// added 2013-11-07
define('KI_CART_RECIP_INTYPE'	,152);	// added 2014-07-29 (INTYPE = input type)
define('KI_CART_RECIP_CHOICE'	,153);	// added 2014-08-21 (drop-down choice)
define('KI_CART_RECIP_NAME'	,101);
define('KI_CART_RECIP_STREET'	,102);
define('KI_CART_RECIP_CITY'	,103);
define('KI_CART_RECIP_STATE'	,104);
define('KI_CART_RECIP_ZIP'	,105);
define('KI_CART_RECIP_COUNTRY'	,106);
define('KI_CART_RECIP_MESSAGE'	,107);
define('KI_CART_RECIP_IS_BUYER',110);
define('KI_CART_SHIP_IS_CARD'	,113);	// does this still make sense?

define('KI_CART_RECIP_EMAIL'	,111);
define('KI_CART_RECIP_PHONE'	,112);

// == buyer

define('KI_CART_BUYER_ID'	,251);	// added 2013-11-07
define('KI_CART_BUYER_INTYPE'	,252);	// added 2014-07-29
define('KI_CART_BUYER_CHOICE'	,253);	// added 2014-08-22
define('KI_CART_BUYER_EMAIL'	,211);
define('KI_CART_BUYER_PHONE'	,212);

// == payment card

define('KI_CART_PAY_CARD_NAME'		,204);	// 2013-11-07: for now, this is assumed to be the buyer's name. Later, we should differentiate.
define('KI_CART_PAY_CARD_NUM'		,202);
define('KI_CART_PAY_CARD_EXP'		,203);
define('KI_CART_PAY_CARD_ENCR'		,252);	// added 2014-02-13
define('KI_CART_PAY_CARD_STREET'	,205);
define('KI_CART_PAY_CARD_CITY'		,206);
define('KI_CART_PAY_CARD_STATE'	,207);
define('KI_CART_PAY_CARD_ZIP'		,208);
define('KI_CART_PAY_CARD_COUNTRY'	,209);

// == payment check

define('KI_CART_PAY_CHECK_NUM'	,230);

// calculated data
define('KI_CART_CALC_SALE_TOTAL'	,301);	// A. item price total
define('KI_CART_CALC_PER_ITEM_TOTAL'	,302);	// B. per-item shipping total
define('KI_CART_CALC_PER_PKG_TOTAL'	,303);	// C. max per-package shipping total
define('KI_CART_CALC_SHIP_TOTAL'	,304);	// D. shipping total (B+C)
define('KI_CART_CALC_FINAL_TOTAL'	,305);	// E. final total (A+D)

