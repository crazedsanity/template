<?php

use crazedsanity\template\Template;
use crazedsanity\core\ToolBox;
use \InvalidArgumentException;
use \Exception;

class TestOfTemplate extends PHPUnit_Framework_TestCase {
	
	
	public function test_findVars() {
		// the "3rd" item won't match, because it's not a valid variable name.
		$found = Template::getTemplateVarDefinitions("{first} {_second} {3rd} {_4th} {first} {with-dashes}");
		
		$this->assertEquals(4, count($found), "expected to find 3 distinct variables, instead, found: ". ToolBox::debug_print($found,0));
		
		$this->assertTrue(isset($found['first']), "did not find the variable 'first'");
		$this->assertEquals($found['first'], 2, "only found one instance of 'first', should have found two");
		
		$this->assertTrue(isset($found['_second']), "did not find the variable '_second'");
		$this->assertEquals($found['_second'], 1, "found incorrect number of the variable '_second'");
		
		$this->assertFalse(isset($found['3rd']), "found an invalidly-named variable (first character was a number)");
		$this->assertFalse(isset($found['_4th']), "found an invalidly-named variable (underscore + number)");
		
		$this->assertTrue(isset($found['_4th']), "did not find the variable '_4th'");
		$this->assertEquals($found['_4th'], 1, "found incorrect number of the variable '_4th'");
	}
	
	public function test_noname() {
		$justFile = new Template(dirname(__FILE__) .'/files/templates/main.tmpl');
		$this->assertEquals('main', $justFile->name, "unexpected name, expected 'main', actual=(". $justFile->name .")");
	}
	
	public function test_setname() {
		$tmpl = new Template(null, 'test');
		$this->assertEquals($tmpl->name, 'test');
		$tmpl->setName(__METHOD__);
		$this->assertEquals($tmpl->name, __METHOD__);
	}

	public function test_create() {
		$justFile = new Template(dirname(__FILE__) .'/files/templates/main.tmpl');
		$this->assertEquals('main', $justFile->name);
		$this->assertEquals(file_get_contents(dirname(__FILE__) .'/files/templates/main.tmpl'), $justFile->contents);

		$full = new Template(dirname(__FILE__) .'/files/templates/main.tmpl', "test");
		$this->assertEquals('test', $full->name);
		$this->assertEquals(file_get_contents(dirname(__FILE__) .'/files/templates/main.tmpl'), $full->contents);

		$empty = new Template(null, "empty");
		$this->assertEquals('empty', $empty->name);
		$this->assertEquals(null, $empty->contents);

		try {
			$x = new Template(dirname(__FILE__) .'/invalid/path/to/main.tmpl');
			$this->assertFalse(true, "template instantiated using an invalid filename");
		}
		catch(InvalidArgumentException $ex) {
			$this->assertTrue((bool)preg_match('~file does not exist~', $ex->getMessage()), "unexpected exception message: ". $ex->getMessage());
		}
	}


	public function test_render() {

		$x = new Template(dirname(__FILE__) .'/files/templates/main.tmpl');

		$originalContents = $x->contents;

		$rendered = $x->render();
		$this->assertTrue(strlen($x->render()) > 0, "failed to render template");

		$this->assertNotEquals($originalContents, $x->render(true), "render did not remove loose template vars");
		$this->assertEquals(0, count(Template::getTemplateVarDefinitions($x->render(true))), "found loose template vars");

		$this->assertEquals($originalContents, $x->render(false), "render removed loose template strings when told not to");
		$this->assertTrue(count(Template::getTemplateVarDefinitions($x->render(false))) > 0, "render removed loose template strings (second check)");

		$allVars = Template::getTemplateVarDefinitions($x->render(false));
		foreach(array_keys($allVars) as $k) {
			$x->addVar($k);
		}
		$this->assertEquals($x->render(false), $x->render(true), "render failed when all template vars accounted for");
	}


	public function test_setRecursion() {
		try {
			$x = new Template(null);
			$x->set_recursionDepth(null);
		}
		catch(InvalidArgumentException $ex) {
			$this->assertTrue((bool)preg_match('~^$~', $ex->getMessage()), "unexpected exception contents: ". $ex->getMessage());
		}
	}


