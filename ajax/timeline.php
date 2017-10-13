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
include ('../inc/includes.php');
header("Content-Type: text/html; charset=UTF-8");

Session::checkLoginUser();

if (!isset($_REQUEST['action'])) {
   exit;
}

switch ($_REQUEST['action']) {
   case "change_task_state":
      if (!isset($_REQUEST['tasks_id'])) {
         exit();
      }
      $task = new TicketTask;
      $task->getFromDB(intval($_REQUEST['tasks_id']));
      if (!in_array($task->fields['state'], [0, Planning::INFO])) {
         echo $new_state = ($task->fields['state'] == Planning::DONE)
                              ? Planning::TODO
                              : Planning::DONE;
         $task->update(['id'         => intval($_REQUEST['tasks_id']),
                             'tickets_id' => intval($_REQUEST['tickets_id']),
                             'state'      => $new_state]);
      }
      break;
   case "viewsubitem":
      Html::header_nocache();
      if (!isset($_REQUEST['type'])) {
         exit();
      }
      if (!isset($_REQUEST['parenttype'])) {
         exit();
      }

      if ($_REQUEST['type'] == "Solution") {
         $ticket = new Ticket;
         $ticket->getFromDB($_REQUEST["tickets_id"]);

         if (!isset($_REQUEST['load_kb_sol'])) {
            $_REQUEST['load_kb_sol'] = 0;
         }

         $sol_params = [
            'item'         => $ticket,
            'kb_id_toload' => $_REQUEST['load_kb_sol']
         ];

         $solution = new Solution();
         $solution->showForm(null, $sol_params);

         // show approbation form on top when ticket is solved
         if ($ticket->fields["status"] == CommonITILObject::SOLVED) {
            echo "<div class='approbation_form'>";
            $followup_obj = new TicketFollowup();
            $followup_obj->showApprobationForm($ticket);
            echo "</div>";
         }
      } else if (($item = getItemForItemtype($_REQUEST['type']))
          && ($parent = getItemForItemtype($_REQUEST['parenttype']))) {
         if (isset($_REQUEST[$parent->getForeignKeyField()])
             && isset($_REQUEST["id"])
             && $parent->getFromDB($_REQUEST[$parent->getForeignKeyField()])) {

            $ol = ObjectLock::isLocked( $_REQUEST['parenttype'], $parent->getID() );
            if ($ol && (Session::getLoginUserID() != $ol->fields['users_id'])) {
               ObjectLock::setReadOnlyProfile( );
            }

            Ticket::showSubForm($item, $_REQUEST["id"], ['parent' => $parent,
                                                                        'tickets_id' => $_REQUEST["tickets_id"]]);
         } else {
            echo __('Access denied');
         }
      }
      Html::ajaxFooter();
      break;
}
