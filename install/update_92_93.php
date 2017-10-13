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

/**
 * Update from 9.2 to 9.3
 *
 * @return bool for success (will die for most error)
**/
function update92to93() {
   global $DB, $migration, $CFG_GLPI;
   $dbutils = new DbUtils();

   $current_config   = Config::getConfigurationValues('core');
   $updateresult     = true;
   $ADDTODISPLAYPREF = [];

   //TRANS: %s is the number of new version
   $migration->displayTitle(sprintf(__('Update to %s'), '9.3'));
   $migration->setVersion('9.3');

   //Create solutions table
   if (!$DB->tableExists('glpi_itilsolutions')) {
      $query = "CREATE TABLE `glpi_itilsolutions` (
         `id` int(11) NOT NULL AUTO_INCREMENT,
         `itemtype` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
         `items_id` int(11) NOT NULL DEFAULT '0',
         `solutiontypes_id` int(11) NOT NULL DEFAULT '0',
         `content` longtext COLLATE utf8_unicode_ci,
         `date_creation` datetime DEFAULT NULL,
         `date_mod` datetime DEFAULT NULL,
         `date_approval` datetime DEFAULT NULL,
         `users_id` int(11) NOT NULL DEFAULT '0',
         `users_id_editor` int(11) NOT NULL DEFAULT '0',
         `users_id_approval` int(11) NOT NULL DEFAULT '0',
         `status` int(11) NOT NULL DEFAULT '1',
         `ticketfollowups_id` int(11) DEFAULT NULL  COMMENT 'Followup reference on reject or approve a ticket solution',
         PRIMARY KEY (`id`),
         KEY `itemtype` (`itemtype`),
         KEY `item_id` (`items_id`),
         KEY `item` (`itemtype`,`items_id`),
         KEY `solutiontypes_id` (`solutiontypes_id`),
         KEY `users_id` (`users_id`),
         KEY `users_id_editor` (`users_id_editor`),
         KEY `users_id_approval` (`users_id_approval`),
         KEY `status` (`status`),
         KEY `ticketfollowups_id` (`ticketfollowups_id`)
         ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
      $DB->queryOrDie($query, "9.3 add table glpi_itilsolutions");
   }

   //Migrate to new  solutions
   $solutions_itemtypes = [
      'Ticket',
      'Change',
      'Problem'
   ];
   $solution = new ITILSolution();
   foreach ($solutions_itemtypes as $itemtype) {
      $table = $dbutils->getTableForItemType($itemtype);
      if ($DB->fieldExists($table, 'solution')) {
         $iterator = $DB->request(
            $dbutils->getTableForItemType($itemtype), [
               'WHERE' => [
                  'NOT' => ['solution' => null]
               ]
            ]
         );
         while ($old_solution = $iterator->next()) {
            $logs_iterator = $DB->request([
               'FROM'   => 'glpi_logs',
               'FIELDS' => [
                  'date_mod',
                  'user_name',
                  'id'
               ],
               'WHERE'  => [
                  'itemtype'           => $itemtype,
                  'items_id'           => $old_solution['id'],
                  'id_search_option'   => 24
               ],
               'ORDER'  => ['id DESC'],
               'START'  => 0,
               'LIMIT'  => 1
            ]);
            $log_result = $logs_iterator->next();
            $users_id = preg_replace(
               "/.*\(([0-9]+)\)/",
               "$1",
               $log_result['user_name']
            );

            $solution->add([
               'itemtype'           => $itemtype,
               'items_id'           => $old_solution['id'],
               'solutiontypes_id'   => $old_solution['solutiontypes_id'],
               'content'            => $old_solution['solution'],
               'is_rejected'        => 0,
               'users_id'           => $users_id,
               'date_creation'      => $log_result['date_mod'],
               'date_mod'           => $log_result['date_mod']
            ]);
         }

         //Drop old solutions fields
         $migration->dropField($table, 'solution');
         $migration->dropKey($table, 'solutiontypes_id');
         $migration->dropField($table, 'solutiontypes_id');
      }
   }

   // ************ Keep it at the end **************
   $migration->executeMigration();

   return $updateresult;
}
