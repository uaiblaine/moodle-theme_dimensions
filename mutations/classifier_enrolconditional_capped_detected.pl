# Never ask for the grouped count when only enrol_conditional instances carry a cap: a full one reads open.
s/in_array\(\$row->enrol, \['self', 'apply', 'conditional'\], true\)/in_array(\$row->enrol, ['self', 'apply'], true)/;
