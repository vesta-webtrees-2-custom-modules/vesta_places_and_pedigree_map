<?php

namespace Cissee\Webtrees\Module\PPM\WhatsNew;

use Cissee\WebtreesExt\WhatsNew\WhatsNewInterface;
use Fisharebest\Webtrees\I18N;

class WhatsNew0 implements WhatsNewInterface {
  
  public function getMessage(): string {
    return I18N::translate("Vesta Places and Pedigree map: Now also provides a replacement for the Place hierarchy list, in order to show additional location data.");
  }
}
