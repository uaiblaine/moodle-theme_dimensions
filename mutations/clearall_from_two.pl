# Offer "Clear all" for a single criterion, which its own chip or control already undoes.
s/'clearall' => \$this->criteria->narrowing_count\(\) >= 2,/'clearall' => \$this->criteria->narrowing_count() >= 1,/;
