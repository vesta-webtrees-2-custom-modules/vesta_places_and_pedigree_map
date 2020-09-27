<?php

namespace Cissee\Webtrees\Module\PPM;

use Aura\Router\RouterContainer;
use Cissee\WebtreesExt\Http\Controllers\GenericPlaceHierarchyController;
use Cissee\WebtreesExt\Http\Controllers\ModulePlaceHierarchyInterface;
use Cissee\WebtreesExt\Http\Controllers\PlaceHierarchyParticipant;
use Cissee\WebtreesExt\MoreI18N;
use Fig\Http\Message\RequestMethodInterface;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Http\Controllers\Admin\ModuleController;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Menu;
use Fisharebest\Webtrees\Module\ModuleChartInterface;
use Fisharebest\Webtrees\Module\ModuleChartTrait;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleConfigTrait;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleListInterface;
use Fisharebest\Webtrees\Module\ModuleListTrait;
use Fisharebest\Webtrees\Module\ModuleTabInterface;
use Fisharebest\Webtrees\Module\ModuleTabTrait;
use Fisharebest\Webtrees\Module\PlaceHierarchyListModule;
use Fisharebest\Webtrees\Services\ChartService;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Services\SearchService;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Statistics;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionObject;
use Vesta\CommonI18N;
use Vesta\Hook\HookInterfaces\FunctionsPlaceInterface;
use Vesta\Hook\HookInterfaces\FunctionsPlaceUtils;
use Vesta\VestaAdminController;
use Vesta\VestaModuleTrait;
use function app;
use function redirect;
use function route;
use function view;

