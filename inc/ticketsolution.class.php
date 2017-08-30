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

/** @file
 * @brief
 */

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

/**
 * TicketSolution Class
 * @since 9.2
 **/
class TicketSolution extends CommonDBTM {

   const SOLUTION_WAITING  = 1;
   const SOLUTION_REJECTED = 2;
   const SOLUTION_APPROVED = 3;

   static $rightname = 'ticket';


   /**
    * Summary of updateSolution
    * @param TicketFollowup $fup
    * @param mixed $status
    * @since 9.2
    */
   private static function updateSolution($fup, $status) {
      $tkt_sol = new self;
      $sols = $tkt_sol->find("tickets_id = ".$fup->input["_job"]->fields['id']." AND approval = ".self::SOLUTION_WAITING);
      if (count($sols)) {
         $sol = array_shift($sols);
         $sol['date_answer'] = $_SESSION["glpi_currenttime"];
         $sol['users_id_approver'] = $fup->input["users_id"];
         $sol['approval_comment'] = $fup->input["content"];
         $sol['approval'] = $status;
         $tkt_sol->update($sol);
      }
   }


   /**
    * Summary of approveSolution
    * @param mixed $fup
    * @since 9.2
    */
   static function approveSolution($fup) {
      self::updateSolution($fup, self::SOLUTION_APPROVED);
   }


   /**
    * Summary of rejectSolution
    * @param mixed $fup
    * @since 9.2
    */
   static function rejectSolution($fup) {
      self::updateSolution($fup, self::SOLUTION_REJECTED);
   }


   /**
    * Summary of addOrUpdateSolution
    * @param Ticket $item
    * @since 9.2
    */
   static function addOrUpdateSolution($item) {
      $tkt_sol = new self;

      $sol_input = [];
      $sol_input['tickets_id'] = $item->getID();
      $sol_input['date_begin'] = $_SESSION["glpi_currenttime"];

      // by default
      $sol_input['approval'] = self::SOLUTION_WAITING;
      if ($item->fields['status'] == CommonITILObject::CLOSED) {
         $sol_input['date_answer'] = $_SESSION["glpi_currenttime"];
         $sol_input['approval'] = self::SOLUTION_APPROVED;
      }

      $sol_input['users_id'] = $item->input['users_id_lastupdater'];
      $sol_input['solutiontemplates_id'] = isset($item->input['solutiontemplates_id'])?$item->input['solutiontemplates_id']:0;
      $sol_input['solutiontypes_id'] = isset($item->input['solutiontypes_id'])?$item->input['solutiontypes_id']:0;
      $sol_input['solution'] = $item->input['solution'];
      $sol_input['technical_solution'] = ''; //$item->input['technical_solution'];
      //$sol_input['timeline_position'] = $item->getTimelinePosition(__CLASS__, $item->input['users_id_lastupdater']);
      $sol_input['users_id_approver'] = 0;

      // must check if it exists already and if yes, must update it
      $sols = $tkt_sol->find("tickets_id = ".$item->getID()." AND approval = ".self::SOLUTION_WAITING);
      if (count($sols)) {
         $sol = array_merge(array_shift($sols), $sol_input);
         $tkt_sol->update($sol);
      } else {
         $tkt_sol->add( $sol_input );
      }
   }

   /**
    * Summary of displayTabContentForItem
    * @param CommonGLPI $item
    * @param mixed $tabnum
    * @param mixed $withtemplate
    * @return boolean
    * @since 9.2
    */
   static function displayTabContentForItem(CommonGLPI $item, $tabnum=1, $withtemplate=0) {

      $sol = new self();
      $sol->showSummary($item);
      return true;
   }

