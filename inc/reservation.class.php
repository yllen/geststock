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


class PluginGeststockReservation extends CommonDBTM {

   public $dohistory     = true;
   static $rightname     = 'plugin_geststock';
   protected $usenotepad = true;

   static $types =  ['Computer', 'Monitor', 'NetworkEquipment', 'Peripheral', 'Phone', 'Printer'];

   // STATUS
   // reservation
   const ASKED      = 1; // demande
   const WAITING    = 2; // en attente livraison
   const RECEIPT    = 3; // livre
   const REFUSED    = 4; // demande refusee
   // tova
   const ACCOMP      = 7;
   const ACCRAILWAY  = 8;
   const DEPFLIGHT   = 9;
   const MAIL        = 10;
   const ACCMAIL     = 11;
   const SECFREIGHT  = 12;
   const FREIGHT     = 13;
   const FRRAILWAY   = 14;
   const FRPTT       = 15;
   const SHUTTLE     = 16;
   const ROAD        = 17;


   static function registerType($type) {

      if (!in_array($type, self::$types)) {
         self::$types[] = $type;
      }
   }


   static function getStatusName($value) {

      switch ($value) {
         case 1 :
            return _x('status', 'Asked', 'geststock');

         case 2 :
            return _x('status', 'Waiting', 'geststock');

         case 3 :
            return _x('status', 'Receipted', 'geststock');

         case 4 :
            return _x('status', 'Refused', 'geststock');
      }
   }


   static function getStatusColor($value) {

      switch ($value) {
         case 1 :
            return '#aaaaff';

         case 2 :
            return '#E5B563';

         case 3 :
            return '#69DE7C';

         case 4 :
            return '#cf9b9b';
      }
   }


   static function getAllStatusArray($withmetaforsearch=false) {

      $tab = [self::ASKED       => _x('status',  'Asked', 'geststock'),
              self::WAITING     => _x('status', 'Waiting', 'geststock'),
              self::REFUSED     => _x('status', 'Refused', 'geststock'),
              self::RECEIPT     => _x('status', 'Receipted', 'geststock')];

      return $tab;
   }


   static function getTypeName($nb=0) {
      return _n('Stock reservation', 'Stock reservations', $nb, 'geststock');
   }


   function defineTabs($options=[]) {

      $ong = [];
      $this->addDefaultFormTab($ong)
         ->addStandardTab('PluginGeststockReservation_Item', $ong, $options)
         ->addStandardTab('Notepad', $ong, $options)
         ->addStandardTab('Log', $ong, $options);

      return $ong;
   }


   function prepareInputForUpdate($input) {

      if (isset($input['tickets_id']) && ($input['tickets_id'] == 0)) {
         Session::addMessageAfterRedirect(__('Ticket is mandatory to make a reservation', 'geststock'),
                                          false, ERROR);
         return false;
      }

      if (isset($input['entities_id_deliv']) && ($input['entities_id_deliv'] == 0)) {
         Session::addMessageAfterRedirect(__('Entity is mandatory to make a reservation', 'geststock'),
                                          false, ERROR);
         return false;
      }

      if (isset($input['locations_id']) && ($input['locations_id'] == 0)) {
         Session::addMessageAfterRedirect(__('Location is mandatory to make a reservation', 'geststock'),
                                          false, ERROR);
         return false;
      }

      if ((PluginGeststockConfig::TOVA == 1)
          && (isset($input['date_tova']) && ($this->fields['date_tova'] != $input['date_tova']))
          && isset($this->fields['date_whished']) && ($this->fields['date_whished'] > $date)) {
         $input['date_whished'] = $input['date_tova'];
      }

      return parent::prepareInputForUpdate($input);
   }


   function prepareInputForAdd($input) {
      global $DB;

      if (empty($input['_itemtype'])) {
         Session::addMessageAfterRedirect(__('Thanks to specify an asset item type', 'geststock'),
                                          false, ERROR);

         return false;
      }
      if (!isset($input['_model']) || empty($input['_model'])) {
         Session::addMessageAfterRedirect(__('Thanks to specify an asset model', 'geststock'),
                                          false, ERROR);
         return false;
      }
      if (!isset($input['_nbrereserv']) || ($input['_nbrereserv'] == 0)) {
         Session::addMessageAfterRedirect(__('Thanks to specify number of items whished', 'geststock'),
                                          false, ERROR);
         return false;
      }
      if (!isset($input['tickets_id']) || $input['tickets_id'] == 0) {
         Session::addMessageAfterRedirect(__('Ticket is mandatory to make a reservation', 'geststock'),
                                          false, ERROR);
        return false;
      }
      if (!isset($input['entities_id_deliv']) || ($input['entities_id_deliv'] == 0)) {
         Session::addMessageAfterRedirect(__('Entity is mandatory to make a reservation', 'geststock'),
                                          false, ERROR);
         return false;
      }
      if (!isset($input['locations_id']) || ($input['locations_id'] == 0)) {
         Session::addMessageAfterRedirect(__('Location is mandatory to make a reservation', 'geststock'),
                                          false, ERROR);
         return false;
      }

      if (isset($input['date_whished']) && $input['date_whished']
          && self::isHoliday($input['date_whished'])) {
         Session::addMessageAfterRedirect(__('Whished date is a holidy date for gestock entity',
                                             'geststock'), false, ERROR);
         return false;
      }
      if (!isset($input['date_whished']) || ($input['date_whished'] == '')) {
         $date  = getdate();
         $hours = $date['hours'];
         if ($date['hours'] < '16') {
            // date à J+1
            $datewhished = date("Y-m-d", strtotime($_SESSION["glpi_currenttime"])
                                +1*DAY_TIMESTAMP);
         } else {
            // date à J+2
            $datewhished = date("Y-m-d", strtotime($_SESSION["glpi_currenttime"])
                                +2*DAY_TIMESTAMP);
         }
         $input['date_whished'] = $datewhished;
       }

       if ((PluginGeststockConfig::TOVA == 1)
             && (!isset($input['date_tova']) || ($input['date_tova'] == ''))) {
          $date  = getdate();
          $hours = $date['hours'];
          if ($date['hours'] < '16') {
             // date à J+1
             $datetova = date("Y-m-d", strtotime($_SESSION["glpi_currenttime"])
                   +1*DAY_TIMESTAMP);
          } else {
             // date à J+2
             $datetova = date("Y-m-d", strtotime($_SESSION["glpi_currenttime"])
                   +2*DAY_TIMESTAMP);
          }
          $input['date_tova'] = $datetova;
       }

      return  $input;
   }


