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

class PluginGeststockConfig extends CommonDBTM {

   static $rightname  = 'plugin_geststock';
   const TOVA         = 0;



   static function getTypeName($nb=0) {
      return __('Setup');
   }


   static function install(Migration $mig) {
      global $DB;

      $table = 'glpi_plugin_geststock_configs';
      if (!$DB->tableExists($table)) { //not installed
         $query = "CREATE TABLE `". $table."`(
                     `id` int(11) NOT NULL,
                     `entities_id_stock` int(11) NULL,
                     `stock_status` int(11) NULL,
                     `transit_status` int(11) NULL,
                     `date_mod` timestamp NULL DEFAULT NULL,
                     `users_id` int(11) NULL,
                     `criterion` varchar(100) NOT NULL,
                     PRIMARY KEY  (`id`),
                     KEY `users_id` (`users_id`),
                     KEY `entities_id_stock` (`entities_id_stock`),
                     KEY `stock_status` (`stock_status`),
                     KEY `transit_status` (`transit_status`)
                   ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
         $DB->queryOrDie($query, 'Error in creating glpi_plugin_geststock_configs'.
                                 "<br>".$DB->error());
      } else { // migration for maedi
         $migration = new Migration(100);
         if (!$DB->fieldExists($table, "stock_status")) {
            $migration->addField($table, 'criterion', 'string', ['value' => 'otherserial',
                                                                 'after' => 'entities_id_stock']);
            $migration->addField($table, 'transit_status', 'integer', ['value' => 3,
                                                                       'after' => 'entities_id_stock']);
            $migration->addField($table, 'stock_status', 'integer', ['value' => 1,
                                                                     'after' => 'entities_id_stock']);
         }
         $migration->executeMigration();
      }
      return true;
   }


   static function uninstall() {
      global $DB;

      if ($DB->tableExists('glpi_plugin_geststock_configs')) {
         $query = "DROP TABLE `glpi_plugin_geststock_configs`";
         $DB->queryOrDie($query, $DB->error());
      }
      return true;
   }


   static function showConfigForm() {

      $config = new self();

      echo "<form method='post' action='".Toolbox::getItemTypeFormURL(__CLASS__)."'>";
      echo "<table class='tab_cadre_fixe'>";
      echo "<tr class='tab_bg_1'>";
      echo "<th colspan='2'>".__('Configuration')."</th></tr>";

      echo "<tr class='tab_bg_1'>";
      $config->getFromDB(1);
      echo "<td>".__('Entity of stock', 'geststock')."</td><td width='70%'>";
      Entity::dropdown(['name'     => 'entities_id_stock',
                        'value'    => isset($config->fields['entities_id_stock'])
                                            ? $config->fields['entities_id_stock'] : '',
                        'addicon'  => false,
                        'comments' => false]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_1'>";
      echo "<td>".__('Status of item in stock', 'geststock')."</td><td width='70%'>";
      State::dropdown(['name'     => 'stock_status',
                       'value'    => isset($config->fields['stock_status'])
                                           ? $config->fields['stock_status'] : '',
                       'addicon'  => false,
                       'comments' => false]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_1'>";
      echo "<td>".__('status of item in transit', 'geststock')."</td><td width='70%'>";
      State::dropdown(['name'     => 'transit_status',
                       'value'    => isset($config->fields['transit_status'])
                                           ? $config->fields['transit_status'] : '',
                       'addicon'  => false,
                       'comments' => false]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_1'>";
      echo "<td>".__('Criterion of items', 'geststock')."</td><td width='70%'>";
      $crit['serial']       = __('Serial number');
      $crit['otherserial']  = __('Inventory number');
      Dropdown::showFromArray('criterion', $crit,
                              ['value' => isset($config->fields['criterion'])
                                                ? $config->fields['criterion'] : '']);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_1'><td class='center' colspan='2'>";
      if ($config->getFromDB(1)) {
         echo Html::submit(_sx('button', 'Update'), ['name'  => 'update',
                                                     'class' => 'btn btn-primary']);
      } else {
         echo Html::submit(_sx('button', 'Add'), ['name'  => 'add',
                                                  'class' => 'btn btn-primary']);
      }
      echo "</td></tr></table>";
      HTML::closeForm();

      return false;
   }

}