	public function test_recursion() {
		$x = new Template(null, "main");
		$x->setContents("{recursive1}");
		$x->set_recursionDepth(50);


		$x->add(new Template(dirname(__FILE__) .'/files/templates/recursive1.tmpl'),false);
		$x->add(new Template(dirname(__FILE__) .'/files/templates/recursive2.tmpl'), false);

		$rendered = $x->render(true);
		$this->assertTrue(strlen($rendered) > 0, "rendered value is blank... ");

		$matches = array();
		$num = preg_match_all('~recursive1~', $rendered, $matches);

		$this->assertEquals(50, $num, "did not recurse... ");
	}



	public function test_origin() {
		$file = dirname(__FILE__) .'/files/templates/main.tmpl';
		$x = new Template($file);
		$this->assertEquals($file, $x->origin);

		$y = new Template(null);
		$this->assertEquals(null, $y->origin);
	}


	public function test_dir() {
		$file = dirname(__FILE__) .'/files/templates/main.tmpl';

		$x = new Template($file);
		$this->assertEquals(dirname($file), $x->dir, "template dir not set");

		$y = new Template(null);
		$this->assertEquals(null, $y->dir, "template dir not null when null used for filename (". $y->dir .")");
	}


	public function test_basics() {
		$x = new Template(dirname(__FILE__) .'/files/templates/main.tmpl');

		$one = new Template(dirname(__FILE__) .'/files/templates/file1.tmpl');
		$one->addVar('file2', "test");
		$one->addVar('var1', "template");
		$one->addVar('var2', "file");
		$one->addVar('var3', " inheritance is awesome");

		$x->add($one);

		$this->assertTrue((bool)preg_match('~file2: test~', $x->render()), "template inheritance failed::: ". $x->render());
		$this->assertTrue((bool)preg_match('~file1: contents from file1~', $x->render()), "contents from file1 not loaded into main template");
		$this->assertTrue((bool)preg_match('~template file inheritance is awesome~', $x->render()), "template var inheritance failed");

		$two = new Template(dirname(__FILE__) .'/files/templates/file2.tmpl');
		$two->addVar('var3', " was changed");

		$x->add($two);

		$this->assertTrue((bool)preg_match('~file2: contents from file2~', $x->render()), "new template did not work");
		$this->assertTrue((bool)preg_match('~template file was changed~', $x->render()), "new template did not overwrite original vars");
	}
	
	
	public function test_addVarList() {
		$varList = array(
			'var1'		=> "template",
			'var2'		=> "file",
			'var3'		=> "inheritance is awesome",
			'var4'		=> "some more stuff...",
			'var5'		=> "",
		);
		
		$x = new Template(__DIR__ .'/files/templates/varArray.tmpl');
		foreach($varList as $k=>$v) {
			$x->addVar($k, $v);
		}
		
		$y = new Template(__DIR__ .'/files/templates/varArray.tmpl');
		$y->addVarList($varList);
		
		$this->assertEquals($x->render(), $y->render(), "Adding vars by array didn't work like adding them individually");
		$this->assertEquals($x, $y);
	}
	
	
	public function test_addVarListWithPrefix() {
		$varList = array(
			'var1'		=> "template",
			'var2'		=> "file",
			'var3'		=> "inheritance is awesome",
			'var4'		=> "some more stuff...",
			'var5'		=> "",
		);
		
		$x = new Template(__DIR__ .'/files/templates/varArray_withPrefix.tmpl');
		
		// make sure we can get the two lines..
		$prerender = $x->render(true);
		
		$bits = explode("\n", $prerender);
		$this->assertEquals($bits[0], $bits[1]);
		
		$x->addVarListWithPrefix($varList, "prefix_");
		$afterPrefix = $x->render(true);
		$prefixRenderBits = explode("\n", $afterPrefix);
		$this->assertNotEquals($prefixRenderBits[0], $prefixRenderBits[1]);
		$this->assertTrue(strlen($prefixRenderBits[0]) == strlen(implode('', $varList)));
		$this->assertEquals(0, strlen($prefixRenderBits[1]));
		
		$x->addVarList($varList);
		$afterAdd = $x->render(true);
		$finalBits = explode("\n", $afterAdd);
		$this->assertEquals($finalBits[0], $finalBits[1]);
	}