   function post_purgeItem() {
      global $DB;

      $resaitem = new PluginGeststockReservation_Item();
      foreach ($DB->request('glpi_plugin_geststock_reservations_items',
                            ['plugin_geststock_reservations_id' => $this->input['id']]) as $ri) {

         if ($resaitem->getFromDB($ri['id'])) {
            $resaitem->delete(['id' => $ri['id']]);
         }
      }
      $fup = new PluginGeststockFollowup();
      foreach ($DB->request('glpi_plugin_geststock_followups',
                            ['plugin_geststock_reservations_id' => $this->input['id']]) as $ri) {

         if ($fup->getFromDB($ri['id'])) {
            $fup->delete(['id' => $ri['id']]);
         }
      }

      parent::post_purgeItem();
   }


   function post_getEmpty() {

      $this->fields['_nbrereserv'] = '';
      $this->fields['_itemtype']    = '';
      $this->fields['_model']       = 0;
   }


   function showForm($ID, $options=[]) {
      global $DB;

      if (!Session::haveRightsOr(self::$rightname, [READ, CREATE, DELETE, PURGE])) {
         return false;
      }
      $this->initForm($ID, $options);
      $options['colspan'] = 4;
      $this->showFormHeader($options);

      $ticket = new Ticket();
      $type   = $item = 0;
      if (!$ID) {
         echo "<tr class='tab_bg_1'>";
         echo "<td>".__('Type')."<p>".__('Model')."</p><p>".__('Stock location', 'geststock')."</p>";
         echo "</td><td colspan='3' class='top'>";
         $config = new PluginGeststockConfig();
         $config->getFromDB(1);
         $entity = $config->fields['entities_id_stock'];
         $number = isset($this->fields['_nbrereserv']) ? $this->fields['_nbrereserv'] : [];
         self::showAllItems("_model", $this->fields['_itemtype'], $this->fields['_model'],
                            $entity, $number);
         echo "</td></tr>";
      }

      if (isset($this->input['_fromticket'])) {
         $options['tickets_id'] = $this->input['tickets_id'];
      }
      if (isset($options['tickets_id']) && ($options['tickets_id'] > 0)) {
         $ticket->getFromDB($options['tickets_id']);
         echo "<td>".__('Delivery entity', 'geststock')."</td>";
         echo "<td colspan='3' class='top'>".
         Dropdown::getDropdownName('glpi_entities', $ticket->fields['entities_id']);
         echo "</td></tr>";

         echo "<tr class='tab_bg_1'>";
         echo "<td>".__('Delivery location', 'geststock')."</td><td colspan='3'>";
         Location::dropdown(['entity'   => $ticket->fields['entities_id'],
                             'value'    => isset($ticket->fields['locations_id'])
                                                       ? $ticket->fields['locations_id'] : '',
                             'addicon'  => false,
                             'comments' => false]);
         echo "</td></tr>";

         echo "<tr class='tab_bg_1'>";
         echo "<td>".__('Ticket')."</td>";
         echo "<td colspan='3'>".Dropdown::getDropdownName('glpi_tickets', $ticket->fields['id']);
         echo Html::hidden('entities_id_deliv', ['value' => $ticket->fields['entities_id']]);
         echo Html::hidden('tickets_id', ['value' => $options['tickets_id']]);
         echo Html::hidden('_fromticket', ['value' => 1]);

      } else {
         echo "<tr class='tab_bg_1'>";
         echo "<td>".__('Delivery entity', 'geststock')." <p> ".__('Delivery location', 'geststock').
               "</p><p> ".__('Ticket')."</td><td colspan='3' class='top'>";
         self::showAllEntities("locations_id", "tickets_id", $this->fields['entities_id_deliv'],
                               $this->fields['locations_id'], $this->fields['tickets_id']);
      }
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>".__('Delivery date', 'geststock')."</td>";
      echo "<td colspan='3'>";
      Html::showDateField("date_whished", ['value'      => $this->fields["date_whished"],
                                           'maybeempty' => true]);
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>".__('Status')."</td><td colspan='3'>";
      if (Session::haveRight(self::$rightname, PluginGeststockGestion::GESTION)
          && $ID) {
         $params = self::getAllStatusArray();
         $params['value'] = $this->fields['status'];
         self::dropdownStatus('status',$params);
      } else {
         echo self::getStatusName($ID ? $this->fields['status']:self::ASKED);
      }
      echo "</td></tr>";

      if (PluginGeststockConfig::TOVA == 1) {
         echo "<tr class='tab_bg_1'>";
         echo "<th colspan='8'>TOVA</th></tr>";

         echo "<tr class='tab_bg_1'>";
         echo "<td> Date Tova</td>";
         echo "<td>";
         Html::showDateField("date_tova", ['value'      => $this->fields["date_tova"],
                                           'maybeempty' => true]);
         echo "</td>";
         echo "<td>Numéro de valise</td><td>";
         echo Html::input('number_tova', ['value' => $this->fields["number_tova"]]);
         echo "</td></tr>";

         echo "<tr class='tab_bg_1'>";
         echo "<td>Type de valise</td><td>";
         $params                        = self::getAllStatusTova();
         $params['value']               = !empty($this->fields['type_tova'])
                                          ? $this->fields['type_tova'] : self::SECFREIGHT;
         $params['tova']                = true;
         $params['display_emptychoice'] = true;
         self::dropdownStatus('type_tova', $params);
         echo "</td><td>Nombre de colis</td><td>";
         Dropdown::showNumber("number_package", ['value' => $this->fields["number_package"],
                                                 'min'   => 1,
                                                 'max'   => 99,
                                                 'step'  => 1]);
         echo "</td></tr>";
      }

      echo "<tr class='tab_bg_1'>";
      echo "<td rowspan='5'>".__('Comments')."</td>";
      echo "<td rowspan='5' class='middle' colspan='3'>";
      Html::textarea(['name'            => 'comment',
                      'value'           => $this->fields["comment"],
                      'enable_richtext' => false,
                      'rows'            => '5',
                      'style'           => 'width:95%']);
      echo "</textarea>";
      if (empty($this->fields['date_reserv'])) {
         echo Html::hidden('date_reserv', ['value' => $_SESSION["glpi_currenttime"]]);
      }
      echo "</td></tr>\n";

      if (($this->fields['status'] == PluginGeststockReservation::RECEIPT)
          || (isset($ticket->fields['status'])
              && in_array($ticket->fields['status'], Ticket::getClosedStatusArray()))) {
         $options['candel']  = false;
         $options['canedit'] = false;
      }
      $options['colspan'] = 6;
      $this->showFormButtons($options);

      return true;
   }


