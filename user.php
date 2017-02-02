<?php
/*
  FILE: user.php
  HISTORY:
    2013-09-15 created for handling log-ins during checkout
    2013-09-25 split most of clsPageUser into clsPageLogin
    2013-10-10 split clsPageUser off to page-user.php to reduce unnecessary lib loading
    2016-12-21 chopped out some disused classes (preserved in "NOT USED/user.php")
*/

/*::::
  ROLE: this talks to the database (all rows in table)
*/
class vcUserTable extends fctUserAccts_admin {

    // ++ OVERRIDES ++ //

    protected function SingularName() {
	return 'vcUserRecord';
    }

    // -- OVERRIDES -- //

}
/*::::
  ROLE: this talks to the database (single row in table)
*/
class vcUserRecord extends frcUserAcct {

    // ++ CLASSES ++ //
    
    protected function ContactsClass() {
	return 'clsCusts';
    }
    
    // -- CLASSES -- //
    // ++ TABLES ++ //
    
    protected function ContactTable() {
	return $this->GetConnection()->MakeTableWrapper($this->ContactsClass());
    }
    
    // -- TABLES -- //
    // ++ RECORDS ++ //
    
    /*----
      RETURNS: recordset of customers for this user (NULL if none)
    */
    public function CustRecs() {
	throw new exception('CustRecs() is deprecated; call ContactRecords().');
    }
    public function ContactRecords() {
	$tCusts = $this->ContactTable();
	$rs = $tCusts->Recs_forUser($this->GetKeyValue());
	return $rs;
    }
    
    // -- RECORDS -- //
    // ++ FIELD VALUES ++ //

    public function UserName() {
	return $this->GetFieldValue('UserName');
    }
    
    // -- FIELD VALUES -- //
    // ++ FIELD CALCULATIONS ++ //

    public function FullName() {
	$sFullName = $this->Value('FullName');
	if (is_null($sFullName)) {
	    return $this->Value('UserName');
	} else {
	    return $this->Value('FullName');
	}
    }
    public function AuthValid($iPass) {
	// get salt for this user
	$sSalt = $this->Value('PassSalt');

	// hash salt+pass
	$sHashed = clsVbzUserTable::HashPass($sSalt,$iPass);
	// see if it matches
	return ($sHashed == $this->Value('PassHash'));
    }
}
