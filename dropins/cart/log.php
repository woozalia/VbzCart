<?php
/*
  NOTES:
    2010-10-16 Should this eventually be folded into the universal event log, or not?
  HISTORY:
    2014-01-20 extracted from cart.php
*/
class VCT_CartLog_admin extends clsCartLog {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('VCR_CartEvent_admin');	// override parent
    }
    /*----
      HISTORY:
	2010-10-16 Created
      ACTION: By default, shows event counts by day and by IP address
    */
    public function AdminPage() {
	global $wgOut;
	global $vgOut,$vgPage;

	$vgPage->UseHTML();

	$out = "\n<b>Show</b>: ";
	$arMenu = array(
	  'date'	=> '\by date\show how many events on each date',
	  'addr'	=> '\by IP\show how many events for each IP address',
	  'list'	=> '\*list*\list cart events in chronological order');
	$strShow = $vgPage->Arg('show');
	$out .= $vgPage->SelfLinkMenu('show',$arMenu,$strShow);

	switch ($strShow) {
	  case 'list':
	      $objRows = $this->GetData();
	      $out .= $objRows->AdminTable();
	    break;
	  case 'date':
	    $strDate = $vgPage->Arg('date');
	    if (is_string($strDate)) {
		$objRows = $this->GetData('DATE(WhenDone)="'.$strDate.'"');
		$out .= $objRows->AdminTable();
	    } else {
		$arFldsDate = array(
		  'DateDone'	=> 'DATE(WhenDone)',
		  'HowMany'	=> 'COUNT(ID)');
		$objRows = $this->DataSetGroup($arFldsDate,'DateDone','DateDone DESC');

		if ($objRows->hasRows()) {
		    $isOdd = TRUE;
		    $out .= "\n<table>";
		    while ($objRows->NextRow()) {
			$wtStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
			$isOdd = !$isOdd;

			$row = $objRows->Row;
			$strDate = $row['DateDone'];
			$intCount = $row['HowMany'];
			$arLink['date'] = $strDate;
			$ftDate = $vgPage->SelfLink($arLink,$strDate);
			$out .= "\n<tr style=\"$wtStyle\"\n><td>$ftDate</td><td align=right>$intCount</td></tr>";
		    }
		    $out .= "\n</table>";
		} else {
		    $out = 'No cart events in database yet.';
		}
	    }
	  break;

	  case 'addr':
	    $strAddr = $vgPage->Arg('addr');
	    if (is_string($strAddr)) {
		$objRows = $this->GetData('Machine="'.$strAddr.'"');
		$out .= $objRows->AdminTable();
	    } else {
		$arFldsAddr = array(
		  'Addr'	=> 'Machine',
		  'AddrNum'	=> 'INET_ATON(Machine)',
		  'HowMany'	=> 'COUNT(ID)');
		$objRows = $this->DataSetGroup($arFldsAddr,'Machine','HowMany DESC');
		if ($objRows->hasRows()) {
		    $isOdd = TRUE;
		    $out .= "\n<table>";
		    while ($objRows->NextRow()) {
			$wtStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
			$isOdd = !$isOdd;

			$row = $objRows->Row;
			$strAddr = $row['Addr'];
			$intCount = $row['HowMany'];
			$arLink['addr'] = $strAddr;
			$ftAddr = $vgPage->SelfLink($arLink,$strAddr);
			$out .= "\n<tr style=\"$wtStyle\"\n><td>$ftAddr</td><td align=right>$intCount</td></tr>";
		    }
		    $out .= "\n</table>";
		} else {
		    $out = 'No cart events in database yet.';
		}
	    }
	  break;
	}
	$wgOut->AddHTML($out); $out = '';
	return $out;
    }
}
class VCR_CartEvent_admin extends clsCartEvent {

    public function AdminTable() {
	if ($this->hasRows()) {
	    //$htUnknown = '<span style="color: #888888;">?</span>';
	    $out = "\n<table class=listing>";
	    $out .= "\n<tr>\n<th>ID</th><th>Cart</th><th>Sess</th><th>When</th><th>Who/How</th><th>Code</th><th>Description</th></tr>";
	    $isOdd = TRUE;
	    $strDateLast = NULL;
	    while ($this->NextRow()) {
		$wtStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
		$isOdd = !$isOdd;

		$row = $this->Values();

		$htWho		= $this->WhoString();

		$htCode		= $row['WhatCode'];

		$htDescr	= $row['WhatDescr'];
		$strNotes	= $row['Notes'];
		if (!is_null($strNotes)) {
		    $htDescr .= " <i>$strNotes</i>";
		}

		$id		= $row['ID'];
		$idCart		= $row['ID_Cart'];
		$idSess		= $row['ID_Sess'];
		$strWhen	= $row['WhenDone'];

		$dtWhen		= strtotime($strWhen);
		$strDate	= date('Y-m-d',$dtWhen);
		$strTime = date('H:i:s',$dtWhen);
		if ($strDate != $strDateLast) {
		    $strDateLast = $strDate;
		    $out .= "\n<tr style=\"background: #444466; color: #ffffff;\"\n><td colspan=4><b>$strDate</b></td></tr>";
		}

		$out .=
		   "\n<tr style=\"$wtStyle\">"
		  ."\n<td>$id</td>"
		  ."<td>$idCart</td>"
		  ."<td>$idSess</td>"
		  ."<td>$strTime</td>"
		  ."<td>$htWho</td>"
		  ."<td>$htCode</td>"
		  ."<td>$htDescr</td>"
		  ."</tr>";
	    }
	    $out .= "\n</table>";
	} else {
	    $out = 'No events logged yet.';
	}
	return $out;
    }
}