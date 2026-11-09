# Hand the band to the style attribute as it came, unsanitised.
s/\$band = \$this->band === null \? null : branding::hex\(\$this->band\);/\$band = \$this->band;/;
