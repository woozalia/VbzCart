<?php
/*
  PURPOSE: stuff that both Restock Request Lines and Received Restock Lines have in common --
    mainly AdminRows_forLCItem().
  HISTORY:
    2016-03-02 started for AdminRows_forLCItem()
*/

trait vtRestockLines {
    /*----
      HISTORY:
	2016-01-07 renamed from AdminList() to AdminRows()
	  ...but I still can't figure out what it's for, so abandoning for now.
	  It iterates through a list of items, but shows mostly information from the Request.
	2016-01-09 renamed from AdminRows_weird() to AdminRows_forLCItem(), because that is
	  what it's for: restock information for a given local catalog item
	2016-03-02 moved to trait vtRestockLines
    */
    public function AdminRows_forLCItem() {

	if ($this->hasRows()) {
	    $out = $this->AdminRows_forItem_Header();
	    $arSort = array();
	    while ($this->NextRow()) {
		$this->AdminRows_forItem_Collate($arSort);
	    }
	    // sort the master array
	    arsort($arSort);
	    
	    // iterate through the master array to pull out display data
	    $rcLine = $this;
//	    $rcReq = $this->ParentTable()->SpawnItem();
	    $isOdd = TRUE;
	    foreach ($arSort as $key=>$data) {
	    
		$sRow = $this->AdminRow_forItem($data);
		
		$cssClass = $isOdd?'odd':'even';
		$isOdd = !$isOdd;
		
		$out .= <<<__END__
  <tr class="$cssClass">
$sRow
  </tr>
__END__;
	    }
	    $out .= "\n</table>";
	} else {
	    $out = $this->AdminRows_forItem_NoDataText();
	}
	return $out;
    }
    protected function AdminRows_forItem_Collate(array &$ar) {
	// build sorting key
	$idMain = $this->ParentID();
	$idItem = $this->ItemID();
	$rcMain = $this->ParentTable($idMain);
	//$dtCreated = $rcReq->Value('WhenCreated');
	//$dtSumm = is_null($dtCreated)?($rcReq->Value('WhenOrdered')):$dtCreated;
	$key = $rcMain->SortingKey().'.'.$idMain;
	// stuff data into arrays
	$arData['line'] = $this->Values();
	$arData['main'] = $rcMain->Values();
	$ar[$key] = $arData;
    }
}
