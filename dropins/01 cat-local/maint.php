<?php
/*
  PART OF: VbzCart admin interface
  PURPOSE: classes for catalog maintenance
  HISTORY:
    2015-11-30 created
*/
class vcCatalogMaintenance extends fcJavaScript {

    // ++ DROP-IN API ++ //

    /*----
      PURPOSE: execution method called by dropin menu
      FUTURE: put the function JS (but not the argument-passing)
	in a separate page output section (before all the HTML), just for tidyness.
      RULES: 
	Before setting controls, we have to emit the HTML for those controls.
	We have to load all the functions before we can pass any arguments to them.
	Menu-item JavaScript must be emitted *after* the menu-item HTML *and* the JS functions.
	Menu-item HTML has to go *after* the other controls (for layout reasons).
    */
    public function MenuExec(array $arArgs=NULL) {
	$this->StartMenu();	// clear HTML/JS output accumulators
	// build HTML and JS components of menu system (but don't emit yet):
	$this->RenderMenuItem(
	  'ajaxtest',				// DOC ID of menu item
	  'AJAX test',				// text to display for menu item
	  'https://vbz.net/ajax?do=test',	// URL to start the process
	  'https://vbz.net/ajax'		// URL to check on the process
	  );
	$this->RenderMenuItem(
	  'build-cat',
	  'Build Catalog',
	  'https://vbz.net/ajax?do=buildcat',	// URL to start the process
	  'https://vbz.net/ajax'		// URL to check on the process
	  );
	$this->RenderMenuItem(
	  'clear',
	  '(clear output)',
	  'https://vbz.net/ajax?do=@clear',	// URL to start the process
	  'https://vbz.net/ajax'		// URL to check on the process
	  );
	  
	$out = "\n<div style='float:right;'><ul class=menu>"
	  .$this->GetMenuHTML()
	  ."\n</ul>"
	  ."\n</div>"
	  .'<div style="font-size: 8pt;">'
	    .'<input type=checkbox id=chkRun checked title="uncheck to stop reloading">'
	    .'<b>Status</b>: <span id=status>[status]</span>'
	  .'</div>'
	  .'<div id=main>This page deliberately left blank. (Oops.)</div>'
	  ."\n<script>"
	  .self::StatusUpdater_Controls(	// main controls JS
	    'main',	// DOM ID for output from the process
	    'status',	// DOM ID for comm status
	    'chkRun'	// DOM ID for start/stop checkbox
	    )
	  .self::StatusUpdater_Functions()	// requires StatusUpdater_Controls
	  .$this->GetMenuJS()
	  ."\n</script>"
	  ;
	
	return $out;
    }
    
    // -- DROP-IN API -- //
    // ++ PAGE COMPONENTS ++ //
        
    /*
      This is probably needlessly subdivided, but for now it's helping me keep things straight.
    */
    
    protected function StartMenu() {
	$this->StartMenuHTML();
	$this->StartMenuJS();
    }
    /*----
      INPUT:
	$sidClick = DOM ID of menu item's display element
	$sText = text to display for menu item
	$urlStart = URL to start the process
	$urlCheck = URL to check on the process
    */
    protected function RenderMenuItem($sidClick,$sText,$urlStart,$urlCheck) {
	$ht = $this->RenderMenuItem_front($sidClick,$sText);
	$this->AddToMenuHTML($ht);
	$js = $this->RenderMenuItem_back($sidClick,$urlStart,$urlCheck);
	$this->AddToMenuJS($js);
    }
    
    private $jsMenu;
    protected function StartMenuJS() {
	$this->jsMenu = NULL;
    }
    protected function AddToMenuJS($js) {
	$this->jsMenu .= $js;
    }
    protected function GetMenuJS() {
	return $this->jsMenu;
    }
    private $htMenu;
    protected function StartMenuHTML() {
	$this->htMenu = NULL;
    }
    protected function AddToMenuHTML($ht) {
	$this->htMenu .= $ht;
    }
    protected function GetMenuHTML() {
	return $this->htMenu;
    }
    protected function RenderMenuItem_front($sName,$sText) {
	return "\n  <li><a href='#' id='$sName'>$sText</a></li>";
    }
    /*----
      INPUT:
	$sidClick = DOM ID of element to start proces when clicked
	$urlStart = URL to start the process
	$urlCheck = URL to check on the process
    */
    protected function RenderMenuItem_back($sidClick,$urlStart,$urlCheck) {
	return self::StatusUpdater_MenuItem($sidClick,$urlStart,$urlCheck);
    }
    
    // -- PAGE COMPONENTS -- //
}
