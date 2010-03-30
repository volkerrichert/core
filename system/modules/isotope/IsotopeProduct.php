<?php if (!defined('TL_ROOT')) die('You can not access this file directly!');

/**
 * TYPOlight webCMS
 * Copyright (C) 2005 Leo Feyer
 *
 * This program is free software: you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation, either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this program. If not, please visit the Free
 * Software Foundation website at http://www.gnu.org/licenses/.
 *
 * PHP version 5
 * @copyright  Winans Creative 2009
 * @author     Andreas Schempp <andreas@schempp.ch>
 * @license    http://opensource.org/licenses/lgpl-3.0.html
 */


class IsotopeProduct extends Controller
{

	/**
	 * Name of the current table
	 * @var string
	 */
	protected $strTable = 'tl_product_data';
	
	/**
	 * Data array
	 * @var array
	 */
	protected $arrData = array();

	/**
	 * Attributes assigned to this product type
	 * @var array
	 */
	protected $arrAttributes = array();
	
	/**
	 * Variant attributes assigned to this product type
	 * @var array
	 */
	protected $arrVariantAttributes = array();

	/**
	 * Product Options
	 * @var array
	 */
	protected $arrOptions = array();

	/**
	 * Downloads for this product
	 */
	protected $arrDownloads = array();

	/**
	 * Cache properties, cache is dropped when serializing
	 */
	protected $arrCache = array();
		
	/**
	 * for widgets, helps determine the encoding type for a form
	 * @todo this seems not to be in use... it is only filled, never used.
	 * @var boolean
	 */
	protected $hasUpload = false;
	
	/**
	 * for widgets, don't submit if certain validation(s) fail
	 * @var boolean
	 */
	protected $doNotSubmit = false;


	/**
	 * Construct the object
	 */
	public function __construct($arrData)
	{
		parent::__construct();
		$this->import('Database');
		$this->import('Isotope');

		$this->arrData = $arrData;		

		$objType = $this->Database->prepare("SELECT * FROM tl_product_types WHERE id=?")->execute($this->arrData['type']);
		$this->arrAttributes = deserialize($objType->attributes, true);
		$this->arrCache['list_template'] = $objType->list_template;
		$this->arrCache['reader_template'] = $objType->reader_template;
		$this->arrVariantAttributes = $objType->variants ? deserialize($objType->variant_attributes) : false;

		// Cache downloads for this product
		if ($objType->downloads)
		{
			$this->arrDownloads = $this->Database->prepare("SELECT * FROM tl_product_downloads WHERE pid=?")->execute($this->arrData['id'])->fetchAllAssoc();
		}

		if (is_array($this->arrVariantAttributes))
		{
			$objProduct = $this->Database->prepare("SELECT MIN(" . $this->Isotope->Store->priceField . ") AS low_price, MAX(" . $this->Isotope->Store->priceField . ") AS high_price FROM tl_product_data WHERE pid=?")
										 ->execute($this->id);

			$this->low_price = $this->Isotope->calculatePrice($objProduct->low_price, $this->arrData['tax_class']);
			$this->high_price = $this->Isotope->calculatePrice($objProduct->high_price, $this->arrData['tax_class']);
		}
		else
		{
			$this->low_price = $this->Isotope->calculatePrice($this->arrData[$this->Isotope->Store->priceField], $this->arrData['tax_class']);
			$this->high_price = $this->Isotope->calculatePrice($this->arrData[$this->Isotope->Store->priceField], $this->arrData['tax_class']);
		}
	}


