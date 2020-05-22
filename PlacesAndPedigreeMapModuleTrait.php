<?php

namespace Cissee\Webtrees\Module\PPM;

use Fisharebest\Webtrees\I18N;
use Vesta\ControlPanelUtils\Model\ControlPanelCheckbox;
use Vesta\ControlPanelUtils\Model\ControlPanelPreferences;
use Vesta\ControlPanelUtils\Model\ControlPanelSection;
use Vesta\ControlPanelUtils\Model\ControlPanelSubsection;

trait PlacesAndPedigreeMapModuleTrait {

  protected function getMainTitle() {
    return I18N::translate('Vesta Places and Pedigree map');
  }

  public function getShortDescription() {
    return
            I18N::translate('Show the location of events and the birthplace of ancestors on a map. Replacement for the original \'Places\' and  \'Pedigree map\' modules.');
  }

  protected function getFullDescription() {
    $description = array();
    $description[] = 
            /* I18N: Module Configuration */I18N::translate('Show the location of events and the birthplace of ancestors on a map. Replacement for the original \'Places\' and  \'Pedigree map\' modules.') . ' ' .
            /* I18N: Module Configuration */I18N::translate('Uses location data from GEDCOM, as well as location data provided by other modules.') . ' ' .
            /* I18N: Module Configuration */I18N::translate('In particular, you should activate the \'%1$s Vesta Webtrees Location Data Provider\' module if you manage your location data via webtrees (outside the GEDCOM).', $this->getVestaSymbol());
    return $description;
  }

  protected function createPrefs() {
    $generalSub = array();
    $generalSub[] = new ControlPanelSubsection(
            /* I18N: Configuration option */I18N::translate('Displayed title'),
            array(/*
        new ControlPanelCheckbox(
                I18N::translate('Include the %1$s symbol in the module title', $this->getVestaSymbol()),
                null,
                'VESTA',
                '1'),*/
        new ControlPanelCheckbox(
                /* I18N: Module Configuration */I18N::translate('Include the %1$s symbol in the tab title', $this->getVestaSymbol()),
                /* I18N: Module Configuration */I18N::translate('Deselect in order to have the tab appear exactly as the original tab.'),
                'VESTA_TAB',
                '1'),
        new ControlPanelCheckbox(
                /* I18N: Module Configuration */I18N::translate('Include the %1$s symbol in the chart menu entry', $this->getVestaSymbol()),
                /* I18N: Module Configuration */I18N::translate('Deselect in order to have the chart menu entry appear exactly as the original chart menu entry.'),
                'VESTA_CHART',
                '1')));

    //$placeSub = array();		
    //$placeSub[] = new ControlPanelSubsection(
    //				/* I18N: Configuration option */I18N::translate('Zoom levels'),
    //				array(new ControlPanelRange(
    //								/* I18N: Configuration option */I18N::translate('Maximal initial zoom level'),
    //								I18N::translate('If all events displayed in the map are located closely together, a suitably small value here avoids zooming in too far when initially displaying the map.'), 
    //								1,
    //								18,
    //								'INITIAL_ZOOM', 
    //								12),
    //						new ControlPanelRange(
    //								/* I18N: Configuration option */I18N::translate('Maximal zoom level for clusters'),
    //								I18N::translate('When clicking on clusters, it may be desirable not to zoom too far either.'), 
    //								1,
    //								18,
    //								'CLUSTERCLICK_MAX_ZOOM', 
    //								18)));

    $sections = array();
    $sections[] = new ControlPanelSection(
            /* I18N: Configuration option */I18N::translate('General'),
            null,
            $generalSub);
    //$sections[] = new ControlPanelSection(
    //				/* I18N: Configuration option */I18N::translate('Common Map Settings'),
    //				null,
    //				$placeSub);


    return new ControlPanelPreferences($sections);
  }

}
