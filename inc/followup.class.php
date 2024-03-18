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

class PluginGeststockFollowup extends CommonDBTM {


   static function install(Migration $mig) {
      global $DB;

      $table = 'glpi_plugin_geststock_followups';
      if (!$DB->tableExists($table)) { //not installed
         $query = "CREATE TABLE `". $table."`(
                     `id` int(11) NOT NULL AUTO_INCREMENT,
                     `plugin_geststock_reservations_id` int(11) NULL,
                     `plugin_geststock_reservations_items_id` int(11) NULL,
                     `locations_id_old` int(11) NULL,
                     `locations_id_new` int(11) NULL,
                     `users_id` int(11) NULL,
                     `date_mod` timestamp NULL DEFAULT NULL,
                     PRIMARY KEY (`id`),
                     KEY `plugin_geststock_reservations_id` (`plugin_geststock_reservations_id`),
                     KEY `plugin_geststock_reservations_items_id` (`plugin_geststock_reservations_items_id`),
                     KEY `locations_id_old` (locations_id_old),
                     KEY `locations_id_new` (locations_id_new),
                     KEY `users_id` (users_id)
                   ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

         $DB->queryOrDie($query, 'Error in creating glpi_plugin_geststock_followups'.
               "<br>".$DB->error());

      }
   }


   static function uninstall() {
      global $DB;

      $tables = ['glpi_plugin_geststock_followups'];

      foreach ($tables as $table) {
         $query = "DROP TABLE IF EXISTS `$table`";
         $DB->queryOrDie($query, $DB->error());
      }
   }


   static function showMassiveActionsSubForm(MassiveAction $ma) {
      global $CFG_GLPI;

      switch ($ma->getAction()) {
         case 'movenext' :
            $config = new PluginGeststockConfig();
            $config->getFromDB(1);
            $entity = $config->fields['entities_id_stock'];
            Location::dropdown(['entity'   => $entity,
                                'addicon'  => false,
                                'comments' => false]);
            echo "<br><br>\n";
            echo Html::submit(_sx('button', 'Move'), ['name' => 'massiveaction'])."</span>";
            return true;
      }
      return parent::showMassiveActionsSubForm($ma);
   }


   static function processMassiveActionsForOneItemtype(MassiveAction $ma, CommonDBTM $item,
                                                       array $ids) {
      global $DB;

      $fup = new PluginGeststockFollowup();
      switch ($ma->getAction()) {
         case 'movenext' :
            $input = $ma->getInput();
            foreach ($ids as $key) {
               foreach ($DB->request("glpi_plugin_geststock_followups",
                                     ['plugin_geststock_reservations_items_id' => $key]) as $val) {
                  if (is_null($val['locations_id_new'])) {
                     if ($fup->update(['id'                => $val['id'],
                                       'locations_id_new'  => $input['locations_id']])) {
                        $ma->itemDone($item->getType(), $val['id'], MassiveAction::ACTION_OK);
                     } else {
                        $ma->itemDone($item->getType(), $val['id'], MassiveAction::ACTION_KO);
                     }
                  } else {
                     $values['plugin_geststock_reservations_id']         = $val['plugin_geststock_reservations_id'];
                     $values['plugin_geststock_reservations_items_id']   = $key;
                     $values["locations_id_old"]                         = $val['locations_id_new'];
                     $values["locations_id_new"]                         = $input['locations_id'];
                     $values['users_id']                                 = Session::getLoginUserID();
                     $fup->add($values);
                  }
               }
            }
            break;
      }
   }


   static function rawSearchOptionstoAdd($itemtype=null) {

      $tab = [];

      $tab[] = ['id'             => '1',
               'table'          => 'glpi_plugin_geststock_followups',
               'field'          => 'locations_id_new',
               'name'           => __('New location', 'geststock'),
               'searchtype'     => 'equals',
               'datatype'       => 'specific'];

      return $tab;
   }
}