	/**
	 * Get a property
	 * @return mixed
	 */
	public function __get($strKey)
	{
		switch( $strKey)
		{
			case 'id':
			case 'pid':
			case 'href_reader':
				return $this->arrData[$strKey];

			case 'price':
				return $this->Isotope->calculatePrice($this->arrData[$this->Isotope->Store->priceField], $this->arrData['tax_class']);

			case 'price_override':
				return ($this->arrData[$this->Isotope->Store->priceOverrideField] ? $this->arrData[$this->Isotope->Store->priceOverrideField] : '');

			case 'total_price':
				return ($this->quantity_requested ? $this->quantity_requested : 1) * $this->price;

			case 'low_price':
			case 'high_price':
				return $this->Isotope->calculatePrice($this->arrData[$strKey], $this->arrData['tax_class']);

			case 'hasDownloads':
				return count($this->arrDownloads) ? true : false;

			default:
				// Initialize attribute
				if (!isset($this->arrCache[$strKey]))
				{
					if (isset($GLOBALS['TL_DCA'][$this->strTable]['fields'][$strKey]))
					{
						switch( $GLOBALS['TL_DCA'][$this->strTable]['fields'][$strKey]['inputType'] )
						{
							case 'mediaManager':
								$varValue = array();
								$arrImages = deserialize($this->arrData[$strKey]);

								if(is_array($arrImages) && count($arrImages))
								{
									foreach( $arrImages as $k => $file )
									{
										$strFile = 'isotope/' . substr($file['src'], 0, 1) . '/' . $file['src'];

										if (is_file(TL_ROOT . '/' . $strFile))
										{
											$objFile = new File($strFile);

											if ($objFile->isGdImage)
											{
												$file['is_image'] = true;

												foreach( array('large', 'medium', 'thumbnail', 'gallery') as $size )
												{
													$strImage = $this->getImage($strFile, $this->Isotope->Store->{$size . '_image_width'}, $this->Isotope->Store->{$size . '_image_height'});
													$arrSize = @getimagesize(TL_ROOT . '/' . $strImage);

													$file[$size] = $strImage;

													if (is_array($arrSize) && strlen($arrSize[3]))
													{
														$file[$size . '_size'] = $arrSize[3];
													}
												}

												$varValue[] = $file;
											}
										}
									}
								}
								break;
						}
					}

					switch( $strKey )
					{
						case 'formatted_price':
							$varValue = $this->Isotope->formatPriceWithCurrency($this->price);
							break;
							
						case 'formatted_low_price':
							$varValue = $this->Isotope->formatPriceWithCurrency($this->low_price);
							break;
							
						case 'formatted_high_price':
							$varValue = $this->Isotope->formatPriceWithCurrency($this->high_price);
							break;
							
						case 'formatted_total_price':
							$varValue = $this->Isotope->formatPriceWithCurrency($this->total_price);
							break;

						case 'images':
							// No image available, add default image
							if (!count($varValue) && is_file(TL_ROOT . '/' . $this->Isotope->Store->missing_image_placeholder))
							{
								foreach( array('large', 'medium', 'thumbnail', 'gallery') as $size )
								{
									$strImage = $this->getImage($this->Isotope->Store->missing_image_placeholder, $this->Isotope->Store->{$size . '_image_width'}, $this->Isotope->Store->{$size . '_image_height'});
									$arrSize = @getimagesize(TL_ROOT . '/' . $strImage);

									$file[$size] = $strImage;

									if (is_array($arrSize) && strlen($arrSize[3]))
									{
										$file[$size . '_size'] = $arrSize[3];
									}
								}

								$varValue[] = $file;
							}
							break;
					}

					$this->arrCache[$strKey] = $varValue ? $varValue : deserialize($this->arrData[$strKey]);
				}

				return $this->arrCache[$strKey];
		}
	}


	/**
	 * Set a property
	 */
	public function __set($strKey, $varValue)
	{
		switch( $strKey )
		{
			case 'reader_jumpTo':
				$this->arrData['href_reader'] = $this->generateFrontendUrl($this->Database->prepare("SELECT * FROM tl_page WHERE id=?")->execute($varValue)->fetchAssoc(), '/product/' . $this->arrData['alias']);
				break;
				
			case 'reader_jumpTo_Override':
				$this->arrData['href_reader'] = $varValue;
				break;
				
			case 'sku':
			case 'name':
			case 'low_price':
			case 'high_price':
				$this->arrData[$strKey] = $varValue;
				break;

			case 'price':
				$this->arrData[$this->Isotope->Store->priceField] = $varValue;
				break;

			case 'price_override':
				$this->arrData[$this->Isotope->Store->overridePriceField] = $varValue;
				break;

			default:
				$this->arrCache[$strKey] = $varValue;
		}

	}


