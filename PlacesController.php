<?php

declare(strict_types=1);

namespace Cissee\Webtrees\Module\PPM;

use Fisharebest\Webtrees\Functions\Functions;
use Fisharebest\Webtrees\Http\Controllers\AbstractBaseController;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Module\PlacesModule;
use ReflectionClass;
use stdClass;
use Vesta\Hook\HookInterfaces\FunctionsPlaceUtils;
use function view;

//TODO: support other map providers?
class PlacesController extends AbstractBaseController {

  protected $module;

  public function __construct(PlacesAndPedigreeMapModuleExtended $module) {
    $this->module = $module;
  }

  public function getTabContent(Individual $individual): string {
    $placesModule = new PlacesModule();

    return view('modules/places/tab', [
        'data' => $this->getMapData($placesModule, $individual),
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

    foreach ($facts as $id => $fact) {
      //$location = new Location($fact->place()->gedcomName());
      // Use the co-ordinates from the fact (if they exist).
      $latitude = $fact->latitude();
      $longitude = $fact->longitude();

      // Use the co-ordinates from a hook otherwise.
      if ($latitude === 0.0 && $longitude === 0.0) {
        $latLon = $this->getLatLon($fact);
        if ($latLon !== null) {
          $longitude = array_pop($latLon);
          $latitude = array_pop($latLon);
        }
      }

      $icon = PlacesModule::ICONS[$fact->getTag()] ?? PlacesModule::DEFAULT_ICON;

      if ($latitude !== 0.0 || $longitude !== 0.0) {
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
                'tooltip' => strip_tags($fact->place()->fullName()),
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

  private function getLatLon($fact) {
    $placerec = Functions::getSubRecord(2, '2 PLAC', $fact->gedcom());
    return FunctionsPlaceUtils::getFirstLatLon($this->module, $fact, $placerec);
  }

}
