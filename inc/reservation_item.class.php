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


class PluginGeststockReservation_Item extends CommonDBChild {

   /// From CommonDBChild
   static public $itemtype          = 'PluginGeststockReservation';
   static public $items_id          = 'plugin_geststock_reservations_id';

   static public $checkParentRights = self::HAVE_VIEW_RIGHT_ON_ITEM;

   static $rightname                = 'plugin_geststock';



   static function getTypeName($nb=0) {
      return _n('Reservable item', 'Reservable items',$nb);
   }


   static function countForGeststock(PluginGeststockReservation $item) {
      global $CFG_GLPI;

      $dbu = new DbUtils();
      $types = implode("','", $CFG_GLPI['asset_types']);
      if (empty($types)) {
         return 0;
      }
      return $dbu->countElementsInTable('glpi_plugin_geststock_reservations_items',
                                        ['itemtype'                         => [$types],
                                         'plugin_geststock_reservations_id' => $item->getID()]);
   }


   static function countReserved($type, $model, $entity, $location) {
      global $DB;

      $reserv = 0;
      $resa = new PluginGeststockReservation();
      foreach ($DB->request('glpi_plugin_geststock_reservations_items',
                            ['itemtype'           => $type,
                             'models_id'          => $model,
                             'locations_id_stock' => $location]) as $data) {
         $resa->getFromDB($data['plugin_geststock_reservations_id']);
         if ($resa->fields['status'] < 3) { //asked of waiting
            $reserv += $data['nbrereserv'];
         }
      }
      return $reserv;
   }


   static function countStock($type, $model, $entity, $location) {

      $item = new $type();
      $dbu  = new DbUtils();
      $itemkey = strtolower($type)."models_id";
      if ($type == "PluginSimcardSimcard") {
         $itemkey = 'plugin_simcard_simcardtypes_id';
      }
      $config  = new PluginGeststockConfig();
      $config->getFromDB(1);
      return $dbu->countElementsInTable($item->getTable(),
                                        [$itemkey       => $model,
                                         'is_deleted'   => 0,
                                         'is_template'  => 0,
                                         'entities_id'  => $entity,
                                         'locations_id' => $location,
                                         'states_id'    => [$config->fields['stock_status'],
                                                            $config->fields['transit_status']]]);
   }


   static function countTransit($type, $model, $entity, $location) {
      global $DB;

      $item    = new $type();
      $dbu     = new DbUtils();
      $itemkey = strtolower($type)."models_id";
      if ($item == "PluginSimcardSimcard") {
         $itemkey = 'plugin_simcard_simcardtypes_id ';
      }
      $config  = new PluginGeststockConfig();
      $config->getFromDB(1);
      return $dbu->countElementsInTable($item->getTable(),
                                        [$itemkey        => $model,
                                         'is_deleted'    => 0,
                                         'is_template'   => 0,
                                         'entities_id'   => $entity,
                                         'locations_id'  => $location,
                                         'states_id'     => $config->fields['transit_status']]);
   }


   static function countAvailable($type, $model, $entity, $location) {

      return self::countStock($type, $model, $entity, $location)
                  - self::countReserved($type, $model, $entity, $location);
   }


   function getTabNameForItem(CommonGLPI $item, $withtemplate=0) {

      if ($item->getType() == 'PluginGeststockReservation') {
         $nb = '';
         if ($_SESSION['glpishow_count_on_tabs']) {
            $nb = self::countForGeststock($item);
         }
         return self::createTabEntry(_n('Associated item', 'Associated items', $nb), $nb);
      }
      return '';
   }


   static function displayTabContentForItem(CommonGLPI $item, $tabnum=1, $withtemplate=0) {

      if ($item->getType() == 'PluginGeststockReservation') {
         self::showForItem($item);
      }
      return true;
   }


   function post_getEmpty() {

      $this->fields['_nbrereserv'] = '';
      $this->fields['_itemtype']   = '';
      $this->fields['_model']      = 0;
   }