   static function install(Migration $mig) {
      global $DB;

      $table = 'glpi_plugin_geststock_reservations';
      if (!$DB->TableExists($table)) { //not installed
         $query = "CREATE TABLE `". $table."`(
                     `id` int(11) NOT NULL AUTO_INCREMENT,
                     `users_id` int(11) NULL,
                     `entities_id_deliv` int(11) NULL,
                     `locations_id` int(11) NULL,
                     `tickets_id` int(11) NULL,
                     `status` int(11) NOT NULL DEFAULT '1',
                     `comment` text COLLATE utf8_unicode_ci,
                     `is_deleted` tinyint(1) NOT NULL default '0',
                     `date_reserv` timestamp NULL DEFAULT NULL,
                     `date_whished` timestamp NULL DEFAULT NULL,
                     `receipt_date` timestamp NULL DEFAULT NULL,
                     `date_tova` timestamp NULL DEFAULT NULL,
                     `number_tova` varchar(255) NULL,
                     `type_tova` int(11) NULL,
                     `number_package` int(11) NULL,
                     `date_mod` timestamp NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE `unicity` (`entities_id_deliv`, `tickets_id`),
                    KEY `users_id` (`users_id`),
                    KEY `entities_id_deliv` (`entities_id_deliv`),
                    KEY `locations_id` (`locations_id`),
                    KEY `tickets_id` (`tickets_id`)
                  ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

         $DB->queryOrDie($query, 'Error in creating glpi_plugin_geststock_reservations'.
                         "<br>".$DB->error());
      } else {
         // migration to 2.1.0
         $mig->changeField($table, 'date_reserv', 'date_reserv', "timestamp NULL DEFAULT NULL");
         $mig->changeField($table, 'date_whished', 'date_whished', "timestamp NULL DEFAULT NULL");
         $mig->changeField($table, 'receipt_date', 'receipt_date', "timestamp NULL DEFAULT NULL");
         $mig->changeField($table, 'date_tova', 'date_tova', "timestamp NULL DEFAULT NULL");
         $mig->changeField($table, 'date_mod', 'date_mod', "timestamp NULL DEFAULT NULL");
      }
   }


   static function uninstall() {
      global $DB;

      $tables = ['glpi_plugin_geststock_reservations'];

      foreach ($tables as $table) {
         $query = "DROP TABLE IF EXISTS `$table`";
         $DB->queryOrDie($query, $DB->error());
      }
   }


