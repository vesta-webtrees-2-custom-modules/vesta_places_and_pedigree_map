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
use Fisharebest\Webtrees\Services\LeafletJsService;
use Fisharebest\Webtrees\Services\RelationshipService;
use Fisharebest\Webtrees\Tree;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Vesta\Hook\HookInterfaces\FunctionsPlaceUtils;
use Vesta\Model\MapCoordinates;
use Vesta\Model\PlaceStructure;
use function intdiv;
use function redirect;
use function route;
use function view;

//adapted from PedigreeMapModule.handle
class PedigreeMapChartController extends PedigreeMapModule {

    use ViewResponseTrait;

    // Limits
    public const VESTA_MAXIMUM_GENERATIONS = 10;

    protected PlacesAndPedigreeMapModuleExtended $module;
    protected ChartService $chart_service;
    protected LeafletJsService $leaflet_js_service;
    protected RelationshipService $relationship_service;

    public function __construct(
        PlacesAndPedigreeMapModuleExtended $module,
        ChartService $chart_service,
        LeafletJsService $leaflet_js_service,
        RelationshipService $relationship_service) {

        parent::__construct(
            $chart_service,
            $leaflet_js_service,
            $relationship_service);

        $this->module = $module;
        $this->chart_service = $chart_service;
        $this->leaflet_js_service = $leaflet_js_service;
        $this->relationship_service = $relationship_service;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface {
        $tree = $request->getAttribute('tree');
        assert($tree instanceof Tree);

        $xref = $request->getAttribute('xref');
        assert(is_string($xref));

        $individual = Registry::individualFactory()->make($xref, $tree);
        $individual = Auth::checkIndividualAccess($individual);

        $user = $request->getAttribute('user');
        $generations = (int) $request->getAttribute('generations');

        //[RC] ref adjusted
        Auth::checkComponentAccess($this->module, ModuleChartInterface::class, $tree, $user);

        // Convert POST requests into GET requests for pretty URLs.
        if ($request->getMethod() === RequestMethodInterface::METHOD_POST) {
            $params = (array) $request->getParsedBody();

            //[RC] ref adjusted
            return redirect(route(get_class($this->module), [
                'tree' => $tree->name(),
                'xref' => $params['xref'],
                'generations' => $params['generations'],
            ]));
        }

        $map = view('modules/pedigree-map/chart', [
            'data' => $this->getMapData($request),
            'leaflet_config' => $this->leaflet_js_service->config(),
        ]);

        return $this->viewResponse('modules/pedigree-map/page', [
                //'module' obsolete
                /* I18N: %s is an individual’s name */
                'title' => MoreI18N::xlate('Pedigree map of %s', $individual->fullName()),
                'tree' => $tree,
                'individual' => $individual,
                'generations' => $generations,
                'maxgenerations' => self::VESTA_MAXIMUM_GENERATIONS,
                'map' => $map,
        ]);
    }

    //TODO: use wrapper around Fact instead, providing lat/lon via getLatLon
    //(no need to re-implement getMapData then)
    /**
     * @param ServerRequestInterface $request
     *
     * @return array<mixed> $geojson
     */
    protected function getMapData(ServerRequestInterface $request): array {

        //TODO: use facts with adjusted lat/lon instead - less intrusive!
        $facts = $this->getPedigreeMapFacts($request, $this->chart_service);

        $geojson = [
            'type' => 'FeatureCollection',
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
                $generation         = (int) log($sosa, 2);
                $color              = 'var(--wt-pedigree-map-gen-' . $generation % self::COUNT_CSS_COLORS . ')';
                $class              = 'wt-pedigree-map-gen-' . $generation % self::COUNT_CSS_COLORS;

                if (array_key_exists($sosa_child, $sosa_points)) {
                    // Would like to use a GeometryCollection to hold LineStrings
                    // rather than generate polylines but the MarkerCluster library
                    // doesn't seem to like them
                    $polyline = [
                        'points' => [
                            $sosa_points[$sosa_child],
                            [$latitude, $longitude],
                        ],
                        'options' => [
                            'color' => $color,
                        ],
                    ];
                }
                $geojson['features'][] = [
                    'type' => 'Feature',
                    'id' => $sosa,
                    'geometry' => [
                        'type' => 'Point',
                        'coordinates' => [$longitude, $latitude],
                    ],
                    'properties' => [
                        'polyline' => $polyline,
                        'iconcolor' => $color,
                        'tooltip' => $fact->place()->gedcomName(),
                        'summary' => view('modules/pedigree-map/events', [
                            'class'        => $class,
                            'fact'         => $fact,
                            'relationship' => $this->getSosaName($sosa),
                            'sosa'         => $sosa,
                        ]),
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
