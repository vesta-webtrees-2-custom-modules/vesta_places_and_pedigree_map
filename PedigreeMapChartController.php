<?php

declare(strict_types=1);

namespace Cissee\Webtrees\Module\PPM;

use Fisharebest\Webtrees\Exceptions\IndividualAccessDeniedException;
use Fisharebest\Webtrees\Exceptions\IndividualNotFoundException;
use Fisharebest\Webtrees\Functions\Functions;
use Fisharebest\Webtrees\Http\Controllers\AbstractBaseController;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Module\PedigreeMapModule;
use Fisharebest\Webtrees\Services\ChartService;
use Fisharebest\Webtrees\Tree;
use ReflectionClass;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Cissee\WebtreesExt\Requests;

use Vesta\Hook\HookInterfaces\FunctionsPlaceUtils;
use function view;

class PedigreeMapChartController extends AbstractBaseController {

  const LINE_COLORS = [
      '#FF0000',
      // Red
      '#00FF00',
      // Green
      '#0000FF',
      // Blue
      '#FFB300',
      // Gold
      '#00FFFF',
      // Cyan
      '#FF00FF',
      // Purple
      '#7777FF',
      // Light blue
      '#80FF80'
      // Light green
  ];

  //for getPreferences and other methods
  protected $module;

  public function __construct(PlacesAndPedigreeMapModuleExtended $module) {
    $this->module = $module;
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

    return $this->viewResponse('modules/pedigree-map/page', [
                'module_name' => $this->module->name(),
                /* I18N: %s is an individual’s name */
                'title' => I18N::translate('Pedigree map of %s', $individual->fullName()),
                'tree' => $tree,
                'individual' => $individual,
                'generations' => $generations,
                'maxgenerations' => $maxgenerations,
                'map' => view($this->module->name() . '::chart',
                        [
                            'module' => $this->module->name(),
                            'ref' => $individual->xref(),
                            'type' => 'pedigree',
                            'generations' => $generations,
                        ]
                ),
    ]);
  }

  //adapted from PedigreeMapModule
  public function mapData(ServerRequestInterface $request, Tree $tree, ChartService $chart_service): ResponseInterface {
    $pedigreeMapModule = new PedigreeMapModule();

    $class = new ReflectionClass($pedigreeMapModule);
    $getPedigreeMapFactsMethod = $class->getMethod('getPedigreeMapFacts');
    $getPedigreeMapFactsMethod->setAccessible(true);
    $summaryDataMethod = $class->getMethod('summaryData');
    $summaryDataMethod->setAccessible(true);


    $xref = Requests::getString($request, 'reference');
    $indi = Individual::getInstance($xref, $tree);
    $color_count = count(self::LINE_COLORS);

    //$facts = $pedigreeMapModule->getPedigreeMapFacts($request, $tree, $chart_service);
    $facts = $getPedigreeMapFactsMethod->invoke($pedigreeMapModule, $request, $tree, $chart_service);

    $geojson = [
        'type' => 'FeatureCollection',
        'features' => [],
    ];

    $sosa_points = [];

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

      $icon = ['color' => 'Gold', 'name' => 'bullseye '];
      if ($latitude !== 0.0 || $longitude !== 0.0) {
        $polyline = null;
        $color = self::LINE_COLORS[log($id, 2) % $color_count];
        $icon['color'] = $color; //make icon color the same as the line
        $sosa_points[$id] = [$latitude, $longitude];
        $sosa_parent = intdiv($id, 2);
        if (array_key_exists($sosa_parent, $sosa_points)) {
          // Would like to use a GeometryCollection to hold LineStrings
          // rather than generate polylines but the MarkerCluster library
          // doesn't seem to like them
          $polyline = [
              'points' => [
                  $sosa_points[$sosa_parent],
                  [$latitude, $longitude],
              ],
              'options' => [
                  'color' => $color,
              ],
          ];
        }
        $geojson['features'][] = [
            'type' => 'Feature',
            'id' => $id,
            'valid' => true,
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [$longitude, $latitude],
            ],
            'properties' => [
                'polyline' => $polyline,
                'icon' => $icon,
                'tooltip' => strip_tags($fact->place()->fullName()),
                'summary' => view('modules/pedigree-map/events',
                        //$pedigreeMapModule->summaryData($indi, $fact, $id)),
                        $summaryDataMethod->invoke($pedigreeMapModule, $indi, $fact, $id)),
                'zoom' => /* $location->zoom() ?: */ 2,
            ],
        ];
      }
    }

    $code = empty($facts) ? StatusCodeInterface::STATUS_NO_CONTENT : StatusCodeInterface::STATUS_OK;

    return response($geojson, $code);
  }

  private function getLatLon($fact) {
    $placerec = Functions::getSubRecord(2, '2 PLAC', $fact->gedcom());
    return FunctionsPlaceUtils::getFirstLatLon($this->module, $fact, $placerec);
  }

}