	/**
	 * Destroy unnessessary data when serializing
	 */
	public function __sleep()
	{
		//clean up product object - remove non-essential data to reduce table size.
		unset($this->arrData['description'], $this->arrData['teaser']);

		return array('arrAttributes', 'arrVariantAttributes', 'arrDownloads', 'arrData', 'arrOptions');
	}


	/**
	 * Make sure required data is available
	 */
	public function __wakeup()
	{
		$this->import('Config');
		$this->import('Input');
		$this->import('Environment');
		$this->import('Session');
		$this->import('Database');
		$this->import('Isotope');
	}

	
	/**
	 * Return the current record as associative array
	 * @return array
	 */
	public function getData()
	{
		return $this->arrData;
	}


	/**
	 * Return all downloads for this product
	 */
	//!@todo: Confirm that files are available, possibly on __wakeup() ?
	public function getDownloads()
	{
		return $this->arrDownloads;
	}


	public function getOptions()
	{
		$arrOptions = array();
		
		foreach( $this->arrOptions as $name => $value )
		{
			$arrOptions[] = $this->getProductOptionValues($name, $GLOBALS['TL_DCA']['tl_product_data']['fields'][$name]['inputType'], $value);
		}
		
		return $arrOptions;
	}
	
	
	/**
	 * A bad function, but it is required to update old tl_iso_order_items.product_options data
	 */
	public function setOptions($arrOptions)
	{
		$this->arrOptions = $arrOptions;
	}


