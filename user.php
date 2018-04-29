<?php
/*
  FILE: user.php
  PURPOSE: I *think* the idea here is to allow additional stuff to be attached to user accounts --
    but I'm no longer (2017-03-28) sure if these vc classes are actually needed or even used anymore.
  HISTORY:
    2013-09-15 created for handling log-ins during checkout
    2013-09-25 split most of clsPageUser into clsPageLogin
    2013-10-10 split clsPageUser off to page-user.php to reduce unnecessary lib loading
    2016-12-21 chopped out some disused classes (preserved in "NOT USED/user.php")
    2018-02-27 vcUserTable now derived from fctUserAccts, not fctUserAccts_admin
*/

/*::::
  ROLE: this talks to the database (all rows in table)
*/
class vcUserTable extends fctUserAccts {

    // ++ SETUP ++ //

    // OVERRIDE
    protected function SingularName() {
	return 'vcUserRecord';
    }

    // -- SETUP -- //

}
/*::::
  ROLE: this talks to the database (single row in table)
*/
class vcUserRecord extends fcrUserAcct {

    // ++ CLASSES ++ //
    
    protected function ContactsClass() {
	return 'vctCusts';
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

    /* 2017-03-28 These should be redundant now.
    public function LoginName() {
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
    } */
    public function AuthValid($iPass) {
	throw new exception('2018-02-27 Is anything still using this? Seems probably obsolete.');
	// get salt for this user
	$sSalt = $this->Value('PassSalt');

	// hash salt+pass
	$sHashed = clsVbzUserTable::HashPass($sSalt,$iPass);
	// see if it matches
	return ($sHashed == $this->Value('PassHash'));
    }
    /*----
      NOTE: This is a kluge. 
	It's used for the checkout pages, so we can greet logged-in users.
	We don't have the dropins loaded during the checkout, so can't access the proper
	  self-linking functions. (This needs some thought. We really don't *need*
	  the dropins; all we need to know is the URL for the user profile. Maybe that
	  should be another type of shopping page, outside of admin space.)
	For now, this isn't even a link, because we don't really have a suitable profile page.
    */
    public function SelfLink_name() {
	return $this->FullName();
    }
}