   function rawSearchOptions() {

      $tab = [];

      $tab[] = ['id'            => 'common',
                'name'          => _n('Stock reservation', 'Stock reservation', 2, 'geststock')];

      $tab[] = ['id'             => '1',
                'table'          => $this->getTable(),
                'field'          => 'id',
                'name'           => __('ID'),
                'massiveaction'  => false,
                'searchtype'     => 'contains',
                'datatype'       => 'itemlink'];

      $tab[] = ['id'             => '2',
                'table'          => 'glpi_plugin_geststock_reservations_items',
                'field'          => 'models_id',
                'name'           => sprintf(__('%1$s - %2$s'), _x('Quantity', 'Number'),
                                            sprintf(__('%1$s - %2$s'), __('Type'), __('Model'))),
                'massiveaction'  => false,
                'forcegroupby'   =>  true,
                'joinparams'     => ['jointype' => 'child'],
                'nosearch'       => true];

      $tab[] = ['id'             => '3',
                'table'          => $this->getTable(),
                'field'          =>  'locations_id',
                'name'           =>  __('Delivery location', 'geststock'),
                'searchtype'     => 'equals',
                'datatype'       => 'specific'];

      $tab[] = ['id'             => '4',
                'table'          => $this->getTable(),
                'field'          =>  'comment',
                'name'           =>  __('Comments'),
                'datatype'       =>  'text'];

      $tab[] = ['id'             => '5',
                'table'          => 'glpi_tickets',
                'field'          => 'id',
                'name'           => __('Ticket'),
                'datatype'       => 'itemlink'];

      $tab[] = ['id'             => '6',
                'table'          => $this->getTable(),
                'field'          => 'entities_id_deliv',
                'name'           => __('Delivery entity', 'geststock'),
                'searchtype'     => 'equals',
                'datatype'       => 'specific'];

      $tab[] = ['id'             => '7',
                'table'          => 'glpi_users',
                'field'          => 'name',
                'name'           => __('User'),
                'datatype'       => 'dropdown'];

      $tab[] = ['id'             => '9',
                'table'          => $this->getTable(),
                'field'          => 'date_mod',
                'name'           => __('Last update'),
                'massiveaction'  => false,
                'datatype'       => 'datetime'];

      $tab[] = ['id'             => '10',
                'table'          => $this->getTable(),
                'field'          => 'receipt_date',
                'name'           => __('Receipt date', 'geststock'),
                'datatype'       => 'date'];

      $tab[] = ['id'             => '11',
                'table'          => $this->getTable(),
                'field'          => 'status',
                'name'           => __('Status'),
                'searchtype'     => 'equals',
                'datatype'       => 'specific'];

      $tab[] = ['id'             => '12',
                'table'          => $this->getTable(),
                'field'          => 'date_whished',
                'name'           => __('Delivery date', 'geststock'),
                'datatype'       => 'date'];

      if (PluginGeststockConfig::TOVA == 1) {
         $tab[] = ['id'             => '13',
                   'table'          => $this->getTable(),
                   'field'          => 'date_tova',
                   'name'           => 'Date Tova',
                   'datatype'       => 'date'];

         $tab[] = ['id'             => '14',
                   'table'          => $this->getTable(),
                   'field'          => 'number_tova',
                   'name'           => 'Numéro de valise',
                   'datatype'       => 'text'];

         $tab[] = ['id'             => '15',
                   'table'          => $this->getTable(),
                   'field'          => 'type_tova',
                   'name'           => 'Type de valise',
                   'searchtype'     => 'equals',
                   'datatype'       => 'specific'];

         $tab[] = ['id'             => '16',
                   'table'          => $this->getTable(),
                   'field'          => 'number_package',
                   'name'           => 'Nombre de colis',
                   'datatype'       => 'number'];
      }

         $tab[] = ['id'             => '17',
                   'table'          => 'glpi_plugin_geststock_followups',
                   'field'          => 'locations_id_new',
                   'name'           => __('Actual location', 'geststock'),
                   'massiveaction'  => false,
                   'forcegroupby'   =>  true,
                   'joinparams'     => ['jointype' => 'child'],
                   'nosearch'       => true];

         $tab[] = ['id'             => '18',
                   'table'          => 'glpi_plugin_geststock_reservations_items',
                   'field'          =>  'locations_id_stock',
                   'name'           =>  __('Stock location', 'geststock'),
                   'massiveaction'  => false,
                   'searchtype'     => 'equals',
                   'datatype'       => 'specific'];

      return $tab;
   }


   static function showAllItems($myname, $value_type=0, $value=0, $entity_restrict=-1, $number=[]) {
      global $DB,$CFG_GLPI;

      $rand  = mt_rand();

      foreach (self::$types as $label) {
         $item = new $label();
         $params[$label] = $item->getTypeName();
      }

      $paramtype['width']               = '80%';
      $paramtype['rand']                = $rand;
      $paramtype['display_emptychoice'] = true;

      if (in_array($value_type, self::$types)) {
         $paramtype['value']     = $value_type;
      }
      Dropdown::showFromArray('_itemtype', $params, $paramtype);

      $field_id = Html::cleanId("dropdown__itemtype$rand");

      $params = ['itemtype'   => '__VALUE__',
                 'value'      => $value,
                 'myname'     => $myname,
                 'entity'     => $entity_restrict];


      echo "<span id='show_$myname$rand'>\n";
      if (isset($value) && $value > 0) {
         self::showAllModels($myname, $value_type, $value, $number);
      } else {
        echo "&nbsp;\n";
      }
      Ajax::updateItemOnSelectEvent($field_id, "show_$myname$rand",
                                    Plugin::getWebDir('geststock')."/ajax/dropdownAllItems.php",
                                    $params);
      echo "</span>";

      return $rand;
   }


