# Every enrolled user is a row, teachers included: the gradable capability is dropped from the join.
s/self::GRADABLE, \$groups/'', \$groups/;
