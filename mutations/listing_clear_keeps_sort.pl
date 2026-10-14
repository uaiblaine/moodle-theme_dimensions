# Let the empty state's "clear filters" drop the organisation and the open panel along with the
# filters. The toolbar's own control keeps them (criteria::keep_params()), so the two controls
# carrying one label would take the reader to two different pages - and this one is drawn ONLY when
# nothing matched, which is the one page with no rows on it to show that anything had changed.
s/links::showcase\(\$categoryid, \$this->criteria->keep_params\(\), \$this->result->anonymous\)\n([ \t]*)->out\(false\)/links::showcase(\$categoryid, [], \$this->result->anonymous)\n$1->out(false)/;
