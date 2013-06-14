<?php
	/***************************************************************
	*  Copyright notice
	*
	*  Version 2.1 2007 Thorsten Wust (t.wuest@wuest-media.de)
    *  Version 2.2 Modified 2013 by Mikkel Olrik (molrik@molrikdata.dk)
	*  All rights reserved
	*
	*  This script is part of the TYPO3 project. The TYPO3 project is
	*  free software; you can redistribute it and/or modify
	*  it under the terms of the GNU General Public License as published by
	*  the Free Software Foundation; either version 2 of the License, or
	*  (at your option) any later version.
	*
	*  The GNU General Public License can be found at
	*  http://www.gnu.org/copyleft/gpl.html.
	*
	*  This script is distributed in the hope that it will be useful,
	*  but WITHOUT ANY WARRANTY; without even the implied warranty of
	*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	*  GNU General Public License for more details.
	*
	*  This copyright notice MUST APPEAR in all copies of the script!
	***************************************************************/
	/**
	* Plugin 'Tw Rss Feeds' for the 'tw_rssfeeds' extension.
	*
	* @author Thorsten W�st <t.wuest@wuest-media.de>
	*/

	require_once (PATH_t3lib . 'class.t3lib_xml.php');
	require_once(PATH_tslib."class.tslib_pibase.php");

	class tx_twrssfeeds_pi1 extends tslib_pibase {
		var $prefixId = "tx_twrssfeeds_pi1";// Same as class name
		var $scriptRelPath = "pi1/class.tx_twrssfeeds_pi1.php"; // Path to this script relative to the extension dir.
		var $extKey = "tw_rssfeeds"; // The extension key.
		var $pi_USER_INT_obj = TRUE;

		########################################################
		########### set vars for parser #######################
		######################################################

		var $parser;
		var $case_folding = TRUE;
		var $data = array();
		var $current_tag = '';
		var $item_count = 0;
		var $image_flag = false;
		var $input_flag = false;
		var $channel_flag = false;
		var $item_flag = false;
        var $item_channel_link = '';

		/**
		 * [initialize the url the parser will be able to work]
		 *
		 * @param	string		$content : function output is added to this
		 * @param	array		$conf : configuration array
		 * @return	string		$content: complete content generated by the tw_rss_feeds plugin
		*/
		function main($content, $conf) {
			$this->conf = $conf;
			$this->pi_setPiVarDefaults();
			$this->pi_loadLL();
			$this->pi_initPIflexForm(); //init the flexes :)

			//$GLOBALS["TSFE"]->set_no_cache();    //temp while developing

			$content = "";

			$get_url = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'goforurl', 'sDEF');
			$get_thefeed = $get_url ? $get_url:
			$this->conf['url'];

			$get_charset = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'charset', 'sDEF');
			$get_char = $get_charset ? $get_charset:
			$this->conf['charset'];

			if ($get_url == "" && $this->conf['url'] == "") {
				//return "Es wurde keine URL eingegeben. Bitte machen Sie es so!"; //Mehrsprachig machen
                return "Der er ikke angivet en URL. Gør venligst dette!"; //DK
			}
			
			switch($get_char){
				
				case 'KEINE':
				$content .= $this->parseRSS($get_thefeed);
				break;
				
				case 'UTF8':
				$content .= mb_convert_encoding($this->parseRSS($get_thefeed),"UTF-8","UTF-8");				
				break;
				
				case 'SJIS':
				$content .= mb_convert_encoding($this->parseRSS($get_thefeed),"UTF-8","EUC-JP");
				break;	

				case 'EUCJP':
				$content .= mb_convert_encoding($this->parseRSS($get_thefeed),"UTF-8","SJIS");
				break;

				case 'ISO88591':
				$content .= mb_convert_encoding($this->parseRSS($get_thefeed),"UTF-8","iso-8859-1");
				break;
				
				case 'ISO88592':
				$content .= mb_convert_encoding($this->parseRSS($get_thefeed),"UTF-8","iso-8859-2");
				break;				

				case 'ISO88593':
				$content .= mb_convert_encoding($this->parseRSS($get_thefeed),"UTF-8","iso-8859-3");
				break;
				
				case 'ISO88594':
				$content .= mb_convert_encoding($this->parseRSS($get_thefeed),"UTF-8","iso-8859-4");
				break;
				
				case 'ISO88595':
				$content .= mb_convert_encoding($this->parseRSS($get_thefeed),"UTF-8","iso-8859-5");
				break;	
				
				case 'ISO88596':
				$content .= mb_convert_encoding($this->parseRSS($get_thefeed),"UTF-8","iso-8859-6");
				break;	
				
				case 'ISO88597':
				$content .= mb_convert_encoding($this->parseRSS($get_thefeed),"UTF-8","iso-8859-7");
				break;	
				
				case 'ISO88598':
				$content .= mb_convert_encoding($this->parseRSS($get_thefeed),"UTF-8","iso-8859-8");
				break;																	
				
				case 'AUTO':
				$content .= mb_convert_encoding($this->parseRSS($get_thefeed),"UTF-8","auto");				
				break;							
				
				default:
				$content .= $this->parseRSS($get_thefeed);
				break;
				
			}

			
			return $content;
		}
		


		/**
		 * [Here we get the flexform datas. we need them for the interesting possibilities of tw_rss_feeds. Further we parse the the $file through getRSSData getting the feed list.]
		 *
		 * ALL FILES ARE FOR FlexForms
		 *
		 * @param	string		$file: url string which will parsed by the xml parser
		 * @param	string		$get_themaxitems: get the maximum items you wanna dispaly
		 * @param	string		$get_thedescsep: separator between content blocks
		 * @param	string		$get_theItemSeparator: separator between content blocks
		 * @param	string		$get_theLinkTarget: check out if you want to set linktarget
		 * @param	string		$get_theSubmitValue: value for forms
		 * @param	string		$get_theChannelDesc: get channel description
		 * @param	string		$get_theItemDesc: get content
		 * @param	string		$get_theImage: get the image
		 * @param	string		$get_thedescsep: separator between content blocks
		 */
		function parseRSS($file) {
		    
            $moheader = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'moheader', 'sDEF');
            $get_moheader = $moheader ? $moheader:
            $this->conf['moheader'];            

			$get_maxitems = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'gomaxitems', 'sDEF');
			$get_themaxitems = $get_maxitems ? $get_maxitems:
			$this->conf['maxItem'];

			$DescSeparator = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'descSeps', 'sDEF');
			$get_thedescsep = $DescSeparator ? $DescSeparator:
			$this->conf['DescSeparator'];

			$ItemSeparator = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'itemSeps', 'sDEF');
			$get_theItemSeparator = $ItemSeparator ? $ItemSeparator:
			$this->conf['ItemSeparator'];

			$LinkTarget = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'linktarget', 'sDEF');
			$get_theLinkTarget = $LinkTarget ? $LinkTarget:
			$this->conf['LinkTarget'];

			if ($LinkTarget == "" && $this->conf['LinkTarget'] == "") {
				$get_theLinkTarget = "_blank";
			}

			/* $SubmitValue = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'subdesc', 'sDEF');
			$get_theSubmitValue = $SubmitValue ? $SubmitValue:
			$this->conf['SubmitValue'];

			if ($SubmitValue == "" && $this->conf['SubmitValue'] == "") {
				$get_theSubmitValue = "Submit";
			} */

			$ChannelDesc = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'channeldesc', 'specificfeeds');
			$get_theChannelDesc = $ChannelDesc ? $ChannelDesc:
			$this->conf['ChannelDesc'];

			$ItemDesc = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'itemdesc', 'specificfeeds');
			$get_theItemDesc = $ItemDesc ? $ItemDesc:
			$this->conf['ItemDesc'];

			$Image = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'imagedesc', 'specificfeeds');
			$get_theImage = $Image ? $Image:
			$this->conf['Image'];

			$Textinput = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'textdesc', 'specificfeeds');
			$get_theTextinput = $Textinput ? $Textinput:
			$this->conf['Textinput'];

			$TitleFile = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'titlehide', 'specificfeeds');
			$get_the_title = $TitleFile ? $TitleFile:
			$this->conf['TitleFile'];

			$LinkDau = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'linkhide', 'specificfeeds');
			$get_da_link_source = $LinkDau ? $LinkDau:
			$this->conf['LinkDau'];

			$LinkAfter = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'linkafter', 'specificfeeds');
			$get_first_line_after_link = $LinkAfter ? $LinkAfter:
			$this->conf['LinkAfter'];

			$HTMLEnt = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'htmlent', 'specificfeeds');
			$get_html_entities = $HTMLEnt ? $HTMLEnt:
			$this->conf['HTMLEnt'];

			$this->getRSSData($file);

			if ($get_themaxitems < $this->item_count) {
				$this->item_count = $get_themaxitems;
			}

			$content .= '<div id="twrss_table" class="motwrss_holder">';
            
            //moheader - den redigerbare overskrift
            if (trim($get_moheader)<>'') {
                $content .= '<h2 class="twrss_bodytext motw_header">'.$get_moheader.'</h2>';            
            }

            //channel header / hidden feedtitle
			if ($get_the_title !== "true") {
				$content .= '';
			} else {
				$content .= '<div class="twrss_head_channel motwrss_head_channel">'.$this->data['CHANNEL']['TITLE'].'</div>';
				$content .= $get_thedescsep;
			}

            //channel description / Display channel description
			if ($get_theChannelDesc !== "true") {
				$content .= '';
			} else {
				$content .= '<div class="twrss_bodytext twrss_channel_description">'.$this->data['CHANNEL']['DESCRIPTION'].'</div>';
				$content .= $get_thedescsep;
			}

            //channel link / hidden feedlink
            $channellinkaddr = trim($this->data['CHANNEL']['LINK']); //full link
            $channellinktext = preg_replace("/^https?:\/\/(.+)$/i","\\1", $channellinkaddr); //remove http://
            $channellinktext = preg_replace("/\//","", $channellinktext); // remove /
            $channellinkatag = '<a href="'.$channellinkaddr.'" target="'.$get_theLinkTarget.'">'.$channellinktext.'</a>';  //adding atag
            
			if ($get_da_link_source !== "true") {
				$content .= '';
			} else {
			    $channellinkwrap = '<div class="twrss_bodytext twrss_channel_link motwrss_channel_link">'.$channellinkatag.'</div>'; // adding div-wrap
				//$content .= '<div class="twrss_bodytext twrss_channel_link motwrss_channel_link"><a href="'.$this->data['CHANNEL']['LINK'].'" target="'.$get_theLinkTarget.'">';
				//$content .= $channellinkwrap; //don't show all here
				//$content .= '</a></div>';
				//$content .= $get_thedescsep;
			}

            //image / display image (image is in descr!)
			/* if ($get_theImage == "true" && isset($this->data['IMAGE'])) {
				$content .= '<div class="twrss_imagefile">';
				$content .= '<a href="'.$this->data['IMAGE']['LINK'].'" target="'.$get_theLinkTarget.'">';
				$content .= '<img src="'.$this->data['IMAGE']['URL'].'" alt="'.@$this->data['IMAGE']['TITLE'].'" border="0"';
				if (isset($this->data['IMAGE']['WIDTH']))
					$content .= ' width="'.$this->data['IMAGE']['WIDTH'].'"';
				if (isset($this->data['IMAGE']['HEIGHT']))
					$content .= ' height="'.$this->data['IMAGE']['HEIGHT'].'"';
				$content .= ' />';
				$content .= '</a>';
				$content .= '</div>';
			} */

			if ($get_first_line_after_link !== "true") {
				$content .= '';
			} else {
				$content .= $get_theItemSeparator;
			}

			for ($i = 1; $i <= $this->item_count; $i++) {
			    
               if (isset($this->data['ITEM'][$i]['PUBDATE'])) {
                    $itemdateraw = $this->data['ITEM'][$i]['PUBDATE']; //get date from rss

                    //$content .= 'time: '.time().' blogtime: '.date("d. m Y",strtotime($itemdateraw));   //test
                    
                    $itemdatetimestamp = strtotime($itemdateraw);   //convert to timestamp
                    $itemday = date("d",$itemdatetimestamp);    //extract day
                    $itemmonth = date("n",$itemdatetimestamp);  //extract month without zeros
                    $danishmonths = array('zero','januar','februar','marts','april','maj','juni','juli','august','september','oktober','november','december');  //danske måneder
                    $itemmonthdk = $danishmonths[intval($itemmonth)];   //udtræk dansk måned
                    $itemyear = date("Y",$itemdatetimestamp);  //extract full year
                    $itemdateadj = $itemday.'. '.$itemmonthdk.' '.$itemyear;    //sammensæt dato på dansk
                    
                    //$content .= '<div class="twrss_bodytext motwrss_item_pubdate">'.'dato: '.$itemdateadj.'</div>';                    
                }
                
                
				//$going = str_replace('<', '&lt;', $this->data['ITEM'][$i]['TITLE']);
				//$go_the_head = str_replace('>', '&gt;', $going);
				//$content .= '<div class="twrss_bodytext twrss_item_link motwrss_item_link"><a href="'.$this->data['ITEM'][$i]['LINK'].'" target="'.$get_theLinkTarget.'" >'.$go_the_head.'</a></div>';
				//$content .= $get_thedescsep;
				$moitemlinkbegin = '<a href="'.$this->data['ITEM'][$i]['LINK'].'" target="'.$get_theLinkTarget.'" >';
                $moitemlinkend = '</a>';
                //$content .= $moitemlinkbegin.'Testlink'.$moitemlinkend;
                
				if ($get_theItemDesc == "true" && isset($this->data['ITEM'][$i]['DESCRIPTION'])) {
				    $itemdescrall = trim($this->data['ITEM'][$i]['DESCRIPTION']);  //alt indhold
                    $itemimageandtext = preg_replace("/\<br(\s*)?\/?\>/i","", $itemdescrall); // remove <br/> and <br />
                    //$itemimageandtext = preg_replace("/<br\/>/","", $itemdescrall); //old version
                    //$itemimageandtext = $itemdescrall;
                    $itemimageandtextarr = preg_split("/<p>/", $itemimageandtext); //image and text into array
                    $itemimage = $itemimageandtextarr[0];   //images
                    $itemtext = '<p>'.substr($itemimageandtext, strpos($itemimageandtext, '<p>'));
                    /* debug 
                        $content .= '<div class="twrss_bodytext motwrss_item_content">'.
                        ' itemtext:'.$itemtext.
                        '</div>';
                         */
                    
                    $pos = strpos(strtolower($itemimage),'<iframe');    //position af evt. iframe med video 
                    if($pos === false) {
                        // string needle NOT found in haystack
                    } else {
                        // string needle found in haystack - så skal bredden ændres
                        $wpos = strpos(strtolower($itemimage),'width'); //hvor starter bredden
                        $hpos = strpos(strtolower($itemimage),'height'); //hvor starter højden
                        $srcpos = strpos(strtolower($itemimage),'src'); //hvor starter kilden
                        $wbegin = $wpos + 7; //width begin
                        $wend = $hpos - 2; //width end
                        $worg = intval(substr($itemimage, $wbegin, (intval($wend)-intval($wbegin))));   //extract width
                        $hbegin = $hpos + 8; //height begin
                        $hend = $srcpos - 2; //height end
                        $horg = intval(substr($itemimage, $hbegin, (intval($hend)-intval($hbegin))));   //extract height
                        $wadj = intval($this->conf['columnWidth']); //get value from ts setup
                        $hadj = round($horg * ($wadj / $worg)); //compute relative height
                        /* debug
                        $content .= '<div class="twrss_bodytext motwrss_item_content">'.
                        'IFRAME FOUND'.
                        ' wpos:'.$wpos.' hpos:'.$hpos.' scrpos:'.$srcpos.'<br />'.
                        ' w:'.$worg.
                        ' h:'.$horg.
                        ' wa:'.$wadj.
                        ' ha:'.$hadj.
                        '</div>';
                         */
                        $itemiframemodw = substr_replace($itemimage, $wadj, $wbegin, (intval($wend)-intval($wbegin)));  //replace width
                        $itemiframemodh = substr_replace($itemiframemodw, $hadj, $hbegin, (intval($hend)-intval($hbegin))); //replace height
                        $itemimage = $itemiframemodh;  //updating the iframe with the new dimensions
                     
                    }        
                    $imgcount = substr_count($itemimage, '<img');
                    if ($imgcount) { //hvis billeder overhovedet
                       $imagesarr = preg_split("/<img/", $itemimage);   //splitting images
                       $imageandtxtarr = preg_split("/>/", $imagesarr[1]);   //splitting image and text
                       $singleimage = '<span class="mo_img" title="'.htmlentities(trim($imageandtxtarr[1])).'">'.'<img'.$imageandtxtarr[0].'></span>'; //reinforce imgtag
                       $singletxt = '<span class="mo_imgtxt">'.htmlentities(trim($imageandtxtarr[1])).'</span>';  //text in span
                       $itemimage = $singleimage.$singletxt;
                    }
                    /* debug  
                    $content .= '<div class="twrss_bodytext motwrss_item_content">'.
                    'Images found: '.$imgcount.'<br />'.                    
                    ' img: '.$imageandtxtarr[0].                    
                    ' txt: '.$imageandtxtarr[1].                    
                    '</div>';*/
                    
                    $morepicsonbloglink = trim($this->conf['morePicsOnBlog']); //get value from ts setup
                    
					if ($get_html_entities !== "true") {
						//$content .= '<div class="twrss_bodytext twrss_item_content motwrss_item_content mofalse">'.$itemdescrall.'</div>'; //do not show it all
                        $content .= '<div class="twrss_bodytext motwrss_item_textholder">';  //showing the textholder begin
                        $content .= '<div class="motwrss_item_text_black">';  //showing the text black bar begin
                        $content .= '<span class="motwrss_item_date">'.$itemdateadj.'</span>';
                        $content .= ' - fra bloggen ';
                        $content .= $channellinkatag;
                        $content .= '</div>';  //showing the text black bar end                                                                       
                        $content .= '<div class="twrss_bodytext motwrss_item_text motwrss_item_text_white">'.$itemtext.'</div>';    //item text
                        $content .= '</div>';  //showing the text end
                        $content .= '<div class="twrss_bodytext motwrss_item_image_header">'.$moitemlinkbegin.$morepicsonbloglink.$moitemlinkend.'</div>';  //showing the image header
                        $content .= '<div class="twrss_bodytext motwrss_item_image">'.$moitemlinkbegin.$itemimage.$moitemlinkend.'</div>';  //showing the image
                        /*
                        $content .= '<div class="twrss_bodytext motwrss_item_footer">';  //showing the footer begin
                        $content .= '<div class="motwrss_item_footer_black">';  //showing the footer black bar begin
                        $content .= '<span class="motwrss_item_date">'.$itemdateadj.'</span>';
                        $content .= ' - fra bloggen ';
                        $content .= $channellinkatag;
                        $content .= '</div>';  //showing the footer black bar end                                                                       
                        $content .= '<div class="twrss_bodytext motwrss_item_text motwrss_item_footer_white">'.$itemtext.'</div>';    //item text
                        $content .= '</div>';  //showing the footer end
                        */
                                                                         
   					} else {
						$content .= '<div class="twrss_bodytext twrss_item_content motwrss_item_content motrue">'.htmlentities($this->data['ITEM'][$i]['DESCRIPTION']).'</div>';
					}

					$content .= $get_thedescsep;
				}

				$content .= $get_theItemSeparator;
			}


			/* if ($get_theTextinput == "true" && isset($this->data['TEXTINPUT'])) {
				$content .= '<div class="twrss_bodytext twrss_input_title">'.$this->data['TEXTINPUT']['TITLE'].'</div>';
				$content .= $get_thedescsep;
				$content .= '<div class="twrss_bodytext twrss_input_description">'.$this->data['TEXTINPUT']['DESCRIPTION'].'</div>';
				$content .= $get_thedescsep;
				$content .= '<form action="'.$this->data['TEXTINPUT']['LINK'].'" method="post" target="'.$get_theLinkTarget.'">';
				$content .= '<input type="text" class="twrss_bodytext twrss_input_name" name="'.$this->data['TEXTINPUT']['NAME'].'" />';
				$content .= '<input type="submit" class="twrss_bodytext twrss_input_submit" value="'.$get_theSubmitValue.'" />';
				$content .= '</form>';
				$content .= $get_theItemSeparator;
			} */
            
			$content .= '</div>';
			return $content;
		}

	/**
	 * [Take $file and send it through the parser to get an array data[]]
	 *
	 * @param	[type]		$file: url with the xml file
	 * @return	[type]		$this->data: get the array from xml file
	 */
		function getRSSData($file) {
			$this->set_parser();
			$this->xml_file($file);
			return $this->data;
		}

	/**
	 * [Get the start element for the parser.]
	 *
	 * @param	[type]		$parser: parser, is a reference to the XML parser calling the handler
	 * @param	[type]		$tag: name, contains the name of the element for which this handler is called
	 * @param	[type]		$attribute: attribs, contains an associative array with the element's attributes 
	 * @return	[type]		...
	 */
		function startElement($parser, $tag, $attribute) {

			if (strtoupper(substr($tag, 0, 3)) == 'RDF') {
				$tag = substr($tag, 4, strlen($tag));
			}

			$this->current_tag = $this->StringToUpper($tag);

			if ($this->StringToUpper($tag) == 'CHANNEL') {
				$this->channel_flag = true;
			}

			if ($this->StringToUpper($tag) == 'IMAGE') {
				$this->image_flag = true;
			}

			if ($this->StringToUpper($tag) == 'TEXTINPUT') {
				$this->input_flag = true;
			}

			if ($this->StringToUpper($tag) == 'ITEM') {
				$this->item_flag = true;
				$this->item_count++;
			}
		}

	/**
	 * [Get the end elment for parsing]
	 *
	 * @param	[type]		$parser: parser, is a reference to the XML parser calling the handler
	 * @param	[type]		$tag: name, contains the name of the element for which this handler is called 
	 * @return	[type]		...
	 */
		function endElement($parser, $tag) {

			if ($this->StringToUpper($tag) == 'CHANNEL') {
				$this->channel_flag = false;
			}

			if ($this->StringToUpper($tag) == 'IMAGE') {
				$this->image_flag = false;
			}

			if ($this->StringToUpper($tag) == 'TEXTINPUT') {
				$this->input_flag = false;
			}

			if ($this->StringToUpper($tag) == 'ITEM') {
				$this->item_flag = false;
			}
		}

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$parser: parser, is a reference to the XML parser calling the handler
	 * @param	[type]		$cdata: Character datas from xml_set_character_data_handler
	 * @return	[type]		...
	 */
		function getCharacterData($parser, $cdata) {

			if ($this->channel_flag == true && $this->item_flag == false && $this->image_flag == false && $this->input_flag == false) {
				if ($this->current_tag != 'CHANNEL') {
					if (!isset($this->data['CHANNEL'][$this->current_tag])) {
						$this->data['CHANNEL'][$this->current_tag] = '';
					}
					$this->data['CHANNEL'][$this->current_tag] .= $cdata;
				}
			}

			if ($this->image_flag == true) {
				if ($this->current_tag != 'IMAGE') {
					if (!isset($this->data['IMAGE'][$this->current_tag])) {
						$this->data['IMAGE'][$this->current_tag] = '';
					}
					$this->data['IMAGE'][$this->current_tag] .= $cdata;
				}
			}

			if ($this->input_flag == true) {
				if ($this->current_tag != 'TEXTINPUT') {
					if (!isset($this->data['TEXTINPUT'][$this->current_tag])) {
						$this->data['TEXTINPUT'][$this->current_tag] = '';
					}
					$this->data['TEXTINPUT'][$this->current_tag] .= $cdata;
				}
			}

			if ($this->item_flag == true) {
				if ($this->current_tag != 'ITEM') {
					if (!isset($this->data['ITEM'][$this->item_count][$this->current_tag])) {
						$this->data['ITEM'][$this->item_count][$this->current_tag] = '';
					}
					$this->data['ITEM'][$this->item_count][$this->current_tag] .= $cdata;
				}
			}
		}

	/**
	 * [Open xml File and read]
	 *
	 * @param	string		$file: the xml file
	 * @return	[type]		...
	 */
		function xml_file($file) {
			if (!($fp = @fopen($file, "r")))
				$this->error("Kann XML-Datei <b>".$file."</b> nicht �ffnen");

			while ($data = fread($fp, 4096)) {
				if (!(xml_parse($this->parser, $data)))
					$this->error("XML-Output: ".xml_error_string(xml_get_error_code($this->parser)));
			}

			xml_parser_free($this->parser);
		}

	/**
	 * [Set php parser to parse the xml file!]
	 *
	 * @return	[type]		...
	 */
		function set_parser() {
			$this->parser = xml_parser_create();
			xml_set_object($this->parser, $this);
			xml_parser_set_option($this->parser, XML_OPTION_CASE_FOLDING, $this->case_folding);
			xml_set_element_handler($this->parser, "startElement", "endElement");
			xml_set_character_data_handler($this->parser, "getCharacterData");
		}

	/**
	 * [Change $tagname from lower to upper cabs]
	 *
	 * @param	string		$tagname: Tagname from the xml file
	 * @return	string		$tagname: Changed tagname
	 */
		function StringToUpper($tagname) {
			if ($this->case_folding)
				return strtoupper($tagname);
			else
				return $tagname;
		}

	/**
	 * [Error function if no xml file is given]
	 *
	 * @param	[type]		$msg: Error message given by function error
	 * @return	[type]		...
	 */
		function error($msg) {
			#die(printf("Fehler: %s", $msg));
			$content .= '<div id="twrss_table">';
			$content .= '<div class="twrss_bodytext twrss_item_content">Fehler: '.$msg.'</div>';
			$content .= '</div>';
			
			return $content;
		}


	}



	if (defined("TYPO3_MODE") && $TYPO3_CONF_VARS[TYPO3_MODE]["XCLASS"]["ext/tw_rssfeeds/pi1/class.tx_twrssfeeds_pi1.php"]) {
		include_once($TYPO3_CONF_VARS[TYPO3_MODE]["XCLASS"]["ext/tw_rssfeeds/pi1/class.tx_twrssfeeds_pi1.php"]);
	}

?>
