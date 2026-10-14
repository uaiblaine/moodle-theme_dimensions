# Print a route for somebody who may walk into the course already.
s/\$hasroute = !\$this->anonymous && !\$mayenter && /\$hasroute = !\$this->anonymous && /;
