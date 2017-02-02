<?php
/*
  HISTORY:
    2014-01-20 extracted from cart.php
*/

class VCT_CartLines_admin extends vctShopCartLines {
    use ftLinkableTable;

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('VCR_CartLine_admin');
	  $this->ActionKey(KS_PAGE_KEY_CART_LINE);
    }
    public function Table_forCart($idCart) {
	$rs = $this->GetData('ID_Cart='.$idCart,NULL,'Seq');
	if ($rs->HasRows()) {
	    $out = <<<__END__
<table class=listing>
  <tr>
    <th>ID</th>
    <th>#</th>
    <th>Item</th>
    <th>Qty</th>
    <th>Added</th>
    <th>Changed</th>
  </tr>
__END__;
	    $isOdd = TRUE;
	    while ($rs->NextRow()) {
		$wtStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
		$isOdd = !$isOdd;

		$id = $rs->GetKeyValue();
		$wtID = $rs->SelfLink();
		$rcItem = $rs->ItemRecord();
		$wtItem = $rcItem->SelfLink_name();

		$wtAdded = $rs->Value('WhenAdded');
		$wtEdited = $rs->Value('WhenEdited');
		$nSeq = $rs->Value('Seq');
		$nQty = $rs->Value('Qty');

		$out .= <<<__END__
  <tr style="$wtStyle">
    <td>$wtID</td>
    <td>$nSeq</td>
    <td>$wtItem</td>
    <td>$nQty</td>
    <td>$wtAdded</td>
    <td>$wtEdited</td>
  </tr>
__END__;
		  ;
	    }
	    $out .= "\n</table>";
	} else {
	    $out = "\n<i>No items in cart</i>";
	}
	return $out;
    }
}

/*====
  HISTORY:
    2010-11-15 created
*/
class VCR_CartLine_admin extends vcrShopCartLine {
    use ftLinkableRecord;

    // ++ BOILERPLATE ++ //
/*
    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
	return clsMenuData_helper_standard::_AdminLink($this,$iText,$iPopup,$iarArgs);
    }
*/
    // -- BOILERPLATE -- //
    // ++ CLASS NAMES ++ //

    protected function ItemsClass() {
	return KS_ADMIN_CLASS_LC_ITEMS;
    }

    // -- CLASS NAMES -- //
}
