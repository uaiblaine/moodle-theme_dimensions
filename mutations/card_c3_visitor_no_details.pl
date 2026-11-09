# Draw "More information" on a visitor's card again: the dialogue it opens is never armed for a visitor.
s/'hasdetails' => !\$this->anonymous && \$url !== null,/'hasdetails' => \$url !== null,/;
