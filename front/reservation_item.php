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


include ("../../../inc/includes.php");
Html::header(PluginGeststockReservation::getTypeName(1), '', "tools", "plugingeststockmenu");


$resa = new PluginGeststockReservation();
if  (isset($_GET["generate"])) {
   $text = '';
   global $DB;
   $resa->getFromDB($_GET['resa']);
   foreach ($DB->request("glpi_plugin_geststock_reservations_items",
                         ['plugin_geststock_reservations_id' => $_GET['resa']]) as $data) {

      $tablmodel = "glpi_".strtolower($data['itemtype'])."models";

      $text .= "ticket = ".$resa->fields['tickets_id']."\t";
      $text .= "nombre = ".$data['nbrereserv']."\t";
      $item = new $data['itemtype']();
      $text .= "type = ".$item->getTypeName()."\t";
      $text .= "model = ".Dropdown::getDropdownName($tablmodel, $data['models_id'])."\n";
   }
   file_put_contents(GLPI_PLUGIN_DOC_DIR."/geststock/resa".$_GET['resa'].".txt", $text,
                     FILE_APPEND);
   Html::back();

} else {
   if (Session::haveRight("plugin_geststock", PluginGeststockGestion::GESTION)) {
      Search::show('PluginGeststockReservation');
   } else {
      Html::displayRightError();
   }
   Html::footer();
}
