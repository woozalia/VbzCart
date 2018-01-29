<?php
/*
  HISTORY:
    2016-01-23 created to tidy up catalog title page code
*/

class vcqtItemsInfo extends vctItems {
    use ftQueryableTable;

    // ++ SETUP ++ //

    // CEMENT
    protected function SingularName() {
	return 'vcqrItemInfo';
    }

    // -- SETUP -- //
    // ++ TABLES ++ //
    
    protected function TitleInfoQuery() {
	return $this->GetConnection()->MakeTableWrapper('vcqtTitlesInfo');
    }
    
    // -- TABLES -- //
    // ++ SQL ++ //
    
    /*----
      USED BY: Title exhibit
      SQL FOR: recordset for information about active Items for the given Title
      HISTORY:
	2017-06-19 removed isCurrent because that field is going away
	  Added Supp_LastUpdate and Supp_CatDate becuse they will be relevant to the item's status
    */
    static public function SQL_forTitlePage($idTitle) {	// Item details for a Title page
	//$sqlStockItemStatus = vcqtStockLinesInfo::SQL_forItemStatus();
	$sqlStockItemStatus = vcqtStockItemsInfo::SQL_forItemStatus('TRUE');	// "TRUE" is a bit of a kluge here...
	// ...or maybe the filter argument should be optional.
	$sql = <<<__END__
SELECT 
    i.ID,
    ID_ItTyp,
    it.NamePlr,
    si.QtyForSale,
    CatNum,
    ItOpt_Descr,
    PriceList,
    PriceSell,
    isAvail,
    isInPrint,
    SC_DateActive,
    SC_LastUpdate,
    SC_DateExpires
FROM
    cat_items AS i
      LEFT JOIN cat_ittyps AS it ON i.ID_ItTyp = it.ID
      LEFT JOIN cat_ioptns AS io ON i.ID_ItOpt = io.ID
      LEFT JOIN ($sqlStockItemStatus) AS si ON si.ID_Item=i.ID
      LEFT JOIN cat_titles AS t ON i.ID_Title=t.ID
WHERE
    ((i.isAvail) OR (si.QtyForSale > 0)) AND i.ID_Title = $idTitle
ORDER BY it.Sort , GrpSort , GrpDescr , i.ItOpt_Sort , io.Sort
__END__;
//*/

	return $sql;
    }

    // -- SQL -- //
    // ++ SQO ++ //
    
