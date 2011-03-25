<?php
/**
 * <!--
 * This file is part of the adventure php framework (APF) published under
 * http://adventure-php-framework.org.
 *
 * The APF is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * The APF is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with the APF. If not, see http://www.gnu.org/licenses/lgpl-3.0.txt.
 * -->
 */
import('modules::genericormapper::data::tools', 'GenericORMapperSetup');

/**
 * @package modules::genericormapper::data::tools
 * @class GenericORMapperUpdate
 *
 * The <strong>GenericORMapperUpdate</strong> can be used to update generic or mapper
 * installations that have been created using the <strong>GenericORMapperSetup</strong>
 * utility. Please note, that manual setups are not fully supported, due to the fact,
 * that table and/or columns names may be different to the default layout generated by
 * the setup tool.
 * <p/>
 * If you have adapted an automatic generated table layout, please remember the steps
 * and execute them after update. This is necessary for additional indexes, in case the
 * columns having a custom index are changed.
 * <p/>
 * In order to adapt the automatically generated change-set, please ensure the last param
 * to be <em>false</em>. This results in displaying the change statements rather to execute
 * them against the given database.
 *
 * @author Christian Achatz
 * @version
 * Version 0.1, 04.10.2009<br />
 */
class GenericORMapperUpdate extends GenericORMapperSetup {

   /**
    * @var string[] Mapping table reconstructed from the given database connection.
    */
   private $ReEngineeredMappingTable = array();
   private $DatabaseMappingTables = array();

   /**
    * @var string[] Relation table reconstructed from the given database connection.
    */
   private $ReEngineeredRelationTable = array();
   private $DatabaseRelationTables = array();

   /**
    * @var string[] Stores the new mapping entries.
    */
   private $NewMappings = array();

   /**
    * @var string[] Stores the removed mapping entries.
    */
   private $RemovedMappings = array();

   /**
    * @var string[] Stores the attributes of mapping entries, that have been added.
    */
   private $NewMappingAttributes = array();

   /**
    * @var string[] Stores the attributes of mapping entries, that have been removed.
    */
   private $RemovedMappingAttributes = array();

   /**
    * @var string[] Stores the attributes of mapping entries, that have been altered.
    */
   private $AlteredMappingAttributes = array();

   /**
    * @var string[] Stores the new relation entries.
    */
   private $NewRelations = array();

   /**
    * @var string[] Stores the removed relation entries.
    */
   private $RemovedRelations = array();

   /**
    * @var string[] Stores the attributes of relation entries, that have been altered.
    */
   private $AlteredRelationAttributes = array();
   
   /**
    * @var string[] Stores the update statements.
    */
   private $UpdateStatements = array();

   /**
    * @public
    *
    * Updates a database, that was setup with the {@link GenericORMapperSetup} tool. You can
    * choose between direct update (<em>$updateInPlace=true</em>) and displaying the update
    * statements for manual update (<em>$updateInPlace=false</em>). Default is direct update.
    *
    * @param string $configNamespace namespace, where the desired mapper configuration is located
    * @param string $configNameAffix name affix of the object and relation definition files
    * @param string $connectionName name of the connection, that the mapper should use to access the database
    * @param boolean $updateInPlace Defines, if the update should be done for you (true) or if
    *                               the update statement should only be displayed (false).
    *                               Default is true.
    *
    * @author Christian Achatz
    * @version
    * Version 0.1, 04.10.2009<br />
    */
   public function updateDatabase($configNamespace, $configNameAffix, $connectionName, $updateInPlace = true) {

      // connection must be present, otherwise update is not possible
      if (empty($connectionName)) {
         throw new GenericORMapperException('[GenericORMapperUpdate::updateDatabase()] Connection name may not be null!',
                 E_USER_ERROR);
      }

      // set the config namespace
      $this->configNamespace = $configNamespace;

      // set the config name affix
      $this->configNameAffix = $configNameAffix;

      // setup object layout (new)
      $this->createMappingTable();

      // setup relation layout (new)
      $this->createRelationTable();

      // generate layout from the database (reverse engineering of the database)
      $cM = &$this->getServiceObject('core::database', 'ConnectionManager');
      $sql = &$cM->getConnection($connectionName);

      // analyze the current database
      $this->analyzeDatabaseTables($sql);

      // re-engineer the database tables concerning the relations
      $this->reEngineerRelations($sql);

      // re-engineer the database tables concerning the objects
      $this->reEngineerMappings($sql);

      // analyze the old and new mapping configuration
      $this->analyzeMappingConfigurationChanges();

      // generate mapping update statements
      $this->generateMappingUpdateStatements();

      // analyze old and new relation configuration
      $this->analyzeRelationConfigurationChanges();

      // generate relation update statements
      $this->generateRelationUpdateStatements();

      // analyze potential changes in storage engines
      // NOTE: this option is commented out by now, since the mechanism
      // implemented by 1.14-alpha is not sufficent for different storage
      // engines by table. Within further checkins we will provide a
      // mechanism to specify the storage engine per table. Then this
      // functionality will be re-enabled!
      //$this->generateStorageEngineUpdate($sql);

      // print alter statements or execute them immediately
      if ($updateInPlace === true) {
         foreach ($this->UpdateStatements as $statement) {
            $sql->executeTextStatement($statement);
         }
      } else {
         echo '<pre>';
         foreach ($this->UpdateStatements as $statement) {
            echo $statement . PHP_EOL . PHP_EOL . PHP_EOL;
         }
         echo '</pre>';
      }
   }