	/**
	 * Return all attributes for this product
	 */
	public function getAttributes()
	{
		$arrData = array();

		foreach( $this->arrAttributes as $attribute )
		{
			$arrData[$attribute] = $this->$attribute;
		}

		return $arrData;
	}

	
	/**
	 * Generate a product template
	 */
	public function generate($strTemplate, &$objModule)
	{
		$this->validateVariant();
		
		$objTemplate = new FrontendTemplate($strTemplate);
		
		$arrProductOptions = array();
		$arrAttributes = $this->getAttributes();
		
		foreach( $arrAttributes as $attribute => $varValue )
		{
			switch( $attribute )
			{
				case 'images':
					if (is_array($varValue) && count($varValue))
					{
						$objTemplate->hasImage = true;
						
						//$objTemplate->mainImage = array_shift($varValue);
						$objTemplate->mainImage = $varValue[0];
						
						//if (count($varValue))
						//{
						$objTemplate->hasGallery = true;
						$objTemplate->gallery = $varValue;
						//}
					}
					break;
					
				default:
					if ($GLOBALS['TL_DCA']['tl_product_data']['fields'][$attribute]['attributes']['is_customer_defined'] || $GLOBALS['TL_DCA']['tl_product_data']['fields'][$attribute]['attributes']['add_to_product_variants'])
					{
						$objTemplate->hasOptions = true;
						$arrProductOptions[$attribute] = $this->generateProductOptionWidget($attribute);
					}
					else
					{						
						$objTemplate->$attribute = $this->generateAttribute($attribute, $varValue);
					}
					break;
			}
        }
        
        
        // Buttons
		$arrButtons = array();
		if (isset($GLOBALS['TL_HOOKS']['isoButtons']) && is_array($GLOBALS['TL_HOOKS']['isoButtons']))
		{
			foreach ($GLOBALS['TL_HOOKS']['isoButtons'] as $callback)
			{
				$this->import($callback[0]);
				$arrButtons = $this->$callback[0]->$callback[1]($arrButtons);
			}
			
			$arrButtons = array_intersect_key($arrButtons, array_flip(deserialize($objModule->iso_buttons, true)));
		}
		
		
		if ($this->Input->post('FORM_SUBMIT') == 'iso_product_'.$this->id && !$this->doNotSubmit)
		{			
			foreach( $arrButtons as $button => $data )
			{
				if (strlen($this->Input->post($button)))
				{
					if (is_array($data['callback']) && count($data['callback']) == 2)
					{
						$this->import($data['callback'][0]);
						$this->{$data['callback'][0]}->{$data['callback'][1]}($this, $objModule);
					}
					break;
				}
			}
		}
		
		
		$objTemplate->buttons = $arrButtons;
		$objTemplate->quantityLabel = $GLOBALS['TL_LANG']['MSC']['quantity'];
		$objTemplate->useQuantity = $objModule->iso_use_quantity;
			

		$objTemplate->raw = $this->arrData;
		$objTemplate->href_reader = $this->href_reader;
		
		$objTemplate->label_detail = $GLOBALS['TL_LANG']['MSC']['detailLabel'];
		
		$objTemplate->price = $this->formatted_price;
		$objTemplate->low_price = $this->formatted_low_price;
		$objTemplate->high_price = $this->formatted_high_price;
		$objTemplate->priceRangeLabel = $GLOBALS['TL_LANG']['MSC']['priceRangeLabel'];
		$objTemplate->options = $arrProductOptions;	
		$objTemplate->hasOptions = count($arrProductOptions) ? true : false;
		
		$objTemplate->enctype = $this->hasUpload ? 'multipart/form-data' : 'application/x-www-form-urlencoded';
		$objTemplate->formId = 'iso_product_'.$this->id;
		$objTemplate->action = ampersand($this->Environment->request, true);
		$objTemplate->formSubmit = 'iso_product_'.$this->id;
		
		$GLOBALS['TL_MOOTOOLS'][] = "<script type=\"text/javascript\">new IsotopeProduct('" . $objModule->id . "', '" . $this->id . "', ['ctrl_" . implode("_".$this->id."', 'ctrl_", array_keys($arrProductOptions)) . "_".$this->id."']);</script>";
		
		return $objTemplate->parse();
	}
	
	
	public function generateAjax()
	{
		$this->validateVariant();
		
		$arrOptions = array();
		$arrAttributes = $this->getAttributes();
		
	
		foreach( $arrAttributes as $attribute => $varValue )
		{
			if ($GLOBALS['TL_DCA']['tl_product_data']['fields'][$attribute]['attributes']['is_customer_defined'] || $GLOBALS['TL_DCA']['tl_product_data']['fields'][$attribute]['attributes']['add_to_product_variants'])
			{
				$arrOptions[] = array
				(
					'id'		=> ('ctrl_' . $attribute . '_' . $this->id),
					'html'		=> $this->generateProductOptionWidget($attribute, true),
				);
			}
			elseif (is_array($this->arrVariantAttributes) && in_array($attribute, $this->arrVariantAttributes))
			{
				$arrOptions[] = array
				(
					'id'		=> ($attribute . '_' . $this->id),
					'html'		=> $this->generateAttribute($attribute, $varValue),
				);
			}
        }
        
        $arrOptions[] = array
        (
        	'id'	=> 'ajax_price',
        	'html'	=> ('<div id="ajax_price">'.$this->formatted_price.'</div>'),
        );
        
        return $arrOptions;
	}
	
	
	protected function generateAttribute($attribute, $varValue)
	{
		$strBuffer = '';
		
		switch($GLOBALS['TL_DCA']['tl_product_data']['fields'][$attribute]['attributes']['type'])
		{
			case 'select':
			case 'radio':
			case 'checkbox':
				if($GLOBALS['TL_DCA']['tl_product_data']['fields'][$attribute]['attributes']['use_alternate_source'])
				{																											
					$objData = $this->Database->prepare("SELECT * FROM " . $GLOBALS['TL_DCA']['tl_product_data']['fields'][$attribute]['attributes']['list_source_table'] . " WHERE id=?")
											  ->limit(1)									 
											  ->execute($varValue);
					
					if(!$objData->numRows)
					{										
						$strBuffer = $varValue;
					}
					else
					{
						//!@todo this is not going to work, whats this?
						$strBuffer = array
						(
							'id'	=> $varValue,
							'raw'	=> $objData->fetchAssoc(),
						);
					}
				}
				else
				{
					//check for a related label to go with the value.
					$arrOptions = deserialize($GLOBALS['TL_DCA']['tl_product_data']['fields'][$attribute]['attributes']['option_list']);
					$varValues = deserialize($varValue);
					$arrLabels = array();
					
					if($GLOBALS['TL_DCA']['tl_product_data']['fields'][$attribute]['attributes']['is_visible_on_front'])
					{
						foreach($arrOptions as $option)
						{
							if(is_array($varValues))
							{
								if(in_array($option['value'], $varValues))
								{
									$arrLabels[] = $option['label'];
								}
							}
							else
							{	
								if($option['value']===$v)
								{
									$arrLabels[] = $option['label'];
								}
							}
						}
						
						if($arrLabels)
						{									
							$strBuffer = join(',', $arrLabels); 
						}
					}
				}
				break;
				
			case 'textarea':
				$strBuffer = $GLOBALS['TL_DCA']['tl_product_data']['fields'][$attribute]['attributes']['use_rich_text_editor'] ? $varValue : nl2br($varValue);
				break;
																														
			default:
				if(!isset($GLOBALS['TL_DCA']['tl_product_data']['fields'][$attribute]['attributes']['is_visible_on_front']) || $GLOBALS['TL_DCA']['tl_product_data']['fields'][$attribute]['attributes']['is_visible_on_front'])
				{
					//just direct render
					$strBuffer = $varValue;
				}
				break;
		}
		
		return '<div id="' . $attribute . '_' . $this->id . '" class="' . $attribute . '">' . $strBuffer . '</div>';
	}
	
	
	/** 
	 * Return a widget object based on a product attribute's properties.
	 *
	 * @access protected
	 * @param string $strField
	 * @param array $arrData
	 * @return string
	 */
	protected function generateProductOptionWidget($strField, $blnAjax=false)
	{
		$arrData = $GLOBALS['TL_DCA']['tl_product_data']['fields'][$strField];
		$strClass = strlen($GLOBALS['ISO_ATTR'][$arrData['inputType']]['class']) ? $GLOBALS['ISO_ATTR'][$arrData['inputType']]['class'] : $GLOBALS['TL_FFL'][$arrData['inputType']];
									
		// Continue if the class is not defined
		if (!$this->classFileExists($strClass))
		{
			return '';
		}

		$arrData['eval']['mandatory'] = ($arrData['eval']['mandatory'] && !$blnAjax) ? true : false;
		$arrData['eval']['required'] = $arrData['eval']['mandatory'];
		
		if ($arrData['attributes']['add_to_product_variants'] && is_array($arrData['options']))
		{
			$arrData['eval']['includeBlankOption'] = true;
			$arrSearch = array('pid'=>$this->arrData['id']);
			
			foreach( $this->arrOptions as $name => $value )
			{
				if ($GLOBALS['TL_DCA']['tl_product_data']['fields'][$name]['attributes']['add_to_product_variants'])
				{
					$arrSearch[$name] = $value;
				}
			}
			
			$arrOptions = $this->Database->prepare("SELECT " . $strField . " FROM tl_product_data WHERE language='' AND published='1' AND " . implode("=? AND ", array_keys($arrSearch)) . "=? GROUP BY " . $strField)->execute($arrSearch)->fetchEach($strField);
			
			foreach( $arrData['options'] as $k => $v )
			{
				if (is_array($v))
				{
					foreach( $v as $kk => $vv )
					{
						if (!in_array($kk, $arrOptions))
						{
							unset($arrData['options'][$k][$kk]);
						}
					}
					
					if (!count($arrData['options'][$k]))
					{
						unset($arrData['options'][$k]);
					}
				}
				else
				{
					if (!in_array($k, $arrOptions))
					{
						unset($arrData['options'][$k]);
					}
				}
			}
		}
		
		if (is_array($GLOBALS['ISO_ATTR'][$arrData['attributes']['type']]['callback']) && count($GLOBALS['ISO_ATTR'][$arrData['attributes']['type']]['callback']))
		{
			foreach( $GLOBALS['ISO_ATTR'][$arrData['attributes']['type']]['callback'] as $callback )
			{
				$this->import($callback[0]);
				$arrData = $this->{$callback[0]}->{$callback[1]}($strField, $arrData, $this);
			}
		}
		
		$objWidget = new $strClass($this->prepareForWidget($arrData, $strField));
					
		$objWidget->storeValues = true;
		$objWidget->tableless = true;
		$objWidget->id .= "_" . $this->id;
		
		// Validate input
		if ($this->Input->post('FORM_SUBMIT') == 'iso_product_'.$this->id)
		{
			$objWidget->validate();

			if ($objWidget->hasErrors())
			{
				$this->doNotSubmit = true;					
			}

			// Store current value
			elseif ($objWidget->submitInput())
			{
				$varValue = $objWidget->value;
			
				// Convert date formats into timestamps
				if (strlen($varValue) && in_array($arrData['eval']['rgxp'], array('date', 'time', 'datim')))
				{
					$objDate = new Date($varValue, $GLOBALS['TL_CONFIG'][$arrData['eval']['rgxp'] . 'Format']);
					$varValue = $objDate->tstamp;
				}
				
				$this->arrOptions[$strField] = $varValue;
			}
		}
		
		if ($objWidget instanceof uploadable)
		{
			$this->hasUpload = true;
		}
		
		return $objWidget->parse() . '<br />';
	}
	
	
	protected function getProductOptionValues($strField, $inputType, $varValue)
	{	
		$arrData = $GLOBALS['TL_DCA']['tl_product_data']['fields'][$strField];
		
		switch($inputType)
		{
			case 'radio':
			case 'checkbox':
			case 'select':
				
				//get the actual labels, not the key reference values.
				$arrOptions = $this->getOptionList($arrData['attributes']);
				
				if(is_array($varValue))
				{
					foreach($varValue as $value)
					{
						foreach($arrOptions as $option)
						{
							if($option['value']==$value)
							{
								$varOptionValues[] = $option['label'];
								break;
							}
						}
					}	
				}
				else
				{
					foreach($arrOptions as $option)
					{
						if($option['value']==$varValue)
						{
							$varOptionValues[] = $option['label'];
							break;
						}
					}
				}
				break;
				
			default:
				//these values are not by reference - they were directly entered.  
				if(is_array($varValue))
				{
					foreach($varValue as $value)
					{
						$varOptionValues[] = $value;
					}
				}
				else
				{
					$varOptionValues[] = $varValue;
				}
				
				break;
		
		}		
		
		$arrValues = array
		(
			'name'		=> $arrData['label'][0],
			'values'	=> $varOptionValues			
		);
		
		return $arrValues;
	}


