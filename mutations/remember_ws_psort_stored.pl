# Never remember the grouped A-Z order.
s/remembered::KEY_PSORT => \$psort === criteria::SORT_NAME \? criteria::SORT_NAME : null,/remembered::KEY_PSORT => null,/;
