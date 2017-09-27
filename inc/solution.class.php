<?php
/**
 * ---------------------------------------------------------------------
 * GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2015-2017 Teclib' and contributors.
 *
 * http://glpi-project.org
 *
 * based on GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2003-2014 by the INDEPNET Development Team.
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of GLPI.
 *
 * GLPI is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * GLPI is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with GLPI. If not, see <http://www.gnu.org/licenses/>.
 * ---------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}


/**
 * Solution Class
**/
class Solution extends CommonDBTM {

   // From CommonDBTM
   public $dohistory                   = true;

   //static $rightname                   = 'solution';
   //protected $usenotepad               = true;

   static function getTypeName($nb = 0) {
      return _n('Solution', 'Solutions', $nb);
   }

   public static function canCreate() {
      return true;
   }

   public function canCreateItem() {
      $item = new $this->fields['itemtype'];
      $item->getFromDB($this->fields['items_id']);
      return $item->canSolve();
   }

   function canEdit($ID) {
      if ($this->isNewItem()) {
         return $this->item->canSolve();
      } else {
         return parent::canEdit($ID);
      }
   }


   /**
    * Print the phone form
    *
    * @param $ID integer ID of the item
    * @param $options array
    *     - item: CommonITILObject instance
    *     - kb_id_toload: load new item content from KB entry
    *
    * @return boolean item found
   **/
   function showForm($ID, $options = []) {
      global $CFG_GLPI;

      $this->getEmpty();
      $item = $options['item'];
      $this->item = $item;
      $item->check($item->getID(), READ);

      $close_warning = false;
      if ($item instanceof Ticket && $this->isNewItem()) {
         $ti = new Ticket_Ticket();
         $open_child = $ti->countOpenChildren($item->getID());
         if ($open_child > 0) {
            echo "<div class='tab_cadre_fixe warning'>" . __('Warning: non closed children tickets depends on current ticket. Are you sure you want to close it?')  . "</div>";
         }
      }

      $canedit = $item->canSolve();
      $options = [];

      if (isset($options['kb_id_toload']) && $options['kb_id_toload'] > 0) {
         $kb = new KnowbaseItem();
         if ($kb->getFromDB($options['kb_id_toload'])) {
            $this->fields['content'] = $kb->getField('answer');
         }
      }

      // Alert if validation waiting
      $validationtype = $item->getType().'Validation';
      if (method_exists($validationtype, 'alertValidation') && $this->isNewItem()) {
         $validationtype::alertValidation($item, 'solution');
      }

      $this->showFormHeader($options);

      $show_template = $canedit;
      $rand_template = mt_rand();
      $rand_text     = $rand_type = 0;
      if ($canedit) {
         $rand_text = mt_rand();
         $rand_type = mt_rand();
      }
      if ($show_template) {
         echo "<tr class='tab_bg_2'>";
         echo "<td>"._n('Solution template', 'Solution templates', 1)."</td><td>";

         SolutionTemplate::dropdown([
            'value'    => 0,
            'entity'   => $this->getEntityID(),
            'rand'     => $rand_template,
            // Load type and solution from bookmark
            'toupdate' => [
               'value_fieldname' => 'value',
               'to_update'       => 'solution'.$rand_text,
               'url'             => $CFG_GLPI["root_doc"]. "/ajax/solution.php",
               'moreparams' => [
                  'type_id' => 'dropdown_solutiontypes_id'.$rand_type
               ]
            ]
         ]);

         echo "</td><td colspan='2'>";
         if (Session::haveRightsOr('knowbase', [READ, KnowbaseItem::READFAQ])) {
            echo "<a class='vsubmit' title=\"".__s('Search a solution')."\"
                   href='".$CFG_GLPI['root_doc']."/front/knowbaseitem.php?item_itemtype=".
                   $item->getType()."&amp;item_items_id=".$item->getID().
                   "&amp;forcetab=Knowbase$1'>".__('Search a solution')."</a>";
         }
         echo "</td></tr>";
      }

      echo "<tr class='tab_bg_2'>";
      echo "<td>".__('Solution type')."</td><td>";

      echo Html::hidden('itemtype', ['value' => $item->getType()]);
      echo Html::hidden('items_id', ['value' => $item->getID()]);

      // Settings a solution will set status to solved
      if ($canedit) {
         SolutionType::dropdown(['value'  => $this->getField('solutiontypes_id'),
                                 'rand'   => $rand_type,
                                 'entity' => $this->getEntityID()]);
      } else {
         echo Dropdown::getDropdownName('glpi_solutiontypes',
                                        $this->getField('solutiontypes_id'));
      }
      echo "</td><td colspan='2'>";

      if (Session::haveRightsOr('knowbase', [READ, KnowbaseItem::READFAQ]) && isset($options['kb_id_toload']) && $options['kb_id_toload'] != 0) {
         echo '<br/><input type="checkbox" name="kb_linked_id" id="kb_linked_id" value="' . $kb->getID() . '" checked="checked">';
         echo ' <label for="kb_linked_id">' . str_replace('%id', $kb->getID(), __('Link to knowledge base entry #%id')) . '</label>';
      } else {
         echo '&nbsp;';
      }
      echo "</td></tr>";
      if ($canedit && Session::haveRight('knowbase', UPDATE)) {
         echo "<tr class='tab_bg_2'><td>".__('Save and add to the knowledge base')."</td><td>";
         Dropdown::showYesNo('_sol_to_kb', false);
         echo "</td><td colspan='2'>&nbsp;</td></tr>";
      }
      echo "<tr class='tab_bg_2'>";
      echo "<td>".__('Description')."</td><td colspan='3'>";

      if ($canedit) {
         $rand = mt_rand();
         Html::initEditorSystem("content$rand");

         echo "<div id='solution$rand_text'>";
         echo "<textarea id='content$rand' name='content' rows='12' cols='80'>".
                $this->getField('content')."</textarea></div>";

      } else {
         echo Toolbox::unclean_cross_side_scripting_deep($this->getField('content'));
      }
      echo "</td></tr>";

      $options['candel']   = false;
      $options['canedit']  = $canedit;
      $this->showFormButtons($options);
   }


   /**
    * Count solutions for specific item
    *
    * @param string  $itemtype Item type
    * @param integer $items_id Item ID
    *
    * @return integer
    */
   public static function countFor($itemtype, $items_id) {
      return countElementsInTable(
         self::getTable(), [
            'WHERE' => [
               'itemtype'  => $itemtype,
               'items_id'  => $items_id
            ]
         ]
      );
   }
}
