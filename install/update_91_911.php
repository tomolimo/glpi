<?php
/*
 -------------------------------------------------------------------------
 GLPI - Gestionnaire Libre de Parc Informatique
 Copyright (C) 2015-2016 Teclib'.

 http://glpi-project.org

 based on GLPI - Gestionnaire Libre de Parc Informatique
 Copyright (C) 2003-2014 by the INDEPNET Development Team.

 -------------------------------------------------------------------------

 LICENSE

 This file is part of GLPI.

 GLPI is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 GLPI is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with GLPI. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

/** @file
* @brief
*/

/**
 * Update from 9.1 to 9.1.1
 *
 * @return bool for success (will die for most error)
**/
function update91to911() {
   global $DB, $migration, $CFG_GLPI;

   $updateresult     = true;

   //TRANS: %s is the number of new version
   $migration->displayTitle(sprintf(__('Update to %s'), '9.1.1'));
   $migration->setVersion('9.1.1');


   $migration->addField("glpi_tickettasks", "timeline_position", "integer", array('value' => 0));
   $migration->addField("glpi_ticketfollowups", "timeline_position", "integer", array('value' => 0));
   $migration->addField("glpi_ticketvalidations", "timeline_position", "integer", array('value' => 0));
   $migration->addField("glpi_documents_items", "timeline_position", "integer", array('value' => 0));


   // ************ Keep it at the end **************
   $migration->executeMigration();

   // Needs to update this newly created field for existing tasks and existing followups
   // rules to be used for computed timeline_position
   // same rule for tasks or followups
   // timeline_position is set to left (== 1) by default
   // timeline_position is set to middle (== 2) when users_id of the item is watcher on the ticket
   // timeline_position is set to right (== 3) when users_id of the item is not requester of the ticket
   $tables_to_update = array( 'glpi_tickettasks', 'glpi_ticketfollowups', 'glpi_ticketvalidations' ) ;
   foreach( $tables_to_update as $table ){
      $DB->query("UPDATE $table
                   JOIN glpi_tickets_users ON glpi_tickets_users.tickets_id=$table.tickets_id AND glpi_tickets_users.users_id=$table.users_id
                   SET $table.timeline_position = IF( glpi_tickets_users.`type` = ".CommonITILActor::REQUESTER.", ".Ticket::TIMELINE_LEFT.",
                                                      IF( glpi_tickets_users.`type` = ".CommonITILActor::OBSERVER.", ".Ticket::TIMELINE_LEFT.", ".Ticket::TIMELINE_RIGHT."))
                   WHERE $table.timeline_position = 0 ;") ;
      // the one who are not in ticket users list
      $DB->query("UPDATE $table SET $table.timeline_position = ".Ticket::TIMELINE_RIGHT." WHERE $table.timeline_position = 0 ;");
   }

   // for the glpi_documents_items table
   $DB->query("UPDATE glpi_documents_items
               JOIN glpi_documents ON glpi_documents.id=glpi_documents_items.documents_id
               JOIN glpi_tickets_users ON glpi_tickets_users.tickets_id=glpi_documents_items.items_id AND glpi_documents_items.itemtype='Ticket' AND glpi_tickets_users.users_id=glpi_documents.users_id
               SET glpi_documents_items.timeline_position = IF(glpi_tickets_users.`type` = ".CommonITILActor::REQUESTER.", ".Ticket::TIMELINE_LEFT.",
                                                               IF(glpi_tickets_users.`type` = ".CommonITILActor::OBSERVER.", ".Ticket::TIMELINE_LEFT.", ".Ticket::TIMELINE_RIGHT."))
               WHERE glpi_documents_items.timeline_position = 0 ; " ) ;
   // the one who are not in ticket users list
   $DB->query("UPDATE glpi_documents_items SET glpi_documents_items.timeline_position = ".Ticket::TIMELINE_RIGHT." WHERE glpi_documents_items.timeline_position = 0 AND glpi_documents_items.itemtype='Ticket';");

   //$tkt = new Ticket;
   //foreach( $tables_to_update as $table ) {
   //   $type = getItemTypeForTable( $table );
   //   $item = new $type ;
   //   foreach( $DB->request( $table, "timeline_position = 0" ) as $row ) {
   //      $item->getFromDB( $row['id'] );
   //      $position = 0;
   //      $tkt->getFromDB( $item->fields['tickets_id'] ) ;
   //      if( $tkt->isUser( CommonITILActor::OBSERVER, $item->fields['users_id'] ) ) {
   //         $position = Ticket::TIMELINE_MIDDLE;
   //      } else if ($tkt->isUser( CommonITILActor::REQUESTER, $item->fields['users_id'] ) ) {
   //         $position = Ticket::TIMELINE_LEFT;
   //      }
   //      if( $position ) {
   //         $DB->query( "UPDATE $table SET `timeline_position` = $position WHERE `id` = ".$row['id'] ) or die( "Can't update $table with id = ".$row['id'] ) ;
   //      }
   //   }
   //}


   return $updateresult;
}
