# A trail's page would read its band as a course's: no step's competencies, no step left out.
s/band::courses\(\$this->course, \$this->values, \$trail\)/band::courses(\$this->course, \$this->values, null)/;
