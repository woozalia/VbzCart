<?php
/*
  PART OF: VbzCart
  PURPOSE: UI classes for Images
    including Folders, since those are currently only used by Images.
  HISTORY:
    2013-11-13 extracted from page-cat.php
    2016-10-25 Updating for db.v2
*/
/*%%%%
  PURPOSE: extends clsImages to handle store UI interactions
*/
class clsImages_StoreUI extends clsImages {
    
    // ++ OVERRIDES ++ //

    protected function SingularName() {
	return 'clsImage_StoreUI';
    }
    
    // -- OVERRIDES -- //
        
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
    // ++ FIELD CALCULATIONS ++ //

    /*----
      RETURNS: description for the current image's size
    */
    public function SizeDescr() {
	$sType = $this->GetFieldValue('Ab_Size');
	return self::$arSzNames[$sType];
    }

    // -- FIELD CALCULATIONS -- //
    // ++ CLASS NAMES ++ //

    protected function TitlesClass() {
	return 'vctShopTitles';
    }
    protected function TitlesInfoClass() {
	return 'vcqtTitlesInfo';
    }

    // -- CLASS NAMES -- //
    // ++ TABLES ++ //
    
    protected function TitleInfoQuery() {
	return $this->Engine()->Make($this->TitlesInfoClass());
    }
    
    // -- TABLES -- //
    // ++ SHOPPING PAGES ++ //
    
    public function ExhibitSuperTitle() {
	$sAttr = $this->GetFieldValue('AttrDispl');
	$sSize = $this->SizeDescr();
	if (is_null($sAttr)) {
	    $htAttr = NULL;
	} else {
	    $htAttr = " in <b>$sAttr</b>";
	}
	return "<b>$sSize</b> image$htAttr for";
    }
    public function ExhibitMainTitle() {
	return $this->TitleRecord()->NameString();
    }
    /*----
      NOTE: in old code, this was put into the 'cat-image' page section:
	$this->Skin()->Content('cat-image',$rc->DoPage());
    */
    public function ExhibitContent() {
	return $this->DoPage();
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
	$idImg = $this->GetKeyValue();
	$doNav = FALSE;
// build list of available image sizes (except th and sm) in $sSizes
	$rsImgs = $this->Data_forSameAttr();
	$sSizes = NULL;
	while ($rsImgs->NextRow()) {
	    $sImgType = $rsImgs->GetFieldValue('Ab_Size');
	    if (!empty($sImgType)) {
		if (($sImgType != 'th') && ($sImgType != 'sm')) {
		    $sDesc = self::$arSzNames[$sImgType];
		    if ($rsImgs->GetKeyValue() == $idImg) {
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

	    if ($rsImgs->GetKeyValue() == $idImg) {
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

	$rcTitle = $this->TitleRecord();
	
// render the output
	if ($doNav) {
	    $htTitle = '<tr><td colspan=3 class=image-links>'.$rcTitle->Link().'ordering page</a></td></tr>';
	    $out = <<<__END__
<table class=image-navbar>
$ftSizes$ftAttrs
$htTitle
</table>
__END__;
	} else {
	    $out = '<br>';	// is this necessary?
	}
	$sTitle = $rcTitle->CatNum().' '.$rcTitle->NameString();
	// Image.DoPage() should only ever show one Image
	$out .= $this->RenderInline_row($sTitle,$this->Abbrev_forSize());

	return $out;
    }
    
    // -- SHOPPING PAGES -- //
    // ++ SHOPPING PAGE COMPONENTS ++ //

    /*----
      RETURNS: HTML for link to the page corresponding to the current image
      INPUT:
	$htContent = what should go between the <a> and </a> tags.
	  $htContent will be displayed raw, so it may contain HTML.
	$doAbsolute = TRUE: prepend the Title's URL; FALSE: just return a link relative to the Title's URL.
    */
    public function RenderPageLink($htContent,$doAbsolute=FALSE) {
	$sFldrRel = fcString::ConcatArray('-',array($this->Abbrev_forSize(),$this->Attrib_forFolder()));
//	if ($sFldrRel != '') {
//	    $sFldrRel .= '-';
//	}

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
      ACTION: Generate the HTML code to display an image for the current recordset
      INPUT: $sTitle - Title description
      HISTORY:
	2013-11-17
	  * Renamed from Image_HTML() to RenderInline()
	  * Moved from vbz-cat-image.php to vbz-cat-image-ui.php
    */
    public function RenderInline_rows($sTitle,$sSizeKey,array $arAttr=NULL) {
	$out = NULL;
	$this->RewindRows();
	while ($this->NextRow()) {
	    $out .= "\n".$this->RenderInline_row($arAttr,$sSizeKey);
	}
	return $out;
    }
    /*----
      QUESTION: Does anyone actually pass anything in $arAttr? (TODO)
      INPUT: $sTitle - Title description (append view string to this)
    */
    public function RenderInline_row($sTitle, $sSizeKey, array $arAttr=NULL) {
	/*
	$rcTi = $this->TitleInfoQuery()->GetRecord('ID='.$this->TitleID());

	$arAtHr = NULL;	// attributes for <a href>
	$arAtIm = NULL;	// attributed for <img>

	$htTiID = $rcTi->RenderIDAttr();
	$arAtHr['id'] = $htTiID;

	$arAtIm['title'] = $rcTi->ImageTitle();
	//*/
	
	// ++RenderSingle() -- eventually should replace this code
	//$sSzKey = $this->Value('Ab_Size');
	$arAtIm['class'] = 'image-title-'.$sSizeKey;
	$sView = $this->GetFieldValue('AttrDispl');
	if (!empty($sView)) {
	    $sTitle .= ' - '.$sView;
	}
	$arAtIm['title'] = $sTitle;
	$arAtIm['src'] = $this->WebSpec();
	$htAtIm = clsHTML::ArrayToAttrs($arAtIm);

	$htImg = "\n<img$htAtIm />";
	// --RenderSingle()
	
	//$htTi = $rcTi->ShopLink($htImg,$arAtHr);
	return $htImg;
    }
    /*----
      NOTE: Do not put a \n before or after the image tag, because that causes a gap to be rendered --
	which will then be underlined if the image is surrounded by a link.
      INPUT:
	$sText: popup text to display (title of image tag)
    */
    public function RenderSingle($sText,$sSizeKey) {
	//$sSzKey = $this->Value('Ab_Size');
	$sThisKey = $this->GetFieldValue('AttrDispl');
	if (is_null($sThisKey)) {
	    $sPopup = NULL;
	} else {
	    $sPopup = "($sThisKey) ";
	}
	$sPopup .= $sText;
	$arAtIm = array(
	  'title' => $sPopup,
	  'src' => $this->WebSpec(),
	  'class' => 'image-title-'.$sSizeKey
	  );
	$htAtIm = fcHTML::ArrayToAttrs($arAtIm);

	$htImg = "<img$htAtIm />";
	return $htImg;
    }
    
    // -- SHOPPING PAGE COMPONENTS -- //
    
}
