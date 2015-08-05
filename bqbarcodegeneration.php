<?php
/**
* Bgbarcodegeneration module
*
* @short description    generate and print UPC and EAN13 barcode products
*
* @author    Boutiquet
*
* @copyright Boutiquet
*
* @version    1.6
*
* @license   copyright Boutiquet
*/

if (!defined('_PS_VERSION_') && function_exists('curl_init'))
	exit;
require(_PS_ROOT_DIR_.'/tools/tcpdf/tcpdf.php');
require_once(_PS_ROOT_DIR_.'/modules/bqbarcodegeneration/classes/tcpdf_static.php');

class Bqbarcodegeneration extends Module
{
	private $html = '';
	private $posterrors = array();
	private $bean;
	private $bupc;
	private $gs1;
	public $outputhtml;
	public function __construct()
	{
		$this->name = 'bqbarcodegeneration';
		$this->tab = 'administration';
		$this->need_instance = 0;
		$this->module_key = 'e1a081f02de7e65c4e6b1665e0f6a442';
		parent::__construct();
		$this->displayName = $this->l('EAN - UPC codes generator');
		$this->description = $this->l('Generates EAN and UPC codes.');
		$this->confirmUninstall = $this->l('Are you sure you want to uninstall this module ?');
		if (!Configuration::get('JC-EANUPC-EAN') && !Configuration::get('JC-EANUPC-UPC'))
			$this->warning = $this->l('You have not yet set your EAN / UPC codes parameters');
		$this->version = '1.6.6';
		$this->author = 'Boutiquet';
		$this->error = false;
		$this->valid = false;
	}
	public function install()
	{
		if (parent::install() == false || $this->registerHook('addproduct') == false
		|| $this->registerHook('updateproduct') == false || !Configuration::updateValue('JC-EANUPC-EAN', 1)
		|| !Configuration::updateValue('JC-EANUPC-UPC', 1) || !Configuration::updateValue('JC-EANUPC-gs1', 200)
		|| !Configuration::updateValue('JC-EANUPC-font_size', 8) || !Configuration::updateValue('JC-EANUPC-margin_left', 2)
		|| !Configuration::updateValue('JC-EANUPC-margin_top', 2) || !Configuration::updateValue('JC-EANUPC-margin_right', 2)
		|| !Configuration::updateValue('JC-EANUPC-barheight', 15) || !Configuration::updateValue('JC-EANUPC-blocheight', 35)
		|| !Configuration::updateValue('JC-EANUPC-barwidth', 25) || !Configuration::updateValue('JC-EANUPC-blocwidth', 30)
		|| !Configuration::updateValue('JC-EANUPC-blockborder', 0) || !Configuration::updateValue('JC-EANUPC-color', '000000')
		|| !Configuration::updateValue('JC-EANUPC-stretch', 0) || !Configuration::updateValue('JC-EANUPC-unitmeasure', 'mm')
		|| !Configuration::updateValue('JC-EANUPC-setborder', 1) || !Configuration::updateValue('JC-EANUPC-productname', 0)
		|| !Configuration::updateValue('JC-EANUPC-productprice', 0) || !Configuration::updateValue('JC-EANUPC-productref', 0))
			return false;
		return true;
	}
	public function uninstall()
	{
		if (!Configuration::deleteByName('JC-EANUPC-EAN') || !Configuration::deleteByName('JC-EANUPC-UPC')
		|| !Configuration::deleteByName('JC-EANUPC-gs1') || !Configuration::deleteByName('JC-EANUPC-font_size')
		|| !Configuration::deleteByName('JC-EANUPC-margin_left') || !Configuration::deleteByName('JC-EANUPC-margin_top')
		|| !Configuration::deleteByName('JC-EANUPC-margin_right') || !Configuration::deleteByName('JC-EANUPC-barheight')
		|| !Configuration::deleteByName('JC-EANUPC-blocheight') || !Configuration::deleteByName('JC-EANUPC-barwidth')
		|| !Configuration::deleteByName('JC-EANUPC-blocwidth') || !Configuration::deleteByName('JC-EANUPC-blockborder')
		|| !Configuration::deleteByName('JC-EANUPC-color') || !Configuration::deleteByName('JC-EANUPC-stretch')
		|| !Configuration::deleteByName('JC-EANUPC-unitmeasure') || !Configuration::deleteByName('JC-EANUPC-setborder')
		|| !Configuration::deleteByName('JC-EANUPC-productname') || !Configuration::deleteByName('JC-EANUPC-productprice')
		|| !Configuration::deleteByName('JC-EANUPC-productref') || !parent::uninstall())
			return false;
		return true;
	}
	private function refreshproperties()
	{
		$this->bean = (int)Configuration::get('JC-EANUPC-EAN');
		$this->bupc = (int)Configuration::get('JC-EANUPC-UPC');
		$this->gs1 = (int)Configuration::get('JC-EANUPC-gs1');
	}
	public function getContent()
	{
		$this->html = '<h2>'.$this->displayName.'</h2>';
		$this->postprocess();
		$this->displayform();
		$this->html .= $this->support();
		return $this->html;
	}
	private function displayform()
	{	
		$id_lang = $this->context->language->id;
		$pdf_dir_is_writable = is_writable(_PS_ROOT_DIR_.'/modules/'.$this->name.'/pdf');
		$categories = Category::getSimpleCategories((int)$this->context->language->id);
		$manufacturers = Manufacturer::getManufacturers();
		$cat = '';
		foreach ($categories as $value)
			$cat .= '<option value="'.$value['id_category'].'">'.$value['name'].'</option>';
		$man = '<option value="0">'.$this->l('All manufacturers').'</option>';
		foreach ($manufacturers as $value)
			$man .= '<option value="'.$value['id_manufacturer'].'">'.$value['name'].'</option>';
		// $tab = Tools::getValue('tab');
		// $token = Tools::getValue('token');
		$css = $this->_path.'css';
		$js = $this->_path.'js/';
		if (!$pdf_dir_is_writable)
		{
		$this->html .= '<div class=" alert-warning warning warn clear">';
		$this->html .= $this->l('To save bardcode in PDF, please assign CHMOD 777 to the directory:')._PS_ROOT_DIR_.'/modules/'.$this->name.'/pdf'.'</div>';
		}
		$this->html .= '
		<script type="text/javascript">
		var ajax_uri = "'._PS_BASE_URL_.__PS_BASE_URI__.'modules/'.$this->name.'/ajaxsearch.php'.'";
		</script>
		<script type="text/javascript" src="'.$js.'bqbarcodegeneration.js"></script>';
		$this->html .= '<script type="text/javascript" src="'.$js.'jscolor.js"></script>';
		$this->html .= '<link type="text/css" rel="stylesheet" href="'.$css.'/css.css" />';
		$upcpdflink = $enapdflink = '';
		if (file_exists('../modules/'.$this->name.'/pdf/ean13.pdf'))
			$enapdflink = '<a href="../modules/'.$this->name.'/pdf/ean13.pdf" class="button">'.$this->l('Download EAN13 PDF').'</a>';
		if (file_exists('../modules/'.$this->name.'/pdf/upc.pdf'))
			$upcpdflink = '<a href="../modules/'.$this->name.'/pdf/upc.pdf" class="button">'.$this->l('Download UPC PDF').'</a>';
		$tb = Tools::getValue('tb');
		if (!$tb) $tb = 1;
		$this->html .= " <script>
		   $(document).ready(function() {
			
		   //hiding tab content except first one
			  $('.tabContent').not('#tab".$tb."').hide();
			  // adding Active class to first selected tab and show 
			  $('ul.tabs li.tab".$tb."').addClass('active').show();
		   
			  // Click event on tab
			  $('ul.tabs li').click(function () {
				  // Removing class of Active tab
				  $('ul.tabs li.active').removeClass('active');
				  // Adding Active class to Clicked tab
				  $(this).addClass('active');
				  // hiding all the tab contents
				  $('.tabContent').hide();
				  // showing the clicked tabs content using fading effect
				  $($('a', this).attr('href')).fadeIn('slow')
				  return false;
			  });
		   });
		   
		</script>";
		$this->html .= '
			<ul class="tabs">
				<li class="tab1"><a href="#tab1"><img src="'.$this->_path.'img/prefs.gif" />&nbsp;'.$this->l('Parameters').'</a></li>
				<li class="tab2"><a href="#tab2"><img src="'.$this->_path.'logo.gif" />&nbsp;'.$this->l('Generate missing codes').'</a></li>
				<li class="tab3"><a href="#tab3"><img src="'.$this->_path.'img/printer.gif" />&nbsp;'.$this->l('Print Barcode').'</a></li>
				<li class="tab4"><a href="#tab4"><img src="'.$this->_path.'img/pdf.gif" />'.$this->l('Download PDF').'</a></li>
			</ul>
			<div class="tabContainer">
				<div id="tab1" class="tabContent">
						<form action="'.htmlentities($_SERVER['REQUEST_URI']).'&tb=1" method="post">
						<label style="width:40%">'.$this->l('gs1 Prefixe :').' </label>
						<div class="margin-form">
						<input type="text" maxlength="3" size="3" value="'.(int)Configuration::get('JC-EANUPC-gs1').'" id="gs1" name="gs1">
						&nbsp;<label for="gs1" class="t">'.$this->l('Enter gs1 prefix.').'</label>
						</div>
						<label style="width:40%">'.$this->l('EAN code :').' </label>
						<div class="margin-form">
						<input type="checkbox" value="1" id="bean" name="bean" '.((int)Configuration::get('JC-EANUPC-EAN') == 1 ? 'checked' : '').'>
						&nbsp;<label for="bean" class="t">'.$this->l('Generate an EAN code after each product creation.').'</label>
						</div>
						<div style="clear:both;">&nbsp;</div>
						<label style="width:40%">'.$this->l('UPC code :').' </label>
						<div class="margin-form">
						<input type="checkbox" value="1" id="bupc" name="bupc" '.((int)Configuration::get('JC-EANUPC-UPC') == 1 ? 'checked' : '').'>
						&nbsp;<label for="bupc" class="t">'.$this->l('Generate an UPC code after each product creation.').'</label>
						</div>
						<div style="clear:both;">&nbsp;</div>
						<label style="width:40%">'.$this->l('Set measure unit:').' </label>
						<div class="margin-form">
						<select name="unitmeasure">
						<option value="mm" '.(Configuration::get('JC-EANUPC-unitmeasure') == 'mm' ? 'selected' : '').'>'.$this->l(' mm: millimeter ').'</option>
						<option value="in" '.(Configuration::get('JC-EANUPC-unitmeasure') == 'in' ? 'selected' : '').'>'.$this->l('in: inch').'</option>
						<option value="pt" '.(Configuration::get('JC-EANUPC-unitmeasure') == 'pt' ? 'selected' : '').'>'.$this->l('pt:Point').'</option>
						</select>
						</div>
						<label style="width:40%">'.$this->l('font Size :').' </label>
						<div class="margin-form">
						<input type="text" size="3" value="'.(int)Configuration::get('JC-EANUPC-font_size').'" id="font_size" name="font_size">
						&nbsp;<label for="font_size" class="t">'.$this->l('Enter font size value.').'</label>
						</div>
						<label style="width:40%">'.$this->l('Margin Top:').' </label>
						<div class="margin-form">
						<input type="text" size="3" value="'.(int)Configuration::get('JC-EANUPC-margin_top').'" id="margin" name="margin_top">
						&nbsp;<label for="margin" class="t">'.$this->l('Enter numeric value of top margin.').'</label>
						</div>
						<label style="width:40%">'.$this->l('Margin bottom:').' </label>
						<div class="margin-form">
						<input type="text" size="3" value="'.(int)Configuration::get('JC-EANUPC-margin_bottom').'" id="margin" name="margin_bottom">
						&nbsp;<label for="margin" class="t">'.$this->l('Enter numeric value of bottom margin.').'</label>
						</div>
						<label style="width:40%">'.$this->l('Margin Left:').' </label>
						<div class="margin-form">
						<input type="text" size="3" value="'.(int)Configuration::get('JC-EANUPC-margin_left').'" id="margin" name="margin_left">
						&nbsp;<label for="margin" class="t">'.$this->l('Enter numeric value of left margin.').'</label>
						</div>
						<label style="width:40%">'.$this->l('Margin Right:').' </label>
						<div class="margin-form">
						<input type="text" size="3" value="'.(int)Configuration::get('JC-EANUPC-margin_right').'" id="margin" name="margin_right">
						&nbsp;<label for="margin" class="t">'.$this->l('Enter numeric value of right margin.').'</label>
						</div>
						<label style="width:40%">'.$this->l('Barcode Height :').' </label>
						<div class="margin-form">
						<input type="text"size="3" value="'.(float)Configuration::get('JC-EANUPC-barheight').'" id="barheight" name="barheight">
						&nbsp;<label for="barheight" class="t">'.$this->l('Enter Barcode height.').'</label>
						</div>
						<label style="width:40%">'.$this->l('Block Height :').' </label>
						<div class="margin-form">
						<input type="text"size="3" value="'.(float)Configuration::get('JC-EANUPC-blocheight').'" id="blocheight" name="blocheight">
						&nbsp;<label for="blocheight" class="t">'.$this->l('Enter barcode container height.').'</label>
						</div>
						<label style="width:40%">'.$this->l('Barcode Width :').' </label>
						<div class="margin-form">
						<input type="text"size="3" value="'.(float)Configuration::get('JC-EANUPC-barwidth').'" id="barwidth" name="barwidth">
						&nbsp;<label for="barwidth" class="t">'.$this->l('Enter Barcode width.').'</label>
						</div>
						<label style="width:40%">'.$this->l('Bloc Width :').' </label>
						<div class="margin-form">
						<input type="text"size="3" value="'.(float)Configuration::get('JC-EANUPC-blocwidth').'" id="blocwidth" name="blocwidth">
						&nbsp;<label for="blocwidth" class="t">'.$this->l('Enter barcode container width.').'</label>
						</div>
						<label style="width:40%">'.$this->l('Barcode container width border :').' </label>
						<div class="margin-form">
						<select name="blockborder">
						<option value="0" '.(Configuration::get('JC-EANUPC-blockborder') == 0 ? 'selected' : '').'>'.$this->l('no ').'</option>
						<option value="1" '.(Configuration::get('JC-EANUPC-blockborder') == 1 ? 'selected' : '').'>'.$this->l('yes').'</option>
						</select>
						</div>
						<input type="hidden" name="stretch" value="0">
						<label style="width:40%">'.$this->l('Color :').' </label>
						<div class="margin-form">
						<input type="text"maxlength="6" value="'.((Configuration::get('JC-EANUPC-color')) ? Configuration::get('JC-EANUPC-color') : '000000').'"
						id="picker" class="color" name="color">
						&nbsp;<label for="color" class="t">'.$this->l('Barcode color in hexa.').'</label>
						</div>
						<label style="width:40%">'.$this->l('Barcode with border :').' </label>
						<div class="margin-form">
						<select name="setborder">
						<option value="1" '.((int)Configuration::get('JC-EANUPC-setborder') == 1 ? 'selected' : '').'>'.$this->l('no ').'</option>
						<option value="2" '.((int)Configuration::get('JC-EANUPC-setborder') == 2 ? 'selected' : '').'>'.$this->l('yes').'</option>
						</select>
						</div>
						<label style="width:40%">'.$this->l('Show product name :').' </label><div class="margin-form">
						<select name="productname">
						<option value="0" '.((int)Configuration::get('JC-EANUPC-productname') == 0 ? 'selected' : '').'>'.$this->l('no ').'</option>
						<option value="1" '.((int)Configuration::get('JC-EANUPC-productname') == 1 ? 'selected' : '').'>'.$this->l('yes').'</option>
						</select>
						</div>
						<label style="width:40%">'.$this->l('Show product price :').' </label>
						<div class="margin-form">
						<select name="productprice">
						<option value="0" '.((int)Configuration::get('JC-EANUPC-productprice') == 0 ? 'selected' : '').'>'.$this->l('no ').'</option>
						<option value="1" '.((int)Configuration::get('JC-EANUPC-productprice') == 1 ? 'selected' : '').'>'.$this->l('yes').'</option>
						</select>
						</div>
						
						<label style="width:40%">'.$this->l('Use reduced price :').' </label>
						<div class="margin-form">
						<select name="usereduction">
						<option value="0" '.((int)Configuration::get('JC-EANUPC-usereduction') == 0 ? 'selected' : '').'>'.$this->l('no ').'</option>
						<option value="1" '.((int)Configuration::get('JC-EANUPC-usereduction') == 1 ? 'selected' : '').'>'.$this->l('yes').'</option>
						</select>
						</div>
						
						<label style="width:40%">'.$this->l('Show product reference :').' </label>
						<div class="margin-form">
						<select name="productref">
						<option value="0" '.((int)Configuration::get('JC-EANUPC-productref') == 0 ? 'selected' : '').'>'.$this->l('no ').'</option>
						<option value="1" '.((int)Configuration::get('JC-EANUPC-productref') == 1 ? 'selected' : '').'>'.$this->l('yes').'</option>
						</select>
						</div> 
						<label style="width:40%">'.$this->l('maximum number of character for the product name:').' </label>
						<div class="margin-form">
						<input type="text"size="3" value="'.(int)Configuration::get('JC-EANUPC-truncateproductname').'" id="blocwidth" name="truncateproductname">
						</div>
						<div class="margin-form">
						<br />
						<input type="submit" value="'.$this->l(' Save ').'" name="submitParam" class="button" />
						</div>
						</form>
						<div>
						<label for="blocwidth" class="t">'.$this->l('Use a period instead of a comma, it\'s about the fields with height and width').'</label>
						</div>
				</div>
				<div id="tab2" class="tabContent">
						<form action="'.htmlentities($_SERVER['REQUEST_URI']).'&tb=2" method="post">
						<div style="clear:both;">&nbsp;</div>
						<label style="width:40%">'.$this->l('Select categorie:').' </label>
						<div class="margin-form">
						<select name="categorie_generate_id" id="categorie_generate_id">
						<option value="0">'.$this->l('All categories').'</option>
						'.$cat.'
						</select>
						</div>
						<label style="width:40%">'.$this->l('Type :').' </label>
						<div class="margin-form">
						<select id="type" name="typeTogenerate">
						<option value="0">'.$this->l('EAN13 + UPC').'</option>
						<option value="ean13">'.$this->l('EAN13').'</option>
						<option value="upc">'.$this->l('UPC').'</option>
						</select>
						&nbsp;<label for="gs1" class="t">'.$this->l('Type').'</label>
						</div>
						<br><br>
						<div class="margin-form">
						<input type="submit" value="'.$this->l(' Generate missing codes ').'" name="submitUpdate" class="button" />
						</div>
						</form>
						