   static function showForItem(PluginGeststockReservation $resa) {
      global $DB, $CFG_GLPI;

      $instID = $resa->getField('id');

      $dbu = new DbUtils();

      if (!$resa->can($instID, READ)) {
         return false;
      }
      $rand = mt_rand();

      $ticket = new Ticket();
      $ticket->getFromDB($resa->fields['tickets_id']);
      $canedit   = Session::haveRight(self::$rightname, CREATE);
      $canupdate = Session::haveRight(self::$rightname, UPDATE);

      $query = ['SELECT'    => 'itemtype',
                'DISTINCT'  => true,
                'FROM'      => 'glpi_plugin_geststock_reservations_items',
                'WHERE'     => ['plugin_geststock_reservations_id' => $instID],
                'ORDER'     => 'itemtype'];
      $result = $DB->request($query);
      $number = count($result);

      if ($canedit
          && ($resa->fields['status'] < PluginGeststockReservation::RECEIPT)
          && !in_array($ticket->fields['status'], Ticket::getClosedStatusArray())) {
         echo "<div class='firstbloc'>";
         echo "<form name='reservation_item_form$rand' id='reservation_item_form$rand' method='post'
                action='".Toolbox::getItemTypeFormURL(__CLASS__)."'>";

         echo "<table class='tab_cadre_fixe'>";
         echo "<tr class='tab_bg_2'><th colspan='3'>".__('Add an item')."</th></tr>";

         echo "<tr class='tab_bg_1'>";
         echo "<td>".__('Type')." <p> ".__('Model')."</p></td><td colspan='2'>";
         $config = new PluginGeststockConfig();
         $config->getFromDB(1);
         $entity = $config->fields['entities_id_stock'];
         PluginGeststockReservation::showAllItems("model", 0, 0, $entity);
         echo Html::submit(_sx('button', 'Add'), ['name'  => 'additem',
                                                  'class' => 'btn btn-primary']);
         echo Html::hidden('reservations_id', ['value' => $instID]);
         echo "</td></tr>";
         echo "</table>";
         Html::closeForm();
      }

      echo "<div class='spaced'>";
      echo "<table class='tab_cadre_fixe'>";
      echo "<tr class='tab_bg_1'>";
      $link = $resa->getLinkURL();
      echo "<td><a  href='$link'>".__('Reservation')."</a></td>";
      echo "<td class='green'>".
               sprintf(__('%1$s: %2$s'), "<b>".__('Entity')."</b>",
                       Dropdown::getDropdownName('glpi_entities',
                                                 $resa->fields['entities_id_deliv']))."<br /> ".
               sprintf(__('%1$s: %2$s'), "<b>".__('Location')."</b>",
                       Dropdown::getDropdownName('glpi_locations',
                                                  $resa->fields['locations_id']))." <br /> ".
               sprintf(__('%1$s: %2$s'), "<b>".__('Status')."</b>",
                       PluginGeststockReservation::getStatusName($resa->fields['status']))."</td>";
      echo "</td></tr>";

      echo "<td>".__('Ticket')."</td>";
      echo "<td colspan='2'>".$ticket->getLink();
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>".__('Delivery date', 'geststock')."</td>";
      echo "<td colspan='2'>".Html::convDate($resa->fields["date_whished"]);
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>".__('Receipt date', 'geststock')."</td>";
      echo "<td colspan='2'>".Html::convDate($resa->fields["receipt_date"]);
      echo "</td></tr>";

      if (PluginGeststockConfig::TOVA == 1) {
         echo "<tr class='tab_bg_1'>";
         echo "<th colspan='4'>TOVA</th></tr>";

         echo "<tr class='tab_bg_1'>";
         echo "<td>Date Tova&nbsp;&nbsp;";
         echo Html::convDate($resa->fields["date_tova"]);
         echo "</td>";
         echo "<td>Numéro de valise&nbsp;&nbsp;".$resa->fields['number_tova'];
         echo "</td>";
         echo "<td>Type de valise&nbsp;&nbsp;";
         echo PluginGeststockReservation::getStatusTova($resa->fields['type_tova']);
         echo "</td></tr>";
      }

      echo "</table>";
      echo "</div>";

      echo "<div class='spaced'>";

      $nbre = new PluginGeststockReservation_Item_Number();
      $display = $empty = false;
      foreach ($DB->request("glpi_plugin_geststock_reservations_items",
            ['plugin_geststock_reservations_id' => $resa->fields['id']]) as $resait) {
               $resaitem = $resait['id'];
               if ($nbre->getFromDBByRequest(['WHERE' => ['plugin_geststock_reservations_items_id'
                                                            => $resaitem]])) {
                  $num  = importArrayFromDB($nbre->fields['otherserial']);
                  if (count($num) == $resait['nbrereserv']) {
                     $display = true;
                  } else {
                     $display = false;
                  }
               } else {
                  $empty = true;
               }
            }
      if (!$empty && $display
          && Session::haveRight(self::$rightname, PluginGeststockGestion::TRANSFERT)
          && ($resa->fields['status'] < PluginGeststockReservation::RECEIPT)
          && self::canTransfertItem($resa->fields['id'])) {
          echo "<a class='vsubmit' href='".Toolbox::getItemTypeFormURL(__CLASS__)."?transfert=".
                 $resa->fields['tickets_id']."'>";
          echo __('Transfert items', 'geststock');
         echo "</a></div>";
      }

      $i = $volume = $weight = $totvolume = $totweight = $j = 0;
      echo "<div>";
      if ($canupdate && ($resa->fields['status'] < PluginGeststockReservation::RECEIPT)) {
         Html::openMassiveActionsForm('mass'.__CLASS__.$rand);
         $massiveactionparams = ['container' => 'mass'.__CLASS__.$rand];
         Html::showMassiveActions($massiveactionparams);
      }
      echo "<table class='tab_cadre_fixehov'>";
      echo "<tr><th colspan='".($canedit?15:13)."'>"._n('Associated item', 'Associated items', 2);
      echo "</th></tr>";
      $header_begin  = "<tr>";
      $header_top    = '';
      $header_bottom = '';
      $header_end    = '';

      if ($canupdate && ($resa->fields['status'] < PluginGeststockReservation::RECEIPT)) {
         $header_top    .= "<th width='10'>".Html::getCheckAllAsCheckbox('mass'.__CLASS__.$rand);
         $header_top    .= "</th>";
         $header_bottom .= "<th width='10'>".Html::getCheckAllAsCheckbox('mass'.__CLASS__.$rand);
         $header_bottom .= "</th>";
      }

      $header_end .= "<th>".__('Type')."</th>";
      $header_end .= "<th>".__('Model')."</th>";
      $header_end .= "<th>".__('Number reserved', 'geststock')."</th>";
      $header_end .= "<th>".__('Actual location', 'geststock')."</th>";
      $header_end .= "<th>".__('New location', 'geststock')."</th>";
      $header_end .= "<th>".__('Volume', 'geststock')."</th>";
      $header_end .= "<th>".__('Weight', 'geststock')."</th>";
      $header_end .= "<th>".__('Total volume', 'geststock')."</th>";
      $header_end .= "<th>".__('Total weight', 'geststock')."</th>";
      $header_end .= "<tr>";
      echo $header_begin.$header_top.$header_end;

      foreach ($result as $row) {
         $type = $row['itemtype'];
         if (!($item = $dbu->getItemForItemtype($type))) {
           continue;
         }

         $tabl = "glpi_".strtolower($item->getType())."models";
         if ($item->getType() == "PluginSimcardSimcard") {
            $tabl = 'glpi_plugin_simcard_simcardtypes';
         }

         $query = ['FIELDS'    => [$tabl.'.*', 'glpi_plugin_geststock_reservations_items.id AS IDD',
                                   'glpi_plugin_geststock_reservations_items.nbrereserv',
                                   'glpi_plugin_geststock_reservations_items.locations_id_stock',
                                   'glpi_plugin_geststock_reservations_items.models_id'],
                   'FROM'      => 'glpi_plugin_geststock_reservations_items',
                   'LEFT JOIN' => [$tabl => ['FKEY' => [$tabl => 'id',
                                                        'glpi_plugin_geststock_reservations_items'
                                                              => 'models_id']]],
                   'WHERE'     => ['glpi_plugin_geststock_reservations_items.itemtype' => $type,
                                   'glpi_plugin_geststock_reservations_items.plugin_geststock_reservations_id'
                                                                                       => $instID],
                   'ORDER'     => $tabl.'.id'];

         if ($result_linked = $DB->request($query)) {
            if (count($result_linked)) {
               Session::initNavigateListItems($type,
                                             _n('Stock reservation', 'Stock reservations', 2,
                                                'geststock')." = ".$resa->getNameID());

               foreach ($result_linked as $data) {
                  $item->getFromDB($data["id"]);
                  Session::addToNavigateListItems($type,$data["id"]);
                  if ($_SESSION["glpiis_ids_visible"] || empty($data["name"])) {
                     $ID = " (".$data["id"].")";
                  }
                  $name = $item->getLink();

                  $style = "style='background-color:#aaaaff'";
                  echo "<tr ".$style.">";

                  if ($canupdate
                      && ($resa->fields['status'] < PluginGeststockReservation::RECEIPT)) {
                     echo "<td width='10'>";
                     Html::showMassiveActionCheckBox(__CLASS__, $data["IDD"]);
                     echo "</td>";
                  }
                  echo "<td class='center'>".$item->getTypeName(1)."</td>";
                  echo "<td class='center'>".$data['name']."</td>";
                  echo "<td class='center'>".$data['nbrereserv']."</td>";
                  $fup = new PluginGeststockFollowup();
                  foreach ($DB->request(['SELECT' => ['MAX' => 'id'],
                                         'FROM'   => 'glpi_plugin_geststock_followups',
                                         'WHERE'  => ['plugin_geststock_reservations_items_id' => $data["IDD"]]])
                                       as $val) {
                    $fup->getFromDB($val['MAX(`id`)']);
                    echo "<td class='center'>";
                    echo Dropdown::getDropdownName('glpi_locations',
                                                   isset($fup->fields['locations_id_old'])
                                                         ? $fup->fields['locations_id_old']
                                                         : $data['locations_id_stock']);
                    echo "</td>";

                    echo "<td class='center'>";
                    $ticket = new Ticket();
                    $ticket->getFromDB($resa->fields['tickets_id']);
                    echo Dropdown::getDropdownName('glpi_locations',
                                                   isset($fup->fields['locations_id_new'])
                                                         ? $fup->fields['locations_id_new']
                                                         : $ticket->fields['locations_id']);
                    echo "</td>";
                  }

                  $model = $type."Model";
                  if ($item->getType() == "PluginSimcardSimcard") {
                     $model = 'PluginSimcardSimcardType';
                  }
                  foreach ($DB->request('glpi_plugin_geststock_specifications',
                                        ['itemtype'  => $model,
                                         'models_id' => $data['models_id']]) as $specif) {
                     $volume = $specif['volume'] * $data['nbrereserv'];
                     $weight = $specif['weight'] * $data['nbrereserv'];
                     $j++;
                  }
                  $totvolume += $volume;
                  $totweight += $weight;
                  echo "<td class='center'>".($volume > 0 ? $volume : "/")."</td>";
                  echo "<td class='center'>".($weight > 0 ? $weight : "/")."</td>";
                  if ($dbu->countElementsInTable('glpi_plugin_geststock_reservations_items',
                                                 ['plugin_geststock_reservations_id' => $resa->fields['id']])
                      == $j) {
                  echo "<td class='center'>".($totvolume > 0 ? $totvolume : "/")."</td>";
                  echo "<td class='center'>".($totweight > 0 ? $totweight : "/")."</td>";
                  }
                  echo "</tr>";
               }
            }
         }
      }

      if ($number && ($resa->fields['status'] < PluginGeststockReservation::RECEIPT)) {
         echo $header_begin.$header_bottom.$header_end;
      }

      if ($canupdate && ($resa->fields['status'] < PluginGeststockReservation::RECEIPT)) {
         echo "<tr class='tab_bg_1'><td colspan='3' class='center'>";
         echo Html::hidden('users_id', ['value' => Session::getLoginUserID()]);
         echo Html::hidden('reservations_id', ['value' => $resa->getField('id')]);
         echo "</td></tr>";
         echo "</table></div>" ;

         $massiveactionparams['ontop'] = false;
         Html::showMassiveActions($massiveactionparams);

      } else {
         echo "</table></div>";
      }
      Html::closeForm();

      $ticket = new Ticket();
      $ticket->getFromDB($resa->fields['tickets_id']);
      if (!in_array($ticket->fields['status'], Ticket::getClosedStatusArray())) {
         self::showItemToSend($resa);
      }
   }