   /**
    * Compares two mapping keys. In case of mappings case sensitive comparison is done.
    *
    * @param string $a The first key.
    * @param string $b The second key.
    * @return int Compare status (0=equal, 1=different).
    */
   private function compareMappings($a, $b) {
      if ($a === $b) {
         return 0;
      }
      return 1;
   }

   private function compareMappingValues($a, $b) {
      return $this->compareMappings($a, $b);
   }

   /**
    * Compares two relation keys. In case of relations case insensitive comparison is done.
    *
    * @param string $a The first key.
    * @param string $b The second key.
    * @return int Compare status (0=equal, 1=different).
    */
   private function compareRelations($a, $b) {
      $a = strtolower($a);
      $b = strtolower($b);
      if ($a === $b) {
         return 0;
      }
      return 1;
   }

   /**
    * Returns the type of
    *
    * @param string $tableName The
    * @return string The relation type declarator.
    */
   private function getRelationTypeLabel($tableName) {
      $tableName = strtolower($tableName);
      if (substr_count($tableName, 'ass_') > 0) {
         return 'ASSOCIATION';
      }
      return 'COMPOSITION';
   }

   /**
    * @private
    *
    * Returns the fields, that are relevant for comparison.
    *
    * @param string[] $fields
    * @return string[] The fields relevant for comparison.
    *
    * @author Christian Achatz
    * @version
    * Version 0.1, 10.10.2009<br />
    * Version 0.2, 13.10.2009 (Corrected check for primary key)<br />
    */
   private function getRelevantFields($fields) {
      $resultFields = array();

      foreach ($fields as $field) {
         if ($field['Key'] != 'PRI' // do exclude primary key, but allow MUL indices!
                 && $field['Field'] != 'CreationTimestamp'
                 && $field['Field'] != 'ModificationTimestamp') {
            $resultFields[] = $field;
         }
      }

      return $resultFields;
   }

   /**
    * @private
    *
    * Returns the name of the primary key.
    *
    * @param string[] $fields The current definition's fields.
    * @return string The name of the primary key.
    */
   private function getPrimaryKeyName($fields) {
      foreach ($fields as $field) {
         if ($field['Key'] == 'PRI') {
            return $field['Field'];
         }
      }
   }