	protected function getOptionList($arrAttributeData)
	{
		if($arrAttributeData['use_alternate_source']==1)
		{
			if(strlen($arrAttributeData['list_source_table']) > 0 && strlen($arrAttributeData['list_source_field']) > 0)
			{
				//$strForeignKey = $arrAttributeData['list_source_table'] . '.' . $arrAttributeData['list_source_field'];
				$objOptions = $this->Database->execute("SELECT id, " . $arrAttributeData['list_source_field'] . " FROM " . $arrAttributeData['list_source_table']);
				
				if(!$objOptions->numRows)
				{
					return array();
				}
				
				while($objOptions->next())
				{
					$arrValues[] = array
					(
						'value'		=> $objOptions->id,
						'label'		=> $objOptions->$arrAttributeData['list_source_field']
					);
				}
			}
		}
		else
		{
			$arrValues = deserialize($arrAttributeData['option_list']);
		}
		
		return $arrValues;
	}
	
	
	protected function validateVariant()
	{
		if (!is_array($this->arrVariantAttributes))
			return;
			
		$arrOptions = array();
		
		foreach( $this->arrAttributes as $attribute )
		{
			if ($GLOBALS['TL_DCA']['tl_product_data']['fields'][$attribute]['attributes']['add_to_product_variants'])
			{
				$arrOptions[$attribute] = $this->Input->post($attribute);
			}
		}
		
		if (count($arrOptions))
		{
			$objVariant = $this->Database->prepare("SELECT * FROM tl_product_data WHERE pid=? AND " . implode("=? AND ", array_keys($arrOptions)) . "=?")->execute(array_merge(array($this->id), $arrOptions));
			
			// Must match 1 variant, must not match multiple
			if ($objVariant->numRows == 1)
			{
				$this->arrData['vid'] = $objVariant->id;
				
				foreach( $this->arrVariantAttributes as $attribute )
				{
					$this->arrData[$attribute] = $objVariant->$attribute;
					unset($this->arrCache[$attribute]);
				}
			}
			else
			{
				$this->doNotSubmit = true;
			}
		}
	}
}

