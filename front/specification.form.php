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

Session::checkRight("plugin_geststock",  PluginGeststockGestion::GESTION);

if (!isset($_GET["id"])) {
   $_GET["id"] = "";
}

$PluginSpec = new PluginGeststockSpecification();

if (isset($_POST["add"])) {
   $PluginSpec->check(-1, CREATE, $_POST);
   $newID = $PluginSpec->add($_POST);
   Html::back();

} else if (isset($_POST["update"])) {
   $PluginSpec->check($_POST['id'], UPDATE);
   $PluginSpec->update($_POST);
   Html::back();

} else if (isset($_POST["delete"])) {
   $PluginSpec->check($_POST['id'], DELETE);
   $PluginSpec->delete($_POST);
   Html::redirect(Plugin::getWebDir('geststock')."/front/reservation.php");

} else if (isset($_POST["restore"])) {
   $PluginSpec->check($_POST['id'], PURGE);
   $PluginSpec->restore($_POST);
   Html::back();

} else if (isset($_POST["purge"])) {
   $PluginSpec->check($_POST['id'], PURGE);
   if ($PluginSpec->delete($_POST, 1)) {
      Event::log($_POST["id"], "plugingeststockspecification", 4, "tools",
                 sprintf(__('%s purges an item'), $_SESSION["glpiname"]));
   }

   Html::redirect(Plugin::getWebDir('geststock')."/front/reservation.php");

} else {
   Html::header(PluginGeststockSpecification::getTypeName(1), '', "tools", "plugingeststockmenu");
   PluginGeststockSpecification::showSpecification($_POST);
}