   /**
    * @private
    *
    * Analyzes the given database and stores the tables included.
    *
    * @param AbstractDatabaseHandler $sql The database connection to analyze.
    *
    * @author Christian Achatz
    * @version
    * Version 0.1, 10.10.2009<br />
    * Version 0.2, 21.11.2010 (Now ignoring tables that are not created by the GORM)<br />
    */
   private function analyzeDatabaseTables(AbstractDatabaseHandler $sql) {

      $selectTables = 'SHOW TABLES;';
      $resultTables = $sql->executeTextStatement($selectTables);

      while ($dataTables = $sql->fetchData($resultTables)) {

         // gather the offset we are provided by the database due
         // to the fact, that we ordered an associative array!
         $keys = array_keys($dataTables);
         $offset = $keys[0];

         // collect tables
         if (substr_count($dataTables[$offset], 'ent_') == 1) {
            $this->DatabaseMappingTables[] = $dataTables[$offset];
         } elseif (substr_count($dataTables[$offset], 'ass_') == 1) {
            $this->DatabaseRelationTables[] = $dataTables[$offset];
         } elseif (substr_count($dataTables[$offset], 'cmp_') == 1) {
            $this->DatabaseRelationTables[] = $dataTables[$offset];
         }
      }
   }

   /**
    * @private
    *
    * Creates a relation mapping out of the database tables.
    *
    * @param AbstractDatabaseHandler $sql The database connection to analyze.
    * @version
    * Version 0.2, 07.03.2011 (Added support for relations between the same table)<br />
    */
   private function reEngineerRelations(AbstractDatabaseHandler $sql) {

      // create reverse engineered mapping entries
      foreach ($this->DatabaseRelationTables as $relationTable) {

         $selectCreate = 'SHOW COLUMNS FROM ' . $relationTable;
         $resultCreate = $sql->executeTextStatement($selectCreate);

         $fields = array();
         while ($dataCreate = $sql->fetchData($resultCreate)) {
            $fields[] = $dataCreate;
         }

         $relationName = substr($relationTable, 4);
         $sourceId = $fields[0]['Field'];
         $targetId = $fields[1]['Field'];
         $sourceObject = str_replace('ID', '', $sourceId);
         $targetObject = str_replace('ID', '', $targetId);

         $this->ReEngineeredRelationTable[$relationName] = array(
             'Type' => $this->getRelationTypeLabel($relationTable),
             'Table' => $relationTable,
             'SourceID' => $sourceId,
             'TargetID' => $targetId,
             'SourceObject' => str_replace('Source_', '', $sourceObject),
             'TargetObject' => str_replace('Target_', '', $targetObject)
         );
      }
   }

   /**
    * @private
    *
    * Analyzes the current set of database tables and adds an alter statement for the
    * storage engine if necessary.
    *
    * @param AbstractDatabaseHandler $sql The current database connnection.
    *
    * @author Christian Achatz
    * @version
    * Version 0.1, 12.03.2011<br />
    */
   private function generateStorageEngineUpdate(AbstractDatabaseHandler $sql) {

      foreach ($this->DatabaseMappingTables as $objectTable) {

         $selectEngine = 'SHOW CREATE TABLE `' . $objectTable . '`';
         $resultEngine = $sql->executeTextStatement($selectEngine);
         $dataEngine = $sql->fetchData($resultEngine);

         preg_match('/\s*?ENGINE=([^\s]+)\s*?/', $dataEngine['Create Table'], $matches);
         $engine = $matches[1];

         if ($this->getStorageEngine() != $engine) {
            $this->UpdateStatements[] = 'ALTER TABLE `' . $objectTable . '` ENGINE = ' . $this->getStorageEngine() . ';';
         }
      }
   }

