<?php
/*
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
 @copyright Copyright (c) 2017-2022 GestStock plugin team
 @license   AGPL License 3.0 or (at your option) any later version
            http://www.gnu.org/licenses/agpl-3.0-standalone.html
 @link
 @since     version 1.0.0
 --------------------------------------------------------------------------
 */

use Glpi\Event;

include ("../../../inc/includes.php");

Session::checkRight("plugin_geststock", READ);

if (!isset($_GET["id"])) {
   $_GET["id"] = "";
}

$PluginReservation = new PluginGeststockReservation();
$PluginItem        = new PluginGeststockReservation_Item();
$PluginFup         = new PluginGeststockFollowup();
$Fup               = new ITILFollowup();

if (isset($_POST["add"])) {
   $PluginReservation->check(-1, CREATE, $_POST);
   if (empty($_POST['users_id'])) {
      $_POST['users_id'] = Session::getLoginUserID();
   }
   if (isset($_GET['tickets_id'])) {
      $_POST['tickets_id'] = $_GET['tickets_id'];
   }
   if ($newID = $PluginReservation->add($_POST)) {

      Event::log($newID, "geststock", 4, "tools",
                 sprintf(__('%1$s adds the item %2$s'), $_SESSION["glpiname"], $newID));
   }

   if (isset($_POST['_nbrereserv'])) {
      foreach ($_POST['_nbrereserv'] as $lieu => $val) {
         if ($val > 0) {
            $newIDitem = $PluginItem->add(['plugin_geststock_reservations_id' => $newID,
                                           'itemtype'                         => $_POST['_itemtype'],
                                           'models_id'                        => $_POST['_model'],
                                           'nbrereserv'                       => $val,
                                           'locations_id_stock'                => $lieu]);

            if ($newIDitem) {
               $PluginFup->add(['plugin_geststock_reservations_id'       => $newID,
                                'plugin_geststock_reservations_items_id' => $newIDitem,
                                'locations_id_old'                       => $lieu,
                                'users_id'                               => Session::getLoginUserID()]);

               $Fup->add(['itemtype'    => 'Ticket',
                          'items_id'    => $_POST['tickets_id'],
                          'content'     => sprintf(__('%1$s %2$s'),
                                                   __('New reservation created on ', 'geststock'),
                                                   sprintf(__('%1$s (%2$s)'),
                                                           $_SESSION["glpi_currenttime"],
                                                           $newID)),
                          'date'        =>  $_SESSION["glpi_currenttime"],
                          'users_id'    => Session::getLoginUserID()]);
            }
         }
      }
   }
   if ($_SESSION['glpibackcreated']) {
      Html::redirect($PluginReservation->getFormURL()."?id=".$newID);
   }

   $ticket = new Ticket();
   Html::redirect($ticket->getFormURL()."?id=".$_POST['tickets_id']);


} else if (isset($_POST["update"])) {
   $PluginReservation->check($_POST['id'], UPDATE);
   $PluginReservation->update($_POST);
   Html::back();

} else if (isset($_POST["delete"])) {
   $PluginReservation->check($_POST['id'], DELETE);
   $PluginReservation->delete($_POST);
   Html::redirect(Plugin::getWebDir('geststock')."/front/reservation.php");

} else if (isset($_POST["restore"])) {
   $PluginReservation->check($_POST['id'], PURGE);
   $PluginReservation->restore($_POST);
   Html::back();

} else if (isset($_POST["purge"])) {
   $PluginReservation->check($_POST['id'], PURGE);
   if ($PluginReservation->delete($_POST, 1)) {
      Event::log($_POST["id"], "plugingeststockreservation", 4, "tools",
                 sprintf(__('%s purges an item'), $_SESSION["glpiname"]));
   }
   Html::redirect(Plugin::getWebDir('geststock')."/front/reservation.php");

} else {
   Html::header(PluginGeststockReservation::getTypeName(1), '', "tools", "plugingeststockmenu");
   $PluginReservation->display($_GET);
   Html::footer();
}
