<?php
/*
  PURPOSE: shopping cart classes: shopping UI
  HISTORY:
    2016-03-07 Split off some methods from clsShopCart[s] (later renamed vcrShopCart/vctShopCarts)
*/
class vctCarts_ShopUI extends vctShopCarts {
    use ftFrameworkAccess;

    // ++ SETUP ++ //

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('vcrCart_ShopUI');
    }
    
    // -- SETUP -- //
    // ++ SHOPPING UI ++ //

    /*----
      ACTION: Check form input to see if anything needs to be done to the current Cart.
      HISTORY:
	2013-11-09 moved from clsShopCart to clsShopCarts
    */
    public function HandleCartFormInput() {
	$rcCart = $this->CartRecord_do_not_drop();	// get the current cart (create if absent)
	if (!is_object($rcCart)) {
	    throw new exception('Could not retrieve or create cart.');
	}
	$rcCart->HandleFormInput();
    }
    /*----
      HISTORY:
	2013-11-10 Significant change to assumptions. A cart object now only exists
	  to represent a cart record in the database. The cart table object now handles
	  situations where there is no cart record.
    */
    public function RenderCart($bEditable) {
	if ($this->CartIsRegistered()) {
	    $rcCart = $this->CartRecord_current();
	    $out = $rcCart->Render($bEditable);
	} else {
	throw new exception('Is this being called when items are first added?');
	    $out = "<font size=4>You have not put anything in your cart yet.</font>";
	    $sDesc = 'displaying cart - nothing yet; zone '.$this->ShipZoneObj()->Abbr();
	    $arEv = array(
	      clsSysEvents::ARG_CODE		=> '0cart',
	      clsSysEvents::ARG_DESCR_START	=> $sDesc,
	      clsSysEvents::ARG_WHERE		=> __FILE__.' line '.__LINE__,
	      );
	    $this->StartEvent($arEv);
	}
	return $out;
    }

    // -- SHOPPING UI -- //

}
class vcrCart_ShopUI extends vcrShopCart {

    // ++ INPUT ++ //

    // ACTION Do whatever needs to be done to the current cart based on the form input
    public function HandleFormInput() {
// check for input
	// - buttons
	$doAddItems	= array_key_exists(KSF_CART_BTN_ADD_ITEMS,$_POST);
	$doRecalc	= array_key_exists(KSF_CART_BTN_RECALC,$_POST);
	$doCheckout	= array_key_exists(KSF_CART_BTN_CKOUT,$_POST);
	// - other items
	$sShipZone	= fcArray::Nz($_POST,KSF_CART_SHIP_ZONE);
	$doShipZone	= !is_null($sShipZone);
	$sDelete	= fcArray::Nz($_GET,KSF_CART_DELETE);
	$doDelete	= !is_null($sDelete);
	
	$isCart = ($doRecalc || $doCheckout);	// there must already be a cart under these conditions
	$doItems = ($doAddItems || $doRecalc);	// if true, there are items to process
	$doRefresh = FALSE;	// should we refresh the page (clean URL / remove POST)?

	// receive selected shipping zone from form
	if ($doShipZone) {
	    if ($sShipZone != $this->ZoneCode()) {
		// if it has changed:
		$this->ZoneCode($sShipZone);	// set it in memory
		$this->UpdateZone();		// save change back to db
	    }
	}
	
	// check for specific actions
	if ($doItems) {
	    if ($isCart) {
		// zero out all items, so only items in visible cart will be retained:
		$this->ZeroAll();
	    }
	    // get the list of items posted
	    $arItems = $_POST[KSF_CART_ITEM_ARRAY_NAME];
	    // add each non-empty item
	    foreach ($arItems as $key => $val) {
		if (!empty($val)) {
		    $nVal = (int)0+$val;
		    $sqlCatNum = $this->Engine()->SafeParam($key);
		    $this->AddItem($sqlCatNum,$nVal);
		}
	    } // END for each item
	    $doRefresh = TRUE;
	// END do add items
	} elseif ($doDelete) {
	    if ($sDelete == KSF_CART_DELETE_ALL) {
		$this->LogEvent('clr','voiding cart');
		//$this->ID = -1;
		$this->SessionRecord()->DropCart();
	    } elseif (is_numeric($sDelete)) {
		$idLine = $sDelete;
		$this->DelLine($idLine);
		$idCart = $rcCart->GetKeyValue();
		$this->LogEvent('del',"deleting line ID $idLine");
	    } else {
		throw new exception('Received unexpected delete value "'.$sDelete.'".');
	    }
	    $doRefresh = TRUE;
	}
	
	if ($doCheckout) {
	    $this->LogCartEvent('ck1','going to checkout');
	    clsHTTP::Redirect(KWP_CKOUT);
	    $this->LogCartEvent('ck2','sent redirect to checkout');
	} elseif ($doRefresh) {
	    $this->UpdateTimestamp();	// record that the cart was updated
	    // if we changed anything, redirect to a clean URL with no POST data:
	    clsHTTP::Redirect(KWP_CART_REL);
	}
    }
    
