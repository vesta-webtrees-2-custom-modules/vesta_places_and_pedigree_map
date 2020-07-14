<?php

namespace Cissee\Webtrees\Module\PPM;

use Cissee\Webtrees\Module\PPM\PlacesAndPedigreeMapModuleExtended;
use Cissee\WebtreesExt\Http\Controllers\PlaceWithinHierarchyBase;
use Fisharebest\Webtrees\Place;

class PlaceWithinHierarchyBaseImpl implements PlaceWithinHierarchyBase {
  
  /** @var Place */
  protected $actual;
          
  /** @var PlacesAndPedigreeMapModuleExtended|null */
  protected $module;
  
  public function __construct(
          Place $actual,
          ?PlacesAndPedigreeMapModuleExtended $module) {
    
    $this->actual = $actual;
    $this->module = $module;
  }
  
  public function url(): string {
    if ($this->module !== null) {
        return $this->module->listUrl($this->actual->tree(), [
            'place_id' => $this->actual->id(),
            'tree'     => $this->actual->tree()->name(),
        ]);
    }

    // The place-list module is disabled...
    return '#';
  }
  
  public function gedcomName(): string {
    return $this->actual->gedcomName();
  }
    
  public function parent(): PlaceWithinHierarchyBase {
    return new PlaceWithinHierarchyBaseImpl($this->actual->parent(), $this->module);
  }
  
  public function placeName(): string {
    return $this->actual->placeName();
  }
}
