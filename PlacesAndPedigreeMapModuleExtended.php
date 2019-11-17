<?php

namespace Cissee\Webtrees\Module\PPM;

use Fisharebest\Webtrees\Http\Controllers\Admin\ModuleController;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Menu;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleChartInterface;
use Fisharebest\Webtrees\Module\ModuleChartTrait;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleTabInterface;
use Fisharebest\Webtrees\Module\ModuleTabTrait;
use Fisharebest\Webtrees\Services\ChartService;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Tree;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionObject;
use Vesta\Hook\HookInterfaces\FunctionsPlaceInterface;
use Vesta\Hook\HookInterfaces\FunctionsPlaceUtils;
use Vesta\VestaAdminController;
use Vesta\VestaModuleTrait;
use function app;
use function redirect;
use function route;
use function view;

class PlacesAndPedigreeMapModuleExtended extends AbstractModule implements ModuleCustomInterface, ModuleConfigInterface, ModuleTabInterface, ModuleChartInterface {

  use VestaModuleTrait;
  use PlacesAndPedigreeMapModuleTrait;
  use ModuleTabTrait;
  use ModuleChartTrait;

  protected $module_service;
  protected $chart_service;
  
  public function __construct(ModuleService $module_service, ChartService $chart_service) {
    $this->module_service = $module_service;
    $this->chart_service = $chart_service;
  }

  public function customModuleAuthorName(): string {
    return 'Richard Cissée';
  }

  public function customModuleVersion(): string {
    return '2.0.0-beta.5.2';
  }

  public function customModuleLatestVersionUrl(): string {
    return 'https://cissee.de';
  }

  public function customModuleSupportUrl(): string {
    return 'https://cissee.de';
  }

  public function description(): string {
    return $this->getShortDescription();
  }

  /**
   * Where does this module store its resources
   *
   * @return string
   */
  public function resourcesFolder(): string {
    return __DIR__ . '/resources/';
  }

  public function tabTitle(): string {
    return $this->getTabTitle(I18N::translate('Places'));
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

  public function chartMenu(Individual $individual): Menu {
    return new Menu(
            $this->getChartTitle(I18N::translate('Pedigree map')),
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
    return $this->getChartTitle(I18N::translate('Pedigree map of %s', $individual->fullName()));
  }

  public function chartUrl(Individual $individual, array $parameters = []): string {
    return route('module', [
        'module' => $this->name(),
        'action' => 'PedigreeMap',
        'xref' => $individual->xref(),
        'tree' => $individual->tree()->name(),
            ] + $parameters);
  }

  public function getBoxChartMenu(Individual $individual) {
    return $this->getChartMenu($individual);
  }

  public function postPedigreeMapAction(ServerRequestInterface $request): ResponseInterface {
    //TODO use helper for this!

    // Convert POST requests into GET requests for pretty URLs.
    $keys = array('tree','xref','generations');
    $parameters = array_filter($request->getParsedBody(), static function (string $key) use ($keys): bool {
      return in_array($key, $keys);
    }, ARRAY_FILTER_USE_KEY);
    
    return redirect(route('module', [
        'module'      => $this->name(),
        'action'      => 'PedigreeMap']+$parameters));
  }
  
  public function getPedigreeMapAction(ServerRequestInterface $request): ResponseInterface {
    //'tree' is handled specifically in Router.php
    $tree = $request->getAttribute('tree');
    assert($tree instanceof Tree);
    
    $controller = new PedigreeMapChartController($this, $this->chart_service);
    return $controller->page($request, $tree);
  }

  public function getMapDataAction(ServerRequestInterface $request): ResponseInterface {
    //'tree' is handled specifically in Router.php
    $tree = $request->getAttribute('tree');
    assert($tree instanceof Tree);
    
    $controller = new PedigreeMapChartController($this, $this->chart_service);
    return $controller->mapData($request, $tree);
  }

  //////////////////////////////////////////////////////////////////////////////
  
  private function title1(): string {
    return I18N::translate('Places and Pedigree Map Location Data Providers');
  }
  
  private function description1(): string {
    return I18N::translate('Modules listed here are used (in the configured order) to determine map coordinates of places.');
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