				</div>
				<div id="tab3" class="tabContent">
						<form action="'.htmlentities($_SERVER['REQUEST_URI']).'&tb=3" method="post" id="ajaxform">
						<div style="clear:both;">&nbsp;</div>
						<label style="width:40%">'.$this->l('Select categorie:').' </label>
						<div class="margin-form">
							<select name="categorie_id" id="categorie_id">
								'.$cat.'
							</select>
						</div>
						<div style="clear:both;">&nbsp;</div>
						<label style="width:40%">'.$this->l('Choose one or more products').' </label>
						<div class="margin-form">
							<select name="product_id[]" id="product_id" multiple size=8>
								<option value="0">'.$this->l('Choose').'</option>
							</select>
							'.$this->l('Hit Ctrl button to select multiple products').'
						</div>
						<div style="clear:both;">&nbsp;</div>
						
						<label style="width:40%" class="decl">'.$this->l('Choose one or more declinaison').' </label>
						<div class="margin-form decl">
							<select name="declinaisons_id[]" id="declinaisons_id" multiple size=8>
								<option value="0">'.$this->l('Choose').'</option>
							</select>
							'.$this->l('Hit Ctrl button to select multiple declinaisons').'
						</div>
						<div style="clear:both;">&nbsp;</div>
						
						<label style="width:40%">'.$this->l('Filter by manufacturer:').' </label>
						<div class="margin-form">
							<select name="manufacturer_id" id="manufacturer_id">
								'.$man.'
							</select>
						</div>
						<div style="clear:both;">&nbsp;</div>
						<label style="width:40%">'.$this->l('Select the size of print paper').' </label>
						<div class="margin-form">
							<select name="printformat">
								<option value="C76">'.$this->l('Tiquet 80mm (C7) ').'</option>
								<option value="A2">'.$this->l('A2').'</option>
								<option value="A3">'.$this->l('A3').'</option>
								<option value="A4">'.$this->l('A4').'</option>
								<option value="A5">'.$this->l('A5').'</option>
								<option value="A6">'.$this->l('A6').'</option>
								<option value="A7">'.$this->l('A7').'</option>
							</select>
							
