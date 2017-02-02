<?php
/*
PURPOSE: Class for handling specialized stock item query
HISTORY:
  2014-05-12 Created
  2016-03-03 VCR_StkLine_info now descends from VCR_StkLine instead of generic recordset. More to do...
    I don't think this class ended up ever being used for anything -- but now it will.
*/
class vctqaStockLinesInfo extends VCT_StkLines {
    use ftQueryableTable;

    // ++ SETUP ++ //

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng(KS_CLASS_STOCK_LINE_INFO);
    }

    // -- SETUP -- //
    // ++ QUERIES ++ //
    
    protected function ItemInfoQuery() {
	return $this->Engine()->Make('vcqtItemsInfo');
    }
    
    // -- QUERIES -- //
    // ++ SQO ++ //
    
    protected function SQO_Items_forBinExhibit($idBin,$doShowRmvd) {
	$qtItemInfo = $this->ItemInfoQuery();
	$qo = $qtItemInfo->SQO_Items_CatNum();	// record-per-item
	$sroStock = $this->SQO_Source('sl');	// record-per-stock-line
	$qo->Select()->Source()->AddElement(
	  new fcSQL_JoinElement($sroStock,'sl.ID_Item=i.ID')
	);
	$arFilt = array('ID_Bin='.$idBin);
	if (!$doShowRmvd) {
	    $arFilt[] = 'WhenRemoved IS NULL';
	    $arFilt[] = 'Qty>0';
	}
	$qot = new fcSQL_Terms(
	  array(
	    new fcSQLt_Filt('AND',$arFilt),
	    new fcSQLt_Sort(array('WhenRemoved','CatNum_Item'))
	    )
	  );
	$qo->SetTerms($qot);
	$qo->Select()->Fields()->SetFields(
	  array(
	    'ID'	=> 'sl.ID',
	    'ID_Item'	=> 'i.ID',
	    'ID_Title'	=> 'i.ID_Title',
	    'TitleName'	=> 't.Name',
	    'Qty',
	    'WhenAdded',
	    'WhenChanged',
	    'WhenCounted',
	    'WhenRemoved',
	    'Notes'	=> 'sl.Notes'
	    )
	  );
	return $qo;
    }
    
    // -- SQO -- //
    // ++ RECORDS ++ //

    public function GetRecords_forBinExhibit($idBin,$doShowRmvd) {
	$qo = $this->SQO_Items_forBinExhibit($idBin,$doShowRmvd);
	$sql = $qo->Render();
	$rs = $this->DataSQL($sql);
	return $rs;
    }
    
    // -- RECORDS -- //
}
class vcrqaStockLineInfo extends VCR_StkLine {

    // ++ FIELD VALUES ++ //

    public function QtyForSale() {
	return $this->Value('QtyForSale');
    }
    public function QtyForShip() {
	return $this->Value('QtyForShip');
    }
    public function QtyExisting() {
	return $this->Value('QtyExisting');
    }
    public function BinID() {
	return $this->Value('ID_Bin');
    }
    public function PlaceID() {
	return $this->Value('ID_Place');
    }
    public function BinCode() {
	return $this->Value('BinCode');
    }
    public function WhereString() {
	return $this->Value('WhName');
    }
    public function Notes() {
	return $this->Value('Notes');
    }
    
    // -- FIELD VALUES -- //
    // ++ TABLES ++ //
    
    protected function TitleTable() {
	return $this->Engine()->Make(KS_CLASS_CATALOG_TITLES);
    }
    
    // -- TABLES -- //
    // ++ WEB UI ++ //

    /*----
      ASSUMES: There is at least one row to display
      INPUT: doBoxes = put a checkbox next to each row
    */
    public function AdminRows_forBin($doBoxes) {
	$out = <<<__END__
	
<table class=listing>
  <tr>
    <th colspan=2>ID</th>
    <th title="active?">A?</th>
    <th>CatNum</th>
    <th>qty</th>
    <th>title</th>
    <th>when<br>added</th>
    <th>when<br>changed</th>
    <th>when<br>counted</th>
    <th>when<br>removed</th>
    <th>notes</th>
  </tr>
__END__;
	$ftList = NULL;
	$isOdd = FALSE;
	$rcTitle = $this->TitleTable()->SpawnItem();
	while ($this->NextRow()) {
	    $row = $this->Values();

	    $id		= $row['ID'];
	    $htID	= $this->SelfLink();
	    $idItem 	= $row['ID_Item'];
	    $txtCatNum	= is_null($row['CatNum'])?"<i>".$row['ItCatNum']."</i>":$row['CatNum'];

	    //$rcItem	= $tItems->GetItem($idItem);
	    //$htCatNum	= $rcItem->SelfLink($txtCatNum);

	    $isActive	= is_null($row['WhenRemoved']);
	    $hasAnything = ($isActive && ($row['Qty'] > 0));

	    // calculate line formatting:
	    $cssClass = $isOdd?'odd':'even';
	    $isOdd = !$isOdd;
	    //$isActive = is_null($row['WhenRemoved']) && ($row['Qty'] > 0);
	    if (!$hasAnything) {
		$cssClass = 'inact';
	    }
	    $htActive	= clsHTML::fromBool($isActive);

	    $txtQty	= $row['Qty'];

	    $sTitle = $row['TitleName'];
	    $idTitle = $row['ID_Title'];
	    $rcTitle->Value('ID',$idTitle);
	    $htTitle	= $rcTitle->SelfLink($sTitle);
	    
	    $htCatNum		= $row['CatNum_Item'];

	    $txtWhenAdded	= fcDate::NzDate($row['WhenAdded']);
	    $txtWhenChged	= fcDate::NzDate($row['WhenChanged']);
	    $txtWhenCnted	= fcDate::NzDate($row['WhenCounted']);
	    $txtWhenRmved	= fcDate::NzDate($row['WhenRemoved']);
	    $txtNotes	= $row['Notes'];

	    if ($doBoxes && $isActive) {
		$htCk = '<input type=checkbox name="line['.$id.']" />';
	    } else {
		$htCk = '';
	    }

	    if ($isActive) {
		$ftList .= ' '.$idItem;
	    }
	    $out .= <<<__END__
  <tr class="$cssClass">
    <td>$htCk</td>
    <td>$htID</td>
    <td align=center>$htActive</td>
    <td>$htCatNum</td>
    <td>$txtQty</td>
    <td>$htTitle</td>
    <td>$txtWhenAdded</td>
    <td>$txtWhenChged</td>
    <td>$txtWhenCnted</td>
    <td>$txtWhenRmved</td>
    <td>$txtNotes</td>
  </tr>
__END__;
	}
	$out .= "\n</table>";
	if (!is_null($ftList)) {
	    $out .= "<b>Text list</b> (active only): $ftList<br>";
	}
	return $out;
    }
    
    // -- WEB UI -- //
}
