<?php
/*
  HISTORY:
    2014-09-28 split off from orders.php
*/

/*----------
  CLASS PAIR: order messages (table ord_msg)
*/
class clsOrderMsgs extends clsTable {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name('ord_msg');
	  $this->KeyName('ID');
	  $this->ClassSng('clsOrderMsg');
    }

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

	$arIns = array(
	  'ID_Ord'	=> $idOrder,
	  'ID_Pkg'	=> SQLValue($idPackage),	// might be NULL
	  'ID_Media'	=> SQLValue($idMedia),
	  'TxtFrom'	=> SQLValue($sTxtFrom),
	  'TxtTo'	=> SQLValue($sTxtTo),
	  'TxtRe'	=> SQLValue($sSubject),
	  'doRelay'	=> 'FALSE',	// 2010-09-23 this field needs to be re-thought
	  'WhenCreated'	=> 'NOW()',	// later: add this as an optional argument, if needed
	  'WhenEntered'	=> 'NOW()',
	  'WhenRelayed' => 'NULL',
	  'Message'	=> SQLValue($sMessage)
	  );
	return $this->Insert($arIns);
    }

    // -- ACTIONS -- //
    // ++ CALCULATIONS ++ //

    function Record_forOrder($idOrder,$idType=NULL) {
	$sqlTbl = $this->NameSQL();
	$sqlFilt = "ID_Ord=$idOrder";
	if (!is_null($idType)) {
	    $sqlFilt = "($sqlFilt) AND (ID_Media=$idType)";
	}
	$rc = $this->DataSet("WHERE $sqlFilt ORDER BY WhenCreated DESC LIMIT 1");
	$rc->NextRow();
	return $rc;
    }

    // -- CALCULATIONS -- //
}
class clsOrderMsg extends clsDataSet {

    // ++ FIELD ACCESS ++ //

    public function MessageText() {
	return $this->Value('Message');
    }

    // -- FIELD ACCESS -- //

}
