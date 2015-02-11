<?php

use crazedsanity\Template;
use crazedsanity\ToolBox;
use \InvalidArgumentException;
use \Exception;

class TestOfTemplate extends PHPUnit_Framework_TestCase {

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

		$this->assertNotEquals($originalContents, $x->render(), "render did not remove loose template vars");
		$this->assertEquals(0, count(Template::getTemplateVarDefinitions($x->render())), "found loose template vars");

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

		$rendered = $x->render();
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


	public function test_blockRows() {
		$x = new Template(dirname(__FILE__) .'/files/templates/mainWithBlockRow.tmpl');

		$this->assertTrue(is_array($x->blockRows), "missing block rows array");
		$this->assertTrue(count($x->blockRows) > 0, "no block rows found... ");
		$this->assertEquals(1, count($x->blockRows), "failed to parse block rows from main template");

		//make sure setting contents works identically to specifying contents in constructor.
		$y = new Template(null);
		$y->setContents(file_get_contents($x->origin));
		$this->assertEquals($x->blockRows, $y->blockRows);

		$rows = array(
			'first'     => array('var1'=>"this", 'var2'=>"is", 'var3'=>"the first row"),
			'second'    => array('var1'=>"And this", 'var2'=>"can be", 'var3'=>"the next(second) row"),
			'third'     => array('var1'=>"The final", 'var2'=>"version", 'var3'=>"right here")
		);
		$x->parseBlockRow('test', $rows);

		foreach($rows as $rowName=>$data) {
			$joined = implode(' ', $data);
			$testPosition = strpos($x->render(), $joined);
			$this->assertTrue($testPosition !== false, " ($testPosition) rendered template is missing string '". $joined ."'... ". $x->render());
		}

		$this->assertFalse((bool)preg_match('~<!-- BEGIN ~', $x->render()), "rendered template still contains block row begin tag");
		$this->assertFalse((bool)preg_match('~<!-- END ~', $x->render()), "rendered template still contains block row end tag");
	}
}
