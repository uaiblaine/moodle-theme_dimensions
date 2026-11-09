# A course page with a Subcourse activity draws a trail.
s/\$this->values->model === landingfields::MODEL_TRAIL \? trail::of\(\$this->course\) : null/trail::of(\$this->course)/;
