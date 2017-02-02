<?php
/*
  HISTORY:
    2014-09-28 split off from orders.php
    2016-11-04 updated to db.v2
*/

/*----------
  CLASS PAIR: order messages (table ord_msg)
*/
class vctOrderMsgs extends vcBasicTable {

    // ++ CEMENTING ++ //
    
    protected function TableName() {
	return 'ord_msg';
    }
    protected function SingularName() {
	return 'vcrOrderMsg';
    }

    // -- CEMENTING -- //
    // ++ ACTIONS ++ //

    /*----
      ACTION: Adds a message to the order
      INPUT:
	$iMedia: ord_msg.ID_Media
    */
    public function Add(
      $idOrder,
      $idPackage,
      $idMedia,
      $sTxtFrom,
      $sTxtTo,
      $sSubject,
      $sMessage) {
	
	$db = $this->GetConnection();
	$arIns = array(
	  'ID_Ord'	=> $idOrder,
	  'ID_Pkg'	=> $db->Sanitize_andQuote($idPackage),	// might be NULL
	  'ID_Media'	=> $db->Sanitize_andQuote($idMedia),
	  'TxtFrom'	=> $db->Sanitize_andQuote($sTxtFrom),
	  'TxtTo'	=> $db->Sanitize_andQuote($sTxtTo),
	  'TxtRe'	=> $db->Sanitize_andQuote($sSubject),
	  'doRelay'	=> 'FALSE',	// 2010-09-23 this field needs to be re-thought
	  'WhenCreated'	=> 'NOW()',	// later: add this as an optional argument, if needed
	  'WhenEntered'	=> 'NOW()',
	  'WhenRelayed' => 'NULL',
	  'Message'	=> $db->Sanitize_andQuote($sMessage)
	  );
	return $this->Insert($arIns);
    }

    // -- ACTIONS -- //
    // ++ CALCULATIONS ++ //

    function Record_forOrder($idOrder,$idType=NULL) {
    
	/* 2016-11-05 old code
	$sqlTbl = $this->NameSQL();
	$sqlFilt = "ID_Ord=$idOrder";
	if (!is_null($idType)) {
	    $sqlFilt = "($sqlFilt) AND (ID_Media=$idType)";
	}
	$rc = $this->DataSet("WHERE $sqlFilt ORDER BY WhenCreated DESC LIMIT 1");
	*/
	
	$sqlFilt = "ID_Ord=$idOrder";
	if (!is_null($idType)) {
	    $sqlFilt = "($sqlFilt) AND (ID_Media=$idType)";
	}
	$rc = $this->SelectRecords($sqlFilt,'WhenCreated DESC','LIMIT 1');
	
	$rc->NextRow();
	return $rc;
    }

    // -- CALCULATIONS -- //
}
class vcrOrderMsg extends vcBasicRecordset {

    // ++ FIELD VALUES ++ //

    public function MessageText() {
	return $this->GetFieldValue('Message');
    }

    // -- FIELD VALUES -- //

}