    /*----
      RETURNS: SQL object
      RESULT: Item records with Title information and calculated CatNum
    */
    public function SQO_Items_CatNum() {
	$sroItem = $this->SQO_Source('i');
	$qo = $this->TitleInfoQuery()->SQO_Title_CatNums();
	// qo -> records per Title
	$qo->Select()->Source()->AddElement(
	  new fcSQL_JoinElement($sroItem,'i.ID_Title=t.ID')
	);
	// qo -> records per Item
	$qo->Select()->Fields()->SetFields(
	  array(
	    'CatNum_Item' => "UPPER(CONCAT_WS('-',s.CatKey,d.CatKey,t.CatKey,i.CatSfx))",
	    )
	  );
	
	return $qo;
    }
    /*----
      RETURNS: SQL object
      RESULT: metrics/summaries across all items for each $sGroup
	Originally written for $sGroup='ID_ItTyp', in which case this
	returns stats for each Item Type in the recordset, but can be
	used for other groupings such as ID_Title.
      NOTE: includes stock info but not Item Type
      INPUT:
	$sGroup: field to group by (is also included in output fields)
	$sqlFilt: filter to apply (optional)
    */
    public function SQO_Stats($sGroup,$sqlFilt=NULL) {
	$qoStock = vcqtStockLinesInfo::SQO_forItemStatus();
	//$sroItem = new fcSQL_TableSource($this->Name(),'i');
	$sroItem = $this->SQO_Source('i');

	$qeItem = new fcSQL_JoinElement($sroItem);
	$qeSub = new fcSQL_JoinElement(new fcSQL_SubQuerySource($qoStock,'s'),'s.ID_Item=i.ID');
	/*
	if ($doInactive) {
	    $qeSub->Verb('LEFT JOIN');
	}//*/
	
	$joStats = new fcSQL_JoinSource(array($qeItem,$qeSub));
	$sel = new fcSQL_Select($joStats);
	$sel->Fields()->Values(array(
	    $sGroup,
	    'MIN(PriceBuy)' => 'PriceBuy_min',
	    'MAX(PriceBuy)' => 'PriceBuy_max',
	    'SUM(QtyForSale)' => 'QtyForSale',
	    'COUNT(isAvail)' => 'QtyAvail',
	    "GROUP_CONCAT(DISTINCT CatSfx SEPARATOR ', ')" => 'SfxList'
	    )
	  );
	$arTerms = array(
	  //new fcSQLt_Group(array($sGroup.' WITH ROLLUP'))
	  new fcSQLt_Group(array($sGroup))	// WITH ROLLUP seems to be just confusing things
	  );
	if (!is_null($sqlFilt)) {
	    $arTerms[] = new fcSQLt_Filt(NULL,array($sqlFilt));
	}
	$qry = new fcSQL_Query($sel,new fcSQL_Terms($arTerms));
	
	return $qry;
    }
    /*----
      PRODUCES: records for items that are available for sale
    */
    public function SQO_forSale() {
	$qo = vcqtStockLinesInfo::SQO_forItemStatus();
	$qsi = $this->SQO_Source('i');
	$qo->Select()->Source()->AddElements(
	  array(
	    new fcSQL_JoinElement($qsi,'sl.ID_Item=i.ID')
	    )
	  );
	$qof = $qo->Select()->Fields();
	$qof->ClearFields();
	$qof->SetFields(
	  array(
	    'i.ID',	// ID_Item
	    'QtyForSale' => 'SUM(sl.Qty)',
	    )
	  );
	return $qo;
    }
    public function SQO_forSale_wIOpts() {
	$oq = vcqtStockLinesInfo::SQO_forItemStatus();
	$sroItem = new fcSQL_TableSource($this->Name(),'i');
	$sroIO = new fcSQL_TableSource('cat_ioptns','io');
	$oq->Select()->Source()->AddElements(
	  array(
	    new fcSQL_JoinElement($sroItem,'si.ID_Item=i.ID'),
	    new fcSQL_JoinElement($sroIO,'i.ID_ItOpt=io.ID')
	    )
	  );
	$oq->Select()->Fields()->Values(array(
	    'i.ID',	// ID_Item
	    'SUM(sl.Qty)'	=> 'QtyForSale',
	    )
	  );
	return $oq;
    }
    // this will probably not be used after all
    protected function SQO_forTopicPage($idTopic) {	// Item records for all Titles on a Topic page
	throw new exception('(2016-03-03) Is anything calling this?');
	$oq = vcqtStockLinesInfo::SQO_forItemStatus();
	$sroItem = new fcSQL_TableSource($this->Name(),'i');
	$sroTT = new fcSQL_TableSource('cat_title_x_topic','tt');
	
	// add more JOINs
	$oq->Select()->Source()->AddElements(
	  array(
	    new fcSQL_JoinElement($sroItem,'si.ID_Item=i.ID'),
	    new fcSQL_JoinElement($sroTT,'i.ID_Title=tt.ID_Title')
	    )
	  );
	$oq->Select()->Fields()->Values(array(
	    'i.ID',	// ID_Item
	    //'CatNum',
	    //'i.isAvail',
	    'QtyForSale' => 'SUM(sl.Qty)',
	    //'i.ID_Title'
	    // add more columns as needed
	    )
	  );
	
	$oq->Terms()->UseTerm(new fcSQLt_Filt(NULL,array('ID_Topic='.$idTopic)));

	/*
	$arTerms = array(
	  new fcSQLt_Filt(NULL,array('ID_Topic='.$idTopic)),
	  );
	$qry = new fcSQL_Query($sel,new fcSQL_Terms($arTerms));
	//*/
	return $oq;
    }

