<?php
class NP_DepB extends NucleusPlugin {

   function getName() { return 'NP_DepB'; }
   function getAuthor()  { return 'Edmond Hui (admun)'; }
   function getURL() { return 'http://www.nowhere.com'; }
   function getVersion() { return 'v0.0'; }
   function getDescription() {
      return 'This plugin is a test dummy Dep';
   }

   function supportsFeature($what) {
     switch($what) {
       case 'SqlTablePrefix':
         return 1;
       default:
         return 0;
     }
   }

   function getPluginDep() {
     return array();
   }
}
?>