   static function showAllEntities($mynamel, $mynamet, $value_type=0, $valuel=0, $valuet=0,
                                   $entity_restrict=-1) {
      global $DB,$CFG_GLPI;

      $rand  = mt_rand();

      if ($entity_restrict == '-1') {
         foreach (Profile_User::getUserEntities(Session::getLoginUserID()) as $id => $label) {
            $params[$label] = Dropdown::getDropdownName('glpi_entities', $label);
         }
         $paramtype['display_emptychoice'] = true;
      } else {
         $params[$entity_restrict] = Dropdown::getDropdownName('glpi_entities', $entity_restrict);
      }
      $paramtype['width']               = '60%';
      $paramtype['rand']                = $rand;
      $paramtype['value']               = $value_type;

      Dropdown::showFromArray('entities_id_deliv', $params, $paramtype);

      $field_id = Html::cleanId("dropdown_entities_id_deliv$rand");

      $params = ['entity'   => '__VALUE__',
                 'value'    => $valuel,
                 'myname'   => $mynamel];

      echo "<span id='show_$mynamel$rand'>\n";
      if ($value_type > 0) {
         self::showAllLocations($mynamel, $value_type, $valuel);
      } else {
         echo "&nbsp;\n";
      }
      Ajax::updateItemOnSelectEvent($field_id, "show_$mynamel$rand",
                                    Plugin::getWebDir('geststock')."/ajax/dropdownLocations.php",
                                    $params);
      echo "</span>\n";

      $params2 = ['entity'   => '__VALUE__',
                  'value'    => $valuet,
                  'myname'   => $mynamet];

      echo "<span id='show_$mynamet$rand'>\n";
      if ($value_type > 0) {
         self::showAllTickets($mynamet, $value_type, $valuet);
      } else {
         echo "&nbsp;\n";
      }
      Ajax::updateItemOnSelectEvent($field_id, "show_$mynamet$rand",
                                    Plugin::getWebDir('geststock')."/ajax/dropdownTickets.php",
                                    $params2);
      echo "</span>";

      return $rand;
   }


   static function showAllLocations($name, $entity, $location=0) {

      Location::dropdown(['width'    => '80%',
                          'name'     => $name,
                          'entity'   => $entity,
                          'value'    => $location,
                          'addicon'  => false,
                          'comments' => false]);
   }


   static function showAllTickets($name, $entity, $ticket=0) {
      global $DB;

      $ticketlist = [];
      foreach ($DB->request(['SELECT'  => 'tickets_id',
                             'FROM'    => 'glpi_plugin_geststock_reservations']) as $data) {
         $ticketlist[] = $data['tickets_id'];
      }

      $condition = [['NOT' => ['status' => array_merge(Ticket::getSolvedStatusArray(),
                                                       Ticket::getClosedStatusArray())]],
                    ['NOT' => ['id' => $ticketlist]],
                    'type' => Ticket::DEMAND_TYPE];

      Ticket::dropdown(['width'     => '80%',
                        'name'      => $name,
                        'entity'    => $entity,
                        'value'     => $ticket,
                        'addicon'   => false,
                        'comments'  => false,
                        'condition' => $condition]);
   }


   static function showAllModels($name, $itemtype, $model=0, $number=[]) {
      global $CFG_GLPI;

      $rand  = mt_rand();

      $itemtypename = $itemtype.'Model';
      if ($itemtype == "PluginSimcardSimcard") {
         $itemtypename = 'PluginSimcardSimcardType';
      }
      Dropdown::show($itemtypename, ['width'    => '80%',
                                     'name'     => $name,
                                     'value'    => $model,
                                     'rand'     => $rand,
                                     'addicon'  => false,
                                     'comments' => false]);
      $field_id = Html::cleanId("dropdown_".$name.$rand);

      echo "<span id='show_number$rand' style='spacing:5px'>\n";
      $config = new PluginGeststockConfig();
      $config->getFromDB(1);
      $entity = $config->fields['entities_id_stock'];
      $params = ['itemtype'    => $itemtype,
                 'model'       => '__VALUE__',
                 'entity'      => $entity];

      if ($model > 0) {
         self::showAllNumbers($itemtype, $model, $number);
      } else {
        echo "&nbsp;\n";
      }
      Ajax::updateItemOnSelectEvent($field_id, "show_number$rand",
                                    Plugin::getWebDir('geststock')."/ajax/dropdownNumber.php",
                                    $params);
      echo "</span>";
   }


