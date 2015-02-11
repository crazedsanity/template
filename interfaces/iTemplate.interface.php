<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace crazedsanity;

/**
 *
 * @author danf
 */
interface iTemplate {
	public function add(Template $tmpl);
	public function addVar($name, $value);
	public function render($stripUndefinedVars=true);
	public function parseBlockRow($name, array $listOfVarToValue, $useTemplateVar=null);
}
