<?php
/*
  PURPOSE: classes for calculating expected restock items
  NICKNAME: The Expected Items Query
  HISTORY:
    2016-01-10 created -- special classes for complex queries seem to be a good idea
    2017-01-06 updated somewhat
*/

class vctaRRQIs_exp extends fcTable_wSource_wRecords {
    use ftLinkableTable;

    // ++ SETUP ++ //
    
    // CONCRETE
    protected function SingularName() {
	return 'vcraRRQI_exp';
    }
    // OVERRIDE
    public function GetActionKey() {
	return 'rix';	// restock items expected
    }

    // -- SETUP -- //
    // ++ CLASSES ++ //

    protected function RequestItemsClass() {
	return KS_ADMIN_CLASS_RESTOCK_REQ_ITEMS;
    }
    protected function ReceivedsClass() {
	return KS_LOGIC_CLASS_RESTOCKS_RECEIVED;
    }
    protected function ReceivedLinesClass() {
	return KS_LOGIC_CLASS_RESTOCK_LINES_RECEIVED;
    }

    // -- CLASSES -- //
    // ++ TABLES ++ //

    protected function RequestItemTable($id=NULL) {
	return $this->Engine()->Make($this->RequestItemsClass(),$id);
    }
    protected function ReceivedTable($id=NULL) {
	return $this->Engine()->Make($this->ReceivedsClass(),$id);
    }
    protected function ReceivedLineTable($id=NULL) {
	return $this->Engine()->Make($this->ReceivedLinesClass(),$id);
    }

    // -- TABLES -- //
    // ++ RECORDS ++ //
    
    /*----
      RETURNS: recordset of items in currently expected restocks
	i.e. items requested but not yet fulfilled or cancelled
      REPLACES: http://htyp.org/VbzCart/queries/qryRstkItms_expected
    */
    protected function ExpectedItemRecords() {
	$sql = $this->SQLstr_Items_expected();
	$rs = $this->DataSQL($sql);
	return $rs;
    }
    
    // -- RECORDS -- //
    // ++ ARRAYS ++ //

    /*----
      ACTION: Gets a recordset of restock items we are still expecting to receive,
	and converts it to an array.
      RETURNS: the resulting array, or NULL if no items are currently expected
      HISTORY:
	2016-01-10 This used to execute qryRstkItms_expected_byItem directly, but stored queries
	are being phased out.
    */
    public function ExpectedItemArray() {
	$rs = $this->ExpectedItemRecords();
	return $rs->ExpectedItemArray();
    }
    
    // -- ARRAY -- //
    // ++ SQL CALCULATIONS ++ //

    //+objects+//
    
    /*----
      RETURNS: SQL object for creating recordset of expected restock items,
	i.e. requested items minus amounts already received
    */
    protected function SQLobj_Items_expected() {
	$qryRCL = new fcSQL_Table($this->ReceivedLineTable()->NameSQL(),'rcl');
	$qryRC = new fcSQL_Table($this->ReceivedTable()->NameSQL(),'rc');
	
	// items from active restock requests
	$qryActv = $this->RequestItemTable()->SQLobj_Items_active();
	$qryActv->Alias('rqi');	// request items
	
	// lines from received restocks
	$qryJ1 = new fcSQL_Join($qryRCL,$qryRC,array('rcl.ID_Parent'=>'rc.ID'));
	
	// [lines from received restocks] JOIN [items from active restock requests]
	$qryJ2 = new fcSQL_Join($qryJ1,$qryActv,array(
	    'rc.ID_Request'=>'rqi.ID_Parent',
	    'rcl.ID_Item'=>'rqi.ID_Item'
	    )
	  );
	$qryJ2->FieldsArray(
	  array(
	    'SUM(rqi.QtyExp)' => 'QtyExp',
	    'SUM(IFNULL(rcl.QtyRecd,rcl.QtyFiled))' => 'QtyRecd'
	    )
	  );
	  
	return $qryJ2;
    }
    
    //-objects-//
    //+strings+//
    
    protected function SQLstr_Items_expected() {
	$sql = $this->SQLobj_Items_expected()->Render();
	return $sql;
    }
    
    //-strings-//

    // -- SQL CALCULATIONS -- //

}

class vcraRRQI_exp extends vcAdminRecordset {

    // ++ FIELD VALUES ++ //
    
    protected function ItemID() {
	return $this->Value('ID_Item');
    }
    protected function QuantityExpectedOriginally() {
	return $this->Value('QtyExp');
    }
    protected function QuantityReceived() {
	return $this->Value('QtyRecd');
    }
    protected function QuantityExpectedStill() {
	return $this->QuantityExpectedOriginally() - $this->QuantityReceived();
    }
    
    // -- FIELD VALUES -- //
    // ++ ARRAYS ++ //
    
    // PUBLIC so Table object can use it
    public function ExpectedItemArray() {
	if ($this->hasRows()) {
	    while ($this->NextRow()) {
		$id = (int)$this->ItemID();
		$arOut[$id]['ord'] = $this->QuantityExpectedOriginally();	// qty on order
		$arOut[$id]['rcd'] = $this->QuantityReceived();		// qty received so far
		$arOut[$id]['exp'] = $this->QuantityExpectedStill();	// qty still expected
	    }
	    return $arOut;
	} else {
	    return NULL;
	}
    }
    
    // -- ARRAYS -- //
}