//must extend PlaceHierarchyListModule in order to handle urls of standard places via $place->url()
class PlacesAndPedigreeMapModuleExtended extends PlaceHierarchyListModule implements 
  ModuleCustomInterface, 
  ModuleConfigInterface,
  ModuleTabInterface, 
  ModuleChartInterface,
  ModuleListInterface,
  ModulePlaceHierarchyInterface,
  RequestHandlerInterface {

  use ModuleCustomTrait, ModuleConfigTrait, ModuleTabTrait, ModuleChartTrait, VestaModuleTrait, ModuleListTrait {
    VestaModuleTrait::customTranslations insteadof ModuleCustomTrait;
    VestaModuleTrait::customModuleLatestVersion insteadof ModuleCustomTrait;
    VestaModuleTrait::getAssetAction insteadof ModuleCustomTrait;
    VestaModuleTrait::assetUrl insteadof ModuleCustomTrait;
    
    VestaModuleTrait::getConfigLink insteadof ModuleConfigTrait;
  }
  
  use PlacesAndPedigreeMapModuleTrait;

  protected const ROUTE_URL = '/tree/{tree}/vesta-pedigree-map-{generations}/{xref}';
  
  // Defaults
  public const DEFAULT_GENERATIONS = '4';
  public const DEFAULT_PARAMETERS  = [
      'generations' => self::DEFAULT_GENERATIONS,
  ];

  protected $module_service;
  protected $chart_service;
  
  public function __construct(ModuleService $module_service, ChartService $chart_service) {
    parent::__construct(app(SearchService::class));
    $this->module_service = $module_service;
    $this->chart_service = $chart_service;
  }

  public function customModuleAuthorName(): string {
    return 'Richard Cissée';
  }

  public function customModuleVersion(): string {
    return file_get_contents(__DIR__ . '/latest-version.txt');
  }

  public function customModuleLatestVersionUrl(): string {
    return 'https://raw.githubusercontent.com/vesta-webtrees-2-custom-modules/vesta_places_and_pedigree_map/master/latest-version.txt';
  }

  public function customModuleSupportUrl(): string {
    return 'https://cissee.de';
  }

  public function resourcesFolder(): string {
    return __DIR__ . '/resources/';
  }

  public function tabTitle(): string {
    return $this->getTabTitle(MoreI18N::xlate('Places'));
  }

  public function defaultTabOrder(): int {
    return 99;
  }

  public function getTabContent(Individual $individual): string {
    $controller = new PlacesController($this);
    return $controller->getTabContent($individual);
  }

  public function hasTabContent(Individual $individual): bool {
    return true;
  }

  public function isGrayedOut(Individual $individual): bool {
    //TODO could be improved, but probably not when canLoadAjax is true
    return false;
  }

  public function canLoadAjax(): bool {
    return true;
  }
  
  /**
   * Bootstrap the module
   */
  public function onBoot(): void {
      //define our 'pretty' routes
      //note: potentially problematic in case of name clashes; 
      //webtrees isn't interested in solving this properly, see
      //https://www.webtrees.net/index.php/en/forum/2-open-discussion/33687-pretty-urls-in-2-x
      
      $router_container = app(RouterContainer::class);
      assert($router_container instanceof RouterContainer);
      
      $router_container->getMap()
            ->get(static::class, static::ROUTE_URL, $this)
            ->allows(RequestMethodInterface::METHOD_POST)
            ->tokens([
                'generations' => '\d+',
            ]);
      
      //for GenericPlaceHierarchyController
      View::registerCustomView('::modules/generic-place-hierarchy/place-hierarchy', $this->name() . '::modules/generic-place-hierarchy-shared-places/place-hierarchy');
      View::registerCustomView('::modules/generic-place-hierarchy/list', $this->name() . '::modules/generic-place-hierarchy-shared-places/list');
      View::registerCustomView('::modules/generic-place-hierarchy/page', $this->name() . '::modules/generic-place-hierarchy-shared-places/page');
      View::registerCustomView('::modules/generic-place-hierarchy/sidebar', $this->name() . '::modules/generic-place-hierarchy-shared-places/sidebar');
      
      $this->flashWhatsNew('\Cissee\Webtrees\Module\PPM\WhatsNew', 1);
  }
  
  public function chartMenu(Individual $individual): Menu {
    return new Menu(
            $this->getChartTitle(MoreI18N::xlate('Pedigree map')),
            $this->chartUrl($individual),
            $this->chartMenuClass(),
            $this->chartUrlAttributes()
    );
  }

  public function chartMenuClass(): string {
    return 'menu-chart-pedigreemap';
  }

  public function chartTitle(Individual $individual): string {
    /* I18N: %s is an individual’s name */
    return $this->getChartTitle(MoreI18N::xlate('Pedigree map of %s', $individual->fullName()));
  }

  public function chartUrl(Individual $individual, array $parameters = []): string {
    return route(static::class, [
                'tree' => $individual->tree()->name(),
                'xref' => $individual->xref(),
            ] + $parameters + self::DEFAULT_PARAMETERS);
  }

  public function getBoxChartMenu(Individual $individual) {
    return $this->getChartMenu($individual);
  }
  
  public function handle(ServerRequestInterface $request): ResponseInterface {
    $controller = new PedigreeMapChartController($this, $this->chart_service);
    return $controller->handle($request);
  }

  public function getMapDataAction(ServerRequestInterface $request): ResponseInterface {
    //'tree' is handled specifically in Router.php
    $tree = $request->getAttribute('tree');
    assert($tree instanceof Tree);
    
    $controller = new PedigreeMapChartController($this, $this->chart_service);
    return $controller->mapData($request, $tree);
  }
  
  //////////////////////////////////////////////////////////////////////////////

  public function listTitle(): string {
    return $this->getListTitle(MoreI18N::xlate('Place hierarchy'));
  }
  
  public function listMenuClass(): string {
    return 'menu-list-plac';
  }
  
  public function getListAction(ServerRequestInterface $request): ResponseInterface {
    $tree = $request->getAttribute('tree');
    assert($tree instanceof Tree);

    $user = $request->getAttribute('user');

    Auth::checkComponentAccess($this, ModuleListInterface::class, $tree, $user);
    
    $searchService = app(SearchService::class);
    $statistics = app(Statistics::class);
    $participants = app(ModuleService::class)
            ->findByComponent(PlaceHierarchyParticipant::class, $tree, Auth::user())
            ->filter(function (PlaceHierarchyParticipant $php) use ($tree): bool {
                return $php->participates($tree);
            });
            
    $detailsThreshold = intval($this->getPreference('DETAILS_THRESHOLD', 100));        
            
    $controller = new GenericPlaceHierarchyController(
            new PlaceHierarchyUtilsImpl($this, $participants, $searchService, $statistics),
            $detailsThreshold);

    return $controller->show($request);
  }

  public function listUrlAttributes(): array {
    return [];
  }
  
  //////////////////////////////////////////////////////////////////////////////
  
  private function title1(): string {
    return CommonI18N::locationDataProviders();
  }
  
  private function description1(): string {
    return CommonI18N::mapCoordinates();
  }
  
  //hook management - generalize?
  //adapted from ModuleController (e.g. listFooters)
  public function getProvidersAction(): ResponseInterface {
    $modules = FunctionsPlaceUtils::modules($this, true);

    $controller = new VestaAdminController($this->name());
    return $controller->listHooks(
                    $modules,
                    FunctionsPlaceInterface::class,
                    $this->title1(),
                    $this->description1(),
                    true,
                    true);
  }

  public function postProvidersAction(ServerRequestInterface $request): ResponseInterface {
    $modules = FunctionsPlaceUtils::modules($this, true);

    $controller1 = new ModuleController($this->module_service, app(TreeService::class));
    $reflector = new ReflectionObject($controller1);

    //private!
    //$controller1->updateStatus($modules, $request);

    $method = $reflector->getMethod('updateStatus');
    $method->setAccessible(true);
    $method->invoke($controller1, $modules, $request);

    FunctionsPlaceUtils::updateOrder($this, $request);

    //private!
    //$controller1->updateAccessLevel($modules, FunctionsPlaceInterface::class, $request);

    $method = $reflector->getMethod('updateAccessLevel');
    $method->setAccessible(true);
    $method->invoke($controller1, $modules, FunctionsPlaceInterface::class, $request);

    $url = route('module', [
        'module' => $this->name(),
        'action' => 'Providers'
    ]);

    return redirect($url);
  }

  protected function editConfigBeforeFaq() {
    $modules = FunctionsPlaceUtils::modules($this, true);

    $url = route('module', [
        'module' => $this->name(),
        'action' => 'Providers'
    ]);

    //cf control-panel.phtml
    ?>
    <div class="card-body">
        <div class="row">
            <div class="col-sm-9">
                <ul class="fa-ul">
                    <li>
                        <span class="fa-li"><?= view('icons/block') ?></span>
                        <a href="<?= e($url) ?>">
                            <?= $this->title1() ?>
                        </a>
                        <?= view('components/badge', ['count' => $modules->count()]) ?>
                        <p class="small text-muted">
                          <?= $this->description1() ?>
                        </p>
                    </li>
                </ul>
            </div>
        </div>
    </div>		

    <?php
  }
}