	public function test_blockRows() {
		$x = new Template(dirname(__FILE__) .'/files/templates/mainWithBlockRow.tmpl');
		
		$rowDefs = $x->get_block_row_defs();
		$this->assertTrue(is_array($rowDefs), "missing block rows array");
		$this->assertTrue(count($rowDefs) > 0, "no block rows found... ");
		$this->assertEquals(1, count($rowDefs), "failed to parse block rows from main template");

		$rows = array(
			'first'     => array('var1'=>"this", 'var2'=>"is", 'var3'=>"the first row"),
			'second'    => array('var1'=>"And this", 'var2'=>"can be", 'var3'=>"the next(second) row"),
			'third'     => array('var1'=>"The final", 'var2'=>"version", 'var3'=>"right here")
		);
		$x->setBlockRow('test');
		$x->parseBlockRow('test', $rows);

		foreach($rows as $rowName=>$data) {
			$joined = implode(' ', $data);
			$testPosition = strpos($x->render(), $joined);
			$this->assertTrue(is_numeric($testPosition), "string position isn't numeric:". ToolBox::debug_var_dump($testPosition,0));
			$this->assertTrue($testPosition > 0, " ($testPosition) rendered template is missing string '". $joined ."'... ". ToolBox::debug_var_dump($testPosition,0) . $x->render());
		}

		$this->assertFalse((bool)preg_match('~<!-- BEGIN ~', $x->render()), "rendered template still contains block row begin tag");
		$this->assertFalse((bool)preg_match('~<!-- END ~', $x->render()), "rendered template still contains block row end tag");
	}
	
	
	public function test_reset() {
		$x = new Template(__DIR__ .'/files/templates/main.tmpl');
		$y = clone $x;
		
		$x->addVarList(array('one'=>1,'two'=>2));
		$z = clone $x;
		
		$x->reset();
		
		$this->assertEquals($x, $y);
		$this->assertNotEquals($x, $z);
	}
	
	
	public function test_rowParsing() {
		$path = __DIR__ .'/files/templates/testRow.tmpl';
		
		$recordSet = array(
			0 => array(
				'primary_id'    => 1,
				'record_name'   => 'The First Record',
				'another_field' => 'field value',
				'is_active'     => 0,
			),
			1 => array(
				'primary_id'    => 3,
				'record_name'   => 'A third record',
				'another_field' => 'something else',
				'is_active'     => 1,
			),
		);
		
		$old = new Template($path);
		$rendered = "";
		
		$new = new Template($path);
		
		foreach($recordSet as $record) {
			$old->addVarList($record);
			$rendered .= $old->render();
		}
		
		$newRender = $new->renderRows($recordSet);
		
		$this->assertEquals($rendered, $newRender);
		
		$testMe = clone $new;
		$new->reset();
		$this->assertEquals($testMe, $new, "template not reset after renderRows()");
		
		$old->reset();
		
		// make sure rendering works without stripping undefined vars
		
		$renderedWithLeftovers = "";
		foreach($recordSet as $record) {
			$old->addVarList($record);
			$renderedWithLeftovers .= $old->render(false);
		}
		
		$newRenderedWithLeftovers = $new->renderRows($recordSet, false);
		
		$this->assertEquals($renderedWithLeftovers, $newRenderedWithLeftovers);
	}
	
	
	public function test_strippingJsOneLiners() {
		$_tmpl = new Template(__DIR__ .'/files/templates/js.tmpl');
		$foundThis = strstr($_tmpl->contents, 'Typekit.load');
		$this->assertTrue($foundThis !== false);
		$this->assertTrue(strlen($foundThis) > 0);
		
		$rendered = $_tmpl->render(true);
		$foundAfterRender = strstr($rendered, 'Typekit.load');
		$this->assertTrue($foundAfterRender !== false, "javascript was stripped from output: ". $foundAfterRender);
		$this->assertTrue(strlen($foundAfterRender) > 0);
		
		$this->assertEquals(strlen($foundThis), strlen($foundAfterRender));
	}
	
	
	public function test_inheritance() {
		$_main = new Template(__DIR__ .'/files/templates/inheritance_main.tmpl');
		$_sub = new Template(__DIR__ .'/files/templates/inheritance_sub.tmpl');
		
		$mainVars = array(
			'inheritance'	=> "Loads of money",
			'separate'		=> "ONLY FOR MAIN",
		);
		
		$recordSet = array(
			0	=> array('separate'	=> 'first'),
			1	=> array('separate'	=> 'second'),
			2	=> array('separate'	=> 'third'),
		);
		
		$rows = $_sub->renderRows($recordSet);
		$_main->addVarList($mainVars);
		$_main->addVar('subTemplate', $rows, false);
		
		$rendered = $_main->render(false);
		
		$this->assertEquals(0, count(Template::getTemplateVarDefinitions($rendered)));
		
		$this->assertEquals(1, preg_match('/first||/', $rendered));
		$this->assertEquals(1, preg_match('/second||/', $rendered));
		$this->assertEquals(1, preg_match('/third||/', $rendered));
		
		$matches = array();
		$this->assertEquals(4, preg_match_all('/Loads of money/', $rendered, $matches), "inheritance failed: ".ToolBox::debug_print($matches,0));
		$this->assertEquals(4, count($matches[0]), "inheritance failed, not all variables were filled in: ". ToolBox::debug_print($matches) ."\n\n". ToolBox::debug_print($rendered));
	}
	
