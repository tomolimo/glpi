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

   function _addSolution2Ticket($tkt, $sol_txt) {

      $this->login('tech', 'tech');
      // Ticket Solution
      $this->boolean(
         (boolean)$tkt->update([
            'id'        => $tkt->getID(),
            'solution'  => $sol_txt
         ])
      )->isEqualto(true);

      $tl = $tkt->getTimelineItems();

      // gets solution item
      $tli = array_shift($tl);
      $this->array($tli)
         ->string['type']->isEqualTo('TicketSolution');

      $this->array($tli['item'])
            ->string['tickets_id']->isEqualTo($tkt->getID())
            ->string['date_begin']->isEqualTo($_SESSION["glpi_currenttime"])
            ->string['users_id']->isEqualTo('4')
            ->string['solutiontemplates_id']->isEqualTo('0')
            ->string['solutiontypes_id']->isEqualTo('0')
            ->string['technical_solution']->isEqualTo('')
            ->string['users_id_approver']->isEqualTo('0')
            ->string['approval']->isEqualTo('3')
            ->string['date_answer']->isEqualTo($_SESSION["glpi_currenttime"])
            ->boolean['can_edit']->isFalse()
            ->string['content']->isEqualTo($sol_txt);

      $this->variable($tli['item']['approval_comment'])->isNull();
   }


   function _addUpdateSolution2Ticket($tkt, $sol_txt) {

      $this->login('tech', 'tech');
      // Ticket Solution
      $this->boolean(
         (boolean)$tkt->update([
            'id'        => $tkt->getID(),
            'solution'  => $sol_txt
         ])
      )->isEqualto(true);

      $tl = $tkt->getTimelineItems();

      // gets solution item
      $tli = array_shift($tl);
      $this->array($tli)
         ->string['type']->isEqualTo('TicketSolution');

      $this->array($tli['item'])
            ->string['tickets_id']->isEqualTo($tkt->getID())
            ->string['date_begin']->isEqualTo($_SESSION["glpi_currenttime"])
            ->string['users_id']->isEqualTo('4')
            ->string['solutiontemplates_id']->isEqualTo('0')
            ->string['solutiontypes_id']->isEqualTo('0')
            ->string['technical_solution']->isEqualTo('')
            ->string['users_id_approver']->isEqualTo('0')
            ->string['approval']->isEqualTo('1')
            ->boolean['can_edit']->isTrue()
            ->string['content']->isEqualTo($sol_txt);

      $this->variable($tli['item']['date_answer'])->isNull();
      $this->variable($tli['item']['approval_comment'])->isNull();
   }


   function testAddOrUpdateSolution() {

      //
      // 1st use-case
      // entity is auto-close: immediat
      // add of a solution
      //
      // create ticket
      $ticket = $this->_createTicket();

      $this->_addSolution2Ticket($ticket, 'A simple solution 1 from tech.');


      //
      // 2nd use-case
      // entity is auto-close: 1 day
      // add of a solution
      // update it
      //
      // create ticket
      $ticket = $this->_createTicket();

      // change entity auto-close delay for tickets
      $re = new \Entity();
      $this->boolean(
         (boolean)$re->update([
            'id' => $ticket->fields['entities_id'],
            'autoclose_delay' => 1
         ])
      )->isEqualto(true);

      $this->_addUpdateSolution2Ticket($ticket, 'A simple solution 1 from tech.');

      $this->_addUpdateSolution2Ticket($ticket, 'A simple solution 2 from tech.');

   }


   function testDisplayTabContentForItem() {

      // create ticket
      $ticket = $this->_createTicket();

      // test an empty solution ticket
      ob_start();
      $this->boolean(\TicketSolution::displayTabContentForItem($ticket))->isTrue();
      $ret = ob_get_clean();
      $regex="@^<table class='tab_cadre_fixe'><tr class='tab_bg_2'><th class='b'>No solution history for this ticket\\.</th></tr></table>$@";
      $this->string($ret)->match($regex);

      // test a ticket with two solutions (a rejected one and an approved one)
      // change entity auto-close delay for tickets
      $re = new \Entity();
      $this->boolean(
         (boolean)$re->update([
            'id' => $ticket->fields['entities_id'],
            'autoclose_delay' => 1
         ])
      )->isEqualto(true);

      $this->_addUpdateSolution2Ticket($ticket, 'A simple solution 1 from tech.');
      $date_first_solution = substr($_SESSION["glpi_currenttime"], 0, strrpos($_SESSION["glpi_currenttime"], ':'));

      $this->_rejectOrAcceptSolution($ticket, '2', 'Refused this solution', 'A simple solution 1 from tech.');

      $this->_addUpdateSolution2Ticket($ticket, 'A complex solution from tech.');

      $this->_rejectOrAcceptSolution($ticket, '3', 'Approved this solution', 'A complex solution from tech.');

      $this->login();

      // let's have a look at the html
      ob_start();
      $this->boolean(\TicketSolution::displayTabContentForItem($ticket))->isTrue();
      $ret = ob_get_clean();
      $regex="@^<table class='tab_cadre_fixe'><tr class='tab_bg_2'><th class='b'><h3>Solution history</h3></th></tr></table><h3>Today</h3><div class='boxnote bytech' id='viewsolution[0-9]+'><div class='boxnoteleft'><img class='user_picture_verysmall' alt=\"Picture\" src='/glpi/pics/picture_min\\.png'></div><div class='boxnotecontent'><div class='boxnotefloatright'>Created by <a title=\"tech\" href='/glpi/front/user\\.form\\.php\\?id=4'>tech</a> on $date_first_solution</div><div class='boxnotetext '>A simple solution 1 from tech\\.</div></div><div class='boxnoteright'></div></div>$@";
      $this->string($ret)->match($regex);

   }


   function _createTicket() {

      // login TU_USER
      $this->login();

      //add entity
      $re = new \Entity();
      $this->boolean(
         (boolean)$re->add([
            'name' => 'multi-solutions entity '.rand(),
            'entities_id' => '0'
         ])
      )->isEqualto(true);

      // create ticket
      // with post-only as requester
      // tech as assigned to
      // normal as observer
      $ticket = new \Ticket();
      $this->integer((int)$ticket->add([
            'name'                => 'ticket title',
            'description'         => 'a description',
            'content'             => '',
            'entities_id'         => $re->getID(),
            '_users_id_requester' => '3', // post-only
            '_users_id_observer'  => '5', // normal
            '_users_id_assign'    => ['4', '5'] // tech and normal
      ] ))->isGreaterThan(0);

      return $ticket;

   }


   function _rejectOrAcceptSolution($ticket, $status, $fu_content, $txt_sol) {

      //
      // refuse solution
      //
      // change to post-only
      $this->login('post-only', 'postonly');

      // create a followup to refused this solution
      $fup = new \TicketFollowup();
      if ($status == '2') {
         $options = ['add_reopen' => 'Reject'];
      } else {
         $options = ['add_close' => 'Approve'];
      }
      $options = array_merge( $options, ['content' => $fu_content, 'tickets_id' => $ticket->getID(),  'requesttypes_id' => 0]);
      $fup->add($options);

      $tl = $ticket->getTimelineItems();

      // gets latest solution item
      $tl = array_filter($tl, function($var) { return $var['type'] == 'TicketSolution'; } );
      $tli = array_shift($tl);
      $this->array($tli)
         ->string['type']->isEqualTo('TicketSolution');

      $this->array($tli['item'])
            ->string['tickets_id']->isEqualTo($ticket->getID())
            ->string['users_id']->isEqualTo('4')
            ->string['solutiontemplates_id']->isEqualTo('0')
            ->string['solutiontypes_id']->isEqualTo('0')
            ->string['technical_solution']->isEqualTo('')
            ->string['users_id_approver']->isEqualTo('3')
            ->string['approval']->isEqualTo($status)
            ->string['approval_comment']->isEqualTo($fu_content)
            ->string['date_answer']->isEqualTo($_SESSION["glpi_currenttime"])
            ->boolean['can_edit']->isFalse()
            ->string['content']->isEqualTo($txt_sol);
   }


   function testRejectAcceptSolution() {

      $ticket = $this->_createTicket();

      // change entity auto-close delay for tickets
      $re = new \Entity();
      $this->boolean(
         (boolean)$re->update([
            'id' => $ticket->fields['entities_id'],
            'autoclose_delay' => 1
         ])
      )->isEqualto(true);

      $this->_addUpdateSolution2Ticket($ticket, 'A simple solution 1 from tech.');

      $this->_rejectOrAcceptSolution($ticket, '2', 'Refused this solution', 'A simple solution 1 from tech.');

      $this->_addUpdateSolution2Ticket($ticket, 'A complex solution from tech.');

      $this->_rejectOrAcceptSolution($ticket, '3', 'Approved this solution', 'A complex solution from tech.');

   }


   function testShowForm() {
      // create ticket
      $ticket = $this->_createTicket();
      $tkt_solution = new \TicketSolution();

      // let's have a look at the html
      ob_start();
      $tkt_solution->showForm(0, ['parent' => $ticket]);
      $ret = ob_get_clean();
      $regex="@<div id='solution\\d+'><textarea id='solution\\d+' name='solution' rows='12' cols='80'>N/A</textarea></div>@";
      $this->string($ret)->match($regex);

      // change entity auto-close delay for tickets
      $re = new \Entity();
      $this->boolean(
         (boolean)$re->update([
            'id' => $ticket->fields['entities_id'],
            'autoclose_delay' => 1
         ])
      )->isEqualto(true);

      $this->_addUpdateSolution2Ticket($ticket, 'A simple solution 1 from tech.');

      ob_start();
      $tkt_solution->showForm(null, ['parent' => $ticket]);
      $ret = ob_get_clean();
      $regex="@<div id='solution\\d+'><textarea id='solution\\d+' name='solution' rows='12' cols='80'>A simple solution 1 from tech\\.</textarea></div>@";
      $this->string($ret)->match($regex);
   }

}