						</div>
						/
						<label style="width:40%">'.$this->l('Or enter custom format').' </label>
						<div class="margin-form">
						'.$this->l('width').' <input type="text" maxlength="6" name="customprintformatwidth"/> '.$this->l('height').'  : 
						<input type="text" maxlength="6" name="customprintformatheight"/><br> '.$this->l('Keep empty if you want to use format in listbox').'
						</div>
						<label style="width:40%">'.$this->l('Copy number by code').' </label>
						<div class="margin-form">
						<input type="text" maxlength="6" name="nbproduct" value="1"/>'.$this->l('If 0, the value of quantity available will be taken.').' 
						</div>
						<label style="width:40%">'.$this->l('Orientation:').' </label>
						<div class="margin-form">
						<select name="orientation">
						<option value="P" selected>'.$this->l(' P : Portrait ').'</option>
						<option value="L">'.$this->l('L : Landscape').'</option>
						</select>
						</div>
						<label style="width:40%">'.$this->l('Type :').' </label>
						<div class="margin-form">
						<select id="type" name="type">
						<option value="ean13">'.$this->l('EAN13').'</option>
						<option value="upc">'.$this->l('UPC').'</option>
						</select>
						&nbsp;<label for="gs1" class="t">'.$this->l('Type').'</label>
						</div>
						<div class="margin-form">
						<br>
						<input type="hidden" name="id_lang" id="id_lang" value="'.$id_lang.'">
						<input type="submit" value="'.$this->l(' Print Barecodes ').'" name="submitPrint" class="button" />
						</div>
						</form>
				</div>
				<div id="tab4" class="tabContent">
						'.$upcpdflink.' || '.$enapdflink.'
				</div> 
						 
