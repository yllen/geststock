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

/** @file
 * @brief
 */

if (!defined('GLPI_ROOT')) {
	die("Sorry. You can't access directly to this file");
}


class PluginGeststockSpecification extends CommonDBTM {

   public $dohistory     = true;

   static $rightname     = 'plugin_geststock';



    static function getTypeName($nb=0) {
       return __('Specifications', 'geststock');
    }


    function getTabNameForItem(CommonGLPI $item, $withtemplate=0) {

       if (Session::haveRight("plugin_geststock", PluginGeststockGestion::GESTION)) {
          return self::getTypeName(1);
       }
       return '';
    }


    static function displayTabContentForItem(CommonGLPI $item, $tabnum=1, $withtemplate=0) {
       self::showSpecification($item);
    }


    static function showSpecification($item) {
       global $DB;

       if (!Session::haveRight(self::$rightname, PluginGeststockGestion::GESTION)) {
          return false;
       }

       $rand  = mt_rand();
       $spec  = new self();
       $type = $item->getType();
       echo "<div class='center'>";
       echo "<form name='spec_form$rand' id='spec_form$rand' method='post'
               action='".Toolbox::getItemTypeFormURL(__CLASS__)."'>";
       echo "<table class='tab_cadre_fixe'>";
       foreach ($DB->request('glpi_plugin_geststock_specifications',
                             ['models_id' => $item->fields['id'],
                              'itemtype'  => $type]) as $data) {
       }
       echo "<tr class='tab_bg_1'>";
       echo "<td>".__('Length', 'geststock')."</td><td>";
       echo Html::input("length", ["value" => isset($data['length']) ? $data['length'] : '0']);
       echo "&nbsp; cm</td></tr>";

       echo "<tr class='tab_bg_1'>";
       echo "<td>".__('Width', 'geststock')."</td><td>";
       echo Html::input("width", ["value" => isset($data['width']) ? $data['width'] : '0']);
       echo "&nbsp; cm</td></tr>";

       echo "<tr class='tab_bg_1'>";
       echo "<td>".__('Height', 'geststock')."</td><td>";
       echo Html::input("height", ["value" => isset($data['height']) ? $data['height'] : '0']);
       echo "&nbsp; cm</td></tr>";

       echo "<tr class='tab_bg_1'>";
       echo "<td>".__('Weight', 'geststock')."</td><td>";
       Html::autocompletionTextField($spec, "weight",
                                     ["value" => isset($data['weight']) ? $data['weight'] : '000.000']);
       echo "&nbsp; kg</td></tr>";

       echo "<tr class='tab_bg_1'>";
       echo "<td>".__('Volume', 'geststock')."</td><td>";
       echo isset($data['volume']) ? $data['volume']."&nbsp; m3"
                                   : '<font class="red">'.__("can't be calculate", "geststock").'</font>';
       echo "</td></tr>";

       echo "<tr'><td class='center'>";
       if (isset($data['id'])) {
         echo Html::submit(_sx('button', 'Update'), ['name' => 'update',
                                                     'class' => 'btn btn-primary']);
         echo Html::hidden('id', ['value' => $data['id']]);
       } else {
         echo Html::submit(_sx('button', 'Add'), ['name' => 'add',
                                                  'class' => 'btn btn-primary']);
         echo Html::hidden('models_id',  ['value' => $item->fields['id']]);
         echo Html::hidden('itemtype', ['value' => $type]);
       }
       echo "</td></tr>";
       echo "</table>" ;
       Html::closeForm();
       echo "</div>";

       return true;
    }


    static function install(Migration $mig) {
       global $DB;

       $table = 'glpi_plugin_geststock_specifications';
       if (!$DB->tableExists($table)) { //not installed
          $query = "CREATE TABLE `". $table."`(
                     `id` int(11) NOT NULL AUTO_INCREMENT,
                     `models_id` int(11) NOT NULL DEFAULT '0',
                     `itemtype` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
                     `length` int(11) NULL,
                     `width` int(11) NULL,
                     `height` int(11) NULL,
                     `weight` decimal(6,3) NOT NULL DEFAULT '000.000',
                     `volume` float NULL,
                     `date_mod` timestamp NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE `unicity` (`models_id`, `itemtype`)
                  ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

          $DB->queryOrDie($query, 'Error in creating glpi_plugin_geststock_specifications'.
                           "<br>".$DB->error());
      } else {
         // migration to 2.1.0
         $mig->changeField($table, 'date_mod', 'date_mod', "timestamp NULL DEFAULT NULL");
      }
    }


    static function uninstall() {
       global $DB;

       $tables = ['glpi_plugin_geststock_specifications'];

       foreach ($tables as $table) {
          $query = "DROP TABLE IF EXISTS `$table`";
          $DB->queryOrDie($query, $DB->error());
       }
    }


    function prepareInputForAdd($input) {

       if (($input['length'] > 0)
           && ($input['width'] > 0)
           && ($input['height'] > 0)) {
          $input['volume'] = ($input['length'] * $input['width'] * $input['height'] / 1000000);
       } else {
          $input['volume'] = 0;
       }

       return $input;
    }


    function prepareInputForUpdate($input) {

       if (($input['length'] > 0)
           && ($input['width'] > 0)
           && ($input['height'] > 0)) {
          $input['volume'] = ($input['length'] * $input['width'] * $input['height'] / 1000000);
       } else {
          $input['volume'] = 0;
       }

       return $input;
    }
}
