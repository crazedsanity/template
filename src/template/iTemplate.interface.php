<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace crazedsanity\template;

/**
 *
 * @author danf
 */
interface iTemplate {
	public function load($file);
	public function add(Template $tmpl);
	public function addVar($name, $value);
	public function addVarList(array $vars);
	public function setContents($value);
	public function render($stripUndefinedVars=true);
	public function renderRows(array $recordSet, $stripUndefinedVars=false);
	public function parseBlockRow($name, array $listOfVarToValue, $useTemplateVar=null);
	public function __toString();
	public function reset();
	public static function getTemplateVarDefinitions($fromContents);
}
