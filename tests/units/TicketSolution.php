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

namespace tests\units;

use \DbTestCase;

/* Test for inc/ticketsolution.class.php */

class TicketSolution extends DbTestCase {

   function testAddOrUpdateSolution() {

      //
      // 1st use-case
      // entity is auto-close: immediat
      // add of a solution
      //

      // login glpi
      $auth = new \Auth();
      $this->boolean((boolean)$auth->login('glpi', 'glpi', true))->isTrue();

      // change entity auto-closing delay for tickets
      // to be sure it's immediat
      $re = new \Entity();
      $this->boolean(
         (boolean)$re->update([
            'id' => 0, // the root entity
            'autoclose_delay' => 0
         ])
      )->isEqualto(true);

      // create ticket
      $ticket = $this->_createTicket();


      $this->boolean((boolean)$auth->login('tech', 'tech', true))->isTrue();
      $uid = getItemByTypeName('User', 'tech', true);

      // Ticket Solution
      $this->boolean(
         (boolean)$ticket->update([
            'id'   => $ticket->getID(),
            'solution'      => 'A simple solution from tech.'
         ])
      )->isEqualto(true);

      $tl = $ticket->getTimelineItems();

      // gets solution item
      $tli = array_shift($tl);
      $this->array($tli)
         ->string['type']->isEqualTo('TicketSolution');

      $this->array($tli['item'])
            ->string['tickets_id']->isEqualTo($ticket->getID())
            ->string['date_begin']->isEqualTo($_SESSION["glpi_currenttime"])
            ->string['users_id']->isEqualTo('4')
            ->string['solutiontemplates_id']->isEqualTo('0')
            ->string['solutiontypes_id']->isEqualTo('0')
            ->string['technical_solution']->isEqualTo('')
            ->string['users_id_approver']->isEqualTo('0')
            ->string['approval']->isEqualTo('3')
            ->string['date_answer']->isEqualTo($_SESSION["glpi_currenttime"])
            ->boolean['can_edit']->isFalse()
            ->string['content']->isEqualTo('A simple solution from tech.');

      $this->variable($tli['item']['approval_comment'])->isNull();


      //
      // 2nd use-case
      // entity is auto-close: 1 day
      // add of a solution
      // update it

      // change entity auto-closing delay for tickets
      // change to super-admin
      $this->boolean((boolean)$auth->login('glpi', 'glpi', true))->isTrue();
      $re = new \Entity();
      $this->boolean(
         (boolean)$re->update([
            'id' => 0, // the root entity
            'autoclose_delay' => 1
         ])
      )->isEqualto(true);



      // create ticket
      $ticket = $this->_createTicket();

      $this->boolean((boolean)$auth->login('tech', 'tech', true))->isTrue();
      $uid = getItemByTypeName('User', 'tech', true);

      // Ticket Solution
      $this->boolean(
         (boolean)$ticket->update([
            'id'   => $ticket->getID(),
            'solution'      => 'A simple solution from tech.'
         ])
      )->isEqualto(true);

      $tl = $ticket->getTimelineItems();

      // gets solution item
      $tli = array_shift($tl);
      $this->array($tli)
         ->string['type']->isEqualTo('TicketSolution');

      $this->array($tli['item'])
            ->string['tickets_id']->isEqualTo($ticket->getID())
            ->string['date_begin']->isEqualTo($_SESSION["glpi_currenttime"])
            ->string['users_id']->isEqualTo('4')
            ->string['solutiontemplates_id']->isEqualTo('0')
            ->string['solutiontypes_id']->isEqualTo('0')
            ->string['technical_solution']->isEqualTo('')
            ->string['users_id_approver']->isEqualTo('0')
            ->string['approval']->isEqualTo('1')
            ->boolean['can_edit']->isTrue()
            ->string['content']->isEqualTo('A simple solution from tech.');

      $this->variable($tli['item']['date_answer'])->isNull();
      $this->variable($tli['item']['approval_comment'])->isNull();

      $this->boolean((boolean)$auth->login('tech', 'tech', true))->isTrue();

      // try to update the current solution
      $this->boolean(
         (boolean)$ticket->update([
            'id'   => $ticket->getID(),
            'solution'      => 'A simple solution 2 from tech.'
         ])
      )->isEqualto(true);

      $tl = $ticket->getTimelineItems();

      // gets solution item
      $tli = array_shift($tl);
      $this->array($tli)
         ->string['type']->isEqualTo('TicketSolution');

      $this->array($tli['item'])
            ->string['tickets_id']->isEqualTo($ticket->getID())
            ->string['date_begin']->isEqualTo($_SESSION["glpi_currenttime"])
            ->string['users_id']->isEqualTo('4')
            ->string['solutiontemplates_id']->isEqualTo('0')
            ->string['solutiontypes_id']->isEqualTo('0')
            ->string['technical_solution']->isEqualTo('')
            ->string['users_id_approver']->isEqualTo('0')
            ->string['approval']->isEqualTo('1')
            ->boolean['can_edit']->isTrue()
            ->string['content']->isEqualTo('A simple solution 2 from tech.');

      $this->variable($tli['item']['date_answer'])->isNull();
      $this->variable($tli['item']['approval_comment'])->isNull();

      $date_begin1 = $_SESSION["glpi_currenttime"];

      // refuse solution
      // change to post-only
      $this->boolean((boolean)$auth->login('post-only', 'postonly', true))->isTrue();

      // create a followup to refused this solution
      $fup = new \TicketFollowup();
      $fup->add(['content' => 'Refused this solution', 'tickets_id' => $ticket->getID(), 'add_reopen' => 'Reject Solution', 'requesttypes_id' => 0]);

      $tl = $ticket->getTimelineItems();

      // gets solution item
      array_shift($tl);
      $tli = array_shift($tl);
      $this->array($tli)
         ->string['type']->isEqualTo('TicketSolution');

      $this->array($tli['item'])
            ->string['tickets_id']->isEqualTo($ticket->getID())
            ->string['date_begin']->isEqualTo($date_begin1)
            ->string['users_id']->isEqualTo('4')
            ->string['solutiontemplates_id']->isEqualTo('0')
            ->string['solutiontypes_id']->isEqualTo('0')
            ->string['technical_solution']->isEqualTo('')
            ->string['users_id_approver']->isEqualTo('3')
            ->string['approval']->isEqualTo('2')
            ->string['approval_comment']->isEqualTo('Refused this solution')
            ->string['date_answer']->isEqualTo($_SESSION["glpi_currenttime"])
            ->boolean['can_edit']->isFalse()
            ->string['content']->isEqualTo('A simple solution 2 from tech.');


      // add a new solution by tech
      $this->boolean((boolean)$auth->login('tech', 'tech', true))->isTrue();

      // add a solution
      $this->boolean(
         (boolean)$ticket->update([
            'id'   => $ticket->getID(),
            'solution'      => 'A complex solution from tech.'
         ])
      )->isEqualto(true);

      $date_begin2 = $_SESSION["glpi_currenttime"];

      // approve solution
      // change to post-only
      $this->boolean((boolean)$auth->login('post-only', 'postonly', true))->isTrue();

      // create a followup to approve this solution
      $fup = new \TicketFollowup();
      $fup->add(['content' => 'Approved this solution', 'tickets_id' => $ticket->getID(), 'add_close' => 'Approve Solution', 'requesttypes_id' => 0]);

      $tl = $ticket->getTimelineItems();

      // gets solution item
      array_shift($tl);
      $tli = array_shift($tl);
      $this->array($tli)
         ->string['type']->isEqualTo('TicketSolution');

      $this->array($tli['item'])
            ->string['tickets_id']->isEqualTo($ticket->getID())
            ->string['date_begin']->isEqualTo($date_begin2)
            ->string['users_id']->isEqualTo('4')
            ->string['solutiontemplates_id']->isEqualTo('0')
            ->string['solutiontypes_id']->isEqualTo('0')
            ->string['technical_solution']->isEqualTo('')
            ->string['users_id_approver']->isEqualTo('3')
            ->string['approval']->isEqualTo('3')
            ->string['approval_comment']->isEqualTo('Approved this solution')
            ->string['date_answer']->isEqualTo($_SESSION["glpi_currenttime"])
            ->boolean['can_edit']->isFalse()
            ->string['content']->isEqualTo('A complex solution from tech.');

      // change entity auto-closing back to immediat for tickets
      // change to super-admin
      $this->boolean((boolean)$auth->login('glpi', 'glpi', true))->isTrue();
      $re = new \Entity();
      $this->boolean(
         (boolean)$re->update([
            'id' => 0, // the root entity
            'autoclose_delay' => 0
         ])
      )->isEqualto(true);


      // let's have a look at the html
      ob_start();
      \TicketSolution::displayTabContentForItem($ticket);
      $ret = ob_get_clean();
      $date_begin1 = substr($date_begin1, 0, strrpos($date_begin1, ':'));
      $regex="@^<table class='tab_cadre_fixe'><tr class='tab_bg_2'><th class='b'><h3>Solution history</h3></th></tr></table><h3>Today</h3><div class='boxnote bytech' id='viewsolution[0-9]+'><div class='boxnoteleft'><img class='user_picture_verysmall' alt=\"Picture\" src='/glpi/pics/picture_min\\.png'></div><div class='boxnotecontent'><div class='boxnotefloatright'>Created by <a title=\"tech\" href='/glpi/front/user\\.form\\.php\\?id=4'>tech</a> on $date_begin1</div><div class='boxnotetext '>A simple solution 2 from tech\\.</div></div><div class='boxnoteright'></div></div>$@";
      $this->string($ret)->match($regex);
      echo '';
   }


   function testDisplayTabContentForItem() {
      // login glpi
      $auth = new \Auth();
      $this->boolean((boolean)$auth->login('glpi', 'glpi', true))->isTrue();

      // create ticket
      $ticket = $this->_createTicket();

      ob_start();
      $this->boolean(\TicketSolution::displayTabContentForItem($ticket))->isTrue();
      $ret = ob_get_clean();
      $regex="@^<table class='tab_cadre_fixe'><tr class='tab_bg_2'><th class='b'>No solution for this ticket\\.</th></tr></table>$@";
      $this->string($ret)->match($regex);


   }

   function _createTicket() {
      // create ticket
      // with post-only as requester
      // tech as assigned to
      // normal as observer
      $ticket = new \Ticket();
      $this->integer((int)$ticket->add([
            'name'                => 'ticket title',
            'description'         => 'a description',
            'content'             => '',
            '_users_id_requester' => '3', // post-only
            '_users_id_observer'  => '5', // normal
            '_users_id_assign'    => ['4', '5'] // tech and normal
      ] ))->isGreaterThan(0);
      return $ticket;
   }
}
