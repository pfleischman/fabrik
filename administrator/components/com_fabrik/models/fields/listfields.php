<?php
/**
 * @package Joomla
 * @subpackage Fabrik
 * @copyright Copyright (C) 2005 Rob Clayburn. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 */

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die();

jimport('joomla.html.html');
jimport('joomla.form.formfield');
jimport('joomla.form.helper');
JFormHelper::loadFieldClass('list');
require_once(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_fabrik'.DS.'helpers'.DS.'element.php');

/**
 * Renders a list of elements found in a fabrik table
 *
 * @package 	Joomla
 * @subpackage	Fabrik
 * @since		1.6
 */

class JFormFieldListfields extends JFormFieldList
{
	/**
	 * Element name
	 * @access	protected
	 * @var		string
	 */
	var	$_name = 'Listfields';

	/** @var array objects resulting from this elements queries - keyed on idetifying hash */
	var $results = null;


	function getInput()
	{
		if (is_null($this->results)) {
			$this->results = array();
		}

		$db = FabrikWorker::getDbo();
		$controller = JRequest::getVar('view');
		$aEls = array();
		$pluginFilters = trim($this->element['filter']) == '' ? array() : explode("|", $this->element['filter']);
		$bits = array();
		$c = ElementHelper::getRepeatCounter($this);
		$connection = $this->element['connection'];
		$valueformat = JArrayHelper::getValue($this->element, 'valueformat', 'tableelement');
		$onlylistfields = (int)JArrayHelper::getValue($this->element, 'onlylistfields', 0);
		switch ($controller)
		{
			case 'element':
				//@TODO this seems like we could refractor it to use the formModel class as per the table and form switches below?
				$connectionDd = ($c === false) ? $connection :  $connection . '-' . $c;
				if ($connection == '') {
					$paths = JModel::addIncludePath(COM_FABRIK_FRONTEND.DS.'models');
					$groupModel = JModel::getInstance('Group', 'FabrikFEModel');

					$groupId = isset($this->form->rawData) ? JArrayHelper::getValue($this->form->rawData, 'group_id', 0) : $this->form->getValue('group_id');
					$groupModel->setId($groupId);
					$optskey = $valueformat == 'tableelement' ? 'name' : 'id';
					$res = $groupModel->getForm()->getElementOptions(false, $optskey, $onlylistfields, false, $pluginFilters);
					$hash = "$controller.".implode('.', $bits);
					if (array_key_exists($hash, $this->results)) {
						$res = $this->results[$hash];
					} else {
						$this->results[$hash] =& $res;
					}
				} else {

					//****************************//
					$repeat 	= ElementHelper::getRepeat($this);
					$tableDd = $this->element['table'];

					$opts = new stdClass();
					$opts->table = ($repeat) ? 'jform_' . $tableDd . "-" .$c : 'jform_' . $tableDd;

					$opts->livesite = COM_FABRIK_LIVESITE;
					$opts->conn = 'jform_' .$connectionDd;
					$opts->value = $this->value;
					$opts->repeat = $this->value;
					$opts = json_encode($opts);

					$script =	"new ListFieldsElement('$this->id', $opts);\n";

					FabrikHelperHTML::script('administrator/components/com_fabrik/models/fields/listfields.js', true, $script);

					$rows = array(JHTML::_( 'select.option', '', JText::_('SELECT A CONNECTION FIRST')), 'value', 'text');
					$o = new stdClass();
					$o->table_name = '';
					$o->name = '';
					$o->value = '';
					$o->text = JText::_('SELECT A TABLE FIRST');
					$res[] = $o;
					//****************************//
				}
				break;
			case 'list':
				$id = $this->form->getValue('id');
				if (!isset($this->form->model)) {
					JError::raiseNotice(500, 'Model not set in listfields field '. $this->id);
					return;
				}
				$listModel = $this->form->model;
				if ($id !== 0) {
					$formModel =& $listModel->getFormModel();
					$valfield = $valueformat == 'tableelement' ? 'name' : 'id';
					$res = $formModel->getElementOptions(false, $valfield, $onlylistfields, false, $pluginFilters);
				} else {
					$res = array();
				}
				break;
			case 'form':
			if (!isset($this->form->model)) {
					JError::raiseNotice(500, 'Model not set in listfields field '. $this->id);
					return;
				}
				$formModel = $this->form->model;
				$valfield = $valueformat == 'tableelement' ? 'name' : 'id';
				$res = $formModel->getElementOptions(false, $valfield, $onlylistfields, false, $pluginFilters);
				break;
			default:
				return JText::_('THE LISTFIELDS ELEMENT IS ONLY USABLE BY LISTS AND ELEMENTS');
				break;
		}
		$return = '';
		if (is_array($res)) {
			if ($controller == 'element') {
				foreach ($res as $o) {
					$s = new stdClass();
					//element already contains correct key
					if ($controller != 'element') {
						$s->value= $valueformat == 'tableelement' ? $o->table_name.'.'.$o->text : $o->value;
					} else {
						$s->value = $o->value;
					}
					$s->text = FabrikString::getShortDdLabel($o->text);
					$aEls[] = $s;
				}
			} else {
				foreach ($res as &$o) {
					$o->text = FabrikString::getShortDdLabel($o->text);
				}
				$aEls = $res;
			}
			//$id 			= ElementHelper::getId($this, $control_name, $name);
			$aEls[] = JHTML::_('select.option', '', '-');
			// for pk fields - we are no longer storing the key with '`' as thats mySQL specific
			$this->value = str_replace('`', '', $this->value);
			$return = JHTML::_('select.genericlist',  $aEls, $this->name, 'class="inputbox" size="1" ', 'value', 'text', $this->value, $this->id);
			$return .= "<img style=\"margin-left:10px;display:none\" id=\"".$this->id."_loader\" src=\"components/com_fabrik/images/ajax-loader.gif\" alt=\"" . JText::_('LOADING'). "\" />";
		}
		return $return;
	}
}