    // -- SQO -- //
    // ++ SQO pieces ++ //
    
    /*----
      OUTPUT: fields expected by records.Render()
      HISTORY:
	2017-06-19 removed isCurrent because that field is going away
	  Might need to add Supp_LastUpdate and Supp_CatDate becuse they will be relevant to the item's status
    */
    public function Fields_forRender() {
	return array(
	    'i.ID',	// ID_Item
	    'i.CatNum',
	    'i.Supp_CatNum',
	    'i.isAvail',
	    //'i.isCurrent',
	    'i.isInPrint',
	    //'i.isCloseOut',
	    'i.isMaster',
	    'i.isPulled',
	    'i.isDumped',
	    'i.ID_ItOpt',
	    'QtyForSale' => 'SUM(sl.Qty)',
	    'i.PriceBuy',
	    'i.PriceSell'
	    );
    }
    
    // -- SQO pieces -- //
    // ++ RECORDS ++ //
    
    public function GetRecords_forStats($sGroup,$sqlFilt) {
	$qoStats = $this->SQO_Stats($sGroup,$sqlFilt);
	$sql = $qoStats->Render();
	return $this->DataSQL($sql);
    }
    public function Records_forTitle($idTitle) {
	return $this->FetchRecords(self::SQL_forTitlePage($idTitle));
    }
    public function Records_forTopic($idTopic) {
	throw new exception('(2016-03-03) Is anything calling this?');
	$qry = $this->SQO_forTopicPage($idTopic);
	$sql = $qry->Render();
	$rs = $this->DataSQL($sql);
	return $rs;
    }
    /*----
      ASSUMES: item.isAvail means "available from active catalog source"
    */
    public function SQL_byItemType_active() {
	$sqlStock = vcqtStockLinesInfo::SQL_forItemStatus();
	
	$sql = <<<__END__
SELECT * FROM cat_items AS i
    LEFT JOIN cat_ittyps AS it
	ON i.ID_ItTyp = it.ID
    LEFT JOIN (
$sqlStock
    ) AS s ON s.ID_Item = i.ID WHERE (QtyForSale>0) or i.isAvail
__END__;

	return $sql;

    }
    
    // -- RECORDS -- //
    // ++ ARRAYS ++ //
    
    /*----
      RETURNS: array of statistics for each group of active items
      PUBLIC because Items and Topics need to call it in order to display images with full information
      INPUT:
	$sGroup: name of field to group by, for summing stats
      HISTORY:
	2016-02-13 commented out with note "rewriting as records->ResultsArray()"
	2016-02-17 Actually, it's a nuisance to have to get a recordset, so let's just refactor this a bit.
    */
    public function StatsArray($sGroup,$sqlFilt) {
	$rs = $this->GetRecords_forStats($sGroup,$sqlFilt);
	$ar = $rs->ResultsArray();
	
	if (is_null($ar)) {
	    return 'not available';
	} else {
	    return $ar;
	    /*
	    while ($rsStats->NextRow()) {
	    
		$idItTyp = $rsStats->ItemTypeID();
		
		// 2016-02-12 not sure why we're using WITH ROLLUP for some aggregation but not all
		if (is_null($idItTyp)) {
		    // there will be only one row where this happens (this is because of WITH ROLLUP)
		    $sOpts = $rsStats->Value('SfxList');
		    $prcMin = $rsStats->Value('PriceBuy_min');
		    $prcMax = $rsStats->Value('PriceBuy_max');
		    // also get stock count -- for now just use calculated field
		    $qtyStock = $rsStats->Value('QtyForSale');
		} else {
		    $rsItTyp = $rsStats->ItemTypeRecord();
		    if (!is_null($sItTyps)) {
			$sItTyps .= ', ';
		    }
		    $sItTyps .= $rsItTyp->Name();
		}
	    }
	    $arStats['price-min'] = $prcMin;
	    $arStats['price-max'] = $prcMax;
	    $arStats['opt-list'] = $sOpts;
	    $arStats['stock-qty'] = $qtyStock;
	    $arStats['types'] = $sItTyps;
	    $sPrcMin = clsMoney::Format_withSymbol($prcMin);
	    $sPrcMax = clsMoney::Format_withSymbol($prcMax);
	    if ($sPrcMin == $sPrcMax) {
		$sPrc = $sPrcMin;
	    } else {
		$sPrc = $sPrcMin.' - '.$sPrcMax;
	    }
	    if ($qtyStock == 0) {
		$sStock = 'out of stock';
	    } else {
		$sStock = $qtyStock.' in stock';
	    }
	    $sSummary = "$sItTyps: $sOpts @ $sPrc ($sStock)";
	    //*/
	}
	//$arStats['summary'] = $sSummary;
	//return $arStats;
    }
    //*/
    
