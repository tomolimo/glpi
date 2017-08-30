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

// Generic test classe, to be extended for CommonDBTM Object

class DbTestCase extends atoum {
   private $int;
   private $str;

   public function setUp() {
      global $DB;

      // Need Innodb -- $DB->begin_transaction() -- workaround:
      $DB->objcreated = [];

      // By default, no session, not connected
      $_SESSION = [
         'glpi_use_mode'         => Session::NORMAL_MODE,
         'glpi_currenttime'      => date("Y-m-d H:i:s"),
         'glpiis_ids_visible'    => 0,
         'glpiticket_timeline'   => 1
      ];
   }


   public function afterTestMethod($method) {
      global $DB;

      // Cleanup log directory
      foreach (glob(GLPI_LOG_DIR . '/*.log') as $file) {
         if (file_exists($file)) {
            unlink($file);
         }
      }

      // Need Innodb -- $DB->rollback()  -- workaround:
      foreach ($DB->objcreated as $table => $ids) {
         foreach ($ids as $id) {
            $DB->query($q = "DELETE FROM `$table` WHERE `id`=$id");
         }
      }
      unset($DB->objcreated);
   }


   /**
    * Connect using the test user
    */
   protected function login($user_name = TU_USER, $user_pass = TU_PASS) {

      $auth = new Auth();
      if (!$auth->login($user_name, $user_pass, true)) {
         $this->markTestSkipped('No login');
      }
   }

   /**
    * Get a unique random string
    */
   protected function getUniqueString() {
      if (is_null($this->str)) {
         return $this->str = uniqid('str');
      }
      return $this->str .= 'x';
   }

   /**
    * Get a unique random integer
    */
   protected function getUniqueInteger() {
      if (is_null($this->int)) {
         return $this->int = mt_rand(1000, 10000);
      }
      return $this->int++;
   }

   /**
    * change current entity
    */
   protected function setEntity($entityname, $subtree) {
      $res = Session::changeActiveEntities(getItemByTypeName('Entity', $entityname, true), $subtree);
      $this->boolean($res)->isTrue();
   }

   /**
    * Generic method to test if an added object is corretly inserted
    *
    * @param  Object $object The object to test
    * @param  int    $id     The id of added object
    * @param  array  $input  the input used for add object (optionnal)
    *
    * @return nothing (do tests)
    */
   protected function checkInput(CommonDBTM $object, $id = 0, $input = []) {
      $this->integer((int)$id)->isGreaterThan(0);
      $this->boolean($object->getFromDB($id))->isTrue();
      $this->variable($object->getField('id'))->isEqualTo($id);

      if (count($input)) {
         foreach ($input as $k => $v) {
            $this->variable($object->getField($k))->isEqualTo($v);
         }
      }
   }
}