   static function showItemToSend(PluginGeststockReservation $resa) {
      global $DB;

      $instID = $resa->getField('id');

      if (!$resa->can($instID, READ)
          || ($resa->fields['status'] >= PluginGeststockReservation::RECEIPT)) {
         return false;
      }
      $rand = mt_rand();

      $canupdate = Session::haveRight(self::$rightname, UPDATE);

      if ($canupdate) {
         echo "<div class='firstbloc'>";
         echo "<form name='reservationitem_form$rand' id='reservationitem_form$rand'
                enctype='multipart/form-data' method='post'
                action='".Toolbox::getItemTypeFormURL(__CLASS__)."'>";
      }

      $config = new PluginGeststockConfig();
      $config->getFromDB(1);
      $entity = $config->fields['entities_id_stock'];
      echo "<table class='tab_cadre_fixe'>";
      echo "<tr class='tab_bg_2'><th colspan='4'>";
      if ($canupdate) {
         echo __('Select items to send', 'geststock')."</th></tr>";
      } else {
         echo __('Items sended', 'geststock');
      }
      echo "</th></tr>";
      $dbu = new DbUtils();
      foreach ($DB->request('glpi_plugin_geststock_reservations_items',
                            ['plugin_geststock_reservations_id' => $instID]) as $resaitem) {

         $itemtype = $resaitem['itemtype'];
         $item     = new $itemtype();
         $stock    = $resaitem['locations_id_stock'];
         $model    = $resaitem['models_id'];
         $table    = $dbu->getTableForItemType($itemtype);
         $name     = strtolower($itemtype);
         $tab      = "glpi_".$name."models";
         if ($itemtype == "PluginSimcardSimcard") {
            $tab = 'glpi_plugin_simcard_simcardtypes';
         }
         $reserv   = $resaitem['nbrereserv'];

         if ($config->fields['criterion'] == 'serial') {
            $label = __('Serial number');
         } else {
            $label = __('Inventory number');
         }
         echo "<tr>";
         echo "<th>". __('Model')."</th>";
         if ($canupdate) {
            echo "<th>". __('Selection limited', 'geststock')."</th>";
            echo "<th>".sprintf(__('Selection by %s'), $label, 'geststock')."</th>";
            echo "<th>". __('Selection by file', 'geststock')."</th>";
         } else {
            echo "<th>". $label."</th>";
         }
         echo "</tr><tr class='tab_bg_1'>";
         echo "<td>". sprintf(__('%1$s %2$s'), $item->getTypeName(),
                               Dropdown::getDropdownName($tab, $model)).
              "</td><td width='25%'>";

         $name  = strtolower($itemtype);
         if (isset($dispo)) {
            unset($dispo);
         }
         $dispo = [];
         $modelkey = $name.'models_id';
         if ($itemtype == "PluginSimcardSimcard") {
            $modelkey = 'plugin_simcard_simcardtypes_id';
         }
         foreach ($DB->request($table, [$modelkey       => $model,
                                        'locations_id'  => $stock,
                                        'is_deleted'    => 0]) as $data) {
            $item = new $itemtype();
            if ($item->getFromDB($data['id'])
                && ($item->getField('states_id') == $config->fields['stock_status'])) {
               $dispo[$data['id']]     = $data['id'];
            }
         }
         $otherserial = [];
         foreach ($DB->request('glpi_plugin_geststock_reservations_items_numbers',
                               ['plugin_geststock_reservations_items_id' => $resaitem['id'],
                                'itemtype'                               => $itemtype,
                                'models_id'                              => $model,
                                'locations_id_stock'                     => $stock]) as $nbre) {

            $otherserial = importArrayFromDB($nbre['otherserial']);
         }
         foreach ($otherserial as $field => $val) {
            $dispo[$val] = $val;
         }

         if ($reserv < $dispo) {
            $dispol = array_slice($dispo, 0, $reserv, true);
         } else {
            $dispol = $dispo;
         }
         if ($canupdate) {
            self::dropdownItem($resaitem['id'], $itemtype, $model, $stock,
                               ['width'     => '70%',
                                'dispo'     => $dispol,
                                'values'    => $otherserial,
                                'used'      => $otherserial]);
            echo "</td><td width='25%'>";
            if ($reserv < $dispo) {
               $dispo = array_slice($dispo, $reserv, null, true);
               self::dropdownItem($resaitem['id'], $itemtype, $model, $stock,
                                  ['width'     => '70%',
                                   'dispo'     => $dispo,
                                   'values'    => $otherserial,
                                   'used'      => $otherserial]);
            }
            echo "</td><td>";
            echo "<input type='file' name='".$resaitem['id']."'></td></tr>";
         } else {
            echo "- ";
            foreach ($otherserial as $val) {
               $item->getFromDB($val);
               $criterion = $config->fields['criterion'];
               echo $item->fields[$criterion]." - ";
            }
         }
         echo "</td></tr>";
      }
      if ($canupdate) {
         echo "<tr class='tab_bg_1'><td></td><td colspan='2' class='center'>";
         echo Html::submit(_sx('button', 'Update'), ['name'  => 'addotherserial',
                                                     'class' => 'btn btn-primary']);
         echo "</td><td>";
         echo Html::submit(__('Upload files', 'geststock'), ['name'  => 'upload',
                                                             'class' => 'btn btn-primary']);
         echo Html::hidden('reservations_id', ['value' => $instID]);
         echo "</td></tr>";
      }
      echo "</table>";
      Html::closeForm();
      echo "</div>";
   }


