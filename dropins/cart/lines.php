<?php
/*
  HISTORY:
    2014-01-20 extracted from cart.php
*/

class VCT_CartLines_admin extends clsShopCartLines {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('VCR_CartLine_admin');
    }
    public function Table_forCart($iCart) {
	$rs = $this->GetData('ID_Cart='.$iCart,NULL,'Seq');
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

		$id = $rs->KeyValue();
		//$wtID = SelfLink_Page('cart','id',$id,$id);
		$wtID = $rs->AdminLink();
		//$wtItem = $objRecs->Item()->DescLong();
		$rcItem = $rs->ItemRecord();
		//$wtItem = $rcItem->DescLong();
		$wtItem = $rcItem->AdminLink_name();

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
class VCR_CartLine_admin extends clsShopCartLine {

    // ++ BOILERPLATE ++ //

    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
	return clsMenuData_helper::_AdminLink($this,$iText,$iPopup,$iarArgs);
    }

    // -- BOILERPLATE -- //
    // ++ CLASS NAMES ++ //

    protected function ItemsClass() {
	return KS_CLASS_CATALOG_ITEMS;
    }

    // -- CLASS NAMES -- //
}