    // -- ARRAYS -- //

}

class vcqrItemInfo extends vcrItem {

    // ++ ARRAYS ++ //
    
    /*----
      OUTPUT: return array
	return[
    */
    public function ResultsArray() {
    throw new exception('(&lt; 2017-03) Does anything call this? Cannot function at present.');
	$ar = NULL;
	if ($this->HasRows()) {
	echo 'SQL: '.$this->sqlMake.'<br>';
	    $sItTyps = NULL;
	    while ($this->NextRow()) {
		$idItTyp = $this->ItemTypeID();
		
		$sOpts = $this->Value('SfxList');
		$prcMin = $this->Value('PriceBuy_min');
		$prcMax = $this->Value('PriceBuy_max');
		// also get stock count -- for now just use calculated field
		$qtyStock = $this->Value('QtyForSale');
		echo 'RECORD: '.clsArray::Render($this->Values());
		
		/*
		//die(__FILE__.' line '.__LINE__.': this needs checking');
		    $rsItTyp = $this->ItemTypeRecord();
		    if (!is_null($sItTyps)) {
			$sItTyps .= ', ';
		    }
		    $sItTyps .= $rsItTyp->Name();
		}//*/
	    }
	    $ar['price-min'] = $prcMin;
	    $ar['price-max'] = $prcMax;
	    $ar['opt-list'] = $sOpts;
	    $ar['stock-qty'] = $qtyStock;
	    $ar['types'] = $sItTyps;
	    $sPrcMin = clsMoney::Format_withSymbol($prcMin);
	    $sPrcMax = clsMoney::Format_withSymbol($prcMax);
	    if ($sPrcMin == $sPrcMax) {
		$sPrc = $sPrcMin;
	    } else {
		$sPrc = $sPrcMin.' - '.$sPrcMax;
	    }
	    if ($qtyStock == 0) {
		$sStock = 'out of stock';
	    } else {
		$sStock = $qtyStock.' in stock';
	    }
	    $ar['summary'] = "$sItTyps: $sOpts @ $sPrc ($sStock)";
	}
	return $ar;
    }
    
    // -- ARRAYS -- //
    // ++ UI PAGES ++ //

    /*----
      ACTION: Render Item records formatted for a Title exhibit page
      CALLED BY: title.shop.php : vcrShopTitle->ExhibitContent()
      HISTORY:
	2016-01-23 Moved to vcrItemInfo and tidied a bit
    */
    public function Render_TitleListing() {
	$arIn = NULL;
	$arOut = NULL;
	while ($this->NextRow()) {
	    $idItem = $this->GetFieldValue('ID');
	    
	    $qtyInStk = $this->GetFieldValue('QtyForSale');
	    $isInStock = ($qtyInStk > 0);
	    $isForSale = $isInStock || $this->GetFieldValue('isAvail');

	    // get item type
	    $idTyp = $this->GetFieldValue('ID_ItTyp');

	    // put this item into the nested array
	    if ($isInStock) {
		$arIn[$idTyp][$idItem] = $this->GetFieldValues();
	    } else {
		$arOut[$idTyp][$idItem] = $this->GetFieldValues();
	    }
	}
	
	$out = NULL;
	if (!is_null($arIn)) {
	    $out .= $this->Render_ListingArray($arIn,
		'in stock',
		'inStock'
		);
	}
	if (!is_null($arOut)) {
	    $out .= $this->Render_ListingArray($arOut,
	      '<a href="'.KWP_HELP_NO_STOCK_BUT_AVAIL.'"><b>not in stock</b></a>',
	      'noStock'
	      );
	}
	  
	return $out;
    }
    
