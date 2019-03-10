
# Webtrees 2 Custom Module: ⚶ Vesta Places and Pedigree map

This is a [webtrees](https://www.webtrees.net/) custom module. 
The project’s website is [cissee.de](https://cissee.de). 
The original module is on [github](https://github.com/dkniffin/webtrees-openstreetmap).

## Contents

* [Features](#features)
* [Download](#download)
* [Installation](#installation)
* [License](#license)

### Features<a name="features"/>

* This custom module displays the location of events and the birthplace of ancestors on a map.

* Location data is obtained directly from gedcom data, and may also be provided by other custom modules. 

* If you have collected non-GEDCOM location data via webtrees (Control panel > Map > Geographic data), activate the 'Vesta Webtrees Location Data Provider' custom module to make this data available.

* If you have multiple custom modules providing location data, you can change their priority via the module configuration:

![Screenshot](providers.png)

### Download<a name="download"/>

* Current version: 2.0.0-alpha.5.1
* Based on and tested with webtrees 2.0.0-alpha.5. Cannot be used with webtrees 1.x!
* Requires the Hooks module ('hooks_repackaged').
* Requires the ⚶ Vesta Common module ('vesta_common_lib').
* Download the zipped module, including all related modules, [here](https://cissee.de/vesta.latest.zip).
* Support, suggestions, feature requests: <ric@richard-cissee.de>
* Issues also via <https://github.com/ric2016/openstreetmap_hooked/issues>

### Installation

* Unzip the files and copy them to the modules_v4 folder of your webtrees installation. All related modules are included in the zip file. It's safe to overwrite the respective directories if they already exist (they are bundled with other custom modules as well), as long as other custom models using these dependencies are also upgraded to their respective latest versions.
* Enable the main module via Control Panel -> Modules -> Module Administration -> ⚶ OpenStreetMap.

### License<a name="license"/>

* **vesta_places: a webtrees custom module**
* Copyright (C) 2019 Richard Cissée

* Derived from **webtrees** - Copyright (C) 2010 to 2019 webtrees development team.
* Derived from **openstreetmap** - Copyright (C) 2017 Derek Kniffin. See file 'LICENSE' for additional permission notice.
* Derived from **Leaflet** - Copyright (c) 2010-2017, Vladimir Agafonkin; Copyright (c) 2010-2011, CloudMade. See file 'LICENSE' for additional permission notice.
* Derived from **Leaflet.markercluster** - Copyright (C) 2012 David Leaver. See file 'LICENSE' for additional permission notice.
* French translations provided by Pierre Dousselin.

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