   static function dropdownItem($resaitemid, $itemtype, $model, $stock,  $options=[]) {

      $p['name']           = 'itemtype['.$resaitemid.']['.$itemtype.']['.$model.']['.$stock.']';
      $p['values']         = [];
      $p['multiple']       = true;
      $p['width']          = '80%';

      if (count($options)) {
         foreach ($options as $key => $val) {
            $p[$key] = $val;
         }
      }
      $params = [];
      $item   = new $itemtype();
      $config    = new PluginGeststockConfig();
      $config->getFromDB(1);
      $criterion = $config->fields['criterion'];
      foreach ($options['dispo'] as $field => $val) {
         $item->getFromDB($field);
         $params[$val] = $item->fields[$criterion];
      }

      ksort($params);
      return Dropdown::showFromArray($p['name'], $params, $p);
   }


   function isNewItem() {
      return false;
   }


   static function install(Migration $mig) {
      global $DB;

      $table = 'glpi_plugin_geststock_reservations_items';
      if (!$DB->tableExists($table)) { //not installed
         $query = "CREATE TABLE `". $table."`(
                     `id` int(11) NOT NULL AUTO_INCREMENT,
                     `plugin_geststock_reservations_id` int(11) NULL,
                     `itemtype` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
                     `models_id` int(11) NOT NULL DEFAULT '0',
                     `nbrereserv` int(11) NULL,
                     `locations_id_stock` int(11) NULL,
                     PRIMARY KEY (`id`),
                     UNIQUE `unicity` (`plugin_geststock_reservations_id`,`models_id`,`itemtype`, `locations_id_stock`),
                     KEY `plugin_geststock_reservations_id` (`plugin_geststock_reservations_id`),
                     KEY `item` (`itemtype`,`models_id`)
                   ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

         $DB->queryOrDie($query, 'Error in creating glpi_plugin_geststock_reservations_items'.
                         "<br>".$DB->error());
      }
   }


