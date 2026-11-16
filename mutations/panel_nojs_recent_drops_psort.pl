# Keep the grouped A-Z order in the panel's own "Recent" link: without JavaScript it can never be undone.
s/\[criteria::PARAM_OPEN => \$this->areaid, criteria::PARAM_PSORT => null\],/[criteria::PARAM_OPEN => \$this->areaid],/;