   static function showAllNumbers($itemtype, $model, $number=[]) {
      global $DB;

      $nb     = 0;
      $config = new PluginGeststockConfig();
      $config->getFromDB(1);
      $entity = $config->fields['entities_id_stock'];

      echo "<table><tr>";
      $find = false;
      $i = 0;
      foreach ($DB->request('glpi_locations',
               ['entities_id' => $entity]) as $location) {
         $nb = PluginGeststockReservation_Item::countAvailable($itemtype, $model,
                                                               $entity, $location['id']);
         if ($nb > 0) {
            if ($i == 3) {
               echo "<tr>";
            }
            echo "<td>".sprintf(__('%1$s (%2$s)'), $location['name'],
                  "<font class='blue b'>".$nb."</font>")."</td><td>";
            $val = isset($number[$location['id']]) ? $number[$location['id']] : 0;
            Dropdown::showNumber("_nbrereserv[".$location['id']."]", ['value' => $val,
                                                                      'min'   => 0,
                                                                      'max'   => $nb]);
            $find = true;
            $i++;
            echo "</td>";
            if ($i == 3) {
               $i = 0;
               echo "</tr>";
            }
         }
      }
      if (!$find) {
         echo "<td><font class='red b'>".__('No free item', 'geststock')."</font></td>";
      }
      echo "<tr></table>";

   }


   static function getSpecificValueToDisplay($field, $values, array $options=[]) {
      global $DB;

      if (!is_array($values)) {
         $values = [$field => $values];
      }

      switch ($field) {
         case 'status' :
            if ($options['html']) {
               return "<div style='background-color:".self::getStatusColor($values[$field])."';\">".
                        self::getStatusName($values[$field]).'</div>';
            }
            return self::getStatusName($values[$field]);

         case 'type_tova' :
            return self::getStatusTova($values[$field]);
      }
      return parent::getSpecificValueToDisplay($field, $values, $options);
   }


   static function getSpecificValueToSelect($field, $name='', $values='', array $options=[]) {
       global $DB;

       if (!is_array($values)) {
          $values = [$field => $values];
       }
       $options['display'] = false;

       switch ($field) {
          case 'status' :
             $options['value']  = $values[$field];
             return self::dropdownStatus($name, $options);

          case 'locations_id' :
             $options['value']        = $values[$field];
             $options['name']         = $name;
             $options['entity']       = 0;
             $options['entity_sons']  = true;
             return Location::dropdown($options);

          case 'entities_id_deliv' :
             $options['value']        = $values[$field];
             $options['name']         = $name;
             $options['entity']       = 0;
             $options['entity_sons']  = true;
             return Entity::dropdown($options);

          case 'type_tova' :
             $options['value']  = $values[$field];
             $options['tova']   = true;
             return self::dropdownStatus($name, $options);
        }
       return parent::getSpecificValueToSelect($field, $name, $values, $options);
   }


   static function dropdownStatus($name, $options=[]) {

      $params['value']       = 0;
      $params['toadd']       = [];
      $params['on_change']   = '';
      $params['display']     = true;
      $params['tova']        = false;

      if (is_array($options) && count($options)) {
         foreach ($options as $key => $val) {
            $params[$key] = $val;
         }
      }

      $items = [];
      if (count($params['toadd']) > 0) {
         $items = $params['toadd'];
      }

      if ($params['tova']) {
         $items += self::getAllStatusTova();
      } else {
         $items += self::getAllStatusArray();
      }

      return Dropdown::showFromArray($name, $items, $params);
   }


   static function dropdownEntity($name, $options) {
      global $DB;

      $ent = [];
      foreach ($DB->request('glpi_profiles_users',
                            ['users_id' => Session::getLoginUserID()]) as $data) {
         $ent[$data['entities_id']] = Dropdown::getDropdownName('glpi_entities', $data['entities_id']);
      }

      $params['value']       = 0;
      $params['toadd']       = [];
      $params['on_change']   = '';
      $params['display']     = true;

      if (is_array($options) && count($options)) {
         foreach ($$options as $key => $val) {
            $params[$key] = $val;
         }
      }

      $items = [];
      if (count($params['toadd']) > 0) {
         $items = $params['toadd'];
      }

      $items += $ent;

      return Dropdown::showFromArray($name, $items, $params);
   }


   function getTabNameForItem(CommonGLPI $item, $withtemplate=0) {

      $dbu = new DbUtils();
      if (static::canView()) {
         $nb = 0;
         switch ($item->getType()) {
            case 'Ticket' :
               if ($_SESSION['glpishow_count_on_tabs']) {
                  $nb = $dbu->countElementsInTable('glpi_plugin_geststock_reservations',
                                                   ['tickets_id' => $item->getID()]);
               }
               return self::createTabEntry(self::getTypeName(Session::getPluralNumber()), $nb);
         }
      }
      return '';
   }


   static function displayTabContentForItem(CommonGLPI $item, $tabnum=1, $withtemplate=0) {

      switch ($item->getType()) {
         case 'Ticket' :
            self::showForTicket($item);
            break;
      }
      return true;
   }