    // -- INPUT -- //
    // ++ OUTPUT ++ //

    /*----
      DEPRECATED - confirmations are rendered by Order records, not Cart records
	If this *was* going to be used, it belongs with other web UI fx in a descendant class
	The base class here should be business logic only.
      HISTORY:
	2015-10-08 commented out

    static protected function RenderConfirm_footer() {
	$out .= <<<__END__
  <tr>
    <td colspan=2 align=center bgcolor=ffffff class=section-title>
      <input type=submit name="btn-go-prev" value="&lt;&lt; Make Changes">
      <input type=submit name="btn-go-order" value="Place the Order!">
    </td>
  </tr>
__END__;
	return $out;
    } */
    /*----
      DEPRECATED - confirmations are rendered by Order records, not Cart records
      ACTION: Render an order confirmation page
	This is the order as recorded by the CART, just before being converted to an Order.
	It should have navigation buttons and links to allow editing of the data.
      HISTORY:
	2014-10-07 Adapting from clsPageCkout::RenderConfirm()
	2015-10-08 commented out

    public function RenderConfirm_page() {
	$out = NULL;
	$rsCD = $this->FieldRecords();

	$isShipCard = $rsCD->IsShipToCard();
	//$isShipSelf = $rsCD->IsShipToSelf();
	$strCustShipMsg = $rsCD->ShipMsg(FALSE);		// customer message is optional
	$custCardNum = $rsCD->CardNumber();
	$custCardExp = $rsCD->CardExpiry();
	$isShipCardReally = $rsCD->IsShipToCard();
	// TODO: this should probably allow retrieval from stored records... but everything needs to be reorganized
	$sBuyerEmail = $rsCD->BuyerEmailAddress_entered(TRUE);	// email address is required
	$sBuyerPhone = $rsCD->BuyerPhoneNumber_entered(FALSE);	// phone # is optional
	if (is_null($sBuyerPhone)) {
	    $sBuyerPhone = '<i>none</i>';
	}

	$oPage = $this->Engine()->App()->Page();

	$htLink = $oPage->HtmlEditLink(KSQ_PAGE_CART);
	$out .=
	  "\n<tr><td class=section-title>ITEMS ORDERED:</td><td class=section-title align=right>$htLink</td></tr>"
	  ."\n<tr><td colspan=2>\n"
	  .$this->Render(FALSE)	// non-editable display of cart contents & totals
	  ."\n</td></tr>";

	$htLink = $oPage->HtmlEditLink(KSQ_PAGE_SHIP);
	$out .= "<tr><td class=section-title>SHIP TO:</td><td class=section-title align=right>$htLink</td></tr>";

	//$this->doFixedCard = TRUE;
	//$this->doFixedSelf = TRUE;		// 2015-09-01 nothing uses this anymore
	//$this->doFixedName = TRUE;
	//$this->htmlBeforeAddress = '';	// 2015-09-01 nothing uses this anymore
	//$this->htmlBeforeContact = '';	// 2015-09-01 nothing uses this anymore

	$out .= $rsCD->RecipFields()->RenderAddress(
	  array(
	    'do.ship.zone'	=> TRUE,
	    'do.fixed.all'	=> TRUE,
	    'do.fixed.name'	=> TRUE,
	    ),
	  $this->ShipZoneObj()
	  );

	$htLink = $oPage->HtmlEditLink(KSQ_PAGE_PAY);

	$out .= <<<__END__
<tr><td align=right valign=top>
  Special Instructions:<br>
  </td>
  <td>$strCustShipMsg</td>
  </tr>
<tr><td class=section-title>ORDERED BY:</td><td class=section-title align=right>$htLink</td></tr>
<tr><td align=right valign=middle>Email:</td><td>$sBuyerEmail</td></tr>
<tr><td align=right valign=middle>Phone:</td><td>$sBuyerPhone</td></tr>
<tr><td align=right valign=middle>Card Number:</td>
  <td><b>$custCardNum</b>
  - Expires: <b>$custCardExp</b>
  </td></tr>
__END__;
// if card address is different from shipping, then show it too:
// if not shipping to self, then show recipient's phone and/or email:
	if ($isShipCardReally) {
	    //$this->ShowForAddress('Credit card address <b>same as shipping address</b>');
	    $rsCD->BuyerFields()->ShowForAddress('Credit card address <b>same as shipping address</b>');
	}

	//if ($isShipSelf) {
	//    $this->strInsteadOfCont = 'Recipient contact information <b>same as buyer\'s -- shipping to self</b>';
	//}

	// TODO 2012-05-21: this probably won't look right, and will need fixing
	//	also, are strInsteadOf* strings ^ used in confirmation?
	$out .= $rsCD->BuyerFields()->RenderAddress(
	  array(
	    'do.ship.zone'	=> TRUE,
	    'do.fixed.all'	=> TRUE,
	    'do.fixed.name'	=> TRUE,
	    ),
	  $this->ShipZoneObj()
	  );

	//$sPgName = KSQ_ARG_PAGE_DATA;
	//$sPgShow = $this->PageKey_forShow();

	$out .= $this->RenderConfirm_footer();
	$out .= "\n<!-- -".__METHOD__."() in ".__FILE__." -->\n";

	return $out;
    } */
    /*----
      FUNCTION: DisplayObject()
      CALLED BY this.Render() (below)
    */
    private $oPainter;
    protected function DisplayObject($bEditable) {
	if (empty($this->oPainter)) {
	    $oZone = $this->ShipZoneObject();
	    if ($bEditable) {
		$oPainter = new cCartDisplay_full_shop($oZone);
	    } else {
		$oPainter = new cCartDisplay_full_ckout($oZone);
	    }
	    $rsLine = $this->LineRecords();
	    while ($rsLine->NextRow()) {
		if ($bEditable) {
		    $oLine = $rsLine->GetRenderObject_editable();
		} else {
		    $oLine = $rsLine->GetRenderObject_static();
		}
		$oPainter->AddLine($oLine);
	    }
	    $this->oPainter = $oPainter;
	}
	return $this->oPainter;
    }
    /*----
      RETURNS: HTML rendering of cart, including current contents and form controls
      CALLED BY table.RenderCart()
      HISTORY:
	2013-11-10 Significant change to assumptions: A cart object now only exists
	  to represent a cart record in the database. Any functions that need to work
	  when there is no record are now handled by the cart table object.
    */
    public function Render($bEditable) {
	$id = $this->GetKeyValue();
	$this->Engine()->App()->Page()->Skin()->AddFooterStat('cart ID',$id);
	if ($this->HasLines()) {
	    $oPainter = $this->DisplayObject($bEditable);
	    $out = $oPainter->Render();
	} else {
	    $out = "<font size=4>Your cart is empty.</font> (cart ID=$id)";
	    $arEv = array(
	      clsSysEvents::ARG_CODE		=> 'disp',
	      clsSysEvents::ARG_DESCR_START	=> 'displaying cart - empty; zone '.$this->ShipZoneObj()->Abbr(),
	      clsSysEvents::ARG_WHERE		=> __FILE__.' line '.__LINE__,
	      );
	}
	return $out;
    }
    
    // -- OUTPUT -- //

}