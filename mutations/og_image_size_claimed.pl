# Always claim a size: the fallback then advertises 1200x630 for an original of any size.
s/'hasimagesize' => isset\(\$this->image\['width'\], \$this->image\['height'\]\),/'hasimagesize' => true,/;