   /**
    * Show the current ticketfollowup summary
    *
    * @param $ticket Ticket object
    * @since 9.2
    **/
   function showSummary($ticket) {
      global $DB, $CFG_GLPI;

      $tID = $ticket->fields['id'];

      $showuserlink = 0;
      // Display existing Solutions
      if (User::canView()) {
         $showuserlink = 1;
      }
      $techs = $ticket->getAllUsers(CommonITILActor::ASSIGN);

      $RESTRICT = "";

      $query = "SELECT `glpi_ticketsolutions`.*, `glpi_users`.`picture`
                FROM `glpi_ticketsolutions`
                LEFT JOIN `glpi_users` ON (`glpi_ticketsolutions`.`users_id` = `glpi_users`.`id`)
                WHERE `tickets_id` = '$tID'
                      $RESTRICT
                ORDER BY `date_begin` DESC";
      $result = $DB->query($query);

      $rand   = mt_rand();

      echo "<table class='tab_cadre_fixe'><tr class='tab_bg_2'>";
      if ($DB->numrows($result) == 0) {
         echo "<th class='b'>" . __('No solution history for this ticket.')."</th></tr></table>";
      } else {
         echo "<th class='b'><h3>" . __('Solution history')."</h3></th></tr></table>";

         $today          = strtotime('today');
         $lastmonday     = strtotime('last monday');
         $lastlastmonday = strtotime('last monday', strtotime('last monday'));
         // Case of monday
         if (($today-$lastmonday)==7*DAY_TIMESTAMP) {
            $lastlastmonday = $lastmonday;
            $lastmonday = $today;
         }

         $steps = array(0 => array('end'   => $today,
                                   'name'  => __('Today')),
                        1 => array('end'   => $lastmonday,
                                   'name'  => __('This week')),
                        2 => array('end'   => $lastlastmonday,
                                   'name'  => __('Last week')),
                        3 => array('end'   => strtotime('midnight first day of'),
                                   'name'  => __('This month')),
                        4 => array('end'   => strtotime('midnight first day of last month'),
                                   'name'  => __('Last month')),
                        5 => array('end'   => 0,
                                   'name'  => __('Before the last month')),
                       );
         $currentpos = -1;
         $data = $DB->fetch_assoc($result); // to skip latest one if any
         while ($data = $DB->fetch_assoc($result)) {
            $this->getFromDB($data['id']);
            $options = array( 'parent' => $ticket,
                              'rand'   => $rand
                           );
            Plugin::doHook('pre_show_item', array('item' => $this, 'options' => &$options));
            $data = array_merge( $data, $this->fields );

            $time      = strtotime($data['date_begin']);
            if (!isset($steps[$currentpos])
                || ($steps[$currentpos]['end'] > $time)) {
               $currentpos++;
               while (($steps[$currentpos]['end'] > $time) && isset($steps[$currentpos+1])) {
                  $currentpos++;
               }
               if (isset($steps[$currentpos])) {
                  echo "<h3>".$steps[$currentpos]['name']."</h3>";
               }
            }

            $id = 'solution'.$data['id'].$rand;

            $color = 'byuser';
            if (isset($techs[$data['users_id']])) {
               $color = 'bytech';
            }

            $classtoadd = '';

            echo "<div class='boxnote $color' id='view$id'>";

            echo "<div class='boxnoteleft'>";
            echo "<img class='user_picture_verysmall' alt=\"".__s('Picture')."\" src='".
               User::getThumbnailURLForPicture($data['picture'])."'>";
            echo "</div>"; // boxnoteleft

            echo "<div class='boxnotecontent'";
            echo ">";

            echo "<div class='boxnotefloatright'>";
            $username = NOT_AVAILABLE;
            if ($data['users_id']) {
               $username = getUserName($data['users_id'], $showuserlink);
            }
            $name = sprintf(__('Created by %1$s on %2$s'), $username,
                              Html::convDateTime($data['date_begin']));
            if ($data['solutiontypes_id']) {
               $name = sprintf(__('%1$s - %2$s'), $name,
                         Dropdown::getDropdownName('glpi_solutiontypes',
                                                   $data['solutiontypes_id']));
            }
            echo $name;
            echo "</div>"; // floatright

            echo "<div class='boxnotetext $classtoadd'";
            echo ">";
            $content = Toolbox::unclean_cross_side_scripting_deep($data['solution']);
            echo $content.'</div>'; // boxnotetext

            echo "</div>"; // boxnotecontent
            echo "<div class='boxnoteright'>";
            echo "</div>"; // boxnoteright
            echo "</div>"; // boxnote
            Plugin::doHook('post_show_item', array('item' => $this, 'options' => $options));
         }
      }
   }

   /**
    * Summary of showForm
    * @param mixed $ID
    * @param mixed $options
    * @since 9.2
    */
   function showForm($ID, $options = []) {
      if (!isset($_GET['load_kb_sol'])) {
         $_GET['load_kb_sol'] = 0;
      }
      $options['parent']->showSolutionForm($_GET['load_kb_sol']);
   }

}