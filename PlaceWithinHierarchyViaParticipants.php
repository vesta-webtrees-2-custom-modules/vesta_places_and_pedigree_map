<?php

namespace Cissee\Webtrees\Module\PPM;

use Cissee\WebtreesExt\Http\Controllers\DelegatingPlaceWithinHierarchyBase;
use Cissee\WebtreesExt\Http\Controllers\PlaceUrls;
use Cissee\WebtreesExt\Http\Controllers\PlaceWithinHierarchy;
use Fisharebest\Webtrees\Services\GedcomService;
use Fisharebest\Webtrees\Tree;
use Illuminate\Support\Collection;
use Vesta\Hook\HookInterfaces\FunctionsPlaceUtils;
use Vesta\Model\MapCoordinates;
use Vesta\Model\PlaceStructure;

class PlaceWithinHierarchyViaParticipants extends DelegatingPlaceWithinHierarchyBase implements PlaceWithinHierarchy {

  protected $urls;
          
  protected $first;
  protected $others;

  protected $participants;
  
  //0 exclude, 1 restrict to
  protected $participantFilters;

  protected $module;

  /** @var MapCoordinates|null */
  protected $latLon = null;
  
  protected $latLonInitialized = false;

  //all collections keyed by parameterName
  public function __construct(
          PlaceUrls $urls,
          PlaceWithinHierarchy $first,          
          Collection $others,          
          Collection $participants,
          Collection $participantFilters,
          $module) {
    
    parent::__construct($first);
    $this->urls = $urls;
    $this->first = $first;
    $this->others = $others;
    $this->participants = $participants;
    $this->participantFilters = $participantFilters;
    $this->module = $module;
  }
  
  public function getChildPlaces(): array {
    $firstChildren = $this->first->getChildPlaces();
    
    $otherChildrenArray = [];
    foreach ($this->participants as $participant) {
      $parameterName = $participant->filterParameterName();
      $pwh = $this->others->get($parameterName);      
      $otherChildrenArray[$parameterName] = $pwh->getChildPlaces();
    }

    //either filter, or add others
    $ret = [];
    
    foreach ($firstChildren as $id => $finalFirst) {
      $finalOthers = [];
      $exclude = false;
      foreach ($otherChildrenArray as $parameterName => $otherChildren) {
        $otherChild = null;
        if (array_key_exists($id, $otherChildren)) {
          $otherChild = $otherChildren[$id];
        }
        
        $parameterValue = $this->participantFilters->get($parameterName);
        
        //in other, therefore exclude?
        if (($parameterValue === 0) && ($otherChild !== null)) {
          $exclude = true;
          break;
        }
        
        //not in other, therefore exclude?
        if (($parameterValue === 1) && ($otherChild === null)) {
          $exclude = true;
          break;
        }
        
        if ($otherChild === null) {
          //create via participant
          $otherChild = $this->participants->get($parameterName)->findPlace($id, $this->tree(), $this->urls);
        }
        
        $finalOthers[$parameterName] = $otherChild;
      }
      
      if (!$exclude) {
        $ret[$id] = new PlaceWithinHierarchyViaParticipants($this->urls, $finalFirst, new Collection($finalOthers), $this->participants, $this->participantFilters, $this->module);
      }
    }
    
    return $ret;
  }
  
  public function id(): int {
    return $this->first->id();
  }
  
  public function tree(): Tree {
    return $this->first->tree();
  }
  
  public function fullName(bool $link = false): string {
    return $this->first->fullName($link);
  }
  
  public function searchIndividualsInPlace(): Collection {
    $ret = $this->first->searchIndividualsInPlace();
    foreach ($this->others as $other) {
      $ret = $ret->merge($other->searchIndividualsInPlace());
    }
    return $ret;
  }
  
  public function countIndividualsInPlace(): int {
    $counts = new Collection();
    $counts->add($this->first->countIndividualsInPlace());
    foreach ($this->others as $other) {
      $counts->add($other->countIndividualsInPlace());
    }
    return $counts->max();
  }
  
  public function searchFamiliesInPlace(): Collection {
    $ret = $this->first->searchFamiliesInPlace();
    foreach ($this->others as $other) {
      $ret = $ret->merge($other->searchFamiliesInPlace());
    }
    return $ret;
  }
  
  public function countFamiliesInPlace(): int {
    $counts = new Collection();
    $counts->add($this->first->countFamiliesInPlace());
    foreach ($this->others as $other) {
      $counts->add($other->countFamiliesInPlace());
    }
    return $counts->max();
  }
  
  protected function getLatLon(): ?MapCoordinates {
    $ps = $this->placeStructure();
    if ($ps === null) {
      return null;
    }
    return FunctionsPlaceUtils::plac2map($this->module, $ps, false);
  }
  
  public function latitude(): float {
    if (!$this->latLonInitialized) {
      $this->latLon = $this->getLatLon();
      $this->latLonInitialized = true;
    }
    
    //we don't go up the hierarchy here - there may be more than one parent!
    
    $lati = null;
    if ($this->latLon !== null) {
      $lati = $this->latLon->getLati();
    }
    if ($lati === null) {
      return 0.0;
    }
    
    $gedcom_service = new GedcomService();
    return $gedcom_service->readLatitude($lati);
  }
  
  public function longitude(): float {
    if (!$this->latLonInitialized) {
      $this->latLon = getLatLon();
      $this->latLonInitialized = true;
    }

    //we don't go up the hierarchy here - there may be more than one parent!
    
    $long = null;
    if ($this->latLon !== null) {
      $long = $this->latLon->getLong();
    }
    if ($long === null) {
      return 0.0;
    }
    
    $gedcom_service = new GedcomService();
    return $gedcom_service->readLongitude($long);
  }
  
  public function icon(): string {
    return '';
  }
  
  public function boundingRectangleWithChildren(array $children): array {
    $latitudes = [];
    $longitudes = [];

    if ($this->latitude() !== 0.0) {
      $latitudes[] = $this->latitude();
    }
    if ($this->longitude() !== 0.0) {
      $longitudes[] = $this->longitude();
    }

    foreach ($children as $child) { 
      if ($child->latitude() !== 0.0) {
        $latitudes[] = $child->latitude();
      }
      if ($child->longitude() !== 0.0) {
        $longitudes[] = $child->longitude();
      }
    }

    if ((count($latitudes) === 0) || (count($longitudes) === 0)) {
      return [[-180.0, -90.0], [180.0, 90.0]];
    }

    $latiMin = (new Collection($latitudes))->min();
    $longMin = (new Collection($longitudes))->min();
    $latiMax = (new Collection($latitudes))->max();
    $longMax = (new Collection($longitudes))->max();

    if ($latiMin === $latiMax) {
      $latiMin -= 0.5;
      $latiMax += 0.5;
    }

    if ($longMin === $longMax) {
      $longMin -= 0.5;
      $longMax += 0.5;
    }

    return [[$latiMin, $longMin], [$latiMax, $longMax]];
  }

  public function placeStructure(): ?PlaceStructure {
    //TODO: merge all (more efficient wrt plac2map)
    return $this->first->placeStructure();
  }

  public function additionalLinksHtmlBeforeName(): string {
    $html = $this->first->additionalLinksHtmlBeforeName();
    foreach ($this->others as $other) {
      $html .= $other->additionalLinksHtmlBeforeName();
    }
    return $html;
  }
  
  public function links(): Collection {
    return $this->first->links();
  }
}
