# A look taken off the list has its cohort deleted, which opens every menu it restricted alone.
s/self::empty_cohort\(\(int\) \$cohort->id\);/cohort_delete_cohort(\$cohort);/;