   /**
    * @private
    *
    * Creates a object mapping out of the database tables.
    *
    * @param AbstractDatabaseHandler $sql The database connection to analyze.
    */
   private function reEngineerMappings(AbstractDatabaseHandler $sql) {

      foreach ($this->DatabaseMappingTables as $objectTable) {

         $selectCreate = 'SHOW COLUMNS FROM ' . $objectTable;
         $resultCreate = $sql->executeTextStatement($selectCreate);

         $fields = array();
         while ($dataCreate = $sql->fetchData($resultCreate)) {
            $fields[] = $dataCreate;
         }
         $mainFields = $this->getRelevantFields($fields);
         $primaryKey = $this->getPrimaryKeyName($fields);
         $objectName = str_replace('ID', '', $primaryKey);

         $objectFields = array();
         foreach ($mainFields as $field) {

            $objectFields[$field['Field']] = strtoupper($field['Type']);


            /* Wenn das Feld NULL sein darf, bewirkt KEINE Angabe eines DEFAULTs, dass DEFAULT NULL gesetzt wird. Wird hingegen
              ein benutzerdefinierter String genutzt, wird dieser in der Spalte "Default" ausgegeben. Dazu zählt auch ein leerer
              String. Sprich: Steht hier kein "NULL", ist es DEFAULT ''. Steht hier ein NULL, kann es jedoch nichts oder aber
              DEFAULT NULL sein. Kann man da vielleicht eine Ausnahme programmieren? Sonst wäre es doof, wenn man stetig den
              DEFAULT-Value angeben müsste!? Bei den Feldern, die NICHT NULL sein dürfen, ist es etwas konfus: Ich darf zwar kein
              DEFAULT NULL angeben, aber lasse ich DEFAUL weg, bekomme ich als Standard "NULL" zurückgeliefert. Sehr merkwürdig, aber
              in diesem Falle ist es wohl so, dass dies bedeuten soll, dass hier KEIN DEFAULT gesetzt wird. Ansonsten gilt bei Angabe
              einer Zeichenkette das Gleiche wie bei NULL-Feldern. */


            /*
              CREATE TABLE IF NOT EXISTS `test` (
              `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
              `Test1` varchar(10) ,
              `Test2` varchar(10) DEFAULT '',
              `Test3` varchar(10) DEFAULT NULL,
              `Test4` varchar(10) NOT NULL,
              `Test5` varchar(10) NOT NULL DEFAULT '',
              PRIMARY KEY (`ID`)
              ) ENGINE=MyISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

              +-------+------------------+------+-----+---------+----------------+
              | Field | Type             | Null | Key | Default | Extra          |
              +-------+------------------+------+-----+---------+----------------+
              | ID    | int(10) unsigned | NO   | PRI | NULL    | auto_increment |
              | Test1 | varchar(10)      | YES  |     | NULL    |                |
              | Test2 | varchar(10)      | YES  |     |         |                |
              | Test3 | varchar(10)      | YES  |     | NULL    |                |
              | Test4 | varchar(10)      | NO   |     | NULL    |                |
              | Test5 | varchar(10)      | NO   |     |         |                |
              +-------+------------------+------+-----+---------+----------------+
             */

            // correct empty NULL values as NO for MySQL4
            if (empty($field['Null'])) {
               $field['Null'] = 'NO';
            }

            //
            /* if($field['Null'] == 'YES' && $field['Default'] == 'NULL'){
              $objectFields[$field['Field']] .= '';
              }
              elseif($field['Null'] == 'YES' && empty($field['Default'])){
              $objectFields[$field['Field']] .= 'DEFAULT \'\'';
              }
              elseif($field['Null'] == 'NO' && $field['Default'] == 'NULL'){
              $objectFields[$field['Field']] .= 'NOT NULL';
              }
              elseif($field['Null'] == 'NO' && empty($field['Default'])){
              $objectFields[$field['Field']] .= 'NOT NULL DEFAULT \'\'';
              } */

            // add a null/not null indicator to preserve the correct datatype
            if ($field['Null'] == 'NO') {
               $objectFields[$field['Field']] .= ' NOT NULL';
            } else {
               $objectFields[$field['Field']] .= ' NULL';
            }

            // add default indicator to preserve correct data type
            if (!empty($field['Default'])) {
               $objectFields[$field['Field']] .= ' DEFAULT \'' . $field['Default'] . '\'';
            } else {
               $objectFields[$field['Field']] .= ' DEFAULT \'\'';
            }

         }

         $this->ReEngineeredMappingTable[$objectName] = array_merge(
                         array(
                             'ID' => $primaryKey,
                             'Table' => $objectTable
                         ),
                         $objectFields
         );
      }
   }