   static function uninstall() {
      global $DB;

      $tables = ['glpi_plugin_geststock_reservations_items'];

      foreach ($tables as $table) {
         $query = "DROP TABLE IF EXISTS `$table`";
         $DB->queryOrDie($query, $DB->error());
      }
   }


   function rawSearchOptions() {

      $tab = [];

      $config = new PluginGeststockConfig();
      $config->getFromDB(1);
      if ($config->fields['criterion'] == 'serial') {
         $label = __('Serial number');
      } else {
         $label = __('Inventory number');
      }

      $tab[] = ['id'             => '11',
                'table'          => 'glpi_plugin_geststock_reservations_items_numbers',
                'field'          => 'otherserial',
                'name'           => $label,
                'massiveaction'  => false,
                'datatype'       => 'specific'];

      return $tab;
   }


   static function getSpecificValueToSelect($field, $name='', $values='', array $options=[]) {

      if (!is_array($values)) {
         $values = [$field => $values];
      }
      $options['display'] = false;

      switch ($field) {
         case 'locations_id_stock' :
            $options['value']        = $values[$field];
            $options['name']         = $name;
            $options['entity']       = 0;
            $options['entity_sons']  = true;
            $options[$field]         = $values[$field];;
            return Location::dropdown($options);

      }
      return parent::getSpecificValueToSelect($field, $name, $values, $options);
   }


