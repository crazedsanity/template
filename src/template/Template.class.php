<?php

namespace crazedsanity\template;

use crazedsanity\template\iTemplate;
use crazedsanity\core\ToolBox;

/**
 * Description of template
 *
 * @author danf
 */
class Template implements iTemplate {
	private $_contents;
	private $_name;
	private $_templates = array();
	private $_blockRows = array();
	private $_origin;
	private $_dir;
	private $recursionDepth=10;
	
	const VARIABLE_REGEX = '([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)';


	//-------------------------------------------------------------------------
	/**
	 * @param $file         Template file to use for contents (can be null)
	 * @param null $name    Name to use for this template
	 */
	public function __construct($file, $name=null) {
		if(!is_null($name)) {
			$this->_name = $name;
		}
		$this->load($file);
	}
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function load($file) {
		$this->_origin = $file;
		if(!is_null($file)) {
			if(file_exists($file)) {
				if (is_null($this->_name)) {
					$bits = explode('/', $file);
					$this->_name = preg_replace('~\.tmpl~', '', array_pop($bits));
				}
				$this->_contents = file_get_contents($file);
				$this->_dir = dirname($file);
			}
			else {
				throw new \InvalidArgumentException("file does not exist (". $file .")");
			}
		}
		return $this;
	}
	//-------------------------------------------------------------------------



	//-------------------------------------------------------------------------
	/**
	 * @param $newValue     How many times to recurse (default=10)
	 */
	public function set_recursionDepth($newValue) {
		if(is_numeric($newValue) && $newValue > 0) {
			$this->recursionDepth = $newValue;
		}
		else {
			throw new \InvalidArgumentException();
		}
		return $this;
	}
	//-------------------------------------------------------------------------



	//-------------------------------------------------------------------------
	/**
	 * @param $name                 Internal var to retrieve
	 * @return array|mixed|string   Value of internal var
	 */
	public function __get($name) {
		switch($name) {
			case 'name':
				return $this->_name;
		
			case 'templates':
				return $this->_templates;

			case 'blockRows':
				return $this->_blockRows;

			case 'contents':
				return $this->_contents;

			case 'dir':
				return $this->_dir;

			case 'origin':
				return $this->_origin;
			
			default:
				throw new \InvalidArgumentException;
		}
	}
	//-------------------------------------------------------------------------



	//-------------------------------------------------------------------------
	/**
	 * @param $value        Set internal contents to this value.
	 */
	public function setContents($value) {
		$this->_contents = $value;
		return $this;
	}
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function setName($value) {
		$this->_name = $value;
		return $this;
	}
	//-------------------------------------------------------------------------



	//-------------------------------------------------------------------------
	/**
	 * @param Template $template    Template object to add
	 * @param bool $render          If the template should be rendered (default=true)
	 * @throws \Exception           Problems with nesting of block rows
	 */
	public function add(Template $template, $render=true) {
		if(strlen($template->name)) {
			foreach($template->templates as $name=>$content) {
				$this->_templates[$name] = $content;
			}

			if($render === true) {
				$this->_templates[$template->name] = $template->render();
			}
			else {
				$this->_templates[$template->name] = $template->contents;
			}
		}
		else{
			throw new \InvalidArgumentException("template is missing a name");
		}
		return $this;
	}
	//-------------------------------------------------------------------------



	//-------------------------------------------------------------------------
	/**
	 * @param $name             Name of template var
	 * @param null $value       Value (contents) of template
	 */
	public function addVar($name, $value=null, $render=true) {
		$x = new Template(null, $name);
		if(is_null($value)) {
			$value = "";
		}
		elseif(is_bool($value)) {
			if($value === true) {
				$value = 1;
			}
			else {
				$value = 0;
			}
		}
		if(is_string($value) || is_numeric($value)) {
			$x->setContents($value);
		}
		else {
			throw new \InvalidArgumentException("value was not appropriate: ". var_export($value, true));
		}
		$this->add($x, $render);
		return $this;
	}
	//-------------------------------------------------------------------------



	//-------------------------------------------------------------------------
	/**
	 * Add an array of variables, or Template objects (or a mixture)
	 * 
	 * @param array $vars
	 * @param type $render
	 */
	public function addVarList(array $vars=null, $render=true) {
		if(is_array($vars)) {
			foreach($vars as $k=>$v) {
				if(is_object($v) && get_class($v) == get_class($this)) {
					$this->add($v, $render);
				}
				elseif(is_array($v)) {
					$this->addVarList($v, $render);
				}
				else {
					$this->addVar($k, $v);
				}
			}
		}
		return $this;
	}
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function addVarListWithPrefix(array $vars, $prefix, $render=true) {
		$newVars = array();
		foreach($vars as $k=>$v) {
			$newVars[$prefix . $k] = $v;
		}
		
		$this->addVarList($newVars);
		return $this;
	}
	//-------------------------------------------------------------------------



	//-------------------------------------------------------------------------
	/**
	 * @param bool $stripUndefinedVars      Removes undefined template vars
	 * @return mixed|string                 Rendered template
	 */
	public function render($stripUndefinedVars=false) {
		$numLoops = 0;
		if(is_string($this->_contents) || is_numeric($this->_contents)) {
			$out = $this->_contents;
		}
		else {
			$out = "";
		}

		$rendered = array();
		foreach($this->_templates as $name=>$contents) {
			$rendered[$name] = $contents;
		}

		$tags = array();
		while (preg_match_all('~\{'. self::VARIABLE_REGEX .'\}~U', $out, $tags) && $numLoops < $this->recursionDepth) {
			$out = ToolBox::mini_parser($out, $rendered, '{', '}');
			$numLoops++;
		}

		if($stripUndefinedVars === true) {
			$out = preg_replace('/\{'. self::VARIABLE_REGEX .'\}/', '', $out);
		}

		return $out;
	}
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function renderRows(array $recordSet, $stripUndefinedVars=false) {
		$renderedRows = "";
		
		if(is_array($recordSet) && count($recordSet)) {
			foreach($recordSet as $record) {
				$this->addVarList($record);
				$renderedRows .= $this->render($stripUndefinedVars);
				$this->reset();
			}
		}
		return $renderedRows;
	}
	//-------------------------------------------------------------------------



