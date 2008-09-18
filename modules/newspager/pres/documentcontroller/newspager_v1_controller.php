<?php
   import('modules::newspager::biz','newspagerManager');


   /**
   *  @package modules::newspager::pres
   *  @class newspager_v1_controller
   *
   *  Document controller for the newspager module.<br />
   *
   *  @author Christian Achatz
   *  @version
   *  Version 0.1, 02.20.2008<br />
   */
   class newspager_v1_controller extends baseController
   {

      function newspager_v1_controller(){
      }


      /**
      *  @public
      *
      *  Implements the abstract transformation function of the baseController class.<br />
      *
      *  @author Christian Achatz
      *  @version
      *  Version 0.1, 02.20.2008<br />
      *  Version 0.2, 05.01.2008 (language is now published to the java script code)<br />
      *  Version 0.3, 18.09.2008 (Introduced datadir attribute to be able to operate the module in more than one application)<br />
      */
      function transformContent(){

         // get current data dir or trigger error
         $DataDir = $this->__Document->getAttribute('datadir');
         if($DataDir === null){
            trigger_error('[newspager_v1_controller::transformContent()] Tag attribute "datadir" was not present in the &lt;core:importdesign /&gt; tag definition! Please specify a news content directory!');
            return;
          // end if
         }

         // get manager
         $nM = &$this->__getAndInitServiceObject('modules::newspager::data','newspagerManager',$DataDir);

         // load default news page
         $N = $nM->getNewsByPage();

         // fill place holders
         $this->setPlaceHolder('NewsLanguage',$this->__Language);
         $this->setPlaceHolder('NewsCount',$N->get('NewsCount'));
         $this->setPlaceHolder('Headline',$N->get('Headline'));
         $this->setPlaceHolder('Subheadline',$N->get('Subheadline'));
         $this->setPlaceHolder('Content',$N->get('Content'));

         // set news service base url
         $Reg = &Singleton::getInstance('Registry');
         if($Reg->retrieve('apf::core','URLRewriting') === true){
            $this->setPlaceHolder('NewsServiceBaseURL','/~/modules_newspager_biz-action/Pager/page/');
            $this->setPlaceHolder('NewsServiceLangParam','/lang/');
            $this->setPlaceHolder('NewsServiceDataDir','/datadir/'.base64_encode($DataDir));
          // end if
         }
         else{
            $this->setPlaceHolder('NewsServiceBaseURL','./?modules_newspager_biz-action:Pager=page:');
            $this->setPlaceHolder('NewsServiceLangParam','|lang:');
            $this->setPlaceHolder('NewsServiceDataDir','|datadir:'.base64_encode($DataDir));
          // end else
         }

       // end function
      }

    // end class
   }
?>