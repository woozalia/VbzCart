<?php
/*
  PURPOSE: Page class for site home page
  HISTORY:
    2018-02-05 attempting minimal kluge to get something up
*/

class vcPageHome extends vcPage_shop {
    // ++ SETUP ++ //

    // CEMENT
    protected function Class_forTagHTML() : string {
	return 'vcTag_html_home';
    }

    // -- SETUP -- //
}
class vcTag_html_home extends vcTag_html_shop {

    // ++ SETUP ++ //
    
    // CEMENT
    protected function Class_forTag_body() {
	return 'vcTag_body_home';
    }

    // -- SETUP -- //

}
class vcTag_body_home extends vcTag_body_shop {

    // ++ CLASSES ++ //
    
    // CEMENT
    /*
    protected function Class_forPageHeader() {
	return 'vcpePageHeader_shop';
    }
    protected function Class_forPageNavigation() {
	return NULL;	// probably not a good way to suppress the navbar. Better: make a class that doesn't try to create it.
    }*/
    // CEMENT
    protected function Class_forPageContent() {
	return 'vcPageContent_home';
    }
    
    // -- CLASSES -- //
    // ++ EVENTS ++ //

    // CreateElements: parent creates header, navbar, content
    // CEMENT
    protected function OnRunCalculations(){}
    
    // -- EVENTS -- //

}
class vcPageContent_home extends vcPageContent_shop {
    use vtTableAccess_Title_shop;

    // ++ MAIN CONTENT API ++ //

    // CEMENT
    protected function OnRunCalculations() {
	$this->FigureExhibitPage_fromInput();
    }
    
    // -- MAIN CONTENT API -- //
    // ++ FRAMEWORK ++ //
    
    protected function GetPageObject() {
	return fcApp::Me()->GetPageObject();
    }
    protected function GetDatabase() {
	return fcApp::Me()->GetDatabase();
    }
    // ALIAS FOR vtTableAccess_Title_shop
    protected function GetConnection() {
	return fcApp::Me()->GetDatabase();
    }
    
    // -- FRAMEWORK -- //
    // ++ CLASS ++ //
    
    protected function WikiPageClass() {
	return 'fctNodes_SimpleWikiPage';
    }
    
    // -- CLASS -- //
    // ++ TABLE ++ //
    
    protected function GetWikiPageTable($id) {
	return $this->GetDatabase()->MakeTableWrapper($this->WikiPageClass(),$id);
    }

    // -- TABLE -- //
    // ++ QUERIES ++ //
/*    
    protected function SupplierItemTypeQuery() {
	return $this->GetDatabase()->MakeTableWrapper('vcqtSuppliertItemTypes');
    } */
    protected function StockItemQuery() {
	return $this->GetDatabase()->MakeTableWrapper('vcqtStockItemsInfo');
    }
    protected function StockTitleQuery() {
	return $this->GetDatabase()->MakeTableWrapper('vcqtStockTitlesInfo');
    }
    protected function TitleInfoQuery() {
	return fcApp::Me()->GetDatabase()->MakeTableWrapper('vcqtTitlesInfo');
    }
    
    // -- QUERIES -- //
    // ++ OUTPUT ++ //
    