   static function getSpecificValueToDisplay($field, $values, array $options=[]) {

      if (!is_array($values)) {
         $values = [$field => $values];
      }

      switch ($field) {
         case 'locations_id_stock' :
            return Dropdown::getDropdownName('glpi_locations', $values[$field]);
      }
      return parent::getSpecificValueToDisplay($field, $values, $options);
   }


   function prepareInputForUpdate($input) {
      global $DB;

      if (!empty($status)) {
         $resa = new PluginGeststockReservation();
         $resa->update(['id'     => $this->fields['plugin_geststock_reservations_id'],
                        'status' => $status]);
      }

      return parent::prepareInputForUpdate($input);
   }


   function post_purgeItem() {
      global $DB;

      $nbre    = new PluginGeststockReservation_Item_Number();
      $config  = new PluginGeststockConfig();
      $config->getFromDB(1);
      foreach ($DB->request('glpi_plugin_geststock_reservations_items_numbers',
                            ['plugin_geststock_reservations_items_id' => $this->fields['id']]) as $rin) {
         // delete otherserial
         $nbre->delete(['id' => $rin['id']]);

         //update status of item
         $item = new $this->fields['itemtype']();
         $otherserial = importArrayFromDB($rin['otherserial']);
         foreach ($otherserial as $serial => $val) {
            if ($item->getFromDB($val)) {
               // change status of item
               $item->update(['id'            => $val,
                              'states_id'     => $config->fields['stock_status']]);
            }
         }
      }
      // delete followup of item
      $fup = new PluginGeststockFollowup();
      foreach ($DB->request('glpi_plugin_geststock_followups',
                            ['plugin_geststock_reservations_items_id' => $this->input['id']]) as $ri) {

         if ($fup->getFromDB($ri['id'])) {
            $fup->delete(['id' => $ri['id']]);
         }
      }

      parent::post_purgeItem();
   }