   static function showForTicket(Ticket $ticket) {
      global $DB, $CFG_GLPI;

      $ID = $ticket->getField('id');
      if (!$ticket->can($ID, READ)) {
         return false;
      }

      $dbu  = new DbUtils();
      $rand = mt_rand();

      $nb    = $dbu->countElementsInTable('glpi_plugin_geststock_reservations',
                                          ['tickets_id' => $ticket->getID(),
                                           'is_deleted' => 0]);
      $nbdel = $dbu->countElementsInTable('glpi_plugin_geststock_reservations',
                                          ['tickets_id' => $ticket->getID(),
                                           'is_deleted' => 1]);
      if (Session::haveRight(self::$rightname, READ)) {
         if (Session::haveRight(self::$rightname, CREATE)
             && ($ticket->fields['type'] != Ticket::DEMAND_TYPE)) {
             echo "<p><font class='red b'>".__("can't create a ticket for ticket type incident",
                                               'geststock')."</font></p>";
             exit;
          }
          if ($nbdel > 0) {
            echo "<p><font class='red b'>".__('Reservation present in dustbin', 'geststock')."</font></p>";
         } else if ($nb == 0) {
            echo "<div class='firstbloc'>";
            echo "<form name='reservationticket_form$rand' id='reservationticket_form$rand'
                   method='post' action='".Toolbox::getItemTypeFormURL(__CLASS__)."'>";

            echo "<table class='tab_cadre_fixe'>";
            echo "<tr class='tab_bg_2'>";
            echo "<th colspan='3'>".__('Add a reservation', 'geststock')."</th></tr>";

            echo "<tr class='tab_bg_2 center'><td>";
            echo Html::hidden('tickets_id', ['value' => $ID]);
            echo Html::hidden('entities_id', ['value' => $ticket->fields['entities_id']]);
            if (Session::haveRight(self::$rightname, CREATE)) {
               echo "<a href='".Toolbox::getItemTypeFormURL(__CLASS__)."?tickets_id=$ID'>";
               echo __('Create a reservation from this ticket', 'geststock');
               echo "</a>";
            }
            echo "</td></tr></table>";
            Html::closeForm();
            echo "</div>";

         } else {
            foreach ($DB->request('glpi_plugin_geststock_reservations',
                                  ['tickets_id' => $ticket->getField('id')], true) as $resaid) {

               $resa = new self();
               $resa->getFromDB($resaid['id']);
            }
            PluginGeststockReservation_Item::showForItem($resa);
         }
      }
   }


   static function getAllStatusTova() {

      $tab = [self::ACCOMP       => _x('status',  'Accompanied', 'geststock'),
              self::ACCRAILWAY   => _x('status', 'Accompanied RAILWAY', 'geststock'),
              self::DEPFLIGHT    => _x('status', 'Flight department', 'geststock'),
              self::MAIL         => _x('status', 'Mail', 'geststock'),
              self::ACCMAIL      => _x('status', 'Accompanied mail', 'geststock'),
              self::SECFREIGHT   => _x('status', 'Secure freight', 'geststock'),
              self::FREIGHT      => _x('status', 'Freight', 'geststock'),
              self::FRRAILWAY    => _x('status', 'Freight RAILWAY', 'geststock'),
              self::FRPTT        => _x('status', 'Freight PTT', 'geststock'),
              self::SHUTTLE      => _x('status', 'Nantes shuttle', 'geststock'),
              self::ROAD         => _x('status', 'Road', 'geststock')];

      return $tab;
   }


   static function getStatusTova($value) {

      switch ($value) {
         case 7 :
            return _x('status', 'Accompanied', 'geststock');

         case 8 :
            return _x('status', 'Accompanied RAILWAY', 'geststock');

         case 9 :
            return _x('status', 'Flight department', 'geststock');

         case 10 :
            return _x('status', 'Mail', 'geststock');

         case 11 :
            return _x('status', 'Accompanied mail', 'geststock');

         case 12 :
            return _x('status', 'Secure freight', 'geststock');

         case 13 :
            return _x('status', 'Freight', 'geststock');

         case 14 :
            return _x('status', 'Freight RAILWAY', 'geststock');

         case 15 :
            return _x('status', 'Freight PTT', 'geststock');

         case 16 :
            return _x('status', 'Nantes shuttle', 'geststock');

         case 17 :
            return _x('status', 'Road', 'geststock');
      }
   }


