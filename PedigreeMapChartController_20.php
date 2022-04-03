<?php

declare(strict_types=1);

namespace Cissee\Webtrees\Module\PPM;

use Cissee\WebtreesExt\MoreI18N;
use Fig\Http\Message\RequestMethodInterface;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\Http\ViewResponseTrait;
use Fisharebest\Webtrees\Module\ModuleChartInterface;
use Fisharebest\Webtrees\Module\PedigreeMapModule;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\ChartService;
use Fisharebest\Webtrees\Tree;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionClass;
use Vesta\Hook\HookInterfaces\FunctionsPlaceUtils;
use Vesta\Model\MapCoordinates;
use Vesta\Model\PlaceStructure;
use function intdiv;
use function redirect;
use function route;
use function view;

//adapted from PedigreeMapModule.handle - we could just use that, if getMapData() wasn't private,
//which is the part we actually override
class PedigreeMapChartController_20 {
 
  use ViewResponseTrait;
    
  // Limits
  public const MAXIMUM_GENERATIONS = 10;
  private const MINZOOM            = 2;

  protected $module;
  protected $chart_service;

  public function __construct(
          PlacesAndPedigreeMapModuleExtended_20 $module,
          ChartService $chart_service) {
    
    $this->module = $module;
    $this->chart_service = $chart_service;
  }

  public function handle(ServerRequestInterface $request): ResponseInterface {
      $tree = $request->getAttribute('tree');
      assert($tree instanceof Tree);

      $xref = $request->getAttribute('xref');
      assert(is_string($xref));

      $individual  = Registry::individualFactory()->make($xref, $tree);
      $individual  = Auth::checkIndividualAccess($individual);

      $user        = $request->getAttribute('user');
      $generations = (int) $request->getAttribute('generations');
      
      //[RC] ref adjusted
      Auth::checkComponentAccess($this->module, ModuleChartInterface::class, $tree, $user);

      // Convert POST requests into GET requests for pretty URLs.
      if ($request->getMethod() === RequestMethodInterface::METHOD_POST) {
          $params = (array) $request->getParsedBody();

          //[RC] ref adjusted
          return redirect(route(get_class($this->module), [
              'tree'        => $tree->name(),
              'xref'        => $params['xref'],
              'generations' => $params['generations'],
          ]));
      }

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

      return $this->viewResponse('modules/pedigree-map/page', [
          //'module' obsolete
          /* I18N: %s is an individual’s name */
          'title'          => MoreI18N::xlate('Pedigree map of %s', $individual->fullName()),
          'tree'           => $tree,
          'individual'     => $individual,
          'generations'    => $generations,
          'maxgenerations' => self::MAXIMUM_GENERATIONS,
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
  
  /**
   * @param ServerRequestInterface $request
   *
   * @return array<mixed> $geojson
   */
  protected function getMapData(ServerRequestInterface $request): array
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
          $location = new PlaceLocation($fact->place()->gedcomName());

          // Use the co-ordinates from the fact (if they exist).
          $latitude  = $fact->latitude();
          $longitude = $fact->longitude();

          // Use the co-ordinates from the location otherwise.
          if ($latitude === null && $longitude === null) {
              $latitude  = $location->latitude();
              $longitude = $location->longitude();
          }

          if ($latitude !== null || $longitude !== null) {
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
                      'tooltip'   => $fact->place()->gedcomName(),
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

  protected function getLatLon(Fact $fact): ?MapCoordinates {
    $ps = PlaceStructure::fromFact($fact);
    if ($ps === null) {
      return null;
    }
    return FunctionsPlaceUtils::plac2map($this->module, $ps, false);
  }

}