    protected function FigureExhibitPage_fromInput() {

    /* 2018-02-06 implement later; complex disentangling is involved
	$oArticle = $this->GetWikiPageTable(30); 	// hard-wired for now
	
	$oArticle->LoadLeafValues();
	
	echo fcArray::Render($oArticle->GetFieldValues());
	
	//$sTitle = $oArticle->
	  */
	$oPage = $this->GetPageObject();
	$oPage->SetContentTitleContext('hello and welcome to....');
	//$oPage->SetPageTitle('Home Page');
	$oPage->SetBrowserTitle('Vee Bee Zee dot net');
//	$oPage->SetContentTitle('The Virtual BaZaar');

	//$oPage->GetTagNode_html()->SetMetaDescription('vbz.net online retail');
	
	$sItems = $this->GetRandomItems();

	$this->SetValue(<<<__END__
<table><tr><td width=30% class=content>
<h1>So...</h1>
<p>...if <i>you</i> had an online store that was mostly printed t-shirts &ndash; some <a href="/topic/0524">fantasy</a> art (<a href="/topic/0670">light</a>, <a href="/topic/0671">dark</a>) a lot of classic rock bands like <a href="/topic/1213">Pink Floyd</a> and <a href="/topic/0584">The Grateful Dead</a> &ndash; and a few other gift items and generally a pretty eclectic range, and you were planning to expand into more practical areas while ultimately creating <a href="/wiki/The_Virtual_Bazaar_Manifesto">a distributed network of small retailers working together to destroy the plutonomy</a>, what would <i>you</i> put on the front page?</p>

<p>Personally, I'd put this:</p>
</td><td style="background-color: rgba(0,0,64,0.8);">
$sItems
</td></tr></table>
__END__
	);
    }
    protected function GetRandomItems() {
	$tq = $this->StockItemQuery();
	/*
	$sql = vcqtStockItemsInfo::SQL_forItemStatus('QtyForSale>0');
	$rs = $tq->FetchRecords($sql);
	$q = $rs->RowCount();
	*/
	
	$t = $this->StockTitleQuery();
	$rs = $t->SelectRecords_forTitleStockStatus();
	$q = $rs->RowCount();
	
	$nToGet = 10;	// change as needed
	
	$sPlur = fcString::Pluralize($q);
	$out = "<div class=content>There are currently <b>$q</b> different title$sPlur in stock.<br>Here are as many as $nToGet of them. (Reload the page for more.)</div><br>";

//	$out .= "<b>SQL AVAIL</b>: ".$rs->sql.'<br>';
	
	// copy data to array
	while ($rs->NextRow()) {
	    $arTitles[] = $rs->GetFieldValues();
	}
	
	$arRand = array_rand($arTitles,$nToGet);
	shuffle($arRand);	// array_rand returns chosen items in order, so randomize

	// load up information about each of the titles
	
	$sqlIDs = NULL;
	// build a list of the IDs
	foreach($arRand as $idx => $idxRand) {
	    $arTitle = $arTitles[$idxRand];
	    $idTitle = $arTitle['ID_Title'];
	    $sqlIDs .= is_null($sqlIDs)?($idTitle):(','.$idTitle);
	}
	// fetch extended status information for all titles listed
	$sqlFilt = "ID_Title IN ($sqlIDs)";
	$tTiInfo = $this->TitleInfoQuery();	// (vcqtTitlesInfo)
	
	/*
	$sql = $tTiInfo->SQL_ExhibitInfo($sqlFilt);
	$sql2 = $tTiInfo->SQL_forStockStatus_byTitle();
	$out.= "<br><b>SQL 1</b>: $sql<br><b>SQL 2</b>: $sql2<br>";
	*/
	$sql = $tTiInfo->SQL_forStockStatus_byTitle($sqlFilt);
	$rs = $tTiInfo->FetchRecords($sql);	// (vcqrTitleInfo)
	//$out .= "<b>SQL FINAL</b>: $sql<br>";
	
	// display the results
	while ($rs->NextRow()) {
	    $out .= $rs->RenderImages_withLink_andSummary();
	}

	/*
	//$tTitles = $this->TitleTable();
	
	$sqlIDs = NULL;
	for($idx=0; $idx<$nToGet; $idx++) {
	    $idxTitle = $arRand[$idx];
	    $arTitle =	$arTitles[$idxTitle];
	    $idTitle =	$arTitle['ID_Title'];
	    $qSale =	$arTitle['QtyForSale'];
	    $rc = 	$tTitles->GetRecord_forKey($idTitle);	// Title record object
//	    $out .= fcArray::Render($arTitle);
	    $sqlIDs .= is_null($sqlIDs)?($idTitle):(','.$idTitle);
	    
	    $sTitle = $rc->NameString();
	    $sPopup = "&ldquo;$sTitle&rdquo; - $qSale in stock";
	    $htImgs = $rc->RenderImages_forRow($sPopup,vctImages::SIZE_THUMB);
	    $htTitle = $rc->ShopLink($htImgs);
	    $out .= $htTitle;
	}
	$sqlFilt = "ID_Title IN ($sqlIDs)";
	$sql = $tTiInfo->SQL_ExhibitInfo($sqlFilt);
	// debugging
	$out .= "<b>SQL</b>: ".$sql;
	*/
	return $out; //.'<br><br><b>SQL</b>:'.$sql;
    }
}

class vcqtStockTitlesInfo extends fcTable_wSource_wRecords {

    // ++ SETUP ++ //

    // CEMENT
    public function GetKeyName() {
	return 'ID_Title';
    }
    // CEMENT
    protected function SingularName() {
	return 'vcqrStockTitleInfo';
    }

    // -- SETUP -- //
    // ++ SQL ++ //
    
    static public function SQL_forTitleStatus() {
	$sql = <<<__END__
SELECT 
    ID_Title,
    SUM(sl.QtyForSale) AS QtyForSale,
    SUM(sl.QtyForShip) AS QtyForShip,
    SUM(sl.QtyExisting) AS QtyExisting
FROM
    (SELECT 
        st.ID,
            st.ID_Bin,
            i.ID_Title,
            IF(sb.isForSale, st.Qty, 0) AS QtyForSale,
            IF(sb.isForShip, st.Qty, 0) AS QtyForShip,
            st.Qty AS QtyExisting,
            st.CatNum,
            st.WhenAdded,
            st.WhenChanged,
            st.WhenCounted,
            st.Notes,
            sb.Code AS BinCode,
            sb.ID_Place,
            sp.Name AS WhName
    FROM
        ((stk_lines AS st
    LEFT JOIN stk_bins AS sb ON sb.ID = st.ID_Bin)
    LEFT JOIN stk_places AS sp ON sb.ID_Place = sp.ID)
    LEFT JOIN cat_items AS i ON st.ID_Item=i.ID
    WHERE
        (sb.WhenVoided IS NULL)
            AND (st.Qty <> 0)
            AND (sp.isActivated)) AS sl
WHERE
    QtyForSale > 0
GROUP BY sl.ID_Title
__END__;
	return $sql;
    }
    
    // -- SQL -- //
    // ++ RECORDS ++ //
    
    public function SelectRecords_forTitleStockStatus() {
	$sql = self::SQL_forTitleStatus();
	$rs = $this->FetchRecords($sql);
	return $rs;
    }
}
class vcqrStockTitleInfo extends fcRecord_keyed_single_integer {
/* actually, not used... never mind
    public function GetQuantityForSale() {
	return $this->GetFieldValue('QtyForSale');
    } */
}