	//-------------------------------------------------------------------------
	/**
	 * @param $this->_contents
	 * @return array
	 * @throws \Exception
	 */
	public function get_block_row_defs() {
		$xBeginArr = array();
		$xEndArr = array();
		
		$flags = PREG_PATTERN_ORDER;
		preg_match_all("/<!-- BEGIN (\S{1,}) -->/", $this->_contents, $xBeginArr, $flags);
		$beginArr = $xBeginArr[1];

		preg_match_all("/<!-- END (\S{1,}) -->/", $this->_contents, $xEndArr, $flags);
		$endArr = $xEndArr[1];

		$numIncomplete = 0;
		$nesting = "";
		
		$numIncomplete += count(array_diff($beginArr, $endArr));
		$numIncomplete += count(array_diff($endArr, $beginArr));

		if($numIncomplete > 0) {
			throw new \Exception("invalidly nested block rows: ". $nesting);
		}
		
		//Got valid data, return the list.
		return array_reverse($beginArr);
	}
	//---------------------------------------------------------------------------------------------



	//---------------------------------------------------------------------------------------------
	public function setBlockRow($handle, $removeDefs=true, $addPlaceholder=true) {
		$name = $handle;
		$rowPlaceholder = $name;

		$reg = "/<!-- BEGIN $handle -->(.+){0,}<!-- END $handle -->/sU";
		$m = array();
		preg_match_all($reg, $this->_contents, $m);
		if(!is_array($m) || !isset($m[0][0]) ||  !is_string($m[0][0])) {
			throw new \Exception("could not find block row '". $handle ."' in template '". $this->name .", filename=(". $this->_origin .")");
		} else {

			if($removeDefs === true) {
				$openHandle = "<!-- BEGIN $handle -->";
				$endHandle  = "<!-- END $handle -->";
				$m[0][0] = str_replace($openHandle, "", $m[0][0]);
				$m[0][0] = str_replace($endHandle, "", $m[0][0]);
			}
			if($addPlaceholder === true) {
				$this->_contents = preg_replace($reg, "{". $rowPlaceholder ."}", $this->_contents);
			}
		}
		
		$blockRow = new Template(null, $rowPlaceholder);
		$blockRow->setContents($m[0][0]);
		
		$this->_blockRows[$handle] = $blockRow;
		
		return $blockRow;
	}
	//---------------------------------------------------------------------------------------------
	
	
	
	//---------------------------------------------------------------------------------------------
	public function setAllBlockRows($removeDefs=true) {
		$defs = $this->get_block_row_defs($removeDefs);
		
		foreach($defs as $rowName) {
			$this->setBlockRow($rowName);
		}

		return($this->_blockRows);
	}
	//---------------------------------------------------------------------------------------------



	//---------------------------------------------------------------------------------------------
	/**
	 * @param string $name				Name of the existing block row to parse
	 * @param array  $recordSet			Data to iterate through to create parsed rows.
	 * @param string $varName (null)	Parse into the given name instead of the default (__BLOCKROW__$name)
	 */
	public function parseBlockRow($name, array $recordSet, $varName=null) {
		if(isset($this->_blockRows[$name])) {
			
			$myBlockRow = $this->_blockRows[$name];
			if(!is_null($varName)) {
				$myBlockRow->setName($varName);
			}
			
			$rendered = $myBlockRow->renderRows($recordSet, true);
			$this->addVar($myBlockRow->name, $rendered);
		}
		else {
			throw new \InvalidArgumentException("block row '". $name ."' does not exist... ");
		}
		
		return $rendered;
	}
	//---------------------------------------------------------------------------------------------



	//---------------------------------------------------------------------------------------------
	public function __toString() {
		return $this->render();
	}
	//---------------------------------------------------------------------------------------------



	//---------------------------------------------------------------------------------------------
	/**
	 * Search string for template var definitions like "{var}"
	 * 
	 * @param type $fromContents
	 * @return array				Array of definitions: index is the name, value is the number of
	 *									times that variable is defined.
	 */
	public static function getTemplateVarDefinitions($fromContents) {
		$matches = array();
		preg_match_all('~\{'. self::VARIABLE_REGEX .'\}~U', $fromContents, $matches);

		$retval = array();
		
		foreach($matches[1] as $name) {
			if(!isset($retval[$name])) {
				$retval[$name] = 1;
			}
			else {
				$retval[$name]++;
			}
		}

		return $retval;
	}
	//---------------------------------------------------------------------------------------------



	//---------------------------------------------------------------------------------------------
	/**
	 * Searches $this->_contents for template vars.
	 * 
	 * @param type $fromContents	Content to search; uses internal content if none provided.
	 * @return array				see getTemplateVarDefinitions()
	 */
	public function getVarDefinitions($fromContents = null) {
		if(is_null($fromContents)) {
			$fromContents = $this->_contents;
		}

		return self::getTemplateVarDefinitions($fromContents);
	}
	//---------------------------------------------------------------------------------------------
	
	
	
	//---------------------------------------------------------------------------------------------
	public function reset() {
		$this->_blockRows = array();
		$this->_templates = array();
		return $this;
	}
	//---------------------------------------------------------------------------------------------
}
