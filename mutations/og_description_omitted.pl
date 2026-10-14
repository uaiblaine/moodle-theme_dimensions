# Always emit the description tag: a course without a public description then
# ships an empty og:description instead of none.
s/'hasdescription' => \$this->description !== null,/'hasdescription' => true,/;
