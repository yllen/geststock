<?php
/*
 * @version $Id: $
 -------------------------------------------------------------------------
 LICENSE

 This file is part of GestStock plugin for GLPI.

 GestStock is free software: you can redistribute it and/or modify
 it under the terms of the GNU Affero General Public License as published by
 the Free Software Foundation, either version 3 of the License, or
 (at your option) any later version.

 GestStock is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 GNU Affero General Public License for more details.

 You should have received a copy of the GNU Affero General Public License
 along with GestStock. If not, see <http://www.gnu.org/licenses/>.

 @package   geststock
 @author    Nelly Mahu-Lasson
 @copyright Copyright (c) 2017 GestStock plugin team
 @license   AGPL License 3.0 or (at your option) any later version
            http://www.gnu.org/licenses/agpl-3.0-standalone.html
 @link
 @since     version 1.0.0
 --------------------------------------------------------------------------
 */

class PluginGeststockTicket {


   static function afterUpdate(Ticket $ticket) {
      global $DB;

      if (isset($ticket->input['status'])
          && ($ticket->input['status'] == CommonITILObject::CLOSED)) {

         $reservation = new PluginGeststockReservation();
         // transfert only if not done
         if ($reservation->getFromDB($resa['id'])
             && is_null($resa['receipt_date'])) {
            $reservation->transfertItem($ticket->input['id']);
         }
      }
   }


   static function beforeUpdate(Ticket $ticket) {
      global $DB;

      if (isset($ticket->input['status'])
            && ($ticket->input['status'] == CommonITILObject::CLOSED)) {

         $resaitem    = new PluginGeststockReservation_Item();
         $nbre        = new PluginGeststockReservation_Item_Number();

         foreach ($DB->request('glpi_plugin_geststock_reservations',
                               ['tickets_id' => $ticket->input['id']]) as $resa) {

            // no transfert if count items selected <> items reserved
            $resaid = $resa['id'];
            if ($resaitem->getFromDBByQuery("WHERE `plugin_geststock_reservations_id` = $resaid")) {
               foreach ($DB->request("glpi_plugin_geststock_reservations_items",
                                     ['plugin_geststock_reservations_id' => $resa['id']]) as $resait) {
                  $resaitid = $resait['id'];
                  $count    = $resait['nbrereserv'];
                  if ($nbre->getFromDBByQuery("WHERE `plugin_geststock_reservations_items_id` = $resaitid")) {
                     $num  = importArrayFromDB($nbre->fields['otherserial']);
                     if ($count <> $num) {
                        Session::addMessageAfterRedirect(__('Number selected different from number reserved',
                                                            'geststock'), false, ERROR);
                        $ticket->input['status'] = $ticket->fields['status'];
                     }
                  } else {
                     Session::addMessageAfterRedirect(__('Number selected different from number reserved',
                                                         'geststock'), false, ERROR);
                     $ticket->input['status'] = $ticket->fields['status'];
                  }
               }
            } else {
               Session::addMessageAfterRedirect(__('No number selected ', 'geststock'), false, ERROR);
               $ticket->input['status'] = $ticket->fields['status'];
            }
         }
      }
   }

}
