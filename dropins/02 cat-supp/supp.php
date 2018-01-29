<?php
/*
  FILE: dropins/cat-supp/suoo.php -- catalog sources for VbzCart Supplier Catalog Suppliers
  PURPOSE: Supplier controls specifically for Supplier Catalogs - so we can take any existing SC functions
    from the Local Catalog Suppliers controls and move them here
  HISTORY:
    2016-02-01 started
*/
class vctaSCSuppliers extends vctAdminSuppliers {

    // ++ SETUP ++ //
    
    // OVERRIDE
    protected function SingularName() {
	return KS_CLASS_SUPPCAT_SUPPLIER;
    }
    // OVERRIDE
    public function GetActionKey() {
	return KS_ACTION_SUPPCAT_SUPPLIER;
    }
    
    // -- SETUP -- //
    // ++ RECORDS ++ //
    
    protected function GetAdminRecords() {
	$sqlFilt = 'ID_PriceFunc IS NOT NULL';
	$sqlSort = 'Name';
	$rs = $this->SelectRecords($sqlFilt,$sqlSort);
	return $rs;
    }
    
    // -- RECORDS -- //
    // ++ ADMIN WEB UI ++ //
    
    //++listing++//

    protected function AdminRows_head() {
	return <<<__END__
  <tr>
    <th>ID</th>
    <th title="active?">A?</th>
    <th>Supplier</th>
  </tr>
__END__;
    }

    //--listing--//
    //++dependent++//
    
    protected function AdminDependent() {
	return NULL;
    }

    // -- ADMIN WEB UI -- //

}

class vcraSCSupplier extends vcrAdminSupplier {
    use vtTableAccess_Supplier_admin;

    // ++ EVENTS ++ //
  
    //protected function OnCreateElements() {}
    protected function OnRunCalculations() {
	$sName = $this->NameString();
	
	$oPage = fcApp::Me()->GetPageObject();
	//$oPage->SetPageTitle($sTitle);
	$oPage->SetBrowserTitle('SC for '.$sName);
	$oPage->SetContentTitle('Supplier Catalog for '.$sName);
    }
    /*
    public function Render() {
	return $this->AdminPage();
    }*/

    // -- EVENTS -- //
    // ++ TABLES ++ //