   function show_PDF($pdf) {

      $pdf->setColumnsSize(50,50);
      $col1 = '<b>'.sprintf(__('%1$s %2$s'), __('ID'), $this->fields['id']).'</b>';
      if (isset($this->fields["date_mod"])) {
         $col2 = sprintf(__('%1$s: %2$s'), __('Last update'),
                         Html::convDateTime($this->fields["date_mod"]));
      } else {
         $col2 = '';
      }
      $pdf->displayTitle($col1, $col2);

      $pdf->setColumnsSize(100);
      $pdf->displayLine(sprintf(__('%1$s: %2$s'), '<b><i>'.__('Delivery entity', 'geststock').'</i></b>',
                                Dropdown::getDropdownName('glpi_entities',
                                                          $this->fields['entities_id_deliv'])));

      $pdf->displayLine(sprintf(__('%1$s: %2$s'), '<b><i>'.__('Delivery location', 'geststock').'</i></b>',
                                Dropdown::getDropdownName('glpi_locations',
                                                          $this->fields['locations_id'])));

      $pdf->setColumnsSize(50,50);
      $pdf->displayLine(sprintf(__('%1$s: %2$s'), '<b><i>'.__('Ticket').'</i></b>',
                                Toolbox::stripTags(Dropdown::getDropdownName('glpi_tickets',
                                                                             $this->fields['tickets_id']))),
                        sprintf(__('%1$s: %2$s'), '<b><i>'.__('Status').'</i></b>',
                                Toolbox::stripTags(self::getStatusName($this->fields['status']))));

      $pdf->displayLine(sprintf(__('%1$s: %2$s'), '<b><i>'.__('Delivery date', 'geststock').'</i></b>',
                                Html::convDateTime($this->fields['date_whished'])),
                        sprintf(__('%1$s: %2$s'), '<b><i>'.__('Receipt date', 'geststock').'</i></b>',
                                Html::convDateTime($this->fields['receipt_date'])));

      if (PluginGeststockConfig::TOVA == 1) {
         $pdf->setColumnsSize(30,30,40);
         $pdf->displayLine(sprintf(__('%1$s: %2$s'), '<b><i>Date Tova </i></b>',
                                   Html::convDateTime($this->fields['date_tova'])),
                           sprintf(__('%1$s: %2$s'), '<b><i>Numéro de valise </i></b>',
                                   $this->fields['number_tova']),
                           sprintf(__('%1$s: %2$s'), '<b><i>Type de valise </i></b>',
                                    Toolbox::stripTags(self::getStatusTova($this->fields['type_tova']))));
      }

      $pdf->setColumnsSize(100);
      $pdf->displayText(sprintf(__('%1$s: %2$s'), '<b><i>'.__('Comments').'</i></b>',
                                $this->fields['comment']));

      $pdf->displaySpace();
   }


   function transfertItem($ticket) {
      global $DB;

      $reservation = new self();
      $resaitem    = new PluginGeststockReservation_Item();
      $otherserial = [];
      $config      = new PluginGeststockConfig();
      $config->getFromDB(1);
      foreach ($DB->request('glpi_plugin_geststock_reservations',
                            ['tickets_id' => $ticket]) as $resa) {
         foreach ($DB->request('glpi_plugin_geststock_reservations_items',
                               ['plugin_geststock_reservations_id' => $resa['id']]) as $ri) {
            foreach ($DB->request('glpi_plugin_geststock_reservations_items_numbers',
                                  ['plugin_geststock_reservations_items_id' => $ri['id']]) as $nbre) {

               $item        = new $ri['itemtype']();
               $otherserial = importArrayFromDB($nbre['otherserial']);
               $listexport  = [];

               foreach ($otherserial as $serial => $val) {
                  if ($item->getFromDB($val)) {
                     //Teste si l'élément est bien en transit (c'est à dire non modifié manuellement) JMC
                     if ($item->getField('states_id') == $config->fields['transit_status']) {
                        // move item to new entity
                        $item->update(['id'            => $val,
                                       'entities_id'   => $resa['entities_id_deliv'],
                                       'locations_id'  => $resa['locations_id'],
                                       'states_id'     => $config->fields['stock_status']]);
                     }
                     $listexport[] = $item->getField($config->fields['criterion']);
                  } else {
                     break;
                  }
                  $itemticket = new Item_Ticket();
                  $newid = $itemticket->add(['itemtype'   => $ri['itemtype'],
                                             'items_id'   => $val,
                                             'tickets_id' => $ticket]);
               }
            }
         }
         if ($reservation->getFromDB($resa['id'])) {
            $reservation->update(['id'            => $resa['id'],
                                  'receipt_date'  => $_SESSION["glpi_currenttime"],
                                  'status'        => $config->fields['transit_status']]);
         }

         $fup = new ITILFollowup();
         $fup->add(['itemtype'    => 'Ticket',
                    'items_id'    => $ticket,
                    'content'     => sprintf(__('%1$s %2$s'),
                                             __('Items removed from stock on ', 'geststock'),
                                             $_SESSION["glpi_currenttime"]." (".implode(',',$listexport).")"),
                    'date'        =>  $_SESSION["glpi_currenttime"],
                    'users_id'    => Session::getLoginUserID()]);
       }
   }


   static function isHoliday($date) {
      global $DB;

      $config  = new PluginGeststockConfig();
      $config->getFromDB(1);

      $query = "SELECT COUNT(*) AS cpt
                FROM `glpi_calendars_holidays`
                INNER JOIN `glpi_holidays`
                     ON (`glpi_calendars_holidays`.`holidays_id` = `glpi_holidays`.`id`)
                LEFT JOIN `glpi_calendars`
                     ON (`glpi_calendars_holidays`.`calendars_id` = `glpi_calendars`.`id`)
                WHERE `glpi_calendars`.`entities_id` = '".$config->fields["entities_id_stock"]."'
                      AND (('$date' <= `glpi_holidays`.`end_date`
                            AND '$date' >= `glpi_holidays`.`begin_date`)
                           OR (`glpi_holidays`.`is_perpetual` = 1
                               AND MONTH(`end_date`)*100 + DAY(`end_date`)
                                                                >= ".date('nd',strtotime($date))."
                               AND MONTH(`begin_date`)*100 + DAY(`begin_date`)
                                                                <= ".date('nd',strtotime($date))."
                              )
                           )";
      if ($result = $DB->request($query)) {
         foreach ($result as $row) {
            return $row['cpt'];
         }
      }
         return false;
      }
}
