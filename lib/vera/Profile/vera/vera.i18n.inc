<?php
/**
 * @file Implements VeraI18N.
 */

class VeraI18N {
  // @todo: Docblock
  public function create_locales($locales) {
    foreach ($locales as $locale) {
      locale_add_language($locale);
    }
  }
}
