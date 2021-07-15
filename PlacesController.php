<?php

declare(strict_types=1);

namespace Cissee\Webtrees\Module\PPM;

use Fisharebest\Webtrees\Http\Controllers\AbstractBaseController;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Module\PlacesModule;
use ReflectionClass;
use stdClass;
use Vesta\Hook\HookInterfaces\FunctionsPlaceUtils;
use Vesta\Model\MapCoordinates;
use Vesta\Model\PlaceStructure;
use function view;

//TODO: support other map providers?
class PlacesController extends AbstractBaseController {

  protected $module;

  public function __construct(PlacesAndPedigreeMapModuleExtended $module) {
    $this->module = $module;
  }

  protected const ICONS = [
        'BIRT' => ['color' => 'lightcoral', 'name' => 'baby-carriage'],
        'BAPM' => ['color' => 'lightcoral', 'name' => 'water'],
        'BARM' => ['color' => 'lightcoral', 'name' => 'star-of-david'],
        'BASM' => ['color' => 'lightcoral', 'name' => 'star-of-david'],
        'CHR'  => ['color' => 'lightcoral', 'name' => 'water'],
        'CHRA' => ['color' => 'lightcoral', 'name' => 'water'],
        'MARR' => ['color' => 'green', 'name' => 'infinity'],
        'DEAT' => ['color' => 'black', 'name' => 'times'],
        'BURI' => ['color' => 'sienna', 'name' => 'times'],
        'CREM' => ['color' => 'black', 'name' => 'times'],
        'CENS' => ['color' => 'mediumblue', 'name' => 'list'],
        'RESI' => ['color' => 'mediumblue', 'name' => 'home'],
        'OCCU' => ['color' => 'mediumblue', 'name' => 'industry'],
        'GRAD' => ['color' => 'plum', 'name' => 'university'],
        'EDUC' => ['color' => 'plum', 'name' => 'university'],
    ];

  protected const DEFAULT_ICON = ['color' => 'gold', 'name' => 'bullseye '];
    
  public function getTabContent(Individual $individual): string {
    $placesModule = new PlacesModule();

    return view('modules/places/tab', [
        'data'     => $this->getMapData($placesModule, $individual),
        'provider' => [
                   'url' => 'https://tile-{s}.openstreetmap.fr/hot/{z}/{x}/{y}.png',
                   'options' => [
                   'attribution' => 'Tile style - from the <a href="https://www.hotosm.org">Humanitarian OpenStreetMap Team </a>, hosting - from <a href="https://openstreetmap.fr">OSM France</a>',
                'max_zoom'    => 19
            ]
        ]
    ]);
  }

  //adapted from PlacesModule
  private function getMapData($placesModule, Individual $indi): stdClass {
    $class = new ReflectionClass($placesModule);
    $getPersonalFactsMethod = $class->getMethod('getPersonalFacts');
    $getPersonalFactsMethod->setAccessible(true);
    $summaryDataMethod = $class->getMethod('summaryData');
    $summaryDataMethod->setAccessible(true);

    //$placesModule->getPersonalFacts($indi);
    $facts = $getPersonalFactsMethod->invoke($placesModule, $indi);

    $geojson = [
        'type' => 'FeatureCollection',
        'features' => [],
    ];

    //[RC] TODO: use hierarchy (higher-level places) as fallback?
    
    foreach ($facts as $id => $fact) {
      $latLon = $this->getLatLon($fact);

      $icon = PlacesController::ICONS[$fact->getTag()] ?? PlacesController::DEFAULT_ICON;

      if ($latLon !== null) {
        $latitude = $latLon->getLati();
        $longitude = $latLon->getLong();
        
        $geojson['features'][] = [
            'type' => 'Feature',
            'id' => $id,
            'valid' => true,
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [$longitude, $latitude],
            ],
            'properties' => [
                'polyline' => null,
                'icon' => $icon,
                'tooltip' => $fact->place()->gedcomName(),
                'summary' => view('modules/places/event-sidebar',
                        //$placesModule->summaryData($indi, $fact)),
                        $summaryDataMethod->invoke($placesModule, $indi, $fact)),
                'zoom' => 15/* only used initially? */ /* $location->zoom() */,
            ],
        ];
      }
    }

    return (object) $geojson;
  }

  private function getLatLon($fact): ?MapCoordinates {
    $ps = PlaceStructure::fromFact($fact);
    if ($ps === null) {
      return null;
    }
    return FunctionsPlaceUtils::plac2map($this->module, $ps, false);
  }

}
