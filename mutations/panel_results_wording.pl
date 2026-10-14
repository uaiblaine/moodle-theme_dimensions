# Word a panel's call to action in courses under a criterion: "Go to X, 9 more courses" while the
# subtitle counts results and the area holds nine more RESULTS.
s/self::cta_label\(\$this->name, \$total, \$filtered\)/self::cta_label(\$this->name, \$total, false)/;
