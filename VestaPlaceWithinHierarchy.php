<?php

namespace Cissee\Webtrees\Module\PPM;

use Cissee\WebtreesExt\Http\Controllers\DefaultPlaceWithinHierarchy;
use Cissee\WebtreesExt\Http\Controllers\PlaceUrls;
use Cissee\WebtreesExt\Http\Controllers\PlaceWithinHierarchy;
use Fisharebest\Webtrees\Module\ModuleInterface;
use Fisharebest\Webtrees\Place;
use Fisharebest\Webtrees\Services\GedcomService;
use Fisharebest\Webtrees\Services\SearchService;
use Fisharebest\Webtrees\Statistics;
use Vesta\Hook\HookInterfaces\FunctionsPlaceUtils;
use Vesta\Model\MapCoordinates;

//DefaultPlaceWithinHierarchy with better lat/lon resolution
class VestaPlaceWithinHierarchy extends DefaultPlaceWithinHierarchy implements PlaceWithinHierarchy {
    
  protected $module;
  protected $latLonInitialized = false;
  
  public function __construct(
          Place $actual,
          PlaceUrls $urls,
          SearchService $search_service, 
          Statistics $statistics,
          ModuleInterface $module) {
    
    parent::__construct($actual, $urls, $search_service, $statistics);
    $this->module = $module;
  }

  protected function initLatLon(): ?MapCoordinates {
    $ps = $this->placeStructure();
    if ($ps === null) {
      return null;
    }
    return FunctionsPlaceUtils::plac2map($this->module, $ps, false);
  }
  
  public function getLatLon(): ?MapCoordinates {
    if (!$this->latLonInitialized) {
      $this->latLon = $this->initLatLon();
      $this->latLonInitialized = true;
    }
    
    return $this->latLon;
  }
  
  public function latitude(): ?float {
    //we don't go up the hierarchy here - there may be more than one parent!
    
    $lati = null;
    if ($this->getLatLon() !== null) {
      $lati = $this->getLatLon()->getLati();
    }
    if ($lati === null) {
      return null;
    }
    
    $gedcom_service = new GedcomService();
    return $gedcom_service->readLatitude($lati);
  }
  
  public function longitude(): ?float {
    //we don't go up the hierarchy here - there may be more than one parent!
    
    $long = null;
    if ($this->getLatLon() !== null) {
      $long = $this->getLatLon()->getLong();
    }
    if ($long === null) {
      return null;
    }
    
    $gedcom_service = new GedcomService();
    return $gedcom_service->readLongitude($long);
  }
  
  public function getChildPlaces(): array {
    $self = $this;    
    $ret = $this
            ->getChildPlacesCacheIds($this->actual)
            ->mapWithKeys(static function (Place $place) use ($self): array {
              return [$place->id() => new VestaPlaceWithinHierarchy(
                      $place, 
                      $self->urls, 
                      $self->search_service, 
                      $self->statistics, 
                      $self->module)];
            })
            ->toArray();
    
    return $ret;
  }
}
