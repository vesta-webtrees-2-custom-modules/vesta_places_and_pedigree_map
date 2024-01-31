<?php

declare(strict_types=1);

namespace Cissee\Webtrees\Module\PPM;

use Fisharebest\Webtrees\Http\ViewResponseTrait;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Module\PlacesModule;
use Fisharebest\Webtrees\Services\LeafletJsService;
use Fisharebest\Webtrees\Services\ModuleService;
use ReflectionClass;
use stdClass;
use Vesta\Hook\HookInterfaces\FunctionsPlaceUtils;
use Vesta\Model\MapCoordinates;
use Vesta\Model\PlaceStructure;
use function view;

//TODO: support other map providers?
class PlacesController {

    use ViewResponseTrait;
    
    protected $module;
    protected $module_service;
    protected $leaflet_js_service;

    public function __construct(
        PlacesAndPedigreeMapModuleExtended $module, 
        ModuleService $module_service,
        LeafletJsService $leaflet_js_service) {
        
        $this->module = $module;
        $this->module_service = $module_service;
        $this->leaflet_js_service = $leaflet_js_service;
    }

    protected const ICONS = [
        'FAM:CENS'  => ['color' => 'darkcyan', 'name' => 'list fas'],
        'FAM:MARR'  => ['color' => 'green', 'name' => 'infinity fas'],
        'INDI:BAPM' => ['color' => 'hotpink', 'name' => 'water fas'],
        'INDI:BARM' => ['color' => 'hotpink', 'name' => 'star-of-david fas'],
        'INDI:BASM' => ['color' => 'hotpink', 'name' => 'star-of-david fas'],
        'INDI:BIRT' => ['color' => 'hotpink', 'name' => 'baby-carriage fas'],
        'INDI:BURI' => ['color' => 'purple', 'name' => 'times fas'],
        'INDI:CENS' => ['color' => 'darkcyan', 'name' => 'list fas'],
        'INDI:CHR'  => ['color' => 'hotpink', 'name' => 'water fas'],
        'INDI:CHRA' => ['color' => 'hotpink', 'name' => 'water fas'],
        'INDI:CREM' => ['color' => 'black', 'name' => 'times fas'],
        'INDI:DEAT' => ['color' => 'black', 'name' => 'times fas'],
        'INDI:EDUC' => ['color' => 'violet', 'name' => 'university fas'],
        'INDI:GRAD' => ['color' => 'violet', 'name' => 'university fas'],
        'INDI:OCCU' => ['color' => 'darkcyan', 'name' => 'industry fas'],
        'INDI:RESI' => ['color' => 'darkcyan', 'name' => 'home fas'],
    ];
    
    protected const DEFAULT_ICON = ['color' => 'gold', 'name' => 'bullseye fas'];

    public function getTabContent(Individual $individual): string {
        $placesModule = new PlacesModule($this->leaflet_js_service, $this->module_service);

        return view('modules/places/tab', [
            'data' => $this->getMapData($placesModule, $individual),
            'leaflet_config' => $this->leaflet_js_service->config(),
        ]);
    }

    public function hasTabContent(Individual $individual): bool {
        $placesModule = new PlacesModule($this->leaflet_js_service, $this->module_service);
        return $this->hasMapData($placesModule, $individual);
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

            $icon = PlacesController::ICONS[$fact->tag()] ?? PlacesController::DEFAULT_ICON;

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
    
    //adapted from PlacesModule
    private function hasMapData($placesModule, Individual $indi): bool {
        $class = new ReflectionClass($placesModule);
        $getPersonalFactsMethod = $class->getMethod('getPersonalFacts');
        $getPersonalFactsMethod->setAccessible(true);
        $summaryDataMethod = $class->getMethod('summaryData');
        $summaryDataMethod->setAccessible(true);

        //$placesModule->getPersonalFacts($indi);
        $facts = $getPersonalFactsMethod->invoke($placesModule, $indi);

        foreach ($facts as $id => $fact) {
            $latLon = $this->getLatLon($fact);

            if ($latLon !== null) {
                return true;
            }
        }

        return false;
    }
    
    private function getLatLon($fact): ?MapCoordinates {
        $ps = PlaceStructure::fromFact($fact);
        if ($ps === null) {
            return null;
        }
        return FunctionsPlaceUtils::plac2map($this->module, $ps, false);
    }

}
