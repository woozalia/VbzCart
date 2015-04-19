<?php
/*
  PART OF: VbzCart
  PURPOSE: UI classes for Images
    including Folders, since those are currently only used by Images.
  HISTORY:
    2013-11-13 extracted from page-cat.php
*/
/*%%%%
  PURPOSE: extends clsImages to handle store UI interactions
*/
class clsImages_StoreUI extends clsImages {
    private $objPage;

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('clsImage_StoreUI');
    }
    public function Page(clsVbzPage $iPage=NULL) {
	if (!is_null($iPage)) {
	    $this->objPage = $iPage;
	}
	if (!is_object($this->objPage)) {
	    throw new exception('Internal error: must set page before trying to read it.');
	}
	return $this->objPage;
    }
}
class clsImage_StoreUI extends clsImage {

    // ++ STATIC ++ //

    static protected function NavbarLine($sType,$sLabel) {
	$out = <<<__END__
<tr>
<td><span class=row-label>$sType</span> &gt;</td>
<td class=image-links>$sLabel</td>
<td>&lt; <span class=row-label>$sType</span></td>
</tr>
__END__;
	return $out;
    }

    static public function RenderUnavailable() {
	return <<<__END__
<table class=border cellpadding="5">
  <tbody>
    <tr>
      <td>
	<table class="hdr" cellpadding="2">
	  <tbody>
	    <tr>
	      <td align="center">
		<span class="page-title">No Images
		<br>Available
		<br></span>for this item
		<br><b>:-(</b>
	      </td>
	    </tr>
	  </tbody>
	</table>
      </td>
    </tr>
  </tbody>
</table>
__END__;
    }

    // -- STATIC -- //
    // ++ STATUS ACCESS ++ //

    /*----
      RETURNS: description for the current image's size
    */
    public function SizeDescr() {
	$sType = $this->Value('Ab_Size');
	return self::$arSzNames[$sType];
    }

    // -- STATUS ACCESS -- //
    // ++ CLASS NAMES ++ //

    protected function TitlesClass() {
	return 'clsTitles_StoreUI';
    }

    // -- CLASS NAMES -- //
    // ++ SHOPPING WEB UI ++ //

    /*----
      RETURNS: HTML for link to the page corresponding to the current image
      INPUT:
	$htContent = what should go between the <a> and </a> tags.
	  $htContent will be displayed raw, so it may contain HTML.
	$doAbsolute = TRUE: prepend the Title's URL; FALSE: just return a link relative to the Title's URL.
    */
    public function RenderPageLink($htContent,$doAbsolute=FALSE) {
	$sFldrRel = $this->Attrib_forFolder();
	if ($sFldrRel != '') {
	    $sFldrRel .= '-';
	}
	$sFldrRel .= $this->Abbrev_forSize();

	if ($doAbsolute) {
	    $sFldr = $this->TitleObj()->URL().'/'.$sFldrRel;
	} else {
	    $sFldr = $sFldrRel;
	}
	return "<a href='$sFldr'>$htContent</a>";
    }
    /*----
      TODO: rename or deprecate this -- the name is misleading; it's actually the href
	to the *title*...
    */
    public function Href($iAbs=false) {
	throw new exception('Href() is deprecated; use RenderPageLink() instead.');
    
	$strFldrRel = $this->Attrib_forFolder();
	if ($strFldrRel) {
	    $strFldrRel .= '-';
	}
	$strFldrRel .= $this->Abbrev_forSize();

	if ($iAbs) {
	    $strFldr = $this->TitleObj()->URL().'/'.$strFldrRel;
	} else {
	    $strFldr = $strFldrRel;
	}
	return '<a href="'.$strFldr.'/">';
    }
    /*-----
      ACTION: outputs a standalone page for larger image sizes - does not use skin
	(Was originally in clsImage, where skin was inaccessible.)
      HISTORY:
	2012-07-14 moved from clsImage to clsImage_StoreUI
	  Returns output instead of echoing it.
	2013-11-24 fixed lots of bugs; removed extra HTML header stuff
    */
    public function DoPage() {
	$idImg = $this->KeyValue();
	$doNav = FALSE;
// build list of available image sizes (except th and sm) in $sSizes
	$rsImgs = $this->Data_forSameAttr();
	$sSizes = NULL;
	while ($rsImgs->NextRow()) {
	    $sImgType = $rsImgs->Value('Ab_Size');
	    if (!empty($sImgType)) {
		if (($sImgType != 'th') && ($sImgType != 'sm')) {
		    $sDesc = self::$arSzNames[$sImgType];
		    if ($rsImgs->KeyValue() == $idImg) {
			$htImgTag = '<b>'.$sDesc.'</b>';
		    } else {
			$htImgTag = $rsImgs->RenderPageLink($sDesc,TRUE);
		    }
		    if (!empty($sSizes)) {
			$sSizes .= ' .. ';
		    }
		    $sSizes .= $htImgTag;
		}
	    }
	}

	$ftSizes = NULL;
	if (!is_null($sSizes)) {
	    $doNav = TRUE;
	    $ftSizes = self::NavbarLine('sizes',$sSizes);
	}

// build list of available images for this title at this size in $sAttrs
	$sAttrs = NULL;
	$isFirst = TRUE;
	$rsImgs = $this->Data_forSameSize();
	while ($rsImgs->NextRow()) {
	    $sImgFldr = $rsImgs->Attrib_forFolder();
	    $sImgDescr = $rsImgs->Attrib_forDisplay();
	    if ($isFirst) {
		$isFirst = FALSE;
	    } else {
		$sAttrs .= ' .. ';
	    }
	    if (empty($sImgDescr)) {
		$sImgDescr = '(standard)';
	    }

	    if ($rsImgs->KeyValue() == $idImg) {
		$sAttrs .= '<b>'.$sImgDescr.'</b>';
	    } else {
		$sAttrs .= $rsImgs->RenderPageLink($sImgDescr,TRUE);
	    }
	}

	$ftAttrs = NULL;
	if (!is_null($sAttrs)) {
	    $doNav = TRUE;
	    $ftAttrs = self::NavbarLine('views',$sAttrs);
	}

// render the output
	if ($doNav) {
	    $htTitle = '<tr><td colspan=3 class=image-links>'.$this->TitleObj()->Link().'ordering page</a></td></tr>';
	    $out = <<<__END__
<table class=image-navbar>
$ftSizes$ftAttrs
$htTitle
</table>
__END__;
	} else {
	    $out = '<br>';	// is this necessary?
	}
	$out .= $this->RenderInline_rows();

	return $out;
    }
    /*-----
      ACTION: Generate the HTML code to display an image for the current recordset
      INPUT: array
	[title] - string for image title
      HISTORY:
	2013-11-17
	  * Renamed from Image_HTML() to RenderInline()
	  * Moved from vbz-cat-image.php to vbz-cat-image-ui.php
    */
    public function RenderInline_rows(array $arAttr=NULL) {
	$out = NULL;
	$this->RewindRows();
	while ($this->NextRow()) {
	    $out .= "\n".$this->RenderInline_row($arAttr);
	}
	return $out;
    }
    /*----
      TODO: Optimize this by only calculating the Title stuff when the Title changes
	-- move those calculations to RenderInline_rows().
	* Does anyone actually pass anything in $arAttr?
    */
    public function RenderInline_row(array $arAttr=NULL) {
	$rcTi = $this->TitleRecord();

	$arAtHr = NULL;	// attributes for <a href>
	$arAtIm = NULL;	// attributed for <img>

	$htTiID = $rcTi->RenderIDAttr();
	$arAtHr['id'] = $htTiID;

	$arAtIm['title'] = $rcTi->ImageTitle();
	$sSzKey = $this->Value('Ab_Size');
	$arAtIm['class'] = 'image-title-'.$sSzKey;
	$sDispl = $this->Value('AttrDispl');
	if (!empty($sDispl)) {
	    $arAtIm['title'] .= ' - '.$sDispl;
	}
	$arAtIm['src'] = $this->WebSpec();
	$htAtIm = clsHTML::ArrayToAttrs($arAtIm);

	$htImg = "\n<img$htAtIm />";
	$htTi = $rcTi->ShopLink($htImg,$arAtHr);
	return $htTi;
    }
    /*-----
      ACTION: Generate the HTML code to display all images in the current dataset
      INPUT:
	iarAttr: array of attributes to apply to all images
	  [name]=value of attribute to use on attribute "name"
      HISTORY:
	2013-11-17
	  * Renamed from Images_HTML() to RenderInline_set()
	  * Moved from vbz-cat-image.php to vbz-cat-image-ui.php
    */
    public function RenderInline_set(array $arAttr=NULL) {
	throw new exception('RenderInline_set() is deprecated; call RenderInline_rows().');
	$out = "\n<!-- +METHOD: ".__METHOD__.' -->';
	while ($this->NextRow()) {
	    $out .= $this->RenderInline($arAttr);
	}
	$out .= "\n<!-- -METHOD: ".__METHOD__.' -->';
	return $out;
    }
    public function RenderInline(array $iarAttr=NULL) {
	throw new exception('RenderInline() is deprecated; call RenderInLine_row() or RenderInline_rows().');
	$sDispl = $this->Value('AttrDispl');
	$sSzKey = $this->Value('Ab_Size');
	if (!empty($sDispl)) {
	    nzApp($iarAttr['title'],' - '.$sDispl);
	}
	$iarAttr['src'] = $this->WebSpec();
	$htAttr = clsHTML::ArrayToAttrs($iarAttr);
	$htClass = 'class="image-title-'.$sSzKey.'" ';
	return "\n<img$htAttr $htClass/>";
    }
    public function RenderRecord($sTitle) {
	throw new exception('Who calls RenderRecord()? Perhaps they can call RenderInline_row() instead.');
	$sSzKey = $this->Value('Ab_Size');
	$url = $this->WebSpec();
	$arAttr = array(
	  'title'	=> htmlspecialchars($sTitle),
	  'class'	=> 'image-title-'.$sSzKey,
	  'src'		=> $url
	  );
	$htAttr = clsHTML::ArrayToAttrs($arAttr);
	return "\n<img$htAttr/>";
    }
    /*----
      ACTION: Renders the current recordset as a series of inline images, using
	the given Title recordset to generate the appropriate links
    */
    /* 2014-08-14 is this even needed anymore?
    public function RenderRecordset_Titles_inline(clsVbzTitle $rsTitles) {
	// first, load the title records into an array for quick access
	$arTtl = $rsTitles->LoadArray();

	$htOut = NULL;
	while ($this->NextRow()) {
	    $sAttr = $this->AttrDescr();
	    if (is_null($sAttr)) {
		$ftAttr = '';
	    } else {
		$ftAttr = ' - '.$sAttr;
	    }
	    $idTtl = $this->TitleID();
	    if ($idTtl != $rsTitles->KeyValue()) {
		$rsTitles->Values($arTtl[$idTtl]);
	    }
	    $sTitleDescr = $rsTitles->TitleStr().$ftAttr;
	    $htOut .= $rsTitles->ShopLink($this->RenderRecord($sTitleDescr));
	}
	return $htOut;
    } */
    /*-----
      PURPOSE: like RenderInline_set, but attributes are title-specific
      ACTION: Generate the HTML code to display all images in the current dataset
      INPUT:
	iarTAttr: array of title-specific attributes
	  [id][name]=value to use on attribute "name" for title "id"
	iarTLink: array of title-specific links
	  [id] = URL
      HISTORY:
	2013-11-18 created
	2014-03-22 obsolete; replacing with RenderRecordset_Titles_inline()
    */
    /*
    public function RenderInLine_set_Titles(array $arTtlAttr,array $arTtlURLs) {
	$out = NULL;
	while ($this->NextRow()) {
	    $idT = $this->Value('ID_Title');
	    $arA = $arTtlAttr[$idT];
	    $out .= '<a href="'.$arTtlURLs[$idT].'">'
	      .$this->RenderInline($arA)
	      .'</a>';
	}
	return $out;
    }*/

    // -- SHOPPING WEB UI -- //
}
