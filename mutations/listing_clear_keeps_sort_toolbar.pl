# Let the toolbar's "clear filters" drop the organisation and the open panel along with the filters,
# so the control on the count row and the one in the empty state lead to two different pages.
s/links::showcase\(\$this->categoryid, \$this->criteria->keep_params\(\), \$this->anonymous\);/links::showcase(\$this->categoryid, [], \$this->anonymous);/;
