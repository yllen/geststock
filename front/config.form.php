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

$pluginGeststockConfig = new PluginGeststockConfig();
$_POST['id']           = 1;
$_POST['users_id']     = $_SESSION['glpiID'];

if (isset($_POST["add"])) {
   if ($newID = $pluginGeststockConfig->add($_POST)) {
      Event::log($newID, "pluginGeststock", 4, "tools",
      //TRANS: %1$s is the user login, %2$s is the name of the item to add
      sprintf(__('%1$s adds the item %2$s'), $_SESSION["glpiname"], isset($_POST["name"])));
      if ($_SESSION['glpibackcreated']) {
         Html::redirect($pluginGeststockConfig->getFormURL()."?id=".$newID);
      }
   }
   Html::back();

} else if (isset($_POST["update"])) {
   $pluginGeststockConfig->update($_POST);
   Html::back();

} else {
   Html::header(PluginGeststockReservation::getTypeName(1), '', "tools", "plugingeststockmenu");
   $pluginGeststockConfig->showConfigForm();
   Html::footer();
}
