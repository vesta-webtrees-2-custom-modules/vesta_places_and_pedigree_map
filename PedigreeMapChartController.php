<?php

declare(strict_types=1);

namespace Cissee\Webtrees\Module\PPM;

use Cissee\WebtreesExt\Requests;
use Fisharebest\Webtrees\Exceptions\IndividualAccessDeniedException;
use Fisharebest\Webtrees\Exceptions\IndividualNotFoundException;
use Fisharebest\Webtrees\Http\Controllers\AbstractBaseController;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Module\PedigreeMapModule;
use Fisharebest\Webtrees\Services\ChartService;
use Fisharebest\Webtrees\Tree;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionClass;
use Vesta\Hook\HookInterfaces\FunctionsPlaceUtils;
use Vesta\Model\MapCoordinates;
use Vesta\Model\PlaceStructure;
use function view;

class PedigreeMapChartController extends AbstractBaseController {

  //for getPreferences and other methods
  protected $module;
  protected $chart_service;

  public function __construct(
          PlacesAndPedigreeMapModuleExtended $module,
          ChartService $chart_service) {
    
    $this->module = $module;
    $this->chart_service = $chart_service;
  }

  //adapted from PedigreeMapModule
  public function page(ServerRequestInterface $request, Tree $tree): ResponseInterface {
    $xref = Requests::getString($request, 'xref');
    $individual = Individual::getInstance($xref, $tree);
    $maxgenerations = $tree->getPreference('MAX_PEDIGREE_GENERATIONS');
    $generations = Requests::getString($request, 'generations', $tree->getPreference('DEFAULT_PEDIGREE_GENERATIONS'));

    if ($individual === null) {
      throw new IndividualNotFoundException();
    }

    if (!$individual->canShow()) {
      throw new IndividualAccessDeniedException();
    }
    
    //uargh (accessed as attributes in PedigreeMapModule.getPedigreeMapFacts)
    $request = $request->withAttribute('xref', $xref);
    $request = $request->withAttribute('generations', $generations);

    $map = view('modules/pedigree-map/chart', [
            'data'     => $this->getMapData($request),
            'provider' => [
                'url'    => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
                'options' => [
                    'attribution' => '<a href="https://www.openstreetmap.org/copyright">&copy; OpenStreetMap</a> contributors',
                    'max_zoom'    => 19
                ]
            ]
        ]);
            
    return $this->viewResponse($this->module->name() . '::page', [
                'module'         => $this->module->name(),
                /* I18N: %s is an individual’s name */
                'title'          => I18N::translate('Pedigree map of %s', $individual->fullName()),
                'tree'           => $tree,
                'individual'     => $individual,
                'generations'    => $generations,
                'maxgenerations' => $maxgenerations,
                'map'            => $map,
    ]);
  }

  // CSS colors for each generation
  private const COLORS = [
      'Red',
      'Green',
      'Blue',
      'Gold',
      'Cyan',
      'Orange',
      'DarkBlue',
      'LightGreen',
      'Magenta',
      'Brown',
  ];
    
  private const DEFAULT_ZOOM = 2;
  
  //adapted from PedigreeMapModule
  /**
   * @param ServerRequestInterface $request
   *
   * @return array<mixed> $geojson
   */
  private function getMapData(ServerRequestInterface $request): array
  {
      $pedigreeMapModule = new PedigreeMapModule($this->chart_service);

      $class = new ReflectionClass($pedigreeMapModule);
      $getPedigreeMapFactsMethod = $class->getMethod('getPedigreeMapFacts');
      $getPedigreeMapFactsMethod->setAccessible(true);
      $getSosaNameMethod = $class->getMethod('getSosaName');
      $getSosaNameMethod->setAccessible(true);
    
      $tree = $request->getAttribute('tree');
      assert($tree instanceof Tree);

      $color_count = count(self::COLORS);

      //[RC] adjusted
      //$facts = $this->getPedigreeMapFacts($request, $this->chart_service);      
      $facts = $getPedigreeMapFactsMethod->invoke($pedigreeMapModule, $request, $this->chart_service);

      $geojson = [
          'type'     => 'FeatureCollection',
          'features' => [],
      ];

      $sosa_points = [];

      foreach ($facts as $sosa => $fact) {
          /*
          $location = new Location($fact->place()->gedcomName());

          // Use the co-ordinates from the fact (if they exist).
          $latitude  = $fact->latitude();
          $longitude = $fact->longitude();

          // Use the co-ordinates from the location otherwise.
          if ($latitude === 0.0 && $longitude === 0.0) {
              $latitude  = $location->latitude();
              $longitude = $location->longitude();
          }

          if ($latitude !== 0.0 || $longitude !== 0.0) {
          */ 
        
          //[RC] adjusted
          $latLon = $this->getLatLon($fact);

          if ($latLon !== null) {
              $latitude = $latLon->getLati();
              $longitude = $latLon->getLong();
        
              $polyline           = null;
              $sosa_points[$sosa] = [$latitude, $longitude];
              $sosa_child         = intdiv($sosa, 2);
              $color              = self::COLORS[$sosa_child % $color_count];

              if (array_key_exists($sosa_child, $sosa_points)) {
                  // Would like to use a GeometryCollection to hold LineStrings
                  // rather than generate polylines but the MarkerCluster library
                  // doesn't seem to like them
                  $polyline = [
                      'points'  => [
                          $sosa_points[$sosa_child],
                          [$latitude, $longitude],
                      ],
                      'options' => [
                          'color' => $color,
                      ],
                  ];
              }
              $geojson['features'][] = [
                  'type'       => 'Feature',
                  'id'         => $sosa,
                  'geometry'   => [
                      'type'        => 'Point',
                      'coordinates' => [$longitude, $latitude],
                  ],
                  'properties' => [
                      'polyline'  => $polyline,
                      'iconcolor' => $color,
                      'tooltip'   => strip_tags($fact->place()->fullName()),
                      'summary'   => view('modules/pedigree-map/events', [
                          'fact'         => $fact,
                          //'relationship' => ucfirst($this->getSosaName($sosa)),
                          //[RC] adjusted
                          'relationship' => ucfirst($getSosaNameMethod->invoke($pedigreeMapModule, $sosa)),
                          'sosa'         => $sosa,
                      ]),
                      //[RC] adjusted
                      'zoom'      => /*$location->zoom() ?:*/ self::DEFAULT_ZOOM,
                  ],
              ];
          }
      }

      return $geojson;
  }

  private function getLatLon($fact): ?MapCoordinates {
    $ps = PlaceStructure::fromFact($fact);
    return FunctionsPlaceUtils::plac2map($this->module, $ps, false);
  }

}
