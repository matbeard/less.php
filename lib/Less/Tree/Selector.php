<?php

/**
 * Selector
 *
 * @package Less
 * @subpackage tree
 */
class Less_Tree_Selector extends Less_Tree{

	public $elements;
	public $condition;
	public $extendList = array();
	public $_css;
	public $index;
	public $evaldCondition = false;
	public $type = 'Selector';
	public $currentFileInfo = array();
	public $isReferenced;

	public $elements_len = 0;

	public $_oelements;
	public $_oelements_len;

	/**
	 * @param boolean $isReferenced
	 */
	public function __construct( $elements, $extendList = array() , $condition = null, $index=null, $currentFileInfo=null, $isReferenced=null ){

		$this->elements = $elements;
		$this->elements_len = count($elements);
		$this->extendList = $extendList;
		$this->condition = $condition;
		if( $currentFileInfo ){
			$this->currentFileInfo = $currentFileInfo;
		}
		$this->isReferenced = $isReferenced;
		if( !$condition ){
			$this->evaldCondition = true;
		}
	}

	function accept($visitor) {
		$this->elements = $visitor->visitArray($this->elements);
		$this->extendList = $visitor->visitArray($this->extendList);
		if( $this->condition ){
			$this->condition = $visitor->visitObj($this->condition);
		}
	}

	function createDerived( $elements, $extendList = null, $evaldCondition = null ){
		$newSelector = new Less_Tree_Selector( $elements, ($extendList ? $extendList : $this->extendList), $this->condition, $this->index, $this->currentFileInfo, $this->isReferenced);
		$newSelector->evaldCondition = $evaldCondition ? $evaldCondition : $this->evaldCondition;
		return $newSelector;
	}


	// Performance issues with 1.6.1
	// Compiling bootstrap almost doubled: from 4.5 seconds to 7.8 seconds
	public function match( $other ){

		if( is_null($other->_oelements) ){
			$other->CacheElements();
		}
		if( !$other->_oelements || ($this->elements_len < $other->_oelements_len) ){
			return 0;
		}

		for( $i = 0; $i < $other->_oelements_len; $i++ ){
			if( $this->elements[$i]->value !== $other->_oelements[$i]) {
				return 0;
			}
		}

		return $other->_oelements_len; // return number of matched elements
	}


	public function CacheElements(){

		$this->_oelements = array();
		$css = '';

		foreach($this->elements as $v){

			$css .= $v->combinator;
			if( !$v->value_is_object ){
				$css .= $v->value;
				continue;
			}

			if( !property_exists($v->value,'value') || !is_string($v->value->value) ){
				return;
			}
			$css .= $v->value->value;
		}

		$this->_oelements_len = preg_match_all('/[,&#\.\w-](?:[\w-]|(?:\\\\.))*/', $css, $matches);
		if( $this->_oelements_len ){
			$this->_oelements = $matches[0];

			if( $this->_oelements[0] === '&' ){
				array_shift($this->_oelements);
				$this->_oelements_len--;
			}
		}
	}


	public function compile($env) {

		$elements = array();
		foreach($this->elements as $el){
			$elements[] = $el->compile($env);
		}

		$extendList = array();
		foreach($this->extendList as $el){
			$extendList[] = $el->compile($el);
		}

		$evaldCondition = false;
		if( $this->condition ){
			$evaldCondition = $this->condition->compile($env);
		}

		return $this->createDerived( $elements, $extendList, $evaldCondition );
	}


	/**
	 * @see Less_Tree::genCSS
	 */
	function genCSS( $output, $firstSelector = true ){

		if( !$firstSelector && $this->elements[0]->combinator === "" ){
			$output->add(' ', $this->currentFileInfo, $this->index);
		}

		foreach($this->elements as $element){
			$element->genCSS( $output );
		}
	}

	function markReferenced(){
		$this->isReferenced = true;
	}

	function getIsReferenced(){
		return !isset($this->currentFileInfo['reference']) || !$this->currentFileInfo['reference'] || $this->isReferenced;
	}

	function getIsOutput(){
		return $this->evaldCondition;
	}

}
