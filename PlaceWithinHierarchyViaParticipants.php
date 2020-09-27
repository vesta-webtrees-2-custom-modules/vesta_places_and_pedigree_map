<?php

namespace Cissee\Webtrees\Module\PPM;

use Cissee\WebtreesExt\Http\Controllers\PlaceHierarchyParticipant;
use Cissee\WebtreesExt\Http\Controllers\PlaceUrls;
use Cissee\WebtreesExt\Http\Controllers\PlaceWithinHierarchy;
use Fisharebest\Webtrees\Place;
use Fisharebest\Webtrees\Services\GedcomService;
use Fisharebest\Webtrees\Tree;
use Illuminate\Support\Collection;
use Vesta\Model\MapCoordinates;
use Vesta\Model\PlaceStructure;

class PlaceWithinHierarchyViaParticipants implements PlaceWithinHierarchy {
  
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
    
    $this->urls = $urls;
    $this->first = $first;
    $this->others = $others;
    $this->participants = $participants;
    $this->participantFilters = $participantFilters;
    $this->module = $module;
  }
  
  public function url(): string {
    return $this->first->url();
  }
  
  public function gedcomName(): string {
    return $this->first->gedcomName();
  }
  
  public function placeName(): string {
    return $this->first->placeName();
  }
  
  public function getChildPlaces(): array {
    $firstChildren = $this->first->getChildPlaces();
    
    $otherChildrenArray = [];
    foreach ($this->participants as $participant) {
      /* @var $participant PlaceHierarchyParticipant */
      $parameterName = $participant->filterParameterName();
      
      /* @var $pwh PlaceWithinHierarchy */
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
        
        //we still have to create for each participant
        //because children of this child may exist
        //but it's sufficient to create a placeholder (may be more efficient)
        if ($otherChild === null) {
          //create via participant
          $otherChild = $this->participants->get($parameterName)->
                  createNonMatchingPlace(new Place($finalFirst->gedcomName(), $finalFirst->tree()), $this->urls);
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
    return $ret->unique();
  }
  
  public function countIndividualsInPlace(): int {
    $counts = new Collection();
    $counts->add($this->first->countIndividualsInPlace());
    foreach ($this->others as $other) {
      $counts->add($other->countIndividualsInPlace());
    }
    //this assumes that one participant always has set of individuals for which all others are subsets,
    //i.e. there are no two indiviuals returned only from different sets
    //assumption is dubious in general (in particular if places aren't linked to shared places consistently)
    return $counts->max();
  }
  
  public function searchFamiliesInPlace(): Collection {
    $ret = $this->first->searchFamiliesInPlace();
    foreach ($this->others as $other) {
      $ret = $ret->merge($other->searchFamiliesInPlace());
    }
    return $ret->unique();
  }
  
  public function countFamiliesInPlace(): int {
    $counts = new Collection();
    $counts->add($this->first->countFamiliesInPlace());
    foreach ($this->others as $other) {
      $counts->add($other->countFamiliesInPlace());
    }
    //this assumes that one participant always has set of families for which all others are subsets,
    //i.e. there are no two families returned only from different sets
    //assumption is dubious in general (in particular if places aren't linked to shared places consistently)
    return $counts->max();
  }
  
  protected function initLatLon(): ?MapCoordinates {
    $ret = $this->first->getLatLon();
    if ($ret !== null) {
      return $ret;
    }
    foreach ($this->others as $other) {
      $ret = $other->getLatLon();
      if ($ret !== null) {
        return $ret;
      }
    }
    return null;
  }
  
  public function getLatLon(): ?MapCoordinates {
    if (!$this->latLonInitialized) {
      $this->latLon = $this->initLatLon();
      $this->latLonInitialized = true;
    }
    
    return $this->latLon;
  }
  
  public function latitude(): float {
    //we don't go up the hierarchy here - there may be more than one parent!
    
    $lati = null;
    if ($this->getLatLon() !== null) {
      $lati = $this->getLatLon()->getLati();
    }
    if ($lati === null) {
      return 0.0;
    }
    
    $gedcom_service = new GedcomService();
    return $gedcom_service->readLatitude($lati);
  }
  
  public function longitude(): float {
    //we don't go up the hierarchy here - there may be more than one parent!
    
    $long = null;
    if ($this->getLatLon() !== null) {
      $long = $this->getLatLon()->getLong();
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

    //never zoom in too far (in particular if there is only one place, but also if the places are close together)
    $latiSpread = $latiMax - $latiMin;
    if ($latiSpread < 1) {
      $latiMin -= (1 - $latiSpread)/2;
      $latiMax += (1 - $latiSpread)/2;
    }
    
    $longSpread = $longMax - $longMin;
    if ($longSpread < 1) {
      $longMin -= (1 - $longSpread)/2;
      $longMax += (1 - $longSpread)/2;
    }

    return [[$latiMin, $longMin], [$latiMax, $longMax]];
  }

  public function placeStructure(): ?PlaceStructure {
    //TODO: merge all (more efficient wrt plac2map if _LOC or _GOV is already set)
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
  
  public function parent(): PlaceWithinHierarchy {
    return new PlaceWithinHierarchyViaParticipants(
            $this->urls, 
            $this->first->parent(), 
            $this->others->map(function ($place) {
              return $place->parent();
            }), 
            $this->participants, 
            $this->participantFilters, 
            $this->module);
  }
}