    protected function SCSourceTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper($this->SCSourcesClass(),$id);
    }

    // -- TABLES -- //
    // ++ RECORDS ++ //
    
    protected function LCSupplierRecord() {
	$rc = $this->SupplierTable()->SpawnRecordset();
	$rc->SetFieldValues($this->GetFieldValues());
	return $rc;
    }
    
    // -- RECORDS -- //
    // ++ ADMIN WEB UI ++ //
    
    //++single++//
    
    /*----
      NOTE: We don't edit the Supplier here, so all the controls and code for that
	can be omitted. Also, we don't really need to display anything about the record
	except the Supplier's name; this is about SCM dependent records.
    */
    protected function AdminPage() {
	//$sShow = $oPage->PathArg('show');	// subpage to show
	
	$sName = $this->NameString();
	
	$oMenu = fcApp::Me()->GetHeaderMenu();
	  $oMenu->SetNode($oGrp = new fcHeaderChoiceGroup('section','Manage'));
	    $oGrpSection = $oGrp;	// save for later
						// ($sKeyValue,$sPopup=NULL,$sDispOff=NULL,$sDispOn=NULL)
	    $oGrp->SetChoice($ol = new fcHeaderChoice('cat','wholesale catalogs from '.$sName,'catalogs'));
	    
	    $oGrp->SetChoice($ol = new fcHeaderChoice('ctg',"groups for organizing $sName catalog items",'catalog groups'));

	    $sShow = $oGrp->GetChoiceValue();

	$rcBase = $this->LCSupplierRecord();
	$htStatus = $rcBase->SelfLink_name();
	if (is_null($sShow)) {
	    $htStatus .= ' &ndash; choose a menu option.';
	}
	$out = "<div class=content>Catalog Management for $htStatus</div>";
	
/*
	// temporarily replace Action Key so we can link back to base admin page for this Supplier
	$sActKey = $this->Table()->ActionKey();
	$this->GetTableWrapper()->ActionKey(KS_ACTION_CATALOG_SUPPLIER);
	$out = 'Catalog Management for '.$this->SelfLink_name();
	$this->Table()->ActionKey($sActKey);	// restore SCM action key
*/
	$sHdr = NULL;
	$oMenu = new fcHeaderMenu();
	switch ($sShow) {
	  case 'cat':
	    $sHdr = 'Catalogs';
	    $sKey = $this->SCSourceTable()->GetActionKey();
			      // ($sGroupKey,$sKeyValue=TRUE,$sDispOff=NULL,$sDispOn=NULL,$sPopup=NULL)
	    $oMenu->SetNode($ol = new fcMenuOptionLink('page',$sKey,'add',NULL,'add a catalog to '.$sName));
	      $ol->AddLinkArray(
		array(
		  'id'		=> KS_NEW_REC,
		  'supp'	=> $this->GetKeyValue()
		  )
		);

	    $oMenu->SetNode($oGrp = new fcHeaderChoiceGroup('show','View'));
						// ($sKeyValue,$sPopup=NULL,$sDispOff=NULL,$sDispOn=NULL)
	      $oGrp->SetChoice($ol = new fcHeaderChoice('inact','include inactive catalogs','inactive'));
		$ol->AddLinkArray($oGrpSection->GetSelfArray());	// make sure we stay in the same section
		$doInact = $ol->GetIsSelected();
	    /*
	    $arActs = array(
	      new clsActionLink_option(
		array(
		  'id'		=> KS_NEW_REC,
		  'supp'	=> $this->GetKeyValue()
		  ),
		$sKey,			// $iLinkKey
		'page',			// $iGroupKey
		'add',			// $iDispOff
		NULL,				// $iDispOn
		"add a catalog to $sName"	// $iDescr
	      ),
	      new clsAction_section('View'),
	      new clsActionLink_option(
		array(),
		'inact',	// link key
		'view',		// group key
		'inactive',	// display when off
		NULL,		// display when on
		'include inactive catalogs'
	      ),
	    );
	    $out .= $oPage->ActionHeader($sHdr,$arActs);
	    $doInact = ($oPage->PathArg('view') == 'inact');
	    */
	    $sSection = $this->SourceAdmin($doInact);
	    break;
	  case 'ctg':
	    $sHdr = 'Catalog Groups';
			      // ($sGroupKey,$sKeyValue=TRUE,$sDispOff=NULL,$sDispOn=NULL,$sPopup=NULL)
	    $oMenu->SetNode($ol = new fcMenuOptionLink('do','edit',NULL,NULL,'edit the list of groups'));
	      $doEdit = $ol->GetIsSelected();
	    /*
	    $arActs = array(
	      new clsActionLink_option(array(),
		'edit',			// $iLinkKey
		'do',				// $iGroupKey
		'edit',				// $iDispOff
		NULL,				// $iDispOn
		'edit the list of groups'	// $iDescr
	      )
	    );
	    $out .= $oPage->ActionHeader($sHdr,$arActs);
	    $doEdit = ($oPage->PathArg('do') == 'edit');
	    */
	    $sSection = $this->GroupAdmin($doEdit);
	    break;
	}
	if (!is_null($sHdr)) {
	    $oHdr = new fcSectionHeader($sHdr,$oMenu);
	    $out .= $oHdr->Render()
	      .$sSection
	      ;
	}
	
	return $out;
    }

    //--single--//
    //++multiple++//
    
    public function AdminLine() {
	$sCatKey = $this->CatKey();
	$id = $this->GetKeyValue();
	$sCatKey = $this->CatKey();
	$sName = $this->NameString();
	$htSupp = $this->SelfLink($sCatKey.' - '.$sName);
	$ftActive = fcHTML::fromBool($this->isActive());

	$out = <<<__END__
    <td>$id</td>
    <td>$ftActive</td>
    <td>$htSupp</td>
__END__;
	return $out;
    }
    
    //--multiple--//
    //++dependent++//
    
    protected function AdminDependent() {
	return NULL;	// no Events listing
    }
    protected function SourceAdmin($doInact) {
	$tbl = $this->SCSourceTable();
	$id = $this->GetKeyValue();
	$sqlFilt = "(ID_Supplier=$id)"
	  .(
	    $doInact
	    ?NULL
	    :(' AND (ID_Supercede IS NULL)')
	    )
	  ;
	$rs = $tbl->SelectRecords($sqlFilt,'ID DESC');
	$out = $rs->AdminRows();
	return $out;
    }
    protected function GroupAdmin($doEdit) {
	$rbl = $this->SupplierCatalogGroupTable();
	$id = $this->GetKeyValue();
	$sqlFilt = "ID_Supplier=$id";
	$rs = $rbl->SelectRecords($sqlFilt,'Sort');
	$out = $rs->AdminList($doEdit,array('ID_Supplier'=>$id));
	return $out;
    }
    
    //--dependent--//
    
    // -- ADMIN WEB UI -- //

}