    // -- UI PAGES -- //
    // ++ UI PIECES ++ //
    
    /*----
      RETURNS: Table header for list of available items on catalog Title pages
      HISTORY:
	2011-01-24 created/corrected from code in Title page-display function
	2017-06-29 moved from item.php : vcrItem to item-info.php : vcqrItemInfo
    */
    static public function Render_TableHdr_forTitle() {
	return <<<__END__

  <tr>
    <th align=left>Option</th>
    <th>Status</th>
    <th align=center><i>List<br>Price</i></th>
    <th align=center class=title-price>Our<br>Price</th>
    <th align=center class=orderQty>Order<br>Qty.</th>
    <th>catalog #</th>
    </tr>
__END__;
    }
    /*----
      HISTORY:
	2016-01-23 Moved to vcrItemInfo and tidied a bit
    */
    protected function Render_ListingArray(array $ar,$sDescr,$cssClass) {
	$out = NULL;

	// calculate how many items are in this status group
	$nQty = 0;
	foreach ($ar as $idTyp => $arItm) {
	    $nQty += count($arItm);	// how many items are in this type?
	}
	if ($nQty > 0) {
	    $out .= "\n<!-- + ITEM TABLE + -->\n<table class=main><tbody>";
	    $sNoun = fcString::Pluralize($nQty,'This item is','These items are');
	    $out .= "<tr class=$cssClass><td colspan=5>$sNoun $sDescr</td></tr>"
	      .self::Render_TableHdr_forTitle()
	      ;

	    $this->SetCanOrder(FALSE);	// must be at least one orderable line
	    $idTypeLast = NULL;
	    foreach ($ar as $idTyp => $arItm) {
		foreach ($arItm as $idItem => $row) {
		
		    // Item Type header
		    $idType = $row['ID_ItTyp'];
		    if ($idType != $idTypeLast) {
			$sType = $row['NamePlr'];
			$out .= "\n<tr class=section-header><td colspan=3>$sType</td></tr>";
			$idTypeLast = $idType;
		    }
		    $this->ClearFields();
		    $this->SetFieldValues($row);	// stuff row values into an object for easier access
		    $out .= $this->Render_TableRow();
		}
	    }
	    if ($this->GetCanOrder()) {
		$ctrlBtn = '<input name="'
		  .vcGlobals::Me()->GetButtonName_AddToCart()
		  .'" value="Add to Cart" type="submit">'
		  ;
	    } else {
		$ctrlBtn = NULL;	// nothing to order here
	    }
	    $out .= "\n<tr><td colspan=4 align=right>$ctrlBtn</td></tr>"
	      ."\n</tbody></table>"
	      ."\n<!-- - ITEM TABLE - -->"
	      ;
	}
	return $out;
    }
    private $canOrder;
    protected function SetCanOrder($b) {
	$this->canOrder = $b;
    }
    protected function GetCanOrder() {
	return $this->canOrder;
    }
    /*-----
      ASSUMES: (2016-02-10 ASSUMPTION NEEDS RECHECKING - isForSale field is gone)
	This item is ForSale, so isForSale = true and (qtyForSale>0 || isInPrint) = true
      HISTORY:
	2011-01-24 Renamed Print_TableRow() -> Render_TableRow; corrected to match header
	2016-01-23 Moved to vcrItemInfo and tidied a bit
    */
    public function Render_TableRow() {
	$arStat = $this->AvailStatus();
	$strCls = $arStat['cls'];

	$id = $this->GetKeyValue();
	$sCatNum = $this->GetFieldValue('CatNum');
	$htDescr = $this->GetFieldValue('ItOpt_Descr');
	$htStat = $arStat['html'];
	$dlrPriceList = $this->GetFieldValue('PriceList');
	$dlrPriceSell = $this->GetFieldValue('PriceSell');
	$htPrList = fcMoney::Format_withSymbol($dlrPriceList);
	$htPrSell = fcMoney::Format_withSymbol($dlrPriceSell);

	$dlrPrUse = is_null($dlrPriceSell)?$dlrPriceList:$dlrPriceSell;
	if (is_null($dlrPrUse)) {
	    $htCtrl = '<span title="not available for ordering because price has not been set">N/A</span>';
	} else {
	    //$htCtrlName = KSF_CART_ITEM_PFX.$sCatNum.KSF_CART_ITEM_SFX;
	    $htCtrlName = vcGlobals::Me()->MakeItemControlName($sCatNum);
	    $htCtrl = "<input size=1 name='$htCtrlName'>";
	    $this->SetCanOrder(TRUE);
	}

	$out = <<<__END__
  <tr class=$strCls><!-- ID=$id -->
    <td>&emsp;$htDescr</td>
    <td>$htStat</td>
    <td align=right><i>$htPrList</i></td>
    <td align=right>$htPrSell</td>
    <td>$htCtrl</td>
    <td>$sCatNum</td>
  </tr>
__END__;
	return $out;
    }
    