   static function showMassiveActionsSubForm(MassiveAction $ma) {
      global $CFG_GLPI;

      switch ($ma->getAction()) {
         case 'moveactual' :
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


   public function getSpecificMassiveActions($checkitem=NULL) {

      $actions[__CLASS__.MassiveAction::CLASS_ACTION_SEPARATOR.'moveactual']
               = __('Actual location', 'geststock');
      $actions['PluginGeststockFollowup'.MassiveAction::CLASS_ACTION_SEPARATOR.'movenext']
               = __('New location', 'geststock');
      return $actions;
   }


   static function processMassiveActionsForOneItemtype(MassiveAction $ma, CommonDBTM $item,
                                                       array $ids) {
      global $DB;

      $fup = new PluginGeststockFollowup();
      switch ($ma->getAction()) {
         case 'moveactual' :
            $input = $ma->getInput();
            foreach ($ids as $key) {
               foreach ($DB->request("glpi_plugin_geststock_followups",
                                     ['plugin_geststock_reservations_items_id' => $key]) as $val) {
                  if (is_null($val['locations_id_new'])) {
                     $fup->update(['id'               => $val['id'],
                                   'locations_id_new' => $input['locations_id']]);
                  }
                  $values['plugin_geststock_reservations_id']         = $val['plugin_geststock_reservations_id'];
                  $values['plugin_geststock_reservations_items_id']   = $key;
                  $values["locations_id_old"]                         = $input['locations_id'];
                  $values['users_id']                                 = Session::getLoginUserID();
                  $fup->add($values);
               }

               foreach ($DB->request("glpi_plugin_geststock_reservations_items_numbers",
                                     ['plugin_geststock_reservations_items_id' => $key]) as $rin) {
                  // update location for item
                  $itemt       = new $rin['itemtype']();
                  $otherserial = importArrayFromDB($rin['otherserial']);
                  foreach ($otherserial as $id => $itemid) {
                     if ($itemt->getFromDB($itemid)) {
                        $itemt->update(['id'            => $itemid,
                                        'locations_id'  => $input['locations_id']]);
                     }
                  }
                  // update location for list otherserial
                  $rinbre = new PluginGeststockReservation_Item_Number();
                  if ($rinbre->getFromDB($rin['id'])) {
                     $rinbre->update(['id'                  => $rin['id'],
                                      'locations_id_stock'  => $input['locations_id']]);
                  }
               }
               // update location for reservation item
               if ($item->getFromDB($key)) {
                  if ($item->update(['id'                  => $key,
                                     'locations_id_stock'  => $input['locations_id']])) {
                     $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_OK);
                  } else {
                     $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
                  }
               }
            }
            break;
      }
   }


   static function pdfForReservation(PluginPdfSimplePDF $pdf, PluginGeststockReservation $resa) {
      global $DB, $CFG_GLPI;

      $instID = $resa->fields['id'];

      $dbu = new DbUtils();

      if (!$resa->can($instID, READ)) {
         return false;
      }
      if (!Session::haveRight("plugin_geststock", READ)) {
         return false;
      }

      $pdf->setColumnsSize(100);
      $pdf->displayTitle('<b>'._n('Associated item', 'Associated items',2).'</b>');

      $query = ['SELECT'    => 'itemtype',
                'DISTINCT'  => true,
                'FROM'      => 'glpi_plugin_geststock_reservations_items',
                'WHERE'     => ['plugin_geststock_reservations_id' => $instID],
                'ORDER'     => 'itemtype'];
      $result = $DB->request($query);
      $number = count($result);

      $pdf->setColumnsSize(15,20,10,20,20,15);
      $config = new PluginGeststockConfig();
      $config->getFromDB(1);
      if ($config->fields['criterion']== 'serial') {
         $label = __('Serial number');
      } else {
         $label = __('Inventory number');
      }
      $pdf->displayTitle('<b><i>'.__('Type'), __('Model'), __('Number reserved', 'geststock'),
                                  __('Actual location', 'geststock'), __('New location', 'geststock'),
                                  $label.
                         '</i></b>');

      if (!$number) {
         $pdf->displayLine(__('No item found'));
      } else {
         foreach ($result as $row) {
            $type = $row['itemtype'];
            if (!($item = $dbu->getItemForItemtype($type))) {
               continue;
            }

            $tabl = strtolower($item->getType());

            $tabmod    = 'glpi_'.$tabl.'models';
            $query = ['SELECT'     => [$tabmod.'.*',
                                       'glpi_plugin_geststock_reservations_items.id AS IDD',
                                       'glpi_plugin_geststock_reservations_items.nbrereserv',
                                       'glpi_plugin_geststock_reservations_items.locations_id_stock',
                                       'glpi_plugin_geststock_reservations_items.models_id'],
                       'FROM'      =>  'glpi_plugin_geststock_reservations_items',
                       'LEFT JOIN' => [$tabmod => ['FKEY' => [$tabmod => 'id',
                                                              'glpi_plugin_geststock_reservations_items'
                                                                      => 'models_id']]],
                       'WHERE'     => ['glpi_plugin_geststock_reservations_items.itemtype' => $type,
                                       'glpi_plugin_geststock_reservations_items.plugin_geststock_reservations_id'
                                                                                           => $instID],
                        'ORDER'    => $tabmod.'.id'];

            $dbu = new DbUtils();
            if ($result_linked = $DB->request($query)) {
               if (count($result_linked)) {
                  foreach ($result_linked as $data) {
                     $item->getFromDB($data["id"]);
                     $name = $data["name"];
                     if ($_SESSION["glpiis_ids_visible"] || empty($data["name"])) {
                        $name = sprintf(__('%1$s (%2$s)'), $name, $data["id"]);
                     }

                     $fup = new PluginGeststockFollowup();
                     foreach ($DB->request(['SELECT' => ['MAX' => 'id'],
                                            'FROM'   => 'glpi_plugin_geststock_followups',
                                            'WHERE'  => ['plugin_geststock_reservations_items_id'
                                                         => $data["IDD"]]]) as $val) {
                        $fup->getFromDB($val['MAX(`id`)']);
                        $newlocation = $fup->fields['locations_id_new'];
                     }
                     $nbre = '';
                     foreach ($DB->request("glpi_plugin_geststock_reservations_items_numbers",
                                           ['plugin_geststock_reservations_items_id'
                                                    => $data['IDD']]) as $numberitem) {
                        $table           = $dbu->getTableForItemType($numberitem['itemtype']);
                        $nbreotherserial = importArrayFromDB($numberitem['otherserial']);

                        foreach ($nbreotherserial as $id => $itemid) {
                           foreach ($DB->request($table, ['id' => $itemid]) as $dataitem) {
                              $item = new $numberitem['itemtype']();
                              $item->getFromDB($dataitem["id"]);
                              $nbre  .= $dataitem['otherserial']."\n";
                           }
                        }
                     }

                     $pdf->setColumnsSize(15,20,10,20,20,15);
                     $pdf->displayLine($item->getTypeName(1), $name,
                                       $data['nbrereserv'],
                                       Dropdown::getDropdownName('glpi_locations',
                                                                 $data['locations_id_stock']),
                                       Dropdown::getDropdownName('glpi_locations',
                                                                 $newlocation),
                                       $nbre);

                     $pdf->setColumnsSize(10,90);
                     // display serie or otherserial item???
                  } // while
               } // numrows device
            } // result
         } // for
      } // else
      $pdf->displaySpace();
   }


   static function canTransfertItem($resa) {
      global $DB;

      $otherserial = [];
      foreach ($DB->request('glpi_plugin_geststock_reservations_items',
                            ['plugin_geststock_reservations_id' => $resa]) as $ri) {
         foreach ($DB->request('glpi_plugin_geststock_reservations_items_numbers',
                               ['plugin_geststock_reservations_items_id' => $ri['id']]) as $nbre) {

            $otherserial = importArrayFromDB($nbre['otherserial']);
         }
      }
      if ($ri['nbrereserv'] == count($otherserial)) {
         return true;
      }
      return false;
   }

}
