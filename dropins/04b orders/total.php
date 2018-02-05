<?php
/*
  FILE: dropins/orders/total.php -- helper class for checking totals
  HISTORY:
    2014-02-23 adapted from code in order.php
*/

class clsTotals {
    private $doAllMatch;
    private $arItems;

    public function __construct() {
	$this->doAllMatch = TRUE;
    }
    public function AddItem(clsTotal $oItem) {
	$sName = $oItem->Name();
	$this->arItems[$sName] = $oItem;
    }
    public function FoundMismatch($bMismatch=NULL) {
	if (!is_null($bMismatch)) {
	    $this->doAllMatch = $bMismatch;
	}
	return $this->doAllMatch;
    }
    public function RenderAll() {
	$out = NULL;
	foreach ($this->arItems as $sName => $oTotal) {
	    $out .= $oTotal->RenderLine();
	}
	return $out;
    }
}
class clsTotal {
    private $oRoot;
    private $sName;
    private $sDescr;
    private $nCalc;
    private $nSaved;
    private $htShow;
    private $htStatus;

    public function __construct($sName,$sDescr,$prcCalc,$prcSaved,$htShow) {
	$this->oRoot = NULL;
	$this->sName = $sName;
	$this->sDescr = $sDescr;
	$this->htShow = $htShow;
	$this->nCalc = (int)(round($prcCalc * 100));
	$this->nSaved = (int)(round($prcSaved * 100));
    }
    protected function Root() {
	return $this->oRoot;
    }
    public function Name() {
	return $this->sName;
    }
    public function Check() {
	$intCalc = $this->nCalc;
	$intSaved = $this->nSaved;
	if ($intSaved == $intCalc) {
	    $htOut = $prcSaved.'</td><td><font color=green>ok</font>';
	} else {
	    if ($intSaved < $intCalc) {
		$htOut = '<font color=blue>'.$prcSaved.'</font></td><td> under by <b>'.($prcCalc-$prcSaved).'</b>';
	    } else {
		$htOut = '<font color=red>'.$prcSaved.'</font></td><td> over by <b>'.($prcSaved-$prcCalc).'</b>';
	    }
	    $this->Root()->FoundMismatch(TRUE);	// calculations do not match saved balance
	}
	return $htOut;
    }
    protected function RenderSaved() {
	return	$this->nSaved;	// TODO: format nicely
    }
    protected function RenderDescr() {
	return $this->sDescr;
    }
    protected function DisplayValue() {
	if (is_null($this->htShow)) {
	    return $this->RenderSaved();
	} else {
	    return $this->htShow;
	}
    }
    public function RenderLine() {
	$htNum = $this->DisplayValue();
	$htDescr = $this->RenderDescr();
	return <<<__EOL__
  <tr>
    <td align=right>
      <b>$htDescr</b>: $
    </td>
    <td align=right>
      $htNum
    </td>
  </tr>
__EOL__;
    }
}