   /**
    * @private
    *
    * Generates update statements for the mapping configuration.
    *
    * @author Christian Achatz
    * @version
    * Version 0.1, 04.10.2009<br />
    * Version 0.2, 13.10.2009 (Added ticks to delimit table names.)<br />
    */
   private function generateMappingUpdateStatements() {

      foreach ($this->NewMappings as $newMapping => $DUMMY) {
         $this->UpdateStatements[] =
                 $this->generateMappingTableLayout(
                         $newMapping,
                         $this->MappingTable[$newMapping]
         );
      }

      foreach ($this->RemovedMappings as $removedMapping => $DUMMY) {
         $this->UpdateStatements[] = 'DROP TABLE '
                 . $this->ReEngineeredMappingTable[$removedMapping]['Table']
                 . ';';
      }

      foreach ($this->NewMappingAttributes as $newAttribute => $values) {

         if (count($values) > 0) {

            foreach ($values as $name => $dataType) {
               $dataType = preg_replace(
                               $this->RowTypeMappingFrom,
                               $this->RowTypeMappingTo,
                               $dataType
               );

               // add dynamic character set specification
               $dataType = str_replace('[charset]', $this->getTableCharset(), $dataType);

               $this->UpdateStatements[] = 'ALTER TABLE `'
                       . $this->MappingTable[$newAttribute]['Table'] . '` ADD `'
                       . $name . '` ' . $dataType . ';';
            }
         }
      }

      foreach ($this->RemovedMappingAttributes as $removedAttribute => $values) {

         if (count($values) > 0) {

            foreach ($values as $name => $dataType) {
               $this->UpdateStatements[] = 'ALTER TABLE `'
                       . $this->MappingTable[$removedAttribute]['Table'] . '` DROP `' . $name . '`;';
            }
         }
      }

      foreach ($this->AlteredMappingAttributes as $alteredAttribute => $values) {

         if (count($values) > 0) {

            foreach ($values as $name) {
               $dataType = preg_replace(
                               $this->RowTypeMappingFrom,
                               $this->RowTypeMappingTo,
                               $this->MappingTable[$alteredAttribute][$name]
               );

               // add dynamic character set specification
               $dataType = str_replace('[charset]', $this->getTableCharset(), $dataType);

               $this->UpdateStatements[] = 'ALTER TABLE `'
                       . $this->MappingTable[$alteredAttribute]['Table'] . '` CHANGE `' . $name . '` '
                       . '`' . $name . '` ' . $dataType . ';';
            }
         }
      }
   }

   /**
    * @private
    *
    * Analyzes the old and new mapping configuration and stores the changes locally.
    */
   private function analyzeMappingConfigurationChanges() {

      // gather overall mapping changes
      $this->NewMappings = array_diff_ukey(
                      $this->MappingTable,
                      $this->ReEngineeredMappingTable,
                      array($this, 'compareMappings')
      );
      $this->RemovedMappings = array_diff_ukey(
                      $this->ReEngineeredMappingTable,
                      $this->MappingTable,
                      array($this, 'compareMappings')
      );

      // evaluate changes within the attributes
      foreach ($this->MappingTable as $mappingKey => $mappingValue) {

         // only scan entries, that are not within the new and removed ones!
         if (!isset($this->NewMappings[$mappingKey])
                 && !isset($this->RemovedMappings[$mappingKey])) {

            // new columns
            $this->NewMappingAttributes[$mappingKey] = array_diff_ukey(
                            $this->MappingTable[$mappingKey],
                            $this->ReEngineeredMappingTable[$mappingKey],
                            array($this, 'compareMappings')
            );

            // removed columns
            $this->RemovedMappingAttributes[$mappingKey] = array_diff_ukey(
                            $this->ReEngineeredMappingTable[$mappingKey],
                            $this->MappingTable[$mappingKey],
                            array($this, 'compareMappings')
            );

            // changed columns
            foreach ($this->MappingTable[$mappingKey] as $key => $value) {

               // only scan entries, that are also existent within the re-engineered mapping table!
               if (isset($this->ReEngineeredMappingTable[$mappingKey][$key])) {
                  $diff = $this->compareMappingValues(
                                  $this->MappingTable[$mappingKey][$key],
                                  $this->ReEngineeredMappingTable[$mappingKey][$key]
                  );
                  if ($diff === 1) {
                     $this->AlteredMappingAttributes[$mappingKey][] = $key;
                  }
               }
            }
         }
      }
   }

