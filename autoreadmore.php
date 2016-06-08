<?php
/**
 * AutoReadMore plugin
 *
 * @package		AutoReadMore
 * @author www.toao.net
 * @author Gruz <arygroup@gmail.com>
 * @copyright	Copyleft - All rights reversed
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

// No direct access
defined('_JEXEC') or die('Restricted access');
jimport( 'joomla.plugin.plugin' );
jimport( 'gjfields.gjfields' );
jimport( 'gjfields.helper.plugin' );

$latest_gjfields_needed_version = '1.0.43';
$error_msg = 'Install the latest GJFields plugin version <span style="color:black;">'.__FILE__.'</span>: <a href="http://www.gruz.org.ua/en/extensions/gjfields-sefl-reproducing-joomla-jform-fields.html">GJFields</a>';

$isOk = true;
while (true) {
	$isOk = false;
	if (!class_exists('JPluginGJFields')) {
		$error_msg = 'Strange, but missing GJFields library for <span style="color:black;">'.__FILE__.'</span><br> The library should be installed together with the extension... Anyway, reinstall it: <a href="http://www.gruz.org.ua/en/extensions/gjfields-sefl-reproducing-joomla-jform-fields.html">GJFields</a>';
		break;
	}
	$gjfields_version = file_get_contents(JPATH_ROOT.'/libraries/gjfields/gjfields.xml');
	preg_match('~<version>(.*)</version>~Ui',$gjfields_version,$gjfields_version);
	$gjfields_version = $gjfields_version[1];
	if (version_compare($gjfields_version,$latest_gjfields_needed_version,'<')) {
		break;
	}
	$isOk = true;
	break;
}
if (!$isOk) {
	JFactory::getApplication()->enqueueMessage($error_msg, 'error');
	if (JFactory::getApplication()->isAdmin()) {
	}
}
else {
$com_path = JPATH_SITE.'/components/com_content/';
if (!class_exists ('ContentRouter') ) { require_once $com_path.'router.php'; } // require_once $com_path.'router.php';
if (!class_exists ('ContentHelperRoute') ) { require_once $com_path.'helpers/route.php'; } // require_once $com_path.'helpers/route.php';

class plgContentAutoReadMoreCore extends JPluginGJFields {


	/**
	 * Defines some some variables and loads languages.
	 *
	 * It's the common function I use in many plugins
	 *
	 * @author Gruz <arygroup@gmail.com>
	 */
	public function __construct(& $subject, $config) {
		parent::__construct($subject, $config);
		//~ $this->plg_name = $config['name'];
		//~ $this->plg_type = $config['type'];
		//~ $this->_loadLanguage();
	}


	/**
	 * Loads English and the national to prevent untranslated constants
	 *
	 * It's the common function I use in many plugins
	 *
	 * @author Gruz <arygroup@gmail.com>
	 * @return	void
	 */
	private function _loadLanguage() {
		$path = dirname(__FILE__);
		$ext_name = 'plg_'.$this->plg_type.'_'.$this->plg_name;
		$jlang = JFactory::getLanguage();
		$jlang->load($ext_name, $path, 'en-GB', true);
		$jlang->load($ext_name, $path, $jlang->getDefault(), true);
		$jlang->load($ext_name, $path, null, true);
	}


	/**
	 * Truncates the article text
	 *
	 * @param	string	$context 	The context of the content being passed to the plugin.
	 * @param	object	$article	The article object.
	 * @param	object	$params	The article params
	 * @param	int	$page	Is int 0 when is called not form an article, and empty when called from an article
	 *
	 */
	public function onContentPrepare($context, &$article, &$params, $page=null){
		if (!JFactory::getApplication()->isSite()) {return false;}
		$jinput = JFactory::getApplication()->input;
		if ($jinput->get('option',null,'CMD') == 'com_dump') {return;}
		// SOME SPECIAL RETURNS }
		$debug = $this->paramGet('debug') ;
		if ($debug) {
			if (function_exists('dump') && false) {
				dump ($article,'context = '. $context);
			}
			else {
				if ($debug == 1) {
					JFactory::getApplication()->enqueueMessage(
					'Context : '.$context . '<br />'.
					'Title : '.@$article->title . '<br />'.
					'Id : '.@$article->id . '<br />'.
					'CatId : '.@$article->catid . '<br />'
					, 'warning');
				}
				elseif($debug == 2) {
					echo '<pre style="height:180px;overflow:auto;">';
					echo '<b>Context : '.$context . '</b><br />';
					echo '<b>Content Item object : </b><br />';
					print_r($article);
					if (!empty($params)) {
						echo '<b>Params:</b><br />';
						print_r ($params);
					} else {
						echo '<b>Params NOT passed</b><br />';
					}
					echo '</pre>'.PHP_EOL;
				}
			}
		}
		if ($context == 'com_tags.tag') {
			//$context = $article->type_alias;
			$article->catid = $article->core_catid;
			$article->id = $article->content_item_id;
			$article->slug = $article->id.':'.$article->core_alias;
		}

		if (!$this->_checkIfAllowedContext($context,$article)) { return; }
		if (!$this->_checkIfAllowedCategoryAndItem($context,$article)) { return; }

		if (is_object($params)) {
			$this->params_content = $params;
		} else {
			$this->params_content = new JRegistry;
			$this->params_content->loadString($params, 'JSON'); // Load my plugin params.
		}


		// Add css code
		if (!isset($GLOBALS['+xji*;!1'])) {
			$doc = JFactory::getDocument();
			$csscode = $this->paramGet('csscode');
			$doc->addStyleDeclaration( $csscode);
			$GLOBALS['+xji*;!1'] = true;
		}

		if (isset($article->introtext)) {
			$text = $article->introtext; // For core article
		}
		else {
			$text = $article->text; // In most non core content items and modules
		}

		$this->fulltext_loaded = false;//fulltext is not loaded, we must load it manually if needed
		if ($this->paramGet('Ignore_Existing_Read_More') && isset($article->introtext) && isset($article->fulltext)) {
			$text = $article->introtext.PHP_EOL.$article->fulltext;
			$this->fulltext_loaded = true;
		}
		else if ($this->paramGet('Ignore_Existing_Read_More') && isset($article->readmore) && $article->readmore>0 ) {
			//if we ignore manual readmore and we know it's present, then we must load the full text
			$text .= $this->loadFullText ($article->id);
			$this->fulltext_loaded = true;
		}
		$thumbnails = $this->getThumbNails($text,$article,$context);





		// If we must use an existing readmore, but also have to hande image
		//~ if (!$this->paramGet('Ignore_Existing_Read_More') && !empty($article->readmore) && $article->readmore>0 ) {
			//~ if ($this->paramGet('Force_Image_Handle')) {
				//~ $text = $thumbnails.$text;
				//~ $debug_addon = '';
				//~ if ($debug) {
					//~ $debug_addon = '<code>[DEBUG: AutoReadMore fired here]</code>';
				//~ }
				//~ $article->introtext = $text.$debug_addon;
				//~ $article->text = $text.$debug_addon;
			//~ }
			//~ return;
		//~ }
//~

		// How many characters are we allowed?
		$app = JFactory::getApplication();

		// get current menu item number of leading articles
		//it's strange, but I couldn't call the variable as $params - it removes the header line in that case. I can't believe, but true.
		// So don't use $params = $app->getParams(); Either use another var name like $gparams = $app->getParams(); or the direct call
		// as shown below: $app->getParams()->def('num_leading_articles', 0);
		$num_leading_articles = $app->getParams()->def('num_leading_articles', 0);

		// count how many times the plugin is called to know if the current article is a leading one
		$GLOBALS['plg_content_AutoReadMore_Count'] = (isset($GLOBALS['plg_content_AutoReadMore_Count'])) ? $GLOBALS['plg_content_AutoReadMore_Count']+1 : 1;

		if ($GLOBALS['plg_content_AutoReadMore_Count'] <= $num_leading_articles) {
			// This is a leading (full-width) article.
			$maxLimit = $this->paramGet('leadingMax');
		} else {
			// This is not a leading article.
			$maxLimit = $this->paramGet('introMax');
		}

		if (!is_numeric($maxLimit)) $maxLimit = 500;


		$this->trimming_dots = '';
		if ($this->paramGet ('add_trimming_dots') != 0) {
			$this->trimming_dots = $this->paramGet ('trimming_dots');
		}

		$limittype = $this->paramGet('limittype');
		if ($limittype == 0) {// Limit by chars
			if (JString::strlen(strip_tags($text)) > $maxLimit) {
				if ($this->paramGet('Strip_Formatting') == 1) {
					// First, remove all new lines
					$text = preg_replace("/\r\n|\r|\n/", "", $text);
					// Next, replace <br /> tags with \n
					$text = preg_replace("/<BR[^>]*>/i", "\n", $text);
					// Replace <p> tags with \n\n
					$text = preg_replace("/<P[^>]*>/i", "\n\n", $text);
					// Strip all tags
					$text = strip_tags($text);
					// Truncate
					$text = JString::substr($text, 0, $maxLimit);
					//$text = String::truncate($text, $maxLimit, '...', true);
					// Pop off the last word in case it got cut in the middle
					$text = preg_replace("/[.,!?:;]? [^ ]*$/", "", $text);
					// Add ... to the end of the article.
					$text = trim($text) . $this->trimming_dots ;
					// Replace \n with <br />
					$text = str_replace("\n", "<br />", $text);
				} else {
					// Truncate
					//$text = JString::substr($text, 0, $maxLimit);
					if (!class_exists('AutoReadMoreString')) { require_once (dirname(__FILE__).'/helpers/AutoReadMoreString.php'); }

					$text = AutoReadMoreString::truncate($text, $maxLimit, '&hellip;', true);

					// Pop off the last word in case it got cut in the middle
					$text = preg_replace("/[.,!?:;]? [^ ]*$/", "", $text);
					// Pop off the last tag, if it got cut in the middle.
					$text = preg_replace('/<[^>]*$/', '', $text);

					$text = $this->addTrimmingDots($text);
					// Use Tidy to repair any bad XHTML (unclosed tags etc)
					$text = AutoReadMoreString::cleanUpHTML($text);

				}
				// Add a "read more" link, makes sense only for com_content
				$article->readmore = true;
			}
		}
		else if ($limittype == 1) {//Limit by words

			if (!class_exists('AutoReadMoreString')) { require_once (dirname(__FILE__).'/helpers/AutoReadMoreString.php'); }
			$text = AutoReadMoreString::truncateByWords($text,$maxLimit,$article->readmore);

			$text = $this->addTrimmingDots($text);
			$text = AutoReadMoreString::cleanUpHTML($text);

		}
		else if ($limittype == 2) {// Limit by paragraphs
			$paragraphs = explode ('</p>',$text);
			if(count($paragraphs)<=$maxLimit+1) {
				// do nothing, as we have $maxLimit paragraphs
			}
			else {
				$text = array();
				for ($i = 0; $i <$maxLimit ; $i++) {
					$text[] = $paragraphs[$i];
				}
				unset ($paragraphs);
				$text = implode('</p>',$text);
				$article->readmore = true;
			}
		}
		if ($this->paramGet('Strip_Formatting') == 1) {
			$text = strip_tags($text);
		}

		// If we have thumbnails, add it to $text.
		$text = $thumbnails . $text;

		if ($this->paramGet('wrap_output') == 1) {
			$template = $this->paramGet('wrap_output_template');
			$text = str_replace('%OUTPUT%',$text,$template);
		}
		if ($this->paramGet('readmore_text') && empty($this->alternative_readmore)) {
			$article->alternative_readmore = JText::_($this->paramGet('readmore_text'));
		}
		$debug_addon = '';
		if ($debug) {
			$debug_addon = '<code>[DEBUG: AutoReadMore fired here]</code>';
		}
		$article->introtext = $text.$debug_addon;
		$article->text = $text.$debug_addon;
	}


	function _checkIfAllowedCategoryAndItem ($context,$article) {
		if (!isset($article->catid) && !isset($article->id) ) {
			return true;
		}

		$data = array();

		if ( // Prepare data from joomla core articles or frontpage
			($this->paramGet('joomla_articles')		&& 	$context == 'com_content.category')	 ||
			($this->paramGet('Enabled_Front_Page')	&& 	$context == 'com_content.featured')
		) {
			$prefix = '';
			if ($context == 'com_content.featured') {
				$prefix = 'fp_';
			}
			$row = array(
				'category_switch' => $this->paramGet($prefix.'categories_switch'),
				'category_ids' => $this->paramGet($prefix.'categories'),
				'item_switch' => $this->paramGet($prefix.'articles_switch'),
				'item_ids' => $this->paramGet($prefix.'id'),
			);
			$data[$context] = $row;
		}

		$context_switch = $this->paramGet('context_switch') ;
		if ($context_switch == 'include') {
			$paramsContexts = $this->paramGet('contextsToInclude');
			$contextsToInclude = json_decode($paramsContexts);
			if (!empty($paramsContexts) && $contextsToInclude === NULL) { // The default joomla installation procedure doesn't store defaut params into the DB in the correct way
				$paramsContexts = str_replace("'",'"',$paramsContexts);
				$contextsToInclude = json_decode($paramsContexts);
			}
			if (!empty($contextsToInclude) && !empty($contextsToInclude->context)) {
				foreach ($contextsToInclude->context as $k=>$v) {
					if ($v != $context) { continue; }
					$row = array(
						'category_switch' => $contextsToInclude->context_categories_switch[$k],
						'category_ids' => $contextsToInclude->categories_ids[$k],
						'item_switch' => $contextsToInclude->context_content_items_switch[$k],
						'item_ids' => $contextsToInclude->context_content_item_ids[$k],
					);
					$data[$context] = $row;
				}
			}
		}
		if (empty($data[$context])) { return true; }

		$item_switch = $data[$context]['item_switch'];
		$item_ids = $data[$context]['item_ids'];
		if (!is_array($item_ids)) {
			$item_ids = array_map('trim',explode (',',$item_ids));
		}
		$category_switch = $data[$context]['item_switch'];
		$category_ids = $data[$context]['category_ids'];
		if (!is_array($category_ids)) {
			$category_ids = array_map('trim',explode (',',$category_ids));
		}
		switch ($item_switch) {
			case '1'://some articles are selected
				if (in_array($article->id,$item_ids)) {
					return true;
				}
				break;
			case '2'://some articles are excluded
				//if the article is among the excluded ones - return false
				if (in_array($article->id,$item_ids)) {
					return false;
				}
				break;
			case '0'://no specific articles set
			default :
				break;
		}

		$in_array = in_array ($article->catid,$category_ids);
		switch ($category_switch) {
			case '0'://ALL CATS
				return true;
				break;
			case '1'://selected cats
				if ($in_array) { return true; }
				else { return false; }
				break;
			case '2'://excludes cats
				if ($in_array) { return false; }
				else { return true; }
				break;
			default :
				break;
		}
		return true;
	}
	/**
	 * Check if current context is allowed either by settings or by some hardcoded rules
	 *
	 * @author Gruz <arygroup@gmail.com>
	 * @param	string	$context	Context passed to the onContentPrepare method
	 * @param	object	$article	Content item object
	 * @return	boold			true if allowed, false otherwise
	 */
	function _checkIfAllowedContext ($context,$article) {
		$jinput = JFactory::getApplication()->input;
		$context_global = explode ('.',$context);
		$context_global = $context_global [0];


		// Some hard-coded contexts to exclude
		$hard_coded_exclude_global_contexts = array(
			'com_virtuemart', // never fire for VirtueMart
		);
		$contextsToExclude = array(
			'com_tz_portfolio.p_article', // never run for full article
			'com_content.article', // never run for full article
			//~ 'mod_custom.content', // never run at a custom HTML module - DISABLED here, because the user must be allowed to choose this. At some circumstances joomla HTML modules may be needed to cut
		) ;
		if (in_array($context_global,$hard_coded_exclude_global_contexts) || in_array($context,$contextsToExclude)  ) { return false; }
		// SOME SPECIAL RETURNS {
		if($context == 'easyblog.blog' && $jinput->get('view',null,'CMD')  == 'entry' ){  // fix easyblog
			return false;
		}

		$view = $jinput->get('view',null,'CMD');
		$article_id = $jinput->get('id',null,'INT');
		if (
			($view == "article" && $article->id == $article_id) ||
			($context == 'com_k2.item' && $article->id == $article_id)) {//it it's already a full article - go away'
			if (!isset($GLOBALS['joomlaAutoReadMorePluginArticleShown']) ) { // But leave a flag not to go aways in a module
				$GLOBALS['joomlaAutoReadMorePluginArticleShown'] = $article_id;
				return false;
			}
		}


		if ($this->paramGet('Enabled_Front_Page') == 0 and $context == 'com_content.featured') { return false;}
		else if ($this->paramGet('Enabled_Front_Page') == 1 and $context == 'com_content.featured') { return true;}
		if ($this->paramGet('joomla_articles') == 0 and $context == 'com_content.category') { return false;}
		else if ($this->paramGet('joomla_articles') == 1 and $context == 'com_content.category') {	return true;	}

		$context_switch = $this->paramGet('context_switch') ;
		switch ($context_switch) {
			case 'include':
				$paramsContexts = $this->paramGet('contextsToInclude');
				$contextsToInclude = json_decode($paramsContexts);
				if (!empty($paramsContexts) && $contextsToInclude === NULL) { // The default joomla installation procedure doesn't store defaut params into the DB in the correct way
					$paramsContexts = str_replace("'",'"',$paramsContexts);
					$contextsToInclude = json_decode($paramsContexts);
				}
				if (!empty($contextsToInclude) && !empty($contextsToInclude->context)) {
					foreach($contextsToInclude->context as $k=>$v) {
						if ($context == $v) {

							return true;
						}
					}
				}
				return false;
				break;
			case 'exclude':
				if ($this->paramGet('exclude_mod_contexts') && strpos($context,'mod_') === 0) { // Not to work on modules, like mod_roksprocket.article
					return false;
				}
				$contextsToExclude = $this->paramGet('contextsToExclude');
				$contextsToExclude = array_map('trim',explode(",",$contextsToExclude));
				if (in_array($context,$contextsToExclude)  ) { return false; }
				break;
			case 'all_enabled':
				return true;
				break;
			case 'all_disabled':
				if (!in_array($context,array('com_content.category','com_content.featured'))) { // this check just in case, should never come here in such a case
					return false;
				}
				break;
			default :
				break;
		}
		return true;

	}



	/**
	 * Add Trimming dots
	 *
	 * Full description (multiline)
	 *
	 * @author Gruz <arygroup@gmail.com>
	 * @param	string	$text
	 * @return	string
	 */
	function addTrimmingDots($text) {
		// Add ... to the end of the article if the last character is a letter or a number.
		if ($this->paramGet ('add_trimming_dots') == 2) {
			if (preg_match('/\w/ui', JString::substr($text, -1))) { $text = trim($text) . $this->trimming_dots; }
		}
		else {
			$text = trim($text) . $this->trimming_dots;
		}
		return $text;
	}



	/**
	 * Returns the full text of the article based on the article id
	 *
	 * @author Gruz <arygroup@gmail.com>
	 * @param 	integer	$id	The id of the artice to load
	 * @return 	string	The article fulltext
	 */
	function loadFullText ($id) {
		$article = JTable::getInstance("content");
		$article->load($id);
		return $article->fulltext;
	}


	/**
	 * Returns text with handled images - added classes, stripped attributes, if needed
	 *
	 * @author Gruz <arygroup@gmail.com>
	 * @param	string	$text	HTML code of the article
	 * @param	object	$article	Article object for additional information like $article->id
	 * @param	text		$context	Context
	 * @return	array			Array of 2 string og HTML code
	 */
	function getThumbNails( & $text, & $article, $context) {
		// Are we working with any thumbnails?
		if ($this->paramGet('Thumbnails') < 1) {
			return;
		}
		$thumbnails = array();
		// Extract all images from the article.
		$imagesfound  = preg_match_all('/<img [^>]*>/iu', $text, $matches);
		//if we found less thumbnail then expected and the fulltext is not loaded,
		// then load fulltext and search in it also
		$matches_tmp = array ();
		if ($imagesfound < $this->paramGet('Thumbnails') && !$this->fulltext_loaded && isset($article->id)) {
			$fulltext = '';
			if (isset($article->fulltext)) {
				$fulltext = $article->fulltext;
			}

			else if(in_array($context,array('com_content.category','com_content.featured'))) {
				$this->loadFullText ($article->id);
			}
			$matches_tmp = $matches[0];
			$imagesfound  = preg_match_all('/<img [^>]*>/ui', $fulltext, $matches);
		}
		$matches = array_merge($matches_tmp,$matches[0]);

		// Loop through the thumbnails.
		for ($thumbnail = 0; $thumbnail < $this->paramGet('Thumbnails'); $thumbnail++) {
			if (!isset($matches[$thumbnail])) break;
			// Remove the image from $text
			$text = str_replace($matches[$thumbnail], '', $text);
			// See if we need to remove styling.
			if (trim($this->paramGet('Thumbnails_Class')) != '') {
				// Remove style, class, width, border, and height attributes.
				if ($this->paramGet('Strip_Image_Formatting')) {
					$matches[$thumbnail] = preg_replace('/(style|class|width|height|border) ?= ?[\'"][^\'"]*[\'"]/i', '', $matches[$thumbnail]);
					// Add CSS class name.
					$matches[$thumbnail] = preg_replace('@/?>$@', 'class="' . $this->paramGet('Thumbnails_Class') . '" />', $matches[$thumbnail]);
					// Add CSS class name.
				}
				else {
					$matches[$thumbnail] = preg_replace('@(class=["\'])@', '$1' . $this->paramGet('Thumbnails_Class').' ', $matches[$thumbnail], -1, $count);
					if ($count<1) {
						$matches[$thumbnail] = preg_replace('@/?>$@', 'class="' . $this->paramGet('Thumbnails_Class') . '" />', $matches[$thumbnail]);
					}
				}
			}

			if (trim($matches[$thumbnail]) != '') {
				$thumbnails[] = $matches[$thumbnail];
			}
		}

		if (empty($thumbnails) && trim($this->paramGet('default_image')) !='' ) {
			$thumbnails[] = '<img src="'.$this->paramGet('default_image').'">';
		}

		// Make this thumbnail a link.
		//$matches[$thumbnail] = "<a href='" . $link . "'>{$matches[$thumbnail]}</a>";
		// Add to the list of thumbnails.
		if ($this->paramGet('image_link_to_article')) {
			$jinput = JFactory::getApplication()->input;
			while (true) {
				$option = $jinput->get('option',null,'CMD');
				if (in_array($option,array ( // Do not create link for K2, VM and so on
						'com_k2',
						'com_virtuemart'
					) )) 	{
					if (!empty($article->link)) {
						$link = $article->link;
					}
					break;
				}
				if (!isset($article->catid)) { break; }
				if (!isset($article->slug)) { $article->slug = '';}
				if (isset($article->router) && isset($article->catid)) {
					$link = JRoute::_(call_user_func($article->router,$article->slug,$article->catid));
					break;
				}
				if (isset($article->link)) {
					$link = $article->link;
					$link = JRoute::_($link);
					break;
				}
				// Prepare the article link
				if ( $this->params_content->get('access-view') ) :
					$link = JRoute::_(ContentHelperRoute::getArticleRoute($article->slug, $article->catid));
				else :
					$menu = JFactory::getApplication()->getMenu();
					$active = $menu->getActive();
					if (!isset($active->id)) {
						$active = $menu->getDefault();
					}
					$itemId = $active->id;
					$link1 = JRoute::_('index.php?option=com_users&view=login&Itemid=' . $itemId);
					$returnURL = JRoute::_(ContentHelperRoute::getArticleRoute($article->slug, $article->catid));
					$link = new JURI($link1);
					$link->setVar('return', base64_encode($returnURL));
				endif;
				break;
			}
			if (isset($link)) {
				foreach ($thumbnails as $k=>$v) {
					$thumbnails[$k] = '<a href="'.$link.'">'.$v.'</a>';
				}
			}
		}

		return implode(PHP_EOL,$thumbnails);

	}


}


class plgContentAutoReadMore extends plgContentAutoReadMoreCore {
	public function __construct(& $subject, $config) {
		parent::__construct($subject, $config);
	}
}

}
