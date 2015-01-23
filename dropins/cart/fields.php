<?php
/*
  HISTORY:
    2014-01-20 extracted from cart.php
    2014-01-22 renamed from data.php to fields.php
*/
// SHOPPING CART DATA
class VCT_CartFields_admin extends clsCartVars {
    public function __construct($iDB) {
      parent::__construct($iDB);
	//$this->Name('shop_cart_data');
	$this->ClassSng('VCR_CartField_admin');
    }

    // ++ CLASS NAMES ++ //
    protected function CartsClass() {
	return KS_CLASS_ADMIN_CARTS;
    }
    // -- CLASS NAMES -- //
    // ++ TABLE ACCESS ++ //

    protected function CartTable($id=NULL) {
	return $this->Engine()->Make($this->CartsClass(),$id);
    }

    // -- TABLE ACCESS -- //
    // ++ ADMIN UI ++ //

    public function Table_forCart($iCart) {
	$oPage = $this->Engine()->App()->Page();
	$out = NULL;

	if ($oPage->ReqArgBool('btnSaveField')) {
	    $idEdit = $oPage->ReqArg('edit-field');
	    $txtVal = $oPage->ReqArg('val');
	    $rcCart = $this->CartTable($iCart);
	    $rcField = $rcCart->GetItem($idEdit);
	    $sField = $rcField->Value();
	    $htDescr = '['.$idEdit.']: ['.$sField.'] &rarr; ['.$txtVal.']';
	    $sqlDescr = '['.$idEdit.']: ['.$sField.'] => ['.$txtVal.']';

	    $out .= 'Updating '.$htDescr;
	    $objCart->StartEvent(__METHOD__,'ED-D','admin edited '.$sqlDescr);
	    $rcField->UpdateValue($txtVal);
	    $objCart->FinishEvent();

	    $idEdit = NULL;	// field saved -- not editing now
	} else {
	    $idEdit = $oPage->PathArg('edit-field');
	}

	$objRecs = $this->GetData('ID_Cart='.$iCart);
	if ($objRecs->HasRows()) {
	    $arLink = array(
	      'page'	=> 'cart',
	      'id'	=> $iCart
	      );
	    if (!empty($idEdit)) {
//		$out .= '<form action="'.$vgOut->SelfURL($arLink).'">';
		$out = '<form method=post>';
	    }
	    $out .= "\n<table class=listing>\n<tr><th>Type</th><th>Value</th></tr>";
	    $isOdd = TRUE;
	    while ($objRecs->NextRow()) {
		$ftStyle = $isOdd?'odd':'even';
		$isOdd = !$isOdd;

		$out .= $objRecs->AdminLine($idEdit,$ftStyle);
	    }
	    if (!empty($idEdit)) {
		$out .= '</form>';
	    }
	    $out .= "\n</table>";
	} else {
	    $out = "\n<i>No data in cart</i>";
	}
	return $out;
    }

    // -- ADMIN UI -- //
}
class VCR_CartField_admin extends clsCartVar {
    // display-strings for field-types
    static $arTypeNames = array (
      KI_CART_SHIP_ZONE		=> 'ship zone',
     // KI_CART_RECIP_ID		=> 'ship-to ID',
      KI_CART_RECIP_INTYPE	=> 'ship-to input type',
      KI_CART_RECIP_CHOICE	=> 'ship-to address ID',
      KI_CART_RECIP_NAME	=> 'ship-to name',
      KI_CART_RECIP_STREET	=> 'ship-to street',
      KI_CART_RECIP_CITY	=> 'ship-to city',
      KI_CART_RECIP_STATE	=> 'ship-to state',
      KI_CART_RECIP_ZIP		=> 'ship-to zipcode',
      KI_CART_RECIP_COUNTRY	=> 'ship-to country',
      KI_CART_RECIP_MESSAGE	=> 'ship-to message',
      KI_CART_RECIP_IS_BUYER	=> 'ship to self?',
      KI_CART_SHIP_IS_CARD	=> 'ship to = card?',
      KI_CART_RECIP_EMAIL	=> 'ship-to email',
      KI_CART_RECIP_PHONE	=> 'ship-to phone',
// -- payment
      KI_CART_PAY_CARD_INTYPE	=> 'card input type',
      KI_CART_PAY_CARD_CHOICE	=> 'card ID',
      KI_CART_PAY_CARD_NUM	=> 'card number',
      KI_CART_PAY_CARD_EXP	=> 'card expiry',
      KI_CART_PAY_CARD_NAME	=> 'card owner',
      KI_CART_PAY_CARD_STREET	=> 'card street address',
      KI_CART_PAY_CARD_CITY	=> 'card address city',
      KI_CART_PAY_CARD_STATE	=> 'card address state',
      KI_CART_PAY_CARD_ZIP	=> 'card zipcode',
      KI_CART_PAY_CARD_COUNTRY	=> 'card country',
      KI_CART_PAY_CHECK_NUM	=> 'check number',
      KI_CART_BUYER_EMAIL	=> 'customer email',
      KI_CART_BUYER_PHONE	=> 'customer phone',

      KI_CART_CALC_SALE_TOTAL	=> 'item total',
      KI_CART_CALC_PER_ITEM_TOTAL	=> 's/h per-item total',
      KI_CART_CALC_PER_PKG_TOTAL	=> 's/h package total',
    );
    // ++ BOILERPLATE: admin link stuff ++ //

    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
	return clsMenuData_helper::_AdminLink($this,$iText,$iPopup,$iarArgs);
    }
    public function AdminRedirect(array $iarArgs=NULL) {
	return clsMenuData_helper::_AdminRedirect($this,$iarArgs);
    }
    public function AdminName() {
	return $this->Value('Number');
    }

    // -- BOILERPLATE -- //
    // ++ FIELD ACCESS ++ //

    protected function ValueText() {
	$strType = $this->TypeID();
	$ar = self::$arTypeNames;
	if (isset($ar[$strType])) {
	    $wtType = $ar[$strType];
	} else {
	    $wtType = "<b>?</b>$strType";
	}
	return $wtType;
    }

    // -- FIELD ACCESS -- //
    // ++ ACTIONS ++ //

    public function UpdateValue($sNew) {
	$arUpd = array('Val'=>SQLValue($sNew));
	return $this->Update($arUpd);
    }

    // -- ACTIONS -- //
    // ++ ADMIN UI ++ //

    public function AdminLine($idEdit,$cssClass) {
	$txtType = $this->ValueText();
	$idType = $this->Value('ID_Type');
	$htType = htmlspecialchars($txtType);

	// TODO: the link setup will need fixing
	$arLink['edit-field'] = $idType;
	$ftType = $this->AdminLink($htType,'edit &ldquo;'.$htType.'&rdquo;',$arLink);

	$ftVal = htmlspecialchars($this->Value('Val'));

	$doEdit = ($idType == $idEdit);
	if ($doEdit) {
	    $ftTypeCtrl = '<b>'.$ftType.'</b><br><input type=submit name=btnSaveField value="Save">';
	    $ftValCtrl = '<input type=hidden name=edit-field value="'.$idType.'">'
	      .'<textarea name=val width=30 height=2>'.$ftVal.'</textarea>';
	} else {
	    $ftTypeCtrl = $ftType;
	    $ftValCtrl = $ftVal;
	}
	$out = "\n<tr class=\"$cssClass\"><td>$ftTypeCtrl</td><td>$ftValCtrl</td></tr>";
	return $out;
    }

    // -- ADMIN UI -- //
}
