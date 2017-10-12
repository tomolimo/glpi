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
 * ITILSolution Class
**/
class ITILSolution extends CommonDBTM {

   // From CommonDBTM
   public $dohistory                   = true;
   private $item                       = null;

   static function getTypeName($nb = 0) {
      return _n('Solution', 'Solutions', $nb);
   }

   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
      if ($item->isNewItem()) {
         return;
      }
      if (static::canView()) {
         $nb    = 0;
         $title = self::getTypeName(Session::getPluralNumber());
         if ($_SESSION['glpishow_count_on_tabs']) {
            $nb = self::countFor($item->getType(), $item->getID());
         }
         return self::createTabEntry($title, $nb);
      }
   }

   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
      $sol = new self();
      $sol->showSummary($item);
   }

   public static function canUpdate() {
      //FIXME
      return true;
   }

   public static function canCreate() {
      //always true, will rely on self::canCreateItem
      return true;
   }

   public static function canView() {
      //FIXME
      return true;
   }

   public function canCreateItem() {
      if ($this->isNewItem()) {
         $item = new $this->fields['itemtype'];
         $item->getFromDB($this->fields['items_id']);
         return $item->canSolve();
      } else {
         //FIXME
         return true;
      }
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

      if ($this->isNewItem()) {
         $this->getEmpty();
      }

      if (!isset($options['item']) && isset($options['parent'])) {
         //when we came from aja/viewsubitem.php
         $options['item'] = $options['parent'];
      }
      $item = $options['item'];
      $this->item = $item;
      $item->check($item->getID(), READ);

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

   function prepareInputForAdd($input) {
      $input['users_id'] = Session::getLoginUserID();
      return $input;
   }

   function post_addItem() {
      //adding a solution mean the ITIL object is now closed
      if ($this->item == null) {
         $this->item = new $this->fields['itemtype'];
         $this->item->getFromDB($this->fields['items_id']);
      }

      $item = $this->item;
      $status = $item::SOLVED;
      if ($this->item->getType() == 'Ticket') {
         $autoclosedelay =  Entity::getUsedConfig(
            'autoclose_delay',
            $this->item->getEntityID(),
            '',
            Entity::CONFIG_NEVER
         );

         // 0 = immediatly
         if ($autoclosedelay == 0) {
            $status = $item::CLOSED;
         }
      }
      $this->item->update([
         'id'     => $this->item->getID(),
         'status' => $status
      ]);
      if ($this->item->getType() == 'Ticket') {
         Ticket_Ticket::manageLinkedTicketsOnSolved($this->item->getID(), $this);
      }
   }

   /**
    * Remove solutions for an item
    *
    * @param string  $itemtype Item type
    * @param integer $items_id Item ID
    *
    * @return void
    */
   public function removeForItem($itemtype, $items_id) {
      $this->deleteByCriteria(
         [
            'itemtype'  => $itemtype,
            'items_id'  => $items_id
         ],
         true
      );
   }

   /**
    * Show solutions for an item
    *
    * @param CommonITILObject $item Item instance
    *
    * return void
    */
   public function showSummary(CommonITILObject $item) {
      global $DB, $CFG_GLPI;

      if (isset($_GET["start"])) {
         $start = intval($_GET["start"]);
      } else {
         $start = 0;
      }

      $can_edit = self::canUpdate() || $item->canSolve();
      $can_add  = !in_array($item->fields["status"],
                     array_merge($item->getSolvedStatusArray(), $item->getClosedStatusArray()))
            && Ticket::canUpdate() && $item->canSolve();
      $where = [
         'itemtype'  => $item->getType(),
         'items_id'  => $item->getID()
      ];

      $rand   = mt_rand();
      $number = countElementsInTable(
         self::getTable(),
         $where
      );

      // Not closed ticket or closed
      if ($can_add) {
         echo "<div id='addbutton".$item->getID() . "$rand' class='center firstbloc'>".
               "<a class='vsubmit' href='javascript:viewAddSubitem".$item->getID()."$rand(\"Solution\");'>";
         echo __('Add a new solution');
         echo "</a></div>\n";

      }
      // No solutions in database
      if ($number < 1) {
         $no_txt = sprintf(__('No solutions for this %1$s'), $item->getTypeName(1));
         echo "<div class='center'>";
         echo "<table class='tab_cadre_fixe'>";
         echo "<tr class='tab_bg_2'><th class='b'>$no_txt</th></tr>";
         echo "</table>";
         echo "</div>";
         return;
      }
      // Display the pager

      // Output events
      echo "<div class='center'>";

      $solutions = $DB->request(
         self::getTable(),
         $where + ['ORDER' => 'id DESC']
      );

      foreach ($solutions as $solution) {
         $options = [
            'parent' => $item,
            'rand'   => $rand
         ];
         Plugin::doHook('pre_show_item', ['item' => $this, 'options' => &$options]);

         $user = new User();
         $user->getFromDB($solution['users_id']);

         $class = '';
         if ($solution['is_rejected'] == 0) {
            $class .= " accepted";
         }

         echo "<div class='boxnote$class' id='viewitemSolution{$solution['id']}$rand'>";

         echo "<div class='boxnoteleft center'>";
         echo "<img class='user_picture_verysmall' alt='' src='" . User::getThumbnailURLForPicture($user->fields['picture'])."'>";

         echo "<br/><span class='h_user_name'>";
         $userdata = getUserName($solution['users_id'], 2);
         echo $user->getLink()."&nbsp;";
         echo Html::showToolTip($userdata["comment"],
                                 ['link' => $userdata['link']]);
         echo "</span>";

         echo "</div>"; // boxnoteleft

         echo "<div class='boxnotecontent'";
         if ($can_edit) {
            echo " onclick='javascript:viewEditSubitem".$item->getID()."$rand(event, \"Solution\", ".$solution['id'].", this, \"viewitemSolution".$solution['id'].$rand."\")'";
         }
         echo ">";

         echo "<div class='boxnotefloatright right'>";
         echo "<div class='h_date'><i class='fa fa-clock-o'></i>".Html::convDateTime($solution['date_creation'])."</div>";
         if (!empty($solution['solutiontypes_id'])) {
            echo Dropdown::getDropdownName("glpi_solutiontypes", $solution['solutiontypes_id'])."<br>";
         }

         if (isset($solution['users_id_editor']) && $solution['users_id_editor'] > 0) {
            echo "<div class='users_id_editor' id='users_id_editor_".$solution['users_id_editor']."'>";
            $user->getFromDB($solution['users_id_editor']);
            $message = __('Last edited on %1$s by %2$s');
            if ($solution['is_rejected'] == 1) {
               $message = __('Rejected on %1$s by %2$s');
            }
            echo sprintf(
               $message,
               Html::convDateTime($solution['date_mod']),
               $user->getLink()
            );
            echo "</div>";
         }
         echo "</div>"; // boxnotefloatright

         $content = preg_replace_callback("/(&#[0-9]+;)/", function($m) {
            return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
         }, $solution['content']);
         $content = Toolbox::unclean_cross_side_scripting_deep($content);

         $content = autolink($content, 40);

         $long_text = "";
         if ((substr_count($content, "<br") > 30) || (strlen($content) > 2000)) {
            $long_text = " long_text";
         }

         echo "<div class='item_content$long_text'>";
         echo "<p>";

         if ($CFG_GLPI["use_rich_text"]) {
            echo html_entity_decode($content);
         } else {
            echo $content;
         }
         echo "</p>";

         if (!empty($long_text)) {
            echo "<p class='read_more'>";
            echo "<a class='read_more_button'>.....</a>";
            echo "</p>";
         }
         echo "</div>";

         echo "</div>"; // boxnotecontent
         echo "</div>"; // boxnote

         Plugin::doHook('post_show_item', ['item' => $this, 'options' => $options]);
      }

      $js = '';
      if ($can_add) {
         echo "<div class='ajax_box' id='viewitem" . $item->getID() . "$rand'></div>\n";
         $js .= "function viewAddSubitem" . $item->getID() . "$rand(itemtype) {\n";
         $params = [
            'type'                        => __CLASS__,
            'parenttype'                  => $item->getType(),
            $item->getForeignKeyField()   => $item->getID(),
            'id'                          => -1
         ];
         if (isset($_GET['load_kb_sol'])) {
            $params['load_kb_sol'] = $_GET['load_kb_sol'];
         }
         $out = Ajax::updateItemJsCode(
            "viewitem" . $item->getID() . "$rand",
            $CFG_GLPI["root_doc"]."/ajax/viewsubitem.php",
            $params,
            "",
            false
         );
         $js .= str_replace("\"itemtype\"", "itemtype", $out);
         $js .= "};";
      }
      if ($can_edit) {
         $js .= "function viewEditSubitem" . $item->getID() . "$rand(e, itemtype, items_id, o, domid) {
            domid = (typeof domid === 'undefined')
                        ? 'viewitem".$item->getID().$rand."'
                        : domid;
            console.log(domid);
            console.log($('#'+domid));
            var target = e.target || window.event.srcElement;
            if (target.nodeName == 'a') return;
            if (target.className == 'read_more_button') return;
            $('#'+domid).addClass('edited');
            $('#'+domid+' > *').hide();
            $('#'+domid+' .cancel_edit_item_content').show()
                                                      .click(function() {
                                                         $(this).hide();
                                                         $('#'+domid).removeClass('edited');
                                                         $('#'+domid+' .edit_item_content').empty().hide();
                                                         $('#'+domid+' .displayed_content').show();
                                                      });

            var _editor = $('<div class=\'edit_item_content\'></div>');
            $('#'+domid).append(_editor);
            $('#'+domid+' .edit_item_content').show()
                                                .load('".$CFG_GLPI["root_doc"]."/ajax/timeline.php',
                                                      {'action'    : 'viewsubitem',
                                                      'type'      : itemtype,
                                                      'parenttype': 'Ticket',
                                                      'tickets_id': ".$item->getID().",
                                                      'id'        : items_id
                                                      });


         };";
      }
      if ($js != '') {
         echo Html::scriptBlock($js);
      }
   }
}
