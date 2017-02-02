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

class vcraSCSupplier extends VC_Supplier {

    // ++ ADMIN WEB UI ++ //
    
    //++single++//
    
    /*----
      NOTE: We don't edit the Supplier here, so all the controls and code for that
	can be omitted. Also, we don't really need to display anything about the record
	except the Supplier's name; this is about SCM dependent records.
    */
    protected function AdminPage() {
	$oPage = $this->Engine()->App()->Page();

	$sShow = $oPage->PathArg('show');	// subpage to show
	
	$sName = $this->NameString();
	
	$arActs = array(
	  new clsAction_section('Manage'),	// menu divider
	  new clsActionLink_option(array(),
	    'cat',
	    'show',
	    'catalogs',
	    NULL,
	    'wholesale catalogs from '.$sName
	  ),
	  new clsActionLink_option(array(),
	    'ctg',
	    'show',
	    'catalog groups',
	    NULL,
	    "groups for organizing $sName catalog items"
	  )
	);
	$oPage->PageHeaderWidgets($arActs);
	$oPage->TitleString('SCM:'.$sName);
	
	// temporarily replace Action Key so we can link back to base admin page for this Supplier
	$sActKey = $this->Table()->ActionKey();
	$this->Table()->ActionKey(KS_ACTION_CATALOG_SUPPLIER);
	$out = 'Catalog Management for '.$this->SelfLink_name();
	$this->Table()->ActionKey($sActKey);	// restore SCM action key

	switch ($sShow) {
	  case 'cat':
	    $sHdr = 'Catalogs';
	    $sKey = $this->SCSourceTable()->ActionKey();
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
	    $out .= $this->SourceAdmin($doInact);
	    break;
	  case 'ctg':
	    $sHdr = 'Catalog Groups';
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
	    $out .= $this->GroupAdmin($doEdit);
	    break;
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
	$ftActive = clsHTML::fromBool($this->isActive());

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