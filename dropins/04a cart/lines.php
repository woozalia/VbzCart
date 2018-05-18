<?php
/*
  HISTORY:
    2014-01-20 extracted from cart.php
    2018-02-21 revisions to make it work again
*/

class vctAdminCartLines extends vctShopCartLines implements fiLinkableTable {
    use ftLinkableTable;

    // ++ SETUP ++ //

    protected function SingularName() {
	return 'vcrAdminCartLine';
    }
    public function GetActionKey() {
	return KS_PAGE_KEY_CART_LINE;
    }

    // -- SETUP -- //
    
    public function Table_forCart($idCart) {
	$rs = $this->SelectRecords('ID_Cart='.$idCart,'Seq');
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
		$cssClass = $isOdd?'odd':'even';
		$isOdd = !$isOdd;

		$id = $rs->GetKeyValue();
		$wtID = $rs->SelfLink();
		$rcItem = $rs->ItemRecord();
		$wtItem = $rcItem->SelfLink_name();

		$wtAdded = $rs->GetWhenAdded();
		$wtEdited = $rs->GetWhenEdited();
		$nSeq = $rs->GetSequence();
		$nQty = $rs->GetQtyOrd();

		$out .= <<<__END__
  <tr class="$cssClass">
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
class vcrAdminCartLine extends vcrShopCartLine implements fiLinkableRecord {
    use ftLinkableRecord;

    // ++ CLASS NAMES ++ //

    protected function ItemsClass() {
	return KS_ADMIN_CLASS_LC_ITEMS;
    }

    // -- CLASS NAMES -- //
}