    // -- UI PIECES -- //
    // ++ CALCULATIONS ++ //

    /*----
      ACTION: Returns an array with human-friendly text about the item's availability status
	This is mainly a separate method because it's a little complicated.
      RETURNS:
	array['html']: status text, in HTML format
	array['cls']: class to use for displaying item row in a table
      USED BY: Render_TableRow()
      HISTORY:
	2010-11-16 Modified truth table for in-print status so that if isInPrint=FALSE, then status always shows
	  "out of print" even if isCurrent=FALSE. What happens when a supplier has been discontinued? Maybe we need to
	  check that separately. Wait for an example to come up, for easier debugging.
	2011-01-24 Corrected to use cat_items fields
	2016-01-23 Moved from cls... never got back to finding the class name and pasting it here...
      NOTES: isCurrent is going away. What we have to work with now is:
	* SC_DateAvail (the effective date of the source)
	* SC_LastUpdate (last time the source was updated)
	* SC_DateExpires (date when source is no longer active)
      RULES:
	* If the source has an expiration date, then no need to worry about whether it is out-of-date.
	* If the source is out of print (isInPrint=FALSE) but still available (isAvail=TRUE), then something like "discontinued; availability limited"
	* If the source is unavailable and out of print but still in stock, then something like "out of print; can't get more"
    */
    private function AvailStatus() {
	$qtyInStock = $this->GetFieldValue('QtyForSale');	// query-calculated field

	$isInStock = ($qtyInStock > 0);
/*
	if ($isInStock) {
	    $strCls = 'inStock';
	    $strStk = $qtyInStock.' in stock';
	} else {
	    $strCls = 'noStock';
	    $strStk = 'none in stock';
	}
*/
	if ($this->IsInPrint()) {
	    if ($this->HasSCExpiration()) {
		// definitely in print; same as "isCurrent" was intended to be
		if ($isInStock) {
		    // in stock, can get more
		    $txt = $qtyInStock.' in stock; more available';
		} else {
		    // none in stock but can backorder
		    $txt = '<a title="more information" href="'.KWP_HELP_NO_STOCK_BUT_AVAIL.'">available (will be backordered)</a>';
		    // TODO: maybe a checkbox -- "confirm with me before charging/shipping"
		}
	    } else {
		// in print as far as we know, but source has no expiration date
/*
  Here's where it gets tricky. The catalog appears to be current, but has no expiration -- so it *might* not be valid anymore.
  ..but even if we're reasonably sure the *catalog* has expired, the item might be in a newer catalog we just haven't entered yet.
  
  If there was a more recent catalog in which this item was not listed, then it would no longer be marked "in print".
  So we don't need to check for that; it's already done. This is only for the "stale catalog, no replacement [yet]" scenario.
  
  Given a stale catalog: as time goes by, the likelihood of the item being in print decreases.
  
  It doesn't seem accurate to say "probably not in print", though; it's more accurate to say "in-print status uncertain".
  
  So for now, let's use a global setting (say, one year) to determine when a catalog's validity becomes questionable.
  Before that time is up, we'll say "probably in print", and after that we'll say the status is "uncertain".
  
  In all cases where we don't know the expiration, we'll show the catalog date and when the catalog record was last updated.
  
  We'll use the catalog record's WhenUpdated as the date to figure from (for now, at least).
  
*/
		// convert timestamps to seconds

		$sdtWhenActive = $this->SCWhenActive();			// catalog's starting date
		$sdtWhenUpdated = $this->SCWhenUpdated();		// when the catalog record was last updated
		// 2017-07-02 This could be used at some point, but right now we're not doing anything with it.
		//$sdtWhenExpires = $this->SCWhenExpires();		// catalog's expiration date (if any)
		
		if (is_null($sdtWhenUpdated)) {
		    // this field sometimes isn't set in legacy data
		    $sdtWhenUpdated = $sdtWhenActive;
		}

		// numeric date/time (seconds since zero-day)
		$ndtWhenActive = strtotime($sdtWhenActive);		// catalog's starting date
		$ndtWhenUpdated = strtotime($sdtWhenUpdated);		// when the catalog record was last updated
		$ndtNow = time();					// current date/time
		
		// date intervals
		$ndiSinceActive_sec = $ndtNow - $ndtWhenActive;
		$ndiSinceUpdate_sec = $ndtNow - $ndtWhenUpdated;
		
		$kSecsPerDay = 60*60*24;
		$ndiSinceActive_day = round($ndiSinceActive_sec / $kSecsPerDay);
		$ndiSinceUpdate_day = round($ndiSinceUpdate_sec / $kSecsPerDay);
		
		$isStale = ($ndiSinceUpdate_day > 365);

		$sPlurActive = fcString::Pluralize($ndiSinceActive_day);
		$sPlurUpdate = fcString::Pluralize($ndiSinceUpdate_day);
		$htDaysAgo = "<span title='$sdtWhenUpdated'>$ndiSinceUpdate_day day$sPlurUpdate ago</span>";

		if ($isStale) {
		    $sStatusSource = "availability uncertain (last confirmed $htDaysAgo)";
		} else {
		    $sStatusSource = "probably still in print (last confirmed $htDaysAgo)";
		}
		if ($isInStock) {
		    $txt = $qtyInStock.' in stock; '.$sStatusSource;
		} else {
		    $txt = 'none in stock; '.$sStatusSource;
		}
	    }
	} else {
	    // definitely out of print
	    if ($isInStock) {
		$sStk = $qtyInStock.' in stock';
	    } else {
		$sStk = 'none in stock';
	    }
	    // TODO: if Supplier is inactive, make this more definitive e.g. "no more available"
	    // Possibly flags to distinguish between "supplier gone", "supplier won't sell to us", and "not dealing with them anymore"?
	    // If Supplier is active, then text could be just "out of print" or "discontinued"
	    $txt = "<b>$sStk</b>; not currently available for backorder";
	}
	if ($isInStock) {
	    $css = 'inStock';
	} else {
	    $css = 'noStock';
	}
	
	$arOut['html'] = $txt;
	$arOut['cls'] = $css;
	return $arOut;
    }
    
    // -- CALCULATIONS -- //
}