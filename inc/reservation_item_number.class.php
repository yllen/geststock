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


if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}


class PluginGeststockReservation_Item_Number extends CommonDBChild {

   /// From CommonDBChild
   static public $itemtype          = 'PluginGeststockReservation_Item';
   static public $items_id          = 'plugin_geststock_reservations_items_id';

   static public $checkParentRights = self::HAVE_VIEW_RIGHT_ON_ITEM;

   static $rightname                = 'plugin_geststock';


   function prepareInputForAdd($input) {

      if (isset($input["otherserial"])) {
         $input["otherserial"] = exportArrayToDB($input["otherserial"]);
      }

      return $input;
   }


   function prepareInputForUpdate($input) {

      if (isset($input["otherserial"])) {
         if ((!isset($input["otherserial"])) || (!is_array($input["otherserial"]))) {
            $input["otherserial"] = [];
         }
         $input["otherserial"] = exportArrayToDB($input["otherserial"]);
      }

      return $input;
   }


   static function install(Migration $mig) {
      global $DB;

      $table = 'glpi_plugin_geststock_reservations_items_numbers';
      if (!$DB->tableExists($table)) { //not installed
         $query = "CREATE TABLE `glpi_plugin_geststock_reservations_items_numbers`(
                     `id` int(11) NOT NULL AUTO_INCREMENT,
                     `plugin_geststock_reservations_items_id` int(11) NULL,
                     `itemtype` varchar(100) COLLATE utf8_unicode_ci  NULL,
                     `models_id` int(11) NULL,
                     `locations_id_stock` int(11) NULL,
                     `otherserial` text COLLATE utf8_unicode_ci,
                     `users_id` int(11) NULL,
                     `date_mod` timestamp NULL DEFAULT NULL,
                     PRIMARY KEY (`id`),
                     KEY `plugin_geststock_reservations_items_id` (`plugin_geststock_reservations_items_id`),
                     KEY `users_id` (users_id),
                     KEY `item` (`itemtype`,`models_id`)
                   ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

         $DB->queryOrDie($query, 'Error in creating glpi_plugin_geststock_reservations_items_numbers'.
               "<br>".$DB->error());
      } else {
         // migration to 2.1.0
         $mig->changeField($table, 'date_mod', 'date_mod', "timestamp NULL DEFAULT NULL");
      }      
   }


   static function uninstall() {
      global $DB;

      $tables = ['glpi_plugin_geststock_reservations_items_numbers'];

      foreach ($tables as $table) {
         $query = "DROP TABLE IF EXISTS `$table`";
         $DB->queryOrDie($query, $DB->error());
      }
   }

}
