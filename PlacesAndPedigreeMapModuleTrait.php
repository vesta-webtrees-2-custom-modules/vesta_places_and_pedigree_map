<?php

namespace Cissee\Webtrees\Module\PPM;

use Fisharebest\Webtrees\I18N;
use Vesta\CommonI18N;
use Vesta\ControlPanelUtils\Model\ControlPanelCheckbox;
use Vesta\ControlPanelUtils\Model\ControlPanelFactRestriction;
use Vesta\ControlPanelUtils\Model\ControlPanelPreferences;
use Vesta\ControlPanelUtils\Model\ControlPanelSection;
use Vesta\ControlPanelUtils\Model\ControlPanelSubsection;
use Vesta\ControlPanelUtils\Model\ControlPanelTextbox;
use Vesta\Model\PlaceHistory;

trait PlacesAndPedigreeMapModuleTrait {

  protected function getMainTitle() {
    return CommonI18N::titleVestaPlacesAndPedigreeMap();
  }

  public function getShortDescription() {
    return
            I18N::translate('The Place hierarchy. Also show the location of events and the birthplace of ancestors on a map. Replacement for the original \'Place hierarchy\', \'Places\' and  \'Pedigree map\' modules.');
  }

  protected function getFullDescription() {
    $description = array();
    $description[] =
            /* I18N: Module Configuration */I18N::translate('The Place hierarchy. Also show the location of events and the birthplace of ancestors on a map. Replacement for the original \'Place hierarchy\', \'Places\' and  \'Pedigree map\' modules.') . ' ' .
            /* I18N: Module Configuration */I18N::translate('Uses location data from GEDCOM, as well as location data provided by other modules.') . ' ' .
            /* I18N: Module Configuration */I18N::translate('In particular, you should activate the \'%1$s Vesta Webtrees Location Data Provider\' module if you manage your location data via webtrees (outside the GEDCOM).', $this->getVestaSymbol());
    $description[] =
            CommonI18N::requires1(CommonI18N::titleVestaCommon());
    return $description;
  }

  protected function createPrefs() {
    $generalSub = array();
    $generalSub[] = new ControlPanelSubsection(
            CommonI18N::displayedTitle(),
            array(/*
        new ControlPanelCheckbox(
                I18N::translate('Include the %1$s symbol in the module title', $this->getVestaSymbol()),
                null,
                'VESTA',
                '1'),*/
        new ControlPanelCheckbox(
                CommonI18N::vestaSymbolInListTitle(),
                CommonI18N::vestaSymbolInTitle2(),
                'VESTA_LIST',
                '1'),
        new ControlPanelCheckbox(
                CommonI18N::vestaSymbolInTabTitle(),
                CommonI18N::vestaSymbolInTitle2(),
                'VESTA_TAB',
                '1'),
        new ControlPanelCheckbox(
                CommonI18N::vestaSymbolInChartTitle(),
                CommonI18N::vestaSymbolInTitle2(),
                'VESTA_CHART',
                '1')));

    $placeSub = array();
    $placeSub[] = new ControlPanelSubsection(
            /* I18N: Module Configuration */I18N::translate('Display details'),
            array(
        new ControlPanelTextbox(
                /* I18N: Module Configuration */I18N::translate('Threshold size'),
                /* I18N: Module Configuration */I18N::translate('Do not display details (such as location and number of individuals) if list size (for a specific place) is larger than ths threshold.') . ' ' .
                /* I18N: Module Configuration */I18N::translate('A smaller threshold setting prevents performance issues in large lists, which may be encountered in flat place hierarchies.'),
                'DETAILS_THRESHOLD',
                '100')));

    $placeSub[] = new ControlPanelSubsection(
            CommonI18N::placeHistory(),
            array(
                new ControlPanelFactRestriction(
                    PlaceHistory::getPicklistFacts(),
                    CommonI18N::restrictPlaceHistory(),
                    'RESTRICTED_PLACE_HISTORY',
                    PlaceHistory::initialFactsStringForPreferences())));


    //$placeSub[] = new ControlPanelSubsection(
    //				/* I18N: Module Configuration */I18N::translate('Zoom levels'),
    //				array(new ControlPanelRange(
    //								/* I18N: Module Configuration */I18N::translate('Maximal initial zoom level'),
    //								I18N::translate('If all events displayed in the map are located closely together, a suitably small value here avoids zooming in too far when initially displaying the map.'),
    //								1,
    //								18,
    //								'INITIAL_ZOOM',
    //								12),
    //						new ControlPanelRange(
    //								/* I18N: Module Configuration */I18N::translate('Maximal zoom level for clusters'),
    //								I18N::translate('When clicking on clusters, it may be desirable not to zoom too far either.'),
    //								1,
    //								18,
    //								'CLUSTERCLICK_MAX_ZOOM',
    //								18)));

    $sections = array();
    $sections[] = new ControlPanelSection(
            CommonI18N::general(),
            null,
            $generalSub);
    $sections[] = new ControlPanelSection(
    				/* I18N: Module Configuration */I18N::translate('Place hierarchy list'),
    				null,
    				$placeSub);


    return new ControlPanelPreferences($sections);
  }

}