   /**
    * @private
    *
    * Analyzes the old and new relation configuration and stores the changes locally.
    * With relations, only type changes can be applied. Otherwise the data structure
    * gets corrupted!
    */
   private function analyzeRelationConfigurationChanges() {

      // new relations
      $this->NewRelations = array_diff_ukey(
                      $this->RelationTable,
                      $this->ReEngineeredRelationTable,
                      array($this, 'compareRelations')
      );

      // removed relations
      $this->RemovedRelations = array_diff_ukey(
                      $this->ReEngineeredRelationTable,
                      $this->RelationTable,
                      array($this, 'compareRelations')
      );

      // evaluate changes within the attributes
      foreach ($this->RelationTable as $relationKey => $relationValue) {

         // use lowercase relation key for re-engineered values!
         $reEngRelationKey = strtolower($relationKey);

         // only scan entries, that are not within the new and removed ones!
         if (!isset($this->NewRelations[$relationKey])
                 && !isset($this->RemovedRelations[$relationKey])) {

            // changed columns (we only check for relation type, because for all other
            // cases, a new relation *must* be created!)
            foreach ($this->RelationTable[$relationKey] as $key => $DUMMY) {

               // only scan entries, that are also existent within the re-engineered
               // relation table! Further, only respect the type key.
               if (isset($this->ReEngineeredRelationTable[$reEngRelationKey][$key])
                       && $key == 'Type') {
                  if ($this->ReEngineeredRelationTable[$reEngRelationKey][$key]
                          !== $this->RelationTable[$relationKey][$key]) {
                     $this->AlteredRelationAttributes[] = $relationKey;
                  }
               }

               if (isset($this->ReEngineeredRelationTable[$reEngRelationKey][$key])
                       && isset($this->ReEngineeredRelationTable[$reEngRelationKey]['TargetID'])
                       && isset($this->ReEngineeredRelationTable[$reEngRelationKey]['Table'])
                       && ($key == 'SourceID')) {
                  $SourceIDColumn = str_replace('Source_', '', $this->ReEngineeredRelationTable[$reEngRelationKey][$key]);
                  $TargetIDColumn = str_replace('Target_', '', $this->ReEngineeredRelationTable[$reEngRelationKey]['TargetID']);
                  if ($SourceIDColumn == $this->ReEngineeredRelationTable[$reEngRelationKey][$key] &&
                          $TargetIDColumn == $this->ReEngineeredRelationTable[$reEngRelationKey]['TargetID']) {
                     // header
                     $update = 'ALTER TABLE `' . $this->ReEngineeredRelationTable[$reEngRelationKey]['Table'] . '` ' . PHP_EOL;

                     // source id
                     $update .= 'CHANGE `' . $SourceIDColumn . '`  `Source_' . $SourceIDColumn . '` ' . $this->getIndexColumnDataType() . ' NOT NULL default \'0\',' . PHP_EOL;

                     // target id
                     $update .= 'CHANGE `' . $TargetIDColumn . '`  `Target_' . $TargetIDColumn . '` ' . $this->getIndexColumnDataType() . ' NOT NULL default \'0\'' . PHP_EOL;

                     $this->UpdateStatements[] = $update;
                  }
               }
            }
         }
      }
   }

   /**
    * @private
    *
    * Generates update statements for relation changes.
    *
    * @author Christian Achatz
    * @version
    * Version 0.1, 11.10.2009<br />
    */
   private function generateRelationUpdateStatements() {

      foreach ($this->NewRelations as $newRelation => $DUMMY) {
         $this->UpdateStatements[] =
                 $this->generateRelationTableLayout($this->RelationTable[$newRelation]);
      }
      foreach ($this->RemovedRelations as $removedRelation => $DUMMY) {
         $this->UpdateStatements[] = 'DROP TABLE '
                 . $this->ReEngineeredRelationTable[$removedRelation]['Table'] . ';';
      }

      // changed relation types: $this->AlteredRelationAttributes
      foreach ($this->AlteredRelationAttributes as $alteredRelation) {
         $reEngAlteredRelation = strtolower($alteredRelation);
         $this->UpdateStatements[] = 'RENAME TABLE `'
                 . $this->ReEngineeredRelationTable[$reEngAlteredRelation]['Table']
                 . '` TO `' . $this->RelationTable[$alteredRelation]['Table'] . '`;';
      }
   }

}
?>