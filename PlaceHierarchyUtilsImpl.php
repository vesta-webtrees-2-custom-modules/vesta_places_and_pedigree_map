<?php

namespace Cissee\Webtrees\Module\PPM;

use Cissee\WebtreesExt\Http\Controllers\PlaceHierarchyParticipant;
use Cissee\WebtreesExt\Http\Controllers\PlaceHierarchyUtils;
use Cissee\WebtreesExt\Http\Controllers\PlaceUrls;
use Cissee\WebtreesExt\Http\Controllers\PlaceWithinHierarchy;
use Cissee\WebtreesExt\MoreI18N;
use Fisharebest\Webtrees\Module\ModuleInterface;
use Fisharebest\Webtrees\Place;
use Fisharebest\Webtrees\Services\SearchService;
use Fisharebest\Webtrees\Tree;
use Illuminate\Support\Collection;

class PlaceHierarchyUtilsImpl implements PlaceHierarchyUtils {

    /** @var ModuleInterface */
    protected $module;

    /** @var Collection<PlaceHierarchyParticipant> */
    protected $participants;

    /** @var SearchService */
    protected $search_servicet;

    public function __construct(
        ModuleInterface $module,
        Collection $participants,
        SearchService $search_service) {

        $this->module = $module;
        $this->participants = $participants;
        $this->search_service = $search_service;
    }

    public function getUrlFilters(array $requestParameters): array {
        $participantFilters = [];
        foreach ($this->participants as $participant) {
            $parameterName = $participant->filterParameterName();
            if (array_key_exists($parameterName, $requestParameters)) {
                $parameterValue = intVal($requestParameters[$parameterName]);
                $participantFilters[$parameterName] = $parameterValue;
            }
        }

        return $participantFilters;
    }

    public function findPlace(int $id, Tree $tree, array $requestParameters): PlaceWithinHierarchy {
        $participantFilters = $this->getUrlFilters($requestParameters);
        $urls = new PlaceUrls($this->module, $participantFilters, $this->participants);

        $first = null;
        $others = [];
        $otherParticipants = [];

        foreach ($this->participants as $participant) {

            /** @var PlaceHierarchyParticipant $participant */
            $parameterName = $participant->filterParameterName();
            $parameterValue = -1;
            if (array_key_exists($parameterName, $participantFilters)) {
                $parameterValue = intVal($participantFilters[$parameterName]);
            }

            $asFirst = ($parameterValue === 1) && ($first === null);

            $pwh = $participant->findPlace($id, $tree, $urls, !$asFirst);

            if ($asFirst) {
                //no need to load non-specific!
                $first = $pwh;
                //and no need to keep track of this participant
            } else {
                $otherParticipants[$parameterName] = $participant;
                $others[$parameterName] = $pwh;
            }
        }

        if ($first === null) {
            $actual = Place::find($id, $tree);
            $first = new VestaPlaceWithinHierarchy(
                $actual,
                $urls,
                $this->search_service,
                $this->module);
        }

        return new PlaceWithinHierarchyViaParticipants(
            $urls,
            $first,
            new Collection($others),
            new Collection($otherParticipants),
            new Collection($participantFilters),
            $this->module);
    }

    public function hierarchyActionLabel(): string {
        return MoreI18N::xlate('Show place hierarchy');
    }

    public function listActionLabel(): string {
        return MoreI18N::xlate('Show all places in a list');
    }

    public function pageLabel(): string {
        return MoreI18N::xlate('Place hierarchy');
    }

    public function placeHierarchyView(): string {
        return 'modules/generic-place-hierarchy/place-hierarchy';
    }

    public function eventsView(): string {
        return 'modules/generic-place-hierarchy/events';
    }
    
    public function listView(): string {
        return 'modules/generic-place-hierarchy/list';
    }

    public function pageView(): string {
        return 'modules/generic-place-hierarchy/page';
    }

    public function sidebarView(): string {
        return 'modules/generic-place-hierarchy/sidebar';
    }

}