			</div>';
	}
	private function postprocess()
	{
		$sufixe = ((int)Configuration::get('JC-EANUPC-gs1')) ? (int)Configuration::get('JC-EANUPC-gs1') : '200';
		if (Tools::isSubmit('submitParam'))
		{
			if (!Configuration::updateValue('JC-EANUPC-EAN', (int)Tools::getValue('bean'))
			|| !Configuration::updateValue('JC-EANUPC-UPC', (int)Tools::getValue('bupc'))
			|| !Configuration::updateValue('JC-EANUPC-gs1', (int)Tools::getValue('gs1')))
				$this->posterrors[] = $this->l('Cannot update settings');
			// validate fields
			if (!Validate::isInt(Tools::getValue('font_size')))
				$this->posterrors[] = $this->l('font_size required and shoud be numeric.');
			if (!Validate::isInt(Tools::getValue('margin_top')))
				$this->posterrors[] = $this->l('Margin top required and shoud be numeric.');
			if (!Validate::isInt(Tools::getValue('margin_bottom')))
				$this->posterrors[] = $this->l('Margin bottom required and shoud be numeric.');
			if (!Validate::isInt(Tools::getValue('margin_left')))
				$this->posterrors[] = $this->l('Margin left required and shoud be numeric.');
			if (!Validate::isInt(Tools::getValue('margin_right')))
				$this->posterrors[] = $this->l('Margin right required and shoud be numeric.');
			if (!Validate::isFloat(Tools::getValue('barheight')))
				$this->posterrors[] = $this->l('Barcode Height required and shoud be numeric.');
			if (!Validate::isFloat(Tools::getValue('blocheight')))
				$this->posterrors[] = $this->l('block Height required and shoud be numeric.');
			if (!Validate::isInt(Tools::getValue('blockborder')))
				$this->posterrors[] = $this->l('block blockborder required and shoud be numeric.');
			if (!Validate::isFloat(Tools::getValue('barwidth')))
				$this->posterrors[] = $this->l('Width required and shoud be numeric.');
			if (!Validate::isFloat(Tools::getValue('blocwidth')))
				$this->posterrors[] = $this->l('block Width required and shoud be numeric.');
			if (!Validate::isColor(Tools::getValue('color')))
				$this->posterrors[] = $this->l('Color required and shoud be numeric.');
			// if (!Validate::isInt('stretch'))
			// $this->posterrors[] = $this->l('Stretch no valid.');
			// if (!Tools::getValue('unitmeasure'))
			// $this->posterrors[] = $this->l('Unit of measure.');
			// if (!Validate::isInt('setborder'))
			// $this->posterrors[] = $this->l('Set border no valid.');
			if (!count($this->posterrors))
			{
				Configuration::updateValue('JC-EANUPC-font_size', (int)Tools::getValue('font_size'));
				Configuration::updateValue('JC-EANUPC-margin_top', (int)Tools::getValue('margin_top'));
				Configuration::updateValue('JC-EANUPC-margin_bottom', (int)Tools::getValue('margin_bottom'));
				Configuration::updateValue('JC-EANUPC-margin_left', (int)Tools::getValue('margin_left'));
				Configuration::updateValue('JC-EANUPC-margin_right', (int)Tools::getValue('margin_right'));
				Configuration::updateValue('JC-EANUPC-barheight', (float)Tools::getValue('barheight'));
				Configuration::updateValue('JC-EANUPC-blocheight', (float)Tools::getValue('blocheight'));
				Configuration::updateValue('JC-EANUPC-blockborder', (int)Tools::getValue('blockborder'));
				Configuration::updateValue('JC-EANUPC-barwidth', (float)Tools::getValue('barwidth'));
				Configuration::updateValue('JC-EANUPC-blocwidth', (float)Tools::getValue('blocwidth'));
				Configuration::updateValue('JC-EANUPC-color', Tools::getValue('color'));
				Configuration::updateValue('JC-EANUPC-stretch', (int)Tools::getValue('stretch'));
				Configuration::updateValue('JC-EANUPC-unitmeasure', Tools::getValue('unitmeasure'));
				Configuration::updateValue('JC-EANUPC-setborder', Tools::getValue('setborder'));
				Configuration::updateValue('JC-EANUPC-productname', (int)Tools::getValue('productname'));
				Configuration::updateValue('JC-EANUPC-productref', (int)Tools::getValue('productref'));
				Configuration::updateValue('JC-EANUPC-truncateproductname', (int)Tools::getValue('truncateproductname'));
				Configuration::updateValue('JC-EANUPC-productprice', (int)Tools::getValue('productprice'));
				Configuration::updateValue('JC-EANUPC-usereduction', (int)Tools::getValue('usereduction'));
				$this->html .= '<div class="bootstrapalert-success conf confirm">'.$this->l('Settings updated').'</div>';
			}
			else
			foreach ($this->posterrors as $err)
				$this->html .= '<div class="alert-error error">'.$err.'</div>';
		}
		elseif (Tools::isSubmit('submitUpdate'))
		{	
			$sqlWhere = '';
			$categorie_generate_id = Tools::getValue('categorie_generate_id');
			if ($categorie_generate_id != 0) $sqlWhere = "AND id_category_default = '".$categorie_generate_id."'";
			$this->refreshproperties();
			$this->html .= '<div class="bootstrapalert-success conf confirm">'.$this->l('Missing codes generated').'</div>';
			if (Tools::getValue('typeTogenerate') == 'EAN13' || (Tools::getValue('typeTogenerate') == 0))
			{
				$products_to_update_code = Db::getInstance()->ExecuteS('SELECT `id_product` FROM '._DB_PREFIX_.'product
				WHERE (ean13 IS NULL OR ean13 = "" OR ean13 = "0")'.$sqlWhere);
				foreach ($products_to_update_code as $product)
				{
					$ean13 = '';
					$ean13 = $this->generateEAN($product['id_product'], $sufixe);
					Db::getInstance()->Execute('UPDATE '._DB_PREFIX_.'product SET ean13 = "'.$ean13.'" WHERE id_product = '.(int)$product['id_product']);
				}
				$products_attr_to_update_code = Db::getInstance()->ExecuteS('SELECT `id_product`,`id_product_attribute`
				FROM '._DB_PREFIX_.'product_attribute WHERE ean13 IS NULL OR ean13 = "" OR ean13 = "0"');
				foreach ($products_attr_to_update_code as $product_attr)
				{
					$ean13 = '';
					$ean13 = $this->generateEAN($product_attr['id_product_attribute'], $sufixe, 'ean13', true);
					Db::getInstance()->Execute('UPDATE '._DB_PREFIX_.'product_attribute SET ean13 = "'.$ean13.'" WHERE
					id_product_attribute ='.$product_attr['id_product_attribute']);
				}
			}
			if (Tools::getValue('typeTogenerate') == 'UPC' || (Tools::getValue('typeTogenerate') == 0))
			{
				$products_to_update_code = Db::getInstance()->ExecuteS('SELECT `id_product` FROM '._DB_PREFIX_.'product WHERE
				(upc IS NULL OR upc = "" OR upc = "0") '.$sqlWhere);
				foreach ($products_to_update_code as $product)
				{
					$upc = '';
					$upc = $this->generateEAN($product['id_product'], $sufixe, 'upc');
					Db::getInstance()->Execute('UPDATE '._DB_PREFIX_.'product SET upc = "'.$upc.'" WHERE id_product ='.(int)$product['id_product']);
				}
				$products_attr_to_update_code = Db::getInstance()->ExecuteS('SELECT `id_product`,`id_product_attribute` FROM '._DB_PREFIX_.'product_attribute
				WHERE upc IS NULL OR upc = "" OR upc = "0"');
				foreach ($products_attr_to_update_code as $product_attr)
				{
					$upc = '';
					$upc = $this->generateEAN($product_attr['id_product_attribute'], $sufixe, 'upc', true);
					Db::getInstance()->Execute('UPDATE '._DB_PREFIX_.'product_attribute SET upc = "'.$upc.'" WHERE
					id_product_attribute ='.$product_attr['id_product_attribute']);
				}
			}
		}
		elseif (Tools::isSubmit('submitPrint'))
		{
			$margin = array();
			$printformat = Tools::getValue('printformat');
			$nbproduct = (int)Tools::getValue('nbproduct');
			$printformatw = (int)Tools::getValue('customprintformatwidth');
			$printformath = (int)Tools::getValue('customprintformatheight');
			
			if ($printformatw && $printformath) $printformat = array($printformath,$printformatw);
			$type = Tools::getValue('type');
			$categorie_id = Tools::getValue('categorie_id');
			$manufacturer_id = Tools::getValue('manufacturer_id');
			$products = array();
			$products = Tools::getValue('product_id');
			$declinaisons = Tools::getValue('declinaisons_id');
			$orientation = Tools::getValue('orientation');
			$unitmeasure = Configuration::get('JC-EANUPC-unitmeasure');
			$font_size = Configuration::get('JC-EANUPC-font_size');
			$color = Configuration::get('JC-EANUPC-color');
			$margin['top'] = Configuration::get('JC-EANUPC-margin_top');
			$margin['left'] = Configuration::get('JC-EANUPC-margin_left');
			$margin['right'] = Configuration::get('JC-EANUPC-margin_right');
			$margin['bottom'] = Configuration::get('JC-EANUPC-margin_bottom');
			$barheight = Configuration::get('JC-EANUPC-barheight');
			$barwidth = Configuration::get('JC-EANUPC-barwidth');
			$blocheight = Configuration::get('JC-EANUPC-blocheight');
			$blocwidth = Configuration::get('JC-EANUPC-blocwidth');
			$blockborder = Configuration::get('JC-EANUPC-blockborder');
			$stretch = Configuration::get('JC-EANUPC-stretch');
			$setborder = Configuration::get('JC-EANUPC-setborder');
			$showname = Configuration::get('JC-EANUPC-productname');
			$showprice = Configuration::get('JC-EANUPC-productprice');
			$showref = Configuration::get('JC-EANUPC-productref');
			// render pdf
			if (!count($this->posterrors))
				$this->printPDF($type, $categorie_id, $products, $declinaisons, $unitmeasure, $orientation, $font_size, $color, $margin, $barheight, $barwidth,
				$blocheight, $blocwidth, $blockborder, $printformat, $stretch, $setborder, $showname, $showref, $showprice, $nbproduct, $manufacturer_id);
			else
				foreach ($this->posterrors as $err) $this->html .= '<div class="bootstrapalert-error">'.$err.'</div>';
		}
		$this->refreshproperties();
	}
	public function hookAddProduct($params)
	{
		$this->refreshproperties();
		$sufixe = ((int)Configuration::get('JC-EANUPC-gs1')) ? (int)Configuration::get('JC-EANUPC-gs1') : '200';
		if (_PS_VERSION_ < 1.6) $id = $params['product']->id;
		else  $id = $params['id_product'];
		if (Tools::getValue('bean', $this->bean) == 1)
		{
			$ean13 = $this->generateEAN($id, $sufixe);
			Db::getInstance()->Execute('UPDATE '._DB_PREFIX_.'product SET ean13 = "'.(int)$ean13.'" 
			WHERE (ean13 IS NULL OR ean13 = "" OR ean13 = 0) AND id_product = "'.(int)$id.'"');
		}
		$products_attr_to_update_code = Db::getInstance()->ExecuteS('SELECT `id_product`,`id_product_attribute`
		FROM '._DB_PREFIX_.'product_attribute WHERE (ean13 IS NULL OR ean13 = "" OR ean13 = "0") AND id_product = "'.(int)$id.'"');
		if ($products_attr_to_update_code)
			foreach ($products_attr_to_update_code as $product_attr)
			{
				$ean13 = '';
				$ean13 = $this->generateEAN($product_attr['id_product_attribute'], $sufixe, 'ean13', true);
				Db::getInstance()->Execute('UPDATE '._DB_PREFIX_.'product_attribute SET ean13 = "'.(int)$ean13.'" WHERE
				id_product_attribute ='.(int)$product_attr['id_product_attribute']);
			}
		if (Tools::getValue('bupc', $this->bupc) == 1)
		{
			$upc = $this->generateEAN($id, $sufixe, 'upc');
			Db::getInstance()->Execute('UPDATE '._DB_PREFIX_.'product SET upc = '.(int)$upc.' WHERE (upc IS NULL OR upc = "" OR upc = 0) AND id_product = "'.(int)$id.'"');
		}
		$products_attr_to_update_code = Db::getInstance()->ExecuteS('SELECT `id_product`,`id_product_attribute`
		FROM '._DB_PREFIX_.'product_attribute WHERE (upc IS NULL OR upc = "" OR ean13 = "0") AND id_product = "'.(int)$id.'"');
		if ($products_attr_to_update_code)
			foreach ($products_attr_to_update_code as $product_attr)
			{
				$upc = '';
				$upc = $this->generateEAN($product_attr['id_product_attribute'], $sufixe, 'upc', true);
				Db::getInstance()->Execute('UPDATE '._DB_PREFIX_.'product_attribute SET upc = "'.(int)$upc.'" WHERE
				id_product_attribute ='.(int)$product_attr['id_product_attribute']);
			}
	}
	public function hookUpdateProduct($params)
	{
		return $this->hookAddProduct($params);
	}
	public function printPDF($type, $categorie_id = 1, $products = array(), $declinaisons = array(), $unitmeasure = 'mm', $orientation = 'P', $font_size = 10,
	$color = '000000', $margins = array('top' => 0, 'left' => 0, 'right' => 0,'bottom'=>0), $barheight = 80, $barwidth = 50,
	$blocheight = 150, $blocwidth = 100, $blockborder = 0, $dimension = 'A4', $stretch = 0, $setborder = 1,
	$showname = 1, $showref = 1, $showprice = 1, $nbproduct = 1000, $manufacturer_id = 0)
	{
		$usereduction = Configuration::get('JC-EANUPC-usereduction');
		$font_size_barcode = $font_size;
		($unitmeasure == 'mm') ? $font_size *= 2.84 : $font_size *= 1;
		$color = $this->hex2RGB(trim($color));
		$max_length = 1000;
		if (Configuration::get('JC-EANUPC-truncateproductname')) $max_length = Configuration::get('JC-EANUPC-truncateproductname');
		$allproduct = array();
		if (!isset($products) || empty($products) || !$products || $products['0'] == '0')
		$allproduct = Product::getProducts((int)$this->context->language->id, 0, '3000', 'id_product', 'ASC', $categorie_id /*id categorie*/ );
		else
		{	if (empty($declinaisons))
			{
				$i = 0;
				foreach ($products as $product)
				{
					$p = new Product($product, false, $this->context->language->id);
					$allproduct[$i]['id_product'] = $p->id;
					$allproduct[$i]['name'] = $p->name;
					$allproduct[$i]['price'] = Product::getPriceStatic($p->id, true, null, 6, null, false, $usereduction);
					$allproduct[$i]['reference'] = $p->reference;
					$allproduct[$i]['ean13'] = $p->ean13;
					$allproduct[$i]['upc'] = $p->upc;
					$allproduct[$i]['id_manufacturer'] = $p->id_manufacturer;
					$allproduct[$i]['quantity'] = Product::getQuantity($p->id);
					$i++;
				}
			}
			else 
			{	$i = 0;
				$comb_array = array();
				foreach ($products as $product)
				{
					$p = new Product($product, $this->context->language->id);
					if ($p->id)
					{
						/* Build attributes combinations */
						$combinations = $p->getAttributeCombinations($this->context->language->id);
						$groups = array();
							
						if (is_array($combinations))
						{
							foreach ($combinations as $k => $combination)
							{
								if (in_array($combination['id_product_attribute'], $declinaisons))
								{
								// $price_to_convert = Tools::convertPrice($p->getPrice(true), $this->context->currency);
								// $price = Tools::displayPrice($price_to_convert, $this->context->currency);
									$comb_array[$combination['id_product_attribute']]['id_product_attribute'] = $combination['id_product_attribute'];
									$comb_array[$combination['id_product_attribute']]['attributes'][] = $combination['group_name'].'-'.$combination['attribute_name'];
									$comb_array[$combination['id_product_attribute']]['wholesale_price'] = $combination['wholesale_price'];
									$comb_array[$combination['id_product_attribute']]['price'] = Product::getPriceStatic($p->id, true, $combination['id_product_attribute'], 6, null, false, $usereduction);
									$comb_array[$combination['id_product_attribute']]['weight'] = $combination['weight'].Configuration::get('PS_WEIGHT_UNIT');
									$comb_array[$combination['id_product_attribute']]['unit_impact'] = $combination['unit_price_impact'];
									$comb_array[$combination['id_product_attribute']]['reference'] = $combination['reference'];
									$comb_array[$combination['id_product_attribute']]['ean13'] = $combination['ean13'];
									$comb_array[$combination['id_product_attribute']]['quantity'] = $combination['quantity'];
									$comb_array[$combination['id_product_attribute']]['upc'] = $combination['upc'];
									$comb_array[$combination['id_product_attribute']]['product_name'] = $p->name[$this->context->language->id];
								}	
							}
						}

					}
				}
				$i = 0;
				foreach ($comb_array as $product)
				{
					// $p = new Product($product, false, $this->context->language->id);
					// $allproduct[$i]['id_product'] = $product['id_product'];
					$allproduct[$i]['name'] = $product['product_name'].' | '.implode(',', $product['attributes']);
					$allproduct[$i]['price'] = $product['price'];
					$allproduct[$i]['reference'] = $product['reference'];
					$allproduct[$i]['ean13'] = $product['ean13'];
					$allproduct[$i]['upc'] = $product['upc'];
					$allproduct[$i]['quantity'] = $product['quantity'];
					// $allproduct[$i]['id_manufacturer'] = $p->id_manufacturer;
					$i++;
				}
			}
		}
		$array = array(
			1 => 'one',
			2 => 'two',
			3 => 'three'
		);
		if ($manufacturer_id != 0)
		{
			foreach ($allproduct as $key => $product)
				if ($product['id_manufacturer'] != $manufacturer_id) unset($allproduct[$key]);
			
		}
		if ($dimension == 'C76')
		{
			$custom_layout = array(
				($blocwidth + $margins['left'] + $margins['right']),
				($blocheight + $barheight)
			);
			$pdf = new TCPDF($orientation, $unitmeasure, $custom_layout);
		}
		else
		$pdf = new TCPDF($orientation, $unitmeasure, $dimension);
		$pdf->SetCreator('bqbarcodegeneration');
		$pdf->setPrintHeader(false);
		$pdf->setPrintFooter(false);
		$pdf->SetFont('helvetica', '', $font_size);
		// set default font subsetting mode
		$pdf->setFontSubsetting(true);
		// set font
		$pdf->SetFont('freeserif', '', $font_size);
		$pdf->SetMargins($margins['left'], $margins['top'], $margins['right'], false);
		$pdf->SetHeaderMargin(0);
		$pdf->SetFooterMargin(0);
		// set auto page breaks
		$pdf->SetAutoPageBreak(true, $margins['bottom']);
		$page_width = TcpdfStatic::getPageSizeFromFormat($dimension);
		$nblignes = 1;
		if ($dimension == 'C76')
			$nbcol = 1;
		else
		{
			$nbcol = (int)$page_width[0] / (($blocwidth / 25.4) * 72);
			$nblignes = (int)$page_width[1] / (($blocheight / 25.4) * 72);
		}
		
		if ((int)$nbcol == 0)
			$nbcol = 1; // limit division by zero
		$c = 0;
		if ($type == 'upc')
			$type2 = 'UPCA';
		else
			$type2 = $type;
		$htmloutput = '<table border="0" ><tr>';
		
		
		foreach ($allproduct as $product)
		{
		$quantity = 0;
		if ($nbproduct == 0) $quantity = $product['quantity'];
		else $quantity = $nbproduct;
		
		
		if ($quantity == 0) $quantity++;
		
			
			
			for ($i = 0; $i < $quantity; $i++)
			{
			$barcode = $product[$type];
			$pdf_params = $pdf->serializeTCPDFtagParameters(array(
				Tools::substr($barcode, 0, -1),
				$type2,
				'',
				'',
				$barwidth,
				$barheight,
				0.4,
					array(
					'position' => 'C',
					'border' => ($setborder == 2) ? true : false,
					'padding' => 0,
					'fitwidth' => false,
					'fgcolor' => $color,
					'bgcolor' => array(
						255,
						255,
						255
					),
					'text' => true,
					'font' => 'Helvetica',
					'font_size' => $font_size_barcode,
					'stretchtext' => $stretch,
					'label' => ''
				),
				'N'
			));
			if ($c % $nbcol == 0 && $c > 0 && $dimension != 'C76') $htmloutput .= '</tr><tr>';
			// $currency = Currency::getCurrent();
			// convert to pixel unit 1mm = 2.84
			if (empty($barcode))
			{
				$this->html .= '<div class=" error">'.
				$this->l('There is one or more missed code in selected type, plz regenerate missed code and try again').'</div>';
				return $this->html;
			}
			$autre = '';
			if ($showname)
				$autre .= ' <tr><td><div style="text-align:left;">'.Tools::truncate($product['name'], $max_length).'</div></td></tr>';
			// if ($showprice)
				// $autre .= ' <tr><td><p style="text-align:left">'.$this->l('Price').': '.Tools::displayPrice(Product::getPriceStatic($product['id_product'])).'</p></td></tr>';
			if ($showprice)
				$autre .= ' <tr><td><p style="text-align:left">'.$this->l('Price').': '.Tools::displayPrice($product['price']).'</p></td></tr>';
			if ($showref)
				$autre .= ' <tr><td><p style="text-align:left">'.$this->l('Reference').': '.$product['reference'].'</p></td></tr>';
			
			$htmloutput .= '<td style="'.(($blockborder == 1) ? 'border:1px dashed #555; border-right:0px solid #fff; ' : '').' font-size:'.$font_size.'%;margin:0;padding:0">
			&nbsp;&nbsp;<table>
			'.$autre.'
			</table>';
			$htmloutput .= '<table border="0" ><tr><td colspan=2>
			<tcpdf method="write1DBarcode" params="'.$pdf_params.'"/></td></tr>
			<tr><td style="font-size:150%"><p style="text-align:center">'.Tools::substr($barcode, 0, 1).' '.wordwrap(Tools::substr($barcode, 1, 12), 4, '  ', true).'</p></td></tr></table>';
			$htmloutput .= '</td>';
			$c++;
			
			if ($dimension == 'C76')
			{
				$htmloutput .= '</tr></table>';
				$pdf->AddPage();
				$pdf->writeHTML($htmloutput, true, 0, true, 0);
				$htmloutput = '<table border="0" ><tr>';
			}
			
			}
		}
		$htmloutput .= '</tr></table> ';
		if ($dimension != 'C76')
		{
			
			// $pdf->SetAutoPageBreak(true, 0);
			$pdf->AddPage();
			$pdf->writeHTML($htmloutput, true, 0, true, 0);
			
			
		}
		$filename = dirname(__FILE__).'/pdf/'.$type.'.pdf';
		//Output the document
		ob_clean();
		$pdf->Output($filename, 'F');
		header('Content-type: application/pdf; charset=utf-8');
		header('Content-Disposition: inline; filename="$filename"');
		header('Content-Length: '.filesize($filename));
		readfile($filename);
	}
	public function generateEAN($number, $gs1, $type = 'ean13', $is_attributte = false)
	{
		$pad = 9;
		if ($is_attributte) $gs1 = $gs1.'1';
		if ($is_attributte)	$pad = $pad - 1;
		
		if ($type == 'ean13')
			$code = $gs1.str_pad($number, $pad, '0', STR_PAD_LEFT);
		if ($type == 'upc')
			$code = $gs1.str_pad($number, $pad - 1, '0', STR_PAD_LEFT);
		$weightflag = true;
		$sum = 0;
		// Weight for a digit in the checksum is 3, 1, 3.. starting from the last digit.
		// loop backwards to make the loop length-agnostic. The same basic functionality
		// will work for codes of different lengths.
		for ($i = Tools::strlen($code) - 1; $i >= 0; $i--)
		{
			$sum += (int)$code[$i] * ($weightflag ? 3 : 1);
			$weightflag = !$weightflag;
		}
		$checksum = 10 - ($sum % 10);
		if (Tools::strlen($checksum) > 1)
		{
			$checksum = strrev($checksum);
			$checksum = $checksum[0];
		}
		$code .= $checksum;
		return $code;
	}
	protected static function convertSign($s)
	{
		return str_replace(array(
			'€',
			'£',
			'¥'
		), array(
			chr(128),
			chr(163),
			chr(165)
		), $s);
	}
	/**
	 * Convert a hexa decimal color code to its RGB equivalent
	 *
	 * @param string $hex_str (hexadecimal color value)
	 * @param boolean $return_as_string (if set true, returns the value separated by the separator character. Otherwise returns associative array)
	 * @param string $seperator (to separate RGB values. Applicable only if second parameter is true.)
	 * @return array or string (depending on second parameter. Returns False if invalid hex color value)
	 */
	public function hex2RGB($hex_str, $return_as_string = false, $seperator = ',')
	{
		$hex_str = preg_replace('/[^0-9A-Fa-f]/', '', $hex_str); // Gets a proper hex string
		$rgb_array = array();
		if (Tools::strlen($hex_str) == 6)
		{ //If a proper hex code, convert using bitwise operation. No overhead... faster
			$color_val = hexdec($hex_str);
			$rgb_array['red'] = 0xFF & ($color_val >> 0x10);
			$rgb_array['green'] = 0xFF & ($color_val >> 0x8);
			$rgb_array['blue'] = 0xFF & $color_val;
		}
		elseif (Tools::strlen($hex_str) == 3)
		{ //if shorthand notation, need some string manipulations
			$rgb_array['red'] = hexdec(str_repeat(Tools::substr($hex_str, 0, 1), 2));
			$rgb_array['green'] = hexdec(str_repeat(Tools::substr($hex_str, 1, 1), 2));
			$rgb_array['blue'] = hexdec(str_repeat(Tools::substr($hex_str, 2, 1), 2));
		}
		else
		return false; //Invalid hex color code
		return $return_as_string ? implode($seperator, $rgb_array) : $rgb_array; // returns the rgb string or the associative array
	}
	public function getAttributeCombinations($id_product, $id_product_attributes)
	{
		$product = new Product($id_product, $this->context->language->id);
		if ($product->id)
		{
			/* Build attributes combinations */
			$combinations = $product->getAttributeCombinations($this->context->language->id);
			$groups = array();
			$comb_array = array();
			if (is_array($combinations))
			{
				foreach ($combinations as $k => $combination)
				{
					$price_to_convert = Tools::convertPrice($combination['price'], Context::getContext()->currency->id);
					$price = Tools::displayPrice($price_to_convert, Context::getContext()->currency->id);

					$comb_array[$combination['id_product_attribute']]['id_product_attribute'] = $combination['id_product_attribute'];
					$comb_array[$combination['id_product_attribute']]['attributes'][] = array($combination['group_name'], $combination['attribute_name'], $combination['id_attribute']);
					$comb_array[$combination['id_product_attribute']]['wholesale_price'] = $combination['wholesale_price'];
					$comb_array[$combination['id_product_attribute']]['price'] = $price;
					$comb_array[$combination['id_product_attribute']]['unit_impact'] = $combination['unit_price_impact'];
					$comb_array[$combination['id_product_attribute']]['reference'] = $combination['reference'];
					$comb_array[$combination['id_product_attribute']]['ean13'] = $combination['ean13'];
					$comb_array[$combination['id_product_attribute']]['upc'] = $combination['upc'];
					
				}
			}

		}
	}
	public function support()
	{
		$contact = '';
		$contact .= '
		 <br style="clear:both;"/> <br/>
			<fieldset>
			<legend>Boutiquet</legend>
			<p>
			'.$this->l('This module has been developped by').'<strong> <a href="http://www.Boutiquet"> Boutiquet</a></strong><br />
			'.$this->l('Please report all bugs to').'<strong> <a href="mailto:7ached@gmail.com"> 7ached@gmail.com</a></strong>
			</p>
			</fieldset>';
		return $contact;
	}
}