	/**
	 * @expectedException InvalidArgumentException
	 */
	public function test_invalid_parseBlockRow() {
		$x = new Template(__DIR__ .'/files/templates/mainWithBlockRow.tmpl');
		
		$recordSet = array(
			0	=> array("var1"=>1, "var2"=>2, "var3"=>3),
			1	=> array("var1"=>4, "var2"=>5, "var3"=>6),
		);
		$x->parseBlockRow("test", $recordSet);
	}
	
	
	public function test_parseBlockRow() {
		$x = new Template(__DIR__ .'/files/templates/mainWithBlockRow.tmpl');
		$x->setBlockRow('test');
	}
	
	
	public function test_setAllBlockRows() {
		$x = new Template(__DIR__ .'/files/templates/mainWithBlockRow.tmpl');
		
		$allDefs = $x->get_block_row_defs();
		$allRows = $x->setAllBlockRows();
		
		$this->assertEquals(count($allDefs), count($allRows), "did not get equal lists of rows from get vs set");
		foreach($allDefs as $testRowName) {
			$this->assertTrue(isset($allRows[$testRowName]), "could not find definition for row '". $testRowName ."' in list of all rows");
		}
		
		$this->assertEquals(1, count($allRows), "invalid number of block rows found");
		$this->assertTrue(isset($allRows['test']), "could not find block row 'test'");
		
		$rowsFromObject = $x->blockRows;
		$this->assertTrue(is_array($rowsFromObject));
		$this->assertEquals(1, count($rowsFromObject));
		
		$this->assertEquals($allRows, $rowsFromObject, "rows returned does not match rows in object");
		
		$recordSet = array(
			0	=> array("var1"=>"1", "var2"=>"2", "var3"=>"3"),
			1	=> array("var1"=>"x4", "var2"=>"x5", "var3"=>"x6"),
		);
		$checkThis = $x->parseBlockRow("test", $recordSet);
		
		
		$this->assertTrue(strlen($checkThis) > 0, "no length in parsed row (". $checkThis .")");
		
		$this->assertEquals(0, strpos($checkThis, '1 2 3'), "could not find parsed values... '". $checkThis ."'");
		$this->assertEquals(6, strpos($checkThis, 'x4 x5 x6'), "could not find second set of parsed values... '". $checkThis ."'");
	}
	
	
	public function test_getDefinitions() {
		$x = new Template(__DIR__ ."/files/templates/definitionsTest.tmpl");
		$contents = file_get_contents($x->origin);
		
		$this->assertEquals($x->render(), $contents, "template output differs from file contents");
		
		$staticOut = Template::getTemplateVarDefinitions($contents);
		$normalOut = $x->getVarDefinitions($contents);
		$testOut = $x->getVarDefinitions(file_get_contents(__DIR__ .'/files/templates/main.tmpl'));
		
		$this->assertEquals($staticOut, $normalOut, "static output differs from function call output");
		$this->assertNotEquals($staticOut, $testOut, "static output does NOT differ from test that uses a different file [MUST BE DIFFERENT]");
		$this->assertNotEquals($normalOut, $testOut, "normal output does NOT differ from test that uses a different file [MUST BE DIFFERENT]");
		
		$expectations = array(
			'var1'		=> 2,
			'var2'		=> 1,
			'stuff4'	=> 1,
		);
		$leftOvers = $expectations;
		
		foreach($expectations as $name=>$occurrences) {
			$this->assertTrue(isset($normalOut[$name]), "missing '{$name}' from output");
			$this->assertEquals($occurrences, $normalOut[$name], "invalid number of occurrences for '{$name}'");
			
			unset($leftOvers[$name]);
		}
		
		$this->assertEquals(array(), $leftOvers, "found some unexpected leftovers");
	}
}
